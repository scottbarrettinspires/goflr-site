<?php
// Sends seller-provided PDFs (uploaded by the agent, with signature boxes placed in the browser) to the buyers through DigiSigner.
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
@set_time_limit(180);
header('Content-Type: application/json'); header('Cache-Control: no-store');
function out($a) { echo json_encode($a); exit; }
// DigiSigner rectangle = [x1, y1, x2, y2]. The browser sends top-left coordinates; if the boxes land upside down on a test, flip this to true.
const SG_RECT_FROM_TOP = false;
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
$emails = (array)($j['emails'] ?? []);
$ROLE = ['buyer1' => 1, 'buyer2' => 1]; $TYPES = ['SIGNATURE' => 1, 'INITIALS' => 1, 'DATE' => 1];
$docsIn = (array)($j['docs'] ?? []);
if (!$docsIn || count($docsIn) > 8) out(['ok' => false, 'msg' => 'Add between 1 and 8 documents.']);
$items = []; $meta = [];
$name = trim($_SESSION['name'] ?? '') ?: 'Your agent';
foreach ($docsIn as $d) {
    $title = trim(preg_replace('/[^A-Za-z0-9 ._()&,\'-]/', ' ', preg_replace('/\.pdf$/i', '', (string)($d['name'] ?? 'Seller Disclosure'))));
    $title = trim(preg_replace('/\s+/', ' ', $title)) ?: 'Seller Disclosure';
    $pdf = base64_decode((string)($d['b64'] ?? ''), true);
    if ($pdf === false || strlen($pdf) < 500 || substr($pdf, 0, 4) !== '%PDF') out(['ok' => false, 'msg' => '"' . $title . '" is not a readable PDF.']);
    $by = [];
    foreach ((array)($d['fields'] ?? []) as $f) {
        $r = (string)($f['role'] ?? ''); $t = (string)($f['type'] ?? '');
        if (!isset($ROLE[$r]) || !isset($TYPES[$t])) continue;
        $pg = (int)($f['page'] ?? 0); $H = (float)($f['ph'] ?? 792);
        $x = (float)($f['x'] ?? 0); $y = (float)($f['y'] ?? 0); $w = (float)($f['w'] ?? 0); $h = (float)($f['h'] ?? 0);
        if ($w < 5 || $h < 5 || $pg < 0 || $pg > 400) continue;
        $y1 = SG_RECT_FROM_TOP ? $y : $H - ($y + $h); $y2 = SG_RECT_FROM_TOP ? $y + $h : $H - $y;
        $by[$r][] = ['type' => $t, 'page' => $pg, 'rectangle' => [round($x, 1), round($y1, 1), round($x + $w, 1), round($y2, 1)], 'required' => true];
    }
    if (!$by) out(['ok' => false, 'msg' => 'Place at least one signature box on "' . $title . '".']);
    $sg = [];
    foreach ($by as $r => $fields) {
        $e = trim((string)($emails[$r] ?? ''));
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) out(['ok' => false, 'msg' => 'Please check the email address for ' . ($r === 'buyer1' ? 'Buyer 1' : 'Buyer 2') . '.']);
        $sg[] = ['email' => $e, 'role' => $r, 'fields' => $fields];
    }
    $items[] = [$pdf, $title . '.pdf', 'Florida Luxury Realty: please sign ' . $title . ' for ' . $addr, $name . ' (Florida Luxury Realty) has sent you ' . $title . ' for ' . $addr . ' to review and sign. If you have questions, reply to ' . $me . '.', $sg];
    $meta[] = ['form' => $title . ' - ' . $addr, 'what' => $title];
}
$_SESSION['sig_n'][] = time();
$s = sg_send_multi($items, []);
if (!$s['ok']) out($s);
$rec = ['id' => $s['id'], 'agent' => $me, 'form' => $meta[0]['form'], 'form_id' => '', 'doc' => $s['docs'][0], 'created' => gmdate('c'), 'signers' => [], 'folder' => null, 'offer_address' => $addr, 'hint' => $buyer['id'], 'completed' => false, 'filed' => null];
for ($i = 1; $i < count($meta); $i++) { $rec['extra'][] = ['form' => $meta[$i]['form'], 'form_id' => '', 'doc' => $s['docs'][$i], 'filed' => null]; }
sg_save($s['id'], $rec);
sg_dir();
@file_put_contents(__DIR__ . '/logs/signature-requests.log', json_encode(['t' => gmdate('c'), 'agent' => $me, 'seller_docs' => array_map(function ($m) { return $m['what']; }, $meta), 'address' => $addr, 'buyer_folder' => $buyer['name'], 'request' => $s['id']]) . "\n", FILE_APPEND | LOCK_EX);
out(['ok' => true, 'id' => $s['id'], 'count' => count($meta), 'addr' => $addr]);
