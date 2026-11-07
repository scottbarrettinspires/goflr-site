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
    // Pages that send a loading screen early (e.g. the Contract Forms Wizard) reach here in pieces, so each change
    // below applies to whichever piece holds its anchor: the top bar and <body> in the first, </body> in the last.
    $out = $buf;
    if (stripos($buf, '</body>') !== false) {
    $badge = '<a href="https://hynri.ai" target="_blank" rel="noopener" style="position:fixed;right:12px;bottom:12px;z-index:9999;display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.92);border:1px solid #d7e2e2;border-radius:999px;padding:5px 12px;box-shadow:0 2px 8px rgba(0,0,0,.08);font-family:sans-serif;font-size:.74rem;color:#5a6a6c;text-decoration:none">Powered by <strong style="color:#036f72">Hynri</strong></a>';
    $out = preg_replace('/<\/body>/i', $badge . '</body>', $out, 1);
    }
    // "Login As" banner: visible on every page while a staff/broker account is viewing as an agent.
    if (!empty($_SESSION['impersonator']) && stripos($out, '<body') !== false) {
        $who = htmlspecialchars($_SESSION['name'] ?? $email);
        $banner = '<div style="position:fixed;top:0;left:0;right:0;z-index:9998;background:#b7791f;color:#fff;text-align:center;padding:8px 12px;font-family:sans-serif;font-size:.86rem;font-weight:600;box-shadow:0 2px 6px rgba(0,0,0,.15)">'
            . 'Viewing as ' . $who . ' &nbsp; <a href="return_to_staff.php" style="color:#fff;text-decoration:underline;font-weight:700">Return to my account</a></div>'
            . '<div style="height:38px"></div>';
        $out = preg_replace('/<body([^>]*)>/i', '<body$1>' . $banner, $out, 1);
    }
    // Notifications / Messages / Settings icons (with red counts) on every signed-in page, in the same spot as on
    // Home: the top right of the page title, just under the menu bar. Home places its own.
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'home.php') {
        if (strpos($out, '<section class="hero">') !== false) {
            $out = preg_replace_callback('#<section class="hero">#', function () use ($email) { return '<section class="hero hashi">' . flr_header_icons($email); }, $out, 1);
        } elseif (strpos($out, '</div></header>') !== false) { // a page with no title area: a slim row under the menu bar
            $out = preg_replace_callback('#</div></header>#', function () use ($email) { return '</div></header><div class="hirow">' . flr_header_icons($email) . '</div>'; }, $out, 1);
        }
    }
    return $out;
});

// The three top-bar icons. Unread messages are a quick lookup; the notifications count is the quick local check,
// remembered for 3 minutes per person (Home and notif_api.php refresh it with the full count).
function flr_header_icons($email) {
    $msgs = 0; $nt = 0;
    try {
        require_once __DIR__ . '/dlib.php'; require_once __DIR__ . '/msglib.php';
        $staff = flr_is_broker();
        $msgs = om_unread_count(om_my_keys($email, $staff));
        $c = $_SESSION['ntc'] ?? null;
        if (is_array($c) && time() - (int)($c['t'] ?? 0) < 180) { $nt = (int)$c['n']; }
        else { require_once __DIR__ . '/notiflib.php'; $nt = nt_count(nt_sections($email, $staff, false)); $_SESSION['ntc'] = ['t' => time(), 'n' => $nt]; }
    } catch (Throwable $e) {}
    $badge = function ($n) { return $n ? '<span class="hib">' . ($n > 99 ? '99+' : $n) . '</span>' : ''; };
    $i = function ($href, $title, $n, $svg) use ($badge) { return '<a class="hic" href="' . $href . '" title="' . $title . '" aria-label="' . $title . ($n ? ', ' . $n : '') . '">' . $svg . $badge($n) . '</a>'; };
    $sv = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
    return '<style>.hashi{position:relative;padding-right:200px}.hashi .hicons{position:absolute;top:40px;right:20px}.hirow{max-width:1080px;margin:0 auto;padding:16px 20px 0;display:flex;justify-content:flex-end}'
        . '.hicons{display:flex;gap:10px;align-items:center}.hic{position:relative;flex:0 0 52px;width:52px;height:52px;border-radius:50%;background:#fff;color:#036f72;display:flex;align-items:center;justify-content:center;text-decoration:none;box-shadow:0 4px 14px rgba(3,111,114,.2)}'
        . '.hic:hover{background:#e6f2f2}.hic svg{width:24px;height:24px}.hib{position:absolute;top:-4px;right:-4px;min-width:22px;height:22px;padding:0 6px;box-sizing:border-box;border-radius:99px;background:#c0392b;color:#fff;font:700 .75rem/22px system-ui,sans-serif;text-align:center;border:2px solid #fff}'
        . '@media(max-width:600px){.hashi{padding-right:20px;padding-top:18px}.hashi .hicons{position:static;justify-content:flex-end;gap:8px;margin:0 0 6px}.hic{flex-basis:44px;width:44px;height:44px}.hic svg{width:21px;height:21px}}</style>'
        . '<span class="hicons">'
        . $i('notifications.php', 'Notifications', $nt, $sv . '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>')
        . $i('messages.php', 'Messages', $msgs, $sv . '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>')
        . $i('settings.php', 'Settings', 0, $sv . '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>')
        . '</span>';
}
