<?php
// Register / un-register for a training event. POST JSON {csrf, key: <event key>, on: true|false}.
// Returns the redrawn widget so every copy of it on the page (Home card, Training list) updates at once.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php"; // flr_is_broker()
require_once __DIR__ . "/evreglib.php";
header('Content-Type: application/json');
$in = json_decode((string)file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($in) || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($in['csrf'] ?? ''))) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
$key = (string)($in['key'] ?? '');
$err = er_set($key, !empty($in['on']));
if ($err !== '') { echo json_encode(['ok' => false, 'msg' => $err]); exit; }
$e = flr_event_by_key($key);
echo json_encode(['ok' => true, 'html' => er_widget($e, er_for([$key])[$key] ?? [])]);
