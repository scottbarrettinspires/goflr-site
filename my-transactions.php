<?php
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/pstage.php";
$me = $_SESSION['email'];
$side = (($_GET['side'] ?? '') === 'seller') ? 'seller' : 'buyer';
$buyers = array_values(array_filter(flr_tx_list($me), function ($t) { return $t['side'] === 'buyer'; }));
$sellers = array_values(array_filter(flr_tx_list($me), function ($t) { return $t['side'] === 'seller'; }));
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
<title>Active Business - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
<style>
.stabs{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px}.stabs a{padding:9px 16px;border-radius:99px;border:1px solid var(--line);background:var(--white);color:var(--teal);text-decoration:none;font-weight:600}.stabs a.on{background:var(--teal);color:#fff;border-color:var(--teal)}
/* Sidebar + tabs, matching the Leads page's look. */
.lwrap{display:grid;grid-template-columns:220px 1fr;gap:18px;align-items:start}
.lside{display:flex;flex-direction:column;gap:8px;position:sticky;top:12px}
.sb{font:inherit;font-weight:700;text-align:left;padding:12px 14px;border-radius:12px;border:2px solid rgba(0,0,0,.08);cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:8px;text-decoration:none;background:var(--white);color:var(--teal)}
.sb.on{background:var(--teal);color:#fff;outline:3px solid #1f2a2c;outline-offset:2px}
.sb .n{font-size:.78rem;font-weight:700;background:rgba(0,0,0,.08);border-radius:99px;padding:1px 8px}
.sb.on .n{background:rgba(255,255,255,.25)}
@media(max-width:800px){.lwrap{grid-template-columns:1fr}.lside{position:static;flex-direction:row;flex-wrap:wrap}.sb{flex:1 1 45%}}
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (!flr_is_broker()): ?>
    <a href="leads.php">Leads</a>
    <a href="my-transactions.php" aria-current="page">Active Business</a>
    <?php endif; ?>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a> <a href="collab.php">Collaboration</a>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a> <a href="mls_data.php">MLS Data</a><?php endif; ?>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>

<section class="hero">
  <h1>Active Business</h1>
  <p>Buyers and sellers who became clients, sorted into the same kind of pipeline stages as your Leads.</p>
</section>

<main class="wrap">
  <div class="lwrap">
    <div class="lside">
      <a class="sb<?= $side === 'buyer' ? ' on' : '' ?>" href="my-transactions.php?side=buyer">Buyers <span class="n"><?= count($buyers) ?></span></a>
      <a class="sb<?= $side === 'seller' ? ' on' : '' ?>" href="my-transactions.php?side=seller">Sellers <span class="n"><?= count($sellers) ?></span></a>
      <a class="sb" href="forms.php" style="margin-top:10px">Forms <span class="n" aria-hidden="true">&rsaquo;</span></a>
    </div>
    <div>
<?php if ($side === 'buyer'): ?>
      <h2 style="margin:0 0 10px">Buyers</h2>
<?php if (!$buyers): ?><p class="note">No buyer records yet. <a href="buyer.php?new=1">Create one</a> once a buyer signs an agreement.</p>
<?php else: ?>
      <?= ps_group_box('buyer', $buyers, function ($t) { return 'buyer.php?tx=' . urlencode($t['folder']); }) ?>
<?php endif; ?>
<?php else: ?>
      <h2 style="margin:0 0 10px">Sellers</h2>
<?php if (!$sellers): ?><p class="note">No seller records yet. They appear here once the listing folder is created in Active Business &rsaquo; 2 SELLERS.</p>
<?php else: ?>
      <?= ps_group_box('seller', $sellers, function ($t) { return 'seller.php?tx=' . urlencode($t['folder']); }) ?>
<?php endif; ?>
<?php endif; ?>
    </div>
  </div>
  <details style="margin-top:22px">
    <summary style="cursor:pointer;font-weight:700;color:var(--teal)">Browse the raw transaction folders and files</summary>
    <div class="tips" style="margin-top:12px">
      <div><b>1</b><span>Open a folder to see its files.</span></div>
      <div><b>2</b><span>Click any document to view it in a new tab.</span></div>
      <div><b>3</b><span>Fully executed contracts are tagged and listed first.</span></div>
    </div>
    <div class="panel"><iframe class="frame" title="Active Business" src="https://script.google.com/macros/s/AKfycby0tfcs_7y9_B1cH5zxcpRJkGAvbvY9WYrqsp_RR8tgcm6nEEvQVNQ1jS5iI-Lq54PC/exec"></iframe></div>
  </details>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
<script>try{fetch("tx_sync.php",{credentials:"same-origin"}).catch(function(){});}catch(e){}</script>
</body>
</html>
