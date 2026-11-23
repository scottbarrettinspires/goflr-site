<?php
// Staff-only agent income. Mirrors the "Agent Compensation" sheet (Compensation tab) and the "FLR Revenue Share" sheet
// (Sponsees, Sponsee Transactions, Quarterly Payouts) onto the server -- the sheets stay the source of truth and are
// re-imported on demand (page upload, comp_api.php?action=import, or tools/import_finance.php). Closed buyer/seller
// sides, sold price and gross commission come from the site's own closed files. Nothing is guessed: a figure that
// can't be worked out from the sheet terms plus the site data shows as unknown.
//
// Import shape: {tabs: {"Compensation": {header:[...], rows:[[...]]}, "Sponsees": {...}, "Sponsee Transactions": {...}, "Quarterly Payouts": {...}}}
// Canonical headers (the importer matches by these names, so extra columns in a pull are harmless):
const FM_TABS = [
    'Compensation' => ['id', 'name', 'status', 'plan', 'type', 'res_agent', 'res_brok', 'txn_fee', 'lux_threshold', 'lux_agent', 'lux_brok', 'comm_agent', 'comm_brok', 'cap', 'cap_paid', 'monthly_fee', 'cap_prorated', 'cap_status'],
    'Sponsees' => ['sponsee_id', 'sponsee', 'sponsor_id', 'sponsor', 'status', 'hired', 'named_in_writing', 'statement_date'],
    'Sponsee Transactions' => ['closing_date', 'sponsee_id', 'sponsee', 'sponsor_id', 'sponsor', 'reference', 'sale_type', 'flr_revenue', 'counted', 'share_pct', 'earned', 'check'],
    'Quarterly Payouts' => ['quarter', 'quarter_end', 'sponsor_id', 'sponsor', 'sponsee_id', 'sponsee', 'revenue', 'earlier', 'room', 'counted', 'sponsee_count', 'share_pct'],
];
require_once __DIR__ . '/arlib.php';
require_once __DIR__ . '/ablib.php';

function fm_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS fin_tabs (tab TEXT PRIMARY KEY, header TEXT NOT NULL, rows TEXT NOT NULL, imported TEXT, imported_by TEXT)');
    return $pdo;
}
// Import a pull. Each tab present REPLACES the stored copy of that tab (a mirror, not a merge); tabs left out are kept.
// Returns ['tabs' => [name => rowcount], ...] or ['error' => '...'] without changing anything.
function fm_import($data, $by = '') {
    if (!is_array($data) || empty($data['tabs']) || !is_array($data['tabs'])) return ['error' => 'Not the expected {tabs:{...}} shape.'];
    $clean = [];
    foreach ($data['tabs'] as $name => $t) {
        if (!isset(FM_TABS[$name])) return ['error' => 'Unknown tab "' . $name . '". Expected: ' . implode(', ', array_keys(FM_TABS)) . '.'];
        if (!is_array($t) || empty($t['header']) || !isset($t['rows']) || !is_array($t['rows'])) return ['error' => 'Tab "' . $name . '" needs header and rows.'];
        $pos = []; foreach (FM_TABS[$name] as $k) { $i = array_search($k, $t['header'], true); if ($i === false) return ['error' => 'Tab "' . $name . '" is missing the column "' . $k . '".']; $pos[$k] = $i; }
        $rows = [];
        foreach ($t['rows'] as $r) { if (!is_array($r)) continue; $o = []; foreach ($pos as $k => $i) $o[$k] = trim((string)($r[$i] ?? '')); if (implode('', $o) !== '') $rows[] = $o; }
        $clean[$name] = $rows;
    }
    $pdo = fm_db(); $pdo->exec('BEGIN IMMEDIATE'); $counts = [];
    try {
        foreach ($clean as $name => $rows) {
            $pdo->prepare('INSERT INTO fin_tabs (tab, header, rows, imported, imported_by) VALUES (?,?,?,?,?) ON CONFLICT(tab) DO UPDATE SET header=excluded.header, rows=excluded.rows, imported=excluded.imported, imported_by=excluded.imported_by')
                ->execute([$name, json_encode(FM_TABS[$name]), json_encode($rows), date('c'), strtolower(trim((string)$by))]);
            $counts[$name] = count($rows);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); return ['error' => 'Import failed, nothing changed: ' . $e->getMessage()]; }
    flr_audit('', 'finance_import', '', json_encode($counts));
    return ['tabs' => $counts];
}
function fm_tab($name) { $q = fm_db()->prepare('SELECT rows FROM fin_tabs WHERE tab = ?'); $q->execute([$name]); $v = $q->fetchColumn(); $r = $v ? json_decode($v, true) : []; return is_array($r) ? $r : []; }
function fm_imported() { $o = []; foreach (fm_db()->query('SELECT tab, imported FROM fin_tabs')->fetchAll() as $r) $o[$r['tab']] = $r['imported']; return $o; }
function fm_money($s) { $s = (string)$s; if (!preg_match('/\d/', $s)) return null; return (float)preg_replace('/[^0-9.]/', '', $s); }
function fm_pct($s) { return preg_match('/^\s*(\d+(\.\d+)?)\s*%\s*$/', (string)$s, $m) ? (float)$m[1] : null; }

// One agent's plan terms as numbers (null where the sheet is blank or isn't a number).
function cp_plan(array $r) {
    $mf = (string)$r['monthly_fee'];
    return ['agent_id' => $r['id'], 'plan' => $r['plan'], 'type' => $r['type'], 'res_agent_pct' => fm_pct($r['res_agent']), 'res_brokerage_pct' => fm_pct($r['res_brok']), 'transaction_fee' => fm_money($r['txn_fee']),
        'luxury_threshold' => fm_money($r['lux_threshold']), 'luxury_agent_pct' => fm_pct($r['lux_agent']), 'luxury_brokerage_pct' => fm_pct($r['lux_brok']), 'commercial_agent_pct' => fm_pct($r['comm_agent']), 'commercial_brokerage_pct' => fm_pct($r['comm_brok']),
        'cap' => fm_money($r['cap']), 'cap_paid_sheet' => fm_money($r['cap_paid']), 'cap_this_year_sheet' => fm_money($r['cap_prorated']), 'cap_status' => $r['cap_status'],
        'monthly_fee' => stripos($mf, 'annual') !== false ? null : fm_money($mf), 'fee_text' => $mf, 'fee_is_annual' => stripos($mf, 'annual') !== false];
}
// Brokerage dollar on one closed RESIDENTIAL side under the plan. Returns null when it can't be worked out.
//   Flat fee plans: the transaction fee, unless the price is at/above the luxury threshold and a luxury brokerage % is set.
//   Percentage plans: brokerage % of gross commission (luxury % at/above the threshold).
// The $325 admin fee, referral fees, mentorship adjustments and commercial deals are not included.
function cp_company_dollar(array $p, $price, $gci) {
    $lux = $p['luxury_threshold'] !== null && $p['luxury_threshold'] > 0 && $price > 0 && $price >= $p['luxury_threshold'] && $p['luxury_brokerage_pct'] !== null;
    if ($p['type'] === 'Flat fee') {
        if ($lux) return $gci > 0 ? round($gci * $p['luxury_brokerage_pct'] / 100, 2) : null;
        if ($p['transaction_fee'] === null) return null;
        if ($p['luxury_threshold'] > 0 && $price <= 0) return null; // can't tell which side of the threshold
        return (float)$p['transaction_fee'];
    }
    if ($p['type'] === 'Percentage split') {
        if ($gci <= 0) return null;
        $b = $lux ? $p['luxury_brokerage_pct'] : $p['res_brokerage_pct'];
        return $b === null ? null : round($gci * $b / 100, 2);
    }
    return null;
}
// Closed sides for one agent email from the site's closed files.
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
// Add 'company' to each row (oldest first), limiting the running total per calendar year to the plan's annual cap.
function cp_with_company(array $rows, array $p) {
    $paid = [];
    foreach ($rows as $i => $r) {
        $rows[$i]['company'] = null; if ($r['closed'] === '') continue;
        $c = cp_company_dollar($p, $r['price'], $r['gci']); if ($c === null) continue;
        $y = substr($r['closed'], 0, 4); $already = $paid[$y] ?? 0.0;
        if ($p['cap'] !== null && $p['cap'] > 0) $c = max(0.0, min($c, $p['cap'] - $already));
        $paid[$y] = $already + $c; $rows[$i]['company'] = round($c, 2);
    }
    return $rows;
}
function cp_in_range($d, $from, $to) { if ($d === '') return $from === '0000-01-01'; return $d >= $from && $d <= $to; }
function cp_months($from, $to) { return max(0, ((int)substr($to, 0, 4) - (int)substr($from, 0, 4)) * 12 + (int)substr($to, 5, 2) - (int)substr($from, 5, 2) + 1); }

// Everything staff see, for the period (month | quarter | ytd | YYYY | all). $status '' = every status.
function cp_overview($period = 'ytd', $status = 'Active') {
    if ($period === 'all') { $from = '0000-01-01'; $to = '9999-12-31'; } else { [$from, $to] = ab_period_range($period); }
    $comp = fm_tab('Compensation'); $sponsees = fm_tab('Sponsees'); $stx = fm_tab('Sponsee Transactions');
    $records = []; foreach (ar_all() as $a) $records[$a['agent_id']] = $a;
    $names = []; foreach ($comp as $r) $names[$r['id']] = $r['name'];
    $sponsorOf = []; $sponsoring = []; foreach ($sponsees as $s) { $sponsorOf[$s['sponsee_id']] = $s['sponsor_id']; $sponsoring[$s['sponsor_id']][] = $s; $names[$s['sponsee_id']] = $names[$s['sponsee_id']] ?? $s['sponsee']; $names[$s['sponsor_id']] = $names[$s['sponsor_id']] ?? $s['sponsor']; }
    $earnedBy = []; // sponsor => sponsee => [sides, earned] for the period, from the Revenue Share sheet's per-closing results
    foreach ($stx as $t) { if (!cp_in_range($t['closing_date'], $from, $to)) continue; $e = fm_money($t['earned']);
        $b = &$earnedBy[$t['sponsor_id']][$t['sponsee_id']]; $b = $b ?? ['sides' => 0, 'earned' => 0.0, 'unprocessed' => 0]; $b['sides']++; if ($e === null) $b['unprocessed']++; else $b['earned'] += $e; unset($b); }
    $out = []; $tot = ['buyer_sides' => 0, 'seller_sides' => 0, 'volume' => 0, 'gci' => 0, 'company' => 0.0, 'fees' => 0.0, 'revshare' => 0.0, 'cap_paid_sheet' => 0.0];
    foreach ($comp as $r) {
        if ($status !== '' && strtolower($r['status']) !== strtolower($status)) continue;
        $id = $r['id']; $p = cp_plan($r); $rec = $records[$id] ?? null; $email = $rec ? (string)$rec['email'] : '';
        $rows = $email === '' ? [] : cp_with_company(cp_closed_rows($email), $p);
        $in = array_values(array_filter($rows, function ($x) use ($from, $to) { return cp_in_range($x['closed'], $from, $to); }));
        $b = count(array_filter($in, function ($x) { return $x['side'] === 'buyer'; })); $s = count($in) - $b;
        $sum = function ($k) use ($in) { return array_sum(array_map(function ($x) use ($k) { return (float)($x[$k] ?? 0); }, $in)); };
        $unknown = count(array_filter($in, function ($x) { return $x['company'] === null; }));
        $fees = ($p['monthly_fee'] === null || $period === 'all') ? null : $p['monthly_fee'] * cp_months($from, $to);
        $spon = []; $rev = 0.0;
        foreach (($sponsoring[$id] ?? []) as $s2) { $e = $earnedBy[$id][$s2['sponsee_id']] ?? ['sides' => 0, 'earned' => 0.0, 'unprocessed' => 0]; $rev += $e['earned'];
            $spon[] = ['agent_id' => $s2['sponsee_id'], 'name' => $names[$s2['sponsee_id']] ?? $s2['sponsee'], 'status' => $s2['status'], 'hired' => $s2['hired'], 'named_in_writing' => $s2['named_in_writing'], 'closed_sides' => $e['sides'], 'not_processed' => $e['unprocessed'], 'revshare_income' => round($e['earned'], 2)]; }
        $sp = $sponsorOf[$id] ?? '';
        $out[] = ['agent_id' => $id, 'name' => $names[$id], 'status' => $r['status'], 'email' => $email, 'has_agent_record' => $rec !== null, 'plan' => $p, 'sponsor' => $sp === '' ? null : ['agent_id' => $sp, 'name' => $names[$sp] ?? $sp],
            'buyer_sides' => $b, 'seller_sides' => $s, 'volume' => (int)$sum('price'), 'gci' => (int)$sum('gci'), 'sides_unknown_income' => $unknown,
            'company_income' => $in ? round($sum('company'), 2) : ($email === '' ? null : 0.0), 'benefits_fees' => $fees === null ? null : round($fees, 2),
            'sponsoring' => $spon, 'revshare_income' => round($rev, 2),
            'closed' => array_map(function ($x) { return ['folder' => $x['folder'], 'side' => $x['side'], 'name' => $x['name'], 'closed' => $x['closed'], 'price' => $x['price'], 'gci' => $x['gci'], 'company' => $x['company']]; }, array_reverse($in))];
        $tot['buyer_sides'] += $b; $tot['seller_sides'] += $s; $tot['volume'] += (int)$sum('price'); $tot['gci'] += (int)$sum('gci'); $tot['company'] += $sum('company'); $tot['fees'] += (float)$fees; $tot['revshare'] += $rev; $tot['cap_paid_sheet'] += (float)$p['cap_paid_sheet'];
    }
    usort($out, function ($a, $b) { return [$b['buyer_sides'] + $b['seller_sides'], $a['name']] <=> [$a['buyer_sides'] + $a['seller_sides'], $b['name']]; });
    return ['ok' => true, 'period' => $period, 'from' => $from, 'to' => $to, 'imported' => fm_imported(), 'agents' => $out, 'totals' => $tot,
        'notes' => ['Plan terms, sponsors and revenue share come from the Agent Compensation and FLR Revenue Share sheets as of the import time shown; the sheets stay the source of truth.',
            'Closed sides, price and gross commission come from the closed files on this site (the commission the agent entered per file). Closed files need an Agent Record with an email to be matched to an agent.',
            'Brokerage income = what the plan terms give the brokerage on each closed residential side (flat transaction fee, or brokerage % of gross commission; luxury terms at or above the threshold), limited to the yearly cap. It excludes the $325 admin fee, referral fees, mentorship adjustments and commercial deals, and does not include sales closed before the site tracked them. "Cap paid (sheet)" is the sheet\'s own running figure for this year.',
            'Revenue share is read from each closing on the Sponsee Transactions tab (already limited by the sponsee\'s cap and tier); closings the office has not entered there yet are not counted.']];
}
