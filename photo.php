<?php
require __DIR__ . "/guard.php";
$p = flr_photo_path($_SESSION['email'] ?? '');
if (!is_file($p)) { http_response_code(404); exit; }
header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
readfile($p);
