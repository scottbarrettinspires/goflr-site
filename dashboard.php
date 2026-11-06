<?php
// Broker Dashboard: leads, active deals and upcoming deadlines across every agent. Staff / broker accounts only.
// The content itself lives in dashboard_body.php, shared with a staff account's personalized home page (home.php).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
if (!flr_is_broker()) { header('Location: home.php'); exit; }
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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Broker Dashboard - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=16">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="leads.php">Leads</a>
    <a href="my-transactions.php">Transactions</a>
    <a href="forms.php">Forms</a>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a>
    <a href="dashboard.php" aria-current="page">Dashboard</a><?php if (flr_is_broker()): ?><a href="mls_data.php">MLS Data</a><?php endif; ?>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1>Broker Dashboard</h1><p>Leads, active deals and pending closings across every agent, built from the server's own records.</p></section>
<main class="wrap">
<?php require __DIR__ . "/dashboard_body.php"; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Broker / staff access only.</footer>
</body>
</html>
