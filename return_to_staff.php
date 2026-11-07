<?php
// Ends a "Login As" session (login_as.php) and restores the staff/broker's own account. Linked from the banner
// guard.php shows on every page while impersonating.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/flrdb.php";
if (empty($_SESSION['impersonator'])) { header('Location: home.php'); exit; }
$imp = $_SESSION['impersonator'];
flr_audit(flr_akey((string)($_SESSION['email'] ?? '')), 'impersonate_end', '', 'Returned to ' . ($imp['email'] ?? ''));
$_SESSION['email'] = $imp['email'] ?? ''; $_SESSION['name'] = $imp['name'] ?? ''; $_SESSION['pic'] = $imp['pic'] ?? '';
if (!empty($imp['staff'])) { $_SESSION['staff'] = $imp['staff']; }
unset($_SESSION['impersonator']);
$_SESSION['checked'] = 0; // re-check the roster as the staff account on the next page load
header('Location: directory.php'); exit;
