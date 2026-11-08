<?php
// Forms library: the forms agents see on the Forms page (Active Business > Forms), stored on the server so staff can
// add, edit, move and delete forms and sections from the site itself (Settings > Forms). Each form is either a Google
// Drive file (View / Download open Drive) or a PDF uploaded here (stored outside the web root, served by form_file.php
// to signed-in users only). The first time the table is empty it is filled from formsseed.php (the old fixed list).
require_once __DIR__ . '/flrdb.php';

const FM_CATS = ['buyer' => 'Buyer', 'seller' => 'Seller', 'agent' => 'Agent', 'commercial' => 'Commercial', 'leasing' => 'Leasing'];
const FM_MAX_PDF = 25000000;

function fm_db() {
    static $done = false; $pdo = flr_db();
    if ($done) return $pdo;
    $pdo->exec('CREATE TABLE IF NOT EXISTS form_sections (id INTEGER PRIMARY KEY AUTOINCREMENT, cat TEXT NOT NULL, name TEXT NOT NULL, sort INTEGER DEFAULT 0, deleted INTEGER DEFAULT 0, updated TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS form_items (id INTEGER PRIMARY KEY AUTOINCREMENT, section_id INTEGER NOT NULL, name TEXT NOT NULL, drive_id TEXT, file TEXT, sort INTEGER DEFAULT 0, deleted INTEGER DEFAULT 0, updated TEXT, updated_by TEXT)');
    $done = true;
    if (!(int)$pdo->query('SELECT COUNT(*) FROM form_sections')->fetchColumn()) {
        $seed = require __DIR__ . '/formsseed.php'; $now = date('c');
        $pdo->beginTransaction();
        foreach ($seed as $i => $s) {
            $pdo->prepare('INSERT INTO form_sections (cat, name, sort, updated) VALUES (?,?,?,?)')->execute([$s['cat'], $s['section'], ($i + 1) * 10, $now]);
            $sid = (int)$pdo->lastInsertId();
            foreach ($s['forms'] as $j => $f) $pdo->prepare('INSERT INTO form_items (section_id, name, drive_id, file, sort, updated, updated_by) VALUES (?,?,?,?,?,?,?)')->execute([$sid, $f[0], $f[1], '', ($j + 1) * 10, $now, 'starting list']);
        }
        $pdo->commit();
    }
    return $pdo;
}
function fm_dir() { $d = flr_db_dir() . '/forms'; if (!is_dir($d)) @mkdir($d, 0750, true); return $d; }

// [cat => [ ['id','name','forms' => [...]], ... ]] -- every category key present, sorted.
function fm_all() {
    $pdo = fm_db(); $out = array_fill_keys(array_keys(FM_CATS), []); $byId = [];
    foreach ($pdo->query('SELECT * FROM form_sections WHERE deleted = 0 ORDER BY sort, id') as $s) {
        if (!isset($out[$s['cat']])) continue;
        $byId[$s['id']] = [$s['cat'], count($out[$s['cat']])];
        $out[$s['cat']][] = ['id' => (int)$s['id'], 'name' => $s['name'], 'forms' => []];
    }
    foreach ($pdo->query('SELECT * FROM form_items WHERE deleted = 0 ORDER BY id') as $f) {
        if (!isset($byId[$f['section_id']])) continue;
        list($c, $i) = $byId[$f['section_id']]; $out[$c][$i]['forms'][] = $f;
    }
    $key = function ($n) { return strtolower(preg_replace('/[^A-Za-z0-9 ]/', '', $n)); }; // sort by name, ignoring quotes and brackets
    foreach ($out as &$secs) foreach ($secs as &$sec) usort($sec['forms'], function ($a, $b) use ($key) { return strcmp($key($a['name']), $key($b['name'])); });
    unset($secs, $sec);
    return $out;
}
function fm_item($id) { $q = fm_db()->prepare('SELECT * FROM form_items WHERE id = ? AND deleted = 0'); $q->execute([(int)$id]); return $q->fetch() ?: null; }
function fm_section($id) { $q = fm_db()->prepare('SELECT * FROM form_sections WHERE id = ? AND deleted = 0'); $q->execute([(int)$id]); return $q->fetch() ?: null; }

// Links for one form.
function fm_view_url($f) { return $f['file'] !== '' && $f['file'] !== null ? 'form_file.php?id=' . (int)$f['id'] : 'https://drive.google.com/file/d/' . rawurlencode($f['drive_id']) . '/view'; }
function fm_dl_url($f) { return $f['file'] !== '' && $f['file'] !== null ? 'form_file.php?id=' . (int)$f['id'] . '&dl=1' : 'https://drive.google.com/uc?export=download&id=' . rawurlencode($f['drive_id']); }

// A Google Drive file link (or just the file ID) -> the file ID, or ''.
function fm_drive_id($s) {
    $s = trim((string)$s);
    if (preg_match('~/d/([A-Za-z0-9_-]{20,})~', $s, $m) || preg_match('~[?&]id=([A-Za-z0-9_-]{20,})~', $s, $m) || preg_match('~^([A-Za-z0-9_-]{20,})$~', $s, $m)) return $m[1];
    return '';
}

// ---- Staff changes. Each returns '' on success or an error message. ----
function fm_save_item($id, $sectionId, $name, $link, array $upload, $by) {
    $pdo = fm_db(); $name = trim(mb_substr((string)$name, 0, 140));
    if ($name === '') return 'Enter the form name.';
    if (!fm_section($sectionId)) return 'Choose the section the form goes in.';
    $old = $id ? fm_item($id) : null; if ($id && !$old) return 'That form was not found.';
    $drive = ''; $file = '';
    $hasUp = !empty($upload['tmp_name']) && is_uploaded_file($upload['tmp_name']);
    if ($hasUp) {
        if ((int)$upload['size'] > FM_MAX_PDF) return 'That PDF is too large (25 MB max).';
        $fh = fopen($upload['tmp_name'], 'rb'); $head = $fh ? fread($fh, 5) : ''; if ($fh) fclose($fh);
        if ($head !== '%PDF-') return 'Please upload a PDF file.';
        $file = bin2hex(random_bytes(12)) . '.pdf';
        if (!move_uploaded_file($upload['tmp_name'], fm_dir() . '/' . $file)) return 'The PDF could not be saved. Please try again.';
    } elseif (trim((string)$link) !== '') {
        $drive = fm_drive_id($link); if ($drive === '') return 'That doesn\'t look like a Google Drive file link. Open the PDF in Drive, choose Share > Copy link, and paste it here.';
    } elseif ($old) { $drive = (string)$old['drive_id']; $file = (string)$old['file']; }
    else return 'Paste the form\'s Google Drive link or upload the PDF.';
    $now = date('c');
    if ($old) {
        if (($hasUp || $drive !== '') && $old['file'] !== '' && $old['file'] !== $file) @unlink(fm_dir() . '/' . basename($old['file']));
        $pdo->prepare('UPDATE form_items SET section_id=?, name=?, drive_id=?, file=?, updated=?, updated_by=? WHERE id=?')->execute([(int)$sectionId, $name, $drive, $file, $now, $by, (int)$id]);
        flr_audit('', 'form_edit', (string)$id, $name . ' (by ' . $by . ')');
    } else {
        $pdo->prepare('INSERT INTO form_items (section_id, name, drive_id, file, sort, updated, updated_by) VALUES (?,?,?,?,0,?,?)')->execute([(int)$sectionId, $name, $drive, $file, $now, $by]);
        flr_audit('', 'form_add', (string)$pdo->lastInsertId(), $name . ' (by ' . $by . ')');
    }
    return '';
}
function fm_delete_item($id, $by) {
    $f = fm_item($id); if (!$f) return 'That form was not found.';
    fm_db()->prepare('UPDATE form_items SET deleted = 1, updated = ?, updated_by = ? WHERE id = ?')->execute([date('c'), $by, (int)$id]);
    flr_audit('', 'form_delete', (string)$id, $f['name'] . ' (by ' . $by . ')'); // uploaded PDF kept on disk, so a delete can be undone by the office
    return '';
}
function fm_save_section($id, $cat, $name, $by) {
    $name = trim(mb_substr((string)$name, 0, 80)); if ($name === '') return 'Enter the section name.';
    $pdo = fm_db();
    if ($id) { if (!fm_section($id)) return 'That section was not found.'; $pdo->prepare('UPDATE form_sections SET name = ?, updated = ? WHERE id = ?')->execute([$name, date('c'), (int)$id]); }
    else {
        if (!isset(FM_CATS[$cat])) return 'Choose a category.';
        $q = $pdo->prepare('SELECT COALESCE(MAX(sort), 0) FROM form_sections WHERE cat = ?'); $q->execute([$cat]);
        $pdo->prepare('INSERT INTO form_sections (cat, name, sort, updated) VALUES (?,?,?,?)')->execute([$cat, $name, (int)$q->fetchColumn() + 10, date('c')]);
    }
    flr_audit('', 'form_section', (string)$id, $name . ' (by ' . $by . ')');
    return '';
}
function fm_delete_section($id, $by) {
    $s = fm_section($id); if (!$s) return 'That section was not found.';
    $q = fm_db()->prepare('SELECT COUNT(*) FROM form_items WHERE section_id = ? AND deleted = 0'); $q->execute([(int)$id]);
    if ((int)$q->fetchColumn()) return 'Move or delete the forms in "' . $s['name'] . '" first, then delete the section.';
    fm_db()->prepare('UPDATE form_sections SET deleted = 1, updated = ? WHERE id = ?')->execute([date('c'), (int)$id]);
    flr_audit('', 'form_section_delete', (string)$id, $s['name'] . ' (by ' . $by . ')');
    return '';
}
// Move a section up (-1) or down (+1) within its category.
function fm_move_section($id, $dir) {
    $s = fm_section($id); if (!$s) return;
    $q = fm_db()->prepare('SELECT id, sort FROM form_sections WHERE cat = ? AND deleted = 0 ORDER BY sort, id'); $q->execute([$s['cat']]); $rows = $q->fetchAll();
    $ids = array_column($rows, 'id'); $i = array_search($s['id'], $ids); $j = $i + ($dir < 0 ? -1 : 1);
    if ($i === false || !isset($ids[$j])) return;
    $t = $ids[$i]; $ids[$i] = $ids[$j]; $ids[$j] = $t;
    $u = fm_db()->prepare('UPDATE form_sections SET sort = ? WHERE id = ?'); foreach ($ids as $k => $sid) $u->execute([($k + 1) * 10, $sid]);
}
// Drive file ID => form name, for every Drive form (sign.php uses this to know which files can be sent for signature).
function fm_drive_names() { $o = []; foreach (fm_db()->query("SELECT drive_id, name FROM form_items WHERE deleted = 0 AND drive_id <> ''") as $r) $o[$r['drive_id']] = $r['name']; return $o; }
