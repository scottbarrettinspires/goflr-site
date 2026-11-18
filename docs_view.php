<?php
// "Documents" for one client record: every executed document with its Google Drive link. Opens in the record's content panel
// like a stage (Shop Homes, Make An Offer...), and on its own page with the site header when opened full-size.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/doclib.php";
require_once __DIR__ . "/subpage.php";
$me = strtolower($_SESSION['email'] ?? ''); $tx = (string)($_GET['tx'] ?? '');
sp_head('Documents', $tx);
$j = dc_list($me, $tx);
$h = function ($x) { return htmlspecialchars((string)$x); };
$fd = function ($d) { $t = strtotime((string)$d); return $t ? date('M j, Y', $t) : ''; };
?>
<style>.pdg h3{margin:16px 0 6px;font-size:1rem;color:#036f72}.pdg h3:first-of-type{margin-top:4px}.pdr{display:flex;flex-wrap:wrap;gap:6px 16px;align-items:center;padding:10px 12px;background:#fff;border:1px solid #dfe9e9;border-radius:10px;margin:0 0 8px}.pdr b{flex:1 1 260px}.pdr small{color:#5a6a6c}.pdr a{font-weight:700;color:#036f72;text-decoration:none}.pdr a:hover{text-decoration:underline}</style>
<div class="pdg">
<?php if (empty($j['ok'])): ?>
  <p class="sigerr"><?= $h($j['msg'] ?? 'Could not load the documents.') ?></p>
<?php else: ?>
  <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;margin:0 0 6px"><strong>Document tracker for <?= $h($j['name']) ?></strong><?php if ($j['folder_url'] !== ''): ?><a href="<?= $h($j['folder_url']) ?>" target="_blank" rel="noopener" style="font-weight:700;color:#036f72">Open the Drive folder</a><?php endif; ?></div>
<?php if ($j['needs_upload']): ?>
  <h3 style="color:#b45309">Needs a signed copy uploaded</h3>
<?php foreach ($j['needs_upload'] as $u): ?>
  <div class="pdr" style="background:#fff7ed;border-color:#f3d9b8"><b><?= $h($u['title']) ?></b><small>Downloaded for signing on <?= $h($fd($u['downloaded_on'])) ?>. The signed copy has not been uploaded yet.</small><a href="<?= $h($u['upload_url']) ?>">Upload signed copy</a></div>
<?php endforeach; endif; ?>
<?php if ($j['pending']): ?>
  <h3 style="color:#8a5a00">Waiting for signatures</h3>
<?php foreach ($j['pending'] as $p): ?>
  <div class="pdr" style="background:#fffbea;border-color:#f1e1ae"><b><?= $h($p['title']) ?></b><small>Sent <?= $h($fd($p['sent'])) ?></small>
<?php foreach ($p['signers'] as $s): ?><small><?= $s['signed'] ? '&#10003; Signed' : 'Waiting' ?>: <?= $h($s['name'] !== '' ? $s['name'] . ' ' : '') ?><?= $h($s['email']) ?></small><?php endforeach; ?></div>
<?php endforeach; endif; ?>
<?php if (!$j['count']): ?>
  <p class="note">No signed documents yet. Signed buyer agreements, contracts, addenda and disclosures appear here as soon as they are executed.</p>
<?php endif; if ($j['groups']): ?>  <h3 style="margin-top:18px">Executed documents</h3><?php endif; foreach ($j['groups'] as $g): ?>
  <h4 style="margin:12px 0 6px;font-size:.92rem;color:#036f72"><?= $h($g['label']) ?></h4>
<?php foreach ($g['items'] as $i): ?>
  <div class="pdr"><b><?= $h($i['title']) ?></b><small><?= $i['source'] === 'upload' ? 'Uploaded signed copy' : 'Signed by e-signature' ?><?= $i['signed_on'] !== '' ? ' &middot; ' . $h($fd($i['signed_on'])) : '' ?></small>
<?php if ($i['drive_url'] !== ''): ?><a href="<?= $h($i['drive_url']) ?>" target="_blank" rel="noopener">Open in Google Drive</a><?php endif; ?>
<?php if ($i['download_url'] !== ''): ?><a href="<?= $h($i['download_url']) ?>" target="_top">Download</a><?php endif; ?>
<?php if ($i['folder'] !== ''): ?><small>Filed in <?= $h($i['folder']) ?></small><?php endif; ?>
<?php if ($i['note'] !== ''): ?><small><?= $h($i['note']) ?></small><?php endif; ?></div>
<?php endforeach; endforeach; ?>
<?php endif; ?>
</div>
<?php sp_foot(); ?>
