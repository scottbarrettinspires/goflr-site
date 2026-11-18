<?php
// Upload another document to a client record and assign it to a required document or give it a name.
// POST (multipart): csrf, tx, file, target ("key:flood" ... or "custom"), custom (name). With back=1 redirects to the Compliance Document Records page.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/doclib.php";
@set_time_limit(120);
$back = !empty($_POST['back']); $tx = (string)($_POST['tx'] ?? '');
$fail = function ($m) use ($back, $tx) { if ($back) { header('Location: docs_view.php?embed=1&tx=' . urlencode($tx) . '&err=' . urlencode($m)); exit; } header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => $m]); exit; };
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) $fail('This page expired. Please reload and try again.');
list($id, $e) = dc_upload($_SESSION['email'] ?? '', $tx, $_FILES['file'] ?? [], (string)($_POST['target'] ?? ''), (string)($_POST['custom'] ?? ''));
if ($e !== '') $fail($e);
if ($back) { header('Location: docs_view.php?embed=1&tx=' . urlencode($tx) . '&added=1'); exit; }
header('Content-Type: application/json'); echo json_encode(['ok' => true, 'id' => $id]);
