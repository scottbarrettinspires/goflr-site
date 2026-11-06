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
$bagType = bag_agreement_type($tx);
sp_head('Buyer Agreement', $tx);
?>
<div class="panel sigbox">
<?php if ($agreements): ?>
  <h2 style="margin-top:0">Signed Buyer Agreement</h2>
<?php if ($bagType === 'exclusive'): ?>
  <p class="note" style="background:#e1f3f2;border-left:3px solid #036f72;padding:8px 12px">Exclusive Buyer Brokerage Agreement on file &mdash; this buyer doesn't need to sign anything further to see more homes.</p>
<?php elseif ($bagType === 'showing'): ?>
  <p class="note" style="background:#fff4e5;border-left:3px solid #b26a00;padding:8px 12px">Showing Agreement on file &mdash; it only covers the home(s) named on that signed form. Before showing this buyer a home it doesn't name, get a new Showing Agreement signed for it, or move up to an Exclusive Buyer Brokerage Agreement so this isn't needed again.</p>
<?php endif; ?>
<?php foreach ($agreements as $r): ?>
  <p style="margin:0 0 10px"><strong><?= htmlspecialchars($r['form']) ?></strong><br>Signed <?= htmlspecialchars(date('M j, Y', strtotime($r['created'] ?? 'now'))) ?> &middot; <a href="signed.php?dl=<?= urlencode($r['id']) ?>">Download the signed copy</a></p>
<?php endforeach; ?>
<?php if ($bagType === 'showing'): ?>
  <p><a class="btn" href="sign.php?f=<?= BAG_SHOWING_FORM_ID ?>&s=b&tx=<?= urlencode($tx) ?>" target="_top">Send a new Showing Agreement for signature</a> <a class="btn ghost" href="sign.php?f=<?= BAG_EXCLUSIVE_FORM_ID ?>&s=b&tx=<?= urlencode($tx) ?>" target="_top">Move up to Exclusive instead</a></p>
<?php endif; ?>
<?php else: ?>
  <h2 style="margin-top:0">No signed Buyer Agreement filed here yet</h2>
  <p class="note">Send one for e-signature, or download a copy to sign in person. If one was already sent before this record existed, open <a href="signed.php" target="_top">Signed Documents</a> and file it to this transaction.</p>
  <?= bag_choice_cards($tx) ?>
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
