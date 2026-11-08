<?php
// Serves a profile picture (headshot or logo) uploaded on the Profile page, to signed-in users only.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/profilelib.php";
$k = (string)($_GET['k'] ?? ''); $t = (string)($_GET['t'] ?? '');
if (!preg_match('/^[a-f0-9]{24}$/', $k) || !isset(PF_KINDS[$t])) { http_response_code(404); exit; }
$p = pf_path($k, $t);
if (!is_file($p)) { http_response_code(404); exit; }
while (ob_get_level()) @ob_end_clean();
header('Content-Type: ' . ($t === 'headshot' ? 'image/jpeg' : 'image/png'));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
if (!empty($_GET['dl'])) header('Content-Disposition: attachment; filename="' . ($t === 'headshot' ? 'headshot.jpg' : ($t === 'logo' ? 'logo-color.png' : 'logo-white.png')) . '"');
readfile($p);
