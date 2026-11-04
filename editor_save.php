<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
@set_time_limit(120);
header('Content-Type: application/json'); header('Cache-Control: no-store');
function out($a) { echo json_encode($a); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'msg' => 'Not allowed']);
$j = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($j) || !hash_equals($_SESSION['csrf'] ?? '', (string)($j['csrf'] ?? ''))) out(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']);
$b64 = (string)($j['b64'] ?? '');
if ($b64 === '' || strlen($b64) > 60000000) out(['ok' => false, 'msg' => 'The packet is empty or too large.']);
$tx = (string)($j['tx'] ?? '');
$all = preg_match('/^[A-Za-z0-9_-]{10,80}$/', $tx) ? (sg_folders($_SESSION['email'], $tx) ?: []) : [];
$pick = null; foreach ($all as $f) { if ($f['id'] === $tx && $f['group'] === '1 BUYERS / 1 Buyers') $pick = $f; }
if (!$pick) out(['ok' => false, 'msg' => 'Please open this from one of your buyer folders.']);
$r = sg_script_post(['op' => 'save', 'email' => $_SESSION['email'], 'folderId' => $pick['id'], 'name' => 'Contract Packet (completed) - ' . gmdate('Y-m-d') . '.pdf', 'b64' => $b64]);
if (!is_array($r) || empty($r['ok'])) out(['ok' => false, 'msg' => 'Could not save to the buyer folder' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.']);
sg_dir();
@file_put_contents(__DIR__ . '/logs/signature-requests.log', json_encode(['t' => gmdate('c'), 'agent' => $_SESSION['email'], 'completed_packet' => $r['name'], 'folder' => $pick['name']]) . "\n", FILE_APPEND | LOCK_EX);
out(['ok' => true, 'name' => $r['name'], 'url' => $r['url'], 'folder' => $pick['group'] . ' / ' . $pick['name']]);
