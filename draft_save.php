<?php
// Quietly keeps the unsigned, filled-in documents in Contract Offers / [address] / Drafts - Not Signed.
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
@set_time_limit(120);
header('Content-Type: application/json'); header('Cache-Control: no-store');
function out($a) { echo json_encode($a); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false]);
$j = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($j) || !hash_equals($_SESSION['csrf'] ?? '', (string)($j['csrf'] ?? ''))) out(['ok' => false, 'msg' => 'expired']);
$tx = (string)($j['tx'] ?? '');
$addr = trim(preg_replace('/\s+/', ' ', (string)($j['property'] ?? '')));
$b64 = (string)($j['b64'] ?? ''); $b64d = (string)($j['b64d'] ?? '');
if ($b64 === '' || strlen($b64) > 60000000 || strlen($b64d) > 60000000) out(['ok' => false, 'msg' => 'empty']);
if ($addr === '' || strlen($addr) > 120 || preg_match('/[\\\\\/:*?"<>|]/', $addr)) out(['ok' => false, 'msg' => 'address']);
$all = preg_match('/^[A-Za-z0-9_-]{10,80}$/', $tx) ? (sg_folders($_SESSION['email'], $tx) ?: []) : [];
$pick = null; foreach ($all as $f) { if ($f['id'] === $tx && $f['group'] === '1 BUYERS / 1 Buyers') $pick = $f; }
if (!$pick) out(['ok' => false, 'msg' => 'folder']);
$d = gmdate('Y-m-d');
$r = sg_script_post(['op' => 'save', 'email' => $_SESSION['email'], 'hint' => $tx, 'offerAddress' => $addr, 'subfolder' => 'Drafts - Not Signed', 'name' => 'Contract Packet (draft) - ' . $d . '.pdf', 'b64' => $b64]);
if (!is_array($r) || empty($r['ok'])) out(['ok' => false, 'msg' => 'save']);
if ($b64d !== '') { sg_script_post(['op' => 'save', 'email' => $_SESSION['email'], 'hint' => $tx, 'offerAddress' => $addr, 'subfolder' => 'Drafts - Not Signed', 'name' => 'Buyer Disclosures (draft) - ' . $d . '.pdf', 'b64' => $b64d]); }
sg_draft_mark($_SESSION['email'], $addr, $tx, false);
out(['ok' => true, 'addr' => $addr]);
