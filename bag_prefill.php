<?php
// Buyer Agreement prefill. GET ?tx=&kind= -> {ok, fields, filled}: the form values the site knows for this buyer.
// POST JSON {csrf, b64} (the filled agreement from the browser) -> {ok, token} for sign.php?pf=token.
// POST JSON {csrf, action:'send', tx, kind, b64} -> sends it to DigiSigner with the buyer(s) and agent as signers -> {ok, id}.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/baglib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$me = strtolower($_SESSION['email'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $j = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($j) || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($j['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
    if (($j['action'] ?? '') === 'send') { // send for signing to the buyer(s) on the record
        $pdf = base64_decode((string)($j['b64'] ?? ''), true);
        list($id, $e) = bag_send($me, (string)($j['tx'] ?? ''), (string)($j['kind'] ?? ''), $pdf === false ? '' : $pdf);
        echo json_encode($e === '' ? ['ok' => true, 'id' => $id] : ['ok' => false, 'msg' => $e]); exit;
    }
    $pdf = base64_decode((string)($j['b64'] ?? ''), true);
    if ($pdf === false || strlen($pdf) < 500 || substr($pdf, 0, 4) !== '%PDF' || strlen($pdf) > 25 * 1024 * 1024) { echo json_encode(['ok' => false, 'msg' => 'The filled agreement could not be read.']); exit; }
    echo json_encode(['ok' => true, 'token' => bag_pf_put($me, $pdf)]); exit;
}
$tx = (string)($_GET['tx'] ?? ''); $kind = (string)($_GET['kind'] ?? 'exclusive');
$own = false; foreach (flr_tx_list($me) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') $own = true; }
if (!$own || !isset(BAG_FORMS[$kind])) { echo json_encode(['ok' => false, 'msg' => 'That buyer record was not found in your business.']); exit; }
echo json_encode(['ok' => true] + bag_prefill($me, $tx, $kind));
