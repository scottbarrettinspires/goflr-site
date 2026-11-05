<?php
// New-lead push alerts. Pushes carry no data (no encryption needed); the app fetches the alert text when the push arrives.
require_once __DIR__ . '/leadlib.php';
require_once __DIR__ . '/push_keys.php';

function px_b64u($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function px_subs_file($email) { return ld_dir() . '/push-' . sha1(strtolower(trim($email))) . '.json'; }
function px_alerts_file($email) { return ld_dir() . '/alerts-' . sha1(strtolower(trim($email))) . '.json'; }
function px_idx_file($ep) { $d = ld_dir() . '/pushidx'; if (!is_dir($d)) @mkdir($d, 0755, true); return $d . '/' . hash('sha256', $ep) . '.json'; }

function px_subscribe($email, $endpoint) {
    if (!preg_match('#^https://[a-z0-9.-]+(:\d+)?/#i', $endpoint) || strlen($endpoint) > 800) return false;
    $f = px_subs_file($email); $list = (array)json_decode((string)@file_get_contents($f), true);
    $list = array_values(array_filter($list, function ($x) use ($endpoint) { return ($x['ep'] ?? '') !== $endpoint; }));
    $list[] = ['ep' => $endpoint, 'since' => date('c')]; $list = array_slice($list, -6);
    @file_put_contents($f, json_encode($list), LOCK_EX);
    @file_put_contents(px_idx_file($endpoint), json_encode(['agent' => sha1(strtolower(trim($email)))]), LOCK_EX);
    return true;
}
function px_unsubscribe($email, $endpoint) {
    $f = px_subs_file($email); $list = (array)json_decode((string)@file_get_contents($f), true);
    $list = array_values(array_filter($list, function ($x) use ($endpoint) { return ($x['ep'] ?? '') !== $endpoint; }));
    @file_put_contents($f, json_encode($list), LOCK_EX); @unlink(px_idx_file($endpoint));
}
// ES256 JWT for the push service (VAPID).
function px_vapid($endpoint) {
    $p = parse_url($endpoint); $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $h = px_b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])); $c = px_b64u(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => 'https://goflr.com']));
    if (!openssl_sign($h . '.' . $c, $der, PUSH_PRIVATE_PEM, OPENSSL_ALGO_SHA256)) return null;
    // DER signature -> raw r||s (64 bytes)
    $o = 3; $lr = ord($der[$o]); $r = substr($der, $o + 1, $lr); $o += 1 + $lr + 1; $ls = ord($der[$o]); $s = substr($der, $o + 1, $ls);
    $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT); $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    return $h . '.' . $c . '.' . px_b64u($r . $s);
}
// Save the alert for the app to read, then wake every phone the agent turned alerts on for.
function px_notify($agentFile, $title, $body, $url) {
    $sha = basename($agentFile, '.json');
    $af = ld_dir() . '/alerts-' . $sha . '.json'; $al = (array)json_decode((string)@file_get_contents($af), true);
    $al[] = ['id' => bin2hex(random_bytes(4)), 't' => time(), 'title' => $title, 'body' => $body, 'url' => $url]; $al = array_slice($al, -10);
    @file_put_contents($af, json_encode($al), LOCK_EX);
    $sf = ld_dir() . '/push-' . $sha . '.json'; $subs = (array)json_decode((string)@file_get_contents($sf), true); $keep = [];
    foreach ($subs as $s) {
        $ep = (string)($s['ep'] ?? ''); $jwt = $ep !== '' ? px_vapid($ep) : null; if (!$jwt) continue;
        $ch = curl_init($ep);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_HTTPHEADER => ['TTL: 86400', 'Urgency: high', 'Content-Length: 0', 'Authorization: vapid t=' . $jwt . ', k=' . PUSH_PUBLIC], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4]);
        curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code === 404 || $code === 410) { @unlink(px_idx_file($ep)); continue; } // phone unsubscribed
        $keep[] = $s;
    }
    if (count($keep) !== count($subs)) @file_put_contents($sf, json_encode($keep), LOCK_EX);
}
