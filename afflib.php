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
const AF_FIELDS = ['name' => 120, 'contact' => 120, 'phone' => 40, 'email' => 160, 'website' => 200, 'notes' => 600];

function af_db() {
    static $done = false; $pdo = flr_db();
    if (!$done) { $done = true;
        $pdo->exec('CREATE TABLE IF NOT EXISTS affiliates (id INTEGER PRIMARY KEY AUTOINCREMENT, akey TEXT NOT NULL, cat TEXT NOT NULL, name TEXT NOT NULL,
            contact TEXT, phone TEXT, email TEXT, website TEXT, notes TEXT, created TEXT, updated TEXT)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS affiliates_akey ON affiliates (akey, cat)');
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
    if ($v['website'] !== '' && !preg_match('~^https?://~i', $v['website'])) $v['website'] = 'https://' . $v['website'];
    if ($v['website'] !== '' && !filter_var($v['website'], FILTER_VALIDATE_URL)) return [0, 'Please check the website address.'];
    $ak = flr_akey($me); $now = date('c'); $id = (int)($in['id'] ?? 0);
    if ($id) {
        $q = af_db()->prepare('UPDATE affiliates SET cat = ?, name = ?, contact = ?, phone = ?, email = ?, website = ?, notes = ?, updated = ? WHERE id = ? AND akey = ?');
        $q->execute([$cat, $v['name'], $v['contact'], $v['phone'], $v['email'], $v['website'], $v['notes'], $now, $id, $ak]);
        if (!$q->rowCount()) return [0, 'That business was not found in your favorites.'];
        flr_audit($ak, 'affiliate', (string)$id, 'Updated ' . AF_CATS[$cat] . ': ' . $v['name']);
        return [$id, ''];
    }
    af_db()->prepare('INSERT INTO affiliates (akey, cat, name, contact, phone, email, website, notes, created, updated) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$ak, $cat, $v['name'], $v['contact'], $v['phone'], $v['email'], $v['website'], $v['notes'], $now, $now]);
    $id = (int)af_db()->lastInsertId();
    flr_audit($ak, 'affiliate', (string)$id, 'Added ' . AF_CATS[$cat] . ': ' . $v['name']);
    return [$id, ''];
}
function af_delete($me, $id) {
    $ak = flr_akey($me); $q = af_db()->prepare('DELETE FROM affiliates WHERE id = ? AND akey = ?'); $q->execute([(int)$id, $ak]);
    if (!$q->rowCount()) return 'That business was not found in your favorites.';
    flr_audit($ak, 'affiliate', (string)$id, 'Removed a favorite business'); return '';
}
