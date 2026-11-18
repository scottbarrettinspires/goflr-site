<?php
// Public download page for documents an agent shared by link (no sign-in): listing agents open this from the email.
require __DIR__ . "/flrdb.php";
require_once __DIR__ . "/sharelib.php";
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$t = (string)($_GET['t'] ?? '');
if (isset($_GET['n'])) { // download one document
    $d = sh_bytes($t, (int)$_GET['n']);
    if (empty($d['ok'])) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit($d['msg'] ?? 'Not found'); }
    while (ob_get_level()) { @ob_end_clean(); }
    header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . $d['name'] . '"'); header('Content-Length: ' . strlen($d['pdf'])); echo $d['pdf']; exit;
}
$r = sh_get($t); $h = function ($x) { return htmlspecialchars((string)$x); };
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Documents from Florida Luxury Realty</title>
<link rel="stylesheet" href="style.css?v=20">
<style>body{background:#f4f8f8}.shw{max-width:640px;margin:0 auto;padding:24px 16px}.shc{background:#fff;border:1px solid #dbe6e5;border-radius:14px;padding:20px}.shr{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;padding:12px 0;border-top:1px solid #e7efee}.shr b{flex:1 1 240px}</style></head><body>
<div class="shw"><div class="shc">
<?php if (!$r): ?>
  <h2>This link has expired</h2><p>Please ask the sender for a new link.</p>
<?php else: ?>
  <h2>Documents from Florida Luxury Realty</h2>
  <p><?= $r['address'] !== '' ? 'Property: <strong>' . $h($r['address']) . '</strong><br>' : '' ?>Sent by <?= $h($r['agent_name'] ?: 'your agent') ?>, Florida Luxury Realty.</p>
  <p class="note">The contract is not valid until the seller signs it.</p>
<?php foreach ($r['docs'] as $i => $d): ?>
  <div class="shr"><b><?= $h($d['title']) ?></b><a class="btn" href="share.php?t=<?= $h($t) ?>&amp;n=<?= (int)$i ?>">Download</a></div>
<?php endforeach; ?>
  <p class="note" style="margin-top:14px">This link works until <?= $h(date('M j, Y', strtotime($r['expires']))) ?>.</p>
<?php endif; ?>
</div></div></body></html>
