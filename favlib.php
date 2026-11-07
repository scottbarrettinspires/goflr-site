<?php
// Directory favorites: each signed-in person can star agents they work with, and starred agents are listed in a
// Favorites section at the top of their Directory. Private to the person who starred them (no one is told).
// Keyed by the signed-in email and the agent's directory email.
require_once __DIR__ . '/flrdb.php';

function fav_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS dir_favs (owner TEXT NOT NULL, fav_email TEXT NOT NULL, created TEXT, PRIMARY KEY (owner, fav_email))');
    return $pdo;
}
// [fav_email => true] for this person.
function fav_list($owner) {
    $q = fav_db()->prepare('SELECT fav_email FROM dir_favs WHERE owner = ?'); $q->execute([strtolower(trim((string)$owner))]);
    return array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN), true);
}
function fav_set($owner, $favEmail, $on) {
    $owner = strtolower(trim((string)$owner)); $favEmail = strtolower(trim((string)$favEmail));
    if ($owner === '' || !filter_var($favEmail, FILTER_VALIDATE_EMAIL) || $owner === $favEmail) return false;
    if ($on) fav_db()->prepare('INSERT OR IGNORE INTO dir_favs (owner, fav_email, created) VALUES (?,?,?)')->execute([$owner, $favEmail, date('c')]);
    else fav_db()->prepare('DELETE FROM dir_favs WHERE owner = ? AND fav_email = ?')->execute([$owner, $favEmail]);
    return true;
}
