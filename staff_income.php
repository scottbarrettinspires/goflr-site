<?php
// Staff-only: every agent's record, commission plan, closed sides (buyer/seller), brokerage income, monthly benefits fee,
// sponsorship (revenue share) and revenue-share income. All logic lives in complib.php (also served by comp_api.php).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/complib.php";
if (!flr_is_broker()) { http_response_code(403); exit('Staff only.'); }
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$h = function ($x) { return htmlspecialchars((string)$x); };
$m = function ($n) { return $n === null ? '<span class="sinot">not set</span>' : '$' . number_format((float)$n, 2); };
$period = (string)($_GET['period'] ?? 'ytd'); if (!in_array($period, ['month', 'quarter', 'ytd', 'all'], true) && !preg_match('/^\d{4}$/', $period)) $period = 'ytd';
$status = (string)($_GET['status'] ?? 'Active');
$err = ''; $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $err = 'This page expired. Please reload and try again.';
    else { $up = $_FILES['pull'] ?? null;
        if (!$up || ($up['error'] ?? 1) !== UPLOAD_ERR_OK || ($up['size'] ?? 0) > 5000000) $err = 'Choose the finance data file (.json) to import.';
        else { $r = fm_import(json_decode((string)file_get_contents($up['tmp_name']), true), $_SESSION['email']);
            if (isset($r['error'])) $err = $r['error']; else { $ok = 'Imported: ' . implode(', ', array_map(function ($t, $n) { return $t . ' (' . $n . ' rows)'; }, array_keys($r['tabs']), $r['tabs'])) . '.'; } } }
}
$o = cp_overview($period, strtolower($status) === 'all' ? '' : $status); $t = $o['totals'];
$imp = $o['imported'];
sp_head('Agent Income & Plans', '');
?>
<style>.sinot{color:#9a6b00;font-size:.82rem}.sitl{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:0 0 14px}.sitl div{background:#fff;border:1px solid var(--line);border-radius:12px;padding:10px 12px}.sitl b{display:block;font-size:1.2rem}.sitl span{font-size:.8rem;color:#455355}
.sit{width:100%;border-collapse:collapse;font-size:.9rem}.sit th{text-align:left;border-bottom:2px solid var(--line);padding:6px 8px 6px 0}.sit td{padding:7px 8px 7px 0;border-bottom:1px solid var(--line);vertical-align:top}.sit td.n,.sit th.n{text-align:right}
.siag{border:1px solid var(--line);border-radius:12px;margin:0 0 10px;background:#fff}.siag>summary{cursor:pointer;padding:10px 14px;display:flex;flex-wrap:wrap;gap:6px 18px;align-items:center}.siag>summary b{min-width:170px}.siag .in{padding:4px 14px 14px;border-top:1px solid var(--line)}
.sig2{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:4px 16px}.sig2 label{display:block;font-size:.85rem;color:#455355;margin:8px 0}.sig2 input,.sig2 select,.sig2 textarea{width:100%;margin-top:3px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;font:inherit}</style>
<p style="margin:0 0 12px;color:#455355;font-size:.92rem">Staff only. Plan terms, sponsors and revenue share are a copy of the Agent Compensation and FLR Revenue Share sheets<?= $imp ? ' (imported ' . $h(substr(max($imp), 0, 10)) . ')' : ' (not imported yet)' ?>; closed sides come from the closed files on this site. Anything that can't be worked out shows as not set rather than a guess.</p>
<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 14px">
  <select name="period" onchange="this.form.submit()"><?php foreach (['month' => 'This month', 'quarter' => 'This quarter', 'ytd' => date('Y') . ' year to date', (string)(date('Y') - 1) => (string)(date('Y') - 1), 'all' => 'All time'] as $k => $l): ?><option value="<?= $h($k) ?>"<?= (string)$k === $period ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
  <select name="status" onchange="this.form.submit()"><?php foreach (['Active', 'Inactive', 'all'] as $s): ?><option<?= strtolower($s) === strtolower($status) ? ' selected' : '' ?>><?= $h($s) ?></option><?php endforeach; ?></select>
  <a class="btn" href="comp_api.php?action=overview&period=<?= $h($period) ?>&status=<?= $h($status) ?>" target="_blank">JSON</a>
</form>
<?php if ($ok): ?><p class="note">Saved.</p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= $h($err) ?></p><?php endif; ?>
<div class="sitl">
  <div><b><?= (int)$t['buyer_sides'] ?> / <?= (int)$t['seller_sides'] ?></b><span>Closed buyer / seller sides</span></div>
  <div><b>$<?= number_format($t['volume']) ?></b><span>Sales volume</span></div>
  <div><b>$<?= number_format($t['gci']) ?></b><span>Gross commission</span></div>
  <div><b>$<?= number_format($t['company'], 2) ?></b><span>Brokerage income (agents with a split set)</span></div>
  <div><b>$<?= number_format($t['fees'], 2) ?></b><span>Monthly benefits fees in period</span></div>
  <div><b>$<?= number_format($t['revshare'], 2) ?></b><span>Revenue share earned</span></div>
</div>
<?php foreach ($o['agents'] as $a): $c = $a['plan']; $pc = function ($v) { return $v === null ? '&mdash;' : rtrim(rtrim(number_format($v, 2), '0'), '.') . '%'; }; $md = function ($v) { return $v === null ? '&mdash;' : '$' . number_format($v, 2); }; ?>
<details class="siag" id="a-<?= $h($a['agent_id']) ?>">
  <summary><b><?= $h($a['name']) ?></b>
    <span><?= (int)$a['buyer_sides'] ?> buyer / <?= (int)$a['seller_sides'] ?> seller</span>
    <span>GCI $<?= number_format($a['gci']) ?></span>
    <span>Brokerage <?= $m($a['company_income']) ?></span>
    <span>Fee <?= $c['fee_text'] === '' ? '<span class="sinot">not set</span>' : $h($c['fee_is_annual'] ? $c['fee_text'] : '$' . number_format($c['monthly_fee'], 2) . '/mo') ?></span>
    <span>Rev share <?= $a['sponsoring'] ? '$' . number_format($a['revshare_income'], 2) : '&mdash;' ?></span>
  </summary>
  <div class="in">
    <p style="font-size:.88rem;color:#455355;margin:8px 0"><?= $h($a['agent_id']) ?> &middot; <?= $h($a['status']) ?><?= $a['email'] ? ' &middot; ' . $h($a['email']) : '' ?>
      <?= $a['has_agent_record'] ? ' &middot; <a href="agentrecords.php?id=' . urlencode($a['agent_id']) . '">Agent record</a>' : ' &middot; <span class="sinot">no Agent Record imported, so closed files can\'t be matched</span>' ?>
      <?= $a['sponsor'] ? ' &middot; sponsored by ' . $h($a['sponsor']['name']) : '' ?></p>
    <h3 style="margin:10px 0 4px;font-size:1rem">Commission plan <span style="font-weight:400;font-size:.82rem;color:#455355">(from the Agent Compensation sheet)</span></h3>
    <?php if ($c['type'] === ''): ?><p class="sinot">No plan terms are entered for this agent on the sheet.</p><?php else: ?>
    <table class="sit"><tr><th>Plan</th><td colspan="3"><?= $h($c['plan']) ?></td></tr>
      <tr><th>Standard residential</th><td><?= $h($c['type']) ?><?= $c['type'] === 'Flat fee' ? ': ' . $md($c['transaction_fee']) . ' per transaction, agent keeps the rest' : ': agent ' . $pc($c['res_agent_pct']) . ' / brokerage ' . $pc($c['res_brokerage_pct']) ?></td>
      <th>Luxury (at or above <?= $c['luxury_threshold'] === null ? '&mdash;' : '$' . number_format($c['luxury_threshold']) ?>)</th><td>agent <?= $pc($c['luxury_agent_pct']) ?> / brokerage <?= $c['luxury_brokerage_pct'] === null ? 'per standard terms' : $pc($c['luxury_brokerage_pct']) ?></td></tr>
      <tr><th>Commercial</th><td>agent <?= $pc($c['commercial_agent_pct']) ?> / brokerage <?= $pc($c['commercial_brokerage_pct']) ?></td>
      <th>Yearly cap (to brokerage)</th><td><?= $md($c['cap']) ?><?= $c['cap_this_year_sheet'] !== null && $c['cap_this_year_sheet'] != $c['cap'] ? ' (prorated ' . $md($c['cap_this_year_sheet']) . ')' : '' ?> &middot; paid this year (sheet) <?= $md($c['cap_paid_sheet']) ?><?= $c['cap_status'] ? ' &middot; ' . $h($c['cap_status']) : '' ?></td></tr>
      <tr><th>Benefits fee</th><td colspan="3"><?= $c['fee_text'] === '' ? '<span class="sinot">not set</span>' : $h($c['fee_text']) ?><?= $a['benefits_fees'] !== null ? ' &middot; ' . $md($a['benefits_fees']) . ' in this period' : '' ?></td></tr></table>
    <?php endif; ?>
    <?php if ($a['sides_unknown_income']): ?><p class="sinot"><?= (int)$a['sides_unknown_income'] ?> closed side(s) can't be valued: the plan needs a gross commission and/or sold price on the file.</p><?php endif; ?>
    <h3 style="margin:14px 0 4px;font-size:1rem">Closed in this period</h3>
    <?php if ($a['closed']): ?><table class="sit"><tr><th>Closed</th><th>Side</th><th>Client</th><th class="n">Price</th><th class="n">Gross commission</th><th class="n">Brokerage</th></tr>
      <?php foreach ($a['closed'] as $r): ?><tr><td><?= $h($r['closed']) ?></td><td><?= $h(ucfirst($r['side'])) ?></td><td><?= $h($r['name']) ?></td><td class="n">$<?= number_format($r['price']) ?></td><td class="n">$<?= number_format($r['gci']) ?></td><td class="n"><?= $m($r['company']) ?></td></tr><?php endforeach; ?></table>
    <?php else: ?><p style="font-size:.9rem">No closed sides in this period.</p><?php endif; ?>
    <h3 style="margin:14px 0 4px;font-size:1rem">Sponsoring (revenue share)</h3>
    <?php if ($a['sponsoring']): ?><table class="sit"><tr><th>Sponsee</th><th>Status</th><th>Hired</th><th>Named in writing</th><th class="n">Closings this period</th><th class="n">Revenue share earned</th></tr>
      <?php foreach ($a['sponsoring'] as $sp): ?><tr><td><?= $h($sp['name']) ?></td><td><?= $h($sp['status']) ?></td><td><?= $h($sp['hired']) ?></td><td><?= $h($sp['named_in_writing']) ?></td><td class="n"><?= (int)$sp['closed_sides'] ?><?= $sp['not_processed'] ? ' <span class="sinot">(' . (int)$sp['not_processed'] . ' not processed)</span>' : '' ?></td><td class="n">$<?= number_format($sp['revshare_income'], 2) ?></td></tr><?php endforeach; ?></table>
    <?php else: ?><p style="font-size:.9rem;color:#455355">Not sponsoring anyone on the Revenue Share sheet.</p><?php endif; ?>
  </div>
</details>
<?php endforeach; ?>
<?php if (!$o['agents']): ?><p>No agents to show yet. Import the finance data below (and Agent Records, so closed files can be matched to agents).</p><?php endif; ?>
<details class="siag" style="margin-top:16px"><summary><b>Import finance sheets</b></summary><div class="in"><p style="font-size:.9rem;color:#455355">Upload the finance data file Claude prepares from the Agent Compensation and FLR Revenue Share sheets. Each tab in the file replaces the stored copy of that tab; tabs not in the file are kept.</p>
  <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="file" name="pull" accept=".json,application/json" required> <button class="btn" type="submit">Import</button></form></div></details>
<p style="font-size:.82rem;color:#455355;margin-top:14px"><?php foreach ($o['notes'] as $n) echo $h($n) . '<br>'; ?></p>
<?php sp_foot();
