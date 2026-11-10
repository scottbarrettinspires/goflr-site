<?php
// Offer Details for one of the signed-in agent's buyer records, as JSON (page widgets and assistants via the FLR MCP).
// GET  ?tx=ID  -> {ok, tx, offer:{street_number,street_name,unit,city,state,zip,price,mls_id,year_built,annual_taxes,closing_date,property_type,oa_*}, address, buyer, agent, comp}
// POST JSON {csrf, action:'save', tx, offer:{...}} -> {ok}
// POST JSON {csrf, action:'savecomp', tx, kind:'exclusive'|'showing', comp:{Comp_*: value}} -> {ok}
require __DIR__ . "/guard.php";
require_once __DIR__ . "/offerlib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$me = strtolower($_SESSION['email'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { echo json_encode(of_get($me, (string)($_GET['tx'] ?? ''))); exit; }
$j = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($j['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
$tx = (string)($j['tx'] ?? ''); $a = (string)($j['action'] ?? '');
if ($a === 'save') { $e = of_save($me, $tx, is_array($j['offer'] ?? null) ? $j['offer'] : []); echo json_encode($e === '' ? ['ok' => true] : ['ok' => false, 'msg' => $e]); exit; }
if ($a === 'savecomp') { $e = of_save_comp($me, $tx, (string)($j['kind'] ?? ''), is_array($j['comp'] ?? null) ? $j['comp'] : []); echo json_encode($e === '' ? ['ok' => true] : ['ok' => false, 'msg' => $e]); exit; }
http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'Unknown action. Use save or savecomp.']);
