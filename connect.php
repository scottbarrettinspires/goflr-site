<?php
// Start connecting one account (Settings > Connectors): sends the person to that service to sign in and approve.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/oauthlib.php";
$p = (string)($_GET['p'] ?? '');
if (!isset(OA_CONNECTORS[$p])) { header('Location: connectors.php'); exit; }
if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_GET['t'] ?? ''))) { header('Location: connectors.php?err=' . urlencode('This page expired. Please try again.')); exit; }
if (!oa_ready($p)) { header('Location: connectors.php?err=' . urlencode(OA_CONNECTORS[$p][0] . ' is not set up by the office yet.')); exit; }
header('Location: ' . oa_authorize_url($p)); exit;
