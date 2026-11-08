<?php
// Favorite Affiliated Businesses as JSON for the signed-in agent (page widgets and assistants via the FLR MCP).
// GET  -> {ok, categories:{key:label}, affiliates:{key:[{id, name, contact, phone, email, website, notes}]}}
// POST JSON {csrf, action:'save', cat, name, contact?, phone?, email?, website?, notes?, id? (to update)} -> {ok, id}
// POST JSON {csrf, action:'delete', id} -> {ok}
require __DIR__ . "/guard.php";
require_once __DIR__ . "/afflib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$me = $_SESSION['email'];
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { echo json_encode(['ok' => true, 'categories' => AF_CATS, 'affiliates' => af_list($me)]); exit; }
$j = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($j['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
$a = (string)($j['action'] ?? '');
if ($a === 'save') { list($id, $e) = af_save($me, $j); echo json_encode($e === '' ? ['ok' => true, 'id' => $id] : ['ok' => false, 'msg' => $e]); exit; }
if ($a === 'delete') { $e = af_delete($me, (int)($j['id'] ?? 0)); echo json_encode($e === '' ? ['ok' => true] : ['ok' => false, 'msg' => $e]); exit; }
http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'Unknown action. Use save or delete.']);
