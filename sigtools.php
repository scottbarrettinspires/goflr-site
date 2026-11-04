<?php
// Shared helpers for DigiSigner requests: storage, status checks, signed-PDF download and filing into a transaction folder.
require_once __DIR__ . '/config.php';

function sg_dir() {
    $d = __DIR__ . '/logs/requests';
    if (!is_dir($d)) { @mkdir($d, 0755, true); }
    $h = __DIR__ . '/logs/.htaccess';
    if (!is_file($h)) { @file_put_contents($h, "Require all denied\n"); }
    return $d;
}
function sg_path($id) { return sg_dir() . '/' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$id) . '.json'; }
function sg_load($id) { $p = sg_path($id); if (!is_file($p)) return null; $j = json_decode((string)file_get_contents($p), true); return is_array($j) ? $j : null; }
function sg_save($id, $rec) { @file_put_contents(sg_path($id), json_encode($rec), LOCK_EX); }
function sg_for_agent($email) {
    $out = [];
    foreach (glob(sg_dir() . '/*.json') ?: [] as $f) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j) && strtolower($j['agent'] ?? '') === strtolower($email)) $out[] = $j;
    }
    usort($out, function ($a, $b) { return strcmp($b['created'] ?? '', $a['created'] ?? ''); });
    return $out;
}

function sg_api($method, $path, $raw = false) {
    $ch = curl_init('https://api.digisigner.com/v1' . $path);
    curl_setopt_array($ch, [CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_CUSTOMREQUEST => $method]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($r === false || $code < 200 || $code >= 300) return null;
    return $raw ? $r : json_decode($r, true);
}

// Ask DigiSigner (never trust a callback body) whether the request is finished. Updates the stored record.
function sg_refresh(&$rec) {
    if (!empty($rec['completed'])) return true;
    $q = sg_api('GET', '/signature_requests/' . rawurlencode($rec['id']));
    if (!is_array($q)) return false;
    $rec['signers_status'] = [];
    foreach (($q['documents'][0]['signers'] ?? []) as $s) { $rec['signers_status'][$s['email'] ?? ''] = !empty($s['is_signature_completed']); }
    if (!empty($q['is_completed'])) { $rec['completed'] = true; $rec['doc'] = $q['documents'][0]['document_id'] ?? ($rec['doc'] ?? ''); }
    sg_save($rec['id'], $rec);
    return true;
}

function sg_signed_pdf($rec) {
    if (empty($rec['completed']) || empty($rec['doc'])) return null;
    $pdf = sg_api('GET', '/documents/' . rawurlencode($rec['doc']), true);
    return ($pdf !== null && strlen($pdf) > 500) ? $pdf : null;
}

function sg_script_post($payload) {
    $payload['key'] = ROSTER_KEY;
    $ch = curl_init(ROSTER_URL);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => ['Content-Type: text/plain'], CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    return is_array($j) ? $j : null;
}

function sg_folders($email, $hint = '') {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=folders&email=' . urlencode(strtolower($email)) . ($hint !== '' ? '&hint=' . urlencode($hint) : ''));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    $GLOBALS['sg_folders_root'] = is_array($j) ? (string)($j['root'] ?? '') . (empty($j['tops']) ? '' : ' [' . implode(', ', $j['tops']) . ']') : '';
    $GLOBALS['sg_folders_note'] = is_array($j) ? (string)($j['note'] ?? '') : 'The office script did not answer (is the newest version deployed?).';
    return (is_array($j) && !empty($j['ok'])) ? $j['folders'] : null;
}

// File the signed PDF into the chosen transaction folder (brokerage copy). Never overwrites. Returns [ok, message].
function sg_file(&$rec) {
    if (!empty($rec['filed'])) return [true, 'Already filed.'];
    if (!sg_can_file($rec)) return [false, 'No transaction chosen.'];
    $pdf = sg_signed_pdf($rec);
    if ($pdf === null) return [false, 'The signed copy is not ready to download yet.'];
    $name = 'Signed - ' . $rec['form'] . ' - ' . gmdate('Y-m-d') . '.pdf';
    $post = ['op' => 'save', 'email' => $rec['agent'], 'name' => $name, 'b64' => base64_encode($pdf)];
    if (!empty($rec['offer_address'])) { $post['offerAddress'] = $rec['offer_address']; if (!empty($rec['hint'])) { $post['hint'] = $rec['hint']; } } else { $post['folderId'] = $rec['folder']['id']; }
    $r = sg_script_post($post);
    if (!is_array($r) || empty($r['ok'])) return [false, 'Could not save to the transaction folder' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.'];
    $label = !empty($rec['offer_address']) ? '1 BUYERS / 2 Contract Offers / ' . ($r['folder'] ?? $rec['offer_address']) : ($rec['folder']['label'] ?? '');
    $rec['filed'] = ['name' => $r['name'], 'url' => $r['url'], 'at' => gmdate('c'), 'folder' => $label];
    sg_save($rec['id'], $rec);
    @file_put_contents(__DIR__ . '/logs/signature-requests.log', json_encode(['t' => gmdate('c'), 'filed' => $rec['id'], 'agent' => $rec['agent'], 'folder' => $label, 'file' => $r['name']]) . "\n", FILE_APPEND | LOCK_EX);
    return [true, 'Filed in ' . $label . '.'];
}

// Contract Forms Wizard rules, read from the Wizard Rules sheet through the office script.
function sg_wizard_rules() {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=wizard');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    return (is_array($j) && !empty($j['ok'])) ? $j : null;
}

// A request can be filed automatically when it names a destination: a chosen folder, or an address under Contract Offers.
function sg_can_file($rec) { return !empty($rec['folder']['id']) || !empty($rec['offer_address']); }

// Upload a PDF to DigiSigner and create a signature request using the hidden text tags. Returns ['ok'=>true,'id'=>..,'doc'=>..] or ['ok'=>false,'msg'=>..].
function sg_send_pdf($pdf, $filename, $subject, $message, $signers) {
    $tmp = tempnam(sys_get_temp_dir(), 'flrsig'); file_put_contents($tmp, $pdf);
    $ch = curl_init('https://api.digisigner.com/v1/documents');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_POSTFIELDS => ['file' => new CURLFile($tmp, 'application/pdf', $filename)], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 8]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); @unlink($tmp);
    $d = json_decode((string)$r, true);
    if ($code < 200 || $code >= 300 || empty($d['document_id'])) return ['ok' => false, 'msg' => 'DigiSigner did not accept the packet (code ' . $code . ').'];
    $sg = []; foreach ($signers as $s) { $sg[] = ['email' => $s['email'], 'role' => $s['role'], 'order' => 1]; }
    $req = ['embedded' => false, 'use_text_tags' => true, 'hide_text_tags' => true, 'send_emails' => true, 'documents' => [['document_id' => $d['document_id'], 'subject' => $subject, 'message' => $message, 'signers' => $sg]]];
    $ch = curl_init('https://api.digisigner.com/v1/signature_requests');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($req), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $q = json_decode((string)$r, true);
    if ($code < 200 || $code >= 300 || empty($q['signature_request_id'])) return ['ok' => false, 'msg' => 'DigiSigner could not create the request (code ' . $code . '). Check that each role matches a signature tag in the packet.'];
    return ['ok' => true, 'id' => $q['signature_request_id'], 'doc' => $d['document_id']];
}

function sg_packets($email, $folderId) {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=packets&email=' . urlencode(strtolower($email)) . '&folderId=' . urlencode($folderId));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    return (is_array($j) && !empty($j['ok'])) ? $j['files'] : null;
}

function sg_packet_pdf($email, $fileId) {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=packetfile&email=' . urlencode(strtolower($email)) . '&id=' . urlencode($fileId));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    $pdf = (is_array($j) && !empty($j['ok']) && !empty($j['b64'])) ? base64_decode($j['b64'], true) : false;
    return ($pdf !== false && strlen($pdf) > 500) ? ['name' => $j['name'], 'pdf' => $pdf] : null;
}

// Buyer Info: one data file per buyer folder, read and written through the office script.
function sg_buyer_info($email, $folderId) {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=buyerinfo&email=' . urlencode(strtolower($email)) . '&folderId=' . urlencode($folderId));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    return is_array($j) ? $j : ['ok' => false, 'note' => 'The office script did not answer (is the newest version deployed?).'];
}
function sg_buyer_info_save($email, $folderId, $info) {
    $r = sg_script_post(['op' => 'savebuyerinfo', 'email' => $email, 'folderId' => $folderId, 'info' => $info]);
    return is_array($r) ? $r : ['ok' => false, 'note' => 'The office script did not answer (is the newest version deployed?).'];
}

// The agent's license number (from the Agents tab), cached in the session.
function sg_license($email) {
    if (isset($_SESSION['license'])) return (string)$_SESSION['license'];
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=license&email=' . urlencode(strtolower($email)));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    $lic = (is_array($j) && !empty($j['ok'])) ? preg_replace('/[^A-Za-z0-9 \-]/', '', (string)($j['license'] ?? '')) : '';
    if (is_array($j) && !empty($j['ok'])) $_SESSION['license'] = $lic;
    return $lic;
}

// Merge a few fields (like the property address) into a buyer's saved info. Best effort.
function sg_buyer_info_merge($email, $folderId, $fields) {
    $r = sg_buyer_info($email, $folderId);
    if (empty($r['ok'])) return false;
    $info = is_array($r['info'] ?? null) ? $r['info'] : [];
    foreach ($fields as $k => $v) { if ($v !== '' && $v !== null) $info[$k] = mb_substr((string)$v, 0, 200); }
    $w = sg_buyer_info_save($email, $folderId, $info);
    return !empty($w['ok']);
}
