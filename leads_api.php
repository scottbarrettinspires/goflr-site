<?php
// Lead manager actions for the signed-in agent. Every lead file is private to that agent.
require __DIR__ . "/guard.php";
require __DIR__ . "/leadlib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
function out($a) { echo json_encode($a); exit; }
$me = strtolower($_SESSION['email'] ?? '');
list($agent, $phone) = ld_me();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { out(['ok' => true, 'leads' => ld_view($me, $agent, $phone)]); }
$j = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($j) || !hash_equals($_SESSION['csrf'] ?? '', (string)($j['csrf'] ?? ''))) out(['ok' => false, 'msg' => 'This page expired. Please reload.']);
$act = (string)($j['action'] ?? ''); $today = ld_today();
$err = ld_update($me, function (&$db) use ($j, $act, $today) {
    $id = (string)($j['id'] ?? '');
    $find = function ($id) use (&$db) { foreach ($db['leads'] as $i => $L) { if (($L['id'] ?? '') === $id) return $i; } return -1; };
    if ($act === 'save') {
        $in = (array)($j['lead'] ?? []);
        $f = ['first' => ld_clean($in['first'] ?? '', 60), 'last' => ld_clean($in['last'] ?? '', 60), 'phone' => ld_clean($in['phone'] ?? '', 30), 'email' => ld_clean($in['email'] ?? '', 120), 'source' => ld_clean($in['source'] ?? '', 60), 'notes' => ld_clean($in['notes'] ?? '', 4000)];
        if ($f['first'] === '' && $f['last'] === '' && $f['phone'] === '' && $f['email'] === '') return 'Add at least a name, phone or email.';
        $i = $id !== '' ? $find($id) : -1;
        if ($i < 0) { $L = ['id' => bin2hex(random_bytes(6)), 'added' => $today, 'created' => date('c'), 'roles' => ['new' => ['since' => $today, 'touches' => 0]], 'log' => []]; }
        else { $L = $db['leads'][$i]; }
        $L = array_merge($L, $f);
        $want = (array)($j['roles'] ?? []);
        foreach (LD_CATS as $k => $c) {
            if (($L['roles'][$k]['sub'] ?? '') === 'client') continue; // in My Transactions: only "closed sale" moves it on
            $sub = (string)($want[$k] ?? '');
            if ($sub === '' || !isset($c['subs'][$sub])) { unset($L['roles'][$k]); continue; }
            if (($L['roles'][$k]['sub'] ?? '') !== $sub) { $L['roles'][$k] = ['sub' => $sub, 'since' => $today, 'touches' => 0, 'last' => '']; }
        }
        if (empty($L['roles']['new'])) { $L['roles']['new'] = ['since' => $L['added'] ?? $today, 'touches' => 0]; }
        if ($i < 0) { array_unshift($db['leads'], $L); } else { $db['leads'][$i] = $L; }
        return null;
    }
    $i = $find($id); if ($i < 0) return 'That lead was not found.';
    if ($act === 'delete') { array_splice($db['leads'], $i, 1); return null; }
    if ($act === 'closeclient') {
        $L = &$db['leads'][$i]; $sp = (string)($j['sphere'] ?? '');
        if (!isset(LD_CATS['sphere']['subs'][$sp]) || $sp === 'dnc') return 'Choose a Sphere category.';
        foreach (['buyer', 'seller'] as $k) {
            if (($L['roles'][$k]['sub'] ?? '') !== 'client') continue;
            $L['closed'][] = ['role' => $k, 'folder' => $L['roles'][$k]['folder'] ?? '', 'record' => $L['roles'][$k]['record'] ?? '', 'date' => $today];
            $L['log'][] = ['d' => $today, 'role' => $k, 'mid' => '', 'note' => 'Closed sale. Moved to Sphere.'];
            unset($L['roles'][$k]);
        }
        $L['roles']['sphere'] = ['sub' => $sp, 'since' => $today, 'touches' => 0, 'last' => ''];
        return null;
    }
    $role = (string)($j['role'] ?? '');
    if (!in_array($role, ['new', 'buyer', 'seller', 'sphere'], true) || empty($db['leads'][$i]['roles'][$role])) return 'Unknown category.';
    $R = &$db['leads'][$i]['roles'][$role];
    if ($act === 'touch') {
        $R['touches'] = (int)($R['touches'] ?? 0) + 1; $R['last'] = $today;
        $db['leads'][$i]['log'][] = ['d' => $today, 'role' => $role, 'mid' => ld_clean($j['mid'] ?? '', 20), 'note' => ld_clean($j['note'] ?? '', 300)];
        $db['leads'][$i]['log'] = array_slice($db['leads'][$i]['log'], -200);
        return null;
    }
    if ($act === 'untouch') {
        if ((int)($R['touches'] ?? 0) < 1) return 'Nothing to undo.';
        $R['touches']--; $log = &$db['leads'][$i]['log'];
        for ($k = count($log) - 1; $k >= 0; $k--) { if ($log[$k]['role'] === $role) { array_splice($log, $k, 1); break; } }
        $R['last'] = '';
        for ($k = count($log) - 1; $k >= 0; $k--) { if ($log[$k]['role'] === $role) { $R['last'] = $log[$k]['d']; break; } }
        return null;
    }
    return 'Unknown action.';
});
if ($err) out(['ok' => false, 'msg' => $err]);
out(['ok' => true, 'leads' => ld_view($me, $agent, $phone)]);
