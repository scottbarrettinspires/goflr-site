<?php
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/pstage.php";
$me = $_SESSION['email'];
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
if ($view !== 'overview') {
    require_once __DIR__ . '/notiflib.php'; require_once __DIR__ . '/leadlib.php';
    $list = $view === 'buyer' ? $buyers : $sellers;
    $infos = []; try { $infos = nt_infos($me, $list, true); } catch (Throwable $e) {} // client contact details from the record (cached 10 min)
    $leads = []; try { foreach (ld_load($me)['leads'] as $L) foreach (['buyer', 'seller'] as $r) if (!empty($L['roles'][$r]['folder'])) $leads[$L['roles'][$r]['folder']] = $L; } catch (Throwable $e) {}
    $keys = ps_keys($view); $p = $view === 'buyer' ? 'b' : 's';
    foreach ($list as $t) {
        $k = $pk($t); $in = $infos[$t['folder']] ?? []; $L = $leads[$t['folder']] ?? null;
        $nm = trim((string)($in[$p . '1_name'] ?? '')) ?: ($L ? trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? '')) : '') ?: (string)$t['name'];
        if (trim((string)($in[$p . '2_name'] ?? '')) !== '') $nm .= ' & ' . trim($in[$p . '2_name']);
        $ph = trim((string)($in[$p . '1_phone'] ?? '')) ?: (string)($L['phone'] ?? '');
        $em = trim((string)($in[$p . '1_email'] ?? '')) ?: (string)($L['email'] ?? '');
        $next = null; $c = dl_get($t['folder']); if ($c) foreach (dl_compute(is_array($c["data"]) ? $c["data"] : (json_decode((string)$c["data"], true) ?: [])) as $d) { if (!$d['done']) { $next = $d; break; } }
        $rows[] = ['t' => $t, 'k' => $k, 'i' => array_search($k, $keys, true), 'nm' => $nm, 'ph' => $ph, 'em' => $em, 'src' => (string)($L['source'] ?? ''), 'next' => $next,
            'addr' => trim((string)($in['property'] ?? '')) ?: ($nm !== $t['name'] ? (string)$t['name'] : '')];
    }
    usort($rows, function ($a, $b) { return [$a['i'], $a['nm']] <=> [$b['i'], $b['nm']]; });
}
function ab_row($r, $side) {
    $h = function ($x) { return htmlspecialchars((string)$x); };
    $url = ($side === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . urlencode($r['t']['folder']);
    $meta = [PS_STAGES[$side][$r['k']] ?? '']; if ($r['addr'] !== '') $meta[] = $r['addr']; if ($r['src'] !== '') $meta[] = $r['src']; if ($r['ph'] !== '') $meta[] = $r['ph'];
    if ($r['k'] === 'closed') $tag = '<span class="due" style="background:#e3f4e8;color:#2e7d32">Closed</span>';
    elseif ($r['next']) { $d = $r['next']; $cl = $d['left'] < 0 ? 'over' : ($d['left'] <= 3 ? 'today' : 'later'); $tag = '<span class="due ' . $cl . '">' . $h($d['label'] . ' · ' . dl_when($d['left'])) . '</span>'; }
    else $tag = '<span class="due later">No deadlines entered</span>';
    $dig = preg_replace('/[^0-9+]/', '', $r['ph']);
    $qa = '<span class="qa">' . ($dig !== '' ? '<a href="tel:' . $h($dig) . '" title="Call">&#9742;</a><a href="sms:' . $h($dig) . '" title="Text"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg></a>' : '')
        . ($r['em'] !== '' ? '<a href="mailto:' . $h($r['em']) . '" title="Email">&#9993;</a>' : '') . '</span>';
    return '<div class="lrow crow" onclick="if(!event.target.closest(\'.qa\'))location.href=\'' . $h($url) . '\'"><span class="dot" style="background:' . $h(ps_color($side, $r['k'])) . '"></span><div class="who"><a class="nm" href="' . $h($url) . '">' . $h($r['nm']) . '</a><div class="meta">' . $h(implode(' · ', $meta)) . '</div></div>' . $tag . $qa . '</div>';
}
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
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php" aria-current="page">Active Business</a>
    <?php endif; ?>
    <a href="directory.php">Directory</a>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a> <a href="mls_data.php">MLS Data</a><?php endif; ?>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>

<section class="hero">
  <h1>Active Business</h1>
  <p>Buyers and sellers who became clients, sorted into the same kind of pipeline stages as your Leads.</p>
</section>

<main class="wrap">
  <div class="ltabs">
    <a href="my-transactions.php" class="<?= $view === 'overview' ? 'on' : '' ?>" style="<?= $view === 'overview' ? '' : 'color:#5a6a6c;font-weight:600' ?>">Overview</a>
    <a href="my-transactions.php?side=buyer" class="<?= $view === 'buyer' ? 'on' : '' ?>">Buyers <span class="n"><?= count($buyers) ?></span></a>
    <a href="my-transactions.php?side=seller" class="<?= $view === 'seller' ? 'on' : '' ?>">Sellers <span class="n"><?= count($sellers) ?></span></a>
    <a href="forms.php" class="srcbtn">&#128196; Forms</a>
  </div>
<?php if ($view === 'overview'): ?>
  <div class="stats">
    <a class="stat<?= $dlNow ? ' urgent' : ' hot' ?>" href="#deadlines"><b><?= $dlNow ?></b><span>Deadlines due in 3 days</span></a>
    <a class="stat<?= $signedNew ? ' urgent' : '' ?>" href="#signed"><b><?= count($signedNew) ?></b><span>Newly signed documents</span></a>
    <div class="stat hot"><b><?= $cnt['under'] ?></b><span>Under contract</span></div>
    <a class="stat teal" href="my-transactions.php?side=buyer"><b><?= $cnt['buyers'] ?></b><span>Active buyers</span></a>
    <a class="stat teal" href="my-transactions.php?side=seller"><b><?= $cnt['sellers'] ?></b><span>Active sellers</span></a>
    <div class="stat"><b><?= $cnt['listings'] ?></b><span>Active listings</span></div>
    <div class="stat"><b><?= $cnt['pre'] ?></b><span>Prelisting prep</span></div>
    <div class="stat"><b><?= $cnt['shopping'] ?></b><span>Buyers shopping (not under contract)</span></div>
    <div class="stat"><b><?= $waiting ?></b><span>Waiting for signatures</span></div>
    <div class="stat green"><b><?= $cnt['closed'] ?></b><span>Closed</span></div>
  </div>
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
    <div class="wk<?= $over ? ' hasover' : '' ?>">
<?php if ($over): ?>
      <div class="wkd over"><div class="wkh"><span class="dn">Overdue</span><span class="dd">!</span></div><div class="wki"><?php foreach ($over as $d) echo $item($d, 'red'); ?></div></div>
<?php endif; foreach ($cols as $day => $items): $t = strtotime($day); $isToday = $day === date('Y-m-d'); $off = dl_offday($day); ?>
      <div class="wkd<?= $isToday ? ' today' : '' ?><?= $off !== '' ? ' off' : '' ?>"><div class="wkh"><span class="dn"><?= $isToday ? 'Today' : date('D', $t) ?></span><span class="dd"><?= date('j', $t) ?></span><span class="dm"><?= date('M', $t) ?><?= ($off !== '' && $off !== 'Saturday' && $off !== 'Sunday') ? '<br>' . htmlspecialchars($off) : '' ?></span></div>
        <div class="wki"><?php if (!$items): ?><span class="wnone">&mdash;</span><?php else: foreach ($items as $d) echo $item($d, $d['left'] <= 0 ? 'red' : ($d['left'] <= 3 ? 'amber' : '')); endif; ?></div>
      </div>
<?php endforeach; ?>
    </div>
<?php if (!$dlSoon): ?>    <p class="empty" style="padding:10px 0 0">Nothing due in the next 7 days.</p><?php endif; ?>
    <p style="margin:10px 0 0;text-align:right;font-size:.88rem"><a href="deadlines.php" style="font-weight:600">See all upcoming deadlines &rarr;</a></p>
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
</style>
  <div class="lpanel" id="signed"><h2>Newly signed documents</h2>
<?php if (!$signedNew): ?>    <p class="empty">No newly signed documents.</p>
<?php else: foreach ($signedNew as $x): $r = $x['r']; $what = ($r['offer_address'] ?? '') ?: preg_replace('/^[^-]*-\s*/', '', (string)($r['form'] ?? 'Document'));
    $url = $x['rec'] ? $x['rec']['url'] : (empty($r['filed']) && !sg_can_file($r) ? 'signed.php?assign=' . urlencode($r['id']) : 'signed.php?seen=' . urlencode($r['id'])); ?>
    <a class="lrow" href="<?= htmlspecialchars($url) ?>"><div class="who"><div class="nm"><?= htmlspecialchars($what) ?></div><div class="meta"><?= htmlspecialchars($x['rec'] ? 'In ' . $x['rec']['name'] . ' › Signed Documents' : (!empty($r['filed']) ? 'Filed in ' . $r['filed']['folder'] : 'Choose which record to file it in')) ?></div></div><span class="due" style="background:#e3f4e8;color:#2e7d32">Signed</span></a>
<?php endforeach; endif; ?>
  </div>
<?php else: $sideNm = $view === 'buyer' ? 'Buyers' : 'Sellers'; $byK = []; foreach ($rows as $r) $byK[$r['k']] = ($byK[$r['k']] ?? 0) + 1; ?>
  <div class="lpanel">
    <h2><?= $sideNm ?></h2>
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
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
<script>try{fetch("tx_sync.php",{credentials:"same-origin"}).catch(function(){});}catch(e){}</script>
</body>
</html>
