<?php
// Performance data for the signed-in agent, as JSON (for the page's future widgets and for assistants via the FLR MCP).
// GET ?action=closed[&period=month|quarter|ytd|YYYY] -> {ok, period, from, to, buyers:[...], sellers:[...]},
// each item {folder, name, stage, closed (YYYY-MM-DD or ''), price, address, phone, email, source, url}, plus totals (sides and
// $ volume; each side counts its full sold price). No period = every closed file. &q= searches client name or address. POST action=setprice saves a sold price.
// &compare=1 adds previous_year: the same period a year earlier, with its totals.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/ablib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$action = (string)($_GET['action'] ?? ($_POST['action'] ?? 'closed'));
if ($action === 'setprice') { // POST JSON {action:'setprice', csrf, tx, price?, commission? ('3%' or '$12,500')} -> {ok} or {ok:false, msg}
    $j = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($j['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Send a POST with the page csrf token.']); exit; }
    $e = isset($j['price']) && (string)$j['price'] !== '' ? ab_set_price($_SESSION['email'], (string)($j['tx'] ?? ''), (string)$j['price']) : '';
    if ($e === '' && isset($j['commission'])) $e = ab_set_comm($_SESSION['email'], (string)($j['tx'] ?? ''), (string)$j['commission']);
    echo json_encode($e === '' ? ['ok' => true, 'tx' => (string)$j['tx'], 'price' => ab_price((string)$j['tx'])] : ['ok' => false, 'msg' => $e]); exit;
}
if ($action === 'active') { // GET ?action=active -> {ok, totals, pending:[{folder, side, name, stage, expected_close, price, url}]}
    $a = ab_active($_SESSION['email']); $pend = [];
    foreach (['buyer', 'seller'] as $sd) foreach ($a[$sd] as $r) if ($r['pending']) $pend[] = ['folder' => $r['t']['folder'], 'side' => $sd, 'name' => $r['nm'], 'stage' => PS_STAGES[$sd][$r['k']] ?? $r['k'],
        'expected_close' => $r['close'], 'price' => $r['price'], 'gross_commission' => ab_gci($r), 'url' => ($sd === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . rawurlencode($r['t']['folder'])];
    echo json_encode(['ok' => true, 'totals' => ab_active_totals($a), 'pending' => $pend]); exit;
}
if ($action !== 'closed') { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'Unknown action. Use action=closed or action=active.']); exit; }
$per = (string)($_GET['period'] ?? '');
if ($per !== '' && !in_array($per, ['month', 'quarter', 'ytd'], true) && !preg_match('/^\d{4}$/', $per)) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'period must be month, quarter, ytd, or a 4-digit year.']); exit; }
$c = ab_closed($_SESSION['email'], $per === '' ? null : $per);
$qq = strtolower(trim((string)($_GET['q'] ?? ''))); // optional search: every word must appear in the client name or address
if ($qq !== '') foreach ($c as $sd => $rows) $c[$sd] = array_values(array_filter($rows, function ($r) use ($qq) { $hay = strtolower($r['nm'] . ' ' . $r['addr']);
    foreach (preg_split('/\s+/', $qq) as $w) if ($w !== '' && strpos($hay, $w) === false) return false; return true; })); $rg = $per === '' ? ['', ''] : ab_period_range($per);
$map = function ($r, $side) { return ['folder' => $r['t']['folder'], 'name' => $r['nm'], 'stage' => PS_STAGES[$side][$r['k']] ?? $r['k'], 'closed' => $r['closed'], 'price' => $r['price'], 'gross_commission' => ab_gci($r),
    'address' => $r['addr'], 'phone' => $r['ph'], 'email' => $r['em'], 'source' => $r['src'], 'url' => ($side === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . rawurlencode($r['t']['folder'])]; };
$prevTot = null;
if (!empty($_GET['compare']) && $per !== '') { // same period a year earlier (ytd: Jan 1 to the same date last year)
    $lyd = function ($d) { $t = strtotime($d); $y = (int)date('Y', $t) - 1; $m = (int)date('n', $t); return sprintf('%04d-%02d-%02d', $y, $m, min((int)date('j', $t), (int)date('t', mktime(0, 0, 0, $m, 1, $y)))); };
    $pr = [$lyd($rg[0]), $per === 'ytd' ? $lyd(date('Y-m-d')) : $lyd($rg[1])]; if ($per === 'month' || $per === 'quarter') $pr[1] = date('Y-m-t', strtotime($pr[1]));
    $allc = ab_closed($_SESSION['email']); $in = function ($r) use ($pr) { return $r['closed'] !== '' && $r['closed'] >= $pr[0] && $r['closed'] <= $pr[1]; };
    $prevTot = ['from' => $pr[0], 'to' => $pr[1]] + ab_totals(array_values(array_filter($allc['buyer'], $in)), array_values(array_filter($allc['seller'], $in)));
}
echo json_encode(['ok' => true, 'period' => $per ?: 'all', 'from' => $rg[0], 'to' => $rg[1], 'totals' => ab_totals($c['buyer'], $c['seller']), 'previous_year' => $prevTot, 'buyers' => array_map(function ($r) use ($map) { return $map($r, 'buyer'); }, $c['buyer']),
    'sellers' => array_map(function ($r) use ($map) { return $map($r, 'seller'); }, $c['seller'])]);
