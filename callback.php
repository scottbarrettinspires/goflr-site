<?php
// DigiSigner event callback. The body is never trusted: we only read the request id, then ask DigiSigner directly.
require __DIR__ . '/sigtools.php';
$j = json_decode((string)file_get_contents('php://input'), true);
$id = is_array($j) ? ($j['signature_request']['signature_request_id'] ?? '') : '';
if ($id !== '') {
    $rec = sg_load($id);
    $was = $rec && !empty($rec['completed']);
    if ($rec && sg_refresh($rec) && !empty($rec['completed']) && empty($rec['filed']) && sg_can_file($rec)) { sg_file($rec); }
    if ($rec && !$was && !empty($rec['completed'])) { // just finished: phone alert to the agent, linking to the client record
        try { require_once __DIR__ . '/pushlib.php'; require_once __DIR__ . '/leadlib.php'; require_once __DIR__ . '/signed_panel.php'; require_once __DIR__ . '/flrdb.php';
            $cl = sg_record_for($rec, flr_tx_list($rec['agent']));
            @px_notify(ld_file($rec['agent']), 'Signed: ' . preg_replace('/^[^-]*-\s*/', '', (string)$rec['form']), ($cl ? $cl['name'] . ' · ' : '') . 'Tap to open the record', 'https://goflr.com/' . ($cl ? $cl['url'] : 'signed.php?seen=' . urlencode($rec['id'])));
        } catch (Throwable $e) {}
    }
}
header('Content-Type: text/plain');
echo 'DIGISIGNER_EVENT_ACCEPTED';
