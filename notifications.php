<?php
// Notifications (bell on Home): everything that needs attention now, by section. See notiflib.php.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/notiflib.php";
require __DIR__ . "/subpage.php";
$me = strtolower($_SESSION['email'] ?? '');
$isStaff = flr_is_broker();
if (!empty($_GET['refresh']) && !$isStaff) { // re-read every record's details from Drive now
    try { require_once __DIR__ . '/pstage.php'; foreach (flr_tx_list($me) as $t) nt_info_forget($t['folder']); } catch (Throwable $e) {}
}
$sections = nt_sections($me, $isStaff, true);
$total = nt_count($sections);
sp_head('Notifications');
?>
<style>
.ntw{max-width:760px}
.ntsum{margin:0 0 16px;color:#455355}
.nts{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:14px 18px;margin:0 0 16px}
.nts h2{font-size:1.02rem;margin:0 0 6px;display:flex;align-items:center;gap:8px}
.ntn{display:inline-block;min-width:22px;height:22px;padding:0 7px;box-sizing:border-box;border-radius:99px;background:#c0392b;color:#fff;font:700 .75rem/22px system-ui,sans-serif;text-align:center}
.ntn.zero{background:#cfd8dc;color:#455355}
.nti{display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-top:1px solid var(--line);text-decoration:none;color:inherit}
a.nti:hover{background:#f6f9f9}
.ntdot{flex:0 0 10px;height:10px;border-radius:50%;margin-top:6px}.ntdot.red{background:#c0392b}.ntdot.amber{background:#d49a00}
.nti .t{font-weight:600}.nti .s{font-size:.88rem;color:#5a6a6c}.nti .go{margin-left:auto;color:var(--teal);font-weight:600;font-size:.88rem;white-space:nowrap;align-self:center}
.ntempty{color:#5a6a6c;font-size:.92rem;margin:4px 0 2px}
</style>
<div class="ntw">
  <p class="ntsum"><?= $total ? '<strong>' . $total . ' thing' . ($total === 1 ? '' : 's') . '</strong> need' . ($total === 1 ? 's' : '') . ' your attention.' : 'You\'re all caught up.' ?>
  <?php if (!$isStaff): ?> <a href="notifications.php?refresh=1" style="font-size:.88rem;margin-left:6px">Check again</a><?php endif; ?></p>
<?php foreach ($sections as $sec): $n = count($sec['items']); ?>
  <section class="nts" id="<?= htmlspecialchars($sec['key']) ?>">
    <h2><?= htmlspecialchars($sec['title']) ?> <span class="ntn<?= $n ? '' : ' zero' ?>"><?= $n ?></span></h2>
<?php if (!$n): ?>    <p class="ntempty"><?= htmlspecialchars($sec['empty']) ?></p>
<?php else: foreach ($sec['items'] as $it): $tag = $it['url'] !== '' ? 'a' : 'div'; ?>
    <<?= $tag ?> class="nti"<?= $it['url'] !== '' ? ' href="' . htmlspecialchars($it['url']) . '"' : '' ?>><span class="ntdot <?= $it['level'] ?>"></span><span><span class="t"><?= htmlspecialchars($it['title']) ?></span><br><span class="s"><?= htmlspecialchars($it['sub']) ?></span></span><?= $it['url'] !== '' ? '<span class="go">Open &rsaquo;</span>' : '' ?></<?= $tag ?>>
<?php endforeach; endif; ?>
  </section>
<?php endforeach; ?>
</div>
<?php sp_foot();
