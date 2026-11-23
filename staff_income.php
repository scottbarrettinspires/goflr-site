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
$err = ''; $ok = !empty($_GET['saved']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $err = 'This page expired. Please reload and try again.';
    else { $err = cp_save(trim((string)($_POST['agent_id'] ?? '')), $_POST, $_SESSION['email']);
        if ($err === '') { header('Location: staff_income.php?period=' . urlencode($period) . '&status=' . urlencode($status) . '&saved=1#a-' . urlencode((string)$_POST['agent_id'])); exit; } }
}
$o = cp_overview($period, strtolower($status) === 'all' ? '' : $status); $t = $o['totals'];
$allAgents = []; foreach (ar_all() as $a) $allAgents[$a['agent_id']] = $a['preferred_name'] ?: $a['full_name'];
sp_head('Agent Income & Plans', '');
?>
<style>.sinot{color:#9a6b00;font-size:.82rem}.sitl{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:0 0 14px}.sitl div{background:#fff;border:1px solid var(--line);border-radius:12px;padding:10px 12px}.sitl b{display:block;font-size:1.2rem}.sitl span{font-size:.8rem;color:#455355}
.sit{width:100%;border-collapse:collapse;font-size:.9rem}.sit th{text-align:left;border-bottom:2px solid var(--line);padding:6px 8px 6px 0}.sit td{padding:7px 8px 7px 0;border-bottom:1px solid var(--line);vertical-align:top}.sit td.n,.sit th.n{text-align:right}
.siag{border:1px solid var(--line);border-radius:12px;margin:0 0 10px;background:#fff}.siag>summary{cursor:pointer;padding:10px 14px;display:flex;flex-wrap:wrap;gap:6px 18px;align-items:center}.siag>summary b{min-width:170px}.siag .in{padding:4px 14px 14px;border-top:1px solid var(--line)}
.sig2{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:4px 16px}.sig2 label{display:block;font-size:.85rem;color:#455355;margin:8px 0}.sig2 input,.sig2 select,.sig2 textarea{width:100%;margin-top:3px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;font:inherit}</style>
<p style="margin:0 0 12px;color:#455355;font-size:.92rem">Staff only. Closed sides come from each agent's closed files; the plan fields below are entered by staff and drive the brokerage income, benefits fee and revenue-share figures. Anything not entered shows as <span class="sinot">not set</span> rather than a guess.</p>
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
<?php foreach ($o['agents'] as $a): $c = $a['plan']; ?>
<details class="siag" id="a-<?= $h($a['agent_id']) ?>"<?= (($_GET['saved'] ?? '') && strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '#') !== false) ? ' open' : '' ?>>
  <summary><b><?= $h($a['name']) ?></b>
    <span><?= (int)$a['buyer_sides'] ?> buyer / <?= (int)$a['seller_sides'] ?> seller</span>
    <span>GCI $<?= number_format($a['gci']) ?></span>
    <span>Brokerage <?= $m($a['company_income']) ?></span>
    <span>Fee <?= $m($c['monthly_fee']) ?>/mo</span>
    <span>Rev share <?= $a['sponsoring'] ? '$' . number_format($a['revshare_income'], 2) . ($a['revshare_complete'] ? '' : ' (incomplete)') : '&mdash;' ?></span>
  </summary>
  <div class="in">
    <p style="font-size:.88rem;color:#455355;margin:8px 0"><?= $h($a['agent_id']) ?> &middot; <?= $h($a['status']) ?> &middot; <?= $h($a['email']) ?><?= $a['date_hired'] ? ' &middot; hired ' . $h($a['date_hired']) : '' ?><?= $a['office'] ? ' &middot; ' . $h($a['office']) : '' ?> &middot; <a href="agentrecords.php?id=<?= urlencode($a['agent_id']) ?>">Agent record</a>
      <?= $a['sponsor'] ? ' &middot; sponsored by ' . $h($a['sponsor']['name']) : '' ?></p>
    <?php if ($a['sides_missing_commission']): ?><p class="sinot"><?= (int)$a['sides_missing_commission'] ?> closed side(s) have no commission entered, so they add $0.</p><?php endif; ?>
    <?php if ($a['closed']): ?><table class="sit"><tr><th>Closed</th><th>Side</th><th>Client</th><th class="n">Price</th><th class="n">Gross commission</th><th class="n">Brokerage</th></tr>
      <?php foreach ($a['closed'] as $r): ?><tr><td><?= $h($r['closed']) ?></td><td><?= $h(ucfirst($r['side'])) ?></td><td><?= $h($r['name']) ?></td><td class="n">$<?= number_format($r['price']) ?></td><td class="n">$<?= number_format($r['gci']) ?></td><td class="n"><?= $m($r['company']) ?></td></tr><?php endforeach; ?></table>
    <?php else: ?><p style="font-size:.9rem">No closed sides in this period.</p><?php endif; ?>
    <?php if ($a['sponsoring']): ?><h3 style="margin:14px 0 4px;font-size:1rem">Sponsoring (revenue share)</h3><table class="sit"><tr><th>Agent</th><th>Status</th><th class="n">Closed sides</th><th class="n">Rev share %</th><th class="n">Income to <?= $h($a['name']) ?></th></tr>
      <?php foreach ($a['sponsoring'] as $sp): ?><tr><td><?= $h($sp['name']) ?></td><td><?= $h($sp['status']) ?></td><td class="n"><?= (int)$sp['closed_sides'] ?></td><td class="n"><?= $sp['revshare_pct'] === null ? '<span class="sinot">not set</span>' : rtrim(rtrim(number_format($sp['revshare_pct'], 2), '0'), '.') . '%' ?></td><td class="n"><?= $m($sp['revshare_income']) ?></td></tr><?php endforeach; ?></table>
    <?php else: ?><p style="font-size:.9rem;color:#455355">Not sponsoring anyone (set a sponsor on another agent's plan below to link them).</p><?php endif; ?>
    <form method="post" style="margin-top:12px"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="agent_id" value="<?= $h($a['agent_id']) ?>">
      <h3 style="margin:6px 0 0;font-size:1rem">Commission plan</h3>
      <div class="sig2">
        <label>Plan name<input name="plan_name" value="<?= $h($c['plan_name']) ?>"></label>
        <label>Agent split % (agent keeps)<input name="agent_split_pct" inputmode="decimal" value="<?= $c['agent_split_pct'] === null ? '' : $h($c['agent_split_pct']) ?>" placeholder="e.g. 80"></label>
        <label>Yearly cap on brokerage dollars ($, blank = none)<input name="cap_amount" inputmode="numeric" value="<?= $c['cap_amount'] ?: '' ?>"></label>
        <label>Monthly benefits fee ($)<input name="monthly_fee" inputmode="decimal" value="<?= $c['monthly_fee'] === null ? '' : $h($c['monthly_fee']) ?>"></label>
        <label>Sponsored by<select name="sponsor_agent_id"><option value="">No sponsor</option><?php foreach ($allAgents as $id => $nm): if ($id === $a['agent_id']) continue; ?><option value="<?= $h($id) ?>"<?= $id === $c['sponsor_agent_id'] ? ' selected' : '' ?>><?= $h($nm) ?> (<?= $h($id) ?>)</option><?php endforeach; ?></select></label>
        <label>Revenue share % of this agent's brokerage income, paid to sponsor<input name="revshare_pct" inputmode="decimal" value="<?= $c['revshare_pct'] === null ? '' : $h($c['revshare_pct']) ?>"></label>
      </div>
      <label style="display:block;font-size:.85rem;color:#455355;margin:6px 0">Notes<textarea name="notes" rows="2" style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px;font:inherit"><?= $h($c['notes']) ?></textarea></label>
      <button class="btn" type="submit">Save plan</button><?php if ($c['updated']): ?> <span style="font-size:.8rem;color:#455355">Last saved <?= $h(substr($c['updated'], 0, 10)) ?> by <?= $h($c['updated_by']) ?></span><?php endif; ?>
    </form>
  </div>
</details>
<?php endforeach; ?>
<?php if (!$o['agents']): ?><p>No agents to show. Import Agent Records first (Settings &gt; Agent Records).</p><?php endif; ?>
<p style="font-size:.82rem;color:#455355;margin-top:14px"><?php foreach ($o['notes'] as $n) echo $h($n) . '<br>'; ?></p>
<?php sp_foot();
