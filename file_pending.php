<?php
// Files the agent's fully signed documents into the transaction record, in the background so pages load fast.
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
@set_time_limit(170);
header('Content-Type: application/json'); header('Cache-Control: no-store');
function out($a) { echo json_encode($a); exit; }
$j = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($j) || !hash_equals($_SESSION['csrf'] ?? '', (string)($j['csrf'] ?? ''))) out(['ok' => false, 'msg' => 'This page expired. Please reload.']);
$me = strtolower($_SESSION['email']); $filed = 0; $errs = []; $left = 0;
foreach (sg_for_agent($me) as $r) {
    if (empty($r['completed'])) continue;
    $pend = empty($r['filed']); foreach (($r['extra'] ?? []) as $x) { if (empty($x['filed'])) $pend = true; }
    if (!$pend || !sg_can_file($r)) continue;
    if ($filed >= 2) { $left++; continue; }
    list($ok, $m) = sg_file($r);
    if ($ok) $filed++; else $errs[] = is_string($m) ? $m : 'Could not file a document.';
}
out(['ok' => true, 'filed' => $filed, 'left' => $left, 'errors' => $errs]);
