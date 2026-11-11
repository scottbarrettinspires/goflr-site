<?php
// Learn And Grow Your Business: slide presentations and playbooks from past Round Tables. The PDFs live on the server (outside the web
// root, served only to signed-in users) so every agent can view them in the page whatever Google account they use.
// Presentations are shown one slide at a time; playbooks scroll and can be downloaded. Staff add and remove items.
require_once __DIR__ . '/flrdb.php';
const LN_MAX_PDF = 40 * 1024 * 1024;
const LN_KINDS = ['presentation' => 'Presentations', 'playbook' => 'Playbooks'];

function ln_dir() { $d = flr_db_dir() . '/learn'; if (!is_dir($d)) @mkdir($d, 0750, true); return $d; }
function ln_db() {
    static $done = false; $pdo = flr_db();
    if ($done) return $pdo;
    $pdo->exec('CREATE TABLE IF NOT EXISTS learn_items (id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT NOT NULL, title TEXT NOT NULL, series TEXT, session TEXT, file TEXT NOT NULL, size INTEGER DEFAULT 0, added TEXT, added_by TEXT, deleted INTEGER DEFAULT 0)');
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
    $q = ln_db()->prepare('SELECT id, kind, title, series, session, size, added FROM learn_items WHERE deleted = 0' . ($kind !== '' ? ' AND kind = ?' : '') . ' ORDER BY session DESC, id DESC');
    $q->execute($kind !== '' ? [$kind] : []); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function ln_item($id) { $q = ln_db()->prepare('SELECT * FROM learn_items WHERE id = ? AND deleted = 0'); $q->execute([(int)$id]); return $q->fetch(PDO::FETCH_ASSOC) ?: null; }
// Staff only (the caller checks). $upload is the entry from $_FILES. Returns '' or a message to show.
function ln_add($kind, $title, $series, $session, array $upload, $by) {
    if (!isset(LN_KINDS[$kind])) return 'Choose Presentation or Playbook.';
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
    ln_db()->prepare('UPDATE learn_items SET deleted = 1 WHERE id = ?')->execute([(int)$id]);
    flr_audit('', 'learn_delete', (string)(int)$id, $it['title'] . ' (by ' . $by . ')');
    return '';
}
function ln_rename($id, $title, $by) {
    $it = ln_item($id); if (!$it) return 'That item was not found.';
    $title = trim(mb_substr((string)$title, 0, 140)); if ($title === '') return 'Enter a title.';
    ln_db()->prepare('UPDATE learn_items SET title = ? WHERE id = ?')->execute([$title, (int)$id]);
    flr_audit('', 'learn_rename', (string)(int)$id, $it['title'] . ' -> ' . $title . ' (by ' . $by . ')');
    return '';
}
function ln_file_url($id, $dl = false) { return 'learn_file.php?id=' . (int)$id . ($dl ? '&dl=1' : ''); }
function ln_when($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? date('M j, Y', strtotime($d)) : ''; }
