<?php
// Reached from the "Buyer Agreement" step of the Pipeline Stage ladder: shows the signed Buyer Brokerage Agreement for
// this buyer record, if one has been filed here, plus a way to start one if not.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/pstage.php";
require __DIR__ . "/sigtools.php";
require_once __DIR__ . "/subpage.php";
$me = strtolower($_SESSION['email'] ?? '');
$tx = (string)($_GET['tx'] ?? '');
$recs = sg_for_agent($me);
$filed = array_values(array_filter($recs, function ($r) use ($tx) { return (string)($r['folder']['id'] ?? '') === $tx && !empty($r['completed']); }));
$agreements = array_values(array_filter($filed, function ($r) { return stripos((string)($r['form'] ?? ''), 'agreement') !== false || stripos((string)($r['form'] ?? ''), 'buyer brokerage') !== false; }));
$others = array_udiff($filed, $agreements, function ($a, $b) { return strcmp($a['id'], $b['id']); });
sp_head('Buyer Agreement', $tx);
?>
<div class="panel sigbox">
<?php if ($agreements): ?>
  <h2 style="margin-top:0">Signed Buyer Agreement</h2>
<?php foreach ($agreements as $r): ?>
  <p style="margin:0 0 10px"><strong><?= htmlspecialchars($r['form']) ?></strong><br>Signed <?= htmlspecialchars(date('M j, Y', strtotime($r['created'] ?? 'now'))) ?> &middot; <a href="signed.php?dl=<?= urlencode($r['id']) ?>">Download the signed copy</a></p>
<?php endforeach; ?>
<?php else: ?>
  <h2 style="margin-top:0">No signed Buyer Agreement filed here yet</h2>
  <p class="note">Once a Buyer Brokerage Agreement is signed and filed to this transaction, it will show here. If the agreement was sent for signature before this record existed, open <a href="signed.php">Signed Documents</a> and file it to this transaction.</p>
  <p><a class="btn" href="sign.php?f=1IoJl7SSl2grnKbpLMqH_L1gq15XDJi7U&s=b&tx=<?= urlencode($tx) ?>">Send the Exclusive Buyer Brokerage Agreement for signature</a></p>
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
