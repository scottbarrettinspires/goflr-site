<?php
// Link an already-signed document to a required document. POST: csrf, tx, key, ref, page. Returns JSON {ok} or {ok:false,msg}; ?back=1 redirects to the Documents page.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/doclib.php";
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
$tx = (string)($_POST['tx'] ?? '');
$e = dc_link($_SESSION['email'] ?? '', $tx, (string)($_POST['key'] ?? ''), (string)($_POST['ref'] ?? ''), (int)($_POST['page'] ?? 1));
if (!empty($_POST['back'])) { header('Location: docs_view.php?embed=1&tx=' . urlencode($tx) . ($e !== '' ? '&err=' . urlencode($e) : '')); exit; }
echo json_encode($e === '' ? ['ok' => true] : ['ok' => false, 'msg' => $e]);
