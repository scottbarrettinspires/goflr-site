<?php
// Serves affiliate pictures to signed-in agents only. ?f=<file in private_assets/> (brokerage affiliates) or
// ?id=<favorite id>&n=<1|2> (the agent's own favorite; only the owner can see it).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/afflib.php";
$p = '';
if (isset($_GET['id'])) {
    $id = (int)$_GET['id']; $n = ((int)($_GET['n'] ?? 1)) === 2 ? 2 : 1;
    $q = af_db()->prepare('SELECT 1 FROM affiliates WHERE id = ? AND akey = ?'); $q->execute([$id, flr_akey($_SESSION['email'] ?? '')]);
    if ($q->fetchColumn()) $p = af_photo_path($id, $n);
} else {
    $f = basename((string)($_GET['f'] ?? ''));
    if (preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png)$/i', $f)) $p = __DIR__ . '/private_assets/' . $f;
}
if ($p === '' || !is_file($p)) { http_response_code(404); exit; }
header('Content-Type: ' . (preg_match('/\.png$/i', $p) ? 'image/png' : 'image/jpeg')); header('Cache-Control: private, max-age=3600');
readfile($p);
