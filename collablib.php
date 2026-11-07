<?php
// Collaboration: a private, Facebook-group-style feed for FLR agents and staff -- posts (text + optional photo),
// reactions (Like, Love, Haha, Congrats, Wow, Sad), and comments. Everyone signed in can read, post, like and comment; authors can edit/delete their own
// posts and delete their own comments; staff can pin posts and remove any post or comment.
// Photos are resized on upload and kept outside the web folder (served to signed-in users by collab_img.php).
require_once __DIR__ . '/msglib.php'; // om_my_keys / om_person: who someone is and their directory headshot
date_default_timezone_set('America/New_York');

const CO_MAX_BODY = 5000;
const CO_MAX_COMMENT = 2000;
const CO_MAX_UPLOAD = 15000000; // bytes, before resizing
// Reactions, in picker order: key => [emoji, label, text color when it's yours].
const CO_REACTIONS = [
    'like'     => ['👍', 'Like', '#036f72'],
    'love'     => ['❤️', 'Love', '#e0245e'],
    'haha'     => ['😂', 'Haha', '#d49a00'],
    'congrats' => ['🎉', 'Congrats', '#c2570c'],
    'wow'      => ['😮', 'Wow', '#d49a00'],
    'sad'      => ['😢', 'Sad', '#d49a00'],
];

function co_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS co_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, author_email TEXT, author_key TEXT, author_name TEXT, author_staff INTEGER DEFAULT 0, body TEXT, image TEXT, pinned INTEGER DEFAULT 0, deleted INTEGER DEFAULT 0, created TEXT, edited TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS co_posts_feed ON co_posts(deleted, pinned, id)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS co_comments (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, author_email TEXT, author_key TEXT, author_name TEXT, author_staff INTEGER DEFAULT 0, body TEXT, deleted INTEGER DEFAULT 0, created TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS co_comments_post ON co_comments(post_id, deleted, id)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS co_likes (post_id INTEGER NOT NULL, email TEXT NOT NULL, name TEXT, created TEXT, PRIMARY KEY (post_id, email))');
    // Reactions: likes from before reactions existed count as 'like'.
    static $kindOk = false;
    if (!$kindOk) { $kindOk = true; $cols = array_column($pdo->query('PRAGMA table_info(co_likes)')->fetchAll(), 'name');
        if (!in_array('kind', $cols, true)) $pdo->exec("ALTER TABLE co_likes ADD COLUMN kind TEXT NOT NULL DEFAULT 'like'"); }
    return $pdo;
}
function co_img_dir() { $d = flr_db_dir() . '/collab'; if (!is_dir($d)) @mkdir($d, 0750, true); return $d; }

// Who's acting, as stored on posts/comments.
function co_me() {
    $email = strtolower(trim($_SESSION['email'] ?? '')); $staff = function_exists('flr_is_broker') && flr_is_broker();
    $keys = om_my_keys($email, $staff);
    return ['email' => $email, 'name' => trim($_SESSION['name'] ?? '') ?: $email, 'staff' => $staff ? 1 : 0, 'key' => $staff ? 'staff:' . $email : ($keys[0] ?? '')];
}

// ---- Photos: accept JPEG/PNG/WebP/GIF, fix phone rotation, shrink to 1600px, save as JPEG. Returns file name or error. ----
function co_save_image(array $f) {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['', ''];
    if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) return ['', ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) ? 'That photo is too large.' : 'The photo did not upload. Please try again.'];
    if (($f['size'] ?? 0) > CO_MAX_UPLOAD) return ['', 'That photo is too large (15 MB max).'];
    $raw = (string)@file_get_contents($f['tmp_name']); $info = @getimagesizefromstring($raw);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return ['', 'Please choose a photo (JPG, PNG, WebP or GIF).'];
    $im = @imagecreatefromstring($raw); if (!$im) return ['', 'That photo could not be read.'];
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)((@exif_read_data($f['tmp_name'])['Orientation']) ?? 1);
        if ($o === 3) $im = imagerotate($im, 180, 0); elseif ($o === 6) $im = imagerotate($im, -90, 0); elseif ($o === 8) $im = imagerotate($im, 90, 0);
    }
    $w = imagesx($im); $h = imagesy($im); $max = 1600; $s = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $s)); $nh = max(1, (int)round($h * $s));
    $out = imagecreatetruecolor($nw, $nh); imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $name = bin2hex(random_bytes(12)) . '.jpg';
    if (!imagejpeg($out, co_img_dir() . '/' . $name, 82)) return ['', 'The photo could not be saved.'];
    return [$name, ''];
}

// ---- Feed ----
function co_feed($limit = 20, $beforeId = 0) {
    $pdo = co_db();
    // Pinned posts lead the first page only; then everything else newest-first, paged by id.
    $pinned = $beforeId ? [] : $pdo->query('SELECT * FROM co_posts WHERE deleted = 0 AND pinned = 1 ORDER BY id DESC')->fetchAll();
    $q = $pdo->prepare('SELECT * FROM co_posts WHERE deleted = 0 AND pinned = 0' . ($beforeId ? ' AND id < ?' : '') . ' ORDER BY id DESC LIMIT ' . ((int)$limit + 1));
    $q->execute($beforeId ? [(int)$beforeId] : []); $rest = $q->fetchAll();
    $more = count($rest) > $limit; $rest = array_slice($rest, 0, $limit);
    $posts = array_merge($pinned, $rest);
    return [co_decorate($posts), $more];
}
// Add reactions, my reaction and comments to a list of post rows.
function co_decorate(array $posts) {
    if (!$posts) return [];
    $pdo = co_db(); $in = implode(',', array_map('intval', array_column($posts, 'id'))); $me = co_me()['email'];
    $likes = []; foreach ($pdo->query("SELECT post_id, email, name, kind FROM co_likes WHERE post_id IN ($in) ORDER BY created")->fetchAll() as $l) $likes[$l['post_id']][] = $l;
    $coms = []; foreach ($pdo->query("SELECT * FROM co_comments WHERE post_id IN ($in) AND deleted = 0 ORDER BY id")->fetchAll() as $c) $coms[$c['post_id']][] = $c;
    foreach ($posts as &$p) {
        $p['likes'] = $likes[$p['id']] ?? []; $p['mine'] = ''; foreach ($p['likes'] as $l) if ($l['email'] === $me) $p['mine'] = co_kind($l['kind']);
        $p['liked'] = $p['mine'] !== '';
        $p['comments'] = $coms[$p['id']] ?? [];
    }
    return $posts;
}
// One post on its own page (from the side lists), or null if it's gone.
function co_single($id) { $p = co_post($id); return $p ? co_decorate([$p])[0] : null; }

// Side lists. Recent: the 3 newest posts. Most active: the posts with the most likes + comments in the last 30
// days (comments count double, since they're a conversation), not repeating anything already in Recent.
function co_recent($n = 3) {
    return co_side_rows(co_db()->query('SELECT * FROM co_posts WHERE deleted = 0 ORDER BY id DESC LIMIT ' . (int)$n)->fetchAll());
}
function co_active($n = 3, array $skipIds = []) {
    $since = date('c', strtotime('-30 days')); $pdo = co_db();
    $q = $pdo->prepare('SELECT p.*, (SELECT COUNT(*) FROM co_likes l WHERE l.post_id = p.id AND l.created >= ?) AS lk,
        (SELECT COUNT(*) FROM co_comments c WHERE c.post_id = p.id AND c.deleted = 0 AND c.created >= ?) AS cm
        FROM co_posts p WHERE p.deleted = 0' . ($skipIds ? ' AND p.id NOT IN (' . implode(',', array_map('intval', $skipIds)) . ')' : '') . '
        ORDER BY (lk + 2 * cm) DESC, p.id DESC LIMIT ' . (int)$n);
    $q->execute([$since, $since]);
    return co_side_rows(array_values(array_filter($q->fetchAll(), function ($r) { return $r['lk'] + $r['cm'] > 0; })));
}
// Adds total like/comment counts and a short preview to side-list rows.
function co_side_rows(array $rows) {
    if (!$rows) return [];
    $pdo = co_db(); $in = implode(',', array_map('intval', array_column($rows, 'id')));
    $lk = $pdo->query("SELECT post_id, COUNT(*) FROM co_likes WHERE post_id IN ($in) GROUP BY post_id")->fetchAll(PDO::FETCH_KEY_PAIR);
    $top = []; foreach ($pdo->query("SELECT post_id, kind, COUNT(*) n FROM co_likes WHERE post_id IN ($in) GROUP BY post_id, kind ORDER BY n DESC")->fetchAll() as $t) { if (!isset($top[$t['post_id']])) $top[$t['post_id']] = co_kind($t['kind']); }
    $cm = $pdo->query("SELECT post_id, COUNT(*) FROM co_comments WHERE post_id IN ($in) AND deleted = 0 GROUP BY post_id")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($rows as &$r) {
        $r['n_likes'] = (int)($lk[$r['id']] ?? 0); $r['n_comments'] = (int)($cm[$r['id']] ?? 0); $r['top_kind'] = $top[$r['id']] ?? 'like';
        $t = trim(preg_replace('/\s+/', ' ', (string)$r['body']));
        $r['preview'] = $t === '' ? 'Shared a photo' : (mb_strlen($t) > 70 ? rtrim(mb_substr($t, 0, 68)) . '…' : $t);
    }
    return $rows;
}
function co_post($id) { $q = co_db()->prepare('SELECT * FROM co_posts WHERE id = ? AND deleted = 0'); $q->execute([(int)$id]); return $q->fetch() ?: null; }
function co_comment($id) { $q = co_db()->prepare('SELECT * FROM co_comments WHERE id = ? AND deleted = 0'); $q->execute([(int)$id]); return $q->fetch() ?: null; }

// ---- Actions (each checks permission itself) ----
function co_add_post($body, $image) {
    $me = co_me(); $body = trim(mb_substr((string)$body, 0, CO_MAX_BODY));
    if ($body === '' && $image === '') return 'Write something or add a photo.';
    co_db()->prepare('INSERT INTO co_posts (author_email, author_key, author_name, author_staff, body, image, created) VALUES (?,?,?,?,?,?,?)')
        ->execute([$me['email'], $me['key'], $me['name'], $me['staff'], $body, $image, date('c')]);
    return (int)co_db()->lastInsertId();
}
function co_edit_post($id, $body) {
    $me = co_me(); $p = co_post($id); if (!$p || $p['author_email'] !== $me['email']) return 'You can only edit your own posts.';
    $body = trim(mb_substr((string)$body, 0, CO_MAX_BODY)); if ($body === '' && $p['image'] === '') return 'A post needs some text or a photo.';
    co_db()->prepare('UPDATE co_posts SET body = ?, edited = ? WHERE id = ?')->execute([$body, date('c'), (int)$id]); return '';
}
function co_delete_post($id) {
    $me = co_me(); $p = co_post($id); if (!$p || ($p['author_email'] !== $me['email'] && !$me['staff'])) return 'Not allowed.';
    co_db()->prepare('UPDATE co_posts SET deleted = 1, edited = ? WHERE id = ?')->execute([date('c'), (int)$id]);
    if ($p['image'] !== '') @unlink(co_img_dir() . '/' . basename($p['image']));
    if ($p['author_email'] !== $me['email']) flr_audit('', 'collab_remove_post', (string)$id, 'Removed by staff: ' . $me['email']);
    return '';
}
function co_pin_post($id, $on) {
    $me = co_me(); if (!$me['staff'] || !co_post($id)) return 'Not allowed.';
    co_db()->prepare('UPDATE co_posts SET pinned = ? WHERE id = ?')->execute([$on ? 1 : 0, (int)$id]); return '';
}
function co_add_comment($postId, $body) {
    $me = co_me(); if (!co_post($postId)) return 'That post is no longer available.';
    $body = trim(mb_substr((string)$body, 0, CO_MAX_COMMENT)); if ($body === '') return 'Write a comment first.';
    co_db()->prepare('INSERT INTO co_comments (post_id, author_email, author_key, author_name, author_staff, body, created) VALUES (?,?,?,?,?,?,?)')
        ->execute([(int)$postId, $me['email'], $me['key'], $me['name'], $me['staff'], $body, date('c')]);
    return '';
}
function co_delete_comment($id) {
    $me = co_me(); $c = co_comment($id); if (!$c || ($c['author_email'] !== $me['email'] && !$me['staff'])) return 'Not allowed.';
    co_db()->prepare('UPDATE co_comments SET deleted = 1 WHERE id = ?')->execute([(int)$id]); return '';
}
function co_kind($k) { return isset(CO_REACTIONS[$k]) ? $k : 'like'; }
// Set my reaction on a post. Choosing the reaction I already have (or '') removes it; a different one replaces it.
// Returns co_reaction_state() for the post, or null if the post is gone.
function co_react($postId, $kind) {
    $me = co_me(); if (!co_post($postId)) return null; $pdo = co_db();
    $kind = isset(CO_REACTIONS[$kind]) ? $kind : '';
    $cur = $pdo->prepare('SELECT kind FROM co_likes WHERE post_id = ? AND email = ?'); $cur->execute([(int)$postId, $me['email']]); $had = $cur->fetchColumn();
    if ($kind === '' || $had === $kind) { $pdo->prepare('DELETE FROM co_likes WHERE post_id = ? AND email = ?')->execute([(int)$postId, $me['email']]); }
    else { $pdo->prepare('INSERT INTO co_likes (post_id, email, name, kind, created) VALUES (?,?,?,?,?) ON CONFLICT(post_id, email) DO UPDATE SET kind = excluded.kind, name = excluded.name')->execute([(int)$postId, $me['email'], $me['name'], $kind, date('c')]); }
    return co_reaction_state($postId);
}
// What the page needs to redraw a post's reactions: my reaction, counts by kind (most first), and who reacted.
function co_reaction_state($postId) {
    $me = co_me()['email']; $q = co_db()->prepare('SELECT email, name, kind FROM co_likes WHERE post_id = ? ORDER BY created'); $q->execute([(int)$postId]);
    $mine = ''; $counts = []; $people = [];
    foreach ($q->fetchAll() as $l) { $k = co_kind($l['kind']); $counts[$k] = ($counts[$k] ?? 0) + 1; if ($l['email'] === $me) $mine = $k;
        $people[] = ['name' => $l['email'] === $me ? 'You' : $l['name'], 'kind' => $k, 'me' => $l['email'] === $me]; }
    arsort($counts);
    return ['mine' => $mine, 'counts' => $counts, 'total' => array_sum($counts), 'people' => $people];
}

// ---- "New since your last visit" for the Home page ----
function co_new_count($email) {
    $pdo = co_db(); $k = 'co_seen_' . sha1(strtolower($email));
    $q = $pdo->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute([$k]); $last = (int)$q->fetchColumn();
    $n = $pdo->prepare('SELECT COUNT(*) FROM co_posts WHERE deleted = 0 AND id > ? AND author_email <> ?'); $n->execute([$last, strtolower($email)]);
    return (int)$n->fetchColumn();
}
function co_mark_seen($email) {
    $pdo = co_db(); $max = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM co_posts')->fetchColumn();
    $pdo->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)')->execute(['co_seen_' . sha1(strtolower($email)), (string)$max]);
}

// ---- Display helpers ----
function co_ago($iso) {
    $t = strtotime($iso); $d = time() - $t;
    if ($d < 60) return 'Just now'; if ($d < 3600) return floor($d / 60) . 'm'; if ($d < 86400) return floor($d / 3600) . 'h';
    if ($d < 7 * 86400) return floor($d / 86400) . 'd';
    return date(date('Y', $t) === date('Y') ? 'M j' : 'M j, Y', $t);
}
// Round headshot (directory photo, else initials). $p is om_person()'s result.
function co_av($p, $px) {
    return '<span class="cav" style="width:' . $px . 'px;height:' . $px . 'px;flex-basis:' . $px . 'px;font-size:' . round($px * .38) . 'px"><span>' . htmlspecialchars($p['ini']) . '</span>'
        . ($p['photo'] ? '<img src="' . htmlspecialchars($p['photo']) . '" alt="" loading="lazy" onerror="this.remove()">' : '') . '</span>';
}
// One side-list entry: thumbnail (the post's photo, else the author's headshot), name, preview, counts.
function co_side_item($r, $current) {
    $au = om_person($r['author_key'], $r['author_name']);
    $th = $r['image'] !== '' ? '<span class="coth"><img src="collab_img.php?f=' . rawurlencode($r['image']) . '" alt=""></span>' : co_av($au, 52);
    $meta = htmlspecialchars(co_ago($r['created'])) . ($r['n_likes'] ? ' &middot; ' . CO_REACTIONS[$r['top_kind']][0] . ' ' . $r['n_likes'] : '') . ($r['n_comments'] ? ' &middot; &#128172; ' . $r['n_comments'] : '');
    return '<a class="coitem' . ($current ? ' on' : '') . '" href="collab.php?post=' . (int)$r['id'] . '#p' . (int)$r['id'] . '">' . $th
        . '<span class="cotx"><span class="nm">' . htmlspecialchars($r['author_name'] ?: $r['author_email']) . '</span><span class="pv">' . htmlspecialchars($r['preview']) . '</span><span class="mt">' . $meta . '</span></span></a>';
}
// Escaped text with line breaks kept and web links made clickable.
function co_text($s) {
    $out = '';
    foreach (preg_split('~(https?://[^\s<>"\']+[^\s<>"\'.,;:!?)\]])~i', (string)$s, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $part) {
        $e = htmlspecialchars($part);
        $out .= ($i % 2) ? '<a href="' . $e . '" target="_blank" rel="noopener nofollow">' . $e . '</a>' : $e;
    }
    return $out;
}
