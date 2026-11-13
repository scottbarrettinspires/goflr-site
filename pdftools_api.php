<?php
// PDF Tools: file a finished PDF into an Active Business record. Same sign-in and permissions as the page.
// GET  ?op=records[&fresh=1]                  -> {ok, records:[{id, section, name, group}]}   (the signed-in agent's Active Business records)
// POST multipart: csrf, folder (record id), name, file (PDF)  -> {ok, name, url, folder, folder_url}
require __DIR__ . "/guard.php";
require_once __DIR__ . "/pdftoolslib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    list($recs, $note) = ptl_records($me, !empty($_GET['fresh']));
    echo json_encode($recs === null ? ['ok' => false, 'msg' => $note] : ['ok' => true, 'records' => $recs]); exit;
}
if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
$f = $_FILES['file'] ?? null;
if (!$f || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) { echo json_encode(['ok' => false, 'msg' => 'No PDF came through. Please try again.']); exit; }
list($ok, $r) = ptl_save($me, (string)($_POST['folder'] ?? ''), (string)($_POST['name'] ?? ''), (string)file_get_contents($f['tmp_name']));
echo json_encode($ok ? ['ok' => true] + $r : ['ok' => false, 'msg' => $r]);
