<?php
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/pstage.php";
$me = $_SESSION['email'];
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$side = (($_GET['side'] ?? '') === 'seller') ? 'seller' : 'buyer';
$buyers = array_values(array_filter(flr_tx_list($me), function ($t) { return $t['side'] === 'buyer'; }));
$sellers = array_values(array_filter(flr_tx_list($me), function ($t) { return $t['side'] === 'seller'; }));
$view = in_array($_GET['side'] ?? '', ['buyer', 'seller'], true) ? $_GET['side'] : 'overview';
// Overview numbers (all from the server: transaction index, pipeline stages, contract deadlines, signature records).
$pk = function ($t) { return ps_get($t['folder'], $t['side'], (int)$t['stage']); };
$cnt = ['buyers' => 0, 'sellers' => 0, 'listings' => 0, 'pre' => 0, 'under' => 0, 'shopping' => 0, 'closed' => 0];
foreach (array_merge($buyers, $sellers) as $t) {
    $k = $pk($t);
    if ($k === 'closed') { $cnt['closed']++; continue; }
    $cnt[$t['side'] === 'buyer' ? 'buyers' : 'sellers']++;
    if (in_array($k, ['contract', 'contingencies', 'closingready'], true)) $cnt['under']++;
    if ($t['side'] === 'seller' && in_array($k, ['active', 'managing', 'offerrecv'], true)) $cnt['listings']++;
    if ($t['side'] === 'seller' && $k === 'prelisting') $cnt['pre']++;
    if ($t['side'] === 'buyer' && in_array($k, ['agreement', 'shopping', 'offer'], true)) $cnt['shopping']++;
}
$dlSoon = dl_upcoming($me, 7, true);
// Buyers / Sellers tabs: one row per client, styled like the Leads & Contacts list.
$stageF = (string)($_GET['stage'] ?? '');
$rows = [];
require_once __DIR__ . '/ablib.php';
require_once __DIR__ . '/perfcards.php';
$perr = $view === 'overview' ? pf_handle_post($me, 'my-transactions.php') : '';
if ($view !== 'overview') $rows = ab_rows($me, $view, $view === 'buyer' ? $buyers : $sellers, $pk);
else { $ovB = array_values(array_filter(ab_rows($me, 'buyer', $buyers, $pk), function ($r) { return $r['k'] !== 'closed'; }));
       $ovS = array_values(array_filter(ab_rows($me, 'seller', $sellers, $pk), function ($r) { return $r['k'] !== 'closed'; })); }
$dlNow = count(array_filter($dlSoon, function ($d) { return $d['left'] <= 3; }));
$signedNew = []; $waiting = 0;
try { require_once __DIR__ . '/signed_panel.php'; $txAll = flr_tx_list($me);
    foreach (sg_for_agent($me) as $r) {
        if (empty($r['completed'])) { if (strtotime($r['created'] ?? '') > time() - 45 * 86400) $waiting++; continue; }
        if (empty($r['seen'])) { $rec = sg_record_for($r, $txAll); $signedNew[] = ['r' => $r, 'rec' => $rec]; }
    }
} catch (Throwable $e) {}
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
<title>Active Business - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
<?= pf_css() ?>
<style>
.stabs{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px}.stabs a{padding:9px 16px;border-radius:99px;border:1px solid var(--line);background:var(--white);color:var(--teal);text-decoration:none;font-weight:600}.stabs a.on{background:var(--teal);color:#fff;border-color:var(--teal)}
/* Sidebar + tabs, matching the Leads page's look. */
.lwrap{display:grid;grid-template-columns:220px 1fr;gap:18px;align-items:start}
.lside{display:flex;flex-direction:column;gap:8px;position:sticky;top:12px}
.sb{font:inherit;font-weight:700;text-align:left;padding:12px 14px;border-radius:12px;border:2px solid rgba(0,0,0,.08);cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:8px;text-decoration:none;background:var(--white);color:var(--teal)}
.sb.on{background:var(--teal);color:#fff;outline:3px solid #1f2a2c;outline-offset:2px}
.sb .n{font-size:.78rem;font-weight:700;background:rgba(0,0,0,.08);border-radius:99px;padding:1px 8px}
.sb.on .n{background:rgba(255,255,255,.25)}
@media(max-width:800px){.lwrap{grid-template-columns:1fr}.lside{position:static;flex-direction:row;flex-wrap:wrap}.sb{flex:1 1 45%}}
/* Same look as Leads & Contacts: tabs across the top, stat cards, then lists. */
.ltabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}
.ltabs a{font-weight:700;padding:8px 13px;font-size:.88rem;border-radius:10px;border:1px solid var(--line);background:var(--white);color:var(--teal);text-decoration:none;display:flex;gap:8px;align-items:center}
.ltabs a.on{background:var(--teal);color:#fff;border-color:var(--teal)}
.ltabs a .n{font-size:.72rem;font-weight:700;background:rgba(0,0,0,.08);border-radius:99px;padding:1px 7px}.ltabs a.on .n{background:rgba(255,255,255,.25)}
.ltabs .srcbtn{margin-left:auto;font-weight:600;color:#4b5b5d}
a.addmini{display:inline-flex;align-items:center;line-height:1;padding:3px 8px;border-radius:6px;background:#e07b39;color:#fff;font-size:.72rem;font-weight:800;letter-spacing:.02em;text-decoration:none;vertical-align:middle}a.addmini:hover{background:#c96a2c}
.ltabs a.addtab{background:#e07b39;border-color:#e07b39;color:#fff;font-weight:800}.ltabs a.addtab:hover{background:#c96a2c}
dialog.nc{border:0;border-radius:16px;padding:0;width:min(520px,calc(100vw - 32px));box-shadow:0 20px 60px rgba(0,0,0,.25)}dialog.nc::backdrop{background:rgba(15,30,32,.45)}
.nc form{padding:20px 22px}.nc h2{margin:0 0 4px;font-size:1.2rem}.nc .sub{color:#5a6a6c;font-size:.9rem;margin:0 0 14px}
.ncpick{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0 0 14px}.ncpick button{font:inherit;text-align:left;padding:12px 14px;border-radius:12px;border:2px solid var(--line);background:#fff;cursor:pointer}
.ncpick button b{display:block;color:#1f2a2c}.ncpick button span{font-size:.82rem;color:#5a6a6c}.ncpick button.on{border-color:var(--teal);background:var(--teal-soft)}
.ncg{display:grid;grid-template-columns:1fr 1fr;gap:10px}.ncg label,.nc .full{display:block;font-size:.85rem;color:#455355}.nc .full{margin-top:10px}
.nc input,.nc select{display:block;width:100%;box-sizing:border-box;margin-top:3px;padding:9px 11px;border:1px solid var(--line);border-radius:9px;font:inherit;background:#fff}
.nc .acts{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}.nc .acts button{font:inherit;font-weight:600;border-radius:10px;padding:9px 18px;cursor:pointer}
.nc .go{background:var(--teal);color:#fff;border:0}.nc .cancel{background:#fff;border:1px solid var(--line);color:#3c4646}.nc .err{color:#b71c1c;font-size:.88rem;margin:10px 0 0}
@media(max-width:520px){.ncg,.ncpick{grid-template-columns:1fr}}
.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin:0 0 18px}
.stat{display:block;background:var(--white);border:1px solid var(--line);border-radius:12px;padding:12px 14px;color:inherit;text-decoration:none}.stat b{display:block;font-size:1.6rem;line-height:1.1}.stat span{font-size:.82rem;color:#5a6a6c}
a.stat:hover{border-color:var(--teal)}
.stat.hot{border-left:5px solid #e53935}.stat.teal{border-left:5px solid #036f72}.stat.green{border-left:5px solid #2e7d32}
.stat.urgent{background:#d32f2f;border-color:#d32f2f;color:#fff;box-shadow:0 4px 14px rgba(211,47,47,.25)}.stat.urgent span{color:#fff;font-weight:600}.stat.urgent b{font-size:2rem}a.stat.urgent:hover{background:#b71c1c}
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
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Virtual Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php" aria-current="page">Active Business</a>
    <a href="performance.php">Performance</a>
    <?php endif; ?>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a><?php endif; ?>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>

<section class="hero">
  <h1>Active Business</h1>
  <p>What needs your attention today.</p>
</section>

<main class="wrap">
<?php if ($view !== 'overview'): ?>  <p style="margin:0 0 14px"><a href="my-transactions.php">&larr; Back to Active Business</a></p>
<?php endif; ?>
<?php if (!empty($_GET['added'])): ?>  <p class="note">The Pre Listing record is created. It can take a minute to show here.</p>
<?php endif; ?>
<?php if ($view === 'overview'): ?>
<?php if ($perr): ?>  <p class="sigerr"><?= htmlspecialchars($perr) ?></p>
<?php endif; ?>
<div class="deskonly"><?= pf_active_html($me, 'right now') ?></div>
<style>@media(max-width:760px){.deskonly{display:none}}</style>
  <div class="lpanel" id="deadlines"><h2>Contract deadlines: next 7 days</h2>
<?php
    // A week strip like a calendar row: today plus 6 days, each item under the day it's actually due (a deadline on
    // a weekend or holiday moves to the next business day, and says so). Anything overdue sits in its own first column.
    require_once __DIR__ . '/notiflib.php';
    $cols = []; $over = [];
    for ($k = 0; $k < 7; $k++) $cols[date('Y-m-d', strtotime("+$k days"))] = [];
    foreach ($dlSoon as $d) { $eff = $d['nextbiz'] ?: $d['date']; if ($d['left'] < 0) $over[] = $d; elseif (isset($cols[$eff])) $cols[$eff][] = $d; }
    $who = function ($d) { $in = nt_info_cached($d['folder']) ?: []; $p = $d['side'] === 'seller' ? 's' : 'b';
        $nm = trim((string)($in[$p . '1_name'] ?? '')); $ad = trim((string)($in['property'] ?? ''));
        if ($nm === '') { $nm = $d['name'] ?: 'Untitled record'; if ($ad === $nm) $ad = ''; } elseif ($ad === '' && $d['name'] !== $nm) $ad = (string)$d['name'];
        return [$nm, $ad]; };
    $item = function ($d, $cls) use ($who) { list($nm, $ad) = $who($d);
        return '<a class="wkit ' . $cls . '" href="' . ($d['side'] === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . urlencode($d['folder']) . '"><span class="wd">' . htmlspecialchars($d['label']) . '</span><span class="wn">' . htmlspecialchars($nm) . '</span>'
            . ($ad !== '' ? '<span class="wa">' . htmlspecialchars($ad) . '</span>' : '') . ($d['offday'] !== '' ? '<span class="wo">' . htmlspecialchars('Falls on ' . date('D, M j', strtotime($d['date'])) . (in_array($d['offday'], ['Saturday', 'Sunday'], true) ? '' : ' (' . $d['offday'] . ')') . ', so due the next business day') . '</span>' : '') . '</a>'; };
?>
    <div class="wk<?= $over ? ' hasover' : '' ?><?= $dlSoon ? '' : ' wkempty' ?>">
<?php if ($over): ?>
      <div class="wkd over"><div class="wkh"><span class="dn">Overdue</span><span class="dd">!</span></div><div class="wki"><?php foreach ($over as $d) echo $item($d, 'red'); ?></div></div>
<?php endif; foreach ($cols as $day => $items): $t = strtotime($day); $isToday = $day === date('Y-m-d'); $off = dl_offday($day); ?>
      <div class="wkd<?= $isToday ? ' today' : '' ?><?= $off !== '' ? ' off' : '' ?><?= $items ? '' : ' none' ?>"><div class="wkh"><span class="dn"><?= $isToday ? 'Today' : date('D', $t) ?></span><span class="dd"><?= date('j', $t) ?></span><span class="dm"><?= date('M', $t) ?><?= ($off !== '' && $off !== 'Saturday' && $off !== 'Sunday') ? '<br>' . htmlspecialchars($off) : '' ?></span></div>
        <div class="wki"><?php if (!$items): ?><span class="wnone">&mdash;</span><?php else: foreach ($items as $d) echo $item($d, $d['left'] <= 0 ? 'red' : ($d['left'] <= 3 ? 'amber' : '')); endif; ?></div>
      </div>
<?php endforeach; ?>
    </div>
<?php if (!$dlSoon): ?>    <p class="empty" id="dlnone" style="padding:10px 0 0">Nothing due in the next 7 days.</p><?php endif; ?>
<?php
    // Expanded view: this calendar week (Sun-Sat) plus the next 3 weeks, same item cards as the strip above.
    $mStart = strtotime('today') - (int)date('w') * 86400; $mStart = strtotime(date('Y-m-d', $mStart));
    $mEnd = strtotime('+27 days', $mStart); $todayD = date('Y-m-d');
    $mDays = (int)round(($mEnd - strtotime('today')) / 86400);
    $mCells = []; for ($k = 0; $k < 28; $k++) $mCells[date('Y-m-d', strtotime("+$k days", $mStart))] = [];
    $mOver = []; $mCount = 0;
    foreach (dl_upcoming($me, $mDays, true) as $d) { $eff = $d['nextbiz'] ?: $d['date'];
        if ($d['left'] < 0) $mOver[] = $d; elseif (isset($mCells[$eff])) { $mCells[$eff][] = $d; $mCount++; } }
?>
    <div id="dlmonth" hidden>
      <h3 class="mh"><?= date('M j', $mStart) ?> &ndash; <?= date('M j', $mEnd) ?> <span>(<?= $mCount ?> due<?= $mOver ? ', ' . count($mOver) . ' overdue' : '' ?>)</span></h3>
<?php if ($mOver): ?>      <div class="mover"><span class="mol">Overdue</span><?php foreach ($mOver as $d) echo $item($d, 'red'); ?></div>
<?php endif; ?>
      <div class="mcal<?= $mCount ? "" : " mempty" ?>">
<?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $wd): ?>        <div class="mwd"><?= $wd ?></div>
<?php endforeach; foreach ($mCells as $day => $items): $t = strtotime($day); $past = $day < $todayD; $off = dl_offday($day); ?>
        <div class="mc<?= $day === $todayD ? ' today' : '' ?><?= $past ? ' past' : '' ?><?= $off !== '' ? ' off' : '' ?><?= $items ? '' : ' none' ?>">
          <div class="mdh"><span class="mwn"><?= date('D', $t) ?></span><span class="md"><?= date('j', $t) === '1' || $day === date('Y-m-d', $mStart) ? date('M j', $t) : date('j', $t) ?></span><span class="mmo"><?= date('M', $t) ?></span><?= ($off !== '' && $off !== 'Saturday' && $off !== 'Sunday') ? '<span class="mhol">' . htmlspecialchars($off) . '</span>' : '' ?></div>
          <div class="wki"><?php foreach ($items as $d) echo $item($d, $d['left'] <= 0 ? 'red' : ($d['left'] <= 3 ? 'amber' : '')); ?></div>
        </div>
<?php endforeach; ?>
      </div>
<?php if (!$mCount): ?>      <p class="empty mnone0">Nothing due in the next 4 weeks.</p>
<?php endif; ?>
      <p style="margin:10px 0 0;font-size:.85rem;color:#5a6a6c">A deadline that falls on a weekend or holiday shows on the next business day. <a href="deadlines.php">Open the full deadlines list</a></p>
    </div>
    <p style="margin:10px 0 0;text-align:right;font-size:.88rem"><a href="deadlines.php" id="dlmore" role="button" aria-expanded="false" aria-controls="dlmonth" style="font-weight:600">See all upcoming deadlines &darr;</a></p>
<script>(function(){var a=document.getElementById("dlmore"),m=document.getElementById("dlmonth"),w=document.querySelector("#deadlines .wk");
a.addEventListener("click",function(e){e.preventDefault();var open=m.hidden;m.hidden=!open;var h=document.querySelector("#deadlines h2");if(h)h.textContent=open?"Contract deadlines: next 4 weeks":"Contract deadlines: next 7 days";if(w)w.hidden=open;var n=document.getElementById("dlnone");if(n)n.hidden=open;a.setAttribute("aria-expanded",open?"true":"false");a.innerHTML=open?"Show the next 7 days only &uarr;":"See all upcoming deadlines &darr;";if(open)m.scrollIntoView({behavior:"smooth",block:"nearest"});});})();</script>
  </div>
<style>
.wk{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border:1px solid var(--line);border-radius:12px;overflow:hidden}
.wk.hasover{grid-template-columns:minmax(0,1fr) repeat(7,minmax(0,1fr))}
.wkd{border-left:1px solid var(--line);min-height:150px;padding:8px 6px;display:flex;flex-direction:column;gap:6px;min-width:0;background:#fff}.wkd:first-child{border-left:0}
.wkd.off{background:#f6f8f8}.wkd.today{background:#f0f8f8}.wkd.over{background:#fff6f5}
.wkh{display:flex;flex-direction:column;align-items:center;padding:0 0 6px;border-bottom:1px solid #edf1f1;margin-bottom:2px}
.wkh .dn{font-size:.72rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#5a6a6c}
.wkh .dd{font-size:1.45rem;font-weight:500;line-height:1.25;color:#1f2a2c;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.wkh .dm{font-size:.7rem;color:#8a9a9c;text-align:center}
.wkd.today .wkh .dn{color:var(--teal)}.wkd.today .wkh .dd{background:var(--teal);color:#fff}
.wkd.over .wkh .dn{color:#b71c1c}.wkd.over .wkh .dd{background:#d32f2f;color:#fff;font-weight:700}
.wkit{display:flex;flex-direction:column;gap:1px;padding:6px 7px;border-radius:7px;background:#e1f3f2;border-left:3px solid #036f72;text-decoration:none;color:#1f2a2c;font-size:.78rem;line-height:1.3;overflow-wrap:anywhere}
.wkit:hover{filter:brightness(.96)}.wkit.amber{background:#fdf3dc;border-left-color:#d49a00}.wkit.red{background:#fde2e1;border-left-color:#c0392b}
.wkit .wd{font-weight:700}.wkit .wn{font-weight:600;color:#2a3638}.wkit .wa{color:#5a6a6c}.wkit .wo{color:#8a5a0e;font-size:.72rem;font-style:italic}
.wnone{color:#c4cfd0;text-align:center;margin-top:8px}
.wki{display:flex;flex-direction:column;gap:6px;min-width:0}
@media(max-width:760px){.wk,.wk.hasover{grid-template-columns:1fr}.wkd{border-left:0;border-top:1px solid var(--line);min-height:0;flex-direction:row;align-items:flex-start;gap:10px}.wkd:first-child{border-top:0}
  .wkh{flex:0 0 58px;border-bottom:0;margin:0}.wki{flex:1}.wnone{margin:10px 0 0;text-align:left}}
@media(max-width:760px){.wkd.none{display:none}.wk.wkempty{display:none}.wk .wkd{border-top:0}.wk .wkd:not(.none)~.wkd:not(.none){border-top:1px solid var(--line)}}
.ovsub{display:flex;justify-content:space-between;align-items:baseline;margin:14px 0 6px;font-weight:700;color:var(--teal);font-size:.95rem}.ovsub:first-of-type{margin-top:0}.ovsub a{font-weight:600;font-size:.85rem}
.ovsub span{display:inline-flex;align-items:center;gap:8px}

.mh{margin:0 0 10px;font-size:1rem}.mh span{font-weight:500;color:#5a6a6c;font-size:.88rem}
.mover{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;padding:10px;margin:0 0 10px;border:1px solid #f3c7c4;background:#fff6f5;border-radius:12px}.mover .mol{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#b71c1c;align-self:center;margin-right:4px}.mover .wkit{max-width:220px}
.mcal{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border:1px solid var(--line);border-radius:12px;overflow:hidden;background:var(--line);gap:1px}
.mwd{background:#f3f6f6;text-align:center;font-size:.72rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#5a6a6c;padding:6px 0}
.mc{background:#fff;min-height:96px;padding:6px;display:flex;flex-direction:column;gap:5px;min-width:0}.mc.off{background:#f8fafa}.mc.past{background:#fafbfb}.mc.past .mdh{opacity:.45}
.mdh{display:flex;align-items:center;gap:6px;font-size:.8rem;color:#1f2a2c}.mdh .mwn{display:none}.mdh .md{font-weight:600;min-width:24px;height:24px;padding:0 4px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center}
.mdh .mmo{display:none}.mc.today .md{background:var(--teal);color:#fff}.mhol{font-size:.66rem;color:#8a9a9c;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
@media(max-width:760px){.mcal{grid-template-columns:1fr}.mwd{display:none}.mc{min-height:0;flex-direction:row;align-items:flex-start;gap:10px}.mc.none,.mc.past{display:none}.mcal{background:#fff}.mc+.mc{border-top:1px solid var(--line)}.mcal.mempty{display:none}.mdh .mmo{display:block;font-size:.7rem;color:#8a9a9c;padding-left:4px}.mdh .md{font-size:.95rem}.mdh{flex:0 0 58px;flex-direction:column;align-items:flex-start;gap:0}.mdh .mwn{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;color:#5a6a6c}.wki{flex:1}}
</style>
  <div class="lpanel" id="clients"><h2>Active Clients</h2>
    <div class="ovsub"><span>Buyers (<?= count($ovB) ?>) <a href="#" class="addmini" data-k="buyer" role="button">+ ADD</a></span></div>
<?php if (!$ovB): ?>    <p class="empty">No active buyers.</p>
<?php else: foreach ($ovB as $r) echo ab_row($r, 'buyer'); endif; ?>
    <div class="ovsub"><span>Sellers (<?= count($ovS) ?>) <a href="#" class="addmini" data-k="seller" role="button">+ ADD</a></span></div>
<?php if (!$ovS): ?>    <p class="empty">No active sellers.</p>
<?php else: foreach ($ovS as $r) echo ab_row($r, 'seller'); endif; ?>
  </div>
  <div class="lpanel" id="signed"><h2>Newly signed documents</h2>
<?php if (!$signedNew): ?>    <p class="empty">No newly signed documents.</p>
<?php else: foreach ($signedNew as $x): $r = $x['r']; $what = ($r['offer_address'] ?? '') ?: preg_replace('/^[^-]*-\s*/', '', (string)($r['form'] ?? 'Document'));
    $url = $x['rec'] ? $x['rec']['url'] : (empty($r['filed']) && !sg_can_file($r) ? 'signed.php?assign=' . urlencode($r['id']) : 'signed.php?seen=' . urlencode($r['id'])); ?>
    <a class="lrow" href="<?= htmlspecialchars($url) ?>"><div class="who"><div class="nm"><?= htmlspecialchars($what) ?></div><div class="meta"><?= htmlspecialchars($x['rec'] ? 'In ' . $x['rec']['name'] . ' › Signed Documents' : (!empty($r['filed']) ? 'Filed in ' . $r['filed']['folder'] : 'Choose which record to file it in')) ?></div></div><span class="due" style="background:#e3f4e8;color:#2e7d32">Signed</span></a>
<?php endforeach; endif; ?>
  </div>
<?php else: $sideNm = $view === 'buyer' ? 'Buyers' : 'Sellers'; $byK = []; foreach ($rows as $r) $byK[$r['k']] = ($byK[$r['k']] ?? 0) + 1; ?>
  <div class="lpanel">
    <h2><?= $sideNm ?> <a href="#" class="addmini" data-k="<?= $view ?>" role="button">+ ADD</a></h2>
    <div class="spills"><a href="my-transactions.php?side=<?= $view ?>" class="<?= $stageF === '' ? 'on' : '' ?>" style="background:#fff;color:var(--teal);border-color:<?= $stageF === '' ? '#1f2a2c' : 'var(--line)' ?>">All <?= count($rows) ?></a>
<?php foreach (PS_STAGES[$view] as $k => $lbl): ?>
      <a href="my-transactions.php?side=<?= $view ?>&amp;stage=<?= urlencode($k) ?>" class="<?= $stageF === $k ? 'on' : '' ?>" style="background:<?= ps_color($view, $k) ?>;color:<?= ps_fg($view, $k) ?>"><?= htmlspecialchars($lbl) ?> <?= $byK[$k] ?? 0 ?></a>
<?php endforeach; ?>
    </div>
<?php $shown = array_filter($rows, function ($r) use ($stageF) { return $stageF === '' || $r['k'] === $stageF; });
if (!$rows): ?>
    <p class="empty"><?= $view === 'buyer' ? 'No buyer records yet. <a href="buyer.php?new=1">Create one</a> once a buyer signs an agreement.' : 'No seller records yet. They appear here once the listing folder is created.' ?></p>
<?php elseif (!$shown): ?>
    <p class="empty">No <?= strtolower($sideNm) ?> in this stage.</p>
<?php else: foreach ($shown as $r) echo ab_row($r, $view); endif; ?>
  </div>
<?php endif; ?>
<dialog class="nc" id="ncd"><form method="dialog" id="ncf">
  <h2>Add a new client</h2>
  <p class="sub">For a client you're about to sign a buyer agreement with, or a seller you're preparing to list. They're added to Leads &amp; Contacts too, so their history stays in one place.</p>
  <div class="ncpick"><button type="button" data-k="buyer" class="on"><b>Buyer</b><span>Buyer agreement: starts the buyer record</span></button><button type="button" data-k="seller"><b>Seller</b><span>Prelisting: starts the Pre Listing record</span></button></div>
  <div class="ncg"><label>First name<input id="nc_first" autocomplete="off"></label><label>Last name<input id="nc_last" autocomplete="off"></label>
    <label>Phone<input id="nc_phone" inputmode="tel" autocomplete="off"></label><label>Email<input id="nc_email" type="email" autocomplete="off"></label></div>
  <label class="full" id="nc_addrw" hidden>Property address<input id="nc_addr" placeholder="e.g. 123 Palm Ave, Trinity FL" autocomplete="off"></label>
  <label class="full">How you got this client<select id="nc_src"><option value="">Choose&hellip;</option><?php require_once __DIR__ . '/leadlib.php'; foreach (LD_SOURCES as $src): ?><option><?= htmlspecialchars($src) ?></option><?php endforeach; ?></select></label>
  <p class="err" id="nc_err" hidden></p>
  <div class="acts"><button type="button" class="cancel" id="nc_x">Cancel</button><button type="submit" class="go" id="nc_go">Continue to buyer record</button></div>
</form></dialog>
<script>
(function(){var d=document.getElementById("ncd"),f=document.getElementById("ncf"),kind="buyer",go=document.getElementById("nc_go"),er=document.getElementById("nc_err"),CSRF=<?= json_encode($_SESSION['csrf']) ?>;
function v(id){return document.getElementById(id).value.trim();}
function setKind(k){kind=k;f.querySelectorAll(".ncpick button").forEach(function(b){b.classList.toggle("on",b.dataset.k===k);});document.getElementById("nc_addrw").hidden=k!=="seller";go.textContent=k==="seller"?"Create Pre Listing record":"Continue to buyer record";}
document.querySelectorAll(".addmini").forEach(function(btn){btn.addEventListener("click",function(e){e.preventDefault();f.reset();er.hidden=true;setKind(btn.dataset.k||"buyer");d.showModal();document.getElementById("nc_first").focus();});});
f.querySelectorAll(".ncpick button").forEach(function(b){b.addEventListener("click",function(){setKind(b.dataset.k);});});
document.getElementById("nc_x").addEventListener("click",function(){d.close();});
function api(o){o.csrf=CSRF;return fetch("leads_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(o)}).then(function(r){return r.json();}).then(function(j){if(!j.ok)throw new Error(j.msg||"Something went wrong.");return j;});}
function fail(m){er.textContent=m;er.hidden=false;go.disabled=false;setKind(kind);}
f.addEventListener("submit",function(e){e.preventDefault();er.hidden=true;
  var first=v("nc_first"),last=v("nc_last"),phone=v("nc_phone"),email=v("nc_email"),addr=v("nc_addr"),src=v("nc_src");
  if(!first&&!last)return fail("Enter the client's name.");if(!phone&&!email)return fail("Enter a phone number or email.");
  if(kind==="seller"&&!addr)return fail("Enter the property address for the listing.");
  go.disabled=true;go.textContent=kind==="seller"?"Creating the Pre Listing record...":"Starting the buyer record...";
  var roles={};roles[kind]="ready";
  api({action:"save",id:"",lead:{first:first,last:last,phone:phone,email:email,source:src,notes:""},roles:roles,tags:src?[src]:[]}).then(function(j){
    var L=(j.leads||[]).filter(function(x){return (x.first||"")===first&&(x.last||"")===last&&(x.phone||"")===phone;})[0];if(!L)throw new Error("The client was added, but couldn't be opened. Find them in Leads & Contacts.");
    if(kind==="buyer"){location.href="buyer.php?new=1&lead="+encodeURIComponent(L.id);return;}
    return api({action:"startseller",id:L.id,address:addr}).then(function(){location.href="my-transactions.php?side=seller&added=1";},function(e3){
      go.disabled=true;er.innerHTML="";er.append((first+" "+last).trim()+" was added to Leads & Contacts, but the Pre Listing record couldn't be created: "+e3.message+" ");
      var a=document.createElement("a");a.href="leads.php?open="+encodeURIComponent(L.id);a.textContent="Open them in Leads & Contacts to try again";er.append(a);er.hidden=false;go.textContent="Not created";});
  }).catch(function(e2){fail(e2.message);});});
})();
</script>
<?php if ($view === 'overview') echo pf_js(); ?>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
<script>try{fetch("tx_sync.php",{credentials:"same-origin"}).catch(function(){});}catch(e){}</script>
</body>
</html>
