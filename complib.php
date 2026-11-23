<?php
// Staff-only agent income: per-agent compensation plan (entered by staff, kept on the server) combined with the
// agent's closed sides (buyer/seller) from the transactions table. Nothing here is guessed: brokerage income is only
// computed when a plan split has been entered, and revenue share only when a sponsor and a percent have been entered.
//
// Plan fields (table agent_comp, keyed by Agent ID from Agent Records):
//   plan_name            free text, e.g. the name of the commission plan
//   agent_split_pct      % of gross commission the AGENT keeps (the rest is the brokerage's "company dollar")
//   cap_amount           yearly cap on company dollar in dollars (0 = no cap)
//   monthly_fee          monthly benefits fee in dollars
//   sponsor_agent_id     Agent ID of the agent who sponsored this agent ('' = none)
//   revshare_pct         % of THIS agent's company dollar paid to the sponsor as revenue share
//   notes
require_once __DIR__ . '/arlib.php';
require_once __DIR__ . '/ablib.php';

function cp_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS agent_comp (agent_id TEXT PRIMARY KEY, plan_name TEXT, agent_split_pct REAL, cap_amount INTEGER, monthly_fee REAL, sponsor_agent_id TEXT, revshare_pct REAL, notes TEXT, updated TEXT, updated_by TEXT)');
    return $pdo;
}
function cp_get($agentId) {
    $q = cp_db()->prepare('SELECT * FROM agent_comp WHERE agent_id = ?'); $q->execute([$agentId]);
    $r = $q->fetch() ?: [];
    return ['agent_id' => $agentId, 'plan_name' => (string)($r['plan_name'] ?? ''), 'agent_split_pct' => isset($r['agent_split_pct']) && $r['agent_split_pct'] !== null ? (float)$r['agent_split_pct'] : null,
        'cap_amount' => (int)($r['cap_amount'] ?? 0), 'monthly_fee' => isset($r['monthly_fee']) && $r['monthly_fee'] !== null ? (float)$r['monthly_fee'] : null,
        'sponsor_agent_id' => (string)($r['sponsor_agent_id'] ?? ''), 'revshare_pct' => isset($r['revshare_pct']) && $r['revshare_pct'] !== null ? (float)$r['revshare_pct'] : null,
        'notes' => (string)($r['notes'] ?? ''), 'updated' => (string)($r['updated'] ?? ''), 'updated_by' => (string)($r['updated_by'] ?? ''), 'saved' => (bool)$r];
}
function cp_num($v, $min, $max, $label, &$err) {
    $v = trim((string)$v); if ($v === '') return null;
    $n = preg_replace('/[^0-9.]/', '', $v);
    if ($n === '' || (float)$n < $min || (float)$n > $max) { $err = $label . ' must be a number from ' . $min . ' to ' . $max . '.'; return null; }
    return (float)$n;
}
// Save one agent's plan. $in keys as above (strings). Returns '' or a human-readable error.
function cp_save($agentId, array $in, $by = '') {
    if (!ar_get($agentId)) return 'No agent record with that Agent ID.';
    $err = '';
    $split = cp_num($in['agent_split_pct'] ?? '', 0, 100, 'Agent split %', $err); if ($err) return $err;
    $cap = cp_num($in['cap_amount'] ?? '', 0, 10000000, 'Yearly cap', $err); if ($err) return $err;
    $fee = cp_num($in['monthly_fee'] ?? '', 0, 100000, 'Monthly benefits fee', $err); if ($err) return $err;
    $rs = cp_num($in['revshare_pct'] ?? '', 0, 100, 'Revenue share %', $err); if ($err) return $err;
    $sp = trim((string)($in['sponsor_agent_id'] ?? ''));
    if ($sp !== '') {
        if ($sp === $agentId) return 'An agent cannot sponsor themselves.';
        if (!ar_get($sp)) return 'The sponsor Agent ID ' . $sp . ' was not found in Agent Records.';
        $seen = [$agentId]; $cur = $sp; // no loops: walking up from the sponsor must never come back to this agent
        while ($cur !== '' && count($seen) < 50) { if (in_array($cur, $seen, true)) return 'That sponsor would create a loop (the sponsor is already sponsored by this agent).'; $seen[] = $cur; $cur = cp_get($cur)['sponsor_agent_id']; }
    }
    cp_db()->prepare('INSERT INTO agent_comp (agent_id, plan_name, agent_split_pct, cap_amount, monthly_fee, sponsor_agent_id, revshare_pct, notes, updated, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?)
        ON CONFLICT(agent_id) DO UPDATE SET plan_name=excluded.plan_name, agent_split_pct=excluded.agent_split_pct, cap_amount=excluded.cap_amount, monthly_fee=excluded.monthly_fee, sponsor_agent_id=excluded.sponsor_agent_id, revshare_pct=excluded.revshare_pct, notes=excluded.notes, updated=excluded.updated, updated_by=excluded.updated_by')
        ->execute([$agentId, mb_substr(trim((string)($in['plan_name'] ?? '')), 0, 120), $split, $cap === null ? 0 : (int)$cap, $fee, $sp, $rs, mb_substr(trim((string)($in['notes'] ?? '')), 0, 1000), date('c'), strtolower(trim((string)$by))]);
    $r = ar_get($agentId);
    flr_audit(flr_akey($r['email']), 'comp_plan', $agentId, 'Compensation plan saved: split ' . ($split ?? '-') . '%, cap ' . (int)$cap . ', fee ' . ($fee ?? '-') . ', sponsor ' . ($sp ?: 'none') . ', rev share ' . ($rs ?? '-') . '%');
    return '';
}

// Closed sides for one agent email: rows {folder, side, name, closed (YYYY-MM-DD), price, gci}. Records marked cancelled/expired are left out.
function cp_closed_rows($email) {
    $out = [];
    foreach (flr_tx_live($email) as $t) {
        if (!in_array($t['side'], ['buyer', 'seller'], true) || ab_pk($t) !== 'closed') continue;
        $price = ab_price($t['folder']); $gci = ab_gci(['comm' => ab_comm($t['folder']), 'price' => $price]);
        $out[] = ['folder' => $t['folder'], 'side' => $t['side'], 'name' => (string)$t['name'], 'closed' => ab_closed_date($t['folder'], $t['side']), 'price' => $price, 'gci' => $gci];
    }
    usort($out, function ($a, $b) { return strcmp($a['closed'], $b['closed']) ?: strcmp($a['folder'], $b['folder']); });
    return $out;
}
// Add 'company' (brokerage dollar) to each row, oldest first, applying the yearly cap by closing year. Null company when no
// split is entered or the row has no commission.
function cp_with_company(array $rows, $comp) {
    $byYear = [];
    foreach ($rows as $i => $r) {
        $rows[$i]['company'] = null;
        if ($comp['agent_split_pct'] === null || $r['gci'] <= 0 || $r['closed'] === '') continue;
        $raw = $r['gci'] * (100 - $comp['agent_split_pct']) / 100; $y = substr($r['closed'], 0, 4);
        $paid = $byYear[$y] ?? 0.0;
        $co = $comp['cap_amount'] > 0 ? max(0.0, min($raw, $comp['cap_amount'] - $paid)) : $raw;
        $byYear[$y] = $paid + $co; $rows[$i]['company'] = round($co, 2);
    }
    return $rows;
}
function cp_in_range($d, $from, $to) { if ($d === '') return $from === '0000-01-01'; /* undated files count only in All time */ return $d >= $from && $d <= $to; }
function cp_months($from, $to) { // whole months touched by the range, for the benefits fee total
    return max(0, ((int)substr($to, 0, 4) - (int)substr($from, 0, 4)) * 12 + (int)substr($to, 5, 2) - (int)substr($from, 5, 2) + 1);
}

// Everything staff see, for the period (month | quarter | ytd | YYYY | all). Returns {ok, period, from, to, agents:[...], totals:{...}}.
function cp_overview($period = 'ytd', $status = 'Active') {
    if ($period === 'all') { $from = '0000-01-01'; $to = '9999-12-31'; } else { [$from, $to] = ab_period_range($period); }
    $agents = ar_all(); $comp = []; $rows = []; $sponsored = [];
    foreach ($agents as $a) {
        $id = $a['agent_id']; $comp[$id] = cp_get($id);
        $rows[$id] = $a['email'] === '' ? [] : cp_with_company(cp_closed_rows($a['email']), $comp[$id]);
        if ($comp[$id]['sponsor_agent_id'] !== '') $sponsored[$comp[$id]['sponsor_agent_id']][] = $id;
    }
    $names = []; foreach ($agents as $a) $names[$a['agent_id']] = $a['preferred_name'] ?: $a['full_name'];
    $out = []; $tot = ['buyer_sides' => 0, 'seller_sides' => 0, 'volume' => 0, 'gci' => 0, 'company' => 0.0, 'fees' => 0.0, 'revshare' => 0.0];
    foreach ($agents as $a) {
        if ($status !== '' && strtolower($a['status']) !== strtolower($status)) continue;
        $id = $a['agent_id']; $c = $comp[$id];
        $in = array_values(array_filter($rows[$id], function ($r) use ($from, $to) { return cp_in_range($r['closed'], $from, $to); }));
        $b = array_values(array_filter($in, function ($r) { return $r['side'] === 'buyer'; })); $s = array_values(array_filter($in, function ($r) { return $r['side'] === 'seller'; }));
        $sum = function ($rs, $k) { return array_sum(array_map(function ($r) use ($k) { return (float)($r[$k] ?? 0); }, $rs)); };
        $missing = count(array_filter($in, function ($r) { return $r['gci'] <= 0; }));
        $company = $c['agent_split_pct'] === null ? null : $sum($in, 'company');
        $fees = ($c['monthly_fee'] === null || $period === 'all') ? null : $c['monthly_fee'] * cp_months($from, $to);
        $spon = []; $rev = 0.0; $revKnown = true;
        foreach (($sponsored[$id] ?? []) as $sid) {
            $sc = $comp[$sid]; $sin = array_filter($rows[$sid], function ($r) use ($from, $to) { return cp_in_range($r['closed'], $from, $to); });
            $amt = ($sc['revshare_pct'] === null || $sc['agent_split_pct'] === null) ? null : $sum($sin, 'company') * $sc['revshare_pct'] / 100;
            if ($amt === null) $revKnown = false; else $rev += $amt;
            $spon[] = ['agent_id' => $sid, 'name' => $names[$sid] ?? $sid, 'status' => ar_get($sid)['status'] ?? '', 'closed_sides' => count($sin), 'revshare_pct' => $sc['revshare_pct'], 'revshare_income' => $amt === null ? null : round($amt, 2)];
        }
        $sp = $c['sponsor_agent_id'];
        $out[] = ['agent_id' => $id, 'name' => $names[$id], 'status' => $a['status'], 'email' => $a['email'], 'date_hired' => $a['fields']['date_hired'] ?? '', 'office' => $a['fields']['office'] ?? '',
            'plan' => $c, 'sponsor' => $sp === '' ? null : ['agent_id' => $sp, 'name' => $names[$sp] ?? $sp],
            'buyer_sides' => count($b), 'seller_sides' => count($s), 'volume' => (int)$sum($in, 'price'), 'gci' => (int)$sum($in, 'gci'), 'sides_missing_commission' => $missing,
            'company_income' => $company === null ? null : round($company, 2), 'benefits_fees' => $fees === null ? null : round($fees, 2),
            'sponsoring' => $spon, 'revshare_income' => $spon ? round($rev, 2) : 0.0, 'revshare_complete' => $revKnown,
            'closed' => array_map(function ($r) { return ['folder' => $r['folder'], 'side' => $r['side'], 'name' => $r['name'], 'closed' => $r['closed'], 'price' => $r['price'], 'gci' => $r['gci'], 'company' => $r['company']]; }, array_reverse($in))];
        $tot['buyer_sides'] += count($b); $tot['seller_sides'] += count($s); $tot['volume'] += (int)$sum($in, 'price'); $tot['gci'] += (int)$sum($in, 'gci');
        $tot['company'] += (float)$company; $tot['fees'] += (float)$fees; $tot['revshare'] += $rev;
    }
    usort($out, function ($a, $b) { return [$b['buyer_sides'] + $b['seller_sides'], $a['name']] <=> [$a['buyer_sides'] + $a['seller_sides'], $b['name']]; });
    return ['ok' => true, 'period' => $period, 'from' => $from, 'to' => $to, 'agents' => $out, 'totals' => $tot,
        'notes' => ['Company income = gross commission x (100 - agent split %), capped per calendar year when a cap is entered. Shown only for agents with a split entered.',
            'Revenue share = the sponsored agent\'s company income x the revenue share % entered on the sponsored agent. Shown as incomplete when a sponsored agent has no split or % yet.',
            'Gross commission comes from the commission the agent entered on each closed file; sides without one are counted but add $0 (see sides_missing_commission).']];
}
