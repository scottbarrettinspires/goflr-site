<?php
// Shared header/nav/footer for the small pages reached from a buyer record's Pipeline Stage ladder
// (buyer_agreement.php, shop_notes.php, signed_filed.php, contingencies.php, closing_prep.php, close_transaction.php).
// Each one also loads inside the ladder's own panel, via ?embed=1 appended automatically by ps_box() — in that mode the
// page skips the site nav/footer and shows a small title bar with a link to open the same page on its own.
function sp_embed() { return !empty($_GET['embed']); }
function sp_full_url() {
    $qs = $_GET; unset($qs['embed']);
    $path = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?');
    return $path . ($qs ? ('?' . http_build_query($qs)) : '');
}
function sp_head($title, $backTx = '') {
    if (sp_embed()) {
        ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= htmlspecialchars($title) ?> - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=16">
<style>body{margin:0;background:#fff}.embedwrap{padding:14px}</style>
</head>
<body>
<div class="embedwrap">
<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px">
  <h2 style="margin:0;font-size:1.1rem"><?= htmlspecialchars($title) ?></h2>
  <a href="<?= htmlspecialchars(sp_full_url()) ?>" target="_top" style="font-size:.82rem;white-space:nowrap">Open full page &#8599;</a>
</div>
        <?php
        return;
    }
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
    <?php if (!(function_exists('flr_is_broker') && flr_is_broker())): ?>
    <a href="leads.php">Leads</a>
    <a href="my-transactions.php">Transactions</a>
    <a href="forms.php">Forms</a>
    <?php endif; ?>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a>
<?php if (function_exists('flr_is_broker') && flr_is_broker()): ?>    <a href="recruiting.php">Recruiting</a> <a href="mls_data.php">MLS Data</a> <a href="agentrecords.php">Agent Records</a>
<?php endif; ?>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1><?= htmlspecialchars($title) ?></h1><?php if ($backTx !== ''): ?><p><a href="buyer.php?tx=<?= urlencode($backTx) ?>">&larr; Back to the buyer record</a></p><?php endif; ?></section>
<main class="wrap">
<?php
}
function sp_foot() {
    if (sp_embed()) { ?>
</div>
</body>
</html>
<?php
        return;
    }
    ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
<?php
}
