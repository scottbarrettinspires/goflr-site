<?php
// Staff-only: every agent's record, commission plan, closed sides (buyer/seller), brokerage income, monthly benefits fee,
// sponsorship (revenue share) and revenue-share income. All logic lives in complib.php (also served by comp_api.php).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/complib.php";
require_once __DIR__ . "/comp_panel.php";
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
$focusId = trim((string)($_GET['agent'] ?? '')); $focusEmail = strtolower(trim((string)($_GET['email'] ?? '')));
if ($focusId === '' && $focusEmail !== '') { $q = ar_db()->prepare('SELECT agent_id FROM agent_records WHERE email = ?'); $q->execute([$focusEmail]); $focusId = (string)$q->fetchColumn(); }
$o = cp_overview($period, ($focusId !== '' || $focusEmail !== '' || strtolower($status) === 'all') ? '' : $status); $t = $o['totals'];
$focusMiss = false;
if ($focusId !== '') $o['agents'] = array_values(array_filter($o['agents'], function ($a) use ($focusId) { return $a['agent_id'] === $focusId; }));
elseif ($focusEmail !== '') { $o['agents'] = []; $focusMiss = true; }
$imp = $o['imported'];
sp_head('Agent Income & Plans', '');
?>
<?= cp_panel_css() ?>
<p style="margin:0 0 12px;color:#455355;font-size:.92rem">Staff only. Plan terms, sponsors and revenue share are a copy of the Agent Compensation and FLR Revenue Share sheets<?= $imp ? ' (imported ' . $h(substr(max($imp), 0, 10)) . ')' : ' (not imported yet)' ?>; closed sides come from the closed files on this site. Anything that can't be worked out shows as not set rather than a guess.</p>
<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 14px"><?php if ($focusId !== ''): ?><input type="hidden" name="agent" value="<?= $h($focusId) ?>"><?php endif; ?>
  <select name="period" onchange="this.form.submit()"><?php foreach (['month' => 'This month', 'quarter' => 'This quarter', 'ytd' => date('Y') . ' year to date', (string)(date('Y') - 1) => (string)(date('Y') - 1), 'all' => 'All time'] as $k => $l): ?><option value="<?= $h($k) ?>"<?= (string)$k === $period ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
  <select name="status" onchange="this.form.submit()"><?php foreach (['Active', 'Inactive', 'all'] as $s): ?><option<?= strtolower($s) === strtolower($status) ? ' selected' : '' ?>><?= $h($s) ?></option><?php endforeach; ?></select>
  <a class="btn" href="comp_api.php?action=overview&period=<?= $h($period) ?>&status=<?= $h($status) ?>" target="_blank">JSON</a>
</form>
<?php if ($focusId !== '' || $focusMiss): ?><p><a href="staff_income.php?period=<?= $h($period) ?>">&larr; All agents</a></p><?php endif; ?>
<?php if ($focusMiss): ?><p class="note">No Agent Record on the server matches that email, so this agent can't be looked up yet. Import Agent Records (Settings &gt; Agent Records).</p><?php endif; ?>
<?php if ($ok): ?><p class="note">Saved.</p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= $h($err) ?></p><?php endif; ?>
<?php if ($focusId === '' && !$focusMiss): ?><div class="sitl">
  <div><b><?= (int)$t['buyer_sides'] ?> / <?= (int)$t['seller_sides'] ?></b><span>Closed buyer / seller sides</span></div>
  <div><b>$<?= number_format($t['volume']) ?></b><span>Sales volume</span></div>
  <div><b>$<?= number_format($t['gci']) ?></b><span>Gross commission</span></div>
  <div><b>$<?= number_format($t['company'], 2) ?></b><span>Brokerage income (agents with a split set)</span></div>
  <div><b>$<?= number_format($t['fees'], 2) ?></b><span>Monthly benefits fees in period</span></div>
  <div><b>$<?= number_format($t['revshare'], 2) ?></b><span>Revenue share earned</span></div>
</div><?php endif; ?>
<?php foreach ($o['agents'] as $a): $c = $a['plan']; $pc = function ($v) { return $v === null ? '&mdash;' : rtrim(rtrim(number_format($v, 2), '0'), '.') . '%'; }; $md = function ($v) { return $v === null ? '&mdash;' : '$' . number_format($v, 2); }; ?>
<details class="siag" id="a-<?= $h($a['agent_id']) ?>"<?= $focusId !== '' ? ' open' : '' ?>>
  <summary><b><?= $h($a['name']) ?></b>
    <span><?= (int)$a['buyer_sides'] ?> buyer / <?= (int)$a['seller_sides'] ?> seller</span>
    <span>GCI $<?= number_format($a['gci']) ?></span>
    <span>Brokerage <?= $m($a['company_income']) ?></span>
    <span>Fee <?= $c['fee_text'] === '' ? '<span class="sinot">not set</span>' : $h($c['fee_is_annual'] ? $c['fee_text'] : '$' . number_format($c['monthly_fee'], 2) . '/mo') ?></span>
    <span>Rev share <?= $a['sponsoring'] ? '$' . number_format($a['revshare_income'], 2) : '&mdash;' ?></span>
  </summary>
  <div class="in">
<?= cp_panel_body($a) ?>
  </div>
</details>
<?php endforeach; ?>
<?php if (!$o['agents'] && !$focusMiss): ?><p>No agents to show yet. Import the finance data below (and Agent Records, so closed files can be matched to agents).</p><?php endif; ?>
<details class="siag" style="margin-top:16px"><summary><b>Import finance sheets</b></summary><div class="in"><p style="font-size:.9rem;color:#455355">Upload the finance data file Claude prepares from the Agent Compensation and FLR Revenue Share sheets. Each tab in the file replaces the stored copy of that tab; tabs not in the file are kept.</p>
  <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="file" name="pull" accept=".json,application/json" required> <button class="btn" type="submit">Import</button></form></div></details>
<p style="font-size:.82rem;color:#455355;margin-top:14px"><?php foreach ($o['notes'] as $n) echo $h($n) . '<br>'; ?></p>
<?php sp_foot();
