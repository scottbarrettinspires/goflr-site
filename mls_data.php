<?php
// MLS Grid connection settings and a sample-data proof of the fetch -> parse -> cache -> display pipeline.
// Staff/broker only. Not connected to live data: mls_settings()['connected'] stays false until Stellar MLS approves
// a back-office license and the site is explicitly turned on here, matching what was promised in the email to Stellar.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/mlslib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
if (!flr_is_broker()) { header('Location: home.php'); exit; }
$msg = ''; $err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    if (($_POST['op'] ?? '') === 'savesettings') {
        mls_save_settings(['base_url' => (string)($_POST['base_url'] ?? ''), 'originating_system' => (string)($_POST['originating_system'] ?? ''), 'token' => (string)($_POST['token'] ?? ''), 'agents' => !empty($_POST['agents']), 'connected' => !empty($_POST['connected'])]);
        $msg = 'Saved.';
    } elseif (($_POST['op'] ?? '') === 'runsample') {
        mls_cache_listings(mls_sample_raw()); $msg = 'Sample listings parsed and cached below — this is fixture data, not a live MLS pull.';
    } elseif (($_POST['op'] ?? '') === 'pulllive') {
        $r = mls_fetch_page('Property', ['$top' => 10, '$filter' => "OriginatingSystemName eq '" . preg_replace('/[^A-Za-z0-9 _-]/', '', mls_settings()['originating_system']) . "' and MlgCanView eq true"]);
        if (!empty($r['ok'])) { mls_cache_listings($r['rows']); $msg = count($r['rows']) . ' listing(s) pulled and cached.'; } else { $err = $r['note'] ?? 'Could not reach MLS Grid.'; }
    }
}
$s = mls_settings();
$cached = mls_db()->query('SELECT data, updated FROM mls_listings ORDER BY updated DESC LIMIT 25')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#036f72">
<link rel="icon" href="/icon-192.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>MLS Data - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=17">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Virtual Broker Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="recruiting.php">Recruiting</a>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>
<section class="hero"><h1>MLS Data</h1><p>MLS Grid back-office connection settings, and a sample-data test of the pipeline before any live pull.</p></section>
<main class="wrap">
  <div class="panel sigbox">
    <h2 style="margin-top:0">Status</h2>
    <p><strong>Connected:</strong> <?= $s['connected'] ? 'Yes — live lookups are enabled' : 'No — not connected' ?> &nbsp; <strong>Agents can use lookups:</strong> <?= !empty($s['agents']) ? 'Yes' : 'No — office staff only' ?></p>
    <p class="note">Development mode: the MLS number lookup on Make An Offer works for office staff only while the website is being built. Switch the connection to the license issued for goflr.com (new token) before turning on agent access or launching. Note: records carry a "can use" tag (IDX, BO and so on) shown in the table below, so you can see which use each listing is marked for.</p>
  </div>
<?php if ($msg): ?><p class="note"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <form method="post" class="panel sigbox">
    <h2 style="margin-top:0">Connection settings</h2>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="savesettings">
    <label>MLS Grid base URL<input type="text" name="base_url" value="<?= htmlspecialchars($s['base_url']) ?>" placeholder="https://api.mlsgrid.com/v2"></label>
    <label>Originating system name<input type="text" name="originating_system" value="<?= htmlspecialchars($s['originating_system']) ?>" placeholder="mfrmls"></label>
    <label>Access token (leave blank to keep the saved one)<input type="password" name="token" placeholder="<?= $s['token'] ? '••••••••' : '' ?>"></label>
    <label class="wr"><input type="checkbox" name="connected" <?= $s['connected'] ? 'checked' : '' ?>> Connected — allow live lookups and pulls</label>
    <label class="wr"><input type="checkbox" name="agents" <?= !empty($s['agents']) ? 'checked' : '' ?>> Open the MLS number lookup to agents (leave off until the goflr.com license is issued)</label>
    <p><button type="submit" class="btn">Save settings</button></p>
  </form>
  <div class="panel sigbox">
    <h2 style="margin-top:0">Try it</h2>
    <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="runsample"><button class="btn ghost" type="submit">Run with sample data</button></form>
<?php if ($s['connected']): ?> <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="pulllive"><button class="btn" type="submit">Pull live (10 listings)</button></form><?php endif; ?>
    <p class="note">"Run with sample data" proves the fetch → parse → cache → display pipeline with three fake listings (SAMPLE-1001/1002/1003) — nothing leaves or reaches goflr.com.</p>
  </div>
<?php if ($cached): ?>
  <div class="panel sigbox">
    <h2 style="margin-top:0">Cached listings</h2>
    <table style="width:100%;border-collapse:collapse;font-size:.92rem">
    <tr style="text-align:left;border-bottom:2px solid var(--line)"><th style="padding:6px 8px 6px 0">ID</th><th style="padding:6px 8px">Status</th><th style="padding:6px 8px">Address</th><th style="padding:6px 8px">Price</th><th style="padding:6px 8px">Marked for</th><th style="padding:6px 0">Updated</th></tr>
<?php foreach ($cached as $c): $d = json_decode($c['data'], true) ?: []; ?>
    <tr style="border-bottom:1px solid var(--line)"><td style="padding:6px 8px 6px 0"><?= htmlspecialchars($d['id'] ?? '') ?></td><td style="padding:6px 8px"><?= htmlspecialchars($d['status'] ?? '') ?></td><td style="padding:6px 8px"><?= htmlspecialchars(trim(($d['address'] ?? '') . ', ' . ($d['city'] ?? ''))) ?></td><td style="padding:6px 8px">$<?= number_format((float)($d['price'] ?? 0)) ?></td><td style="padding:6px 8px"><?= htmlspecialchars((string)($d['can_use'] ?? '')) ?></td><td style="padding:6px 0"><?= htmlspecialchars(date('M j, g:ia', strtotime($c['updated']))) ?></td></tr>
<?php endforeach; ?>
    </table>
  </div>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Broker / staff access only.</footer>
</body>
</html>
