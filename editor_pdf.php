<?php
// Streams one contract packet from the signed-in agent's buyer folder to the editor.
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
@set_time_limit(120);
$tx = (string)($_GET['tx'] ?? ''); $id = (string)($_GET['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]{10,80}$/', $tx) || !preg_match('/^[A-Za-z0-9_-]{10,80}$/', $id)) { http_response_code(400); exit('Bad request'); }
$all = sg_folders($_SESSION['email'], $tx) ?: [];
$ok = false; foreach ($all as $f) { if ($f['id'] === $tx && $f['group'] === '1 BUYERS / 1 Buyers') $ok = true; }
$dr = trim(preg_replace('/\s+/', ' ', (string)($_GET['dr'] ?? '')));
$dres = ($ok && $dr !== '' && strlen($dr) <= 120 && !preg_match('/[\\\\\/:*?"<>|]/', $dr)) ? sg_drafts($_SESSION['email'], $tx, $dr) : null;
$files = $ok ? ($dres ? $dres['files'] : sg_packets($_SESSION['email'], $tx)) : null;
$in = false; foreach (($files ?: []) as $f) { if ($f['id'] === $id) $in = true; }
if (!$in) { http_response_code(404); exit('Not found'); }
$p = sg_packet_pdf($_SESSION['email'], $id);
if (!$p) { http_response_code(502); exit('Could not load the packet'); }
header('Content-Type: application/pdf'); header('Cache-Control: private, no-store');
echo $p['pdf'];
