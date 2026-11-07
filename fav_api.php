<?php
// Star / unstar an agent in the Directory. POST JSON {csrf, email, on}. Only agents who are in the directory can be
// starred, and never yourself.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/favlib.php";
header('Content-Type: application/json');
$in = json_decode((string)file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($in) || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($in['csrf'] ?? ''))) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'expired']); exit; }
$email = strtolower(trim((string)($in['email'] ?? '')));
$listed = false; $d = flr_directory();
foreach ((array)($d['agents'] ?? []) as $p) { if (strtolower(trim((string)($p['email'] ?? ''))) === $email) { $listed = true; break; } }
if (!$listed) { echo json_encode(['ok' => false, 'msg' => 'not in directory']); exit; }
echo json_encode(['ok' => fav_set($_SESSION['email'] ?? '', $email, !empty($in['on']))]);
