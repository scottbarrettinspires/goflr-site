<?php
// Complete a contract task on one of the signed-in agent's Active Business records, with a note, or just add a note. JSON.
// POST JSON {csrf, action:'complete', tx, key, note, fields:{name:value}}  -> {ok}   marks the task done and records the note on the client record
// POST JSON {csrf, action:'note', tx, note}           -> {ok}   adds a dated note to the client record
require __DIR__ . "/guard.php";
require_once __DIR__ . "/rnlib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$me = strtolower($_SESSION['email'] ?? '');
$j = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($j['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
$tx = (string)($j['tx'] ?? ''); $a = (string)($j['action'] ?? '');
if ($a === 'complete') $e = rn_complete_task($me, $tx, (string)($j['key'] ?? ''), (string)($j['note'] ?? ''), is_array($j['fields'] ?? null) ? $j['fields'] : []);
elseif ($a === 'note') $e = rn_add($me, $tx, (string)($j['note'] ?? ''));
else $e = 'Unknown action. Use complete or note.';
echo json_encode($e === '' ? ['ok' => true] : ['ok' => false, 'msg' => $e]);
