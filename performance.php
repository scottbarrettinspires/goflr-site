<?php
// Performance: the agent's results. For now it holds Closed Business (closed buyer and seller records), which moved
// here from Active Business so Active Business only shows live deals. Data: ab_closed() in ablib.php (also
// performance_api.php?action=closed for assistants).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/ablib.php";
$me = $_SESSION['email'];
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$perr = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'setprice') { // sold price for one closed file
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $perr = 'This page expired. Please reload and try again.';
    else $perr = ab_set_price($me, (string)($_POST['tx'] ?? ''), (string)($_POST['price'] ?? ''));
    if ($perr === '') { header('Location: performance.php?' . http_build_query(array_filter(['p' => $_GET['p'] ?? '', 'y' => $_GET['y'] ?? '', 'cmp' => $_GET['cmp'] ?? ''])) . '#closed'); exit; }
}
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
$money = function ($n) { return '$' . number_format((int)$n); };
$priceUI = function ($r) use ($money) { $h = function ($x) { return htmlspecialchars((string)$x); };
    return '<form method="post" class="qa prx" onclick="event.stopPropagation()"><input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="op" value="setprice"><input type="hidden" name="tx" value="' . $h($r['t']['folder']) . '">'
        . ($r['price'] ? '<span class="prv">' . $money($r['price']) . '</span><button type="button" class="pre" title="Edit sold price" aria-label="Edit sold price">&#9998;</button>' : '<button type="button" class="pre add">+ Sold price</button>')
        . '<span class="prf" hidden><input name="price" inputmode="numeric" placeholder="Sold price" value="' . ($r['price'] ?: '') . '" aria-label="Sold price"><button type="submit">Save</button></span></form>'; };
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
.ovsub{display:flex;justify-content:space-between;align-items:baseline;margin:14px 0 6px;font-weight:700;color:var(--teal);font-size:.95rem}.ovsub:first-of-type{margin-top:0}
.ovsub span{display:inline-flex;align-items:center;gap:8px}
.tiles{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:0 0 14px}
.tile{border:1px solid var(--line);border-radius:12px;padding:12px 14px;background:#fff}.tile b{display:block;font-size:1.6rem;line-height:1.15;color:#1f2a2c}
.tl{display:flex;align-items:center;gap:6px;font-size:.8rem;font-weight:700;color:#4b5b5d}.ts{display:block;font-size:.8rem;color:#5a6a6c;margin-top:2px}
.tile.vol{background:#f0f8f8;border-color:#cfe5e5}
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
.prx{display:inline-flex;align-items:center;gap:6px;margin:0 4px 0 0}.prv{font-weight:700;font-size:.85rem;color:#1f2a2c}
.prx .pre{font:inherit;font-size:.78rem;border:1px solid var(--line);background:#fff;color:var(--teal);border-radius:8px;padding:4px 8px;cursor:pointer}.prx .pre.add{font-weight:700}
.prf{display:inline-flex;gap:4px}.prf input{width:110px;padding:5px 8px;border:1px solid var(--line);border-radius:8px;font:inherit;font-size:.85rem}.prf button{font:inherit;font-size:.8rem;font-weight:700;border:0;background:var(--teal);color:#fff;border-radius:8px;padding:5px 10px;cursor:pointer}
@media(max-width:700px){.tiles{grid-template-columns:repeat(2,minmax(0,1fr))}.tile b{font-size:1.25rem;overflow-wrap:anywhere}.chart.many .bar em{display:none}.chart.many .bar{width:45%}.chart.many .bars{gap:1px}.plot{height:150px}.xl{font-size:.64rem}.crow .prx{order:5;margin-left:24px}}
.sw.bl{background:#8fcfd1}.sw.sl{background:#efb48f}.bar.bl{background:#8fcfd1}.bar.sl{background:#efb48f}.chart.cmp .bar{width:min(16px,22%)}.chart.cmp .bar em{display:none}
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
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php">Active Business</a>
    <a href="performance.php" aria-current="page">Performance</a>
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
<?php foreach (['month' => 'Current Month', 'quarter' => 'Current Quarter', 'ytd' => 'Year To Date', 'prev' => 'Previous Years'] as $k => $t): ?>      <a href="performance.php?p=<?= $k ?><?= !empty($_GET['cmp']) ? '&amp;cmp=1' : '' ?>" class="<?= $p === $k ? 'on' : '' ?>"><?= $t ?></a>
<?php endforeach; ?>
<?php if ($p !== 'nodate'): $q = array_filter(['p' => $p, 'y' => $p === 'prev' ? $yr : '', 'cmp' => empty($_GET['cmp']) ? 1 : '']); ?>      <a href="performance.php?<?= htmlspecialchars(http_build_query($q)) ?>" class="cmpbtn<?= !empty($_GET['cmp']) ? ' on' : '' ?>" role="switch" aria-checked="<?= !empty($_GET['cmp']) ? 'true' : 'false' ?>"><span class="ck"></span>Compare to last year</a>
<?php endif; ?>
  </nav>
<?php if ($p === 'prev' && $years): ?>    <div class="pp yrs"><?php foreach ($years as $y): ?><a href="performance.php?p=prev&amp;y=<?= $y ?><?= !empty($_GET['cmp']) ? '&amp;cmp=1' : '' ?>" class="<?= $y === $yr ? 'on' : '' ?>"><?= $y ?></a><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($p !== 'prev' || $cmp): ?>  <p class="cbl"><?= htmlspecialchars($p === 'prev' ? (string)$yr : $label) ?><?php if ($cmp): ?> <span class="vs">compared with <?= htmlspecialchars(date('M j, Y', strtotime($rgL[0])) . ' – ' . date('M j, Y', strtotime($rgL[1]))) ?></span><?php endif; ?></p>
<?php endif; ?>
  <div class="lpanel" id="closed"><h2>Closed Business <span class="cbc"><?= count($cb) + count($cs) ?> closed</span></h2>
<?php if ($p === 'prev' && !$years): ?>    <p class="empty">No closed files from previous years.</p>
<?php else: ?>
<?php if ($perr): ?>    <p class="sigerr"><?= htmlspecialchars($perr) ?></p>
<?php endif; ?>
    <div class="tiles">
      <div class="tile"><span class="tl"><i class="sw b"></i>Buyer sides</span><b><?= $tot['buyer_sides'] ?></b><span class="ts"><?= $money($tot['buyer_volume']) ?></span><?php if ($cmp): ?><span class="tc"><?= $yLast ?>: <?= $totL['buyer_sides'] ?> (<?= $money($totL['buyer_volume']) ?>) &middot; <?= $delta($tot['buyer_sides'], $totL['buyer_sides']) ?></span><?php endif; ?></div>
      <div class="tile"><span class="tl"><i class="sw s"></i>Seller sides</span><b><?= $tot['seller_sides'] ?></b><span class="ts"><?= $money($tot['seller_volume']) ?></span><?php if ($cmp): ?><span class="tc"><?= $yLast ?>: <?= $totL['seller_sides'] ?> (<?= $money($totL['seller_volume']) ?>) &middot; <?= $delta($tot['seller_sides'], $totL['seller_sides']) ?></span><?php endif; ?></div>
      <div class="tile"><span class="tl">Total sides</span><b><?= $tot['sides'] ?></b><span class="ts">closed transactions</span><?php if ($cmp): ?><span class="tc"><?= $yLast ?>: <?= $totL['sides'] ?> &middot; <?= $delta($tot['sides'], $totL['sides']) ?></span><?php endif; ?></div>
      <div class="tile vol"><span class="tl">$ Volume</span><b><?= $money($tot['volume']) ?></b><span class="ts">credit for every side you closed</span><?php if ($cmp): ?><span class="tc"><?= $yLast ?>: <?= $money($totL['volume']) ?> &middot; <?= $delta($tot['volume'], $totL['volume'], function ($x) use ($money) { return $money($x); }) ?></span><?php endif; ?></div>
    </div>
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
<?php if ($tot['missing_price']): ?>    <p class="cbn" style="margin:0 0 12px"><?= $tot['missing_price'] ?> closed file<?= $tot['missing_price'] === 1 ? ' has' : 's have' ?> no sold price yet, so <?= $tot['missing_price'] === 1 ? 'it adds' : 'they add' ?> $0 to volume. Use <b>+ Sold price</b> on the row.</p>
<?php endif; ?>
    <div class="ovsub"><span>Buyers (<?= count($cb) ?>)</span></div>
<?php if (!$cb): ?>    <p class="empty">No closed buyer transactions in this period.</p>
<?php else: foreach ($cb as $r) echo ab_row($r, 'buyer', $priceUI($r)); endif; ?>
    <div class="ovsub"><span>Sellers (<?= count($cs) ?>)</span></div>
<?php if (!$cs): ?>    <p class="empty">No closed seller transactions in this period.</p>
<?php else: foreach ($cs as $r) echo ab_row($r, 'seller', $priceUI($r)); endif; ?>
<?php endif; ?>
<?php if ($undated && $p !== 'nodate'): ?>    <p class="cbn"><?= $undated ?> closed file<?= $undated === 1 ? ' has' : 's have' ?> no closing date, so <?= $undated === 1 ? 'it isn\'t' : 'they aren\'t' ?> counted in a period. <a href="performance.php?p=nodate#closed">See <?= $undated === 1 ? 'it' : 'them' ?></a></p>
<?php endif; ?>
  </div>
<script>(function(){
document.querySelectorAll(".prx .pre").forEach(function(b){b.addEventListener("click",function(e){e.stopPropagation();var f=b.closest(".prx");f.querySelector(".prf").hidden=false;f.querySelectorAll(".pre,.prv").forEach(function(x){x.hidden=true;});var i=f.querySelector("input[name=price]");i.focus();i.select();});});
var c=document.querySelector(".chart");if(!c)return;var t=c.querySelector(".tip");
function show(g){var r=g.getBoundingClientRect(),cr=c.getBoundingClientRect();t.textContent=g.dataset.tip;t.hidden=false;var x=r.left-cr.left+r.width/2-t.offsetWidth/2;x=Math.max(4,Math.min(x,cr.width-t.offsetWidth-4));t.style.left=x+"px";t.style.top=(r.top-cr.top-t.offsetHeight+6)+"px";}
c.querySelectorAll(".grp").forEach(function(g){g.addEventListener("mouseenter",function(){show(g);});g.addEventListener("focus",function(){show(g);});g.addEventListener("mouseleave",function(){t.hidden=true;});g.addEventListener("blur",function(){t.hidden=true;});});
})();</script>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
