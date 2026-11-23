<?php
// Staff-only agent income, plans, sponsors and revenue share, as JSON (for the page and for assistants via the FLR MCP).
// GET  ?action=overview[&period=month|quarter|ytd|YYYY|all][&status=Active|Inactive|all] -> {ok, imported, agents:[...], totals, notes}
// GET  ?action=agent&agent_id=A-0001[&period=] -> {ok, agent:{...}} (same item as overview, with its closed files)
// POST JSON {action:'import', csrf, tabs:{"Compensation":{header,rows}, "Sponsees":{...}, ...}} -> {ok, tabs:{name:rows}} or {ok:false, msg}
//      (replaces each tab sent with the pull from the Agent Compensation / FLR Revenue Share sheets; see FM_TABS in complib.php)
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/complib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (!flr_is_broker()) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'Staff only.']); exit; }
$j = json_decode((string)file_get_contents('php://input'), true); if (!is_array($j)) $j = $_POST;
$action = (string)($_GET['action'] ?? ($j['action'] ?? 'overview'));
$period = (string)($_GET['period'] ?? 'ytd'); if (!in_array($period, ['month', 'quarter', 'ytd', 'all'], true) && !preg_match('/^\d{4}$/', $period)) $period = 'ytd';
$status = (string)($_GET['status'] ?? 'Active'); if (strtolower($status) === 'all') $status = '';
if ($action === 'import') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($j['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
    $r = fm_import($j, $_SESSION['email'] ?? '');
    echo json_encode(isset($r['error']) ? ['ok' => false, 'msg' => $r['error']] : ['ok' => true] + $r); exit;
}
if ($action === 'agent') {
    $id = trim((string)($_GET['agent_id'] ?? ''));
    $o = cp_overview($period, '');
    foreach ($o['agents'] as $a) if ($a['agent_id'] === $id) { echo json_encode(['ok' => true, 'from' => $o['from'], 'to' => $o['to'], 'imported' => $o['imported'], 'agent' => $a]); exit; }
    echo json_encode(['ok' => false, 'msg' => 'No agent with that Agent ID on the Compensation sheet.']); exit;
}
echo json_encode(cp_overview($period, $status));
