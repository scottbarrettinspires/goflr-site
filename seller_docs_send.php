<?php
// Sends seller-provided PDFs (uploaded by the agent, with signature boxes placed in the browser) to the buyers through DigiSigner.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/sigtools.php";
@set_time_limit(180);
header('Content-Type: application/json'); header('Cache-Control: no-store');
function out($a) { echo json_encode($a); exit; }
// DigiSigner rectangle = [x1, y1, x2, y2]. The browser sends top-left coordinates; if the boxes land upside down on a test, flip this to true.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'msg' => 'Not allowed']);
$j = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($j) || !hash_equals($_SESSION['csrf'] ?? '', (string)($j['csrf'] ?? ''))) out(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']);
$me = $_SESSION['email'];
$tx = (string)($j['tx'] ?? '');
$all = preg_match('/^[A-Za-z0-9_-]{10,80}$/', $tx) ? (sg_folders($me, $tx) ?: []) : [];
$buyer = null; foreach ($all as $f) { if ($f['id'] === $tx && $f['group'] === '1 BUYERS / 1 Buyers') $buyer = $f; }
if (!$buyer) out(['ok' => false, 'msg' => 'Please open this from one of your buyer folders.']);
$_SESSION['sig_n'] = array_filter($_SESSION['sig_n'] ?? [], function ($t) { return $t > time() - 3600; });
if (count($_SESSION['sig_n']) >= 20) out(['ok' => false, 'msg' => 'Too many requests this hour. Please try again later.']);
$addr = trim(preg_replace('/\s+/', ' ', (string)($j['addr'] ?? '')));
if ($addr === '' || strlen($addr) > 120 || preg_match('/[\\\\\/:*?"<>|]/', $addr)) out(['ok' => false, 'msg' => 'Please enter the property address (no slashes or special characters).']);
$name = trim($_SESSION['name'] ?? '') ?: 'Your agent';
$si = sg_seller_items((array)($j['docs'] ?? []), (array)($j['emails'] ?? []), $addr, $me, $name);
if (!empty($si['err'])) out(['ok' => false, 'msg' => $si['err']]);
$items = $si['items']; $meta = $si['meta'];
$_SESSION['sig_n'][] = time();
$s = sg_send_multi($items, [], ['subject' => 'Florida Luxury Realty: please sign your documents for ' . $addr, 'message' => $name . ' (Florida Luxury Realty) has sent you documents for ' . $addr . ' to review and sign. If you have questions, reply to ' . $me . '.']);
if (!$s['ok']) out($s);
$rec = ['id' => $s['id'], 'agent' => $me, 'form' => $meta[0]['form'], 'form_id' => '', 'doc' => $s['docs'][0], 'created' => gmdate('c'), 'signers' => [], 'folder' => null, 'offer_address' => $addr, 'hint' => $buyer['id'], 'completed' => false, 'filed' => null];
for ($i = 1; $i < count($meta); $i++) { $rec['extra'][] = ['form' => $meta[$i]['form'], 'form_id' => '', 'doc' => $s['docs'][$i], 'filed' => null]; }
sg_save($s['id'], $rec);
sg_dir();
@file_put_contents(__DIR__ . '/logs/signature-requests.log', json_encode(['t' => gmdate('c'), 'agent' => $me, 'seller_docs' => array_map(function ($m) { return $m['what']; }, $meta), 'address' => $addr, 'buyer_folder' => $buyer['name'], 'request' => $s['id']]) . "\n", FILE_APPEND | LOCK_EX);
out(['ok' => true, 'id' => $s['id'], 'count' => count($meta), 'addr' => $addr]);
