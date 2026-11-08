<?php
// Every service sends people back here after they sign in (register https://goflr.com/oauth_cb.php with each app).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/oauthlib.php";
$me = strtolower($_SESSION['email'] ?? '');
$state = (string)($_GET['state'] ?? '');
if (!empty($_GET['error']) || empty($_GET['code'])) {
    unset($_SESSION['oa'][$state]);
    $why = (string)($_GET['error_description'] ?? $_GET['error'] ?? '');
    header('Location: connectors.php?err=' . urlencode(stripos($why, 'denied') !== false || stripos($why, 'cancel') !== false ? 'Connection cancelled. Nothing was connected.' : 'The connection did not finish' . ($why !== '' ? ': ' . mb_substr($why, 0, 160) : '.'))); exit;
}
list($ok, $msg) = oa_finish($me, $state, (string)$_GET['code']);
header('Location: connectors.php?' . ($ok ? 'connected=' . urlencode($msg) : 'err=' . urlencode($msg))); exit;
