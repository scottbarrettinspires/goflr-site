<?php
// Learn And Grow Your Business: slide presentations and playbooks from past Round Tables. The PDFs live on the server (outside the web
// root, served only to signed-in users) so every agent can view them in the page whatever Google account they use.
// Presentations are shown one slide at a time; playbooks scroll and can be downloaded. Staff add and remove items.
require_once __DIR__ . '/flrdb.php';
const LN_MAX_PDF = 40 * 1024 * 1024;
const LN_KINDS = ['presentation' => 'Presentations', 'playbook' => 'Playbooks', 'video' => 'Videos'];
const LN_YT_FEED = 'https://www.youtube.com/feeds/videos.xml?channel_id=UCqtGhg69kehCESHx7G1804A'; // FLR Online Campus (@FLROnlineCampus)

function ln_dir() { $d = flr_db_dir() . '/learn'; if (!is_dir($d)) @mkdir($d, 0750, true); return $d; }
function ln_db() {
    static $done = false; $pdo = flr_db();
    if ($done) return $pdo;
    $pdo->exec('CREATE TABLE IF NOT EXISTS learn_items (id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT NOT NULL, title TEXT NOT NULL, series TEXT, session TEXT, file TEXT NOT NULL, size INTEGER DEFAULT 0, added TEXT, added_by TEXT, deleted INTEGER DEFAULT 0)');
    foreach (['gid TEXT', 'gmod INTEGER DEFAULT 0'] as $col) { try { $pdo->exec('ALTER TABLE learn_items ADD COLUMN ' . $col); } catch (Exception $e) {} }
    $done = true;
    if (!(int)$pdo->query('SELECT COUNT(*) FROM learn_items')->fetchColumn() && is_dir(__DIR__ . '/learn_seed')) ln_seed($pdo);
    return $pdo;
}
// First use: bring in the Round Table material already in the Training folder (copied into learn_seed/ with the site).
function ln_seed($pdo) {
    $seed = [
        ['presentation', 'Holiday Business and Marketing Roundtable', 'Round Table', '2026-11-11', '1XyQLyFKxhGXom4iNdJEXSB7Q4QS9bMzD'],
        ['presentation', 'Basics of AI for Real Estate Agents', 'Zoom', '2026-11-05', '1fpoyDz5peJzZOPiMxm2GKGYt7edUx3Ho'],
        ['presentation', 'AI Basics for Real Estate Agents', 'Zoom', '2026-11-05', '1J1zf9nLim8CZaQB67polFpiR1jTY5-b0'],
        ['presentation', 'The Deal-Maker\'s Playbook', 'Round Table', '2026-10-28', '1GhGlE3bMmD0t8lodO959jXohhv5t_8w5'],
        ['playbook', 'Holiday Business Playbook', 'Round Table', '2026-11-11', '1qflTWaop81DjW3vv3TMd0VY9RuDUwnHZ'],
        ['playbook', 'Holiday Business Playbook (Print Version)', 'Round Table', '2026-11-11', '1FG130V5r_iKxlkB238RPgqecgUeAkpQD'],
    ];
    $now = date('c');
    foreach ($seed as $s) {
        $src = __DIR__ . '/learn_seed/' . $s[4] . '.pdf'; if (!is_file($src)) continue;
        $file = bin2hex(random_bytes(12)) . '.pdf'; if (!@copy($src, ln_dir() . '/' . $file)) continue;
        $pdo->prepare('INSERT INTO learn_items (kind, title, series, session, file, size, added, added_by) VALUES (?,?,?,?,?,?,?,?)')->execute([$s[0], $s[1], $s[2], $s[3], $file, filesize($src), $now, 'seed']);
    }
}
function ln_list($kind = '') {
    $q = ln_db()->prepare('SELECT id, kind, title, series, session, size, added, gid FROM learn_items WHERE deleted = 0' . ($kind !== '' ? ' AND kind = ?' : '') . ' ORDER BY session DESC, id DESC');
    $q->execute($kind !== '' ? [$kind] : []); return $q->fetchAll(PDO::FETCH_ASSOC);
}
// A presentation and its playbook are paired when their titles match once the word "playbook" is taken out (and "print version" for print copies).
function ln_pair_key($t) {
    $t = strtolower((string)$t); $t = preg_replace('/\bprint(able)?( version)?\b/', ' ', $t); $t = preg_replace('/\bplaybooks?\b/', ' ', $t);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $t));
}
// For each item id: the items of the other kind that go with it. [id => [[id, kind, title], ...]]
function ln_partners(array $lists) {
    $by = ['presentation' => [], 'playbook' => []];
    foreach ($lists as $kind => $rows) if (isset($by[$kind])) foreach ($rows as $r) { $k = ln_pair_key($r['title']); if ($k !== '') $by[$kind][$k][] = $r; }
    $out = [];
    foreach (['presentation' => 'playbook', 'playbook' => 'presentation'] as $kind => $other)
        foreach ($by[$kind] as $k => $rows) foreach ($rows as $r) {
            $out[$r['id']] = array_map(function ($p) { return ['id' => (int)$p['id'], 'kind' => $p['kind'], 'title' => $p['title']]; }, $by[$other][$k] ?? []);
        }
    return $out;
}
function ln_item($id) { $q = ln_db()->prepare('SELECT * FROM learn_items WHERE id = ? AND deleted = 0'); $q->execute([(int)$id]); return $q->fetch(PDO::FETCH_ASSOC) ?: null; }

// ---- Google Drive sync: Training > Presentations / Playbooks are the source of truth for items that have a gid ----
function ln_script_get($op, array $args = [], $timeout = 60) {
    if (!defined('ROSTER_URL')) require_once __DIR__ . '/config.php';
    $ch = curl_init(ROSTER_URL . '?' . http_build_query(['key' => ROSTER_KEY, 'op' => $op] + $args));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    return is_array($j) ? $j : null;
}
function ln_drive_title($name) { return trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', preg_replace('/\.pdf$/i', '', (string)$name)))); }
// Reads the two Drive folders and makes the Learn list match: new files appear, renamed files take the new name, deleted files disappear.
// The PDF itself is only downloaded when someone opens it (ln_ensure_file). $list is for tests; normally it comes from the office script.
// Runs at most every 2 minutes unless $force. Returns '' or a message.
function ln_sync($force = false, $list = null) {
    $pdo = ln_db(); $stamp = ln_dir() . '/.last_sync';
    if (!$force && $list === null && is_file($stamp) && time() - filemtime($stamp) < 120) return '';
    if ($list === null) {
        @touch($stamp);
        $r = ln_script_get('learnlist', [], 20);
        if (!$r || empty($r['ok']) || !is_array($r['items'] ?? null)) return 'Could not read the Training folders in Drive: ' . (string)($r['note'] ?? 'no answer from the office script (is the latest version deployed?)');
        $list = $r['items'];
    }
    // First sync: tie the items copied in at launch to their Drive files (same order they were seeded in).
    $seedGids = ['1XyQLyFKxhGXom4iNdJEXSB7Q4QS9bMzD', '1fpoyDz5peJzZOPiMxm2GKGYt7edUx3Ho', '1J1zf9nLim8CZaQB67polFpiR1jTY5-b0', '1GhGlE3bMmD0t8lodO959jXohhv5t_8w5', '1qflTWaop81DjW3vv3TMd0VY9RuDUwnHZ', '1FG130V5r_iKxlkB238RPgqecgUeAkpQD'];
    $seedRows = $pdo->query("SELECT id FROM learn_items WHERE added_by = 'seed' AND (gid IS NULL OR gid = '') ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    if ($seedRows && count($seedRows) === count($seedGids)) foreach ($seedRows as $i => $rid) $pdo->prepare('UPDATE learn_items SET gid = ? WHERE id = ?')->execute([$seedGids[$i], $rid]);
    $have = []; foreach ($pdo->query("SELECT * FROM learn_items WHERE gid IS NOT NULL AND gid <> '' AND gid NOT LIKE 'yt:%'")->fetchAll(PDO::FETCH_ASSOC) as $r) $have[$r['gid']] = $r;
    $seen = [];
    foreach ($list as $d) {
        $gid = (string)($d['id'] ?? ''); $kind = (string)($d['kind'] ?? ''); if ($gid === '' || ($kind !== 'presentation' && $kind !== 'playbook')) continue;
        $seen[$gid] = 1; $title = ln_drive_title($d['name'] ?? ''); $mod = (int)round(((float)($d['modified'] ?? 0)) / 1000); $size = (int)($d['size'] ?? 0);
        if ($title === '') continue;
        if (isset($have[$gid])) {
            $r = $have[$gid];
            if ($r['deleted']) $pdo->prepare('UPDATE learn_items SET deleted = 0 WHERE id = ?')->execute([$r['id']]);
            if ($r['title'] !== $title || $r['kind'] !== $kind) { $pdo->prepare('UPDATE learn_items SET title = ?, kind = ? WHERE id = ?')->execute([$title, $kind, $r['id']]); flr_audit('', 'learn_sync_rename', (string)$r['id'], $r['title'] . ' -> ' . $title); }
            // Changed in Drive since we copied it (or never copied): mark so the next open downloads the new version. First sync after launch just records the date.
            if ((int)$r['gmod'] === 0) $pdo->prepare('UPDATE learn_items SET gmod = ? WHERE id = ?')->execute([$mod, $r['id']]);
            elseif ($mod > (int)$r['gmod']) $pdo->prepare("UPDATE learn_items SET gmod = ?, file = '', size = ? WHERE id = ?")->execute([$mod, $size, $r['id']]);
        } else {
            $pdo->prepare('INSERT INTO learn_items (kind, title, series, session, file, size, added, added_by, gid, gmod) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$kind, $title, '', date('Y-m-d', $mod ?: time()), '', $size, date('c'), 'drive', $gid, $mod]);
            flr_audit('', 'learn_sync_add', (string)$pdo->lastInsertId(), $kind . ': ' . $title);
        }
    }
    foreach ($have as $gid => $r) if (!isset($seen[$gid]) && !$r['deleted']) { $pdo->prepare('UPDATE learn_items SET deleted = 1 WHERE id = ?')->execute([$r['id']]); flr_audit('', 'learn_sync_remove', (string)$r['id'], $r['title']); }
    return '';
}
// Makes sure the PDF for a Drive item is on the server (downloads it the first time it is opened or after it changed in Drive). Returns the path or ''.
function ln_ensure_file(array $it, $content = null) {
    $path = $it['file'] !== '' ? ln_dir() . '/' . basename($it['file']) : '';
    if ($path !== '' && is_file($path)) return $path;
    if (empty($it['gid'])) return '';
    if ($content === null) {
        $r = ln_script_get('learnfile', ['id' => $it['gid']], 120);
        if (!$r || empty($r['ok']) || empty($r['b64'])) return '';
        $content = base64_decode($r['b64'], true);
    }
    if (!is_string($content) || strncmp($content, '%PDF-', 5) !== 0 || strlen($content) > LN_MAX_PDF) return '';
    $file = bin2hex(random_bytes(12)) . '.pdf'; if (file_put_contents(ln_dir() . '/' . $file, $content) === false) return '';
    ln_db()->prepare('UPDATE learn_items SET file = ?, size = ? WHERE id = ?')->execute([$file, strlen($content), (int)$it['id']]);
    return ln_dir() . '/' . $file;
}

// ---- Videos: recorded Zoom webinars on the FLR Online Campus YouTube channel, embedded on the page ----
function ln_yt_id($v) {
    $v = trim((string)$v);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $v)) return $v;
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|live/|shorts/|v/))([A-Za-z0-9_-]{11})~', $v, $m)) return $m[1];
    return '';
}
// Staff adds a video by pasting its YouTube link (needed for unlisted videos, which the channel feed does not show). Returns '' or a message.
function ln_add_video($title, $url, $session, $by) {
    $id = ln_yt_id($url); if ($id === '') return 'Paste the YouTube link for the video (for example https://www.youtube.com/watch?v=...).';
    $title = trim(mb_substr((string)$title, 0, 140)); if ($title === '') return 'Enter a title.';
    $session = trim((string)$session); if ($session !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $session)) return 'The session date must look like 2026-11-11.';
    $pdo = ln_db(); $q = $pdo->prepare('SELECT id, deleted FROM learn_items WHERE gid = ?'); $q->execute(['yt:' . $id]); $ex = $q->fetch(PDO::FETCH_ASSOC);
    if ($ex) { $pdo->prepare('UPDATE learn_items SET deleted = 0, title = ?, session = ? WHERE id = ?')->execute([$title, $session ?: date('Y-m-d'), $ex['id']]); return ''; }
    $pdo->prepare('INSERT INTO learn_items (kind, title, series, session, file, size, added, added_by, gid, gmod) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute(['video', $title, '', $session ?: date('Y-m-d'), '', 0, date('c'), $by, 'yt:' . $id, 0]);
    flr_audit('', 'learn_add', (string)$pdo->lastInsertId(), 'video: ' . $title . ' (by ' . $by . ')');
    return '';
}
// Pulls the channel's newest public videos (the YouTube feed lists the latest 15) and adds any we do not have yet. Runs at most every 10 minutes.
// A video staff removed stays removed; a title staff changed is kept.
function ln_yt_sync($force = false, $xml = null) {
    $pdo = ln_db(); $stamp = ln_dir() . '/.last_yt';
    if (!$force && $xml === null && is_file($stamp) && time() - filemtime($stamp) < 600) return '';
    if ($xml === null) {
        @touch($stamp);
        $ch = curl_init(LN_YT_FEED); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6]);
        $xml = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($xml === false || $code !== 200) return 'Could not read the YouTube channel (HTTP ' . $code . ').';
    }
    $x = @simplexml_load_string((string)$xml); if (!$x) return 'The YouTube channel answer could not be read.';
    $x->registerXPathNamespace('a', 'http://www.w3.org/2005/Atom'); $x->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');
    foreach ($x->xpath('//a:entry') ?: [] as $e) {
        $e->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');
        $vid = (string)(($e->xpath('yt:videoId') ?: [''])[0]); $title = trim((string)($e->children('http://www.w3.org/2005/Atom')->title)); $pub = substr((string)($e->children('http://www.w3.org/2005/Atom')->published), 0, 10);
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $vid) || $title === '') continue;
        $q = $pdo->prepare('SELECT id FROM learn_items WHERE gid = ?'); $q->execute(['yt:' . $vid]); if ($q->fetch()) continue;
        $pdo->prepare('INSERT INTO learn_items (kind, title, series, session, file, size, added, added_by, gid, gmod) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute(['video', mb_substr($title, 0, 140), '', $pub ?: date('Y-m-d'), '', 0, date('c'), 'youtube', 'yt:' . $vid, 0]);
        flr_audit('', 'learn_sync_add', (string)$pdo->lastInsertId(), 'video: ' . $title);
    }
    return '';
}
function ln_video_id(array $it) { return strncmp((string)($it['gid'] ?? ''), 'yt:', 3) === 0 ? substr($it['gid'], 3) : ''; }
// Staff only (the caller checks). $upload is the entry from $_FILES. Returns '' or a message to show.
function ln_add($kind, $title, $series, $session, array $upload, $by) {
    if ($kind !== 'presentation' && $kind !== 'playbook') return 'Choose Presentation or Playbook.';
    $title = trim(mb_substr((string)$title, 0, 140)); if ($title === '') return 'Enter a title.';
    $session = trim((string)$session); if ($session !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $session)) return 'The session date must look like 2026-11-11.';
    if (empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) return 'Choose the PDF to upload.';
    if ((int)$upload['size'] > LN_MAX_PDF) return 'That PDF is too large (40 MB max).';
    $fh = fopen($upload['tmp_name'], 'rb'); $head = $fh ? fread($fh, 5) : ''; if ($fh) fclose($fh);
    if ($head !== '%PDF-') return 'Please upload a PDF file. In Google Slides or Docs, use File > Download > PDF.';
    $file = bin2hex(random_bytes(12)) . '.pdf';
    if (!move_uploaded_file($upload['tmp_name'], ln_dir() . '/' . $file)) return 'The PDF could not be saved. Please try again.';
    ln_db()->prepare('INSERT INTO learn_items (kind, title, series, session, file, size, added, added_by) VALUES (?,?,?,?,?,?,?,?)')->execute([$kind, $title, trim(mb_substr((string)$series, 0, 60)), $session, $file, (int)$upload['size'], date('c'), $by]);
    flr_audit('', 'learn_add', (string)ln_db()->lastInsertId(), $kind . ': ' . $title . ' (by ' . $by . ')');
    return '';
}
function ln_delete($id, $by) {
    $it = ln_item($id); if (!$it) return 'That item was not found.';
    if (!empty($it['gid']) && strncmp($it['gid'], 'yt:', 3) !== 0) return 'This comes from the Training folder in Google Drive. Delete it there and it disappears here within a couple of minutes.';
    ln_db()->prepare('UPDATE learn_items SET deleted = 1 WHERE id = ?')->execute([(int)$id]);
    flr_audit('', 'learn_delete', (string)(int)$id, $it['title'] . ' (by ' . $by . ')');
    return '';
}
function ln_rename($id, $title, $by) {
    $it = ln_item($id); if (!$it) return 'That item was not found.';
    if (!empty($it['gid']) && strncmp($it['gid'], 'yt:', 3) !== 0) return 'This comes from the Training folder in Google Drive. Rename it there and the new name shows here within a couple of minutes.';
    $title = trim(mb_substr((string)$title, 0, 140)); if ($title === '') return 'Enter a title.';
    ln_db()->prepare('UPDATE learn_items SET title = ? WHERE id = ?')->execute([$title, (int)$id]);
    flr_audit('', 'learn_rename', (string)(int)$id, $it['title'] . ' -> ' . $title . ' (by ' . $by . ')');
    return '';
}
function ln_file_url($id, $dl = false) { return 'learn_file.php?id=' . (int)$id . ($dl ? '&dl=1' : ''); }
function ln_when($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? date('M j, Y', strtotime($d)) : ''; }
