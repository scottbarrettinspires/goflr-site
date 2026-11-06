<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/events.php";
require __DIR__ . "/sigtools.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
// Agent dismissed a "signed" notice.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? '')) && isset($_POST['seen'])) {
    $rec = sg_load((string)$_POST['seen']);
    if ($rec && strtolower($rec['agent'] ?? '') === $me) { $rec['seen'] = true; sg_save($rec['id'], $rec); }
    header('Location: home.php'); exit;
}
// Agent dismissed an announcement.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? '')) && isset($_POST['annseen'])) {
    $aid = substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_POST['annseen']), 0, 40);
    if ($aid !== '') { flr_ann_dismiss($me, $aid); sg_script_post(['op' => 'dismissann', 'email' => $me, 'id' => $aid]); }
    header('Location: home.php'); exit;
}
$anns = flr_announcements($me);
require_once __DIR__ . "/leadlib.php";
require_once __DIR__ . "/push_keys.php";
ld_autosort($me);
$hotN = 0; $newN = 0; $hotNames = []; $newLeads = []; $unreached = [];
foreach (ld_view($me, '', '') as $L) {
    $isNew = true; foreach ($L['view'] as $v) { if ($v['role'] !== 'new') $isNew = false; }
    if ($isNew) { $newN++; $newLeads[] = $L; }
    foreach ($L['view'] as $v) { if ($v['sub'] === 'unid' && isset($v['over']) && $v['over'] >= 0) { $unreached[] = [$L, $v]; break; } }
    foreach ($L['view'] as $v) { if (isset($v['over']) && $v['over'] >= 0 && $v['sub'] !== 'dnc') { $hotN++; if (count($hotNames) < 4) $hotNames[] = trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? '')); break; } }
}
// Fully signed requests the agent has not dismissed. Check anything still open, and file finished documents into the transaction record.
$signedNotes = []; $checked = 0; $needFile = false;
$myrecs = array_values(array_filter(sg_for_agent($me), function ($x) { return empty($x['seen']); }));
sg_refresh_many($myrecs, 6);
foreach ($myrecs as $r) {
    if (empty($r['completed'])) continue;
    $pend = empty($r['filed']); foreach (($r['extra'] ?? []) as $x) { if (empty($x['filed'])) $pend = true; }
    if ($pend && sg_can_file($r)) { $needFile = true; } // filed in the background by file_pending.php
    $signedNotes[] = $r;
}
$unsent = sg_draft_unsent($me);
$full = trim($_SESSION['name'] ?? '');
$first = $full !== '' ? preg_split('/\s+/', $full)[0] : '';
$pic = $_SESSION['pic'] ?? '';
$ini = '';
foreach (array_slice(preg_split('/\s+/', $full), 0, 2) as $w) { if ($w !== '') $ini .= mb_strtoupper(mb_substr($w, 0, 1)); }
if ($ini === '') $ini = 'FLR';
// Next upcoming event (Eastern time). Today's event stays until its end time has passed.
$tz = new DateTimeZone('America/New_York');
$now = new DateTime('now', $tz);
$next = null;
foreach ($FLR_EVENTS as $ev) {
    $end = DateTime::createFromFormat('Y-m-d g:i A', $ev['date'] . ' ' . ($ev['end'] ?: '11:59 PM'), $tz);
    if ($end && $end >= $now) { $next = $ev; $dt = new DateTime($ev['date'], $tz); break; }
}
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
<title>Home - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php" aria-current="page">Home</a>
    <a href="my-transactions.php">My Transactions</a>
    <a href="leads.php">Leads</a>
    <a href="forms.php">Forms</a>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>

<section class="hero welcome">
  <div class="who">
    <?php if ($pic): ?><img class="av" src="<?= htmlspecialchars($pic === 'photo.php' ? 'photo.php?v=' . (int)@filemtime(flr_photo_path($_SESSION['email'] ?? '')) : $pic) ?>" alt="" referrerpolicy="no-referrer" onerror="this.outerHTML='<span class=&quot;av&quot;><?= htmlspecialchars($ini) ?></span>'"><?php else: ?><span class="av"><?= htmlspecialchars($ini) ?></span><?php endif; ?>
    <div>
      <h1>Welcome<?= $first !== '' ? ', ' . htmlspecialchars($first) : '' ?></h1>
      <p>What needs your attention today.</p>
    </div>
  </div>
<?php if ($next): ?>
  <div class="nextup">
    <div class="tile"><b><?= strtoupper($dt->format('M')) ?></b><span><?= $dt->format('j') ?></span></div>
    <div class="nb">
      <span class="tag">Next up &middot; <?= htmlspecialchars($next['type']) ?></span>
      <h2><?= htmlspecialchars($next['title']) ?></h2>
      <div class="meta"><?= htmlspecialchars($dt->format('l')) ?><?= $next['start'] ? ' &middot; ' . htmlspecialchars($next['start'] . ' – ' . $next['end']) : '' ?><?= $next['where'] ? ' &middot; ' . htmlspecialchars($next['where']) : '' ?></div>
      <div class="row">
        <?php if ($next['zoom']): ?><a class="btn" target="_blank" rel="noopener" href="<?= htmlspecialchars($next['zoom']) ?>">Join on Zoom</a><?php endif; ?>
        <?php if ($next['rsvp']): ?><a class="btn" target="_blank" rel="noopener" href="<?= htmlspecialchars($next['rsvp']) ?>">RSVP</a><?php endif; ?>
        <a class="btn ghost" href="training.php">See all training</a>
      </div>
    </div>
  </div>
<?php endif; ?>
</section>

<main class="wrap">
  <div class="panel sigbox" id="appcard" hidden style="border-left:5px solid #036f72;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
    <div style="flex:1;min-width:220px"><strong>&#128242; Put FLR Office on your phone.</strong><br><span style="font-size:.88rem;color:#5a6a6c">Install it from the browser, no app store, and get an alert for every new lead.</span></div>
    <a class="btn" href="install.php">Install the app</a> <a href="#" id="appx" style="font-size:.85rem">Not now</a>
  </div>
<script>(function(){var c=document.getElementById("appcard"),st=window.matchMedia("(display-mode: standalone)").matches||navigator.standalone===true,dis=false;try{dis=localStorage.getItem("flrAppDismiss")==="1";}catch(e){}
if(!st&&!dis){c.hidden=false;}else{c.style.display="none";}document.getElementById("appx").onclick=function(e){e.preventDefault();c.hidden=true;c.style.display="none";try{localStorage.setItem("flrAppDismiss","1");}catch(x){}};})();</script>
  <div class="panel sigbox" id="pushcard" hidden style="border-left:5px solid #036f72;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
    <div style="flex:1;min-width:220px" id="pushtext"><strong>&#128276; Get an alert on this phone for every new lead.</strong><br><span style="font-size:.88rem;color:#5a6a6c">Works best in the FLR Office app on your home screen.</span></div>
    <button class="btn" id="pushon" type="button">Turn on alerts</button>
  </div>
  <p id="pushok" hidden style="font-size:.85rem;color:#4b5b5d;margin:0 0 12px">&#128276; New-lead alerts are on for this phone. <a href="#" id="pushtest">Send a test</a> &middot; <a href="#" id="pushoff">Turn off</a></p>
<?php if ($newLeads):
    usort($newLeads, function ($a, $b) { return strcmp(($b['created'] ?? $b['added'] ?? ''), ($a['created'] ?? $a['added'] ?? '')); });
    $today = ld_today(); ?>
  <div class="panel sigbox" style="border-left:5px solid #b71c1c;margin-bottom:16px">
    <p><span class="tag" style="background:#fde2e1;color:#b71c1c">New leads</span></p>
    <p><strong><?= count($newLeads) ?> new lead<?= count($newLeads) === 1 ? '' : 's' ?> waiting.</strong> Call within 5 minutes when you can, then sort each one into Buyers, Sellers or Sphere.</p>
<?php foreach (array_slice($newLeads, 0, 8) as $L):
        $nm = trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? '')) ?: (($L['phone'] ?? '') ?: ($L['email'] ?? 'New lead'));
        $d = $L['added'] ?? $today; $when = $d === $today ? 'Today' : ($d === ld_add($today, -1) ? 'Yesterday' : date('M j', strtotime($d)));
        $tel = preg_replace('/[^0-9+]/', '', (string)($L['phone'] ?? '')); ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 0;border-top:1px solid var(--line)">
      <div style="flex:1;min-width:180px"><strong><?= htmlspecialchars($nm) ?></strong><br><span style="font-size:.85rem;color:#5a6a6c"><?= htmlspecialchars($when . (!empty($L['source']) ? ' · ' . $L['source'] : '')) ?></span></div>
      <?php if ($tel !== ''): ?><a class="btn" href="tel:<?= htmlspecialchars($tel) ?>">Call</a> <a class="btn ghost" href="sms:<?= htmlspecialchars($tel) ?>">Text</a><?php endif; ?>
      <?php if (!empty($L['email'])): ?><a class="btn ghost" href="mailto:<?= htmlspecialchars($L['email']) ?>">Email</a><?php endif; ?>
      <a class="btn ghost" href="leads.php?open=<?= urlencode($L['id']) ?>">Open lead</a>
    </div>
<?php endforeach; ?>
<?php if (count($newLeads) > 8): ?><p style="margin:8px 0 0"><a href="leads.php">See all <?= count($newLeads) ?> new leads</a></p><?php endif; ?>
  </div>
<?php endif; ?>
<?php if ($unreached): usort($unreached, function ($a, $b) { return $b[1]['over'] - $a[1]['over']; }); ?>
  <div class="panel sigbox" style="border-left:5px solid #e53935;margin-bottom:16px">
    <p><span class="tag" style="background:#fde2e1;color:#b71c1c">Not reached yet</span></p>
    <p><strong><?= count($unreached) ?> lead<?= count($unreached) === 1 ? '' : 's' ?> still waiting to hear from you.</strong> Try again today. Once you reach someone, move them to Ready, Soon or Later and work toward a buyer agreement or listing appointment.</p>
<?php foreach (array_slice($unreached, 0, 6) as $u): $L = $u[0]; $v = $u[1];
        $nm = trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? '')) ?: (($L['phone'] ?? '') ?: ($L['email'] ?? 'Lead'));
        $tel = preg_replace('/[^0-9+]/', '', (string)($L['phone'] ?? '')); ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 0;border-top:1px solid var(--line)">
      <div style="flex:1;min-width:180px"><strong><?= htmlspecialchars($nm) ?></strong><br><span style="font-size:.85rem;color:#5a6a6c"><?= htmlspecialchars(($v['role'] === 'seller' ? 'Seller' : 'Buyer') . ' · attempt ' . $v['step'] . ' · ' . $v['ch'] . ($v['over'] > 0 ? ' · ' . $v['over'] . ' day' . ($v['over'] === 1 ? '' : 's') . ' overdue' : ' · due today')) ?></span></div>
      <?php if ($tel !== ''): ?><a class="btn" href="tel:<?= htmlspecialchars($tel) ?>">Call</a> <a class="btn ghost" href="sms:<?= htmlspecialchars($tel) ?>">Text</a><?php endif; ?>
      <?php if (!empty($L['email'])): ?><a class="btn ghost" href="mailto:<?= htmlspecialchars($L['email']) ?>">Email</a><?php endif; ?>
      <a class="btn ghost" href="leads.php?open=<?= urlencode($L['id']) ?>">Open lead</a>
    </div>
<?php endforeach; ?>
<?php if (count($unreached) > 6): ?><p style="margin:8px 0 0"><a href="leads.php">See all <?= count($unreached) ?></a></p><?php endif; ?>
  </div>
<?php endif; ?>
<?php foreach ($anns as $a): ?>
  <div class="panel sigbox" style="border-left:5px solid <?= !empty($a['personal']) ? '#c2570c' : '#006b68' ?>;margin-bottom:16px">
    <p><span class="tag"><?= !empty($a['personal']) ? 'Message for you' : 'Announcement' ?></span></p>
    <?php if (!empty($a['title'])): ?><p><strong><?= htmlspecialchars($a['title']) ?></strong></p><?php endif; ?>
    <p style="white-space:pre-line"><?= htmlspecialchars($a['message'] ?? '') ?></p>
    <p class="note" style="background:none;border:0;padding:0;margin:0 0 10px"><?= htmlspecialchars(trim(($a['by'] ?? '') . (!empty($a['date']) ? ' · ' . date('M j, Y', strtotime($a['date'])) : ''), ' ·')) ?></p>
    <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="annseen" value="<?= htmlspecialchars($a['id']) ?>"><button class="btn ghost" type="submit">Dismiss</button></form>
  </div>
<?php endforeach; ?>
<?php foreach ($unsent as $u): ?>
  <div class="panel sigbox" style="border-left:5px solid #c9a24b;margin-bottom:16px">
    <p><strong>Documents ready, not sent yet: <?= htmlspecialchars($u['addr']) ?></strong></p>
    <p>The completed contract documents are saved in <strong>1 BUYERS / 2 Contract Offers / <?= htmlspecialchars($u['addr']) ?> / Drafts - Not Signed</strong>.</p>
    <p><a class="btn" href="sign_packet.php?tx=<?= urlencode($u['tx']) ?>&amp;dr=<?= urlencode($u['addr']) ?>">Email To Buyers For Signing</a></p>
  </div>
<?php endforeach; ?>
<?php foreach ($signedNotes as $n):
    $names = []; $link = '';
    if (!empty($n['filed'])) { $names[] = $n['filed']['name']; $link = $n['filed']['folder_url'] ?? ''; if ($link === '') $link = $n['filed']['url']; }
    foreach (($n['extra'] ?? []) as $x) { if (!empty($x['filed'])) { $names[] = $x['filed']['name']; if (($n['filed']['folder_url'] ?? '') === '' && $link === '') $link = $x['filed']['url']; } }
    $where = !empty($n['filed']) ? $n['filed']['folder'] : ''; ?>
  <div class="panel sigbox" style="border-left:5px solid #2e8b57;margin-bottom:16px">
    <p><strong>Signed: <?= htmlspecialchars($n['offer_address'] ?? preg_replace('/^[^-]*-\s*/', '', (string)($n['form'] ?? ''))) ?></strong></p>
    <?php if ($names): ?>
    <p>Your buyers have signed. These documents are filed in <strong><?= htmlspecialchars($where) ?></strong>:</p>
    <ul><?php foreach ($names as $nm): ?><li><?= htmlspecialchars($nm) ?></li><?php endforeach; ?></ul>
    <p><?php if ($link): ?><a class="btn" href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener">Open the record</a> <?php endif; ?><a class="btn ghost" href="signed.php">Signed Documents</a>
    <?php elseif (sg_can_file($n)): ?>
    <p style="display:flex;align-items:center;gap:10px"><i class="bspin" style="border-color:rgba(0,107,104,.3);border-top-color:#006b68"></i>Your buyers have signed. Filing the documents in your transaction record...</p>
    <p>
    <?php else: ?>
    <p>Fully signed, but not filed in a transaction record yet.</p>
    <p><a class="btn" href="signed.php">Choose the record</a>
    <?php endif; ?>
    <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="seen" value="<?= htmlspecialchars($n['id']) ?>"> <button class="btn ghost" type="submit">Dismiss</button></form></p>
  </div>
<?php endforeach; ?>
  <div class="grid">
    <a class="card" href="leads.php" style="border-left:5px solid #e53935">
      <h2>Hot Leads</h2>
<?php if ($hotN): ?>
      <p><strong><?= $hotN ?> lead<?= $hotN === 1 ? '' : 's' ?> to contact now</strong><?= $newN ? ' &middot; ' . $newN . ' NEW to sort' : '' ?></p>
      <p style="margin:0"><?= htmlspecialchars(implode(', ', $hotNames)) ?><?= $hotN > count($hotNames) ? ' and more' : '' ?></p>
<?php else: ?>
      <p>Nothing due right now.<?= $newN ? ' ' . $newN . ' NEW lead' . ($newN === 1 ? '' : 's') . ' to sort.' : ' Add leads to start your follow-up plan.' ?></p>
<?php endif; ?>
    </a>
    <div class="card soon">
      <h2>Upcoming Contract Deadlines</h2>
      <p>Inspection, financing, appraisal and closing dates on your open contracts.</p>
      <span class="tag">Coming soon</span>
    </div>
  </div>
<?php if ($needFile): ?>
<script>
(async function(){try{var r=await fetch("file_pending.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:<?= json_encode($_SESSION['csrf']) ?>})});var j=await r.json();if(j.ok&&j.filed>0&&!(j.errors||[]).length)location.reload();}catch(e){}})();
</script>
<?php endif; ?>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only. Having trouble signing in? Contact the office.</footer>
<script>
(function(){
var KEY=<?= json_encode(PUSH_PUBLIC) ?>,CSRF=<?= json_encode($_SESSION['csrf']) ?>,card=document.getElementById("pushcard"),ok=document.getElementById("pushok");
if(!("serviceWorker" in navigator)||!("PushManager" in window)||!("Notification" in window))return;
function k(s){var p="=".repeat((4-s.length%4)%4),b=atob((s+p).replace(/-/g,"+").replace(/_/g,"/")),a=new Uint8Array(b.length);for(var i=0;i<b.length;i++)a[i]=b.charCodeAt(i);return a;}
function post(o){o.csrf=CSRF;return fetch("push_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(o)}).then(function(r){return r.json();});}
navigator.serviceWorker.ready.then(function(reg){
  reg.pushManager.getSubscription().then(function(sub){
    if(sub&&Notification.permission==="granted"){ok.hidden=false;post({endpoint:sub.endpoint});}else if(Notification.permission!=="denied"){card.hidden=false;card.style.display="flex";}
  });
  document.getElementById("pushon").onclick=function(){var b=this;b.disabled=true;b.textContent="Turning on...";
    Notification.requestPermission().then(function(p){if(p!=="granted")throw new Error("Notifications were not allowed. You can allow them in your phone's settings for this app.");
      return reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:k(KEY)});})
    .then(function(sub){return post({endpoint:sub.endpoint});}).then(function(j){if(!j.ok)throw new Error(j.msg||"Could not turn on alerts.");card.hidden=true;card.style.display="none";ok.hidden=false;return post({action:"test"});})
    .catch(function(e){document.getElementById("pushtext").innerHTML="<strong>"+String(e.message).replace(/</g,"&lt;")+"</strong>";b.disabled=false;b.textContent="Try again";});};
  document.getElementById("pushtest").onclick=function(e){e.preventDefault();post({action:"test"});this.textContent="Sent";};
  document.getElementById("pushoff").onclick=function(e){e.preventDefault();reg.pushManager.getSubscription().then(function(sub){if(!sub)return;return post({action:"off",endpoint:sub.endpoint}).then(function(){return sub.unsubscribe();});}).then(function(){ok.hidden=true;card.hidden=false;card.style.display="flex";});};
});
})();
</script>
</body>
</html>
