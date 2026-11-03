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
