<?php
// Performance data for the signed-in agent, as JSON (for the page's future widgets and for assistants via the FLR MCP).
// GET ?action=closed[&period=month|quarter|ytd|YYYY] -> {ok, period, from, to, buyers:[...], sellers:[...]},
// each item {folder, name, stage, closed (YYYY-MM-DD or ''), address, phone, email, source, url}. No period = every closed file.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/ablib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$action = (string)($_GET['action'] ?? 'closed');
if ($action !== 'closed') { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'Unknown action. Use action=closed.']); exit; }
$per = (string)($_GET['period'] ?? '');
if ($per !== '' && !in_array($per, ['month', 'quarter', 'ytd'], true) && !preg_match('/^\d{4}$/', $per)) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'period must be month, quarter, ytd, or a 4-digit year.']); exit; }
$c = ab_closed($_SESSION['email'], $per === '' ? null : $per); $rg = $per === '' ? ['', ''] : ab_period_range($per);
$map = function ($r, $side) { return ['folder' => $r['t']['folder'], 'name' => $r['nm'], 'stage' => PS_STAGES[$side][$r['k']] ?? $r['k'], 'closed' => $r['closed'],
    'address' => $r['addr'], 'phone' => $r['ph'], 'email' => $r['em'], 'source' => $r['src'], 'url' => ($side === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . rawurlencode($r['t']['folder'])]; };
echo json_encode(['ok' => true, 'period' => $per ?: 'all', 'from' => $rg[0], 'to' => $rg[1], 'buyers' => array_map(function ($r) use ($map) { return $map($r, 'buyer'); }, $c['buyer']),
    'sellers' => array_map(function ($r) use ($map) { return $map($r, 'seller'); }, $c['seller'])]);
