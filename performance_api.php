<?php
// Performance data for the signed-in agent, as JSON (for the page's future widgets and for assistants via the FLR MCP).
// GET ?action=closed -> {ok, buyers:[...], sellers:[...]}, each item {folder, name, stage, address, phone, email, source, url}.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/ablib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$action = (string)($_GET['action'] ?? 'closed');
if ($action !== 'closed') { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'Unknown action. Use action=closed.']); exit; }
$c = ab_closed($_SESSION['email']);
$map = function ($r, $side) { return ['folder' => $r['t']['folder'], 'name' => $r['nm'], 'stage' => PS_STAGES[$side][$r['k']] ?? $r['k'],
    'address' => $r['addr'], 'phone' => $r['ph'], 'email' => $r['em'], 'source' => $r['src'], 'url' => ($side === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . rawurlencode($r['t']['folder'])]; };
echo json_encode(['ok' => true, 'buyers' => array_map(function ($r) use ($map) { return $map($r, 'buyer'); }, $c['buyer']),
    'sellers' => array_map(function ($r) use ($map) { return $map($r, 'seller'); }, $c['seller'])]);
