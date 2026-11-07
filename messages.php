<?php
// Messages page for everyone signed in: the inbox (with each sender's headshot), Reply, and a compose form. Opened
// from the envelope on Home, and from the Message button on each Directory card (?to=<key>). See msglib.php.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php"; // flr_is_broker()
require_once __DIR__ . "/msglib.php";
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
$isStaff = flr_is_broker();
$msgErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $msgErr = 'This page expired. Please reload and try again.'; }
    else { $_POST['back'] = 'messages.php'; $msgErr = om_handle_post($me, trim($_SESSION['name'] ?? ''), $isStaff); }
}
sp_head('Messages');
$omPage = 'messages.php';
require __DIR__ . "/msg_panel.php";
sp_foot();
