<?php
// Serves a Collaboration photo to signed-in users only. Photos live outside the web folder (see collablib.php).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/collablib.php";
$f = (string)($_GET['f'] ?? '');
if (!preg_match('/^[a-f0-9]{24}\.jpg$/', $f) || !is_file($path = co_img_dir() . '/' . $f)) { http_response_code(404); exit; }
header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=604800');
header('Content-Length: ' . filesize($path));
readfile($path);
