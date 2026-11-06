<?php
// Shared header/nav/footer for the small pages reached from a buyer record's Pipeline Stage ladder
// (buyer_agreement.php, shop_notes.php, signed_filed.php, contingencies.php, closing_prep.php, close_transaction.php).
function sp_head($title, $backTx = '') {
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
<title><?= htmlspecialchars($title) ?> - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=16">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="my-transactions.php">My Transactions</a>
    <a href="leads.php">Leads</a>
    <a href="deadlines.php">Deadlines</a>
    <a href="forms.php">Forms</a>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a>
<?php if (function_exists('flr_is_broker') && flr_is_broker()): ?>    <a href="dashboard.php">Dashboard</a><a href="mls_data.php">MLS Data</a>
<?php endif; ?>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1><?= htmlspecialchars($title) ?></h1><?php if ($backTx !== ''): ?><p><a href="buyer.php?tx=<?= urlencode($backTx) ?>">&larr; Back to the buyer record</a></p><?php endif; ?></section>
<main class="wrap">
<?php
}
function sp_foot() {
    ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
<?php
}
