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

// Roster lookup. Agent Records first; the old roster sheet is a temporary fallback. Returns an array or null (fail closed).
function flr_roster_info($email) {
    if (strpos(ROSTER_URL, 'PASTE_') === 0) return null;
    $q = '?key=' . urlencode(ROSTER_KEY) . '&email=' . urlencode(strtolower(trim($email)));
    $j = flr_get_json(ROSTER_URL . $q);
    if (is_array($j) && ($j['active'] ?? false) === true) return $j;
    if (defined('OLD_ROSTER_URL')) {
        $o = flr_get_json(OLD_ROSTER_URL . $q);
        if (is_array($o) && ($o['active'] ?? false) === true) return $o;
        if (!is_array($j)) return $o;
    }
    return $j;
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

// Headshot cache. The roster script sends a data: URI; we shrink it and keep it in photos/ (web access denied),
// then photo.php (sign-in required) serves it to the signed-in agent only.
function flr_photo_path($email) { return __DIR__ . '/photos/' . sha1(strtolower(trim($email))) . '.jpg'; }

function flr_store_photo($email, $dataUri) {
    if (!is_string($dataUri) || strlen($dataUri) > 3000000 || !preg_match('#^data:image/(png|jpeg|gif|webp);base64,([A-Za-z0-9+/=]+)$#', $dataUri, $m)) return false;
    $path = flr_photo_path($email);
    if (is_file($path) && time() - filemtime($path) < 86400) return true;
    $raw = base64_decode($m[2], true);
    if ($raw === false || strlen($raw) < 100) return false;
    $dir = __DIR__ . '/photos';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    if (!is_file($dir . '/.htaccess')) { @file_put_contents($dir . '/.htaccess', "Require all denied\n"); }
    $out = $raw;
    if (function_exists('imagecreatefromstring')) {
        $im = @imagecreatefromstring($raw);
        if ($im) {
            $w = imagesx($im); $h = imagesy($im); $s = min($w, $h);
            $sq = imagecreatetruecolor(240, 240);
            imagefill($sq, 0, 0, imagecolorallocate($sq, 255, 255, 255));
            imagecopyresampled($sq, $im, 0, 0, (int)(($w - $s) / 2), (int)(($h - $s) / 4), 240, 240, $s, $s);
            ob_start(); imagejpeg($sq, null, 85); $out = ob_get_clean();
        }
    }
    return @file_put_contents($path, $out) !== false;
}

// Staff / agent directory from the office script, cached for a few minutes in photos/ (web access denied).
function flr_dir_dir() {
    $d = __DIR__ . '/photos';
    if (!is_dir($d)) { @mkdir($d, 0755, true); }
    if (!is_file($d . '/.htaccess')) { @file_put_contents($d . '/.htaccess', "Require all denied\n"); }
    return $d;
}
function flr_directory() {
    $f = flr_dir_dir() . '/directory.json';
    if (is_file($f) && time() - filemtime($f) < 300) { $j = json_decode((string)@file_get_contents($f), true); if (is_array($j)) return $j; }
    $j = flr_get_json(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=directory');
    if (is_array($j) && !empty($j['ok'])) { @file_put_contents($f, json_encode($j), LOCK_EX); return $j; }
    if (is_file($f)) { $o = json_decode((string)@file_get_contents($f), true); if (is_array($o)) return $o; }
    return null;
}
function flr_dir_photo_key($fileId) { return substr(sha1('dir|' . $fileId), 0, 20); }
// Headshot for a person: their own upload on the Profile page when there is one, otherwise $fallback (the roster photo URL, or '').
function flr_headshot_url($email, $fallback = '') {
    $email = strtolower(trim((string)$email)); if ($email === '') return $fallback;
    require_once __DIR__ . '/profilelib.php'; $u = pf_url($email, 'headshot'); return $u !== '' ? $u : $fallback;
}

// Announcements from the FLR Announcements sheet (all-agent and agent-specific), cached briefly per agent; dismissals are kept per agent.
function flr_ann_file($email, $kind) { return flr_dir_dir() . '/ann-' . $kind . '-' . sha1(strtolower(trim($email))) . '.json'; }
function flr_ann_dismissed($email) { $j = json_decode((string)@file_get_contents(flr_ann_file($email, 'seen')), true); return is_array($j) ? $j : []; }
function flr_ann_dismiss($email, $id) { $s = flr_ann_dismissed($email); if (!in_array($id, $s, true)) { $s[] = $id; @file_put_contents(flr_ann_file($email, 'seen'), json_encode(array_values($s)), LOCK_EX); } }
function flr_announcements($email) {
    $f = flr_ann_file($email, 'list'); $items = null;
    if (is_file($f) && time() - filemtime($f) < 120) { $j = json_decode((string)@file_get_contents($f), true); if (is_array($j)) $items = $j; }
    if ($items === null) {
        $j = flr_get_json(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=announcements&email=' . urlencode(strtolower($email)));
        if (is_array($j) && !empty($j['ok'])) { $items = (array)($j['items'] ?? []); @file_put_contents($f, json_encode($items), LOCK_EX); }
        elseif (is_file($f)) { $o = json_decode((string)@file_get_contents($f), true); $items = is_array($o) ? $o : []; }
        else { $items = []; }
    }
    $seen = flr_ann_dismissed($email);
    return array_values(array_filter($items, function ($a) use ($seen) { return !in_array($a['id'] ?? '', $seen, true); }));
}

// Upcoming birthdays/anniversaries (next 14 days) for Active agents, from the server-side Agent Records table
// (arlib.php) -- no Apps Script call. Never breaks the Home page: any error just means an empty panel.
function flr_agent_dates() {
    try { require_once __DIR__ . '/arlib.php'; return ar_upcoming_dates(14); } catch (Throwable $e) { return []; }
}

// Staff sign-in: an Active person on the FLR Staff Directory sheet (Broker, Office Admin, Owner) can sign in too.
function flr_staff_info($email) {
    $email = strtolower(trim($email)); if ($email === '') return null;
    $d = flr_directory(); if (!$d) return null;
    foreach ((array)($d['staff'] ?? []) as $p) {
        if (strtolower(trim((string)($p['email'] ?? ''))) === $email) {
            return ['active' => true, 'staff' => true, 'position' => (string)($p['position'] ?? ''), 'name' => (string)($p['name'] ?? ''), 'photo_key' => !empty($p['photo']) ? flr_dir_photo_key($p['photo']) : '', 'commercial' => true, 'leasing' => true];
        }
    }
    return null;
}
