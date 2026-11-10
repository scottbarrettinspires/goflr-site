<?php
// Buyer Agreement stage: the blank forms (Exclusive Buyer Brokerage Agreement, Showing Agreement), the signed copy on
// file for a buyer record (from DigiSigner or uploaded by the agent), and requests still out for signature.
// Plain functions so the page, its PDF endpoint (bagdoc.php) and assistants share one set of rules.
require_once __DIR__ . '/sigtools.php';
require_once __DIR__ . '/pstage.php';

const BAG_FORMS = [
    'exclusive' => ['id' => BAG_EXCLUSIVE_FORM_ID, 'title' => 'Exclusive Buyer Brokerage Agreement', 'note' => 'Covers every home shown to this buyer from here on. No further agreement needed.'],
    'showing'   => ['id' => BAG_SHOWING_FORM_ID, 'title' => 'Showing Agreement', 'note' => 'Covers only the home(s) named on the signed form. A home it doesn\'t name needs a new Showing Agreement, or an upgrade to Exclusive.'],
];
function bag_kind_of($form) {
    $t = strtolower((string)$form);
    if (strpos($t, 'exclusive buyer brokerage') !== false) return 'exclusive';
    if (strpos($t, 'showing agreement') !== false && strpos($t, 'amendment') === false) return 'showing';
    return null;
}
// Where uploaded signed copies are kept on the server (outside the web root, next to the database).
function bag_updir() {
    $d = flr_db_dir() . '/signed_uploads'; if (!is_dir($d)) @mkdir($d, 0750, true); return $d;
}
// The blank form as PDF bytes, cached on the server for a day (fetched through the office script from the Forms folder).
function bag_blank_pdf($kind) {
    if (!isset(BAG_FORMS[$kind])) return null;
    $d = flr_db_dir() . '/formcache'; if (!is_dir($d)) @mkdir($d, 0750, true);
    $p = $d . '/' . BAG_FORMS[$kind]['id'] . '.pdf';
    if (is_file($p) && filemtime($p) > time() - 86400) return (string)file_get_contents($p);
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=file&id=' . urlencode(BAG_FORMS[$kind]['id']));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    $pdf = (is_array($j) && !empty($j['ok']) && !empty($j['b64'])) ? base64_decode($j['b64'], true) : false;
    if ($pdf === false || strlen($pdf) < 500 || substr($pdf, 0, 4) !== '%PDF') return is_file($p) ? (string)file_get_contents($p) : null;
    @file_put_contents($p, $pdf, LOCK_EX);
    return $pdf;
}
// For one buyer record: ['signed' => newest signed agreement record or null, 'all_signed' => [...], 'pending' => [...]].
function bag_state($me, $tx) {
    $signed = []; $pending = [];
    foreach (sg_for_agent($me) as $r) {
        if ((string)($r['folder']['id'] ?? '') !== $tx || !bag_kind_of($r['form'] ?? '')) continue;
        if (!empty($r['completed'])) $signed[] = $r;
        elseif (strtotime($r['created'] ?? '') > time() - 45 * 86400) $pending[] = $r;
    }
    usort($signed, function ($a, $b) { return strcmp($b['completed_at'] ?? $b['created'] ?? '', $a['completed_at'] ?? $a['created'] ?? ''); });
    return ['signed' => $signed[0] ?? null, 'all_signed' => $signed, 'pending' => $pending];
}
// The signed copy as PDF bytes (uploaded copy kept here, or DigiSigner's signed document).
function bag_signed_pdf(array $rec) {
    if (!empty($rec['local'])) { $p = bag_updir() . '/' . basename($rec['local']); return is_file($p) ? (string)file_get_contents($p) : null; }
    return sg_signed_pdf($rec);
}
// Save a signed copy the agent uploads ($file = one $_FILES entry). It becomes this record's signed agreement, is filed
// to the transaction folder in Drive when possible, and shows everywhere a DigiSigner-signed copy does.
// Returns [record id, ''] or ['', error message].
function bag_upload($me, $tx, $kind, array $file, $signedOn = '') {
    if (!isset(BAG_FORMS[$kind])) return ['', 'Choose which agreement this is.'];
    $own = null; foreach (flr_tx_list($me) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') $own = $t; }
    if (!$own) return ['', 'That buyer record was not found in your business.'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['', 'Choose the signed PDF to upload.'];
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) return ['', 'The upload did not finish. Please try again with a smaller file.'];
    if ((int)($file['size'] ?? 0) > 25 * 1024 * 1024) return ['', 'That file is larger than 25 MB. Please upload a smaller PDF.'];
    $pdf = (string)@file_get_contents((string)$file['tmp_name']);
    if (strlen($pdf) < 500 || substr($pdf, 0, 4) !== '%PDF') return ['', 'Please upload the signed copy as a PDF.'];
    if ($signedOn !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $signedOn) || $signedOn > date('Y-m-d'))) return ['', 'Please enter the date it was signed (today or earlier).'];
    $id = 'UP' . bin2hex(random_bytes(8));
    if (!@file_put_contents(bag_updir() . '/' . $id . '.pdf', $pdf, LOCK_EX)) return ['', 'The signed copy could not be saved. Please try again.'];
    $at = $signedOn !== '' ? $signedOn . 'T12:00:00' . date('P') : date('c');
    $rec = ['id' => $id, 'agent' => $me, 'form' => BAG_FORMS[$kind]['title'], 'form_id' => BAG_FORMS[$kind]['id'], 'doc' => '', 'created' => $at, 'completed' => true,
        'completed_at' => $at, 'uploaded' => true, 'local' => $id . '.pdf', 'signers' => [], 'seen' => true,
        'folder' => ['id' => $tx, 'label' => trim((string)$own['grp'] . ' / ' . (string)$own['name'], ' /')]];
    list($ok, $r) = sg_save_bytes($rec, $pdf, 'Signed - ' . BAG_FORMS[$kind]['title'] . ' - ' . ($signedOn ?: date('Y-m-d')) . '.pdf');
    $rec['filed'] = $ok ? $r : ['name' => 'Signed - ' . BAG_FORMS[$kind]['title'] . '.pdf', 'url' => '', 'at' => gmdate('c'), 'folder' => 'Saved here only (' . (is_string($r) ? $r : 'Drive filing failed') . ')'];
    sg_save($id, $rec);
    flr_audit(flr_akey($me), 'agreement', $tx, 'Uploaded signed ' . BAG_FORMS[$kind]['title'] . ($ok ? ' and filed it to the transaction folder' : ' (not filed to Drive)'));
    return [$id, ''];
}
