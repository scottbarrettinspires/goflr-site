<?php
// Lead manager: storage (one private file per agent) and the nurture-plan engine.
// Model: NEW (not sorted yet) -> Buyers / Sellers / Sphere, each with sub-categories that pick the plan.
require_once __DIR__ . '/lib.php';

const LD_CATS = [
    'buyer'  => ['label' => 'Buyers',  'subs' => ['ready' => 'Ready', 'soon' => 'Soon', 'later' => 'Later', 'unid' => 'Unidentified', 'dnc' => 'Do Not Contact']],
    'seller' => ['label' => 'Sellers', 'subs' => ['ready' => 'Ready', 'soon' => 'Soon', 'later' => 'Later', 'unid' => 'Unidentified', 'dnc' => 'Do Not Contact']],
    'sphere' => ['label' => 'Sphere',  'subs' => ['a' => 'A Yes', 'b' => 'B Likely', 'c' => 'C Maybe', 'd' => 'D Maybe Not', 'dnc' => 'Do Not Contact']],
];
// Plan for each role + sub-category: message prefix, loop-from step (null = no loop), fixed spacing in days (null = use each message's own spacing).
const LD_PLANS = [
    'new'    => ['P1+P2', null, null],
    'buyer'  => ['ready' => ['P3-B', 9, null], 'soon' => ['P4-B', 1, null], 'later' => ['P5-B', 1, null], 'unid' => ['P10-B', 8, null]],
    'seller' => ['ready' => ['P3-S', 9, null], 'soon' => ['P4-S', 1, null], 'later' => ['P5-S', 1, null], 'unid' => ['P10-S', 8, null]],
    'sphere' => ['a' => ['P7', 1, 30], 'b' => ['P7', 1, 45], 'c' => ['P7', 1, 60], 'd' => ['P7', 1, 90]],
];
const LD_HEAT = ['new' => 0, 'ready' => 1, 'a' => 1, 'soon' => 2, 'b' => 2, 'later' => 3, 'c' => 3, 'unid' => 4, 'd' => 4, 'dnc' => 9];
const LD_LOFTY = ['ready' => 'Hot', 'soon' => 'Warm', 'later' => 'Nurturing/Cold', 'unid' => 'Nurturing/Cold', 'a' => 'Sphere Of Influence', 'b' => 'Sphere Of Influence', 'c' => 'Sphere Of Influence', 'd' => 'Sphere Of Influence', 'dnc' => 'Do not contact'];

function ld_today() { return (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d'); }
function ld_days($a, $b) { return (int)round((strtotime($b . ' 12:00') - strtotime($a . ' 12:00')) / 86400); }
function ld_add($d, $n) { return date('Y-m-d', strtotime($d . ' 12:00') + $n * 86400); }

function ld_dir() {
    $d = __DIR__ . '/leads';
    if (!is_dir($d)) { @mkdir($d, 0755, true); }
    if (!is_file($d . '/.htaccess')) { @file_put_contents($d . '/.htaccess', "Require all denied\n"); }
    return $d;
}
function ld_file($email) { return ld_dir() . '/' . sha1(strtolower(trim($email))) . '.json'; }
function ld_load($email) {
    $j = json_decode((string)@file_get_contents(ld_file($email)), true);
    return is_array($j) ? $j : ['leads' => []];
}
// Read-modify-write under a lock so two tabs cannot overwrite each other.
function ld_update($email, callable $fn) {
    $f = ld_file($email); $h = fopen($f, 'c+');
    if (!$h) return null;
    flock($h, LOCK_EX);
    $raw = stream_get_contents($h); $db = json_decode((string)$raw, true); if (!is_array($db)) $db = ['leads' => []];
    $res = $fn($db);
    if (is_file($f) && filesize($f) > 0) { @copy($f, $f . '.bak'); }
    ftruncate($h, 0); rewind($h); fwrite($h, json_encode($db)); fflush($h);
    flock($h, LOCK_UN); fclose($h);
    return $res;
}

function ld_msgs() {
    static $m = null;
    if ($m === null) {
        $m = [];
        foreach ((array)json_decode((string)@file_get_contents(__DIR__ . '/leadmsgs.json'), true) as $r) {
            $m[] = ['id' => $r[0], 'plan' => $r[1], 'step' => $r[2], 'ch' => $r[3], 'subj' => $r[4], 'text' => $r[5], 'goal' => $r[6], 'days' => $r[7]];
        }
    }
    return $m;
}
function ld_plan_msgs($code) {
    $out = [];
    foreach (explode('+', $code) as $part) {
        foreach (ld_msgs() as $m) {
            $ok = strpos($part, '-') !== false ? strpos($m['id'], $part . '-') === 0 : ($m['plan'] === $part);
            if ($ok) $out[] = $m;
        }
    }
    return $out;
}

// Next touch for one role of a lead. Returns null when nothing is scheduled (Do Not Contact, or a plan that has ended).
function ld_next($role, $r) {
    $sub = $r['sub'] ?? 'new';
    if ($sub === 'dnc') return null;
    $p = $role === 'new' ? LD_PLANS['new'] : (LD_PLANS[$role][$sub] ?? null);
    if (!$p) return null;
    $msgs = ld_plan_msgs($p[0]); $n = (int)($r['touches'] ?? 0); $len = count($msgs);
    if (!$len) return null;
    if ($n < $len) { $i = $n; }
    elseif ($p[1]) { $span = $len - $p[1] + 1; $i = $p[1] - 1 + (($n - $len) % $span); }
    else { return ['done' => true, 'plan' => $p[0], 'due' => $r['last'] ?? $r['since'] ?? ld_today(), 'step' => $n]; }
    $m = $msgs[$i];
    // First touch: counted from the day the lead entered this plan (Sphere starts right away). Later touches: from the last touch.
    if ($n === 0) { $due = ld_add($r['since'] ?? ld_today(), $p[2] !== null ? 0 : (int)$m['days']); }
    else { $due = ld_add(($r['last'] ?? '') ?: ($r['since'] ?? ld_today()), $p[2] !== null ? $p[2] : (int)$m['days']); }
    return ['plan' => $p[0], 'msg' => $m, 'step' => $n + 1, 'of' => $len, 'due' => $due];
}

function ld_fill($text, $lead, $agent, $phone) {
    return strtr($text, ['{First}' => trim($lead['first'] ?? '') ?: 'there', '{Agent}' => $agent, '{Phone}' => $phone]);
}

// Everything the page needs: each lead with its roles' next touches and a "hot" score.
function ld_view($email, $agent, $phone) {
    $db = ld_load($email); $today = ld_today(); $out = [];
    foreach ($db['leads'] as $L) {
        $roles = [];
        $active = array_filter(['buyer', 'seller', 'sphere'], function ($k) use ($L) { return !empty($L['roles'][$k]); });
        $list = $active ? $active : ['new'];
        foreach ($list as $k) {
            $r = $k === 'new' ? ($L['roles']['new'] ?? ['since' => $L['added'] ?? $today, 'touches' => 0]) : $L['roles'][$k];
            if ($k === 'new') $r['sub'] = 'new';
            $nx = ld_next($k, $r); $v = ['role' => $k, 'sub' => $r['sub'], 'touches' => (int)($r['touches'] ?? 0), 'last' => $r['last'] ?? '', 'since' => $r['since'] ?? ''];
            if ($nx && empty($nx['done'])) {
                $v += ['due' => $nx['due'], 'over' => ld_days($nx['due'], $today), 'step' => $nx['step'], 'of' => $nx['of'], 'plan' => $nx['plan'], 'mid' => $nx['msg']['id'], 'ch' => $nx['msg']['ch'],
                       'subj' => ld_fill($nx['msg']['subj'], $L, $agent, $phone), 'text' => ld_fill($nx['msg']['text'], $L, $agent, $phone), 'goal' => $nx['msg']['goal']];
            } elseif ($nx) { $v += ['done' => true, 'plan' => $nx['plan']]; }
            $v['lofty'] = $k === 'new' ? (($r['touches'] ?? 0) < 4 ? 'New Leads' : 'Attempting Contact') : (LD_LOFTY[$r['sub']] ?? '');
            $roles[] = $v;
        }
        $L['view'] = $roles; $out[] = $L;
    }
    return $out;
}

function ld_clean($s, $max = 200) { return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$s)), 0, $max); }

// The signed-in agent's name and phone for {Agent} and {Phone}.
function ld_me() {
    $email = strtolower($_SESSION['email'] ?? ''); $name = trim($_SESSION['name'] ?? ''); $phone = '';
    $d = flr_directory();
    if ($d) { foreach (array_merge((array)($d['agents'] ?? []), (array)($d['staff'] ?? [])) as $p) {
        if (($email !== '' && strtolower($p['email'] ?? '') === $email) || ($name !== '' && strcasecmp($p['name'] ?? '', $name) === 0)) { if (!empty($p['phone'])) { $phone = $p['phone']; break; } }
    } }
    return [$name ?: 'your agent', $phone];
}
