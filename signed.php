<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email']);

// Download a signed copy (own requests only).
if (isset($_GET['dl'])) {
    $rec = sg_load($_GET['dl']);
    if (!$rec || strtolower($rec['agent']) !== $me) { http_response_code(404); exit('Not found'); }
    if (empty($rec['completed']) || empty($rec['doc'])) { http_response_code(409); exit('The signed copy is not ready yet.'); }
    @set_time_limit(280); while (ob_get_level()) { @ob_end_clean(); }
    header('X-Accel-Buffering: no'); $fn = 'Signed - ' . preg_replace('/[^A-Za-z0-9 ._()-]/', '', $rec['form']); $started = false;
    // Stream it straight from DigiSigner so a large signed copy starts downloading at once instead of loading into memory first.
    $ch = curl_init('https://api.digisigner.com/v1/documents/' . rawurlencode($rec['doc']));
    curl_setopt_array($ch, [CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_TIMEOUT => 270, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_WRITEFUNCTION => function ($c, $d) use (&$started, $fn) {
        if (!$started) { $started = true; $zip = substr($d, 0, 2) === 'PK'; // several documents signed together come back as a ZIP of separate PDFs
            header('Content-Type: ' . ($zip ? 'application/zip' : 'application/pdf')); header('Content-Disposition: attachment; filename="' . $fn . ($zip ? '.zip' : '.pdf') . '"'); }
        echo $d; flush(); return strlen($d); }]);
    curl_exec($ch); curl_close($ch); exit;
}

$msg = '';
if (isset($_GET['refile'])) { // file this request's documents again (clears what was filed before)
    $rr = sg_load((string)$_GET['refile']);
    if ($rr && strtolower($rr['agent'] ?? '') === $me && !empty($rr['completed'])) { $rr['filed'] = null; unset($rr['extra'], $rr['zip']); sg_save($rr['id'], $rr); }
    header('Location: signed.php'); exit;
}
$recs = sg_for_agent($me);
// Check status of anything still open, and file anything that is done and has a transaction.
sg_refresh_many($recs);
$pendingFile = 0;
foreach ($recs as $i => $r) { // filing happens in the background (file_pending.php) so this page loads fast
    $pend = empty($r['filed']); foreach (($r['extra'] ?? []) as $x) { if (empty($x['filed'])) $pend = true; }
    if (!empty($r['completed']) && $pend && sg_can_file($r)) $pendingFile++;
}
// Assign a finished, unfiled document to a transaction.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    $rid = (string)($_POST['rid'] ?? ''); $tx = (string)($_POST['tx'] ?? '');
    $folders = sg_folders($me) ?: [];
    $pick = null; foreach ($folders as $f) { if ($f['id'] === $tx) { $pick = ['id' => $f['id'], 'label' => $f['group'] . ' / ' . $f['name']]; } }
    foreach ($recs as $i => $r) {
        if ($r['id'] !== $rid) continue;
        if (!$pick) { $msg = 'Please choose a transaction.'; break; }
        if (empty($r['completed'])) { $msg = 'That request is not fully signed yet.'; break; }
        $r['folder'] = $pick;
        list($ok, $m) = sg_file($r);
        $msg = $ok ? $m : $m; $recs[$i] = $r; break;
    }
}
$folders = $folders ?? null;
function ago($iso) { $t = strtotime($iso); return $t ? date('M j, Y', $t) : ''; }
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
<title>Signed Documents - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
<style>.stabs{display:flex;gap:8px;margin:0 0 18px}.stabs a{padding:9px 16px;border-radius:99px;border:1px solid var(--line);background:var(--white);color:var(--teal);text-decoration:none;font-weight:600}.stabs a.on{background:var(--teal);color:#fff;border-color:var(--teal)}</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="my-transactions.php" aria-current="page">My Transactions</a>
    <a href="leads.php">Leads</a>
    <a href="forms.php">Forms</a>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1>Signed Documents</h1><p>Forms you sent for signature, and where the signed copies went.</p></section>
<main class="wrap">
  <div class="stabs"><a href="my-transactions.php">Transaction Folders</a><a href="signed.php" class="on">Signed Documents</a></div>
<?php if ($msg): ?><p class="note"><strong><?= htmlspecialchars($msg) ?></strong></p><?php endif; ?>
<?php if (!$recs): ?>
  <p class="note">Nothing sent yet. Use the Contract Forms Wizard in <a href="my-transactions.php">My Transactions</a>
    <a href="leads.php">Leads</a>, then Review and complete, then send it for signature.</p>
<?php endif; ?>
<?php foreach ($recs as $r):
    $who = []; foreach ($r['signers'] ?? [] as $s) { $d = !empty($r['signers_status'][$s['email']]); $who[] = htmlspecialchars($s['email']) . ($d ? ' &#10003;' : ''); }
?>
  <div class="panel sigbox sgrow">
    <h2><?= htmlspecialchars($r['form']) ?></h2>
    <p class="note">Sent <?= ago($r['created']) ?> &middot; <?= implode(', ', $who) ?></p>
    <?php if (empty($r['completed'])): ?>
      <p>Waiting for signatures.</p>
    <?php else: ?>
      <p><strong>Fully signed.</strong> <a class="btn" href="signed.php?dl=<?= urlencode($r['id']) ?>">Download signed copy</a></p>
      <?php if (!empty($r['filed'])): ?>
        <p class="note"><a href="signed.php?refile=<?= urlencode($r['id']) ?>">File the signed documents again</a> (use this if the filed copy would not open)</p>
        <p class="note">Filed in <?= htmlspecialchars($r['filed']['folder']) ?> as <a href="<?= htmlspecialchars($r['filed']['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($r['filed']['name']) ?></a></p>
<?php foreach (($r['extra'] ?? []) as $x): if (!empty($x['filed'])): ?>
        <p class="note">Also filed: <a href="<?= htmlspecialchars($x['filed']['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($x['filed']['name']) ?></a></p>
<?php endif; endforeach; ?>
      <?php elseif (sg_can_file($r)): ?>
        <p class="note" style="display:flex;align-items:center;gap:10px"><i class="bspin" style="border-color:rgba(0,107,104,.3);border-top-color:#006b68"></i>Filing the signed documents in your transaction record...</p>
      <?php elseif ((string)($_GET['assign'] ?? '') !== $r['id']): ?>
        <p class="note">Not filed yet. <a class="btn ghost" href="signed.php?assign=<?= urlencode($r['id']) ?>">Assign to a transaction</a></p>
      <?php else:
          if ($folders === null) { $fl = sg_folders($me); $folders = $fl ?: []; $fok = is_array($fl); } ?>
        <?php if ($folders): ?>
        <form method="post" class="sg">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
          <input type="hidden" name="rid" value="<?= htmlspecialchars($r['id']) ?>">
          <select name="tx" class="txsel">
            <option value="">Assign to a transaction...</option>
<?php $g = ''; foreach ($folders as $f): if ($f['group'] !== $g): if ($g !== '') echo "          </optgroup>\n"; $g = $f['group']; echo '          <optgroup label="' . htmlspecialchars($g) . "\">\n"; endif; ?>
            <option value="<?= htmlspecialchars($f['id']) ?>"><?= htmlspecialchars($f['name']) ?></option>
<?php endforeach; if ($g !== '') echo "          </optgroup>\n"; ?>
          </select>
          <button class="btn" type="submit">File it</button>
        </form>
        <?php else: ?><p class="note"><?= !empty($fok) ? "Not filed yet. You have no transaction folders to file into yet. Create an address folder in My Transactions, then come back and assign it here." : "Not filed yet. Your transaction folders could not be loaded right now. Please try again, or contact the office." ?></p><?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<p id="fmsg" class="note" hidden></p>
<?php if ($pendingFile): ?>
<script>
(async function(){var m=document.getElementById("fmsg");
  try{var r=await fetch("file_pending.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:<?= json_encode($_SESSION['csrf']) ?>})});var j=await r.json();
    if(j.ok&&j.filed>0&&!(j.errors||[]).length){location.reload();return;}
    if(j.busy&&!(j.errors||[]).length){m.textContent="Filing is still in progress. Reload this page in a minute.";m.className="note";m.hidden=false;return;}
    m.textContent=(j.errors&&j.errors.length)?"Could not file: "+j.errors.join("; "):(j.msg||"Could not file the signed documents.");m.className="note sigerr";m.hidden=false;
  }catch(e){m.textContent="Could not file the signed documents: "+e.message;m.className="note sigerr";m.hidden=false;}
})();
</script>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
