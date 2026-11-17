<?php
// Serves a photo from private_assets/ to signed-in agents (the folder itself is closed to the web).
require __DIR__ . "/guard.php";
$f = basename((string)($_GET['f'] ?? ''));
$p = __DIR__ . '/private_assets/' . $f;
if (!preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png)$/i', $f) || !is_file($p)) { http_response_code(404); exit; }
header('Content-Type: ' . (preg_match('/\.png$/i', $f) ? 'image/png' : 'image/jpeg')); header('Cache-Control: private, max-age=86400');
readfile($p);
