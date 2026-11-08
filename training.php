<?php
// Training & Events: listed from events.php (the one schedule), with Register + "who's going" on each event.
require __DIR__ . "/guard.php"; require_once __DIR__ . "/dlib.php"; require_once __DIR__ . "/evreglib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$upcoming = array_values(array_filter($FLR_SCHEDULE, function ($e) { return !flr_event_past($e); }));
$regs = er_for(array_map('flr_event_key', $upcoming));
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
<title>Training - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php">Active Business</a>
    <?php endif; ?>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a> <a href="mls_data.php">MLS Data</a><?php endif; ?>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>

<section class="hero">
  <h1>Training &amp; Events</h1>
  <p>Round Tables meet Wednesdays at 11:00 AM in person and Thursdays at 5:00 PM on Zoom. Past dates drop off automatically.</p>
</section>

<main class="wrap">
  <div class="events">
<?php foreach (flr_event_groups($upcoming) as $g): $e = $g[0]; $d = new DateTime($e['date']); $days = flr_group_days($g);
      $cls = $e['status'] === 'cancelled' ? ' cx' : ($e['status'] === 'holiday' ? ' hol' : ''); ?>
<article class="card ev<?= $cls ?>" data-date="<?= htmlspecialchars(end($g)['date']) ?>">
  <div class="tile"><b><?= strtoupper($d->format('M')) ?></b><span<?= strpos($days, '–') !== false ? ' class="range"' : '' ?>><?= $days ?></span></div>
  <div class="body"><span class="tag"><?= htmlspecialchars($e['status'] === 'cancelled' ? 'Cancelled' : flr_group_tag($g)) ?></span><h2><?= htmlspecialchars($e['title']) ?></h2>
<?php if ($e['status'] !== ''): ?>
    <div class="meta"><?= htmlspecialchars(implode(' · ', array_filter([$d->format('l'), $e['start'] !== '' ? $e['start'] . ' – ' . $e['end'] : '', $e['address']]))) ?></div>
<?php endif; ?>
    <p><?= htmlspecialchars($e['desc']) ?></p>
<?php if ($e['status'] === ''): ?>
    <?php if ($e['rsvp']): ?><div class="row"><a class="btn" target="_blank" rel="noopener" href="<?= htmlspecialchars($e['rsvp']) ?>">RSVP form</a></div><?php endif; ?>
    <?= er_sessions($g, $regs, true) ?>
<?php endif; ?>
  </div>
</article>
<?php endforeach; ?>
  </div>
  <p class="empty" id="empty"<?= $upcoming ? ' hidden' : '' ?>>No upcoming events are listed right now. Check back soon.</p>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
<?= er_assets() ?>
<script>
(function(){var n=new Date(),t=n.getFullYear()+"-"+String(n.getMonth()+1).padStart(2,"0")+"-"+String(n.getDate()).padStart(2,"0"),c=0;
document.querySelectorAll(".ev").forEach(function(a){if(a.getAttribute("data-date")<t){a.hidden=true}else{c++}});
if(!c)document.getElementById("empty").hidden=false;})();
</script>
</body>
</html>
