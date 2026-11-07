<?php
// A small shared inbox: agents can send a note to the office, and staff can post notes to each other, all in one
// feed that every staff/broker account sees on their Home page. Not a full two-way thread -- a message is either
// open or marked handled.
require_once __DIR__ . '/flrdb.php';

function msg_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS office_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, from_email TEXT, from_name TEXT, from_staff INTEGER DEFAULT 0, body TEXT NOT NULL, created TEXT, done INTEGER DEFAULT 0, done_by TEXT, done_at TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS office_messages_done ON office_messages(done, id)');
    return $pdo;
}
function msg_add($email, $name, $isStaff, $body) {
    $body = trim(mb_substr((string)$body, 0, 2000));
    if ($body === '') return false;
    msg_db()->prepare('INSERT INTO office_messages (from_email, from_name, from_staff, body, created) VALUES (?,?,?,?,?)')
        ->execute([strtolower(trim((string)$email)), trim((string)$name), $isStaff ? 1 : 0, $body, date('c')]);
    return true;
}
function msg_list($openOnly = true, $limit = 50) {
    $sql = 'SELECT * FROM office_messages' . ($openOnly ? ' WHERE done = 0' : '') . ' ORDER BY id DESC LIMIT ' . (int)$limit;
    return msg_db()->query($sql)->fetchAll();
}
function msg_done($id, $byEmail) {
    msg_db()->prepare('UPDATE office_messages SET done = 1, done_by = ?, done_at = ? WHERE id = ?')->execute([strtolower(trim((string)$byEmail)), date('c'), (int)$id]);
}
function msg_open_count() {
    return (int)msg_db()->query('SELECT COUNT(*) FROM office_messages WHERE done = 0')->fetchColumn();
}
