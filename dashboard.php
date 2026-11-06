<?php
// Broker Dashboard: leads, active deals and upcoming deadlines across every agent. Staff / broker accounts only.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
if (!flr_is_broker()) { header('Location: home.php'); exit; }
$pdo = flr_db();

// One row per agent we've seen (populated as each agent's data is first touched), with live counts.
$agents = $pdo->query("SELECT akey, email, meta, updated FROM agents ORDER BY email")->fetchAll();
$leadCounts = $pdo->query("SELECT akey, COUNT(*) n FROM leads GROUP BY akey")->fetchAll(PDO::FETCH_KEY_PAIR);
$txRows = $pdo->query("SELECT akey, side, stage_name, listed FROM transactions")->fetchAll();
$txByAgent = [];
foreach ($txRows as $t) { if (!(int)($t['listed'] ?? 1)) continue; $txByAgent[$t['akey']]['total'] = ($txByAgent[$t['akey']]['total'] ?? 0) + 1;
    if ($t['side'] === 'buyer') $txByAgent[$t['akey']]['buyers'] = ($txByAgent[$t['akey']]['buyers'] ?? 0) + 1;
    if ($t['side'] === 'seller') $txByAgent[$t['akey']]['sellers'] = ($txByAgent[$t['akey']]['sellers'] ?? 0) + 1;
    $sn = strtolower((string)($t['stage_name'] ?? ''));
    if (strpos($sn, 'pending') !== false || strpos($sn, 'closing') !== false) $txByAgent[$t['akey']]['pending'] = ($txByAgent[$t['akey']]['pending'] ?? 0) + 1;
}
$dlAll = dl_upcoming('', 14, true);
$dlByAgent = []; $overdueTotal = 0; $soonTotal = 0;
foreach ($dlAll as $d) { $k = flr_akey($d['email']); $dlByAgent[$k] = ($dlByAgent[$k] ?? 0) + 1; if ($d['left'] < 0) $overdueTotal++; else $soonTotal++; }

$totalLeads = array_sum($leadCounts);
$totalActive = 0; foreach ($txByAgent as $t) $totalActive += $t['total'] ?? 0;
$totalPending = 0; foreach ($txByAgent as $t) $totalPending += $t['pending'] ?? 0;
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
  <div class="grid">
    <div class="card"><h2><?= $totalLeads ?></h2><p>Total leads in the system</p></div>
    <div class="card"><h2><?= $totalActive ?></h2><p>Open buyer / seller records</p></div>
    <div class="card" style="border-left:5px solid #b7791f"><h2><?= $totalPending ?></h2><p>Records in a Pending / Closing stage</p></div>
    <div class="card" style="border-left:5px solid #c0392b"><h2><?= $overdueTotal ?></h2><p>Overdue contract deadlines</p></div>
  </div>
  <h2 style="margin:22px 0 10px">By agent</h2>
  <div class="panel sigbox">
  <table style="width:100%;border-collapse:collapse;font-size:.92rem">
  <tr style="text-align:left;border-bottom:2px solid var(--line)"><th style="padding:6px 8px 6px 0">Agent</th><th style="padding:6px 8px">Leads</th><th style="padding:6px 8px">Buyer recs</th><th style="padding:6px 8px">Seller recs</th><th style="padding:6px 8px">Pending/Closing</th><th style="padding:6px 0">Deadlines (14d)</th></tr>
<?php foreach ($agents as $a): $k = $a['akey']; $meta = json_decode($a['meta'] ?: '{}', true) ?: []; $dn = $meta['name'] ?? ($a['email'] ?: $k); $nL = (int)($leadCounts[$k] ?? 0); $t = $txByAgent[$k] ?? []; $nd = (int)($dlByAgent[$k] ?? 0);
    if ($nL === 0 && empty($t)) continue; // nothing on record for this agent yet ?>
  <tr style="border-bottom:1px solid var(--line)">
    <td style="padding:6px 8px 6px 0"><?= htmlspecialchars($dn) ?><?= $a['email'] && $a['email'] !== $dn ? ' <span style="color:#6a777a;font-size:.85rem">' . htmlspecialchars($a['email']) . '</span>' : '' ?></td>
    <td style="padding:6px 8px"><?= $nL ?></td>
    <td style="padding:6px 8px"><?= (int)($t['buyers'] ?? 0) ?></td>
    <td style="padding:6px 8px"><?= (int)($t['sellers'] ?? 0) ?></td>
    <td style="padding:6px 8px<?= ($t['pending'] ?? 0) ? ';font-weight:600;color:#b7791f' : '' ?>"><?= (int)($t['pending'] ?? 0) ?></td>
    <td style="padding:6px 0<?= $nd ? ';font-weight:600;color:#1b4b4d' : '' ?>"><?= $nd ?: '—' ?></td>
  </tr>
<?php endforeach; ?>
  </table>
  <?php if (!$agents): ?><p class="note">No agent activity recorded yet.</p><?php endif; ?>
  </div>
  <p style="margin:16px 0"><a class="btn ghost" href="deadlines.php?all=1">See every upcoming deadline</a></p>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Broker / staff access only.</footer>
</body>
</html>
