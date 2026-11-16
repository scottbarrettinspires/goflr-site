<?php
// Upload a signed buyer agreement for one of the signed-in agent's buyer records (multipart form POST).
// POST fields: csrf, tx, kind (exclusive|showing|extension), on (YYYY-MM-DD signed), expires (exclusive/extension), homes (showing), pdf (file)
// -> {ok, rid, agreement} or {ok:false, msg}. The signed copy is filed to the record; the lead becomes Active Business.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/baglib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$me = strtolower($_SESSION['email'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'Use POST.']); exit; }
if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { echo json_encode(['ok' => false, 'msg' => 'That file is too large to upload. Please choose a smaller PDF.']); exit; }
if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
$tx = (string)($_POST['tx'] ?? '');
list($rid, $err) = bag_upload($me, $tx, (string)($_POST['kind'] ?? ''), $_FILES['pdf'] ?? [], (string)($_POST['on'] ?? ''), (string)($_POST['expires'] ?? ''), (string)($_POST['homes'] ?? ''));
echo json_encode($err === '' ? ['ok' => true, 'rid' => $rid, 'agreement' => ag_standing($me, $tx)] : ['ok' => false, 'msg' => $err]);
