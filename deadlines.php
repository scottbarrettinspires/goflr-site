<?php
// All upcoming contract deadlines across the agent's open buyer and seller records (or, for the broker, every agent's).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
$broker = flr_is_broker();
$all = $broker && ($_GET['all'] ?? '') === '1';
$rows = dl_upcoming($all ? '' : $me, 60, true);
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
<title>Contract Deadlines - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=16">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Virtual Broker Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php">Active Business</a>
    <a href="performance.php">Performance</a>
    <?php endif; ?>
<?php if ($broker): ?>    <a href="recruiting.php">Recruiting</a>
<?php endif; ?>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>
<section class="hero"><h1>Contract Deadlines</h1><p>Every date entered on a Contract Deadlines panel, across your open buyer and seller records, for the next 60 days (including anything overdue).</p></section>
<main class="wrap">
<?php if ($broker): ?>
  <p style="margin:0 0 14px"><a class="btn <?= $all ? '' : 'ghost' ?>" href="deadlines.php?all=1">All agents</a> <a class="btn <?= $all ? 'ghost' : '' ?>" href="deadlines.php">Just mine</a></p>
<?php endif; ?>
<?php if (!$rows): ?>
  <div class="panel sigbox"><p class="note">Nothing due in the next 60 days<?= $all ? ' for any agent' : '' ?>. Enter the Effective Date and periods on a buyer or seller record's Contract Deadlines panel to start tracking.</p></div>
<?php else: ?>
  <div class="panel sigbox">
  <table style="width:100%;border-collapse:collapse;font-size:.94rem">
  <tr style="text-align:left;border-bottom:2px solid var(--line)"><th style="padding:6px 8px 6px 0">Date</th><th style="padding:6px 8px">Deadline</th><th style="padding:6px 8px">Record</th><?php if ($all): ?><th style="padding:6px 8px">Agent</th><?php endif; ?><th style="padding:6px 0">Status</th></tr>
<?php foreach ($rows as $r): $color = $r['left'] < 0 ? '#c0392b' : ($r['left'] <= 3 ? '#b7791f' : '#1b4b4d'); ?>
  <tr style="border-bottom:1px solid var(--line)">
    <td style="padding:6px 8px 6px 0;white-space:nowrap"><?= date('M j, Y', strtotime($r['date'])) ?><?= $r['offday'] !== '' ? ' <span style="color:#b7791f;font-size:.85rem">(' . htmlspecialchars($r['offday']) . ')</span>' : '' ?></td>
    <td style="padding:6px 8px"><?= htmlspecialchars($r['label']) ?></td>
    <td style="padding:6px 8px"><a href="<?= $r['side'] === 'seller' ? 'seller.php' : 'buyer.php' ?>?tx=<?= urlencode($r['folder']) ?>"><?= htmlspecialchars($r['name'] ?: 'Open record') ?></a><?= $r['stage'] ? ' <span style="color:#6a777a;font-size:.85rem">· ' . htmlspecialchars($r['stage']) . '</span>' : '' ?></td>
<?php if ($all): ?>    <td style="padding:6px 8px"><?= htmlspecialchars($r['email']) ?></td><?php endif; ?>
    <td style="padding:6px 0;color:<?= $color ?>;font-weight:600;white-space:nowrap"><?= htmlspecialchars(dl_when($r['left'])) ?></td>
  </tr>
<?php endforeach; ?>
  </table>
  </div>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
