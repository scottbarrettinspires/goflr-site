<?php
require_once __DIR__ . '/lib.php';
flr_session();
header('Cache-Control: no-store');
$email = $_SESSION['email'] ?? '';
if ($email === '') { header('Location: index.php'); exit; }
if (time() - ($_SESSION['checked'] ?? 0) > RECHECK_SECONDS) {
    $ri = flr_roster_info($email);
    if (is_array($ri) && ($ri['active'] ?? false) === true) { $_SESSION['checked'] = time(); $_SESSION['perm'] = flr_perms($ri); if (flr_store_photo($email, $ri['photo'] ?? '')) { $_SESSION['pic'] = 'photo.php'; } }
    elseif (($st = flr_staff_info($email))) { $_SESSION['checked'] = time(); $_SESSION['perm'] = flr_perms($st); $_SESSION['staff'] = $st['position'] ?: 'Staff'; }
    else { flr_logout(); header('Location: index.php?e=1'); exit; }
}
