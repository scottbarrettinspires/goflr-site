<?php
// Performance: the agent's results. For now it holds Closed Business (closed buyer and seller records), which moved
// here from Active Business so Active Business only shows live deals. Data: ab_closed() in ablib.php (also
// performance_api.php?action=closed for assistants).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/ablib.php";
$me = $_SESSION['email'];
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
require_once __DIR__ . "/perfcards.php";
$perr = pf_handle_post($me, 'performance.php?' . http_build_query(array_filter(['p' => $_GET['p'] ?? '', 'y' => $_GET['y'] ?? '', 'cmp' => $_GET['cmp'] ?? ''])));
$all = ab_closed($me); // every closed file, with its closing date
$thisY = (int)date('Y');
$years = []; $undated = 0;
foreach (['buyer', 'seller'] as $sd) foreach ($all[$sd] as $r) { if ($r['closed'] === '') $undated++; elseif ((int)substr($r['closed'], 0, 4) < $thisY) $years[(int)substr($r['closed'], 0, 4)] = true; }
krsort($years); $years = array_keys($years);
$p = (string)($_GET['p'] ?? 'ytd'); if (!in_array($p, ['month', 'quarter', 'ytd', 'prev', 'nodate'], true)) $p = 'ytd';
$yr = (int)($_GET['y'] ?? 0); if ($p === 'prev' && !in_array($yr, $years, true)) $yr = $years[0] ?? 0;
if ($p === 'nodate') $rg = null; else $rg = ab_period_range($p === 'prev' ? (string)$yr : $p);
$pick = function ($rows) use ($p, $rg, $yr) { return array_values(array_filter($rows, function ($r) use ($p, $rg, $yr) {
    if ($p === 'nodate') return $r['closed'] === '';
    if ($p === 'prev' && !$yr) return false;
    return $r['closed'] !== '' && $r['closed'] >= $rg[0] && $r['closed'] <= $rg[1]; })); };
$cb = $pick($all['buyer']); $cs = $pick($all['seller']);
$tot = ab_totals($cb, $cs);
// Compare with the same period a year earlier (Year To Date compares with Jan 1 to the same date last year).
$cmp = !empty($_GET['cmp']) && $rg && $p !== 'nodate' && !($p === 'prev' && !$yr);
$ly = function ($d) { $t = strtotime($d); $y = (int)date('Y', $t) - 1; $m = (int)date('n', $t); $dd = min((int)date('j', $t), (int)date('t', mktime(0, 0, 0, $m, 1, $y))); return sprintf('%04d-%02d-%02d', $y, $m, $dd); };
$rgL = null; $cbL = $csL = []; $totL = null;
if ($cmp) {
    $rgL = [$ly($rg[0]), $p === 'ytd' ? $ly(date('Y-m-d')) : $ly($rg[1])];
    if ($p === 'month' || $p === 'quarter') $rgL[1] = date('Y-m-t', strtotime($rgL[1]));
    $inL = function ($r) use ($rgL) { return $r['closed'] !== '' && $r['closed'] >= $rgL[0] && $r['closed'] <= $rgL[1]; };
    $cbL = array_values(array_filter($all['buyer'], $inL)); $csL = array_values(array_filter($all['seller'], $inL)); $totL = ab_totals($cbL, $csL);
}
$yNow = $rg ? (int)substr($rg[0], 0, 4) : $thisY; $yLast = $yNow - 1;
$delta = function ($now, $was, $fmt = null) { $d = $now - $was; $f = $fmt ?: function ($x) { return (string)$x; };
    return $d === 0 ? 'no change' : ($d > 0 ? '▲ ' . $f($d) : '▼ ' . $f(-$d)); };
// Chart buckets: weeks of the month for Current Month, otherwise months of the period.
$buckets = [];
if ($rg && $p !== 'nodate' && !($p === 'prev' && !$yr)) {
    if ($p === 'month') { $last = (int)date('t', strtotime($rg[0]));
        foreach ([[1, 7], [8, 14], [15, 21], [22, $last]] as $w) $buckets[] = ['lbl' => date('M', strtotime($rg[0])) . ' ' . $w[0] . '–' . $w[1], 'from' => substr($rg[0], 0, 8) . sprintf('%02d', $w[0]), 'to' => substr($rg[0], 0, 8) . sprintf('%02d', $w[1])]; }
    else { for ($t = strtotime($rg[0]); $t <= strtotime($rg[1]); $t = strtotime('+1 month', $t)) $buckets[] = ['lbl' => date('M', $t), 'from' => date('Y-m-01', $t), 'to' => date('Y-m-t', $t)]; }
    foreach ($buckets as &$bk) { $in = function ($r) use ($bk) { return $r['closed'] >= $bk['from'] && $r['closed'] <= $bk['to']; };
        $bk['b'] = count(array_filter($cb, $in)); $bk['s'] = count(array_filter($cs, $in));
        $bk['bv'] = array_sum(array_map(function ($r) { return (int)$r['price']; }, array_filter($cb, $in))); $bk['sv'] = array_sum(array_map(function ($r) { return (int)$r['price']; }, array_filter($cs, $in)));
        if ($cmp) { $f = $ly($bk['from']); $t2 = $ly($bk['to']); if ((int)date('j', strtotime($bk['to'])) === (int)date('t', strtotime($bk['to']))) $t2 = date('Y-m-t', strtotime($t2));
            $inl = function ($r) use ($f, $t2, $rgL) { return $r['closed'] >= $f && $r['closed'] <= $t2 && $r['closed'] >= $rgL[0] && $r['closed'] <= $rgL[1]; };
            $bk['lb'] = count(array_filter($cbL, $inl)); $bk['ls'] = count(array_filter($csL, $inl));
            $bk['lbv'] = array_sum(array_map(function ($r) { return (int)$r['price']; }, array_filter($cbL, $inl))); $bk['lsv'] = array_sum(array_map(function ($r) { return (int)$r['price']; }, array_filter($csL, $inl))); } }
    unset($bk);
}
$money = 'pf_money'; $priceUI = 'pf_price_ui';
$qn = intdiv((int)date('n') - 1, 3) + 1;
$label = ['month' => date('F Y'), 'quarter' => 'Q' . $qn . ' ' . $thisY, 'ytd' => $thisY . ' year to date', 'prev' => $yr ? (string)$yr : 'Previous years', 'nodate' => 'No closing date entered'][$p];
?>
<!doctype html>
<html lang="en">
<head>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#036f72">
<link rel="icon" href="/icon-192.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="FLR Office">
<script>if("serviceWorker" in navigator){navigator.serviceWorker.register("/sw.js").catch(function(){});}</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Performance - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
<?= pf_css() ?>
<style>
.lpanel{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:16px;margin:0 0 14px}
.lpanel h2{margin:0 0 12px;font-size:1.15rem}
.lrow{display:flex;gap:12px;align-items:center;padding:12px;border:1px solid var(--line);border-radius:12px;margin:0 0 8px;background:var(--white);color:inherit;text-decoration:none}
a.lrow:hover{border-color:var(--teal)}.lrow .who{flex:1;min-width:0;display:block}.lrow .nm,.lrow .meta{display:block}.lrow .nm{font-weight:700}.lrow .meta{font-size:.82rem;color:#5a6a6c}
.lrow .due{font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:99px;white-space:nowrap}
.empty{color:#6a777a;padding:14px;text-align:center;margin:0}
.dot{width:12px;height:12px;border-radius:50%;flex:0 0 12px;border:1px solid rgba(0,0,0,.15)}
.due.over{background:#fde2e1;color:#b71c1c}.due.today{background:#fff1d6;color:#8a5a00}.due.later{background:#eef3f3;color:#4b5b5d}
.qa{display:flex;flex:0 0 auto}.qa a{display:inline-flex;width:30px;height:30px;border-radius:9px;align-items:center;justify-content:center;background:var(--teal-soft);color:var(--teal);text-decoration:none;font-size:.85rem;margin-left:4px}
.crow{cursor:pointer}.crow:hover{border-color:var(--teal)}.crow a.nm{color:inherit;text-decoration:none}
.lrow .meta{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.spills{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 14px}.spills a{font-weight:700;font-size:.82rem;padding:5px 10px;border-radius:99px;text-decoration:none;border:2px solid transparent}.spills a.on{border-color:#1f2a2c}
@media(max-width:640px){.crow{flex-wrap:wrap}.crow .who{flex:1 1 calc(100% - 30px)}.crow .due{margin-left:24px;white-space:normal;max-width:calc(100% - 140px)}.crow .qa{margin-left:auto}}
.cbhd{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px 16px;margin:0 0 12px}.cbhd h2{margin:0!important}
.csw{display:flex;flex-direction:column;gap:4px;flex:0 1 380px;min-width:240px}.csl{font-size:.78rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:var(--teal)}
.csrch{position:relative;display:flex;align-items:center;gap:8px;border:2px solid var(--teal);border-radius:12px;padding:0 12px;background:#f0f8f8;color:var(--teal)}
.csrch:focus-within{background:#fff;box-shadow:0 0 0 4px rgba(3,111,114,.15)}.csrch input{flex:1;border:0;outline:0;font:inherit;font-size:.95rem;font-weight:600;color:#1f2a2c;padding:10px 0;background:transparent;min-width:0}
.csres{position:absolute;top:calc(100% + 6px);left:0;right:0;z-index:20;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.12);max-height:340px;overflow:auto;padding:4px}
.csres a{display:block;padding:8px 10px;border-radius:8px;text-decoration:none;color:#1f2a2c}.csres a:hover,.csres a.on{background:#eef6f6}.csres a b{display:block;font-size:.92rem}.csres a span{display:block;font-size:.78rem;color:#5a6a6c}
.cxf{margin:0 0 12px}.cxi[hidden]{display:none}
.csnone{margin:0;padding:10px;font-size:.88rem;color:#5a6a6c}
@media(max-width:700px){.csw{flex:1 1 100%}}.csrch input::placeholder{color:#5f7f80;font-weight:500}
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Virtual Broker Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php">Active Business</a>
    <a href="performance.php" aria-current="page">Performance</a>
    <a href="learn.php">Learn</a>
    <?php endif; ?>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a><?php endif; ?>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>

<section class="hero">
  <h1>Performance</h1>
  <p>Your closed business and results.</p>
</section>

<main class="wrap">
  <nav class="pmenu" aria-label="Time period">
<?php foreach (['ytd' => 'Year To Date', 'month' => 'Current Month', 'quarter' => 'Current Quarter', 'prev' => 'Previous Years'] as $k => $t): ?>      <a href="performance.php?p=<?= $k ?><?= !empty($_GET['cmp']) ? '&amp;cmp=1' : '' ?>" class="<?= $p === $k ? 'on' : '' ?>"><?= $t ?></a>
<?php endforeach; ?>
<?php if ($p !== 'nodate'): $q = array_filter(['p' => $p, 'y' => $p === 'prev' ? $yr : '', 'cmp' => empty($_GET['cmp']) ? 1 : '']); ?>      <a href="performance.php?<?= htmlspecialchars(http_build_query($q)) ?>" class="cmpbtn<?= !empty($_GET['cmp']) ? ' on' : '' ?>" role="switch" aria-checked="<?= !empty($_GET['cmp']) ? 'true' : 'false' ?>"><span class="ck"></span>Compare to last year</a>
<?php endif; ?>
  </nav>
<?php if ($p === 'prev' && $years): ?>    <div class="pp yrs"><?php foreach ($years as $y): ?><a href="performance.php?p=prev&amp;y=<?= $y ?><?= !empty($_GET['cmp']) ? '&amp;cmp=1' : '' ?>" class="<?= $y === $yr ? 'on' : '' ?>"><?= $y ?></a><?php endforeach; ?></div>
<?php endif; ?>
<?= pf_active_html($me, 'right now · not affected by the time period', false) ?>
  <div class="lpanel" id="closed"><div class="cbhd"><h2>Closed Business <span class="cbc"><?= count($cb) + count($cs) ?> closed</span></h2>
    <div class="csw"><label class="csl" for="csq">View a closed transaction record</label><div class="csrch" role="search"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input id="csq" type="search" placeholder="Search closed by client or address" autocomplete="off" aria-label="Search all closed transactions by client name or property address" aria-controls="csres">
      <div id="csres" class="csres" role="listbox" hidden></div></div></div></div>
<?php $srch = []; foreach (['buyer', 'seller'] as $sd) foreach ($all[$sd] as $r) $srch[] = ['n' => $r['nm'], 'a' => $r['addr'], 's' => $sd === 'buyer' ? 'Buyer' : 'Seller', 'd' => $r['closed'] !== '' ? 'Closed ' . date('M j, Y', strtotime($r['closed'])) : 'No closing date', 'u' => ($sd === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . rawurlencode($r['t']['folder'])]; ?>
<script>(function(){var D=<?= json_encode($srch, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,q=document.getElementById("csq"),box=document.getElementById("csres"),sel=-1;
function esc(t){return String(t).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}
function norm(t){return String(t||"").toLowerCase().replace(/[^a-z0-9 ]/g," ").replace(/\s+/g," ").trim();}
function run(){var v=norm(q.value);sel=-1;if(!v){box.hidden=true;box.innerHTML="";return;}var w=v.split(" ");
  var hits=D.filter(function(x){var h=norm(x.n+" "+x.a);return w.every(function(t){return h.indexOf(t)>=0;});}).slice(0,8);
  box.innerHTML=hits.length?hits.map(function(x){return '<a role="option" href="'+esc(x.u)+'"><b>'+esc(x.n)+'</b><span>'+esc([x.s,x.a,x.d].filter(Boolean).join(" · "))+'</span></a>';}).join(""):'<p class="csnone">No closed transaction matches "'+esc(q.value)+'".</p>';box.hidden=false;}
q.addEventListener("input",run);q.addEventListener("focus",run);
q.addEventListener("keydown",function(e){var a=box.querySelectorAll("a");if(!a.length)return;if(e.key==="ArrowDown"||e.key==="ArrowUp"){e.preventDefault();sel=(sel+(e.key==="ArrowDown"?1:-1)+a.length)%a.length;a.forEach(function(x,i){x.classList.toggle("on",i===sel);});a[sel].scrollIntoView({block:"nearest"});}
  else if(e.key==="Enter"){e.preventDefault();location.href=(a[sel>=0?sel:0]).getAttribute("href");}else if(e.key==="Escape"){box.hidden=true;}});
document.addEventListener("click",function(e){if(!e.target.closest(".csrch"))box.hidden=true;});})();</script>
<?php if ($p !== 'prev' || $cmp): ?>  <p class="cbl"><?= htmlspecialchars($p === 'prev' ? (string)$yr : $label) ?><?php if ($cmp): ?> <span class="vs">compared with <?= htmlspecialchars(date('M j, Y', strtotime($rgL[0])) . ' – ' . date('M j, Y', strtotime($rgL[1]))) ?></span><?php endif; ?></p>
<?php endif; ?>
<?php if ($p === 'prev' && !$years): ?>    <p class="empty">No closed files from previous years.</p>
<?php else: ?>
<?php if ($perr): ?>    <p class="sigerr"><?= htmlspecialchars($perr) ?></p>
<?php endif; ?>
<?php if ($buckets): $mx = max(1, max(array_map(function ($b) { return max($b['b'], $b['s'], $b['lb'] ?? 0, $b['ls'] ?? 0); }, $buckets))); $ticks = $mx <= 4 ? range(0, $mx) : [0, (int)ceil($mx / 2), $mx]; ?>
    <div class="chart<?= $cmp ? ' cmp' : '' ?><?= count($buckets) > 6 ? ' many' : '' ?>" role="img" aria-label="Closed sides by <?= $p === 'month' ? 'week' : 'month' ?>, buyer and seller">
      <?php if ($cmp): ?>      <div class="lg"><span><i class="sw bl"></i>Buyer <?= $yLast ?></span><span><i class="sw b"></i>Buyer <?= $yNow ?></span><span><i class="sw sl"></i>Seller <?= $yLast ?></span><span><i class="sw s"></i>Seller <?= $yNow ?></span></div>
<?php else: ?>      <div class="lg"><span><i class="sw b"></i>Buyer side</span><span><i class="sw s"></i>Seller side</span></div>
<?php endif; ?>
      <div class="plot">
        <div class="yax"><?php foreach (array_reverse($ticks) as $tk): ?><span style="bottom:<?= round($tk / $mx * 100, 2) ?>%"><?= $tk ?></span><?php endforeach; ?></div>
        <div class="grid"><?php foreach ($ticks as $tk): ?><i style="bottom:<?= round($tk / $mx * 100, 2) ?>%"></i><?php endforeach; ?>
<?php foreach ($buckets as $bk): ?>
          <div class="grp" tabindex="0" data-tip="<?= htmlspecialchars($cmp ? $bk['lbl'] . ' — ' . $yNow . ': ' . $bk['b'] . ' buyer, ' . $bk['s'] . ' seller (' . $money($bk['bv'] + $bk['sv']) . ') · ' . $yLast . ': ' . $bk['lb'] . ' buyer, ' . $bk['ls'] . ' seller (' . $money($bk['lbv'] + $bk['lsv']) . ')' : $bk['lbl'] . ' — Buyer sides: ' . $bk['b'] . ' (' . $money($bk['bv']) . ') · Seller sides: ' . $bk['s'] . ' (' . $money($bk['sv']) . ')') ?>">
            <div class="bars"><?php if ($cmp): ?><span class="bar bl" style="height:<?= round($bk['lb'] / $mx * 100, 2) ?>%"></span><?php endif; ?><span class="bar b" style="height:<?= round($bk['b'] / $mx * 100, 2) ?>%"><?= $bk['b'] ? '<em>' . $bk['b'] . '</em>' : '' ?></span><?php if ($cmp): ?><span class="bar sl" style="height:<?= round($bk['ls'] / $mx * 100, 2) ?>%"></span><?php endif; ?><span class="bar s" style="height:<?= round($bk['s'] / $mx * 100, 2) ?>%"><?= $bk['s'] ? '<em>' . $bk['s'] . '</em>' : '' ?></span></div>
            <span class="xl"><?= htmlspecialchars($bk['lbl']) ?></span>
          </div>
<?php endforeach; ?>
        </div>
      </div>
      <div class="tip" hidden></div>
    </div>
<?php endif; ?>
    <div class="tiles">
      <div class="tile"><span class="tl"><i class="sw b"></i>Buyer sides</span><b><?= $tot['buyer_sides'] ?></b><span class="ts"><?= $money($tot['buyer_volume']) ?> &middot; GCI <?= $money($tot['buyer_gci']) ?></span><?php if ($cmp): ?><span class="tc"><?= $yLast ?>: <?= $totL['buyer_sides'] ?> (<?= $money($totL['buyer_volume']) ?>) &middot; <?= $delta($tot['buyer_sides'], $totL['buyer_sides']) ?></span><?php endif; ?></div>
      <div class="tile"><span class="tl"><i class="sw s"></i>Seller sides</span><b><?= $tot['seller_sides'] ?></b><span class="ts"><?= $money($tot['seller_volume']) ?> &middot; GCI <?= $money($tot['seller_gci']) ?></span><?php if ($cmp): ?><span class="tc"><?= $yLast ?>: <?= $totL['seller_sides'] ?> (<?= $money($totL['seller_volume']) ?>) &middot; <?= $delta($tot['seller_sides'], $totL['seller_sides']) ?></span><?php endif; ?></div>
      <div class="tile"><span class="tl">Total sides</span><b><?= $tot['sides'] ?></b><span class="ts">closed transactions</span><?php if ($cmp): ?><span class="tc"><?= $yLast ?>: <?= $totL['sides'] ?> &middot; <?= $delta($tot['sides'], $totL['sides']) ?></span><?php endif; ?></div>
      <div class="tile gci"><span class="tl">Gross commission earned</span><b><?= $money($tot['gci']) ?></b><span class="ts">Buyer <?= $money($tot['buyer_gci']) ?> &middot; Seller <?= $money($tot['seller_gci']) ?></span><?php if ($cmp): ?><span class="tc"><?= $yLast ?>: <?= $money($totL['gci']) ?> &middot; <?= $delta($tot['gci'], $totL['gci'], function ($x) use ($money) { return $money($x); }) ?></span><?php endif; ?></div>
      <div class="tile vol"><span class="tl">$ Volume</span><b><?= $money($tot['volume']) ?></b><span class="ts">credit for every side you closed</span><?php if ($cmp): ?><span class="tc"><?= $yLast ?>: <?= $money($totL['volume']) ?> &middot; <?= $delta($tot['volume'], $totL['volume'], function ($x) use ($money) { return $money($x); }) ?></span><?php endif; ?></div>
    </div>
<?php if ($tot['missing_gci']): ?>    <p class="cbn" style="margin:0 0 12px"><?= $tot['missing_gci'] ?> closed file<?= $tot['missing_gci'] === 1 ? ' has' : 's have' ?> no gross commission yet (a percent with a price, or a dollar amount), so <?= $tot['missing_gci'] === 1 ? 'it adds' : 'they add' ?> $0 to commission earned. Add it on the file's record page.</p>
<?php endif; ?>
<?php if ($tot['missing_price']): ?>    <p class="cbn" style="margin:0 0 12px"><?= $tot['missing_price'] ?> closed file<?= $tot['missing_price'] === 1 ? ' has' : 's have' ?> no sold price yet, so <?= $tot['missing_price'] === 1 ? 'it adds' : 'they add' ?> $0 to volume. Find the file with the search above and add it on its record page.</p>
<?php endif; ?>
<?php endif; ?>
<?php if ($undated): $ud = []; foreach (['buyer', 'seller'] as $sd) foreach ($all[$sd] as $r) if ($r['closed'] === '') $ud[] = '<a href="' . ($sd === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . rawurlencode($r['t']['folder']) . '">' . htmlspecialchars($r['nm']) . '</a>'; ?>    <p class="cbn"><?= $undated ?> closed file<?= $undated === 1 ? ' has' : 's have' ?> no closing date, so <?= $undated === 1 ? 'it isn\'t' : 'they aren\'t' ?> counted in a period. Add the Closing date in Contract Deadlines: <?= implode(', ', $ud) ?></p>
<?php endif; ?>
  </div>
<?php $canc = ab_cancelled($me); $cc = ['contract' => 0, 'listing' => 0, 'expired' => 0]; foreach ($canc as $r) if (isset($cc[$r['outcome']])) $cc[$r['outcome']]++; ?>
  <div class="lpanel" id="cancelled"><div class="cbhd"><h2>Cancelled &amp; Expired Business <span class="cbc"><?= count($canc) ?> total</span></h2>
    <div class="csw"><label class="csl" for="cxq">View a cancelled or expired record</label><div class="csrch" role="search"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input id="cxq" type="search" placeholder="Search by client or address" autocomplete="off" aria-label="Search cancelled and expired business by client name or property address"></div></div></div>
    <p class="cbl">All time &middot; not affected by the time period. Mark a file from its record page with <b>Mark cancelled or expired</b>.</p>
    <div class="pp cxf" role="tablist">
      <a href="#" class="on" data-k="">All (<?= count($canc) ?>)</a>
      <a href="#" data-k="contract">Cancelled Purchase Contracts (<?= $cc['contract'] ?>)</a>
      <a href="#" data-k="listing">Cancelled Listing Agreements (<?= $cc['listing'] ?>)</a>
      <a href="#" data-k="expired">Expired Listing Agreements (<?= $cc['expired'] ?>)</a>
    </div>
<?php if (!$canc): ?>    <p class="empty">No cancelled or expired business yet.</p>
<?php else: ?>
    <div id="cxlist">
<?php foreach ($canc as $r): $tag = '<span class="due over">' . htmlspecialchars($r['outcome_label'] . ($r['on'] ? ' · ' . date('M j, Y', strtotime($r['on'])) : '')) . '</span>'; ?>
      <div class="cxi" data-k="<?= htmlspecialchars($r['outcome']) ?>" data-s="<?= htmlspecialchars(strtolower($r['nm'] . ' ' . $r['addr'])) ?>"><?= ab_row($r, $r['side'], '<span class="due later cls">' . ($r['side'] === 'buyer' ? 'Buyer' : 'Seller') . '</span>', $tag) ?></div>
<?php endforeach; ?>
    </div>
    <p class="empty" id="cxnone" hidden>No cancelled or expired business matches.</p>
<?php endif; ?>
  </div>
<script>(function(){var q=document.getElementById("cxq"),k="",f=document.querySelectorAll(".cxf a");
function run(){var w=(q.value||"").toLowerCase().replace(/[^a-z0-9 ]/g," ").split(/\s+/).filter(Boolean),n=0;
  document.querySelectorAll(".cxi").forEach(function(x){var ok=(!k||x.dataset.k===k)&&w.every(function(t){return x.dataset.s.indexOf(t)>=0;});x.hidden=!ok;if(ok)n++;});
  var e=document.getElementById("cxnone");if(e)e.hidden=n>0;}
q.addEventListener("input",run);f.forEach(function(a){a.addEventListener("click",function(e){e.preventDefault();k=a.dataset.k;f.forEach(function(b){b.classList.toggle("on",b===a);});run();});});})();</script>
<?= pf_js() ?>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
