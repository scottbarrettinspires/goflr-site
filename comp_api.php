<?php
// Staff-only agent income and compensation plans, as JSON (for the page and for assistants via the FLR MCP).
// GET  ?action=overview[&period=month|quarter|ytd|YYYY|all][&status=Active|Inactive|all] -> {ok, agents:[...], totals, notes}
// GET  ?action=agent&agent_id=A-0001[&period=] -> {ok, agent:{...}} (same item as overview, with closed files)
// POST JSON {action:'save', csrf, agent_id, plan_name, agent_split_pct, cap_amount, monthly_fee, sponsor_agent_id, revshare_pct, notes} -> {ok} or {ok:false, msg}
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/complib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (!flr_is_broker()) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Staff only.']); exit; }
$j = json_decode((string)file_get_contents('php://input'), true); if (!is_array($j)) $j = $_POST;
$action = (string)($_GET['action'] ?? ($j['action'] ?? 'overview'));
$period = (string)($_GET['period'] ?? 'ytd'); if (!in_array($period, ['month', 'quarter', 'ytd', 'all'], true) && !preg_match('/^\d{4}$/', $period)) $period = 'ytd';
$status = (string)($_GET['status'] ?? 'Active'); if (strtolower($status) === 'all') $status = '';
if ($action === 'save') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($j['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
    $e = cp_save(trim((string)($j['agent_id'] ?? '')), $j, $_SESSION['email'] ?? '');
    echo json_encode($e === '' ? ['ok' => true, 'agent_id' => trim((string)$j['agent_id'])] : ['ok' => false, 'msg' => $e]); exit;
}
$o = cp_overview($period, $status);
if ($action === 'agent') {
    $id = trim((string)($_GET['agent_id'] ?? ''));
    foreach (cp_overview($period, '')['agents'] as $a) if ($a['agent_id'] === $id) { echo json_encode(['ok' => true, 'from' => $o['from'], 'to' => $o['to'], 'agent' => $a]); exit; }
    echo json_encode(['ok' => false, 'msg' => 'No agent record with that Agent ID.']); exit;
}
echo json_encode($o);
