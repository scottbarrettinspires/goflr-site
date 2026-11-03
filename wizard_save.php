<?php
// Saves a built contract packet into the buyer's chosen transaction folder (brokerage copy).
require __DIR__ . "/guard.php";
@set_time_limit(120);
require __DIR__ . "/sigtools.php";
header('Content-Type: application/json');
function out($a) { echo json_encode($a); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'msg' => 'Not allowed']);
$j = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($j) || !hash_equals($_SESSION['csrf'] ?? '', (string)($j['csrf'] ?? ''))) out(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']);
$b64 = (string)($j['b64'] ?? '');
if ($b64 === '' || strlen($b64) > 60000000) out(['ok' => false, 'msg' => 'The packet is empty or too large.']);
$folders = sg_folders($_SESSION['email']) ?: [];
$pick = null; foreach ($folders as $f) { if ($f['id'] === (string)($j['tx'] ?? '') && strpos($f['group'], '1 BUYERS') === 0) { $pick = $f; } }
if (!$pick) out(['ok' => false, 'msg' => 'Please choose one of your buyer transaction folders.']);
$name = 'Contract Packet - ' . gmdate('Y-m-d') . '.pdf';
$r = sg_script_post(['op' => 'save', 'email' => $_SESSION['email'], 'folderId' => $pick['id'], 'name' => $name, 'b64' => $b64]);
if (!is_array($r) || empty($r['ok'])) out(['ok' => false, 'msg' => 'Could not save to the transaction folder' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.']);
sg_dir();
@file_put_contents(__DIR__ . '/logs/signature-requests.log', json_encode(['t' => gmdate('c'), 'packet' => $r['name'], 'agent' => $_SESSION['email'], 'folder' => $pick['group'] . ' / ' . $pick['name'], 'forms' => $j['forms'] ?? []]) . "\n", FILE_APPEND | LOCK_EX);
out(['ok' => true, 'name' => $r['name'], 'url' => $r['url'], 'folder' => $pick['group'] . ' / ' . $pick['name']]);
