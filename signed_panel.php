<?php
// Signed Documents inside a buyer or seller record: every form sent for signature that belongs to this record,
// with its status, the signed copy and where it was filed. Opening the record clears its "newly signed" notices
// on the Notifications page (bell). signed.php still lists everything and is where unassigned documents get filed.
require_once __DIR__ . '/sigtools.php';

function sg_norm_addr($s) { return preg_replace('/[^a-z0-9]/', '', strtolower((string)$s)); }
// Does this signature request belong to the record folder $folder (named $name)?
function sg_belongs($r, $folder, $name) {
    if ($folder !== '' && (($r['folder']['id'] ?? '') === $folder || ($r['hint'] ?? '') === $folder)) return true;
    $a = sg_norm_addr($r['offer_address'] ?? ''); $n = sg_norm_addr($name);
    return $a !== '' && strlen($n) >= 6 && strlen($a) >= 6 && (strpos($n, $a) === 0 || strpos($a, $n) === 0);
}
function sg_record_docs($email, $folder, $name) {
    return array_values(array_filter(sg_for_agent($email), function ($r) use ($folder, $name) { return sg_belongs($r, $folder, $name); }));
}
// Which record a request belongs to: ['url' => ..., 'name' => ...] or null. $txs = flr_tx_list() rows.
function sg_record_for($r, array $txs) {
    foreach ($txs as $t) {
        if (!in_array($t['side'], ['buyer', 'seller'], true)) continue;
        if (sg_belongs($r, (string)$t['folder'], (string)$t['name'])) return ['url' => ($t['side'] === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . urlencode($t['folder']) . '#signed', 'name' => (string)$t['name']];
    }
    return null;
}
function sg_panel($email, $folder, $name) {
    $recs = sg_record_docs($email, $folder, $name);
    sg_refresh_many($recs, 4);
    $pending = false;
    foreach ($recs as $r) { // the agent has now seen these: clear their notices
        if (!empty($r['completed']) && empty($r['seen'])) { $r['seen'] = true; sg_save($r['id'], $r); }
        $p = empty($r['filed']); foreach (($r['extra'] ?? []) as $x) { if (empty($x['filed'])) $p = true; }
        if (!empty($r['completed']) && $p && sg_can_file($r)) $pending = true;
    }
    ob_start(); ?>
  <div class="panel sigbox" id="signed">
    <h2 style="margin-top:0">Signed Documents</h2>
<?php if (!$recs): ?>
    <p class="note">Nothing sent for signature for this record yet. Forms you send from the Contract Forms Wizard show up here, and you get a notice (bell) when they're fully signed.</p>
<?php endif; ?>
<?php foreach ($recs as $r):
    $who = []; foreach ($r['signers'] ?? [] as $s) { $who[] = htmlspecialchars($s['email']) . (!empty($r['signers_status'][$s['email']]) ? ' &#10003;' : ''); }
    $t = strtotime($r['created'] ?? ''); $ct = strtotime($r['completed_at'] ?? ''); ?>
    <div style="border-top:1px solid var(--line);padding:10px 0">
      <strong><?= htmlspecialchars($r['form'] ?? 'Document') ?></strong>
      <?= empty($r['completed']) ? '<span style="font-size:.78rem;font-weight:700;background:#fdf3dc;color:#8a5a0e;border-radius:99px;padding:2px 9px;margin-left:6px">Waiting for signatures</span>' : '<span style="font-size:.78rem;font-weight:700;background:#e3f4e8;color:#2e7d32;border-radius:99px;padding:2px 9px;margin-left:6px">Fully signed</span>' ?>
      <div class="note" style="margin:4px 0">Sent <?= $t ? date('M j, Y', $t) : '' ?><?= $ct ? ' &middot; signed ' . date('M j, Y', $ct) : '' ?> &middot; <?= implode(', ', $who) ?></div>
<?php if (!empty($r['completed'])): ?>
      <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <a class="btn" href="signed.php?dl=<?= urlencode($r['id']) ?>">Download signed copy</a>
<?php if (!empty($r['filed'])): ?>
        <a class="btn ghost" href="<?= htmlspecialchars($r['filed']['url']) ?>" target="_blank" rel="noopener">Open filed copy</a>
<?php foreach (($r['extra'] ?? []) as $x): if (!empty($x['filed'])): ?>
        <a class="btn ghost" href="<?= htmlspecialchars($x['filed']['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($x['filed']['name']) ?></a>
<?php endif; endforeach; ?>
      </div>
      <div class="note" style="margin-top:4px">Filed in <?= htmlspecialchars($r['filed']['folder']) ?></div>
<?php elseif (sg_can_file($r)): ?>
      </div>
      <div class="note" style="margin-top:4px">Filing the signed documents in this record&hellip;</div>
<?php else: ?>
        <a class="btn ghost" href="signed.php?assign=<?= urlencode($r['id']) ?>">File it in a record</a>
      </div>
<?php endif; ?>
<?php endif; ?>
    </div>
<?php endforeach; ?>
  </div>
<?php if ($pending): ?>
<script>(async function(){try{var r=await fetch("file_pending.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:<?= json_encode($_SESSION['csrf'] ?? '') ?>})});var j=await r.json();if(j.ok&&j.filed>0&&!(j.errors||[]).length)location.reload();}catch(e){}})();</script>
<?php endif;
    return ob_get_clean();
}
