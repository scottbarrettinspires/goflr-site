<?php
// Performance: the agent's results. For now it holds Closed Business (closed buyer and seller records), which moved
// here from Active Business so Active Business only shows live deals. Data: ab_closed() in ablib.php (also
// performance_api.php?action=closed for assistants).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/ablib.php";
$me = $_SESSION['email'];
$closed = ab_closed($me);
$cb = $closed['buyer']; $cs = $closed['seller'];
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
<title>Performance - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
<style>
.lpanel{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:16px;margin:0 0 14px}
.lpanel h2{margin:0 0 12px;font-size:1.15rem}
.lrow{display:flex;gap:12px;align-items:center;padding:12px;border:1px solid var(--line);border-radius:12px;margin:0 0 8px;background:var(--white);color:inherit;text-decoration:none}
a.lrow:hover{border-color:var(--teal)}.lrow .who{flex:1;min-width:0;display:block}.lrow .nm,.lrow .meta{display:block}.lrow .nm{font-weight:700}.lrow .meta{font-size:.82rem;color:#5a6a6c}
.lrow .due{font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:99px;white-space:nowrap}
.empty{color:#6a777a;padding:14px;text-align:center;margin:0}
.dot{width:12px;height:12px;border-radius:50%;flex:0 0 12px;border:1px solid rgba(0,0,0,.15)}
.due.over{background:#fde2e1;color:#b71c1c}.due.today{background:#fff1d6;color:#8a5a00}.due.later{background:#eef3f3;color:#4b5b5d}
.qa{display:flex;flex:0 0 auto}.qa a{display:inline-flex;width:30px;height:30px;border-radius:9px;align-items:center;justify-content:center;background:var(--teal-soft);color:var(--teal);text-decoration:none;font-size:.85rem;margin-left:4px}
.crow{cursor:pointer}.crow:hover{border-color:var(--teal)}.crow a.nm{color:inherit;text-decoration:none}
.lrow .meta{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.spills{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px}.spills a{font-weight:700;font-size:.82rem;padding:5px 10px;border-radius:99px;text-decoration:none;border:2px solid transparent}.spills a.on{border-color:#1f2a2c}
@media(max-width:640px){.crow{flex-wrap:wrap}.crow .who{flex:1 1 calc(100% - 30px)}.crow .due{margin-left:24px;white-space:normal;max-width:calc(100% - 140px)}.crow .qa{margin-left:auto}}
.ovsub{display:flex;justify-content:space-between;align-items:baseline;margin:14px 0 6px;font-weight:700;color:var(--teal);font-size:.95rem}.ovsub:first-of-type{margin-top:0}
.ovsub span{display:inline-flex;align-items:center;gap:8px}
</style>
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
    <a href="performance.php" aria-current="page">Performance</a>
    <?php endif; ?>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a><?php endif; ?>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>

<section class="hero">
  <h1>Performance</h1>
  <p>Your closed business and results.</p>
</section>

<main class="wrap">
  <div class="lpanel" id="closed"><h2>Closed Business</h2>
    <div class="ovsub"><span>Buyers (<?= count($cb) ?>)</span></div>
<?php if (!$cb): ?>    <p class="empty">No closed buyer transactions yet.</p>
<?php else: foreach ($cb as $r) echo ab_row($r, 'buyer'); endif; ?>
    <div class="ovsub"><span>Sellers (<?= count($cs) ?>)</span></div>
<?php if (!$cs): ?>    <p class="empty">No closed seller transactions yet.</p>
<?php else: foreach ($cs as $r) echo ab_row($r, 'seller'); endif; ?>
  </div>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
