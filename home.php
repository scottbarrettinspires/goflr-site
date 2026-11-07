<?php
require __DIR__ . "/guard.php";
require_once __DIR__ . "/events.php";
require_once __DIR__ . "/evreglib.php"; // Register + who's going on the Next up card
require __DIR__ . "/sigtools.php";
require_once __DIR__ . "/dlib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
// Staff / broker accounts get a different home page below: their picture, then messages, then the Broker Dashboard
// content -- a personalized home focused on running the brokerage rather than an individual agent's leads and deals.
$isStaff = flr_is_broker();
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
// New Collaboration posts by others since this person last opened the Collaboration page.
$coNew = 0; try { require_once __DIR__ . "/collablib.php"; $coNew = co_new_count($me); } catch (Throwable $e) {}
$anns = flr_announcements($me);
$dlUpcoming = []; $hotN = 0; $newN = 0; $hotNames = []; $newLeads = []; $unreached = [];
$signedNotes = []; $needFile = false; $unsent = [];
if (!$isStaff) {
    require_once __DIR__ . "/leadlib.php";
    require_once __DIR__ . "/push_keys.php";
    ld_autosort($me);
    $dlUpcoming = dl_upcoming($me, 14);
    foreach (ld_view($me, '', '') as $L) {
        $isNew = true; foreach ($L['view'] as $v) { if ($v['role'] !== 'new') $isNew = false; }
        if ($isNew) { $newN++; $newLeads[] = $L; }
        foreach ($L['view'] as $v) { if ($v['sub'] === 'unid' && isset($v['over']) && $v['over'] >= 0) { $unreached[] = [$L, $v]; break; } }
        foreach ($L['view'] as $v) { if (isset($v['over']) && $v['over'] >= 0 && $v['sub'] !== 'dnc') { $hotN++; if (count($hotNames) < 4) $hotNames[] = trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? '')); break; } }
    }
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
if ($isStaff) {
    require_once __DIR__ . "/rcrlib.php";
    list($rAgent, $rPhone) = rcr_me();
    $rcrDue = array_values(array_filter(rcr_view($rAgent, $rPhone), function ($R) { return isset($R['over']) && $R['over'] >= 0; }));
    usort($rcrDue, function ($a, $b) { return $b['over'] - $a['over']; });
    $agentDates = flr_agent_dates();
}
$full = trim($_SESSION['name'] ?? '');
$first = $full !== '' ? preg_split('/\s+/', $full)[0] : '';
$pic = $_SESSION['pic'] ?? '';
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
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php" aria-current="page">Home</a>
    <?php if (!flr_is_broker()): ?>
    <a href="leads.php">Leads</a>
    <a href="my-transactions.php">Transactions</a>
    <a href="forms.php">Forms</a>
    <?php endif; ?>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a> <a href="collab.php">Collaboration</a>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a> <a href="mls_data.php">MLS Data</a> <a href="agentrecords.php">Agent Records</a><?php endif; ?>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>

<section class="hero welcome">
  <div class="who">
    <?php if ($pic): ?><img class="av" src="<?= htmlspecialchars($pic === 'photo.php' ? 'photo.php?v=' . (int)@filemtime(flr_photo_path($_SESSION['email'] ?? '')) : $pic) ?>" alt="" referrerpolicy="no-referrer" onerror="this.outerHTML='<span class=&quot;av&quot;><?= htmlspecialchars($ini) ?></span>'"><?php else: ?><span class="av"><?= htmlspecialchars($ini) ?></span><?php endif; ?>
    <div>
      <h1>Welcome<?= $first !== '' ? ', ' . htmlspecialchars($first) : '' ?></h1>
      <p>What needs your attention today.</p>
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
    <a class="omicon" href="settings.php" title="Settings" aria-label="Settings">
      <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
    </a>
    </span>
  </div>
<style>.homeicons{margin-left:auto;display:flex;gap:10px;align-items:center}
@media(max-width:600px){.who{flex-wrap:wrap}.homeicons{order:-1;width:100%;justify-content:flex-end;gap:8px;margin:-10px 0 -4px}.homeicons .omicon{flex-basis:44px;width:44px;height:44px}.homeicons .omicon svg{width:21px;height:21px}}
.omicon{position:relative;flex:0 0 52px;width:52px;height:52px;border-radius:50%;background:var(--white);color:var(--teal);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(3,111,114,.2);text-decoration:none}
.omicon:hover{background:var(--teal-soft)}.ombadge{position:absolute;top:-4px;right:-4px;min-width:22px;height:22px;padding:0 6px;box-sizing:border-box;border-radius:99px;background:#c0392b;color:#fff;font:700 .75rem/22px system-ui,sans-serif;text-align:center;border:2px solid var(--white)}</style>
</section>

<main class="wrap hasside">
<style>.hasside{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:22px;align-items:start}.homemain{min-width:0}
@media(max-width:900px){.hasside{grid-template-columns:minmax(0,1fr)}}
.homemain .sigbox{max-width:none}
.nextup.nxside{max-width:none;padding:14px 16px;margin:0 0 16px;gap:14px;box-shadow:0 4px 16px rgba(3,111,114,.07)}
.nxside .tile{flex-basis:58px;padding:6px 0}.nxside h2{margin:4px 0 2px;font-size:1.08rem}
.nxside .ersess{margin-top:6px}.nxside .ers{padding:7px 0}.nxside .ers .ereg{margin-top:6px}.nxside .nxmore{margin:0 0 2px}</style>
<div class="homemain">
<?php if ($next): ?>
  <div class="nextup nxside">
    <?php $nd = flr_group_days($nextGroup); ?>
    <div class="tile"><b><?= strtoupper($dt->format('M')) ?></b><span<?= strpos($nd, '–') !== false ? ' class="range"' : '' ?>><?= $nd ?></span></div>
    <div class="nb">
      <h2><?= htmlspecialchars($next['title']) ?></h2>
<?php if ($next['desc'] !== ''): ?>
      <details class="nxmore"><summary>More info</summary><p><?= htmlspecialchars($next['desc']) ?></p></details>
      <style>.nxmore{margin:2px 0 4px}.nxmore summary{display:inline-flex;align-items:center;gap:4px;cursor:pointer;color:var(--teal);font-weight:600;font-size:.88rem;list-style:none;-webkit-tap-highlight-color:transparent}
.nxmore summary::-webkit-details-marker{display:none}.nxmore summary::after{content:"";width:7px;height:7px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);margin:-3px 0 0 4px}
.nxmore[open] summary::after{transform:rotate(-135deg);margin-top:3px}.nxmore[open] summary{font-size:0}.nxmore[open] summary::before{content:"Less info";font-size:.88rem}
.nxmore p{margin:8px 0 2px;font-size:.92rem;line-height:1.55;color:#455355}</style>
<?php endif; ?>
<?php if ($next['rsvp']): ?>      <div class="row" style="margin:6px 0 0"><a class="btn" target="_blank" rel="noopener" href="<?= htmlspecialchars($next['rsvp']) ?>">RSVP form</a></div>
<?php endif; ?>
      <?= er_sessions($nextGroup, er_for(array_map('flr_event_key', $nextGroup)), false), er_assets() ?>
    </div>
  </div>
<?php endif; ?>
<?php if ($coNew): ?>
  <a class="panel sigbox" href="collab.php" style="display:flex;align-items:center;gap:12px;border-left:5px solid #2e8b57;margin:0 0 16px;text-decoration:none;color:inherit">
    <span style="font-size:1.6rem" aria-hidden="true">&#128172;</span>
    <span style="flex:1"><strong><?= $coNew ?> new post<?= $coNew === 1 ? '' : 's' ?> in Collaboration</strong><br><span style="font-size:.88rem;color:#5a6a6c">See what the team is sharing, then like or comment.</span></span>
    <span class="btn sm" style="padding:6px 14px;font-size:.85rem">Open</span>
  </a>
<?php endif; ?>
<?php if ($isStaff): ?>
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
<?php if ($agentDates): ?>
  <div class="panel sigbox celeb">
    <h2>&#127881; Let's Celebrate With Our Agents! &#127881;</h2>
<?php foreach ($agentDates as $D):
        $yrs = (int)($D['years'] ?? 0);
        $what = $D['type'] === 'anniversary' ? '&#127882; ' . $yrs . ' year' . ($yrs === 1 ? '' : 's') . ' with FLR' : '&#127874; Birthday';
        $who = om_person('agent:' . ($D['agent_id'] ?? ''), (string)($D['name'] ?? ''));
        $day = !empty($D['date']) ? date('l, F j', strtotime($D['date'])) : ''; ?>
    <div class="celrow">
      <span class="celav"><span><?= htmlspecialchars($who['ini']) ?></span><?php if ($who['photo']): ?><img src="<?= htmlspecialchars($who['photo']) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></span>
      <div class="celtx"><strong><?= htmlspecialchars($D['name'] ?? '') ?></strong><span class="celwhat"><?= $what ?></span><span class="celday"><?= htmlspecialchars($day) ?></span></div>
    </div>
<?php endforeach; ?>
  </div>
<style>.celeb{border-left:5px solid #7a4fc9;margin-bottom:16px;padding:16px 20px}.celeb h2{margin:0 0 6px;font-size:1.12rem;color:#5b3aa0}
.celrow{display:flex;gap:12px;align-items:center;padding:10px 0;border-top:1px solid var(--line)}
.celav{position:relative;flex:0 0 48px;width:48px;height:48px;border-radius:50%;overflow:hidden;background:#ece3fb;color:#5b3aa0;font-weight:700;display:flex;align-items:center;justify-content:center}
.celav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.celtx{display:flex;flex-direction:column;line-height:1.35}.celwhat{font-size:.92rem}.celday{font-size:.85rem;color:#5a6a6c}</style>
<?php endif; ?>
<?php require __DIR__ . "/dashboard_body.php"; ?>
<?php else: ?>
<?php if ($newLeads):
    usort($newLeads, function ($a, $b) { return strcmp(($b['created'] ?? $b['added'] ?? ''), ($a['created'] ?? $a['added'] ?? '')); });
    $today = ld_today(); ?>
  <div class="panel sigbox" style="border-left:5px solid #b71c1c;margin-bottom:16px">
    <p><span class="tag" style="background:#fde2e1;color:#b71c1c">New leads</span></p>
    <p><strong><?= count($newLeads) ?> new lead<?= count($newLeads) === 1 ? '' : 's' ?> waiting.</strong> Call within 5 minutes when you can, then sort each one into Buyers, Sellers or Sphere.</p>
<?php foreach (array_slice($newLeads, 0, 8) as $L):
        $nm = trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? '')) ?: (($L['phone'] ?? '') ?: ($L['email'] ?? 'New lead'));
        $d = $L['added'] ?? $today; $when = $d === $today ? 'Today' : ($d === ld_add($today, -1) ? 'Yesterday' : date('M j', strtotime($d)));
        $tel = preg_replace('/[^0-9+]/', '', (string)($L['phone'] ?? '')); ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 0;border-top:1px solid var(--line)">
      <div style="flex:1;min-width:180px"><strong><?= htmlspecialchars($nm) ?></strong><br><span style="font-size:.85rem;color:#5a6a6c"><?= htmlspecialchars($when . (!empty($L['source']) ? ' · ' . $L['source'] : '')) ?></span></div>
      <?php if ($tel !== ''): ?><a class="btn" href="tel:<?= htmlspecialchars($tel) ?>">Call</a> <a class="btn ghost" href="sms:<?= htmlspecialchars($tel) ?>">Text</a><?php endif; ?>
      <?php if (!empty($L['email'])): ?><a class="btn ghost" href="mailto:<?= htmlspecialchars($L['email']) ?>">Email</a><?php endif; ?>
      <a class="btn ghost" href="leads.php?open=<?= urlencode($L['id']) ?>">Open lead</a>
    </div>
<?php endforeach; ?>
<?php if (count($newLeads) > 8): ?><p style="margin:8px 0 0"><a href="leads.php">See all <?= count($newLeads) ?> new leads</a></p><?php endif; ?>
  </div>
<?php endif; ?>
<?php if ($unreached): usort($unreached, function ($a, $b) { return $b[1]['over'] - $a[1]['over']; }); ?>
  <div class="panel sigbox" style="border-left:5px solid #e53935;margin-bottom:16px">
    <p><span class="tag" style="background:#fde2e1;color:#b71c1c">Not reached yet</span></p>
    <p><strong><?= count($unreached) ?> lead<?= count($unreached) === 1 ? '' : 's' ?> still waiting to hear from you.</strong> Try again today. Once you reach someone, move them to Ready, Soon or Later and work toward a buyer agreement or listing appointment.</p>
<?php foreach (array_slice($unreached, 0, 6) as $u): $L = $u[0]; $v = $u[1];
        $nm = trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? '')) ?: (($L['phone'] ?? '') ?: ($L['email'] ?? 'Lead'));
        $tel = preg_replace('/[^0-9+]/', '', (string)($L['phone'] ?? '')); ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 0;border-top:1px solid var(--line)">
      <div style="flex:1;min-width:180px"><strong><?= htmlspecialchars($nm) ?></strong><br><span style="font-size:.85rem;color:#5a6a6c"><?= htmlspecialchars(($v['role'] === 'seller' ? 'Seller' : 'Buyer') . ' · attempt ' . $v['step'] . ' · ' . $v['ch'] . ($v['over'] > 0 ? ' · ' . $v['over'] . ' day' . ($v['over'] === 1 ? '' : 's') . ' overdue' : ' · due today')) ?></span></div>
      <?php if ($tel !== ''): ?><a class="btn" href="tel:<?= htmlspecialchars($tel) ?>">Call</a> <a class="btn ghost" href="sms:<?= htmlspecialchars($tel) ?>">Text</a><?php endif; ?>
      <?php if (!empty($L['email'])): ?><a class="btn ghost" href="mailto:<?= htmlspecialchars($L['email']) ?>">Email</a><?php endif; ?>
      <a class="btn ghost" href="leads.php?open=<?= urlencode($L['id']) ?>">Open lead</a>
    </div>
<?php endforeach; ?>
<?php if (count($unreached) > 6): ?><p style="margin:8px 0 0"><a href="leads.php">See all <?= count($unreached) ?></a></p><?php endif; ?>
  </div>
<?php endif; ?>
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
<?php foreach ($signedNotes as $n):
    $names = []; $link = '';
    if (!empty($n['filed'])) { $names[] = $n['filed']['name']; $link = $n['filed']['folder_url'] ?? ''; if ($link === '') $link = $n['filed']['url']; }
    foreach (($n['extra'] ?? []) as $x) { if (!empty($x['filed'])) { $names[] = $x['filed']['name']; if (($n['filed']['folder_url'] ?? '') === '' && $link === '') $link = $x['filed']['url']; } }
    $where = !empty($n['filed']) ? $n['filed']['folder'] : ''; ?>
  <div class="panel sigbox" style="border-left:5px solid #2e8b57;margin-bottom:16px">
    <p><strong>Signed: <?= htmlspecialchars($n['offer_address'] ?? preg_replace('/^[^-]*-\s*/', '', (string)($n['form'] ?? ''))) ?></strong></p>
    <?php if ($names): ?>
    <p>Your buyers have signed. These documents are filed in <strong><?= htmlspecialchars($where) ?></strong>:</p>
    <ul><?php foreach ($names as $nm): ?><li><?= htmlspecialchars($nm) ?></li><?php endforeach; ?></ul>
    <p><?php if ($link): ?><a class="btn" href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener">Open the record</a> <?php endif; ?><a class="btn ghost" href="signed.php">Signed Documents</a>
    <?php elseif (sg_can_file($n)): ?>
    <p style="display:flex;align-items:center;gap:10px"><i class="bspin" style="border-color:rgba(0,107,104,.3);border-top-color:#006b68"></i>Your buyers have signed. Filing the documents in your transaction record...</p>
    <p>
    <?php else: ?>
    <p>Fully signed, but not filed in a transaction record yet.</p>
    <p><a class="btn" href="signed.php">Choose the record</a>
    <?php endif; ?>
    <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="seen" value="<?= htmlspecialchars($n['id']) ?>"> <button class="btn ghost" type="submit">Dismiss</button></form></p>
  </div>
<?php endforeach; ?>
  <div class="grid">
    <a class="card" href="leads.php" style="border-left:5px solid #e53935">
      <h2>Hot Leads</h2>
<?php if ($hotN): ?>
      <p><strong><?= $hotN ?> lead<?= $hotN === 1 ? '' : 's' ?> to contact now</strong><?= $newN ? ' &middot; ' . $newN . ' NEW to sort' : '' ?></p>
      <p style="margin:0"><?= htmlspecialchars(implode(', ', $hotNames)) ?><?= $hotN > count($hotNames) ? ' and more' : '' ?></p>
<?php else: ?>
      <p>Nothing due right now.<?= $newN ? ' ' . $newN . ' NEW lead' . ($newN === 1 ? '' : 's') . ' to sort.' : ' Add leads to start your follow-up plan.' ?></p>
<?php endif; ?>
    </a>
    <a class="card" href="deadlines.php" style="border-left:5px solid #b7791f">
      <h2>Upcoming Contract Deadlines</h2>
<?php if ($dlUpcoming): $overdue = 0; foreach ($dlUpcoming as $d) if ($d['left'] < 0) $overdue++; ?>
      <p><strong><?= count($dlUpcoming) ?> deadline<?= count($dlUpcoming) === 1 ? '' : 's' ?> in the next 14 days</strong><?= $overdue ? ' · ' . $overdue . ' OVERDUE' : '' ?></p>
      <p style="margin:0"><?php foreach (array_slice($dlUpcoming, 0, 3) as $d): ?><?= htmlspecialchars($d['label']) ?> — <?= htmlspecialchars($d['name'] ?: 'untitled') ?> (<?= htmlspecialchars(dl_when($d['left'])) ?>)<br><?php endforeach; ?></p>
<?php else: ?>
      <p>Nothing due in the next 14 days. Enter dates on a buyer or seller record's Contract Deadlines panel.</p>
<?php endif; ?>
    </a>
  </div>
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
