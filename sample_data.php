<?php
// Settings > Sample data (staff only): load realistic agent sample data into your own account, so you can see what
// agents will see as features are built; clear every sample item, for everyone, before the official launch. See samplelib.php.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/samplelib.php";
require __DIR__ . "/subpage.php";
if (!flr_is_broker()) { http_response_code(403); exit('Staff only.'); }
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? ''); $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $err = 'This page expired. Please reload and try again.';
    else {
        try {
            if (($_POST['a'] ?? '') === 'load') { sd_load($me); unset($_SESSION['ntc']); header('Location: sample_data.php?ok=load'); exit; }
            if (($_POST['a'] ?? '') === 'clear') { sd_clear(); unset($_SESSION['ntc']); header('Location: sample_data.php?ok=clear'); exit; }
        } catch (Throwable $e) { $err = 'Something went wrong: ' . $e->getMessage(); }
    }
}
$c = sd_counts(); $total = array_sum($c); $mine = sd_loaded($me); $who = sd_emails();
$csrfIn = '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf']) . '">';
sp_head('Sample data');
?>
<style>.sdw{max-width:760px}.sdc{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:18px 20px;margin:0 0 16px}.sdc h2{font-size:1.05rem;margin:0 0 6px}.sdc p{color:#455355;margin:0 0 10px}
.sdc ul{margin:0 0 12px;padding-left:18px;color:#2a3638;line-height:1.6;font-size:.92rem}.sdc .btn{border:0;cursor:pointer;font:inherit;font-weight:600}.sdc .btn.del{background:#c0392b}
.sdn{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}.sdn span{background:#eef3f3;border-radius:99px;padding:4px 11px;font-size:.85rem}</style>
<div class="sdw">
<?php if ($err): ?>  <p class="sigerr"><?= htmlspecialchars($err) ?></p>
<?php elseif (($_GET['ok'] ?? '') === 'load'): ?>  <p class="note">Sample data loaded into your account. Switch Home to <a href="home.php?view=agent">Agent View</a> to see it the way agents will.</p>
<?php elseif (($_GET['ok'] ?? '') === 'clear'): ?>  <p class="note">All sample data has been cleared.</p>
<?php endif; ?>
  <div class="sdc">
    <h2>Sample data in your account</h2>
    <p>Fills <strong><?= htmlspecialchars($me) ?></strong> with realistic agent data so you can see every screen with something in it. Turn on <strong>Agent View</strong> on Home to get the agent menu (Prospecting, Leads &amp; Contacts, Active Business).</p>
    <ul>
      <li>12 buyer and seller records across every pipeline stage, with client names, phones and addresses</li>
      <li>Contract deadlines of every kind: escrow deposits, loan application, inspection, appraisal, loan approval, title, HOA documents and closing, landing overdue, today, this week, on a weekend or holiday, and later</li>
      <li>15 leads from every prospecting source, in every category (New, Buyers, Sellers, Sphere), some linked to the records</li>
      <li>Prospecting activity and weekly goals</li>
      <li>Signed documents: one newly signed, one waiting for signatures</li>
    </ul>
    <p style="font-size:.85rem">Deadlines are set relative to the day you load, so reload any time to see a fresh week. Reloading replaces your sample data; your real data isn't touched.</p>
    <form method="post"><?= $csrfIn ?><input type="hidden" name="a" value="load"><button class="btn" type="submit"><?= $mine ? 'Reload sample data' : 'Load sample data' ?></button>
      <?php if ($mine): ?> <a href="home.php?view=agent" style="margin-left:10px">Open Agent View &rarr;</a><?php endif; ?></form>
  </div>
  <div class="sdc">
    <h2>Clear all sample data</h2>
    <p>Before the official launch, this removes every sample item for everyone<?= $who ? ' (loaded for ' . htmlspecialchars(implode(', ', $who)) . ')' : '' ?>. Real records, leads and documents are never touched; sample items are marked SAMPLE / smp.</p>
    <div class="sdn"><?php foreach ($c as $k => $n): ?><span><?= (int)$n ?> <?= htmlspecialchars($k) ?></span><?php endforeach; ?></div>
    <form method="post" onsubmit="return confirm('Remove ALL sample data for everyone? This cannot be undone.')"><?= $csrfIn ?><input type="hidden" name="a" value="clear"><button class="btn del" type="submit"<?= $total ? '' : ' disabled' ?>>Clear all sample data</button></form>
  </div>
</div>
<?php sp_foot();
