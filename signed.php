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
    sg_refresh($rec);
    $pdf = sg_signed_pdf($rec);
    if ($pdf === null) { http_response_code(409); exit('The signed copy is not ready yet.'); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Signed - ' . preg_replace('/[^A-Za-z0-9 ._()-]/', '', $rec['form']) . '.pdf"');
    echo $pdf; exit;
}

$msg = '';
$recs = sg_for_agent($me);
// Check status of anything still open, and file anything that is done and has a transaction.
foreach ($recs as $i => $r) {
    if (empty($r['completed'])) { sg_refresh($r); }
    if (!empty($r['completed']) && empty($r['filed']) && !empty($r['folder']['id'])) { sg_file($r); }
    $recs[$i] = $r;
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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Signed Documents - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="my-transactions.php">My Transactions</a>
    <a href="forms.php">Forms</a>
    <a href="signed.php" aria-current="page">Signed</a>
    <a href="training.php">Training</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1>Signed Documents</h1><p>Forms you sent for signature, and where the signed copies went.</p></section>
<main class="wrap">
<?php if ($msg): ?><p class="note"><strong><?= htmlspecialchars($msg) ?></strong></p><?php endif; ?>
<?php if (!$recs): ?>
  <p class="note">Nothing sent yet. Open <a href="forms.php">Forms</a> and choose "Send for signature" on a Buyer or Seller form.</p>
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
        <p class="note">Filed in <?= htmlspecialchars($r['filed']['folder']) ?> as <a href="<?= htmlspecialchars($r['filed']['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($r['filed']['name']) ?></a></p>
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
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
