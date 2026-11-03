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

function sg_folders($email) {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=folders&email=' . urlencode(strtolower($email)));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    return (is_array($j) && !empty($j['ok'])) ? $j['folders'] : null;
}

// File the signed PDF into the chosen transaction folder (brokerage copy). Never overwrites. Returns [ok, message].
function sg_file(&$rec) {
    if (!empty($rec['filed'])) return [true, 'Already filed.'];
    if (empty($rec['folder']['id'])) return [false, 'No transaction chosen.'];
    $pdf = sg_signed_pdf($rec);
    if ($pdf === null) return [false, 'The signed copy is not ready to download yet.'];
    $name = 'Signed - ' . $rec['form'] . ' - ' . gmdate('Y-m-d') . '.pdf';
    $r = sg_script_post(['op' => 'save', 'email' => $rec['agent'], 'folderId' => $rec['folder']['id'], 'name' => $name, 'b64' => base64_encode($pdf)]);
    if (!is_array($r) || empty($r['ok'])) return [false, 'Could not save to the transaction folder' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.'];
    $rec['filed'] = ['name' => $r['name'], 'url' => $r['url'], 'at' => gmdate('c'), 'folder' => $rec['folder']['label'] ?? ''];
    sg_save($rec['id'], $rec);
    @file_put_contents(__DIR__ . '/logs/signature-requests.log', json_encode(['t' => gmdate('c'), 'filed' => $rec['id'], 'agent' => $rec['agent'], 'folder' => $rec['folder']['label'] ?? '', 'file' => $r['name']]) . "\n", FILE_APPEND | LOCK_EX);
    return [true, 'Filed in ' . ($rec['folder']['label'] ?? 'the transaction folder') . '.'];
}
