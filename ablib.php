<?php
// Active Business client rows (shared by Active Business and Performance): one row per buyer/seller record, styled like
// the Leads & Contacts list. ab_rows() returns plain data; ab_row() renders one row.
require_once __DIR__ . '/dlib.php';
require_once __DIR__ . '/pstage.php';
// Client rows for one side (used by the Buyers/Sellers tabs and the Overview's Active Clients card).
function ab_rows($me, $side, $list, $pk) {
    $out = [];
    require_once __DIR__ . '/notiflib.php'; require_once __DIR__ . '/leadlib.php';
    $infos = []; try { $infos = nt_infos($me, $list, true); } catch (Throwable $e) {} // client contact details from the record (cached 10 min)
    $leads = []; try { foreach (ld_load($me)['leads'] as $L) foreach (['buyer', 'seller'] as $r) if (!empty($L['roles'][$r]['folder'])) $leads[$L['roles'][$r]['folder']] = $L; } catch (Throwable $e) {}
    $keys = ps_keys($side); $p = $side === 'buyer' ? 'b' : 's';
    foreach ($list as $t) {
        $k = $pk($t); $in = $infos[$t['folder']] ?? []; $L = $leads[$t['folder']] ?? null;
        $nm = trim((string)($in[$p . '1_name'] ?? '')) ?: ($L ? trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? '')) : '') ?: (string)$t['name'];
        if (trim((string)($in[$p . '2_name'] ?? '')) !== '') $nm .= ' & ' . trim($in[$p . '2_name']);
        $ph = trim((string)($in[$p . '1_phone'] ?? '')) ?: (string)($L['phone'] ?? '');
        $em = trim((string)($in[$p . '1_email'] ?? '')) ?: (string)($L['email'] ?? '');
        $next = null; $c = dl_get($t['folder']); if ($c) foreach (dl_compute(is_array($c["data"]) ? $c["data"] : (json_decode((string)$c["data"], true) ?: [])) as $d) { if (!$d['done']) { $next = $d; break; } }
        $out[] = ['t' => $t, 'k' => $k, 'price' => ab_price($t['folder'], $in), 'comm' => ab_comm($t['folder']), 'i' => array_search($k, $keys, true), 'nm' => $nm, 'ph' => $ph, 'em' => $em, 'src' => (string)($L['source'] ?? ''), 'next' => $next,
            'addr' => trim((string)($in['property'] ?? '')) ?: ($nm !== $t['name'] ? (string)$t['name'] : '')];
    }
    usort($out, function ($a, $b) { return [$a['i'], $a['nm']] <=> [$b['i'], $b['nm']]; });
    return $out;
}
function ab_row($r, $side, $extra = '', $tagOverride = null) {
    $h = function ($x) { return htmlspecialchars((string)$x); };
    $url = ($side === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . urlencode($r['t']['folder']);
    $meta = [PS_STAGES[$side][$r['k']] ?? '']; if ($r['addr'] !== '') $meta[] = $r['addr']; if ($r['src'] !== '') $meta[] = $r['src']; if ($r['ph'] !== '') $meta[] = $r['ph'];
    if ($r['k'] === 'closed') $tag = '<span class="due" style="background:#e3f4e8;color:#2e7d32">' . (!empty($r['closed']) ? $h('Closed ' . date('M j, Y', strtotime($r['closed']))) : 'Closed') . '</span>';
    elseif ($r['next']) { $d = $r['next']; $cl = $d['left'] < 0 ? 'over' : ($d['left'] <= 3 ? 'today' : 'later'); $tag = '<span class="due ' . $cl . '">' . $h($d['label'] . ' · ' . dl_when($d['left'])) . '</span>'; }
    else $tag = '<span class="due later">No deadlines entered</span>';
    if ($tagOverride !== null) $tag = $tagOverride;
    $dig = preg_replace('/[^0-9+]/', '', $r['ph']);
    $qa = '<span class="qa">' . ($dig !== '' ? '<a href="tel:' . $h($dig) . '" title="Call">&#9742;</a><a href="sms:' . $h($dig) . '" title="Text"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg></a>' : '')
        . ($r['em'] !== '' ? '<a href="mailto:' . $h($r['em']) . '" title="Email">&#9993;</a>' : '') . '</span>';
    return '<div class="lrow crow" onclick="if(!event.target.closest(\'.qa\'))location.href=\'' . $h($url) . '\'"><span class="dot" style="background:' . $h(ps_color($side, $r['k'])) . '"></span><div class="who"><a class="nm" href="' . $h($url) . '">' . $h($r['nm']) . '</a><div class="meta">' . $h(implode(' · ', $meta)) . '</div></div>' . $extra . $tag . $qa . '</div>';
}
// Stage key for a record (the pipeline stage saved on the site, else the Drive folder's stage).
function ab_pk($t) { return ps_get($t['folder'], $t['side'], (int)$t['stage']); }
// Closed records for one agent, both sides: ['buyer' => rows, 'seller' => rows], newest first by name order of stage.
// The date a closed file closed: the Closing date from its Contract Deadlines, else the day its pipeline stage was set
// to the closed stage (from the audit log). '' when neither is known.
function ab_closed_date($folder, $side) {
    $c = dl_get($folder);
    if ($c) foreach (dl_compute(is_array($c['data']) ? $c['data'] : (json_decode((string)$c['data'], true) ?: [])) as $d) { if ($d['key'] === 'closing') return $d['nextbiz'] ?: $d['date']; }
    try { $q = flr_db()->prepare("SELECT at FROM audit WHERE action = 'pstage' AND ref = ? AND detail = ? ORDER BY at DESC LIMIT 1");
        $q->execute([$folder, 'Pipeline stage set to ' . (PS_STAGES[$side]['closed'] ?? '')]); $at = $q->fetchColumn();
        if ($at) return date('Y-m-d', strtotime($at)); } catch (Throwable $e) {}
    return '';
}
// Date range for a Performance period: month | quarter | ytd | a 4-digit year (whole calendar periods). Returns [from, to] as YYYY-MM-DD.
function ab_period_range($period, $today = null) {
    $t = $today ?: date('Y-m-d'); $y = (int)substr($t, 0, 4); $m = (int)substr($t, 5, 2);
    if ($period === 'month') return [sprintf('%04d-%02d-01', $y, $m), date('Y-m-t', strtotime($t))];
    if ($period === 'quarter') { $qm = intdiv($m - 1, 3) * 3 + 1; return [sprintf('%04d-%02d-01', $y, $qm), date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $y, $qm + 2)))]; }
    if (preg_match('/^\d{4}$/', (string)$period)) return [$period . '-01-01', $period . '-12-31'];
    return [$y . '-01-01', $y . '-12-31']; // ytd: the whole current year, so a file marked closed ahead of its closing date still counts
}
// Closed records for one agent, both sides, each row with its 'closed' date, newest first:
// ['buyer' => rows, 'seller' => rows]. With $period set, only files that closed in that period (undated files are left out).
function ab_closed($me, $period = null) {
    $all = flr_tx_live($me); $out = [];
    $rg = $period === null ? null : ab_period_range($period);
    foreach (['buyer', 'seller'] as $side) {
        $list = array_values(array_filter($all, function ($t) use ($side) { return $t['side'] === $side && ab_pk($t) === 'closed'; }));
        $rows = ab_rows($me, $side, $list, 'ab_pk');
        foreach ($rows as &$r) $r['closed'] = ab_closed_date($r['t']['folder'], $side); unset($r);
        if ($rg) $rows = array_values(array_filter($rows, function ($r) use ($rg) { return $r['closed'] !== '' && $r['closed'] >= $rg[0] && $r['closed'] <= $rg[1]; }));
        usort($rows, function ($a, $b) { return strcmp($b['closed'], $a['closed']) ?: strcmp($a['nm'], $b['nm']); });
        $out[$side] = $rows;
    }
    return $out;
}
// Sold price: saved on the site (transactions.sold_price), else the purchase price the Contract Forms Wizard put in the
// record's info. Whole dollars; 0 when unknown.
function ab_price_column() {
    static $done = false; if ($done) return; $done = true; $pdo = flr_db();
    foreach ($pdo->query('PRAGMA table_info(transactions)')->fetchAll() as $c) { if ($c['name'] === 'sold_price') return; }
    $pdo->exec('ALTER TABLE transactions ADD COLUMN sold_price INTEGER');
}
function ab_price($folder, array $info = []) {
    ab_price_column();
    $q = flr_db()->prepare('SELECT sold_price FROM transactions WHERE folder = ?'); $q->execute([$folder]); $v = $q->fetchColumn();
    if ($v !== false && $v !== null && (int)$v > 0) return (int)$v;
    $p = preg_replace('/[^0-9.]/', '', (string)($info['price'] ?? '')); return $p === '' ? 0 : (int)round((float)$p);
}
// Save the sold price for one of the agent's own records. Returns '' or a human-readable error.
function ab_set_price($me, $folder, $price) {
    $ok = false; foreach (flr_tx_list($me) as $t) { if ($t['folder'] === $folder) $ok = true; }
    if (!$ok) return 'That record was not found in your business.';
    $n = preg_replace('/[^0-9.]/', '', (string)$price);
    if ($n === '' || (float)$n < 1000 || (float)$n > 500000000) return 'Please enter the sold price in dollars, for example 425000.';
    ab_price_column();
    flr_db()->prepare('UPDATE transactions SET sold_price = ? WHERE folder = ?')->execute([(int)round((float)$n), $folder]);
    flr_audit(flr_akey($me), 'soldprice', $folder, 'Sold price set to $' . number_format((int)round((float)$n)));
    return '';
}
// Totals for a set of closed rows. Volume credit is per side: each closed buyer or seller record counts its full sold
// price, so a property the agent represented on both sides counts twice.
function ab_totals(array $buyers, array $sellers) {
    $sum = function ($rows) { return array_sum(array_map(function ($r) { return (int)($r['price'] ?? 0); }, $rows)); };
    $g = function ($rows) { return array_sum(array_map('ab_gci', $rows)); };
    $missG = count(array_filter(array_merge($buyers, $sellers), function ($r) { return ab_gci($r) === 0; }));
    $miss = count(array_filter(array_merge($buyers, $sellers), function ($r) { return empty($r['price']); }));
    return ['buyer_sides' => count($buyers), 'seller_sides' => count($sellers), 'sides' => count($buyers) + count($sellers),
        'buyer_volume' => $sum($buyers), 'seller_volume' => $sum($sellers), 'volume' => $sum($buyers) + $sum($sellers), 'missing_price' => $miss,
        'buyer_gci' => $g($buyers), 'seller_gci' => $g($sellers), 'gci' => $g($buyers) + $g($sellers), 'missing_gci' => $missG];
}
// Stages that count as a pending sale (under contract, not yet closed). Same keys on both sides.
const AB_PENDING = ['contract', 'contingencies', 'closingready'];
// Expected closing date of a file: the Closing date in its Contract Deadlines ('' if not entered).
function ab_expected_close($folder) {
    $c = dl_get($folder);
    if ($c) foreach (dl_compute(is_array($c['data']) ? $c['data'] : (json_decode((string)$c['data'], true) ?: [])) as $d) { if ($d['key'] === 'closing') return $d['nextbiz'] ?: $d['date']; }
    return '';
}
// Active (not closed) records for one agent: ['buyer' => rows, 'seller' => rows]; each row adds 'pending' (under
// contract) and 'close' (expected closing date). Pending rows first, soonest closing first.
function ab_active($me) {
    $all = flr_tx_live($me); $out = [];
    foreach (['buyer', 'seller'] as $side) {
        $list = array_values(array_filter($all, function ($t) use ($side) { return $t['side'] === $side && ab_pk($t) !== 'closed'; }));
        $rows = ab_rows($me, $side, $list, 'ab_pk');
        foreach ($rows as &$r) { $r['pending'] = in_array($r['k'], AB_PENDING, true); $r['close'] = $r['pending'] ? ab_expected_close($r['t']['folder']) : ''; } unset($r);
        $out[$side] = $rows;
    }
    return $out;
}
// Pipeline totals: active buyers/sellers, pending sides, and anticipated $ volume (each pending side counts its full
// contract price, the same per-side credit as closed volume).
function ab_active_totals(array $act) {
    $pb = array_values(array_filter($act['buyer'], function ($r) { return $r['pending']; }));
    $ps = array_values(array_filter($act['seller'], function ($r) { return $r['pending']; }));
    $t = ab_totals($pb, $ps);
    return ['active_buyers' => count($act['buyer']), 'active_sellers' => count($act['seller']), 'pending_buyer_sides' => count($pb), 'pending_seller_sides' => count($ps),
        'pending' => count($pb) + count($ps), 'anticipated_volume' => $t['volume'], 'anticipated_gci' => $t['gci'], 'missing_gci' => $t['missing_gci'], 'buyer_volume' => $t['buyer_volume'], 'seller_volume' => $t['seller_volume'], 'missing_price' => $t['missing_price']];
}

// Gross commission for one side (before the brokerage split): a percent of the price or a flat dollar amount, entered by
// the agent per file. Stored as transactions.comm_pct / comm_amt.
function ab_comm_columns() {
    static $done = false; if ($done) return; $done = true; $pdo = flr_db(); $have = [];
    foreach ($pdo->query('PRAGMA table_info(transactions)')->fetchAll() as $c) $have[$c['name']] = true;
    if (empty($have['comm_pct'])) $pdo->exec('ALTER TABLE transactions ADD COLUMN comm_pct REAL');
    if (empty($have['comm_amt'])) $pdo->exec('ALTER TABLE transactions ADD COLUMN comm_amt INTEGER');
}
function ab_comm($folder) {
    ab_comm_columns(); $q = flr_db()->prepare('SELECT comm_pct, comm_amt FROM transactions WHERE folder = ?'); $q->execute([$folder]); $r = $q->fetch() ?: [];
    return ['pct' => (float)($r['comm_pct'] ?? 0), 'amt' => (int)($r['comm_amt'] ?? 0)];
}
// Gross commission in dollars for a row (0 when unknown).
function ab_gci(array $r) {
    $c = $r['comm'] ?? ['pct' => 0, 'amt' => 0];
    if ($c['amt'] > 0) return (int)$c['amt'];
    return ($c['pct'] > 0 && (int)($r['price'] ?? 0) > 0) ? (int)round($r['price'] * $c['pct'] / 100) : 0;
}
// Save the gross commission: "3%" or "2.5" (a percent of the price), or "$12,500" / "12500" (flat dollars).
// Empty clears it. Returns '' or a human-readable error.
function ab_set_comm($me, $folder, $val) {
    $ok = false; foreach (flr_tx_list($me) as $t) { if ($t['folder'] === $folder) $ok = true; }
    if (!$ok) return 'That record was not found in your business.';
    ab_comm_columns(); $v = trim((string)$val); $pct = null; $amt = null;
    if ($v !== '') {
        $n = (float)preg_replace('/[^0-9.]/', '', $v);
        if (strpos($v, '%') !== false || ($n > 0 && $n <= 20 && strpos($v, '$') === false)) { if ($n <= 0 || $n > 20) return 'Enter the commission as a percent (for example 3%) or a dollar amount (for example $12,500).'; $pct = $n; }
        elseif ($n >= 100 && $n <= 50000000) $amt = (int)round($n);
        else return 'Enter the commission as a percent (for example 3%) or a dollar amount (for example $12,500).';
    }
    flr_db()->prepare('UPDATE transactions SET comm_pct = ?, comm_amt = ? WHERE folder = ?')->execute([$pct, $amt, $folder]);
    flr_audit(flr_akey($me), 'commission', $folder, $v === '' ? 'Gross commission cleared' : ($pct !== null ? 'Gross commission set to ' . $pct . '%' : 'Gross commission set to $' . number_format($amt)));
    return '';
}

// Cancelled / expired outcomes, by side. Buyers can only have a cancelled purchase contract; sellers can have any.
const AB_OUTCOMES = ['contract' => 'Cancelled purchase contract', 'listing' => 'Cancelled listing agreement', 'expired' => 'Expired listing agreement'];
function ab_outcomes_for($side) { return $side === 'seller' ? AB_OUTCOMES : ['contract' => AB_OUTCOMES['contract']]; }
// Mark one of the agent's records cancelled/expired ($outcome a key of AB_OUTCOMES, $date YYYY-MM-DD), or '' to make it
// live again. Returns '' or a human-readable error.
function ab_set_outcome($me, $folder, $outcome, $date = '') {
    $rec = null; foreach (flr_tx_list($me) as $t) { if ($t['folder'] === $folder) $rec = $t; }
    if (!$rec) return 'That record was not found in your business.';
    flr_outcome_columns();
    if ($outcome === '') {
        flr_db()->prepare('UPDATE transactions SET outcome = NULL, outcome_on = NULL WHERE folder = ?')->execute([$folder]);
        flr_audit(flr_akey($me), 'outcome', $folder, 'Marked active again'); return '';
    }
    $opts = ab_outcomes_for($rec['side']);
    if (!isset($opts[$outcome])) return 'Choose what happened: ' . implode(', ', $opts) . '.';
    $date = $date === '' ? date('Y-m-d') : $date;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date) || $date > date('Y-m-d')) return 'Please enter the date it was cancelled or expired (today or earlier).';
    flr_db()->prepare('UPDATE transactions SET outcome = ?, outcome_on = ? WHERE folder = ?')->execute([$outcome, $date, $folder]);
    flr_audit(flr_akey($me), 'outcome', $folder, $opts[$outcome] . ' on ' . $date);
    return '';
}
// Cancelled and expired records for one agent, newest first; each row adds 'side', 'outcome' (key), 'outcome_label', 'on'.
function ab_cancelled($me) {
    flr_outcome_columns(); $out = [];
    $all = array_values(array_filter(flr_tx_list($me), function ($t) { return (string)($t['outcome'] ?? '') !== '' && in_array($t['side'], ['buyer', 'seller'], true); }));
    foreach (['buyer', 'seller'] as $side) {
        $list = array_values(array_filter($all, function ($t) use ($side) { return $t['side'] === $side; }));
        foreach (ab_rows($me, $side, $list, 'ab_pk') as $r) { $r['side'] = $side; $r['outcome'] = (string)$r['t']['outcome']; $r['outcome_label'] = AB_OUTCOMES[$r['outcome']] ?? 'Cancelled'; $r['on'] = (string)($r['t']['outcome_on'] ?? ''); $out[] = $r; }
    }
    usort($out, function ($a, $b) { return strcmp($b['on'], $a['on']) ?: strcmp($a['nm'], $b['nm']); });
    return $out;
}
