<?php
// Learn And Grow Your Business for assistants. Same sign-in and permissions as the page.
// GET  [?kind=presentation|playbook]  -> {ok, items:[{id, kind, title, series, session, view_url, download_url, partners:[{id, kind, title}]}]}  (partners = the matching playbook for a presentation and vice versa)
// GET  ?refresh=1                    -> re-reads the Drive Training folders right now (otherwise at most every 2 minutes)
// GET  ?id=N                          -> {ok, item}
// POST multipart (staff): action=add, csrf, kind, title, series, session (YYYY-MM-DD), pdf (file) -> {ok}
// POST (staff): action=delete, csrf, id -> {ok}
// POST (staff): action=rename, csrf, id, title -> {ok}
require __DIR__ . "/guard.php";
require_once __DIR__ . "/learnlib.php";
require_once __DIR__ . "/dlib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$syncMsg = ln_sync(!empty($_GET['refresh']));
$partners = ln_partners(['presentation' => ln_list('presentation'), 'playbook' => ln_list('playbook')]);
$shape = function ($r) use ($partners) { return ['partners' => array_map(function ($p) { return ['id' => $p['id'], 'kind' => $p['kind'], 'title' => $p['title']]; }, $partners[$r['id']] ?? []), 'id' => (int)$r['id'], 'kind' => $r['kind'], 'title' => $r['title'], 'series' => (string)$r['series'], 'session' => (string)$r['session'], 'view_url' => 'https://goflr.com/learn.php?tab=' . ($r['kind'] === 'playbook' ? 'playbooks' : 'presentations') . '&id=' . (int)$r['id'], 'download_url' => 'https://goflr.com/' . ln_file_url($r['id'], true)]; };
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    if (isset($_GET['id'])) { $it = ln_item((int)$_GET['id']); echo json_encode($it ? ['ok' => true, 'item' => $shape($it)] : ['ok' => false, 'msg' => 'That item was not found.']); exit; }
    echo json_encode(['ok' => true, 'items' => array_map($shape, ln_list((string)($_GET['kind'] ?? ''))), 'sync_note' => $syncMsg]); exit;
}
$j = $_POST ?: (json_decode((string)file_get_contents('php://input'), true) ?: []);
if (!hash_equals($_SESSION['csrf'], (string)($j['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
if (!flr_is_broker()) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Only office staff can add or remove Learn items.']); exit; }
$by = strtolower($_SESSION['email'] ?? ''); $a = (string)($j['action'] ?? '');
if ($a === 'add') { $e = ln_add((string)($j['kind'] ?? ''), (string)($j['title'] ?? ''), (string)($j['series'] ?? ''), (string)($j['session'] ?? ''), $_FILES['pdf'] ?? [], $by); echo json_encode($e === '' ? ['ok' => true] : ['ok' => false, 'msg' => $e]); exit; }
if ($a === 'delete') { $e = ln_delete((int)($j['id'] ?? 0), $by); echo json_encode($e === '' ? ['ok' => true] : ['ok' => false, 'msg' => $e]); exit; }
if ($a === 'rename') { $e = ln_rename((int)($j['id'] ?? 0), (string)($j['title'] ?? ''), $by); echo json_encode($e === '' ? ['ok' => true] : ['ok' => false, 'msg' => $e]); exit; }
http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'Unknown action. Use add, delete or rename.']);
