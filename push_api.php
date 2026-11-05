<?php
// Turn new-lead alerts on or off for this phone.
require __DIR__ . "/guard.php";
require __DIR__ . "/pushlib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$j = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($j) || !hash_equals($_SESSION['csrf'] ?? '', (string)($j['csrf'] ?? ''))) { echo json_encode(['ok' => false, 'msg' => 'Please reload the page.']); exit; }
$me = strtolower($_SESSION['email'] ?? ''); $ep = (string)($j['endpoint'] ?? '');
if (($j['action'] ?? '') === 'off') { px_unsubscribe($me, $ep); echo json_encode(['ok' => true]); exit; }
if (($j['action'] ?? '') === 'test') {
    px_notify(ld_file($me), 'FLR Office alerts are on', 'You will get an alert like this for every new lead.', '/leads.php'); echo json_encode(['ok' => true]); exit;
}
echo json_encode(['ok' => px_subscribe($me, $ep)]);
