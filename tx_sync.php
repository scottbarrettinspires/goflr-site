<?php
// Keeps the server's transaction index in step with the agent's Active Business folders in Drive,
// links each record to its lead, and carries stage moves made in Drive back to the lead. Called in the background by pages.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/sigtools.php";
require_once __DIR__ . "/leadlib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
@set_time_limit(90);
$me = strtolower($_SESSION['email'] ?? '');
session_write_close(); // do not hold the session while Drive answers
$pdo = flr_db(); $ak = flr_akey($me); $k = 'txsync_' . $ak;
$last = (int)$pdo->query('SELECT v FROM kv WHERE k = ' . $pdo->quote($k))->fetchColumn();
if (empty($_GET['force']) && $last > time() - 600) { echo json_encode(['ok' => true, 'skipped' => true]); exit; }
$pdo->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)')->execute([$k, (string)time()]);
$root = flr_get_json(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=mytxroot&email=' . urlencode($me));
$hint = (is_array($root) && !empty($root['ok']) && !empty($root['id'])) ? (string)$root['id'] : '';
$folders = sg_folders($me, $hint);
if (!is_array($folders)) { $pdo->prepare('DELETE FROM kv WHERE k = ?')->execute([$k]); echo json_encode(['ok' => false, 'msg' => 'Active Business did not answer: ' . ($GLOBALS['sg_folders_note'] ?? '')]); exit; }
list($n, $moved) = flr_tx_sync($me, $folders);
// Link records to leads, and bring Drive stage moves back into the lead.
$tx = []; foreach (flr_tx_list($me, false) as $t) $tx[$t['folder']] = $t;
$changed = [];
ld_update($me, function (&$db) use ($tx, $moved, &$changed) {
    $today = ld_today();
    foreach ($db['leads'] as &$L) {
        foreach (['buyer', 'seller'] as $side) {
            $fid = (string)($L['roles'][$side]['folder'] ?? ''); if ($fid === '' || !isset($tx[$fid])) continue;
            if ((string)$tx[$fid]['lead_id'] !== (string)$L['id']) flr_tx_set($fid, ['lead_id' => (string)$L['id']]);
            if (!in_array($fid, $moved, true) || ($L['roles'][$side]['sub'] ?? '') !== 'client' || !(int)$tx[$fid]['listed']) continue;
            $st = (int)$tx[$fid]['stage']; $valid = $side === 'seller' ? [1, 2, 3] : [1, 3];
            if (!in_array($st, $valid, true) || (int)($L['roles'][$side]['stage'] ?? 1) === $st) continue;
            $L['roles'][$side]['stage'] = $st; $L['roles'][$side]['touches'] = 0; $L['roles'][$side]['last'] = ''; $L['roles'][$side]['since'] = $today;
            $L['log'][] = ['d' => $today, 'role' => $side, 'mid' => '', 'note' => 'Stage: ' . ld_stage_name($side, $st) . ' (moved in Active Business)'];
            $changed[] = $L['id'];
        }
    }
    return null;
});
echo json_encode(['ok' => true, 'records' => count($folders), 'updated' => $n, 'leads_moved' => $changed]);
