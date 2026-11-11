<?php
require __DIR__ . "/guard.php";
require_once __DIR__ . "/events.php";
require_once __DIR__ . "/evreglib.php"; // Register + who's going on the Next up card
require_once __DIR__ . "/sigtools.php";
require_once __DIR__ . "/dlib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
// Staff / broker accounts get a different home page below: their picture, then messages, then the Broker Dashboard
// content -- a personalized home focused on running the brokerage rather than an individual agent's leads and deals.
$isStaff = flr_is_broker();
// Staff choose between Dashboard (their own staff Home) and Agent View (Home exactly as an agent sees it); remembered.
if ($isStaff && isset($_GET['view'])) { $_SESSION['homeview'] = $_GET['view'] === 'agent' ? 'agent' : 'dash'; unset($_SESSION['ntc']); }
// One time (Scott asked, Nov 8 2026): load the preview sample data into scott@myflroffice.com and open Agent View.
// After that it's managed from Settings > Sample data, and cleared from there before launch.
if ($isStaff && $me === 'scott@myflroffice.com') { try { $kq = flr_db()->query("SELECT v FROM kv WHERE k = 'sample_autoload_v1'");
    if (!$kq->fetchColumn()) { flr_db()->prepare("INSERT OR REPLACE INTO kv (k, v) VALUES ('sample_autoload_v1', ?)")->execute([date('c')]); require_once __DIR__ . '/samplelib.php'; sd_load($me); $_SESSION['homeview'] = 'agent'; unset($_SESSION['ntc']); }
} catch (Throwable $e) {} }
$agentView = $isStaff && ($_SESSION['homeview'] ?? 'dash') === 'agent';
$showStaff = $isStaff && !$agentView;
// Agent dismissed a "signed" notice.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? '')) && isset($_POST['seen'])) {
    $rec = sg_load((string)$_POST['seen']);
    if ($rec && strtolower($rec['agent'] ?? '') === $me) { $rec['seen'] = true; sg_save($rec['id'], $rec); }
    header('Location: home.php'); exit;
}
// Agent dismissed an announcement.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? '')) && isset($_POST['annseen'])) {
    $aid = substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_POST['annseen']), 0, 40);
    if ($aid !== '') { flr_ann_dismiss($me, $aid); sg_script_post(['op' => 'dismissann', 'email' => $me, 'id' => $aid]); }
    header('Location: home.php'); exit;
}
// Messages: send (agent -> staff; staff -> one / group / all agents or staff) and dismiss. See msglib.php.
require_once __DIR__ . "/msglib.php";
$msgErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? '')) && (isset($_POST['msgbody']) || isset($_POST['msgdismiss']))) {
    $msgErr = om_handle_post($me, trim($_SESSION['name'] ?? ''), $isStaff);
}
// Unread count for the envelope badge -- taken before the staff Messages panel below marks anything as seen.
$omUnread = 0; try { $omUnread = om_unread_count(om_my_keys($me, $isStaff)); } catch (Throwable $e) {}
// Bell count: the quick local checks now; the page then asks notif_api.php for the full count (record details in Drive).
$ntCount = 0; try { require_once __DIR__ . "/notiflib.php"; $ntCount = nt_count(nt_sections($me, $isStaff, false)); } catch (Throwable $e) {}
$anns = flr_announcements($me);
$signedNotes = []; $needFile = false; $unsent = [];
if (!$isStaff) {
    require_once __DIR__ . "/leadlib.php";
    require_once __DIR__ . "/push_keys.php";
    ld_autosort($me);
    // New leads, hot leads and contract deadlines are on the Notifications page (bell) -- see notiflib.php.
    // Fully signed requests the agent has not dismissed. Check anything still open, and file finished documents into the transaction record.
    $myrecs = array_values(array_filter(sg_for_agent($me), function ($x) { return empty($x['seen']); }));
    sg_refresh_many($myrecs, 6);
    foreach ($myrecs as $r) {
        if (empty($r['completed'])) continue;
        $pend = empty($r['filed']); foreach (($r['extra'] ?? []) as $x) { if (empty($x['filed'])) $pend = true; }
        if ($pend && sg_can_file($r)) { $needFile = true; } // filed in the background by file_pending.php
        $signedNotes[] = $r;
    }
    $unsent = sg_draft_unsent($me);
}
$rcrDue = []; $agentDates = [];
if ($showStaff) {
    require_once __DIR__ . "/rcrlib.php";
    list($rAgent, $rPhone) = rcr_me();
    $rcrDue = array_values(array_filter(rcr_view($rAgent, $rPhone), function ($R) { return isset($R['over']) && $R['over'] >= 0; }));
    usort($rcrDue, function ($a, $b) { return $b['over'] - $a['over']; });
}
// "Let's Celebrate With Our Agents!" (birthdays & work anniversaries, next 14 days) -- staff and agents alike.
$agentDates = flr_agent_dates();
$full = trim($_SESSION['name'] ?? '');
$first = $full !== '' ? preg_split('/\s+/', $full)[0] : '';
$pic = $_SESSION['pic'] ?? ''; $pfPic = flr_headshot_url($_SESSION['email'] ?? '', ''); // a headshot uploaded on the Profile page wins
if ($pfPic !== '') $pic = $pfPic;
$ini = '';
foreach (array_slice(preg_split('/\s+/', $full), 0, 2) as $w) { if ($w !== '') $ini .= mb_strtoupper(mb_substr($w, 0, 1)); }
if ($ini === '') $ini = 'FLR';
// Next upcoming event (Eastern time). Today's event stays until its end time has passed.
$tz = new DateTimeZone('America/New_York');
$now = new DateTime('now', $tz);
$next = null;
foreach ($FLR_EVENTS as $ev) {
    $end = DateTime::createFromFormat('Y-m-d g:i A', $ev['date'] . ' ' . ($ev['end'] ?: '11:59 PM'), $tz);
    if ($end && $end >= $now) { $next = $ev; $dt = new DateTime($ev['date'], $tz); break; }
}
// If the next event is one of a pair (same training in person and on Zoom), show both remaining sessions together.
$nextGroup = [];
if ($next) { foreach (flr_event_groups(array_values(array_filter($FLR_EVENTS, function ($e) { return !flr_event_past($e); }))) as $g) { if (in_array($next, $g, true)) { $nextGroup = $g; break; } } }
if (!$nextGroup && $next) $nextGroup = [$next];
?>
<!doctype html>
<html lang="en">
<head>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#036f72">
<link rel="icon" href="/icon-192.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="FLR Office">
<script>if("serviceWorker" in navigator){navigator.serviceWorker.register("/sw.js").catch(function(){});}</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Home - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Virtual Broker Office</span></a>
  <nav>
    <a href="home.php" aria-current="page">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php">Active Business</a>
    <a href="performance.php">Performance</a>
    <a href="learn.php">Learn</a>
    <?php endif; ?>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a><?php endif; ?>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>

<section class="hero welcome">
  <div class="who">
    <?php if ($pic): ?><img class="av" src="<?= htmlspecialchars($pic === 'photo.php' ? 'photo.php?v=' . (int)@filemtime(flr_photo_path($_SESSION['email'] ?? '')) : $pic) ?>" alt="" referrerpolicy="no-referrer" onerror="this.outerHTML='<span class=&quot;av&quot;><?= htmlspecialchars($ini) ?></span>'"><?php else: ?><span class="av"><?= htmlspecialchars($ini) ?></span><?php endif; ?>
    <div>
      <h1>Welcome<?= $first !== '' ? ', ' . htmlspecialchars($first) : '' ?></h1>
      <p><?= $agentView ? 'This is Home as your agents see it.' : ($isStaff ? 'What needs your attention today.' : 'What&#39;s happening at the brokerage.') ?></p>
    </div>
    <span class="homeicons">
    <a class="omicon" href="notifications.php" title="Notifications" aria-label="Notifications<?= $ntCount ? ', ' . $ntCount . ' need attention' : '' ?>" id="ntbell">
      <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>
      <span class="ombadge" id="ntbadge"<?= $ntCount ? '' : ' hidden' ?>><?= $ntCount > 99 ? '99+' : $ntCount ?></span>
    </a>
    <a class="omicon" href="messages.php" title="Messages" aria-label="Messages<?= $omUnread ? ', ' . $omUnread . ' new' : '' ?>">
      <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
      <?php if ($omUnread): ?><span class="ombadge"><?= $omUnread > 99 ? '99+' : $omUnread ?></span><?php endif; ?>
    </a>
    <a class="omicon" href="directory.php" title="Directory" aria-label="Directory">
      <svg viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18.5 20a6.5 6.5 0 0 0-3-5.5"/></svg>
    </a>
    <a class="omicon" href="settings.php" title="Settings" aria-label="Settings">
      <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
    </a>
    </span>
  </div>
<?php if ($isStaff): ?>
  <nav class="hviews" aria-label="Home view">
    <a href="home.php?view=dash" class="<?= $agentView ? '' : 'on' ?>"<?= $agentView ? '' : ' aria-current="page"' ?>><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>Dashboard</a>
    <a href="home.php?view=agent" class="<?= $agentView ? 'on' : '' ?>"<?= $agentView ? ' aria-current="page"' : '' ?>><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>Agent View</a>
  </nav>
<style>.hviews{display:inline-flex;gap:4px;padding:4px;background:var(--white);border:1px solid var(--line);border-radius:12px;justify-self:start;box-shadow:0 2px 8px rgba(3,111,114,.06)}
.hviews a{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:9px;font-weight:700;font-size:.92rem;color:var(--teal);text-decoration:none}
.hviews a:hover{background:var(--teal-soft)}.hviews a.on{background:var(--teal);color:#fff}</style>
<?php endif; ?>
<style>.homeicons{margin-left:auto;display:flex;gap:10px;align-items:center}
@media(max-width:600px){.who{flex-wrap:wrap}.homeicons{order:-1;width:100%;justify-content:flex-end;gap:8px;margin:-10px 0 -4px}.homeicons .omicon{flex-basis:44px;width:44px;height:44px}.homeicons .omicon svg{width:21px;height:21px}}
.omicon{position:relative;flex:0 0 52px;width:52px;height:52px;border-radius:50%;background:var(--white);color:var(--teal);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(3,111,114,.2);text-decoration:none}
.omicon:hover{background:var(--teal-soft)}.ombadge{position:absolute;top:-4px;right:-4px;min-width:22px;height:22px;padding:0 6px;box-sizing:border-box;border-radius:99px;background:#c0392b;color:#fff;font:700 .75rem/22px system-ui,sans-serif;text-align:center;border:2px solid var(--white)}</style>
</section>

<main class="wrap hasside">
<style>.hasside{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:22px;align-items:start}.homemain{min-width:0}
@media(max-width:900px){.hasside{grid-template-columns:minmax(0,1fr)}}
.homemain .sigbox{max-width:none}
.nextup.nxside{max-width:none;padding:14px 16px;margin:0 0 16px;gap:14px;background:linear-gradient(135deg,#c6e9e7 0%,#ddf2f0 50%,#fde6cf 100%);border:1px solid #9fd3cf;box-shadow:0 6px 20px rgba(3,111,114,.12)}
.nxside .ers{border-top-color:rgba(3,111,114,.18)!important}.nxside .ersess{border-top-color:rgba(3,111,114,.18)}
.nxleft{flex:0 0 58px;display:flex;flex-direction:column;align-items:center;gap:6px}.nxedu{width:58px;height:47px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;font-size:2.6rem;animation:nxwig 1.1s ease-in-out .4s 2;line-height:1;font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif}
.nxside .tile{flex-basis:auto;width:58px;padding:6px 0}
@keyframes nxwig{0%,100%{transform:rotate(0) scale(1)}20%{transform:rotate(-12deg) scale(1.12)}40%{transform:rotate(10deg) scale(1.12)}60%{transform:rotate(-6deg) scale(1.06)}80%{transform:rotate(4deg)}}
@media(prefers-reduced-motion:reduce){.nxedu{animation:none}}.nxside h2{margin:4px 0 2px;font-size:1.08rem}
.nxtitle{display:flex;flex-wrap:wrap;align-items:baseline;column-gap:12px}.nxtitle h2{margin:2px 0 0!important}.nxtitle .nxmore{margin:0}.nxtitle .nxmore[open]{flex-basis:100%}
.nxside .ersess{margin-top:6px}.nxside .ers{display:flex;flex-wrap:wrap;align-items:center;gap:4px 12px;padding:6px 0}.nxside .erwhen{flex:1 1 260px}
.nxside .ers .ereg{margin-top:0;flex:0 1 auto;gap:8px 10px}.nxside .ertxt.muted{display:none}
.nxside .erbtn:not(.on){background:#e07b39;color:#fff}.nxside .erbtn:not(.on):hover{background:#c96a2c}
.nxside .erzoom{background:#fff;color:var(--teal);border-color:var(--teal)}.nxside .erzoom:hover{background:var(--teal-soft)}
.nxside .erwho{flex-wrap:nowrap}.nxside .erwho .ertxt{flex:0 1 auto;white-space:nowrap}
.nxcal{display:inline-flex;align-items:center;gap:6px;margin-left:auto;color:var(--teal);font-weight:600;font-size:.88rem;text-decoration:none;white-space:nowrap}.nxcal:hover{text-decoration:underline}.nxtitle .nxcal{align-self:center;order:1}.nxtitle .nxmore{order:2;flex-basis:100%;margin-top:2px}</style>
<div class="homemain">
<?php if (!$showStaff): // staff Dashboard leaves out the training, celebration and open house cards (Agent View shows them) ?>
<?php if ($next): ?>
  <div class="nextup nxside">
    <?php $nd = flr_group_days($nextGroup); ?>
    <div class="nxleft">
      <?php $nxPick = ['🤩', '😃', '🤓', '😎', '😄', '🙌', '🔔', '📣', '👉']; $nxEmoji = $nxPick[random_int(0, count($nxPick) - 1)]; // a different one each visit, to catch the eye ?>
      <span class="nxedu" title="Training" role="img" aria-label="Training"><?= $nxEmoji ?></span>
      <div class="tile"><b><?= strtoupper($dt->format('M')) ?></b><span<?= strpos($nd, '–') !== false ? ' class="range"' : '' ?>><?= $nd ?></span></div>
    </div>
    <div class="nb">
      <div class="nxtitle"><h2><?= htmlspecialchars($next['title']) ?></h2>
<?php if ($next['desc'] !== ''): ?>
      <details class="nxmore"><summary>More info</summary><p><?= htmlspecialchars($next['desc']) ?></p></details>
      <style>.nxmore{margin:2px 0 4px}.nxmore summary{display:inline-flex;align-items:center;gap:4px;cursor:pointer;color:var(--teal);font-weight:600;font-size:.88rem;list-style:none;-webkit-tap-highlight-color:transparent}
.nxmore summary::-webkit-details-marker{display:none}.nxmore summary::after{content:"";width:7px;height:7px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);margin:-3px 0 0 4px}
.nxmore[open] summary::after{transform:rotate(-135deg);margin-top:3px}.nxmore[open] summary{font-size:0}.nxmore[open] summary::before{content:"Less info";font-size:.88rem}
.nxmore p{margin:8px 0 2px;font-size:.92rem;line-height:1.55;color:#455355}</style>
<?php endif; ?>
      <a class="nxcal" href="training.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="17" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/><path d="M7.5 13.5h2M11 13.5h2M14.5 13.5h2M7.5 17h2M11 17h2"/></svg>Events Schedule</a>
      </div>
<?php if ($next['rsvp']): ?>      <div class="row" style="margin:6px 0 0"><a class="btn" target="_blank" rel="noopener" href="<?= htmlspecialchars($next['rsvp']) ?>">RSVP form</a></div>
<?php endif; ?>
      <?= er_sessions($nextGroup, er_for(array_map('flr_event_key', $nextGroup)), false), er_assets() ?>
    </div>
  </div>
<?php else: ?>
  <p style="margin:0 0 14px"><a class="nxcal" href="training.php"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="17" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/><path d="M7.5 13.5h2M11 13.5h2M14.5 13.5h2M7.5 17h2M11 17h2"/></svg>Events Schedule</a></p>
<?php endif; ?>
<?php endif; ?>
<?php if ($showStaff): ?>
<?php foreach ($anns as $a): ?>
  <div class="panel sigbox" style="border-left:5px solid <?= !empty($a['personal']) ? '#c2570c' : '#006b68' ?>;margin-bottom:16px">
    <p><span class="tag"><?= !empty($a['personal']) ? 'Message for you' : 'Announcement' ?></span></p>
    <?php if (!empty($a['title'])): ?><p><strong><?= htmlspecialchars($a['title']) ?></strong></p><?php endif; ?>
    <p style="white-space:pre-line"><?= htmlspecialchars($a['message'] ?? '') ?></p>
    <p class="note" style="background:none;border:0;padding:0;margin:0 0 10px"><?= htmlspecialchars(trim(($a['by'] ?? '') . (!empty($a['date']) ? ' · ' . date('M j, Y', strtotime($a['date'])) : ''), ' ·')) ?></p>
    <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="annseen" value="<?= htmlspecialchars($a['id']) ?>"><button class="btn ghost" type="submit">Dismiss</button></form>
  </div>
<?php endforeach; ?>
<?php if ($rcrDue): ?>
  <div class="panel sigbox" style="border-left:5px solid #b7791f;margin-bottom:16px">
    <p><span class="tag" style="background:#fdebd3;color:#8a5a0e">Recruiting</span></p>
    <p><strong><?= count($rcrDue) ?> recruiting touch<?= count($rcrDue) === 1 ? '' : 'es' ?> due or overdue.</strong></p>
<?php foreach (array_slice($rcrDue, 0, 6) as $R):
        $rnm = trim(($R['first'] ?? '') . ' ' . ($R['last'] ?? '')) ?: (($R['phone'] ?? '') ?: ($R['email'] ?? 'Recruit'));
        $rover = (int)($R['over'] ?? 0); ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 0;border-top:1px solid var(--line)">
      <div style="flex:1;min-width:180px"><strong><?= htmlspecialchars($rnm) ?></strong><br><span style="font-size:.85rem;color:#5a6a6c"><?= htmlspecialchars(($R['ch'] ?? '') . ($rover > 0 ? ' · ' . $rover . ' day' . ($rover === 1 ? '' : 's') . ' overdue' : ' · due today')) ?></span></div>
      <a class="btn ghost" href="recruiting.php?id=<?= urlencode($R['id']) ?>">Open</a>
    </div>
<?php endforeach; ?>
<?php if (count($rcrDue) > 6): ?><p style="margin:8px 0 0"><a href="recruiting.php">See all <?= count($rcrDue) ?></a></p><?php endif; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . "/dashboard_body.php"; ?>
<?php else: ?>
<?php require __DIR__ . "/celebrate_panel.php"; ?>
<?php require __DIR__ . "/oh_panel.php"; ?>
<?php foreach ($anns as $a): ?>
  <div class="panel sigbox" style="border-left:5px solid <?= !empty($a['personal']) ? '#c2570c' : '#006b68' ?>;margin-bottom:16px">
    <p><span class="tag"><?= !empty($a['personal']) ? 'Message for you' : 'Announcement' ?></span></p>
    <?php if (!empty($a['title'])): ?><p><strong><?= htmlspecialchars($a['title']) ?></strong></p><?php endif; ?>
    <p style="white-space:pre-line"><?= htmlspecialchars($a['message'] ?? '') ?></p>
    <p class="note" style="background:none;border:0;padding:0;margin:0 0 10px"><?= htmlspecialchars(trim(($a['by'] ?? '') . (!empty($a['date']) ? ' · ' . date('M j, Y', strtotime($a['date'])) : ''), ' ·')) ?></p>
    <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="annseen" value="<?= htmlspecialchars($a['id']) ?>"><button class="btn ghost" type="submit">Dismiss</button></form>
  </div>
<?php endforeach; ?>
<?php foreach ($unsent as $u): ?>
  <div class="panel sigbox" style="border-left:5px solid #c9a24b;margin-bottom:16px">
    <p><strong>Documents ready, not sent yet: <?= htmlspecialchars($u['addr']) ?></strong></p>
    <p>The completed contract documents are saved in <strong>1 BUYERS / 2 Contract Offers / <?= htmlspecialchars($u['addr']) ?> / Drafts - Not Signed</strong>.</p>
    <p><a class="btn" href="sign_packet.php?tx=<?= urlencode($u['tx']) ?>&amp;dr=<?= urlencode($u['addr']) ?>">Email To Buyers For Signing</a></p>
  </div>
<?php endforeach; ?>
<?php // Newly signed documents: a notice on the Notifications page (bell) links to the record's Signed Documents. ?>
<?php if ($needFile): ?>
<script>
(async function(){try{var r=await fetch("file_pending.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:<?= json_encode($_SESSION['csrf']) ?>})});var j=await r.json();if(j.ok&&j.filed>0&&!(j.errors||[]).length)location.reload();}catch(e){}})();
</script>
<?php endif; ?>
<?php endif; ?>
</div>
<?php require __DIR__ . "/collab_side.php"; // Collaboration: Recent posts + Most active, down the right side (agents and staff) ?>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only. Having trouble signing in? Contact the office.</footer>
<script>
// Bell: refresh the count with the slower transaction-record checks once the page is up.
(function(){var b=document.getElementById("ntbadge");if(!b)return;fetch("notif_api.php",{credentials:"same-origin"}).then(function(r){return r.json();}).then(function(j){if(!j.ok)return;var n=+j.count||0;
  b.textContent=n>99?"99+":n;b.hidden=!n;document.getElementById("ntbell").setAttribute("aria-label","Notifications"+(n?", "+n+" need attention":""));}).catch(function(){});})();
</script>
<script>
// Phone alerts are turned on in Settings; here we just quietly re-confirm this phone's subscription so it stays active.
(function(){if(!("serviceWorker" in navigator)||!("PushManager" in window)||!("Notification" in window)||Notification.permission!=="granted")return;
navigator.serviceWorker.ready.then(function(reg){return reg.pushManager.getSubscription();}).then(function(sub){if(!sub)return;
  fetch("push_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:<?= json_encode($_SESSION['csrf']) ?>,endpoint:sub.endpoint})}).catch(function(){});}).catch(function(){});})();
</script>
<script>try{fetch("tx_sync.php",{credentials:"same-origin"}).catch(function(){});}catch(e){}</script>
</body>
</html>
