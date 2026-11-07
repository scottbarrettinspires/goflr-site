<?php
// Lead manager: storage (one private file per agent) and the nurture-plan engine.
// Model: NEW (not sorted yet) -> Buyers / Sellers / Sphere, each with sub-categories that pick the plan.
require_once __DIR__ . '/lib.php';
date_default_timezone_set('America/New_York');

const LD_CATS = [
    'buyer'  => ['label' => 'Buyers',  'subs' => ['ready' => 'Ready', 'soon' => 'Soon', 'later' => 'Later', 'unid' => 'Undetermined', 'dnc' => 'Do Not Contact']],
    'seller' => ['label' => 'Sellers', 'subs' => ['ready' => 'Ready', 'soon' => 'Soon', 'later' => 'Later', 'unid' => 'Undetermined', 'dnc' => 'Do Not Contact']],
    'sphere' => ['label' => 'Sphere',  'subs' => ['a' => 'A Always', 'b' => 'B Likely', 'c' => 'C Maybe', 'd' => 'D Maybe Not', 'dnc' => 'Do Not Contact']],
];
// Plan for each role + sub-category: message prefix, loop-from step (null = no loop), fixed spacing in days (null = use each message's own spacing).
const LD_PLANS = [
    'new'    => ['P1+P2', null, null],
    'buyer'  => ['ready' => ['P3-B', 9, null], 'soon' => ['P4-B', 1, null], 'later' => ['P5-B+P6-B@10', 5, null], 'unid' => ['U+P10-B@8', 7, null]],
    'seller' => ['ready' => ['P3-S', 9, null], 'soon' => ['P4-S', 1, null], 'later' => ['P5-S+P6-S@10', 5, null], 'unid' => ['U+P10-S@8', 7, null]],
    'sphere' => ['a' => ['P7', 1, 30], 'b' => ['P7', 1, 45], 'c' => ['P7', 1, 60], 'd' => ['P7', 1, 90]],
];
const LD_HEAT = ['new' => 0, 'ready' => 1, 'a' => 1, 'unid' => 1, 'soon' => 2, 'b' => 2, 'later' => 3, 'c' => 3, 'd' => 4, 'dnc' => 9];
// What each sub-category means: how close the person is to signing a buyer broker or listing agreement (Sphere: how likely to refer).
const LD_STAGE = [
    'buyer'  => ['ready' => 'Ready to sign a buyer broker agreement and buy within about 3 months.', 'soon' => 'Likely to sign a buyer broker agreement in 3 to 6 months.', 'later' => 'Planning to buy 6 or more months out.', 'unid' => 'Not reached yet. Keep trying every few days until you make contact, then move them to Ready, Soon or Later.', 'dnc' => 'Asked not to be contacted. No messages are sent.'],
    'seller' => ['ready' => 'Ready to sign a listing agreement and sell within about 3 months.', 'soon' => 'Likely to sign a listing agreement in 3 to 6 months.', 'later' => 'Planning to sell 6 or more months out.', 'unid' => 'Not reached yet. Keep trying every few days until you make contact, then move them to Ready, Soon or Later.', 'dnc' => 'Asked not to be contacted. No messages are sent.'],
    'sphere' => ['a' => 'Always: refers people to you whenever they can, and a source of future business.', 'b' => 'Likely to refer or use you.', 'c' => 'Maybe. Stay in touch.', 'd' => 'Maybe not. Light touch only.', 'dnc' => 'Asked not to be contacted. No messages are sent.'],
];

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
// Leads live in the server database (flrdb.php). ld_file() is still the agent's handle (its name is the agent key).
require_once __DIR__ . '/flrdb.php';
function ld_load($email) { return flr_leads_load(flr_akey($email), strtolower(trim((string)$email))); }
// Read-modify-write in one locked database transaction so two tabs cannot overwrite each other.
function ld_update($email, callable $fn) { return flr_leads_update(flr_akey($email), $fn, strtolower(trim((string)$email))); }
function ld_update_file($f, callable $fn) { return flr_leads_update(basename((string)$f, '.json'), $fn); }

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
            $from = 0; $pp = $part;
            if (strpos($pp, '@') !== false) { list($pp, $from) = explode('@', $pp, 2); $from = (int)$from; }
            $ok = (strpos($pp, '-') !== false ? strpos($m['id'], $pp . '-') === 0 : ($m['plan'] === $pp)) && (int)$m['step'] >= $from;
            if ($ok) $out[] = $m;
        }
    }
    return $out;
}

// Next touch for one role of a lead. Returns null when nothing is scheduled (Do Not Contact, or a plan that has ended).
// Which plan applies: the sub-category, plus where the person is in the process (under contract, past client).
function ld_plan_for($role, $r, $lead) {
    $sub = $r['sub'] ?? 'new';
    if ($role === 'new') return LD_PLANS['new'];
    if ($sub === 'client') return ((int)($r['stage'] ?? 1) === 3) ? ['P9', 1, null] : null; // under contract: closing check-ins
    $p = LD_PLANS[$role][$sub] ?? null;
    if ($p && $role === 'sphere' && !empty($lead['closed'])) { $p = ['P8+P7', 1, $p[2], 30]; } // past client: home check-ins and referral asks, first one a month after closing
    return $p;
}
function ld_next($role, $r, $lead = []) {
    $sub = $r['sub'] ?? 'new';
    if ($sub === 'dnc') return null;
    $p = ld_plan_for($role, $r, $lead);
    if (!$p) return null;
    $msgs = ld_plan_msgs($p[0]); $n = (int)($r['touches'] ?? 0); $len = count($msgs);
    if (!$len) return null;
    if ($n < $len) { $i = $n; }
    elseif ($p[1]) { $span = $len - $p[1] + 1; $i = $p[1] - 1 + (($n - $len) % $span); }
    else { return ['done' => true, 'plan' => $p[0], 'due' => $r['last'] ?? $r['since'] ?? ld_today(), 'step' => $n]; }
    $m = $msgs[$i];
    // First touch: counted from the day the lead entered this plan (Sphere starts right away). Later touches: from the last touch.
    if ($n === 0) { $due = ld_add($r['since'] ?? ld_today(), isset($p[3]) ? $p[3] : ($p[2] !== null ? 0 : (int)$m['days'])); }
    else { $gd = (int)$m['days']; if ($gd === 0) { $gd = max(array_map(function ($x) { return (int)$x['days']; }, $msgs)); } // a repeat of a "same day" message waits the plan's usual gap
           $due = ld_add(($r['last'] ?? '') ?: ($r['since'] ?? ld_today()), $p[2] !== null ? $p[2] : $gd); }
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
            $nx = ld_next($k, $r, $L); $v = ['role' => $k, 'sub' => $r['sub'], 'touches' => (int)($r['touches'] ?? 0), 'last' => $r['last'] ?? '', 'since' => $r['since'] ?? '', 'folder' => $r['folder'] ?? '', 'record' => $r['record'] ?? '', 'stage' => (int)($r['stage'] ?? 1), 'stagename' => ld_stage_name($k, $r['stage'] ?? 1)];
            if ($nx && empty($nx['done'])) {
                $v += ['due' => $nx['due'], 'over' => ld_days($nx['due'], $today), 'step' => $nx['step'], 'of' => $nx['of'], 'plan' => $nx['plan'], 'mid' => $nx['msg']['id'], 'ch' => $nx['msg']['ch'],
                       'subj' => ld_fill($nx['msg']['subj'], $L, $agent, $phone), 'text' => ld_fill($nx['msg']['text'], $L, $agent, $phone), 'goal' => $nx['msg']['goal']];
            } elseif ($nx) { $v += ['done' => true, 'plan' => $nx['plan']]; }
            $roles[] = $v;
        }
        $L['tags'] = ld_src_names(isset($L['tags']) ? $L['tags'] : ld_auto_tags($L['source'] ?? ''));
        $L['view'] = $roles; $out[] = $L;
    }
    return $out;
}

// How agents get leads. Agents pick theirs in My Lead Sources; picked sources become tags on leads.
const LD_SOURCES = ['Social media', 'Facebook', 'Instagram', 'Website', 'Landing page', 'Agent App', 'Open house', 'FSBO', 'Expired', 'Networking', 'Community event', 'Hosting seminar',
    'Sphere / friend or family', 'Past client', 'Referral', 'Agent referral', 'Zillow', 'Realtor.com', 'Sign call', 'Door knocking', 'Cold calling', 'Direct mail', 'Email campaign',
    'Pre-foreclosure', 'Probate', 'Investor', 'Relocation', 'New construction', 'Renter'];
// Older plural names, kept working by renaming them on the fly.
const LD_SRC_RENAME = ['Open houses' => 'Open house', 'Expireds' => 'Expired', 'Community events' => 'Community event', 'Hosting seminars' => 'Hosting seminar',
    'Sphere / friends and family' => 'Sphere / friend or family', 'Past clients' => 'Past client', 'Referrals' => 'Referral', 'Agent referrals' => 'Agent referral',
    'Sign calls' => 'Sign call', 'Email campaigns' => 'Email campaign', 'Investors' => 'Investor', 'Renters' => 'Renter'];
function ld_src_names($a) { $o = []; foreach ((array)$a as $t) { $t = LD_SRC_RENAME[$t] ?? $t; if (!in_array($t, $o, true)) $o[] = $t; } return $o; }
// Interests and connections, mostly for Sphere: shared activities and circles to remember and to reach people by.
const LD_INTERESTS = ['Friend of a friend', 'Family', 'Neighbor', 'Coworker', 'Church', 'School parents', 'Business owner', 'Veteran',
    'Pickleball', 'Golf', 'Tennis', 'Bowling', 'Fishing', 'Boating', 'Softball', 'Running', 'Cycling',
    'Gym', 'Yoga', 'Gardening', 'Painting', 'Collecting', 'Cooking', 'Wine', 'Travel', 'Dogs', 'Volunteering', 'Book club'];
// Tags an incoming lead gets from where it came in.
function ld_auto_tags($source) {
    $s = trim((string)$source); if ($s === '') return [];
    $map = ['facebook' => ['Facebook', 'Social media'], 'instagram' => ['Instagram', 'Social media'], 'agent app' => ['Agent App'], 'landing page' => ['Landing page'], 'website' => ['Website'], 'lead form' => ['Website'],
        'zillow' => ['Zillow'], 'realtor.com' => ['Realtor.com'], 'email signature' => ['Email campaign'], 'zapier' => ['Website'],
        'open house' => ['Open house'], 'referral' => ['Referral'], 'sign call' => ['Sign call'], 'sphere' => ['Sphere / friend or family'], 'google' => ['Website'], 'other' => []];
    return $map[strtolower($s)] ?? [mb_substr($s, 0, 40)];
}
function ld_clean_tags($a) {
    $out = [];
    foreach ((array)$a as $t) { $t = ld_clean(is_scalar($t) ? $t : '', 40); if ($t !== '' && !in_array($t, $out, true)) $out[] = $t; if (count($out) >= 25) break; }
    return $out;
}
function ld_stage_name($role, $n) {
    $m = $role === 'seller' ? [1 => 'Pre Listing', 2 => 'Active Listing', 3 => 'Pending Listing', 4 => 'Archived'] : [1 => 'Buyer Agreement', 3 => 'Pending Contract', 4 => 'Archived'];
    return $m[(int)$n] ?? 'Active Business';
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

// ---- Lead intake: each agent has a private link that drops leads into their NEW tab ----
// Key = first 12 characters of the agent's file name + a random secret, so the endpoint can find the file without an index.
function ld_intake($email, $name, $phone) {
    return ld_update($email, function (&$db) use ($email, $name, $phone) {
        if (empty($db['intake']['key'])) { $db['intake'] = ['key' => substr(sha1(strtolower(trim($email))), 0, 12) . bin2hex(random_bytes(10)), 'created' => date('c')]; }
        $db['intake']['name'] = $name; $db['intake']['phone'] = $phone;
        return $db['intake'];
    });
}
function ld_intake_reset($email) {
    return ld_update($email, function (&$db) use ($email) {
        $db['intake']['key'] = substr(sha1(strtolower(trim($email))), 0, 12) . bin2hex(random_bytes(10)); $db['intake']['created'] = date('c');
        return $db['intake'];
    });
}
function ld_intake_find($key) {
    if (!preg_match('/^[a-f0-9]{32}$/', (string)$key)) return null;
    $r = flr_intake_lookup($key); if (!$r || empty($r['intake']['key']) || !hash_equals($r['intake']['key'], $key)) return null;
    return ['file' => ld_dir() . '/' . $r['akey'] . '.json', 'agent' => $r['intake']];
}
// Adds an incoming lead to NEW, or adds the inquiry to an existing lead with the same email or phone.
function ld_intake_add($file, $in) {
    $today = ld_today();
    return ld_update_file($file, function (&$db) use ($in, $today) {
        $digits = function ($p) { $d = preg_replace('/\D/', '', (string)$p); return strlen($d) === 11 && $d[0] === '1' ? substr($d, 1) : $d; };
        $note = trim(($in['interest'] ? 'Interested in: ' . $in['interest'] . '. ' : '') . ($in['message'] ? 'Message: ' . $in['message'] : ''));
        foreach ($db['leads'] as &$L) {
            $same = ($in['email'] !== '' && strcasecmp($L['email'] ?? '', $in['email']) === 0) || ($in['phone'] !== '' && strlen($digits($in['phone'])) >= 7 && $digits($L['phone'] ?? '') === $digits($in['phone']));
            if (!$same) continue;
            $L['notes'] = trim(($L['notes'] ?? '') . "\n" . date('M j') . ' new inquiry via ' . $in['source'] . ($note ? ': ' . $note : '.'));
            $L['log'][] = ['d' => $today, 'role' => 'new', 'mid' => '', 'note' => 'New inquiry via ' . $in['source']];
            if (!empty($in['consent'])) $L['consent'] = $in['consent'];
            $L['inquiry'] = date('c');
            $L['tags'] = ld_clean_tags(array_merge((array)($L['tags'] ?? []), ld_auto_tags($in['source'])));
            return ['dup' => true, 'id' => $L['id']];
        }
        unset($L);
        $id = bin2hex(random_bytes(6));
        array_unshift($db['leads'], ['id' => $id, 'first' => $in['first'], 'last' => $in['last'], 'phone' => $in['phone'], 'email' => $in['email'], 'source' => $in['source'],
            'added' => $today, 'created' => date('c'), 'notes' => $note, 'consent' => $in['consent'], 'inbound' => true, 'tags' => ld_auto_tags($in['source']),
            'roles' => ['new' => ['since' => $today, 'touches' => 0]], 'log' => [['d' => $today, 'role' => 'new', 'mid' => '', 'note' => 'Lead came in via ' . $in['source']]]]);
        return ['dup' => false, 'id' => $id];
    });
}

// A NEW lead that has had every first-contact attempt (or has waited 14 days) moves to Undetermined, on the side it asked about.
const LD_NEW_DAYS = 14;
function ld_autosort($email) {
    $db = ld_load($email); $today = ld_today(); $need = false;
    foreach ($db['leads'] as $L) { if (ld_should_sort($L, $today)) { $need = true; break; } }
    if (!$need) return;
    ld_update($email, function (&$db) use ($today) {
        foreach ($db['leads'] as &$L) {
            if (!ld_should_sort($L, $today)) continue;
            $sides = ld_sides_for($L);
            foreach ($sides as $k) { $L['roles'][$k] = ['sub' => 'unid', 'since' => $today, 'touches' => 0, 'last' => '']; }
            $L['log'][] = ['d' => $today, 'role' => 'new', 'mid' => '', 'note' => 'Not reached yet: moved to ' . implode(' and ', array_map(function ($k) { return LD_CATS[$k]['label']; }, $sides)) . ' > Undetermined'];
        }
    });
}
function ld_should_sort($L, $today) {
    foreach (['buyer', 'seller', 'sphere'] as $k) { if (!empty($L['roles'][$k])) return false; }
    $n = $L['roles']['new'] ?? ['since' => $L['added'] ?? $today, 'touches' => 0];
    if (!empty($L['keepnew'])) return false;
    return (int)($n['touches'] ?? 0) >= count(ld_plan_msgs(LD_PLANS['new'][0])) || ld_days($n['since'] ?? ($L['added'] ?? $today), $today) >= LD_NEW_DAYS;
}
// Which side an unreached lead belongs to: what they told us on the form (or in the notes), otherwise Buyers.
function ld_sides_for($L) {
    $t = strtolower(($L['notes'] ?? '') . ' ' . ($L['interest'] ?? ''));
    if (strpos($t, 'buying and selling') !== false) return ['buyer', 'seller'];
    if (preg_match('/interested in: selling/', $t)) return ['seller'];
    return ['buyer'];
}

// ---- Leads <-> Active Business: one person, one record trail ----
// Plain-text summaries of the buyer's wants and the seller's home (same wording as the lead window).
function ld_wants_text($w) {
    $w = (array)$w; $p = []; $a = function ($k) use ($w) { return array_values(array_filter((array)($w[$k] ?? []), 'strlen')); };
    $m = function ($v) { $d = preg_replace('/[^\d.]/', '', (string)$v); return $d !== '' && is_numeric($d) ? number_format((float)$d) : (string)$v; };
    if (!empty($w['price_min']) || !empty($w['price_max'])) { $p[] = '$' . (!empty($w['price_min']) ? $m($w['price_min']) : '0') . ' to ' . (!empty($w['price_max']) ? '$' . $m($w['price_max']) : 'open'); }
    if (!empty($w['beds'])) $p[] = $w['beds'] . '+ beds'; if (!empty($w['baths'])) $p[] = $w['baths'] . '+ baths'; if (!empty($w['sqft'])) $p[] = $w['sqft'] . '+ sq ft';
    if ($a('types')) $p[] = implode('/', $a('types')); if ($a('garage')) $p[] = implode('/', $a('garage')) . ' garage';
    if ($a('pool') && $a('pool')[0] !== 'No pool') $p[] = 'pool: ' . strtolower($a('pool')[0]); if (!empty($w['areas'])) $p[] = 'in ' . $w['areas'];
    if ($a('feats')) $p[] = implode(', ', $a('feats')); if ($a('fin')) $p[] = implode('/', $a('fin')) . ($a('pre') ? ' (' . strtolower($a('pre')[0]) . ')' : '');
    if (!empty($w['move_by'])) $p[] = 'move by ' . $w['move_by'];
    foreach (['must' => 'Must have', 'nice' => 'Nice to have', 'no' => 'Deal breakers'] as $k => $l) { if (!empty($w[$k])) $p[] = $l . ': ' . $w[$k]; }
    return implode(' · ', $p);
}
function ld_home_text($w) {
    $w = (array)$w; $p = []; $a = function ($k) use ($w) { return array_values(array_filter((array)($w[$k] ?? []), 'strlen')); };
    $cy = (int)substr(ld_today(), 0, 4); $age = function ($y) use ($cy) { $y = (int)$y; return $y > 1900 ? ($cy - $y) . ' yrs' : ''; };
    if (!empty($w['addr'])) $p[] = $w['addr']; if ($a('types')) $p[] = $a('types')[0];
    if (!empty($w['beds']) || !empty($w['baths'])) $p[] = ($w['beds'] ?: '?') . ' bd / ' . ($w['baths'] ?: '?') . ' ba';
    if (!empty($w['sqft'])) $p[] = $w['sqft'] . ' sq ft'; if (!empty($w['lot'])) $p[] = 'lot ' . $w['lot']; if (!empty($w['year'])) $p[] = 'built ' . $w['year'];
    if ($a('garage')) $p[] = $a('garage')[0]; if ($a('pool') && $a('pool')[0] !== 'No pool') $p[] = 'pool: ' . implode(', ', $a('pool'));
    if ($a('water') && $a('water')[0] !== 'Not waterfront') $p[] = implode(', ', $a('water')); if ($a('dock')) $p[] = implode(', ', $a('dock'));
    if (!empty($w['roof_year'])) $p[] = ucfirst(trim((($a('roof')[0] ?? '') . ' roof ' . $age($w['roof_year']))));
    if (!empty($w['ac_year'])) $p[] = 'AC ' . $age($w['ac_year']); if (!empty($w['wh_year'])) $p[] = 'water heater ' . $age($w['wh_year']);
    if ($a('cond')) $p[] = $a('cond')[0]; if ($a('upd')) $p[] = 'updated: ' . implode(', ', $a('upd'));
    if ($a('hoa')) $p[] = implode(', ', $a('hoa')) . (!empty($w['hoa_fee']) ? ' (' . $w['hoa_fee'] . ')' : '');
    if ($a('flood')) $p[] = $a('flood')[0]; if ($a('occ')) $p[] = $a('occ')[0]; if (!empty($w['sell_by'])) $p[] = 'sell by ' . $w['sell_by'];
    if (!empty($w['issues'])) $p[] = 'Known issues: ' . $w['issues'];
    return implode(' · ', $p);
}
// Everything the lead section knows, packed for a Active Business record info file.
function ld_pack_for_record($L, $all) {
    $ref = ''; foreach ((array)$all as $x) { if (($x['id'] ?? '') !== '' && ($x['id'] ?? '') === ($L['ref'] ?? '')) $ref = trim(($x['first'] ?? '') . ' ' . ($x['last'] ?? '')); }
    return ['lead_id' => (string)($L['id'] ?? ''), 'lead_source' => (string)($L['source'] ?? ''), 'lead_added' => (string)($L['added'] ?? ''), 'lead_notes' => (string)($L['notes'] ?? ''),
        'lead_ref' => $ref, 'lead_tags' => array_values((array)($L['tags'] ?? [])), 'lead_wants' => (array)($L['wants'] ?? []), 'lead_home' => (array)($L['home'] ?? [])];
}
// The lead linked to a Active Business record folder (buyer or seller side), or null.
function ld_find_by_folder($email, $fid) {
    foreach (ld_load($email)['leads'] as $L) { foreach (['buyer', 'seller'] as $r) { if ($fid !== '' && ($L['roles'][$r]['folder'] ?? '') === $fid) return $L + ['_role' => $r]; } }
    return null;
}
// A lead that looks like the same person (email or phone match), not yet linked to a record on this side.
function ld_find_match($email, $side, $em, $ph) {
    $em = strtolower(trim((string)$em)); $ph = substr(preg_replace('/\D/', '', (string)$ph), -10);
    foreach (ld_load($email)['leads'] as $L) {
        if (!empty($L['roles'][$side]['folder'])) continue;
        if (($em !== '' && strtolower(trim((string)($L['email'] ?? ''))) === $em) || (strlen($ph) === 10 && substr(preg_replace('/\D/', '', (string)($L['phone'] ?? '')), -10) === $ph)) return $L;
    }
    return null;
}
