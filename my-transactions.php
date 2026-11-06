<?php require __DIR__ . "/guard.php"; ?>
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
<title>My Transactions - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
<style>.stabs{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px}.stabs a{padding:9px 16px;border-radius:99px;border:1px solid var(--line);background:var(--white);color:var(--teal);text-decoration:none;font-weight:600}.stabs a.on{background:var(--teal);color:#fff;border-color:var(--teal)}</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="my-transactions.php" aria-current="page">My Transactions</a>
    <a href="leads.php">Leads</a>
    <a href="forms.php">Forms</a>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>

<section class="hero">
  <h1>My Transactions</h1>
  <p>You only see the files shared with your Google account.</p>
</section>

<main class="wrap">
  <div class="stabs"><a href="my-transactions.php" class="on">Transaction Folders</a><a href="signed.php">Signed Documents</a><a href="buyer.php">Buyer Records</a><a href="seller.php">Seller Records</a></div>
  <div class="tips">
    <div><b>1</b><span>Open a folder to see its files.</span></div>
    <div><b>2</b><span>Click any document to view it in a new tab.</span></div>
    <div><b>3</b><span>Fully executed contracts are tagged and listed first.</span></div>
  </div>
  <div class="panel"><iframe class="frame" title="My Transactions" src="https://script.google.com/macros/s/AKfycby0tfcs_7y9_B1cH5zxcpRJkGAvbvY9WYrqsp_RR8tgcm6nEEvQVNQ1jS5iI-Lq54PC/exec"></iframe></div>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
<script>try{fetch("tx_sync.php",{credentials:"same-origin"}).catch(function(){});}catch(e){}</script>
</body>
</html>
