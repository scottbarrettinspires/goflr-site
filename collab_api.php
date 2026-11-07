<?php
// React to a Collaboration post without reloading the page. POST JSON {csrf, like: <post id>, kind: 'love' | ... | ''}.
// Sending the reaction you already have (or '') removes it.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php"; // flr_is_broker()
require_once __DIR__ . "/collablib.php";
header('Content-Type: application/json');
$in = json_decode((string)file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($in) || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($in['csrf'] ?? ''))) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'expired']); exit; }
$r = co_react((int)($in['like'] ?? 0), (string)($in['kind'] ?? ''));
echo json_encode($r ? ['ok' => true] + $r : ['ok' => false, 'msg' => 'gone']);
