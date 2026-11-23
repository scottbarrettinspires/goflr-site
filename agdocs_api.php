<?php
// Staff-only agent documents (agreements in the shared drive), as JSON.
// GET  ?action=list&agent_id=A-0078 -> {ok, folder_url, agent_folder_url, files:[{id, name, url, modified, size}]}
// POST multipart: action=upload, csrf, agent_id, file, name? -> {ok, name, url} or {ok:false, msg}
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/agdocslib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (!flr_is_broker()) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Staff only.']); exit; }
$action = (string)($_GET['action'] ?? ($_POST['action'] ?? 'list'));
$id = trim((string)($_GET['agent_id'] ?? ($_POST['agent_id'] ?? '')));
if ($action === 'upload') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
    echo json_encode(ad_upload($id, $_FILES['file'] ?? [], (string)($_POST['name'] ?? ''), $_SESSION['email'] ?? '')); exit;
}
echo json_encode(ad_list($id));
