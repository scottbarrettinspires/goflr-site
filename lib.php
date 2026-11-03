<?php
require_once __DIR__ . '/config.php';

function flr_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params(['lifetime' => SESSION_DAYS * 86400, 'path' => '/', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'), 'httponly' => true, 'samesite' => 'Lax']);
    session_name('flrsess');
    session_start();
}

function flr_get_json($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) return null;
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

// Roster lookup. Returns the roster's answer for this email, or null on any error (fail closed).
function flr_roster_info($email) {
    if (strpos(ROSTER_URL, 'PASTE_') === 0) return null;
    $j = flr_get_json(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&email=' . urlencode(strtolower(trim($email))));
    return is_array($j) ? $j : null;
}

// True only when the roster says this email is Active.
function flr_roster_active($email) {
    $j = flr_roster_info($email);
    return is_array($j) && ($j['active'] ?? false) === true;
}

// Optional approvals from the roster (Commercial / Leasing columns). Missing means not approved.
function flr_perms($j) {
    return ['commercial' => is_array($j) && ($j['commercial'] ?? false) === true, 'leasing' => is_array($j) && ($j['leasing'] ?? false) === true];
}

function flr_logout() {
    flr_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', !empty($p['secure']), true);
    }
    session_destroy();
}
