<?php
// Lead manager actions for the signed-in agent. Every lead file is private to that agent.
require __DIR__ . "/guard.php";
require __DIR__ . "/leadlib.php";
require_once __DIR__ . "/sigtools.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
function out($a) { echo json_encode($a); exit; }
$me = strtolower($_SESSION['email'] ?? '');
list($agent, $phone) = ld_me();
$warn = '';
ld_autosort($me);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { out(['ok' => true, 'warn' => $warn, 'leads' => ld_view($me, $agent, $phone)]); }
$j = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($j) || !hash_equals($_SESSION['csrf'] ?? '', (string)($j['csrf'] ?? ''))) out(['ok' => false, 'msg' => 'This page expired. Please reload.']);
$act = (string)($j['action'] ?? ''); $today = ld_today();
if ($act === 'sources') {
    $src = ld_src_names(ld_clean_tags($j['sources'] ?? [])); $int = ld_clean_tags($j['interests'] ?? []);
    ld_update($me, function (&$db) use ($src, $int) { $db['sources'] = $src; $db['interests'] = $int; });
    out(['ok' => true, 'sources' => $src, 'interests' => $int, 'leads' => ld_view($me, $agent, $phone)]);
}
if ($act === 'wants') {
    $w = (array)($j['wants'] ?? []); $clean = [];
    foreach ($w as $k => $v) {
        if (!preg_match('/^[a-z_]{1,24}$/', (string)$k)) continue;
        if (is_array($v)) { $clean[$k] = ld_clean_tags($v); } else { $clean[$k] = ld_clean(is_scalar($v) ? $v : '', 600); }
        if (count($clean) >= 40) break;
    }
    $id = (string)($j['id'] ?? ''); $fld = ($j['field'] ?? '') === 'home' ? 'home' : 'wants';
    ld_update($me, function (&$db) use ($id, $clean, $fld) { foreach ($db['leads'] as &$L) { if (($L['id'] ?? '') === $id) { $L[$fld] = $clean; $L[$fld]['updated'] = ld_today(); } } });
    out(['ok' => true, 'leads' => ld_view($me, $agent, $phone)]);
}
if ($act === 'intake_reset') { $ik = ld_intake_reset($me); out(['ok' => true, 'intake' => 'https://goflr.com/lead_in.php?k=' . ($ik['key'] ?? ''), 'leads' => ld_view($me, $agent, $phone)]); }
// Steps that talk to the office script (create a record, move a record folder) run before the lead file is locked.
$pre = [];
$findL = function ($id) use ($me) { foreach (ld_load($me)['leads'] as $x) { if (($x['id'] ?? '') === $id) return $x; } return null; };
if ($act === 'startseller') {
    $L0 = $findL((string)($j['id'] ?? '')); if (!$L0) out(['ok' => false, 'msg' => 'That lead was not found.']);
    $addr = trim(preg_replace('/\s+/', ' ', (string)($j['address'] ?? '')));
    if ($addr === '' || strlen($addr) > 100 || preg_match('/[\\\/:*?"<>|]/', $addr)) out(['ok' => false, 'msg' => 'Enter the property address (no slashes or special characters).']);
    $root = flr_get_json(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=mytxroot&email=' . urlencode($me));
    if (!is_array($root) || empty($root['ok'])) out(['ok' => false, 'msg' => 'Could not find your My Transactions folder.']);
    $nm = trim(($L0['first'] ?? '') . ' ' . ($L0['last'] ?? ''));
    $info = ['s1_name' => $nm, 's1_phone' => (string)($L0['phone'] ?? ''), 's1_email' => (string)($L0['email'] ?? ''), 'property' => $addr, 'lead_source' => (string)($L0['source'] ?? ''), 'lead_added' => (string)($L0['added'] ?? ''), 'lead_notes' => (string)($L0['notes'] ?? ''), 'updated' => gmdate('c')] + ld_pack_for_record($L0, ld_load($me)['leads']);
    $r = sg_script_post(['op' => 'createseller', 'email' => $me, 'hint' => $root['id'], 'name' => $addr, 'info' => $info]);
    if (!is_array($r) || empty($r['ok']) || empty($r['id'])) out(['ok' => false, 'msg' => (is_array($r) && isset($r['active'])) ? 'The office script needs the latest update (v25) before seller records can be created.' : 'Could not create the Pre Listing record' . (is_array($r) && !empty($r['note']) ? ': ' . $r['note'] : '') . '.']);
    $pre = ['folder' => (string)$r['id'], 'record' => $addr];
}
if ($act === 'txmove' || $act === 'closeclient') {
    $L0 = $findL((string)($j['id'] ?? '')); if (!$L0) out(['ok' => false, 'msg' => 'That lead was not found.']);
    $moves = [];
    if ($act === 'txmove') { $rl = (string)($j['role'] ?? ''); $st = (int)($j['stage'] ?? 0);
        $ok = ($rl === 'seller' && in_array($st, [2, 3], true)) || ($rl === 'buyer' && $st === 3);
        if (!$ok || ($L0['roles'][$rl]['sub'] ?? '') !== 'client') out(['ok' => false, 'msg' => 'That move is not available.']);
        $moves[] = [$rl, $st]; }
    else { $only = (string)($j['role'] ?? ''); foreach (['buyer', 'seller'] as $rl) { if (($only === '' || $only === $rl) && ($L0['roles'][$rl]['sub'] ?? '') === 'client') $moves[] = [$rl, 4]; } }
    foreach ($moves as $mv) {
        $fid = (string)($L0['roles'][$mv[0]]['folder'] ?? '');
        $r = $fid !== '' ? sg_script_post(['op' => 'movetx', 'email' => $me, 'folderId' => $fid, 'side' => $mv[0], 'stage' => $mv[1]]) : null;
        $okm = is_array($r) && !empty($r['ok']);
        if ($act === 'txmove' && !$okm) { $warn = 'Stage updated here. The record folder could not be moved in My Transactions yet' . ((is_array($r) && isset($r['active'])) ? ' (the office script needs the v25 update)' : (is_array($r) && !empty($r['note']) ? ': ' . $r['note'] : '')) . ', so move it by hand for now.'; }
        $pre[$mv[0]] = ['ok' => $okm, 'stage' => $mv[1]];
    }
}
$err = ld_update($me, function (&$db) use ($j, $act, $today, $pre) {
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
        if (isset($j['tags'])) { $L['tags'] = ld_src_names(ld_clean_tags($j['tags'])); }
        if (!isset($L['tags'])) { $L['tags'] = ld_auto_tags($L['source'] ?? ''); } // keep the source tag once the lead is saved
        if (isset($j['interests'])) { $L['interests'] = ld_clean_tags($j['interests']); }
        if (array_key_exists('ref', $j)) {
            $ref = (string)$j['ref']; $okRef = '';
            if ($ref !== '' && $ref !== ($L['id'] ?? '')) { foreach ($db['leads'] as $x) { if (($x['id'] ?? '') === $ref) { $okRef = $ref; break; } } }
            if (($L['ref'] ?? '') !== $okRef) { $L['ref'] = $okRef;
                if ($okRef !== '') { $L['tags'] = ld_clean_tags(array_merge(isset($L['tags']) ? (array)$L['tags'] : ld_auto_tags($L['source'] ?? ''), ['Referral'])); $L['log'][] = ['d' => $today, 'role' => 'new', 'mid' => '', 'note' => 'Referred by a Sphere contact']; } }
        }
        elseif ($i < 0) { $L['tags'] = ld_auto_tags($f['source']); }
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
    if ($act === 'startseller') {
        $L = &$db['leads'][$i];
        $L['roles']['seller'] = ['sub' => 'client', 'since' => $today, 'touches' => 0, 'last' => '', 'folder' => $pre['folder'], 'record' => $pre['record'], 'stage' => 1];
        $L['log'][] = ['d' => $today, 'role' => 'seller', 'mid' => '', 'note' => 'Pre Listing record created in My Transactions'];
        return null;
    }
    if ($act === 'txmove') {
        $L = &$db['leads'][$i]; $rl = (string)$j['role']; $L['roles'][$rl]['stage'] = (int)$j['stage'];
        $L['roles'][$rl]['touches'] = 0; $L['roles'][$rl]['last'] = ''; $L['roles'][$rl]['since'] = $today; // a new stage starts its own check-ins
        $L['log'][] = ['d' => $today, 'role' => $rl, 'mid' => '', 'note' => 'Stage: ' . ld_stage_name($rl, (int)$j['stage']) . (empty($pre[$rl]['ok']) ? ' (folder not moved yet)' : ' (record moved)')];
        return null;
    }
    if ($act === 'closeclient') {
        $L = &$db['leads'][$i]; $sp = (string)($j['sphere'] ?? '');
        if (!isset(LD_CATS['sphere']['subs'][$sp]) || $sp === 'dnc') return 'Choose a Sphere category.';
        $only = (string)($j['role'] ?? '');
        foreach (['buyer', 'seller'] as $k) {
            if (($L['roles'][$k]['sub'] ?? '') !== 'client' || ($only !== '' && $only !== $k)) continue;
            $L['closed'][] = ['role' => $k, 'folder' => $L['roles'][$k]['folder'] ?? '', 'record' => $L['roles'][$k]['record'] ?? '', 'date' => $today];
            $L['log'][] = ['d' => $today, 'role' => $k, 'mid' => '', 'note' => 'Closed sale. Moved to Sphere.' . (empty($pre[$k]['ok']) ? ' Move the record folder to Archived by hand.' : ' Record archived.')];
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
ld_autosort($me);
out(['ok' => true, 'warn' => $warn, 'leads' => ld_view($me, $agent, $phone)]);
