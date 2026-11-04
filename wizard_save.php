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
$b64d = (string)($j['b64d'] ?? '');
if ((empty($j['offerOnly']) && $b64 === '' && $b64d === '') || strlen($b64) > 60000000 || strlen($b64d) > 60000000) out(['ok' => false, 'msg' => 'The packet is empty or too large.']);
$folders = sg_folders($_SESSION['email'], (string)($j['tx'] ?? '')) ?: [];
$pick = null; foreach ($folders as $f) { if ($f['id'] === (string)($j['tx'] ?? '') && $f['group'] === '1 BUYERS / 1 Buyers') { $pick = $f; } }
if (!$pick) out(['ok' => false, 'msg' => 'Please choose one of your buyer folders (1 BUYERS / 1 Buyers).']);
$r = ['name' => '', 'url' => '']; $d = ['name' => '', 'url' => ''];
if ($b64 !== '' && empty($j['offerOnly'])) {
    $r = sg_script_post(['op' => 'save', 'email' => $_SESSION['email'], 'folderId' => $pick['id'], 'name' => 'Contract Packet - ' . gmdate('Y-m-d') . '.pdf', 'b64' => $b64]);
    if (!is_array($r) || empty($r['ok'])) out(['ok' => false, 'msg' => 'Could not save to the transaction folder' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.']);
}
if ($b64d !== '' && empty($j['offerOnly'])) {
    $d = sg_script_post(['op' => 'save', 'email' => $_SESSION['email'], 'folderId' => $pick['id'], 'name' => 'Buyer Disclosures - ' . gmdate('Y-m-d') . '.pdf', 'b64' => $b64d]);
    if (!is_array($d) || empty($d['ok'])) out(['ok' => false, 'msg' => 'The contract packet was saved, but the Buyer Disclosures could not be saved' . (is_array($d) && !empty($d['note']) ? ' (' . $d['note'] . ')' : '') . '.']);
}
sg_dir();
@file_put_contents(__DIR__ . '/logs/signature-requests.log', json_encode(['t' => gmdate('c'), 'packet' => $r['name'], 'disclosures' => $d['name'], 'agent' => $_SESSION['email'], 'folder' => $pick['group'] . ' / ' . $pick['name'], 'forms' => $j['forms'] ?? []]) . "\n", FILE_APPEND | LOCK_EX);
$prop = trim(preg_replace('/\s+/', ' ', (string)($j['property'] ?? ''))); $price = trim((string)($j['price'] ?? ''));
if ($prop !== '' || $price !== '') { sg_buyer_info_merge($_SESSION['email'], $pick['id'], ['property' => $prop, 'price' => $price]); }
$offer = ''; $offerNote = '';
if ($prop !== '' && strlen($prop) <= 120 && !preg_match('/[\\\\\/:*?"<>|]/', $prop)) {
    $o = sg_script_post(['op' => 'createoffer', 'email' => $_SESSION['email'], 'hint' => $pick['id'], 'offerAddress' => $prop]);
    if (is_array($o) && !empty($o['ok'])) { $offer = $o['name']; $offerNote = !empty($o['existed']) ? 'already existed' : 'created'; } else { $offerNote = 'could not be created' . (is_array($o) && !empty($o['note']) ? ': ' . $o['note'] : ''); $offer = $prop; }
}
out(['ok' => true, 'offer' => $offer, 'offerNote' => $offerNote, 'name' => $r['name'], 'url' => $r['url'], 'dname' => $d['name'], 'durl' => $d['url'], 'folder' => $pick['group'] . ' / ' . $pick['name']]);
