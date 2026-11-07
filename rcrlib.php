<?php
// Recruiting CRM: storage and the nurture-plan engine for recruiting agents to the brokerage.
// Near-duplicate of leadlib.php's model, but for ONE shared brokerage-wide pipeline (not per-agent), with a single
// status track per recruit instead of buyer/seller/sphere roles. Content (statuses, timeframes, plans R1-R12 and
// every message) comes from "FLR Recruiting CRM - Agent Prospects (DRAFT, private)", the sheet Scott already wrote
// and reviewed -- nothing here invents compensation, fee or legal language.
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/flrdb.php';
date_default_timezone_set('America/New_York');

// One shared pipeline for the whole brokerage: every recruit lives under this single akey, not a per-agent one.
const RCR_AKEY = 'recruiting';

const RCR_TYPES = [
    'other_brokerage'   => 'Licensed - Other Brokerage',
    'new_prelicense'    => 'New / Pre-License',
    'new_not_active'    => 'New Licensee - Not Yet Active',
    'new_joined_other'  => 'New Licensee - Joined Another Brokerage',
    'past_inactive'     => 'Past / Inactive',
    'flr_retention'     => 'FLR Retention',
];
// Status funnel, in sheet order, with the sort weight used to order the Today list (higher = worked first).
const RCR_STATUS = [
    'new'            => ['label' => 'New', 'weight' => 1000],
    'attempting'     => ['label' => 'Attempting', 'weight' => 500],
    'connected'      => ['label' => 'Connected', 'weight' => 1500],
    'meeting'        => ['label' => 'Lunch/Meeting Set', 'weight' => 3000],
    'roundtable'     => ['label' => 'Attended Round Table', 'weight' => 2500],
    'considering'    => ['label' => 'Considering', 'weight' => 2000],
    'joined'         => ['label' => 'Joined', 'weight' => 0],
    'not_now'        => ['label' => 'Not Now', 'weight' => 100],
    'not_interested' => ['label' => 'Not Interested', 'weight' => 0],
    'dnc'            => ['label' => 'Do Not Contact', 'weight' => 0],
];
const RCR_WHEN = ['0-3' => '0-3 months', '3-6' => '3-6 months', '6-12' => '6-12 months', '12+' => '12+ months', 'unknown' => 'Unknown'];
// A status this person has settled into; no further drip is scheduled.
const RCR_CLOSED = ['joined', 'not_interested', 'dnc'];
// Plan code => [label, loop-from-step (1-based, null = no loop)]. Matches the Plans tab exactly.
const RCR_PLANS = [
    'R1'  => ['First Touches', null],
    'R2'  => ['Attempting Contact', null],
    'R3'  => ['Connected, Moving Soon', 2],
    'R4'  => ['Meeting Set', null],
    'R5'  => ['Attended Round Table', null],
    'R6'  => ['Considering', 1],
    'R7'  => ['Nurture, 3 to 12 months or Unknown', 1],
    'R8'  => ['Long-Term, 12+ months', 1],
    'R9'  => ['Trigger Date Approach', null],
    'R10' => ['FLR Agent Retention', 1],
    'R11' => ['New Licensee Path', null],
    'R12' => ['Joined Another Brokerage, Stay Friendly', 4],
];
// Days-before-the-trigger-date for each of R9's 4 steps (from the Messages tab's "Days before Trigger Date" column).
const RCR_R9_BEFORE = [60, 30, 14, 0];
// A prospect this close to (or past) their Trigger Date gets R9 instead of the usual timeframe plan.
const RCR_R9_WINDOW_DAYS = 60;

function rcr_today() { return (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d'); }
function rcr_days($a, $b) { return (int)round((strtotime($b . ' 12:00') - strtotime($a . ' 12:00')) / 86400); }
function rcr_add($d, $n) { return date('Y-m-d', strtotime($d . ' 12:00') + $n * 86400); }
function rcr_clean($s, $max = 200) { return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$s)), 0, $max); }

function rcr_load() { return flr_leads_load(RCR_AKEY); }
function rcr_update(callable $fn) { return flr_leads_update(RCR_AKEY, $fn); }

function rcr_msgs() {
    static $m = null;
    if ($m === null) {
        $m = [];
        foreach ((array)json_decode((string)@file_get_contents(__DIR__ . '/rcrmsgs.json'), true) as $r) {
            $m[] = ['id' => $r[0], 'plan' => $r[1], 'step' => (int)$r[2], 'ch' => $r[3], 'subj' => $r[4], 'text' => $r[5], 'goal' => $r[6], 'days' => (int)$r[7]];
        }
    }
    return $m;
}
function rcr_plan_msgs($code) {
    $out = [];
    foreach (rcr_msgs() as $m) { if ($m['plan'] === $code) $out[] = $m; }
    usort($out, function ($a, $b) { return $a['step'] - $b['step']; });
    return $out;
}

// Which plan code applies, from Prospect Type + Status + When Could They Move + Trigger Date, mirroring the
// "Who It's For" column on the Plans tab, checked in priority order (meeting/decision stages first).
function rcr_plan_code($type, $status, $when, $triggerDate) {
    if (in_array($status, RCR_CLOSED, true)) return null;
    if ($type === 'flr_retention') return 'R10';
    if (in_array($type, ['new_prelicense', 'new_not_active'], true) && in_array($status, ['new', 'attempting', 'connected'], true)) return 'R11';
    if ($type === 'new_joined_other' && in_array($status, ['new', 'attempting', 'connected'], true)) return 'R12';
    if ($status === 'meeting') return 'R4';
    if ($status === 'roundtable') return 'R5';
    if ($status === 'considering') return 'R6';
    if ($triggerDate) { $dLeft = rcr_days(rcr_today(), $triggerDate); if ($dLeft <= RCR_R9_WINDOW_DAYS) return 'R9'; }
    if ($status === 'new') return 'R1';
    if ($status === 'attempting') return 'R2';
    if ($status === 'connected' && $when === '0-3') return 'R3';
    if (in_array($status, ['connected', 'not_now'], true) && in_array($when, ['3-6', '6-12', 'unknown', ''], true)) return 'R7';
    if (in_array($status, ['connected', 'not_now'], true) && $when === '12+') return 'R8';
    return null;
}

function rcr_fill($text, $rec, $agent, $phone) {
    return strtr($text, ['{First}' => trim($rec['first'] ?? '') ?: 'there', '{Agent}' => $agent, '{Phone}' => $phone, '{Trigger}' => trim($rec['trigger_reason'] ?? '') ?: 'upcoming date']);
}

// Next scheduled touch for one recruit record. Null = nothing scheduled (closed status, or no plan fits).
function rcr_next($rec) {
    $status = $rec['status'] ?? 'new'; $type = $rec['type'] ?? 'other_brokerage'; $when = $rec['when'] ?? ''; $trig = $rec['trigger_date'] ?? '';
    $code = rcr_plan_code($type, $status, $when, $trig);
    if (!$code) return null;
    $today = rcr_today(); $n = (int)($rec['touches'] ?? 0);
    if ($code === 'R9') {
        if (!$trig) return null;
        if ($n >= 4) return ['done' => true, 'plan' => 'R9', 'due' => $rec['last'] ?: $rec['since'] ?: $today, 'step' => $n];
        $msgs = rcr_plan_msgs('R9'); $m = $msgs[$n] ?? null; if (!$m) return null;
        $due = rcr_add($trig, -RCR_R9_BEFORE[$n]);
        return ['plan' => 'R9', 'msg' => $m, 'step' => $n + 1, 'of' => 4, 'due' => $due];
    }
    $msgs = rcr_plan_msgs($code); $len = count($msgs); if (!$len) return null;
    $loopFrom = RCR_PLANS[$code][1];
    if ($n < $len) { $i = $n; }
    elseif ($loopFrom) { $span = $len - $loopFrom + 1; $i = $loopFrom - 1 + (($n - $len) % $span); }
    else { return ['done' => true, 'plan' => $code, 'due' => $rec['last'] ?: $rec['since'] ?: $today, 'step' => $n]; }
    $m = $msgs[$i];
    if ($n === 0) { $due = rcr_add($rec['since'] ?: $today, (int)$m['days']); }
    else { $gd = (int)$m['days']; if ($gd === 0) { $gd = max(array_map(function ($x) { return (int)$x['days']; }, $msgs)); }
           $due = rcr_add($rec['last'] ?: ($rec['since'] ?: $today), $gd); }
    return ['plan' => $code, 'msg' => $m, 'step' => $n + 1, 'of' => $len, 'due' => $due];
}

// The broker/admin's name and phone for {Agent} and {Phone} -- whoever is signed in and working the pipeline.
function rcr_me() {
    $email = strtolower($_SESSION['email'] ?? ''); $name = trim($_SESSION['name'] ?? ''); $phone = '';
    $d = flr_directory();
    if ($d) { foreach (array_merge((array)($d['agents'] ?? []), (array)($d['staff'] ?? [])) as $p) {
        if (($email !== '' && strtolower($p['email'] ?? '') === $email) || ($name !== '' && strcasecmp($p['name'] ?? '', $name) === 0)) { if (!empty($p['phone'])) { $phone = $p['phone']; break; } }
    } }
    return [$name ?: 'FLR', $phone];
}

// Everything the recruiting page needs: every recruit plus its computed next touch.
function rcr_view($agent, $phone) {
    $db = rcr_load(); $today = rcr_today(); $out = [];
    foreach ($db['recruits'] ?? [] as $R) {
        $nx = rcr_next($R);
        if ($nx && empty($nx['done'])) {
            $R['due'] = $nx['due']; $R['over'] = rcr_days($nx['due'], $today); $R['step'] = $nx['step']; $R['of'] = $nx['of']; $R['plan'] = $nx['plan'];
            $R['mid'] = $nx['msg']['id']; $R['ch'] = $nx['msg']['ch']; $R['subj'] = rcr_fill($nx['msg']['subj'], $R, $agent, $phone); $R['text'] = rcr_fill($nx['msg']['text'], $R, $agent, $phone); $R['goal'] = $nx['msg']['goal'];
        } elseif ($nx) { $R['done'] = true; $R['plan'] = $nx['plan']; }
        $out[] = $R;
    }
    return $out;
}

// ---- Manual add / edit ----
function rcr_save($in, $id = '') {
    $today = rcr_today();
    return rcr_update(function (&$db) use ($in, $id, $today) {
        if (!isset($db['recruits']) || !is_array($db['recruits'])) $db['recruits'] = [];
        $f = ['first' => rcr_clean($in['first'] ?? '', 60), 'last' => rcr_clean($in['last'] ?? '', 60), 'phone' => rcr_clean($in['phone'] ?? '', 30), 'email' => rcr_clean($in['email'] ?? '', 120),
              'type' => array_key_exists($in['type'] ?? '', RCR_TYPES) ? $in['type'] : 'other_brokerage', 'current_brokerage' => rcr_clean($in['current_brokerage'] ?? '', 80),
              'source' => rcr_clean($in['source'] ?? '', 60), 'owner' => rcr_clean($in['owner'] ?? '', 60), 'referred_by' => rcr_clean($in['referred_by'] ?? '', 80),
              'when' => array_key_exists($in['when'] ?? '', RCR_WHEN) ? $in['when'] : '', 'trigger_date' => rcr_clean($in['trigger_date'] ?? '', 10), 'trigger_reason' => rcr_clean($in['trigger_reason'] ?? '', 60),
              'notes' => rcr_clean($in['notes'] ?? '', 4000)];
        if ($f['first'] === '' && $f['last'] === '' && $f['phone'] === '' && $f['email'] === '') return 'Add at least a name, phone or email.';
        $i = -1; if ($id !== '') { foreach ($db['recruits'] as $k => $R) { if (($R['id'] ?? '') === $id) { $i = $k; break; } } }
        $status = array_key_exists($in['status'] ?? '', RCR_STATUS) ? $in['status'] : null;
        if ($i < 0) {
            $rid = bin2hex(random_bytes(6));
            $R = $f + ['id' => $rid, 'added' => $today, 'created' => date('c'), 'status' => $status ?: 'new', 'since' => $today, 'touches' => 0, 'last' => '', 'log' => [['d' => $today, 'note' => 'Added to the recruiting pipeline.']]];
            array_unshift($db['recruits'], $R);
        } else {
            $R = $db['recruits'][$i]; $R = array_merge($R, $f);
            if ($status && $status !== ($R['status'] ?? 'new')) { $R['log'][] = ['d' => $today, 'note' => 'Status: ' . (RCR_STATUS[$R['status']]['label'] ?? $R['status']) . ' -> ' . RCR_STATUS[$status]['label']]; $R['status'] = $status; $R['since'] = $today; $R['touches'] = 0; $R['last'] = ''; }
            $db['recruits'][$i] = $R;
        }
        return null;
    });
}
function rcr_touch($id, $note = '') {
    $today = rcr_today();
    return rcr_update(function (&$db) use ($id, $today, $note) {
        foreach ($db['recruits'] as &$R) { if (($R['id'] ?? '') === $id) {
            $R['touches'] = (int)($R['touches'] ?? 0) + 1; $R['last'] = $today;
            $R['log'][] = ['d' => $today, 'note' => rcr_clean($note, 300) ?: 'Touch logged.']; $R['log'] = array_slice($R['log'], -200);
            return null;
        } }
        return 'That recruit was not found.';
    });
}
function rcr_delete($id) {
    return rcr_update(function (&$db) use ($id) {
        foreach ($db['recruits'] as $i => $R) { if (($R['id'] ?? '') === $id) { array_splice($db['recruits'], $i, 1); return null; } }
        return 'That recruit was not found.';
    });
}
function rcr_find($id) { foreach (rcr_load()['recruits'] ?? [] as $R) { if (($R['id'] ?? '') === $id) return $R; } return null; }

// ---- Private intake link, same mechanism as the agent lead link (flr_intake_lookup / agents.intake_key) ----
function rcr_intake_ensure() {
    return rcr_update(function (&$db) {
        if (empty($db['intake']['key'])) { $db['intake'] = ['key' => substr(sha1(RCR_AKEY), 0, 12) . bin2hex(random_bytes(10)), 'created' => date('c')]; }
        return $db['intake'];
    });
}
// Looked up the same way as an agent's lead-intake key (flr_intake_lookup), since both live in agents.intake_key --
// but every key here resolves to the one shared RCR_AKEY row, not a per-agent one.
function rcr_intake_find($key) {
    if (!preg_match('/^[a-f0-9]{32}$/', (string)$key)) return null;
    $r = flr_intake_lookup($key); if (!$r || $r['akey'] !== RCR_AKEY || empty($r['intake']['key']) || !hash_equals($r['intake']['key'], $key)) return null;
    return $r['intake'];
}
// Folder for the public intake endpoint's own files (rate-limit counters only -- recruit records live in the DB).
function rcr_dir() {
    $d = __DIR__ . '/leads'; // reuse the same locked-down folder the agent lead intake already uses
    if (!is_dir($d)) { @mkdir($d, 0755, true); }
    if (!is_file($d . '/.htaccess')) { @file_put_contents($d . '/.htaccess', "Require all denied\n"); }
    return $d;
}
function rcr_intake_reset() {
    return rcr_update(function (&$db) {
        $db['intake']['key'] = substr(sha1(RCR_AKEY), 0, 12) . bin2hex(random_bytes(10)); $db['intake']['created'] = date('c');
        return $db['intake'];
    });
}
// Adds an incoming recruit as New/Licensed-Other-Brokerage by default, or merges into an existing match by email/phone.
// $in['referred_by'] carries an agent's hand-off (the agent's name, from the co-op/outside-agent introduction flow).
function rcr_intake_add($in) {
    $today = rcr_today();
    return rcr_update(function (&$db) use ($in, $today) {
        if (!isset($db['recruits']) || !is_array($db['recruits'])) $db['recruits'] = [];
        $digits = function ($p) { $d = preg_replace('/\D/', '', (string)$p); return strlen($d) === 11 && $d[0] === '1' ? substr($d, 1) : $d; };
        foreach ($db['recruits'] as &$R) {
            $same = ($in['email'] !== '' && strcasecmp($R['email'] ?? '', $in['email']) === 0) || ($in['phone'] !== '' && strlen($digits($in['phone'])) >= 7 && $digits($R['phone'] ?? '') === $digits($in['phone']));
            if (!$same) continue;
            $R['notes'] = trim(($R['notes'] ?? '') . "\n" . date('M j') . ' new inquiry via ' . $in['source'] . ($in['message'] ? ': ' . $in['message'] : '.'));
            $R['log'][] = ['d' => $today, 'note' => 'New inquiry via ' . $in['source']];
            return ['dup' => true, 'id' => $R['id']];
        }
        unset($R);
        $rid = bin2hex(random_bytes(6));
        array_unshift($db['recruits'], ['id' => $rid, 'first' => $in['first'], 'last' => $in['last'], 'phone' => $in['phone'], 'email' => $in['email'],
            'type' => array_key_exists($in['type'] ?? '', RCR_TYPES) ? $in['type'] : 'other_brokerage', 'current_brokerage' => $in['current_brokerage'] ?? '',
            'source' => $in['source'], 'referred_by' => $in['referred_by'] ?? '', 'owner' => '', 'when' => '', 'trigger_date' => '', 'trigger_reason' => '',
            'notes' => $in['message'] ?? '', 'added' => $today, 'created' => date('c'), 'status' => 'new', 'since' => $today, 'touches' => 0, 'last' => '',
            'log' => [['d' => $today, 'note' => 'Came in via ' . $in['source'] . ($in['referred_by'] ? ' (handed off by ' . $in['referred_by'] . ')' : '')]]]);
        return ['dup' => false, 'id' => $rid];
    });
}
