<?php
// Reached from the "Executed Contract" step: shows the signed contract/offer paperwork filed to this transaction folder.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/pstage.php";
require_once __DIR__ . "/sigtools.php";
require_once __DIR__ . "/subpage.php";
$me = strtolower($_SESSION['email'] ?? '');
$tx = (string)($_GET['tx'] ?? '');
$kind = (string)($_GET['kind'] ?? '');
$recs = sg_for_agent($me);
$filed = array_values(array_filter($recs, function ($r) use ($tx) { return (string)($r['folder']['id'] ?? '') === $tx && !empty($r['completed']); }));
$matchWords = $kind === 'contract' ? ['contract', 'offer', 'addendum'] : [];
$primary = []; $others = [];
foreach ($filed as $r) {
    $hit = false; foreach ($matchWords as $w) { if (stripos((string)($r['form'] ?? ''), $w) !== false) { $hit = true; break; } }
    if ($hit || !$matchWords) $primary[] = $r; else $others[] = $r;
}
sp_head($kind === 'contract' ? 'Executed Contract' : 'Signed Documents', $tx);
?>
<div class="panel sigbox">
<?php if ($primary): ?>
  <h2 style="margin-top:0"><?= $kind === 'contract' ? 'Contract and offer paperwork filed here' : 'Signed documents filed here' ?></h2>
<?php foreach ($primary as $r): ?>
  <p style="margin:0 0 10px"><strong><?= htmlspecialchars($r['form']) ?></strong><br>Signed <?= htmlspecialchars(date('M j, Y', strtotime($r['created'] ?? 'now'))) ?> &middot; <a href="signed.php?dl=<?= urlencode($r['id']) ?>">Download the signed copy</a></p>
<?php endforeach; ?>
<?php else: ?>
  <h2 style="margin-top:0">No executed contract filed here yet</h2>
  <p class="note">Once an offer is fully signed and filed to this transaction, it will show here. Build and send the offer from <a href="wizard.php?tx=<?= urlencode($tx) ?>" target="_top">Make An Offer</a>, or file an already-signed one from <a href="signed.php" target="_top">Signed Documents</a>.</p>
<?php endif; ?>
<?php if ($others): ?>
  <h3 style="margin:16px 0 6px">Other signed documents filed here</h3>
<?php foreach ($others as $r): ?>
  <p style="margin:0 0 6px"><?= htmlspecialchars($r['form']) ?> &middot; <a href="signed.php?dl=<?= urlencode($r['id']) ?>">Download</a></p>
<?php endforeach; ?>
<?php endif; ?>
  <p style="margin-top:14px"><a class="btn ghost" href="https://drive.google.com/drive/folders/<?= urlencode($tx) ?>" target="_blank" rel="noopener">Open the transaction folder</a></p>
</div>
<?php sp_foot(); ?>
