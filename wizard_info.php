<?php
// Returns the saved Buyer Info for one of the signed-in agent's buyer folders (used by the wizard to prefill).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/sigtools.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$tx = (string)($_GET['tx'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]{10,80}$/', $tx)) { echo json_encode(['ok' => false]); exit; }
$all = sg_folders($_SESSION['email'], $tx) ?: [];
$okf = false; foreach ($all as $f) { if ($f['id'] === $tx && $f['group'] === '1 BUYERS / 1 Buyers') $okf = true; }
if (!$okf) { echo json_encode(['ok' => false]); exit; }
$r = sg_buyer_info($_SESSION['email'], $tx);
$info = is_array($r['info'] ?? null) ? $r['info'] : null;
$mine = strtolower(trim((string)($_SESSION['name'] ?? '')));
if ($info && $mine !== '') { foreach (['b1', 'b2'] as $p) { if (strtolower(trim((string)($info[$p . '_name'] ?? ''))) === $mine) { $info[$p . '_name'] = ''; $info[$p . '_phone'] = ''; $info[$p . '_email'] = ''; } } }
echo json_encode(['ok' => !empty($r['ok']), 'info' => $info]);
