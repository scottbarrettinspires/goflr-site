<?php
// Messaging between agents and staff, shown on Home (staff), the Messages page (everyone) and from Directory cards.
//   Agents -> one staff person, another agent, or "Anyone in the office" (every staff account sees it; whoever handles it clears it
//             for everyone).
//   Staff  -> one agent, a group of agents, all Active agents, one staff person, or all staff.
// A message shows on each recipient's Home page the next time they open it, until that recipient dismisses it.
//
// Who is who:
//   Agents are matched to the server Agent Records table (arlib.php) by their sign-in email -- either the Email
//   column or any address in the Google Account column -- and addressed as "agent:<Agent ID>", so a message
//   follows the agent even if they sign in with a different Google address than their roster email.
//   Staff are addressed by sign-in email ("staff:<email>"); "staff:*" is the shared office inbox. The staff list
//   offered to agents comes from the Staff Directory sheet (flr_directory()).
// "All agents" is expanded to every Active agent at send time.
require_once __DIR__ . '/arlib.php';
date_default_timezone_set('America/New_York'); // message times show in Eastern, like leads and recruiting

function om_db() {
    $pdo = ar_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS msgs (id INTEGER PRIMARY KEY AUTOINCREMENT, from_email TEXT, from_name TEXT, from_key TEXT, from_staff INTEGER DEFAULT 0, body TEXT NOT NULL, to_label TEXT, created TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS msgs_from ON msgs(from_email, id)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS msg_to (msg_id INTEGER NOT NULL, rkey TEXT NOT NULL, seen_at TEXT, done_at TEXT, done_by TEXT, PRIMARY KEY (msg_id, rkey))');
    $pdo->exec('CREATE INDEX IF NOT EXISTS msg_to_rkey ON msg_to(rkey, done_at)');
    static $migrated = false;
    if (!$migrated) { $migrated = true; om_migrate_old($pdo); }
    return $pdo;
}
// One-time: open notes from the earlier shared office inbox move into the new system as "to the office".
function om_migrate_old($pdo) {
    try {
        if ($pdo->query("SELECT v FROM kv WHERE k = 'msg_migrated'")->fetchColumn()) return;
        $has = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'office_messages'")->fetchColumn();
        $pdo->exec('BEGIN IMMEDIATE');
        if ($has) {
            foreach ($pdo->query('SELECT * FROM office_messages WHERE done = 0 ORDER BY id')->fetchAll() as $o) {
                $pdo->prepare('INSERT INTO msgs (from_email, from_name, from_key, from_staff, body, to_label, created) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$o['from_email'], $o['from_name'], $o['from_staff'] ? 'staff:' . $o['from_email'] : '', (int)$o['from_staff'], $o['body'], 'The office', $o['created']]);
                $pdo->prepare('INSERT INTO msg_to (msg_id, rkey) VALUES (?, ?)')->execute([(int)$pdo->lastInsertId(), 'staff:*']);
            }
        }
        $pdo->exec("INSERT OR REPLACE INTO kv (k, v) VALUES ('msg_migrated', '" . date('c') . "')");
        $pdo->exec('COMMIT');
    } catch (Throwable $e) { try { $pdo->exec('ROLLBACK'); } catch (Throwable $e2) {} }
}

// ---- Who's who ----
function om_agent_ids_for($email) {
    static $map = null;
    if ($map === null) { // built once per request: every roster Email and Google Account address -> Agent IDs
        $map = [];
        foreach (ar_all() as $r) {
            // Only real addresses count -- the Google Account column sometimes holds notes like "None".
            $g = array_filter(preg_split('/[\s,;]+/', strtolower((string)($r['fields']['google_account'] ?? '')), -1, PREG_SPLIT_NO_EMPTY), function ($x) { return strpos($x, '@') !== false; });
            foreach (array_unique(array_merge([$r['email']], $g)) as $e) { if ($e !== '') $map[$e][] = $r['agent_id']; }
        }
    }
    $email = strtolower(trim((string)$email));
    return $email === '' ? [] : array_values(array_unique($map[$email] ?? []));
}
// The keys whose messages this signed-in person sees.
function om_my_keys($email, $isStaff) {
    $email = strtolower(trim((string)$email));
    if ($isStaff) return ['staff:' . $email, 'staff:*'];
    return array_map(function ($id) { return 'agent:' . $id; }, om_agent_ids_for($email));
}
function om_active_agents() {
    $out = [];
    foreach (ar_all() as $r) { if (strtolower($r['status']) === 'active') $out[$r['agent_id']] = ['name' => $r['preferred_name'] !== '' ? $r['preferred_name'] : $r['full_name'], 'office' => (string)($r['fields']['office'] ?? '')]; }
    uasort($out, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    return $out;
}
// Staff people agents can pick: [email => "Name (Position)"]. Empty if the directory can't be reached.
function om_staff_people() {
    $out = []; $d = function_exists('flr_directory') ? flr_directory() : null;
    foreach ((array)($d['staff'] ?? []) as $p) {
        $e = strtolower(trim((string)($p['email'] ?? ''))); if ($e === '') continue;
        $out[$e] = trim(($p['name'] ?? $e) . (!empty($p['position']) ? ' (' . $p['position'] . ')' : ''));
    }
    return $out;
}
// The key to address a directory person by (Message button), or '' if they can't be messaged here.
function om_key_for($email, $isStaffPerson) {
    $email = strtolower(trim((string)$email)); if ($email === '') return '';
    if ($isStaffPerson) return 'staff:' . $email;
    $ids = om_agent_ids_for($email); return $ids ? 'agent:' . $ids[0] : '';
}
// Name + headshot for a sender key. Headshots come from the directory (the same photos it shows), served by
// dirphoto.php to signed-in users only; '' when there's no photo, and the page shows initials instead.
function om_person($key, $fallbackName = '') {
    if ($key === 'staff:*' && $fallbackName === 'Florida Luxury Realty') return ['name' => $fallbackName, 'ini' => 'FLR', 'photo' => 'icon-192.png']; // the office's own posts (celebrations)
    static $byEmail = null;
    if ($byEmail === null) {
        $byEmail = []; $d = function_exists('flr_directory') ? flr_directory() : null;
        foreach (['staff', 'agents'] as $t) foreach ((array)($d[$t] ?? []) as $p) {
            $e = strtolower(trim((string)($p['email'] ?? ''))); if ($e !== '' && !isset($byEmail[$e])) $byEmail[$e] = $p;
        }
    }
    $email = ''; $name = $fallbackName;
    if (strpos($key, 'staff:') === 0 && $key !== 'staff:*') { $email = substr($key, 6); }
    elseif (strpos($key, 'agent:') === 0 && ($r = ar_get(substr($key, 6)))) { $email = $r['email']; $name = $name !== '' ? $name : ($r['preferred_name'] ?: $r['full_name']); }
    $p = $byEmail[$email] ?? null;
    if ($p && $name === '') $name = (string)$p['name'];
    $ini = ''; foreach (preg_split('/\s+/', trim($name)) as $w) { if ($w !== '' && mb_strlen($ini) < 2) $ini .= mb_strtoupper(mb_substr($w, 0, 1)); }
    return ['name' => $name, 'ini' => $ini, 'photo' => flr_headshot_url($email, ($p && !empty($p['photo'])) ? 'dirphoto.php?k=' . flr_dir_photo_key($p['photo']) : '')];
}
// Messages not yet opened, for the Home page badge.
function om_unread_count(array $keys) {
    if (!$keys) return 0;
    $q = om_db()->prepare('SELECT COUNT(*) FROM msg_to WHERE done_at IS NULL AND seen_at IS NULL AND rkey IN (' . implode(',', array_fill(0, count($keys), '?')) . ')');
    $q->execute($keys); return (int)$q->fetchColumn();
}
function om_key_label($key) {
    if ($key === 'staff:*') return 'Anyone in the office';
    if (strpos($key, 'staff:') === 0) { $e = substr($key, 6); $s = om_staff_people(); return $s[$e] ?? $e; }
    if (strpos($key, 'agent:') === 0) { $r = ar_get(substr($key, 6)); return $r ? ($r['preferred_name'] ?: $r['full_name']) : substr($key, 6); }
    return $key;
}

// ---- Send / read / dismiss ----
// $keys: recipient keys. Returns the new message id, or false if there's nothing to send.
function om_send($fromEmail, $fromName, $fromKey, $isStaff, $body, array $keys, $label) {
    $body = trim(mb_substr((string)$body, 0, 4000)); $keys = array_values(array_unique(array_filter($keys)));
    if ($body === '' || !$keys) return false;
    $pdo = om_db(); $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->prepare('INSERT INTO msgs (from_email, from_name, from_key, from_staff, body, to_label, created) VALUES (?,?,?,?,?,?,?)')
            ->execute([strtolower(trim((string)$fromEmail)), trim((string)$fromName), (string)$fromKey, $isStaff ? 1 : 0, $body, mb_substr((string)$label, 0, 300), date('c')]);
        $id = (int)$pdo->lastInsertId(); $ins = $pdo->prepare('INSERT OR IGNORE INTO msg_to (msg_id, rkey) VALUES (?, ?)');
        foreach ($keys as $k) $ins->execute([$id, $k]);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
    flr_audit('', 'msg_send', (string)$id, count($keys) . ' recipient(s): ' . $label);
    return $id;
}
// Open messages for these keys, newest first; marks them seen.
function om_inbox(array $keys, $limit = 30) {
    if (!$keys) return [];
    $pdo = om_db(); $in = implode(',', array_fill(0, count($keys), '?'));
    $q = $pdo->prepare("SELECT m.*, t.rkey, t.seen_at FROM msg_to t JOIN msgs m ON m.id = t.msg_id WHERE t.rkey IN ($in) AND t.done_at IS NULL ORDER BY m.id DESC LIMIT " . (int)$limit);
    $q->execute($keys); $rows = $q->fetchAll();
    $seen = $pdo->prepare('UPDATE msg_to SET seen_at = ? WHERE msg_id = ? AND rkey = ? AND seen_at IS NULL');
    foreach ($rows as $r) if (!$r['seen_at']) $seen->execute([date('c'), $r['id'], $r['rkey']]);
    return $rows;
}
// Dismiss (agent) / mark handled (staff) -- only for a key that belongs to the person doing it.
function om_dismiss($msgId, $rkey, array $myKeys, $byEmail) {
    if (!in_array($rkey, $myKeys, true)) return false;
    om_db()->prepare('UPDATE msg_to SET done_at = ?, done_by = ? WHERE msg_id = ? AND rkey = ? AND done_at IS NULL')->execute([date('c'), strtolower(trim((string)$byEmail)), (int)$msgId, $rkey]);
    return true;
}
// What I sent recently, with how many recipients have opened their Home page since / cleared it.
function om_sent($fromEmail, $limit = 10) {
    $q = om_db()->prepare('SELECT m.*, COUNT(t.rkey) n, SUM(t.seen_at IS NOT NULL) seen, SUM(t.done_at IS NOT NULL) done FROM msgs m JOIN msg_to t ON t.msg_id = m.id WHERE m.from_email = ? GROUP BY m.id ORDER BY m.id DESC LIMIT ' . (int)$limit);
    $q->execute([strtolower(trim((string)$fromEmail))]); return $q->fetchAll();
}
function om_get($id) { $q = om_db()->prepare('SELECT * FROM msgs WHERE id = ?'); $q->execute([(int)$id]); return $q->fetch() ?: null; }

// ---- POST handler for Home and the Messages page (both kinds of account). Returns an error string, or redirects. ----
function om_handle_post($me, $myName, $isStaff) {
    $myKeys = om_my_keys($me, $isStaff);
    $back = (($_POST['back'] ?? '') === 'messages.php') ? 'messages.php' : 'home.php';
    if (isset($_POST['msgdismiss'])) {
        om_dismiss((int)$_POST['msgdismiss'], (string)($_POST['rkey'] ?? ''), $myKeys, $me);
        header('Location: ' . $back . '#messages'); exit;
    }
    // Inline reply from the Inbox: goes straight back to whoever sent that message -- only for a message that was
    // actually delivered to one of my keys.
    if (isset($_POST['replyto'])) {
        $mid = (int)$_POST['replyto']; $rk = (string)($_POST['rkey'] ?? ''); $orig = om_get($mid);
        $q = om_db()->prepare('SELECT 1 FROM msg_to WHERE msg_id = ? AND rkey = ?'); $q->execute([$mid, $rk]);
        if (!$orig || !in_array($rk, $myKeys, true) || !$q->fetchColumn() || $orig['from_key'] === '' || in_array($orig['from_key'], $myKeys, true)) return 'That message can\'t be replied to here.';
        $body = (string)($_POST['msgbody'] ?? ''); if (trim($body) === '') return 'Type a reply first.';
        $fromKey = $isStaff ? 'staff:' . strtolower($me) : ($myKeys[0] ?? '');
        om_send($me, $myName, $fromKey, $isStaff, $body, [$orig['from_key']], om_key_label($orig['from_key']));
        header('Location: ' . $back . '?replied=' . $mid . '#om' . $mid); exit;
    }
    if (!isset($_POST['msgbody'])) return '';
    $body = (string)$_POST['msgbody'];
    $fromKey = $isStaff ? 'staff:' . strtolower($me) : ($myKeys[0] ?? '');
    $keys = []; $label = '';
    if ($isStaff) {
        $mode = (string)($_POST['mode'] ?? 'one'); $agents = om_active_agents();
        if ($mode === 'all') { foreach ($agents as $id => $a) $keys[] = 'agent:' . $id; $label = 'All agents (' . count($keys) . ')'; }
        elseif ($mode === 'group') {
            $names = [];
            foreach ((array)($_POST['group'] ?? []) as $id) { $id = (string)$id; if (isset($agents[$id])) { $keys[] = 'agent:' . $id; $names[] = $agents[$id]['name']; } }
            $label = count($names) <= 4 ? implode(', ', $names) : implode(', ', array_slice($names, 0, 3)) . ' + ' . (count($names) - 3) . ' more';
            if (!$keys) return 'Pick at least one agent for the group.';
        } else {
            $to = (string)($_POST['to'] ?? '');
            if (strpos($to, 'agent:') === 0 && isset($agents[substr($to, 6)])) { $keys[] = $to; }
            elseif ($to === 'staff:*' || (strpos($to, 'staff:') === 0 && filter_var(substr($to, 6), FILTER_VALIDATE_EMAIL))) { $keys[] = $to; }
            if (!$keys) return 'Choose who the message is for.';
            $label = om_key_label($to);
        }
    } else {
        // Agents write to one person: the office inbox, a staff person, or another Active agent. Anything else
        // (a tampered form) falls back to the office inbox. An agent not yet in the server Agent Records table can
        // still write in; the recipient just can't reply on-site.
        $to = (string)($_POST['to'] ?? 'staff:*');
        $ok = $to === 'staff:*' || (strpos($to, 'staff:') === 0 && isset(om_staff_people()[substr($to, 6)]))
            || (strpos($to, 'agent:') === 0 && isset(om_active_agents()[substr($to, 6)]) && !in_array($to, $myKeys, true));
        if (!$ok) $to = 'staff:*';
        $keys[] = $to; $label = om_key_label($to);
    }
    if (trim($body) === '') return 'Type a message first.';
    om_send($me, $myName, $fromKey, $isStaff, $body, $keys, $label);
    header('Location: ' . $back . '?msgsent=1#composecard'); exit;
}
