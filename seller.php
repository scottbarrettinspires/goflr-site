<?php
// Seller record page: the seller's details (kept in "Seller Info.json" in the listing folder) and the lead behind the listing.
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
require_once __DIR__ . "/leadbox.php";
require_once __DIR__ . "/dlib.php";
@set_time_limit(120);
header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
// The record list comes from the server's transaction index (kept in step with Drive).
$sellers = array_values(array_filter(flr_tx_list($me), function ($t) { return $t['side'] === 'seller'; }));
$rec = null; foreach ($sellers as $t) { if ($t['folder'] === $tx) $rec = $t; }
if (!$rec && preg_match('/^[A-Za-z0-9_-]{10,80}$/', $tx)) { // not indexed yet: ask Drive once
    foreach ((sg_folders($me, $tx) ?: []) as $f) { if ($f['id'] === $tx && flr_tx_side($f['group']) === 'seller') { list($st, $stn) = flr_tx_stage($f['group']); $rec = ['folder' => $f['id'], 'name' => $f['name'], 'stage' => $st, 'stage_name' => $stn, 'grp' => $f['group']]; } }
}
$FIELDS = ['s1_name', 's1_phone', 's1_email', 's2_name', 's2_phone', 's2_email', 'property', 'mailing'];
$info = []; $msg = ''; $err = ''; $old = false;
if ($rec) {
    $r = sg_seller_info($me, $tx);
    if (!empty($r['ok'])) { $info = is_array($r['info'] ?? null) ? $r['info'] : []; }
    else { $old = !empty($r['old']); $err = $old ? '' : 'Could not load the saved information: ' . ($r['note'] ?? ''); }
    lb_handle_link($me, 'seller', $tx, (string)$rec['name'], (int)$rec['stage'], $info, 'seller.php?tx=' . urlencode($tx));
    dl_handle_save($me, 'seller', $tx, 'seller.php?tx=' . urlencode($tx));
}
if ($rec && !$old && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'save') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $new = $info; // keep everything the lead section packed in (lead_*), update the edited fields
        foreach ($FIELDS as $k) { $new[$k] = mb_substr(trim((string)($_POST['f_' . $k] ?? '')), 0, 200); }
        $mine = strtolower(trim((string)($_SESSION['name'] ?? '')));
        foreach (['s1', 's2'] as $p) { if ($mine !== '' && strtolower($new[$p . '_name']) === $mine) { $new[$p . '_name'] = ''; $new[$p . '_phone'] = ''; $new[$p . '_email'] = ''; $msg = 'Your own name was removed from a seller line. '; } }
        foreach (['s1_email', 's2_email'] as $k) { if ($new[$k] !== '' && !filter_var($new[$k], FILTER_VALIDATE_EMAIL)) { $err = 'Please check the email address: ' . $new[$k]; } }
        if (!$err && $new['s1_name'] === '') $err = 'Please enter the seller\'s name.';
        if (!$err && $new['s1_phone'] === '' && $new['s1_email'] === '') $err = 'Please enter Seller 1\'s phone or email.';
        $new['updated'] = gmdate('c');
        if (!$err) {
            $r = sg_seller_info_save($me, $tx, $new);
            if (!empty($r['ok'])) { $msg .= 'Saved.'; lb_fill_contact($me, $tx, 'seller', $new); } else { $err = 'Could not save: ' . ($r['note'] ?? ''); }
        }
        $info = $new;
    }
}
$v = function ($k) use ($info) { return htmlspecialchars((string)($info[$k] ?? '')); };
$byStage = []; foreach ($sellers as $t) { $byStage[$t['stage_name'] ?: 'Other'][] = $t; }
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
<title>Seller Info - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=13">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="my-transactions.php" aria-current="page">My Transactions</a>
    <a href="leads.php">Leads</a>
    <a href="deadlines.php">Deadlines</a>
    <a href="forms.php">Forms</a>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a>
    <?php if (flr_is_broker()): ?><a href="dashboard.php">Dashboard</a><?php endif; ?>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1>Seller Info</h1><p>The seller's details for the listing, and everything you learned about them as a lead.</p></section>
<main class="wrap">
<?php if (!$rec): ?>
  <div class="panel sigbox">
    <h2 style="margin-top:0">Your seller records</h2>
<?php if (!$sellers): ?><p class="note">No seller records found yet. They appear here once they are in My Transactions &rsaquo; 2 SELLERS. New records can take a few minutes to show.</p><?php endif; ?>
<?php foreach ($byStage as $stn => $list): ?>
    <h3 style="margin:14px 0 6px"><?= htmlspecialchars($stn) ?></h3>
<?php foreach ($list as $t): ?>
    <p style="margin:0 0 6px"><a href="seller.php?tx=<?= urlencode($t['folder']) ?>"><?= htmlspecialchars($t['name']) ?></a><?= $t['lead_id'] ? ' <span style="color:#6a777a;font-size:.85rem">· linked to a lead</span>' : '' ?></p>
<?php endforeach; endforeach; ?>
  </div>
<?php else: ?>
<?= lb_box($me, 'seller', $tx, $info) ?>
<?= dl_box($me, 'seller', $tx, 'seller.php?tx=' . urlencode($tx)) ?>
  <form method="post" class="panel sigbox bi" autocomplete="off" data-lpignore="true" data-form-type="other">
    <h2><?= htmlspecialchars($rec['name']) ?> <span style="font-size:.85rem;font-weight:600;color:#036f72;background:#e1f3f2;border-radius:99px;padding:3px 10px;vertical-align:middle"><?= htmlspecialchars($rec['stage_name'] ?: 'Seller') ?></span></h2>
<?php if ($old): ?><p class="note">Seller details can be viewed and saved here after the office script is updated to v26. The lead box above works now.</p><?php endif; ?>
<?php if ($msg): ?><p class="note"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>"><input type="hidden" name="op" value="save">
    <fieldset class="wq"<?= $old ? ' disabled' : '' ?>><legend>Seller 1</legend>
      <label>Seller full name<input type="text" name="f_s1_name" value="<?= $v('s1_name') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
      <label>Phone<input type="text" name="f_s1_phone" value="<?= $v('s1_phone') ?>" inputmode="tel" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
      <label>Email<input type="email" name="f_s1_email" value="<?= $v('s1_email') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
    <fieldset class="wq"<?= $old ? ' disabled' : '' ?>><legend>Seller 2 (if any)</legend>
      <label>Seller full name<input type="text" name="f_s2_name" value="<?= $v('s2_name') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
      <label>Phone<input type="text" name="f_s2_phone" value="<?= $v('s2_phone') ?>" inputmode="tel" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
      <label>Email<input type="email" name="f_s2_email" value="<?= $v('s2_email') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
    <fieldset class="wq"<?= $old ? ' disabled' : '' ?>><legend>Property and mailing address</legend>
      <label>Property address<input type="text" name="f_property" value="<?= $v('property') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
      <label>Seller mailing address (if different)<input type="text" name="f_mailing" value="<?= $v('mailing') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
    <p><?php if (!$old): ?><button type="submit" class="btn">Save seller info</button> <?php endif; ?><a class="btn ghost" href="https://drive.google.com/drive/folders/<?= urlencode($tx) ?>" target="_blank" rel="noopener">Open the listing folder</a> <a class="btn ghost" href="seller.php">All seller records</a></p>
  </form>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
