<?php
// Favorite Affiliated Businesses: each agent's own list of go-to vendors (title, lenders, inspectors, sign posts,
// photographers...). Stored in the affiliates table; every action is a plain function so pages, affiliates_api.php
// and assistants (FLR MCP) use the same rules.
require_once __DIR__ . '/flrdb.php';

const AF_CATS = [
    'title'      => 'Title Companies',
    'lender'     => 'Lenders',
    'inspector'  => 'Home Inspectors',
    'signco'     => 'Sign Post Companies',
    'photo'      => 'Photographers',
    'installer'  => 'Sign Post Installers',
];
const AF_FIELDS = ['name' => 120, 'contact' => 120, 'role' => 80, 'phone' => 40, 'email' => 160, 'contact2' => 120, 'role2' => 80, 'phone2' => 40, 'email2' => 160, 'website' => 200, 'notes' => 600];
// Photo storage: flrdata/affphotos/<id>_<1|2>.jpg, shown only to the agent who owns the record.
function af_photo_dir() { $d = flr_db_dir() . '/affphotos'; if (!is_dir($d)) @mkdir($d, 0750, true); return $d; }
function af_photo_path($id, $n) { return af_photo_dir() . '/' . (int)$id . '_' . ((int)$n === 2 ? 2 : 1) . '.jpg'; }
// Save an uploaded picture (from $_FILES entry) as a 300px square JPEG. Returns '' or an error message.
function af_photo_save($id, $n, array $up) {
    if (($up['error'] ?? 4) === 4) return '';
    if (($up['error'] ?? 0) !== 0 || !is_uploaded_file($up['tmp_name'] ?? '')) return 'The picture could not be uploaded. Please try a smaller image.';
    if (($up['size'] ?? 0) > 6 * 1024 * 1024) return 'That picture is too large (6 MB limit).';
    $raw = (string)file_get_contents($up['tmp_name']); $info = @getimagesizefromstring($raw);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return 'Please choose a JPG or PNG picture.';
    $src = function_exists('imagecreatefromstring') ? @imagecreatefromstring($raw) : false;
    if (!$src) { if ($info[2] !== IMAGETYPE_JPEG) return 'That picture type could not be read. Please use a JPG.'; return file_put_contents(af_photo_path($id, $n), $raw) === false ? 'The picture could not be saved.' : ''; }
    $w = imagesx($src); $h = imagesy($src); $s = min($w, $h); $dst = imagecreatetruecolor(300, 300);
    imagecopyresampled($dst, $src, 0, 0, (int)(($w - $s) / 2), (int)max(0, ($h - $s) / 2 - $s * .05), 300, 300, $s, $s);
    $ok = imagejpeg($dst, af_photo_path($id, $n), 88); return $ok ? '' : 'The picture could not be saved.';
}
function af_photo_remove($id, $n) { @unlink(af_photo_path($id, $n)); }
function af_has_photo($id, $n) { return is_file(af_photo_path($id, $n)); }
// Up to two contact persons of a favorite as [{name, role, phone, email, photo(url or '')}, ...] (empty slots skipped).
function af_contacts(array $r) {
    $out = [];
    foreach ([1 => ['contact', 'role', 'phone', 'email'], 2 => ['contact2', 'role2', 'phone2', 'email2']] as $n => $k) {
        $nm = trim((string)($r[$k[0]] ?? '')); $ph = trim((string)($r[$k[2]] ?? '')); $em = trim((string)($r[$k[3]] ?? ''));
        if ($nm === '' && $ph === '' && $em === '') continue;
        $out[] = ['name' => $nm, 'title' => trim((string)($r[$k[1]] ?? '')), 'phone' => $ph, 'email' => $em, 'photo' => (!empty($r['id']) && af_has_photo($r['id'], $n)) ? 'affphoto.php?id=' . (int)$r['id'] . '&n=' . $n : ''];
    }
    return $out;
}
// The brokerage companies' contacts in the same shape (photos come from private_assets/).
function af_brokerage_contacts(array $c) {
    $out = []; foreach (array_slice((array)($c['contacts'] ?? []), 0, 2) as $m) $out[] = ['name' => (string)($m['name'] ?? ''), 'title' => (string)($m['title'] ?? ''), 'phone' => (string)($m['phone'] ?? ''), 'email' => (string)($m['email'] ?? ''), 'photo' => !empty($m['photo']) ? 'affphoto.php?f=' . urlencode($m['photo']) : ''];
    return $out;
}

// Category keys in alphabetical order by label (how every list shows them).
function af_cats_sorted() { $c = AF_CATS; asort($c, SORT_NATURAL | SORT_FLAG_CASE); return $c; }
// The brokerage's own affiliated companies in one category (always shown first, for every agent). See brokerage_affiliates.php.
function af_brokerage($cat = null) {
    $cfg = (array)(@include __DIR__ . '/brokerage_affiliates.php'); $out = [];
    foreach ((array)($cfg['companies'] ?? []) as $c) { if ($cat === null || ($c['cat'] ?? '') === $cat) $out[] = $c; }
    return $out;
}

function af_db() {
    static $done = false; $pdo = flr_db();
    if (!$done) { $done = true;
        $pdo->exec('CREATE TABLE IF NOT EXISTS affiliates (id INTEGER PRIMARY KEY AUTOINCREMENT, akey TEXT NOT NULL, cat TEXT NOT NULL, name TEXT NOT NULL,
            contact TEXT, phone TEXT, email TEXT, website TEXT, notes TEXT, created TEXT, updated TEXT)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS affiliates_akey ON affiliates (akey, cat)');
        $have = []; foreach ($pdo->query('PRAGMA table_info(affiliates)') as $c) $have[$c['name']] = 1;
        foreach (['role', 'contact2', 'role2', 'phone2', 'email2'] as $c) if (!isset($have[$c])) $pdo->exec('ALTER TABLE affiliates ADD COLUMN ' . $c . ' TEXT');
    }
    return $pdo;
}
// All of one agent's favorites, grouped by category key (every category present, possibly empty), A to Z.
function af_list($me) {
    $out = []; foreach (AF_CATS as $k => $l) $out[$k] = [];
    $q = af_db()->prepare('SELECT * FROM affiliates WHERE akey = ? ORDER BY name COLLATE NOCASE'); $q->execute([flr_akey($me)]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) { if (isset($out[$r['cat']])) $out[$r['cat']][] = $r; }
    return $out;
}
// Create (no id) or update (id) one favorite. Returns [id, ''] or [0, error message].
function af_save($me, array $in) {
    $cat = (string)($in['cat'] ?? ''); if (!isset(AF_CATS[$cat])) return [0, 'Choose a category.'];
    $v = []; foreach (AF_FIELDS as $f => $max) $v[$f] = mb_substr(trim((string)($in[$f] ?? '')), 0, $max);
    if ($v['name'] === '') return [0, 'Enter the business name.'];
    if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) return [0, 'Please check the email address: ' . $v['email']];
    if ($v['email2'] !== '' && !filter_var($v['email2'], FILTER_VALIDATE_EMAIL)) return [0, 'Please check the second contact\'s email address: ' . $v['email2']];
    if ($v['website'] !== '' && !preg_match('~^https?://~i', $v['website'])) $v['website'] = 'https://' . $v['website'];
    if ($v['website'] !== '' && !filter_var($v['website'], FILTER_VALIDATE_URL)) return [0, 'Please check the website address.'];
    $ak = flr_akey($me); $now = date('c'); $id = (int)($in['id'] ?? 0);
    if ($id) {
        $q = af_db()->prepare('UPDATE affiliates SET cat = ?, name = ?, contact = ?, role = ?, phone = ?, email = ?, contact2 = ?, role2 = ?, phone2 = ?, email2 = ?, website = ?, notes = ?, updated = ? WHERE id = ? AND akey = ?');
        $q->execute([$cat, $v['name'], $v['contact'], $v['role'], $v['phone'], $v['email'], $v['contact2'], $v['role2'], $v['phone2'], $v['email2'], $v['website'], $v['notes'], $now, $id, $ak]);
        if (!$q->rowCount()) return [0, 'That business was not found in your favorites.'];
        $pe = af_photos_apply($id, $in);
        flr_audit($ak, 'affiliate', (string)$id, 'Updated ' . AF_CATS[$cat] . ': ' . $v['name']);
        return [$id, $pe];
    }
    af_db()->prepare('INSERT INTO affiliates (akey, cat, name, contact, role, phone, email, contact2, role2, phone2, email2, website, notes, created, updated) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$ak, $cat, $v['name'], $v['contact'], $v['role'], $v['phone'], $v['email'], $v['contact2'], $v['role2'], $v['phone2'], $v['email2'], $v['website'], $v['notes'], $now, $now]);
    $id = (int)af_db()->lastInsertId(); $pe = af_photos_apply($id, $in);
    flr_audit($ak, 'affiliate', (string)$id, 'Added ' . AF_CATS[$cat] . ': ' . $v['name']);
    return [$id, $pe];
}
// Apply picture uploads / removals from a save request ($_FILES photo1, photo2; $in['rmphoto1'], $in['rmphoto2']). Returns '' or an error.
function af_photos_apply($id, array $in) {
    foreach ([1, 2] as $n) {
        if (!empty($in['rmphoto' . $n])) af_photo_remove($id, $n);
        if (!empty($_FILES['photo' . $n])) { $e = af_photo_save($id, $n, $_FILES['photo' . $n]); if ($e !== '') return 'Saved, but ' . lcfirst($e); }
    }
    return '';
}
function af_delete($me, $id) {
    $ak = flr_akey($me); $q = af_db()->prepare('DELETE FROM affiliates WHERE id = ? AND akey = ?'); $q->execute([(int)$id, $ak]);
    if (!$q->rowCount()) return 'That business was not found in your favorites.';
    af_photo_remove($id, 1); af_photo_remove($id, 2); flr_audit($ak, 'affiliate', (string)$id, 'Removed a favorite business'); return '';
}

// HTML for a list of contact persons (from af_contacts / af_brokerage_contacts): photo, name, title, phone, email.
function af_contacts_html(array $cs) {
    $h = function ($x) { return htmlspecialchars((string)$x); }; $o = '';
    foreach ($cs as $m) {
        $d = preg_replace('/[^0-9+]/', '', (string)$m['phone']); $x = preg_replace('/\D/', '', (string)$m['phone']); if (strlen($x) === 11 && $x[0] === '1') $x = substr($x, 1);
        $ph = strlen($x) === 10 ? substr($x, 0, 3) . '-' . substr($x, 3, 3) . '-' . substr($x, 6) : (string)$m['phone'];
        $o .= '<div class="amgr">' . ($m['photo'] !== '' ? '<img src="' . $h($m['photo']) . '" alt="' . $h($m['name']) . '" loading="lazy" onerror="this.remove()">' : '') . '<div>'
            . ($m['name'] !== '' ? '<b>' . $h($m['name']) . '</b>' : '') . ($m['title'] !== '' ? '<div class="dsub">' . $h($m['title']) . '</div>' : '')
            . ($m['phone'] !== '' ? '<div><a href="tel:' . $h($d) . '">' . $h($ph) . '</a></div>' : '')
            . ($m['email'] !== '' ? '<div><a href="mailto:' . $h($m['email']) . '">' . $h($m['email']) . '</a></div>' : '') . '</div></div>';
    }
    return $o;
}
