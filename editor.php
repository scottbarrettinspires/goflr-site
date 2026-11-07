<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
@set_time_limit(120);
header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? '');
$all = preg_match('/^[A-Za-z0-9_-]{10,80}$/', $tx) ? (sg_folders($me, $tx) ?: []) : [];
$buyer = null; foreach ($all as $f) { if ($f['id'] === $tx && $f['group'] === '1 BUYERS / 1 Buyers') $buyer = $f; }
$draft = $buyer && (string)($_GET['draft'] ?? '') === '1';
$files = ($buyer && !$draft) ? sg_packets($me, $tx) : null;
$id = (string)($_GET['id'] ?? '');
$pick = null;
if ($draft) { $pick = ['id' => '', 'name' => 'Contract packet (new, not saved yet)']; }
elseif ($files) {
    foreach ($files as $f) { if ($id !== '' && $f['id'] === $id) { $pick = $f; break; } }
    if (!$pick) { foreach ($files as $f) { if (stripos($f['name'], 'Buyer Disclosures') !== 0) { $pick = $f; break; } } } // default to the contract packet, not the disclosures
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
<title>Review and Complete - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=18">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="leads.php">Leads</a>
    <a href="my-transactions.php">Transactions</a>
    <a href="forms.php" aria-current="page">Forms</a>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a> <a href="collab.php">Collaboration</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1>Review and Complete</h1><p>Fill in the contract packet here, then continue for signing.</p></section>
<main class="wrap">
<?php if (!$buyer): ?>
  <p class="note">Open this page from a buyer's contract packet: use <a href="wizard.php">the Contract Forms Wizard</a> first.</p>
<?php elseif (!$pick): ?>
  <p class="note">No contract packet found in <?= htmlspecialchars($buyer['name']) ?> yet. <a href="wizard.php?tx=<?= urlencode($tx) ?>">Build one with the wizard</a>.</p>
<?php else: ?>
  <div class="panel sigbox ebar">
    <p><strong><?= htmlspecialchars($buyer['name']) ?></strong> &middot; <?= htmlspecialchars($pick['name']) ?>
<?php if (!$draft && count($files) > 1): ?>
    <br><label for="pk">Packet: </label><select id="pk" class="txsel" onchange="location='editor.php?tx=<?= urlencode($tx) ?>&amp;id='+encodeURIComponent(this.value)">
<?php foreach ($files as $f): ?><option value="<?= htmlspecialchars($f['id']) ?>"<?= $f['id'] === $pick['id'] ? ' selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option><?php endforeach; ?>
    </select>
<?php endif; ?>
    </p>
    <p id="estatus" class="note">Loading...</p>
    <p><button type="button" class="btn" id="esave" disabled>Continue For Signing</button></p>
    <div id="eres" hidden></div>
  </div>
  <div id="pages" class="epages"></div>
  <div class="panel sigbox ebar"><p><button type="button" class="btn" id="esave2" disabled>Continue For Signing</button></p></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script src="flrdraft.js?v=1"></script>
<script src="editor.js?v=12"></script>
<script>
FLREditor.start({draft:<?= $draft ? 'true' : 'false' ?>,pdfUrl:'editor_pdf.php?tx=<?= urlencode($tx) ?>&id=<?= urlencode($pick['id']) ?>',worker:'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',saveUrl:'editor_save.php',csrf:<?= json_encode($_SESSION['csrf']) ?>,tx:<?= json_encode($tx) ?>,kind:<?= json_encode(stripos($pick['name'], 'Buyer Disclosures') === 0 ? 'disclosures' : 'packet') ?>}).catch(function(e){document.getElementById('estatus').textContent='Could not open the packet: '+e.message;});
</script>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
