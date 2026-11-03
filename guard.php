<?php
require_once __DIR__ . '/lib.php';
flr_session();
header('Cache-Control: no-store');
$email = $_SESSION['email'] ?? '';
if ($email === '') { header('Location: index.php'); exit; }
if (time() - ($_SESSION['checked'] ?? 0) > RECHECK_SECONDS) {
    $ri = flr_roster_info($email);
    if (is_array($ri) && ($ri['active'] ?? false) === true) { $_SESSION['checked'] = time(); $_SESSION['perm'] = flr_perms($ri); if (is_string($ri['photo'] ?? null) && preg_match('#^data:image/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]{20,60000}$#', $ri['photo'])) { $_SESSION['pic'] = $ri['photo']; } }
    else { flr_logout(); header('Location: index.php?e=1'); exit; }
}
