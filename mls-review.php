<?php
// Public, read-only preview page for the MLS Grid license application's "Staging URL" question. It shows reviewers how
// MLS data would be used and displayed on goflr.com, using fixture listings only (mlslib.php's SAMPLE- rows). It reads
// no MLS data, touches no database and shows no agent or client information.
require_once __DIR__ . '/mlslib.php';
header('X-Robots-Tag: noindex, nofollow');
$h = function ($x) { return htmlspecialchars((string)$x); };
$rows = mls_sample_rows();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>goflr.com MLS Data Use Preview - Florida Luxury Realty</title>
<style>
:root{--teal:#036f72;--soft:#e6f2f2;--line:#dfe6e7;--muted:#5b6669}
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;line-height:1.5;color:#1f2a2c;background:#f4f8f8}
.top{background:var(--teal);padding:16px;text-align:center}.top img{height:54px}
main{max-width:860px;margin:0 auto;padding:18px 16px 40px}
h1{font-size:1.5rem;margin:6px 0 6px}h2{font-size:1.15rem;margin:0 0 8px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px 18px;margin:0 0 14px}
.note{background:var(--soft);border-left:4px solid var(--teal);padding:10px 14px;border-radius:8px;font-size:.92rem;margin:0 0 14px}
ul{margin:6px 0 0;padding-left:20px}li{margin:3px 0}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}
.lst{border:1px solid var(--line);border-radius:12px;padding:12px 14px;background:#fff}.lst .pr{font-size:1.15rem;font-weight:800}.lst .st{display:inline-block;font-size:.74rem;font-weight:700;background:var(--soft);color:var(--teal);border-radius:99px;padding:2px 9px}
.lst .mt{color:var(--muted);font-size:.88rem}.lst .at{font-size:.74rem;color:var(--muted);margin-top:8px;border-top:1px solid var(--line);padding-top:6px}
.sample{font-size:.72rem;font-weight:800;color:#8a5a00;background:#fdf6e3;border-radius:6px;padding:1px 6px;margin-left:6px}
.foot{text-align:center;color:var(--muted);font-size:.82rem;margin-top:18px}
</style>
</head>
<body>
<div class="top"><img src="logo-white.png" alt="Florida Luxury Realty"></div>
<main>
  <h1>goflr.com: how MLS data will be used</h1>
  <p class="note">This is a preview page for the MLS Grid license review. It uses <strong>fixture (sample) listings only</strong>. No live MLS data is loaded, and nothing on this page comes from Stellar MLS.</p>

  <div class="card">
    <h2>What goflr.com is</h2>
    <p>goflr.com is the private back office of Florida Luxury Realty, Inc. It is used by the brokerage's licensed agents and staff to manage their own clients and transactions. It is not a public consumer search site. Every page except this preview requires an individual sign-in.</p>
  </div>

  <div class="card">
    <h2>How MLS data would be used</h2>
    <ul>
      <li>A signed-in agent looks up listings to work with their own buyer and seller clients, for example while shopping homes with a buyer or filling in a contract offer.</li>
      <li>Listing details (address, price, beds, baths, size, status) would be shown to that agent inside the back office.</li>
      <li>Listings are not shown to the general public, and are not offered to other sites or tools.</li>
    </ul>
    <p style="margin-top:10px;color:var(--muted);font-size:.9rem">Final scope, display rules and required disclaimers will follow whatever the license and the MLS rules require.</p>
  </div>

  <div class="card">
    <h2>Example of the listing display (sample data)</h2>
    <div class="grid">
<?php foreach ($rows as $r): ?>
      <div class="lst">
        <span class="st"><?= $h($r['status']) ?></span><span class="sample">SAMPLE</span>
        <div class="pr">$<?= number_format($r['price']) ?></div>
        <div><?= $h($r['address']) ?>, <?= $h($r['city']) ?>, <?= $h($r['state']) ?> <?= $h($r['zip']) ?></div>
        <div class="mt"><?= (int)$r['beds'] ?> bd &middot; <?= rtrim(rtrim(number_format($r['baths'], 1), '0'), '.') ?> ba &middot; <?= number_format($r['sqft']) ?> sq ft</div>
        <div class="at">Listing courtesy of <?= $h($r['office']) ?>. Sample data, not an MLS listing.</div>
      </div>
<?php endforeach; ?>
    </div>
    <p class="mt" style="color:var(--muted);font-size:.85rem;margin-top:10px">With live data, each listing would carry the MLS-required attribution and disclaimer text.</p>
  </div>

  <div class="card">
    <h2>Contact</h2>
    <p>Florida Luxury Realty, Inc. &middot; Broker: Scott Barrett &middot; <a href="mailto:broker@myflroffice.com">broker@myflroffice.com</a></p>
  </div>
  <p class="foot">Florida Luxury Realty, Inc. &middot; goflr.com</p>
</main>
</body>
</html>
