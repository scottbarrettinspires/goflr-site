<?php
// Staff/broker "Login As": temporarily take on an agent's session to see the back office as they see it.
// Starts only from a POST on the Directory page (CSRF-checked); ends on return_to_staff.php. While impersonating,
// guard.php shows a banner on every page with a link back. Logged to the audit trail both ways.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/flrdb.php";
if (!flr_is_broker()) { header('Location: home.php'); exit; }
if (!empty($_SESSION['impersonator'])) { header('Location: directory.php?e=already'); exit; } // return to your own account first
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { header('Location: directory.php?e=expired'); exit; }
$target = strtolower(trim((string)($_POST['email'] ?? '')));
$d = flr_directory(); $name = ''; $found = false;
if ($d && $target !== '') { foreach ((array)($d['agents'] ?? []) as $p) { if (strtolower(trim((string)($p['email'] ?? ''))) === $target) { $name = (string)($p['name'] ?? ''); $found = true; break; } } }
if (!$found) { header('Location: directory.php?e=notfound'); exit; }

$_SESSION['impersonator'] = ['email' => (string)($_SESSION['email'] ?? ''), 'name' => (string)($_SESSION['name'] ?? ''), 'staff' => (string)($_SESSION['staff'] ?? '')];
flr_audit(flr_akey($target), 'impersonate_start', '', 'Logged in as this agent by ' . $_SESSION['impersonator']['email']);
$_SESSION['email'] = $target; $_SESSION['name'] = $name; unset($_SESSION['staff']);
$_SESSION['checked'] = 0; // forces guard.php to re-check the roster as this agent on the very next page load
header('Location: home.php'); exit;
