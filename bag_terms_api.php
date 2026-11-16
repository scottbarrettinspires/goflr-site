<?php
// Agreement setup answers for one of the signed-in agent's buyer records, as JSON (page and assistants via the FLR MCP).
// GET  ?tx=ID -> {ok, terms:{types[], areas, comm, admin, special, expires, homes, admin_ok}}
// POST JSON {csrf, tx, action:'downloaded'} -> {ok}  (the agent downloaded the form: the signed-copy upload then shows on the record)
// POST JSON {csrf, tx, kind:'exclusive'|'showing', types[], areas, comm, admin, admin_ok, special, expires, homes} -> {ok} or {ok:false, msg}
require __DIR__ . "/guard.php";
require_once __DIR__ . "/baglib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$me = strtolower($_SESSION['email'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $tx = (string)($_GET['tx'] ?? ''); $own = false; foreach (flr_tx_list($me, false) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') $own = true; }
    echo json_encode($own ? ['ok' => true, 'terms' => bag_terms($me, $tx)] : ['ok' => false, 'msg' => 'That buyer record was not found in your business.']); exit;
}
$j = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($j['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
if (($j['action'] ?? '') === 'downloaded') { $tx = (string)($j['tx'] ?? ''); $own = false; foreach (flr_tx_list($me, false) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') $own = true; } if ($own) ag_kv_put('bag_dl_' . $tx, gmdate('c')); echo json_encode(['ok' => $own]); exit; }
$e = bag_terms_save($me, (string)($j['tx'] ?? ''), $j);
echo json_encode($e === '' ? ['ok' => true] : ['ok' => false, 'msg' => $e]);
