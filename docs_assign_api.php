<?php
// Assign pages of a signed document to a required document, or remove an assignment.
// POST: csrf, tx, ref, action=assign|unassign, pages (comma list, empty = whole document), target ("key:flood" ... or "custom"), custom (name), key/label (unassign).
// Returns JSON {ok:true} or {ok:false,msg}.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/doclib.php";
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
$me = $_SESSION['email'] ?? ''; $tx = (string)($_POST['tx'] ?? ''); $ref = (string)($_POST['ref'] ?? '');
if (($_POST['action'] ?? 'assign') === 'unassign') { $e = dc_unassign($me, $tx, $ref, (string)($_POST['key'] ?? ''), (string)($_POST['label'] ?? '')); }
else {
    $pages = array_filter(array_map('intval', explode(',', (string)($_POST['pages'] ?? ''))));
    $e = dc_assign($me, $tx, $ref, $pages, (string)($_POST['target'] ?? ''), (string)($_POST['custom'] ?? ''));
}
echo json_encode($e === '' ? ['ok' => true] : ['ok' => false, 'msg' => $e]);
