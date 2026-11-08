<?php
// Serves a PDF that staff uploaded to the Forms library (signed-in users only). ?dl=1 downloads it instead of opening it.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/formslib.php";
$f = fm_item((int)($_GET['id'] ?? 0));
$path = $f && $f['file'] !== '' ? fm_dir() . '/' . basename($f['file']) : '';
if ($path === '' || !is_file($path)) { http_response_code(404); exit('Form not found.'); }
while (ob_get_level()) @ob_end_clean();
$fn = preg_replace('/[^A-Za-z0-9 ._()\'-]/', '', $f['name']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . $fn . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
readfile($path);
