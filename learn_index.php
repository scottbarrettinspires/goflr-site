<?php
// Saves the words found in a Learn PDF so agents can search inside it. POST id, csrf, text. Called by the Learn page once per PDF.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/learnlib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'msg' => 'POST only.']); exit; }
if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
$e = ln_set_text((int)($_POST['id'] ?? 0), (string)($_POST['text'] ?? ''));
echo json_encode($e === '' ? ['ok' => true] : ['ok' => false, 'msg' => $e]);
