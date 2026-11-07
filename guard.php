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
// Small "Powered by Hynri" badge, bottom-right of every signed-in HTML page (never JSON/image/PDF responses, which never
// contain a </body> tag to anchor it to). Centralized here instead of in every page's own markup.
ob_start(function ($buf) use ($email) {
    if (stripos($buf, '</body>') === false) return $buf;
    $badge = '<a href="https://hynri.ai" target="_blank" rel="noopener" style="position:fixed;right:12px;bottom:12px;z-index:9999;display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.92);border:1px solid #d7e2e2;border-radius:999px;padding:5px 12px;box-shadow:0 2px 8px rgba(0,0,0,.08);font-family:sans-serif;font-size:.74rem;color:#5a6a6c;text-decoration:none">Powered by <strong style="color:#036f72">Hynri</strong></a>';
    $out = preg_replace('/<\/body>/i', $badge . '</body>', $buf, 1);
    // "Login As" banner: visible on every page while a staff/broker account is viewing as an agent.
    if (!empty($_SESSION['impersonator'])) {
        $who = htmlspecialchars($_SESSION['name'] ?? $email);
        $banner = '<div style="position:fixed;top:0;left:0;right:0;z-index:9998;background:#b7791f;color:#fff;text-align:center;padding:8px 12px;font-family:sans-serif;font-size:.86rem;font-weight:600;box-shadow:0 2px 6px rgba(0,0,0,.15)">'
            . 'Viewing as ' . $who . ' &nbsp; <a href="return_to_staff.php" style="color:#fff;text-decoration:underline;font-weight:700">Return to my account</a></div>'
            . '<div style="height:38px"></div>';
        $out = preg_replace('/<body([^>]*)>/i', '<body$1>' . $banner, $out, 1);
    }
    return $out;
});
