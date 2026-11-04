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
    <div class="card soon">
      <h2>Hot Leads</h2>
      <p>New and waiting leads assigned to you will show here.</p>
      <span class="tag">Coming soon</span>
    </div>
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
</body>
</html>
