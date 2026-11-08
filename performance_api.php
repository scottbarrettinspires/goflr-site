<?php
// Performance data for the signed-in agent, as JSON (for the page's future widgets and for assistants via the FLR MCP).
// GET ?action=closed[&period=month|quarter|ytd|YYYY] -> {ok, period, from, to, buyers:[...], sellers:[...]},
// each item {folder, name, stage, closed (YYYY-MM-DD or ''), price, address, phone, email, source, url}, plus totals (sides and
// $ volume; each side counts its full sold price). No period = every closed file. POST action=setprice saves a sold price.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/ablib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$action = (string)($_GET['action'] ?? ($_POST['action'] ?? 'closed'));
if ($action === 'setprice') { // POST JSON {action:'setprice', csrf, tx, price} -> {ok} or {ok:false, msg}
    $j = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($j['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Send a POST with the page csrf token.']); exit; }
    $e = ab_set_price($_SESSION['email'], (string)($j['tx'] ?? ''), (string)($j['price'] ?? ''));
    echo json_encode($e === '' ? ['ok' => true, 'tx' => (string)$j['tx'], 'price' => ab_price((string)$j['tx'])] : ['ok' => false, 'msg' => $e]); exit;
}
if ($action !== 'closed') { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'Unknown action. Use action=closed.']); exit; }
$per = (string)($_GET['period'] ?? '');
if ($per !== '' && !in_array($per, ['month', 'quarter', 'ytd'], true) && !preg_match('/^\d{4}$/', $per)) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'period must be month, quarter, ytd, or a 4-digit year.']); exit; }
$c = ab_closed($_SESSION['email'], $per === '' ? null : $per); $rg = $per === '' ? ['', ''] : ab_period_range($per);
$map = function ($r, $side) { return ['folder' => $r['t']['folder'], 'name' => $r['nm'], 'stage' => PS_STAGES[$side][$r['k']] ?? $r['k'], 'closed' => $r['closed'], 'price' => $r['price'],
    'address' => $r['addr'], 'phone' => $r['ph'], 'email' => $r['em'], 'source' => $r['src'], 'url' => ($side === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . rawurlencode($r['t']['folder'])]; };
echo json_encode(['ok' => true, 'period' => $per ?: 'all', 'from' => $rg[0], 'to' => $rg[1], 'totals' => ab_totals($c['buyer'], $c['seller']), 'buyers' => array_map(function ($r) use ($map) { return $map($r, 'buyer'); }, $c['buyer']),
    'sellers' => array_map(function ($r) use ($map) { return $map($r, 'seller'); }, $c['seller'])]);
