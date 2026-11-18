<?php
// "Documents" for one client record: every executed document with its Google Drive link. Opens in the record's content panel
// like a stage (Shop Homes, Make An Offer...), and on its own page with the site header when opened full-size.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/doclib.php";
require_once __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? ''); $tx = (string)($_GET['tx'] ?? '');
sp_head('Compliance Document Records', $tx);
$j = dc_list($me, $tx);
$h = function ($x) { return htmlspecialchars((string)$x); };
$fd = function ($d) { $t = strtotime((string)$d); return $t ? date('M j, Y', $t) : ''; };
?>
<style>.pdg h3{margin:16px 0 6px;font-size:1rem;color:#036f72}.pdg h3:first-of-type{margin-top:4px}.pdr{display:flex;flex-wrap:wrap;gap:6px 16px;align-items:center;padding:10px 12px;background:#fff;border:1px solid #dfe9e9;border-radius:10px;margin:0 0 8px}.pdr b{flex:1 1 260px}.pdr small{color:#5a6a6c}.pdi{flex:0 0 auto;display:inline-flex;width:26px;height:26px;align-items:center;justify-content:center}.pdi svg{display:block}.pdgreen{background:#e6f5e9;border-color:#b9dfc1}.pdgold{background:#fff6d6;border-color:#ecd37a}.pdred{background:#fde8e8;border-color:#f0b4b4}.pdr a{font-weight:700;color:#036f72;text-decoration:none}.pdr a:hover{text-decoration:underline}</style>
<?php
$ICON = [
  'done' => '<span class="pdi" title="Executed" aria-label="Executed"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2e7d32" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M7.5 12.5l3 3 6-7"/></svg></span>',
  'wait' => '<span class="pdi" title="Waiting for signatures" aria-label="Waiting for signatures"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#a16207" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6.5V12l3.5 2"/></svg></span>',
  'miss' => '<span class="pdi" title="Required document missing" aria-label="Required document missing"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#b71c1c" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="17" height="17" rx="3"/></svg></span>',
];
?>
<div class="pdg">
<?php if (empty($j['ok'])): ?>
  <p class="sigerr"><?= $h($j['msg'] ?? 'Could not load the documents.') ?></p>
<?php else: ?>
<?php if ($j['folder_url'] !== ''): ?>  <div style="text-align:right;margin:0 0 6px"><a href="<?= $h($j['folder_url']) ?>" target="_blank" rel="noopener" style="font-weight:700;color:#036f72">Open the Drive folder</a></div><?php endif; ?>
<?php if ($j['needs_upload']): ?>
  <h3 style="color:#b71c1c">Needs a signed copy uploaded</h3>
<?php foreach ($j['needs_upload'] as $u): ?>
  <div class="pdr pdred"><?= $ICON['miss'] ?><b><?= $h($u['title']) ?></b><small>Downloaded for signing on <?= $h($fd($u['downloaded_on'])) ?>. The signed copy has not been uploaded yet.</small><a href="<?= $h($u['upload_url']) ?>">Upload signed copy</a></div>
<?php endforeach; endif; ?>
<?php if ($j['required']): ?>
  <h3>Required documents</h3>
<?php foreach ($j['required'] as $m): $st = $m['status']; $cls = $st === 'ok' ? 'pdgreen' : ($st === 'waiting' ? 'pdgold' : 'pdred'); $ic = $st === 'ok' ? 'done' : ($st === 'waiting' ? 'wait' : 'miss'); ?>
  <div class="pdr <?= $cls ?>"><?= $ICON[$ic] ?><b><?= $h($m['label']) ?></b>
<?php if ($st === 'ok'): ?><small>On file in <?= $h($m['in']) ?><?= $m['page'] > 1 ? ', page ' . (int)$m['page'] : '' ?></small>
<?php elseif ($st === 'waiting'): ?><small>Sent for signature. Waiting for the signers.</small>
<?php elseif ($st === 'review'): ?><small style="color:#b71c1c;font-weight:700">Needs review: <?= $h($m['note']) ?></small>
<?php else: ?><small><?= $h($m['reason']) ?>. Not on file yet.</small><?php endif; ?>
<?php if ($m['view_url'] !== ''): ?><a href="<?= $h($m['view_url']) ?>" target="_blank" rel="noopener">View</a><a href="<?= $h($m['download_url']) ?>" target="_top">Download</a><?php endif; ?></div>
<?php endforeach; endif; ?>
<?php if (!empty($_GET['err'])): ?><p class="sigerr"><?= $h($_GET['err']) ?></p><?php endif; ?>
<?php if ($j['required'] && $j['count']): $opts = []; foreach ($j['groups'] as $g) foreach ($g['items'] as $it) $opts[] = $it; $need = array_filter($j['required'], function ($m) { return $m['status'] === 'missing' || $m['status'] === 'review'; }); if ($need && $opts): ?>
  <details class="pdr" style="display:block"><summary style="cursor:pointer;font-weight:700;color:#036f72">A required document is inside a signed document? Link it</summary>
  <form method="post" action="docs_link_api.php" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;align-items:center"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>"><input type="hidden" name="tx" value="<?= $h($tx) ?>"><input type="hidden" name="back" value="1">
    <select name="key" required><?php foreach ($need as $m): ?><option value="<?= $h($m['key']) ?>"><?= $h($m['label']) ?></option><?php endforeach; ?></select>
    <span>is in</span><select name="ref" required><?php foreach ($opts as $it): ?><option value="<?= $h($it['ref']) ?>"><?= $h($it['title']) ?></option><?php endforeach; ?></select>
    <label>starting on page <input type="number" name="page" value="1" min="1" max="999" style="width:70px"></label><button class="btn" type="submit">Link</button></form></details>
<?php endif; endif; ?>
<?php if ($j['pending']): ?>
  <h3 style="color:#8a5a00">Waiting for signatures</h3>
<?php foreach ($j['pending'] as $p): ?>
  <div class="pdr pdgold"><?= $ICON['wait'] ?><b><?= $h($p['title']) ?></b><small>Sent <?= $h($fd($p['sent'])) ?></small>
<?php foreach ($p['signers'] as $s): ?><small><?= $s['signed'] ? '&#10003; Signed' : 'Waiting' ?>: <?= $h($s['name'] !== '' ? $s['name'] . ' ' : '') ?><?= $h($s['email']) ?></small><?php endforeach; ?></div>
<?php endforeach; endif; ?>
<?php if (!$j['count']): ?>
  <p class="note">No signed documents yet. Signed buyer agreements, contracts, addenda and disclosures appear here as soon as they are executed.</p>
<?php endif; foreach ($j['groups'] as $g): ?>
  <h4 style="margin:12px 0 6px;font-size:.92rem;color:#036f72"><?= $h($g['label']) ?></h4>
<?php foreach ($g['items'] as $i): ?>
  <div class="pdr pdgreen"><?= $ICON['done'] ?><b><?= $h($i['title']) ?></b><small><?= $i['source'] === 'upload' ? 'Uploaded signed copy' : 'Signed by e-signature' ?><?= $i['signed_on'] !== '' ? ' &middot; ' . $h($fd($i['signed_on'])) : '' ?></small>
<?php if ($i['drive_url'] !== ''): ?><a href="<?= $h($i['drive_url']) ?>" target="_blank" rel="noopener">Open in Google Drive</a><?php endif; ?>
<?php if ($i['view_url'] !== ''): ?><a href="<?= $h($i['view_url']) ?>" target="_blank" rel="noopener">View</a><?php endif; ?>
<?php if ($i['download_url'] !== ''): ?><a href="<?= $h($i['download_url']) ?>" target="_top">Download</a><?php endif; ?>
<?php if ($i['folder'] !== ''): ?><small>Filed in <?= $h($i['folder']) ?></small><?php endif; ?>
<?php if ($i['note'] !== ''): ?><small><?= $h($i['note']) ?></small><?php endif; ?></div>
<?php endforeach; endforeach; ?>
<?php endif; ?>
</div>
<?php sp_foot(); ?>
