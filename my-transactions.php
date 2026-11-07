<?php
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/pstage.php";
$me = $_SESSION['email'];
$side = (($_GET['side'] ?? '') === 'seller') ? 'seller' : 'buyer';
$buyers = array_values(array_filter(flr_tx_list($me), function ($t) { return $t['side'] === 'buyer'; }));
$sellers = array_values(array_filter(flr_tx_list($me), function ($t) { return $t['side'] === 'seller'; }));
$view = in_array($_GET['side'] ?? '', ['buyer', 'seller'], true) ? $_GET['side'] : 'overview';
// Overview numbers (all from the server: transaction index, pipeline stages, contract deadlines, signature records).
$pk = function ($t) { return ps_get($t['folder'], $t['side'], (int)$t['stage']); };
$cnt = ['buyers' => 0, 'sellers' => 0, 'listings' => 0, 'pre' => 0, 'under' => 0, 'shopping' => 0, 'closed' => 0];
foreach (array_merge($buyers, $sellers) as $t) {
    $k = $pk($t);
    if ($k === 'closed') { $cnt['closed']++; continue; }
    $cnt[$t['side'] === 'buyer' ? 'buyers' : 'sellers']++;
    if (in_array($k, ['contract', 'contingencies', 'closingready'], true)) $cnt['under']++;
    if ($t['side'] === 'seller' && in_array($k, ['active', 'managing', 'offerrecv'], true)) $cnt['listings']++;
    if ($t['side'] === 'seller' && $k === 'prelisting') $cnt['pre']++;
    if ($t['side'] === 'buyer' && in_array($k, ['agreement', 'shopping', 'offer'], true)) $cnt['shopping']++;
}
$dlSoon = dl_upcoming($me, 7, true);
$dlNow = count(array_filter($dlSoon, function ($d) { return $d['left'] <= 3; }));
$signedNew = []; $waiting = 0;
try { require_once __DIR__ . '/signed_panel.php'; $txAll = flr_tx_list($me);
    foreach (sg_for_agent($me) as $r) {
        if (empty($r['completed'])) { if (strtotime($r['created'] ?? '') > time() - 45 * 86400) $waiting++; continue; }
        if (empty($r['seen'])) { $rec = sg_record_for($r, $txAll); $signedNew[] = ['r' => $r, 'rec' => $rec]; }
    }
} catch (Throwable $e) {}
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
/* Same look as Leads & Contacts: tabs across the top, stat cards, then lists. */
.ltabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}
.ltabs a{font-weight:700;padding:8px 13px;font-size:.88rem;border-radius:10px;border:1px solid var(--line);background:var(--white);color:var(--teal);text-decoration:none;display:flex;gap:8px;align-items:center}
.ltabs a.on{background:var(--teal);color:#fff;border-color:var(--teal)}
.ltabs a .n{font-size:.72rem;font-weight:700;background:rgba(0,0,0,.08);border-radius:99px;padding:1px 7px}.ltabs a.on .n{background:rgba(255,255,255,.25)}
.ltabs .srcbtn{margin-left:auto;font-weight:600;color:#4b5b5d}
.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin:0 0 18px}
.stat{display:block;background:var(--white);border:1px solid var(--line);border-radius:12px;padding:12px 14px;color:inherit;text-decoration:none}.stat b{display:block;font-size:1.6rem;line-height:1.1}.stat span{font-size:.82rem;color:#5a6a6c}
a.stat:hover{border-color:var(--teal)}
.stat.hot{border-left:5px solid #e53935}.stat.teal{border-left:5px solid #036f72}.stat.green{border-left:5px solid #2e7d32}
.stat.urgent{background:#d32f2f;border-color:#d32f2f;color:#fff;box-shadow:0 4px 14px rgba(211,47,47,.25)}.stat.urgent span{color:#fff;font-weight:600}.stat.urgent b{font-size:2rem}a.stat.urgent:hover{background:#b71c1c}
.lpanel{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:16px;margin:0 0 14px}
.lpanel h2{margin:0 0 12px;font-size:1.15rem}
.lrow{display:flex;gap:12px;align-items:center;padding:12px;border:1px solid var(--line);border-radius:12px;margin:0 0 8px;background:var(--white);color:inherit;text-decoration:none}
a.lrow:hover{border-color:var(--teal)}.lrow .who{flex:1;min-width:0;display:block}.lrow .nm,.lrow .meta{display:block}.lrow .nm{font-weight:700}.lrow .meta{font-size:.82rem;color:#5a6a6c}
.lrow .due{font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:99px;white-space:nowrap}
.empty{color:#6a777a;padding:14px;text-align:center;margin:0}
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (!flr_is_broker()): ?>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php" aria-current="page">Active Business</a>
    <?php endif; ?>
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
  <div class="ltabs">
    <a href="my-transactions.php" class="<?= $view === 'overview' ? 'on' : '' ?>" style="<?= $view === 'overview' ? '' : 'color:#5a6a6c;font-weight:600' ?>">Overview</a>
    <a href="my-transactions.php?side=buyer" class="<?= $view === 'buyer' ? 'on' : '' ?>">Buyers <span class="n"><?= count($buyers) ?></span></a>
    <a href="my-transactions.php?side=seller" class="<?= $view === 'seller' ? 'on' : '' ?>">Sellers <span class="n"><?= count($sellers) ?></span></a>
    <a href="forms.php" class="srcbtn">&#128196; Forms</a>
  </div>
<?php if ($view === 'overview'): ?>
  <div class="stats">
    <a class="stat<?= $dlNow ? ' urgent' : ' hot' ?>" href="#deadlines"><b><?= $dlNow ?></b><span>Deadlines due in 3 days</span></a>
    <a class="stat<?= $signedNew ? ' urgent' : '' ?>" href="#signed"><b><?= count($signedNew) ?></b><span>Newly signed documents</span></a>
    <div class="stat hot"><b><?= $cnt['under'] ?></b><span>Under contract</span></div>
    <a class="stat teal" href="my-transactions.php?side=buyer"><b><?= $cnt['buyers'] ?></b><span>Active buyers</span></a>
    <a class="stat teal" href="my-transactions.php?side=seller"><b><?= $cnt['sellers'] ?></b><span>Active sellers</span></a>
    <div class="stat"><b><?= $cnt['listings'] ?></b><span>Active listings</span></div>
    <div class="stat"><b><?= $cnt['pre'] ?></b><span>Prelisting prep</span></div>
    <div class="stat"><b><?= $cnt['shopping'] ?></b><span>Buyers shopping (not under contract)</span></div>
    <div class="stat"><b><?= $waiting ?></b><span>Waiting for signatures</span></div>
    <div class="stat green"><b><?= $cnt['closed'] ?></b><span>Closed</span></div>
  </div>
  <div class="lpanel" id="deadlines"><h2>Contract deadlines: next 7 days</h2>
<?php if (!$dlSoon): ?>    <p class="empty">Nothing due in the next 7 days.</p>
<?php else: foreach ($dlSoon as $d): $red = $d['left'] <= 0; ?>
    <a class="lrow" href="<?= ($d['side'] === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . urlencode($d['folder']) ?>"><div class="who"><div class="nm"><?= htmlspecialchars($d['label']) ?> &mdash; <?= htmlspecialchars($d['name'] ?: 'untitled record') ?></div><div class="meta"><?= htmlspecialchars(date('D, M j', strtotime($d['date'])) . ($d['offday'] !== '' ? ' (' . $d['offday'] . ' — next business day ' . date('D, M j', strtotime($d['nextbiz'])) . ')' : '')) ?></div></div><span class="due" style="background:<?= $red ? '#fde2e1;color:#b71c1c' : ($d['left'] <= 3 ? '#fdf3dc;color:#8a5a0e' : '#e1f3f2;color:#036f72') ?>"><?= htmlspecialchars(dl_when($d['left'])) ?></span></a>
<?php endforeach; endif; ?>
    <p style="margin:6px 0 0;text-align:right;font-size:.88rem"><a href="deadlines.php" style="font-weight:600">See all upcoming deadlines &rarr;</a></p>
  </div>
  <div class="lpanel" id="signed"><h2>Newly signed documents</h2>
<?php if (!$signedNew): ?>    <p class="empty">No newly signed documents.</p>
<?php else: foreach ($signedNew as $x): $r = $x['r']; $what = ($r['offer_address'] ?? '') ?: preg_replace('/^[^-]*-\s*/', '', (string)($r['form'] ?? 'Document'));
    $url = $x['rec'] ? $x['rec']['url'] : (empty($r['filed']) && !sg_can_file($r) ? 'signed.php?assign=' . urlencode($r['id']) : 'signed.php?seen=' . urlencode($r['id'])); ?>
    <a class="lrow" href="<?= htmlspecialchars($url) ?>"><div class="who"><div class="nm"><?= htmlspecialchars($what) ?></div><div class="meta"><?= htmlspecialchars($x['rec'] ? 'In ' . $x['rec']['name'] . ' › Signed Documents' : (!empty($r['filed']) ? 'Filed in ' . $r['filed']['folder'] : 'Choose which record to file it in')) ?></div></div><span class="due" style="background:#e3f4e8;color:#2e7d32">Signed</span></a>
<?php endforeach; endif; ?>
  </div>
<?php elseif ($view === 'buyer'): ?>
  <div class="lpanel">
    <h2>Buyers</h2>
<?php if (!$buyers): ?><p class="note">No buyer records yet. <a href="buyer.php?new=1">Create one</a> once a buyer signs an agreement.</p>
<?php else: ?>
    <?= ps_group_box('buyer', $buyers, function ($t) { return 'buyer.php?tx=' . urlencode($t['folder']); }) ?>
<?php endif; ?>
  </div>
<?php else: ?>
  <div class="lpanel">
    <h2>Sellers</h2>
<?php if (!$sellers): ?><p class="note">No seller records yet. They appear here once the listing folder is created in Active Business &rsaquo; 2 SELLERS.</p>
<?php else: ?>
    <?= ps_group_box('seller', $sellers, function ($t) { return 'seller.php?tx=' . urlencode($t['folder']); }) ?>
<?php endif; ?>
  </div>
<?php endif; ?>
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
