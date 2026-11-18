<?php
// Send the buyer-signed offer documents to the listing agent as a download link.
// GET ?tx=  -> the plan (listing agent from the offer data, signed documents, draft subject/message with {LINK}).
// POST csrf, tx, action=send (email through the agent's Gmail) | link (make the link only; the page then opens the agent's own email app) | marksent;
//      refs[] (document refs from the plan), to, subject, body, copy (1 = copy the agent). JSON replies.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/sharelib.php";
header('Content-Type: application/json');
$me = $_SESSION['email'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(sh_plan($me, (string)($_GET['tx'] ?? ''))); exit; }
if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
$tx = (string)($_POST['tx'] ?? ''); $refs = (array)($_POST['refs'] ?? []); $a = (string)($_POST['action'] ?? 'send');
if ($a === 'marksent') { sh_mark_sent($me, $tx, (string)($_POST['to'] ?? '')); echo json_encode(['ok' => true]); exit; }
if ($a === 'link') { $r = sh_create($me, $tx, $refs, (string)($_POST['to'] ?? '')); echo json_encode($r); exit; }
echo json_encode(sh_send($me, $tx, $refs, (string)($_POST['to'] ?? ''), (string)($_POST['subject'] ?? ''), (string)($_POST['body'] ?? ''), !empty($_POST['copy'])));
