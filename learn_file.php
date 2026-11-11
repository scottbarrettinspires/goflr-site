<?php
// Serves a Learn The Business PDF to signed-in users. ?dl=1 downloads it instead of showing it.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/learnlib.php";
$it = ln_item((int)($_GET['id'] ?? 0));
$path = $it ? ln_dir() . '/' . basename($it['file']) : '';
if ($path === '' || !is_file($path)) { http_response_code(404); exit('That file was not found.'); }
while (ob_get_level()) @ob_end_clean();
$fn = preg_replace('/[^A-Za-z0-9 ._()\'-]/', '', $it['title']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . $fn . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
readfile($path);
