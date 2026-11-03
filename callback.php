<?php
// DigiSigner event callback. The body is never trusted: we only read the request id, then ask DigiSigner directly.
require __DIR__ . '/sigtools.php';
$j = json_decode((string)file_get_contents('php://input'), true);
$id = is_array($j) ? ($j['signature_request']['signature_request_id'] ?? '') : '';
if ($id !== '') {
    $rec = sg_load($id);
    if ($rec && sg_refresh($rec) && !empty($rec['completed']) && empty($rec['filed']) && sg_can_file($rec)) { sg_file($rec); }
}
header('Content-Type: text/plain');
echo 'DIGISIGNER_EVENT_ACCEPTED';
