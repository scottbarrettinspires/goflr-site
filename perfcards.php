<?php
// Shared Performance cards (used on Performance and on the Active Business overview): the Active Business pipeline card
// (active buyers/sellers, pending sales, anticipated GCI and $ volume, pending-by-closing-month chart, pending rows with
// price and commission editors), plus the CSS/JS both pages need. Data comes from ablib.php.
require_once __DIR__ . '/ablib.php';
function pf_money($n) { return '$' . number_format((int)$n); }
function pf_price_ui($r, $addLbl = '+ Sold price', $ttl = 'sold price', $sec = 'closed') { $money = 'pf_money'; $h = function ($x) { return htmlspecialchars((string)$x); };
    $g = ab_gci($r); $c = $r['comm'] ?? ['pct' => 0, 'amt' => 0]; $cv = $c['amt'] > 0 ? '$' . number_format($c['amt']) : ($c['pct'] > 0 ? rtrim(rtrim(number_format($c['pct'], 3), '0'), '.') . '%' : '');
    $shown = $r['price'] || $g;
    return '<form method="post" class="qa prx" onclick="event.stopPropagation()"><input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="op" value="setprice"><input type="hidden" name="sec" value="' . $h($sec) . '"><input type="hidden" name="tx" value="' . $h($r['t']['folder']) . '">'
        . ($shown ? '<span class="prv">' . ($r['price'] ? $money($r['price']) : '<span class="pmiss">No price</span>') . '<small>' . ($g ? 'GCI ' . $money($g) . ($c['pct'] > 0 && !$c['amt'] ? ' (' . $h($cv) . ')' : '') : '<span class="pmiss">GCI not set</span>') . '</small></span><button type="button" class="pre" title="Edit ' . $ttl . ' and commission" aria-label="Edit ' . $ttl . ' and commission">&#9998;</button>' : '<button type="button" class="pre add">' . $addLbl . ' &amp; GCI</button>')
        . '<span class="prf" hidden><label>' . ucfirst($ttl) . '<input name="price" inputmode="numeric" placeholder="425000" value="' . ($r['price'] ?: '') . '"></label><label>Gross commission<input name="comm" placeholder="3% or $12,750" value="' . $h($cv) . '"></label><button type="submit">Save</button></span></form>'; }

// POST op=setprice from a row editor: saves price and/or gross commission, then reloads $self at the card it came from.
// Returns '' (or redirects) or an error message to show.
function pf_handle_post($me, $self) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['op'] ?? '') !== 'setprice') return '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) return 'This page expired. Please reload and try again.';
    $tx0 = (string)($_POST['tx'] ?? ''); $pv = trim((string)($_POST['price'] ?? ''));
    $err = $pv === '' ? '' : ab_set_price($me, $tx0, $pv);
    if ($err === '' && isset($_POST['comm'])) $err = ab_set_comm($me, $tx0, (string)$_POST['comm']);
    if ($err === '') { header('Location: ' . $self . '#' . (($_POST['sec'] ?? '') === 'active' ? 'active' : 'closed')); exit; }
    return $err;
}

function pf_active_html($me, $note = 'right now') {
    $money = 'pf_money';
// Active Business snapshot (today's pipeline; the period menu doesn't change it).
$act = ab_active($me); $at = ab_active_totals($act);
$pend = []; foreach (['buyer', 'seller'] as $sd) foreach ($act[$sd] as $r) if ($r['pending']) { $r['side'] = $sd; $pend[] = $r; }
usort($pend, function ($a, $b) { return [$a['close'] === '' ? '9999' : $a['close'], $a['nm']] <=> [$b['close'] === '' ? '9999' : $b['close'], $b['nm']]; });
$pb = []; for ($i = 0; $i < 6; $i++) { $t = strtotime(date('Y-m-01') . " +$i month"); $pb[] = ['lbl' => date('M', $t), 'from' => date('Y-m-01', $t), 'to' => date('Y-m-t', $t), 'b' => 0, 's' => 0, 'bv' => 0, 'sv' => 0]; }
$pNoDate = 0; $pLater = 0;
foreach ($pend as $r) { if ($r['close'] === '') { $pNoDate++; continue; } $hit = false;
    foreach ($pb as &$bk) if ($r['close'] >= $bk['from'] && $r['close'] <= $bk['to']) { $k = $r['side'] === 'buyer' ? 'b' : 's'; $bk[$k]++; $bk[$k . 'v'] += (int)$r['price']; $hit = true; } unset($bk);
    if (!$hit && $r['close'] > end($pb)['to']) $pLater++; }
    ob_start(); ?>
  <div class="lpanel" id="active"><h2>Active Business <span class="cbc"><?= htmlspecialchars($note) ?></span></h2>
    <div class="tiles">
      <div class="tile"><span class="tl"><i class="sw b"></i>Active buyers</span><b><?= $at['active_buyers'] ?></b><span class="ts"><?= $at['pending_buyer_sides'] ?> under contract</span></div>
      <div class="tile"><span class="tl"><i class="sw s"></i>Active sellers</span><b><?= $at['active_sellers'] ?></b><span class="ts"><?= $at['pending_seller_sides'] ?> under contract</span></div>
      <div class="tile"><span class="tl">Pending sales</span><b><?= $at['pending'] ?></b><span class="ts">sides under contract</span></div>
      <div class="tile gci"><span class="tl">Anticipated gross commission</span><b><?= $money($at['anticipated_gci']) ?></b><span class="ts">on pending sales</span></div>
      <div class="tile vol"><span class="tl">Anticipated $ volume</span><b><?= $money($at['anticipated_volume']) ?></b><span class="ts">credit for every pending side</span></div>
    </div>
<?php if ($pend): $pmx = max(1, max(array_map(function ($b) { return max($b['b'], $b['s']); }, $pb))); $pt = $pmx <= 4 ? range(0, $pmx) : [0, (int)ceil($pmx / 2), $pmx]; ?>
    <div class="chart" role="img" aria-label="Pending sides by expected closing month, buyer and seller">
      <div class="lg"><span><i class="sw b"></i>Buyer side</span><span><i class="sw s"></i>Seller side</span><span class="lgn">Pending sales by expected closing month</span></div>
      <div class="plot">
        <div class="yax"><?php foreach (array_reverse($pt) as $tk): ?><span style="bottom:<?= round($tk / $pmx * 100, 2) ?>%"><?= $tk ?></span><?php endforeach; ?></div>
        <div class="grid"><?php foreach ($pt as $tk): ?><i style="bottom:<?= round($tk / $pmx * 100, 2) ?>%"></i><?php endforeach; ?>
<?php foreach ($pb as $bk): ?>
          <div class="grp" tabindex="0" data-tip="<?= htmlspecialchars($bk['lbl'] . ' — Buyer sides: ' . $bk['b'] . ' (' . $money($bk['bv']) . ') · Seller sides: ' . $bk['s'] . ' (' . $money($bk['sv']) . ')') ?>">
            <div class="bars"><span class="bar b" style="height:<?= round($bk['b'] / $pmx * 100, 2) ?>%"><?= $bk['b'] ? '<em>' . $bk['b'] . '</em>' : '' ?></span><span class="bar s" style="height:<?= round($bk['s'] / $pmx * 100, 2) ?>%"><?= $bk['s'] ? '<em>' . $bk['s'] . '</em>' : '' ?></span></div>
            <span class="xl"><?= htmlspecialchars($bk['lbl']) ?></span>
          </div>
<?php endforeach; ?>
        </div>
      </div>
      <div class="tip" hidden></div>
    </div>
<?php if ($pNoDate || $pLater): ?>    <p class="cbn" style="margin:0 0 12px"><?= implode(' ', array_filter([$pNoDate ? $pNoDate . ' pending sale' . ($pNoDate === 1 ? ' has' : 's have') . ' no closing date in Contract Deadlines, so ' . ($pNoDate === 1 ? 'it isn\'t' : 'they aren\'t') . ' on the chart.' : '', $pLater ? $pLater . ' close' . ($pLater === 1 ? 's' : '') . ' after ' . date('F Y', strtotime(end($pb)['from'])) . '.' : ''])) ?></p>
<?php endif; ?>
<?php endif; ?>
<?php if ($at['missing_gci']): ?>    <p class="cbn" style="margin:0 0 12px"><?= $at['missing_gci'] ?> pending sale<?= $at['missing_gci'] === 1 ? ' has' : 's have' ?> no gross commission yet, so <?= $at['missing_gci'] === 1 ? 'it adds' : 'they add' ?> $0 to anticipated commission.</p>
<?php endif; ?>
<?php if ($at['missing_price']): ?>    <p class="cbn" style="margin:0 0 12px"><?= $at['missing_price'] ?> pending sale<?= $at['missing_price'] === 1 ? ' has' : 's have' ?> no contract price yet, so <?= $at['missing_price'] === 1 ? 'it adds' : 'they add' ?> $0 to anticipated volume. Use <b>+ Contract price &amp; GCI</b> or the pencil on the row.</p>
<?php endif; ?>
    <div class="ovsub"><span>Pending sales (<?= count($pend) ?>)</span></div>
<?php if (!$pend): ?>    <p class="empty">No sales under contract right now. They show here once a file reaches Executed Contract.</p>
<?php else: foreach ($pend as $r) echo ab_row($r, $r['side'], ($r['close'] !== '' ? '<span class="due later cls">' . htmlspecialchars('Closes ' . date('M j', strtotime($r['close']))) . '</span>' : '') . pf_price_ui($r, '+ Contract price', 'contract price', 'active')); endif; ?>
  </div>
<?php return ob_get_clean();
}

function pf_css() { return '<style>
.ovsub{display:flex;justify-content:space-between;align-items:baseline;margin:14px 0 6px;font-weight:700;color:var(--teal);font-size:.95rem}.ovsub:first-of-type{margin-top:0}
.ovsub span{display:inline-flex;align-items:center;gap:8px}
.tiles{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin:0 0 14px}
.tile{border:1px solid var(--line);border-radius:12px;padding:12px 14px;background:#fff}.tile b{display:block;font-size:1.6rem;line-height:1.15;color:#1f2a2c}
.tl{display:flex;align-items:center;gap:6px;font-size:.8rem;font-weight:700;color:#4b5b5d}.ts{display:block;font-size:.8rem;color:#5a6a6c;margin-top:2px}
.tile.vol{background:#f0f8f8;border-color:#cfe5e5}.tile.gci{background:#fbf6ef;border-color:#efe1cc}
@media(max-width:1100px){.tiles{grid-template-columns:repeat(3,minmax(0,1fr))}}
.sw{display:inline-block;width:10px;height:10px;border-radius:3px}.sw.b{background:#008f94}.sw.s{background:#d9692a}
.chart{position:relative;border:1px solid var(--line);border-radius:12px;padding:12px 14px 8px;margin:0 0 14px}
.lg{display:flex;gap:16px;font-size:.82rem;color:#4b5b5d;margin:0 0 10px}.lg span{display:inline-flex;align-items:center;gap:6px}
.plot{display:flex;gap:8px;height:180px;padding-bottom:22px}
.yax{position:relative;width:18px;flex:none}.yax span{position:absolute;right:0;transform:translateY(50%);font-size:.72rem;color:#8a9a9c}
.grid{position:relative;flex:1;display:flex;align-items:stretch;border-bottom:1px solid #c9d4d4}
.grid>i{position:absolute;left:0;right:0;border-top:1px solid #eef2f2}
.grp{position:relative;flex:1;display:flex;flex-direction:column;align-items:center;outline:none;cursor:default;border-radius:6px}
.grp:hover,.grp:focus{background:rgba(3,111,114,.05)}
.bars{flex:1;width:100%;display:flex;align-items:flex-end;justify-content:center;gap:2px}
.bar{position:relative;width:min(22px,38%);border-radius:4px 4px 0 0;min-height:0}.bar.b{background:#008f94}.bar.s{background:#d9692a}
.bar em{position:absolute;left:50%;bottom:100%;transform:translateX(-50%);font-style:normal;font-size:.72rem;font-weight:700;color:#1f2a2c;padding-bottom:2px}
.xl{position:absolute;top:100%;margin-top:4px;font-size:.72rem;color:#5a6a6c;white-space:nowrap}
.tip{position:absolute;z-index:5;background:#1f2a2c;color:#fff;font-size:.78rem;padding:6px 9px;border-radius:8px;pointer-events:none;max-width:260px}
.prx{display:inline-flex;align-items:center;gap:6px;margin:0 4px 0 0}.prv{font-weight:700;font-size:.85rem;color:#1f2a2c;text-align:right;line-height:1.25}.prv small{display:block;font-weight:600;font-size:.74rem;color:#4b5b5d}.pmiss{color:#8a9a9c;font-weight:600}
.prx .pre{font:inherit;font-size:.78rem;border:1px solid var(--line);background:#fff;color:var(--teal);border-radius:8px;padding:4px 8px;cursor:pointer}.prx .pre.add{font-weight:700}
.prf{display:inline-flex;gap:6px;align-items:flex-end}.prf label{display:flex;flex-direction:column;font-size:.7rem;font-weight:700;color:#4b5b5d;gap:2px}.prf input{width:110px;padding:5px 8px;border:1px solid var(--line);border-radius:8px;font:inherit;font-size:.85rem}.prf button{font:inherit;font-size:.8rem;font-weight:700;border:0;background:var(--teal);color:#fff;border-radius:8px;padding:5px 10px;cursor:pointer}
@media(max-width:700px){.tiles{grid-template-columns:repeat(2,minmax(0,1fr))}.tile b{font-size:1.25rem;overflow-wrap:anywhere}.chart.many .bar em{display:none}.chart.many .bar{width:45%}.chart.many .bars{gap:1px}.plot{height:150px}.xl{font-size:.64rem}.crow .prx{order:5;margin-left:24px}}
.sw.bl{background:#8fcfd1}.sw.sl{background:#efb48f}.bar.bl{background:#8fcfd1}.bar.sl{background:#efb48f}.chart.cmp .bar{width:min(16px,22%)}.chart.cmp .bar em{display:none}
.lg .lgn{margin-left:auto;color:#5a6a6c;font-size:.78rem}.lg span{white-space:nowrap}.cls{margin-right:4px}@media(max-width:700px){.lg .lgn{display:none}}
.tc{display:block;font-size:.76rem;color:#4b5b5d;margin-top:4px;padding-top:4px;border-top:1px dashed #dfe7e7}.vs{color:#5a6a6c}
.cmpbtn{margin-left:auto;display:inline-flex!important;align-items:center;gap:8px;font-weight:600!important;color:#3c4a4c!important}
.cmpbtn .ck{width:30px;height:18px;border-radius:99px;background:#cfd8d8;position:relative;flex:none}.cmpbtn .ck::after{content:"";position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;transition:left .15s}
.cmpbtn.on{background:#fff!important;color:var(--teal)!important;border-color:var(--teal)!important}.cmpbtn.on .ck{background:var(--teal)}.cmpbtn.on .ck::after{left:14px}
@media(max-width:700px){.cmpbtn{margin-left:0}.chart.cmp .bar{width:22%}}
.pmenu{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 10px}.pmenu a{font-weight:700;padding:8px 14px;font-size:.9rem;border-radius:10px;border:1px solid var(--line);background:var(--white);color:var(--teal);text-decoration:none}
.pmenu a.on{background:var(--teal);color:#fff;border-color:var(--teal)}.cbc{font-size:.85rem;font-weight:600;color:#5a6a6c;margin-left:6px}
.pp{display:flex;flex-wrap:wrap;gap:6px}.pp a{font-weight:700;font-size:.82rem;padding:6px 12px;border-radius:99px;border:1px solid var(--line);background:#fff;color:var(--teal);text-decoration:none}
.pp a.on{background:var(--teal);color:#fff;border-color:var(--teal)}.pp.yrs{margin:0 0 10px}.pp.yrs a{font-size:.78rem;padding:4px 10px}
.cbl{margin:0 0 12px;font-size:.88rem;color:#5a6a6c}.cbn{margin:12px 0 0;font-size:.85rem;color:#5a6a6c}
</style>'; }
function pf_js() { return <<<'JS'
<script>(function(){
document.querySelectorAll(".prx .pre").forEach(function(b){b.addEventListener("click",function(e){e.stopPropagation();var f=b.closest(".prx");f.querySelector(".prf").hidden=false;f.querySelectorAll(".pre,.prv").forEach(function(x){x.hidden=true;});var i=f.querySelector("input[name=price]");i.focus();i.select();});});
document.querySelectorAll(".chart").forEach(function(c){var t=c.querySelector(".tip");
function show(g){var r=g.getBoundingClientRect(),cr=c.getBoundingClientRect();t.textContent=g.dataset.tip;t.hidden=false;var x=r.left-cr.left+r.width/2-t.offsetWidth/2;x=Math.max(4,Math.min(x,cr.width-t.offsetWidth-4));t.style.left=x+"px";t.style.top=(r.top-cr.top-t.offsetHeight+6)+"px";}
c.querySelectorAll(".grp").forEach(function(g){g.addEventListener("mouseenter",function(){show(g);});g.addEventListener("focus",function(){show(g);});g.addEventListener("mouseleave",function(){t.hidden=true;});g.addEventListener("blur",function(){t.hidden=true;});});});
})();</script>
JS;
}
