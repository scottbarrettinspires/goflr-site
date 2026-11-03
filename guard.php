<?php
require_once __DIR__ . '/lib.php';
flr_session();
header('Cache-Control: no-store');
$email = $_SESSION['email'] ?? '';
if ($email === '') { header('Location: index.php'); exit; }
if (time() - ($_SESSION['checked'] ?? 0) > RECHECK_SECONDS) {
    if (flr_roster_active($email)) { $_SESSION['checked'] = time(); }
    else { flr_logout(); header('Location: index.php?e=1'); exit; }
}
