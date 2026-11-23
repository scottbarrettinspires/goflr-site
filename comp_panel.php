<?php
// Shared staff-only agent income panel: plan terms, closed sides in a period, sponsees. Used by staff_income.php and the
// Agent Records page. cp_panel_css() once per page; cp_panel_body($a) renders one agent from cp_overview()['agents'].
function cp_panel_css() { return '<style>.sinot{color:#9a6b00;font-size:.82rem}.sitl{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:0 0 14px}.sitl div{background:#fff;border:1px solid var(--line);border-radius:12px;padding:10px 12px}.sitl b{display:block;font-size:1.2rem}.sitl span{font-size:.8rem;color:#455355}\n.sit{width:100%;border-collapse:collapse;font-size:.9rem}.sit th{text-align:left;border-bottom:2px solid var(--line);padding:6px 8px 6px 0}.sit td{padding:7px 8px 7px 0;border-bottom:1px solid var(--line);vertical-align:top}.sit td.n,.sit th.n{text-align:right}\n.siag{border:1px solid var(--line);border-radius:12px;margin:0 0 10px;background:#fff}.siag>summary{cursor:pointer;padding:10px 14px;display:flex;flex-wrap:wrap;gap:6px 18px;align-items:center}.siag>summary b{min-width:170px}.siag .in{padding:4px 14px 14px;border-top:1px solid var(--line)}\n.sig2{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:4px 16px}.sig2 label{display:block;font-size:.85rem;color:#455355;margin:8px 0}.sig2 input,.sig2 select,.sig2 textarea{width:100%;margin-top:3px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;font:inherit}.siag>summary{list-style:none}.siag>summary::-webkit-details-marker{display:none}.siag>summary>b::after{content:" \25BE";color:var(--teal);font-size:1.15em;margin-left:6px}.siag[open]>summary>b::after{content:" \25B4"}</style>'; }
function cp_panel_body($a, $recordLink = true, $part = 'all') { // $part: 'all' | 'plan' (header + plan terms) | 'income' (counts, closed sales, sponsees)
    $h = function ($x) { return htmlspecialchars((string)$x); };
    $m = function ($n) { return $n === null ? '<span class="sinot">not set</span>' : '$' . number_format((float)$n, 2); };
    $c = $a['plan']; $pc = function ($v) { return $v === null ? '&mdash;' : rtrim(rtrim(number_format($v, 2), '0'), '.') . '%'; }; $md = function ($v) { return $v === null ? '&mdash;' : '$' . number_format($v, 2); };
    ob_start(); ?>
    <?php if ($part !== 'income'): ?>
    <?php if ($recordLink): ?>
    <p style="font-size:.88rem;color:#455355;margin:8px 0"><?= $h($a['agent_id']) ?> &middot; <?= $h($a['status']) ?><?= $a['email'] ? ' &middot; ' . $h($a['email']) : '' ?>
      <?= $a['has_agent_record'] ? ($recordLink ? ' &middot; <a href="agentrecords.php?id=' . urlencode($a['agent_id']) . '">Agent record</a>' : '') : ' &middot; <span class="sinot">no Agent Record imported, so closed files can\'t be matched</span>' ?>
      <?= $a['sponsor'] ? ' &middot; sponsored by ' . $h($a['sponsor']['name']) : '' ?></p>
    <?php elseif ($a['sponsor']): ?><p style="font-size:.88rem;color:#455355;margin:8px 0">Sponsored by <?= $h($a['sponsor']['name']) ?></p><?php endif; ?>
    <?php if ($recordLink): ?>
    <h3 style="margin:10px 0 4px;font-size:1rem">Commission plan <span style="font-weight:400;font-size:.82rem;color:#455355">(from the Agent Compensation sheet)</span></h3>
    <?php endif; /* embedded: the dropdown title already says this */ ?>
    <?php if ($c['type'] === ''): ?><p class="sinot">No plan terms are entered for this agent on the sheet.</p><?php else: ?>
    <table class="sit"><?php if ($recordLink): ?><tr><th>Plan</th><td colspan="3"><?= $h($c['plan']) ?></td></tr><?php endif; ?>
      <tr><th>Standard residential</th><td><?= $h($c['type']) ?><?= $c['type'] === 'Flat fee' ? ': ' . $md($c['transaction_fee']) . ' per transaction, agent keeps the rest' : ': agent ' . $pc($c['res_agent_pct']) . ' / brokerage ' . $pc($c['res_brokerage_pct']) ?></td>
      <th>Luxury (at or above <?= $c['luxury_threshold'] === null ? '&mdash;' : '$' . number_format($c['luxury_threshold']) ?>)</th><td>agent <?= $pc($c['luxury_agent_pct']) ?> / brokerage <?= $c['luxury_brokerage_pct'] === null ? 'per standard terms' : $pc($c['luxury_brokerage_pct']) ?></td></tr>
      <tr><th>Commercial</th><td>agent <?= $pc($c['commercial_agent_pct']) ?> / brokerage <?= $pc($c['commercial_brokerage_pct']) ?></td>
      <th>Yearly cap (to brokerage)</th><td><?= $md($c['cap']) ?><?= $c['cap_this_year_sheet'] !== null && $c['cap_this_year_sheet'] != $c['cap'] ? ' (prorated ' . $md($c['cap_this_year_sheet']) . ')' : '' ?> &middot; paid this year (sheet) <?= $md($c['cap_paid_sheet']) ?><?= $c['cap_status'] ? ' &middot; ' . $h($c['cap_status']) : '' ?></td></tr>
      <tr><th>Benefits fee</th><td colspan="3"><?= $c['fee_text'] === '' ? '<span class="sinot">not set</span>' : $h($c['fee_text']) ?><?= $a['benefits_fees'] !== null ? ' &middot; ' . $md($a['benefits_fees']) . ' in this period' : '' ?></td></tr></table>
    <?php endif; ?>
    <?php endif; /* plan part */ ?>
    <?php if ($part !== 'plan'): ?>
    <div class="sitl" style="margin-top:10px"><div><b><?= (int)$a['buyer_sides'] + (int)$a['seller_sides'] ?></b><span>Transactions (<?= (int)$a['buyer_sides'] ?> buyer / <?= (int)$a['seller_sides'] ?> seller)</span></div><div><b>$<?= number_format($a['volume']) ?></b><span>Sales volume</span></div><div><b>$<?= number_format($a['gci']) ?></b><span>Gross commission</span></div><div><b><?= $a['company_income'] === null ? '<span class="sinot">not set</span>' : '$' . number_format($a['company_income'], 2) ?></b><span>Brokerage income</span></div><div><b><?= $a['sponsoring'] ? '$' . number_format($a['revshare_income'], 2) : '&mdash;' ?></b><span>Revenue share earned</span></div></div>
    <?php if ($a['sides_unknown_income']): ?><p class="sinot"><?= (int)$a['sides_unknown_income'] ?> closed side(s) can't be valued: the plan needs a gross commission and/or sold price on the file.</p><?php endif; ?>
    <h3 style="margin:14px 0 4px;font-size:1rem">Closed in this period</h3>
    <?php if ($a['closed']): ?><table class="sit"><tr><th>Closed</th><th>Side</th><th>Client</th><th class="n">Price</th><th class="n">Gross commission</th><th class="n">Brokerage</th></tr>
      <?php foreach ($a['closed'] as $r): ?><tr><td><?= $h($r['closed']) ?></td><td><?= $h(ucfirst($r['side'])) ?></td><td><?= $h($r['name']) ?></td><td class="n">$<?= number_format($r['price']) ?></td><td class="n">$<?= number_format($r['gci']) ?></td><td class="n"><?= $m($r['company']) ?></td></tr><?php endforeach; ?></table>
    <?php else: ?><p style="font-size:.9rem">No closed sides in this period.</p><?php endif; ?>
    <h3 style="margin:14px 0 4px;font-size:1rem">Sponsoring (revenue share)</h3>
    <?php if ($a['sponsoring']): ?><table class="sit"><tr><th>Sponsee</th><th>Status</th><th>Hired</th><th>Named in writing</th><th class="n">Closings this period</th><th class="n">Revenue share earned</th></tr>
      <?php foreach ($a['sponsoring'] as $sp): ?><tr><td><?= $h($sp['name']) ?></td><td><?= $h($sp['status']) ?></td><td><?= $h($sp['hired']) ?></td><td><?= $h($sp['named_in_writing']) ?></td><td class="n"><?= (int)$sp['closed_sides'] ?><?= $sp['not_processed'] ? ' <span class="sinot">(' . (int)$sp['not_processed'] . ' not processed)</span>' : '' ?></td><td class="n">$<?= number_format($sp['revshare_income'], 2) ?></td></tr><?php endforeach; ?></table>
    <?php else: ?><p style="font-size:.9rem;color:#455355">Not sponsoring anyone on the Revenue Share sheet.</p><?php endif; ?>
<?php endif; /* income part */ ?>
<?php return ob_get_clean();
}
