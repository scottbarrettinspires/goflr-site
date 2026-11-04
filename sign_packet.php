<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
@set_time_limit(180);
header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$ROLES = ['buyer1' => 'Buyer 1', 'buyer2' => 'Buyer 2', 'seller1' => 'Seller 1', 'seller2' => 'Seller 2', 'agent' => 'Agent (my signature)', 'broker' => 'Broker', 'buyeragent' => 'Buyer agent', 'listingagent' => 'Listing agent'];
$me = $_SESSION['email'];
$all = sg_folders($me, (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''))) ?: [];
$buyers = []; foreach ($all as $f) { if ($f['group'] === '1 BUYERS / 1 Buyers') $buyers[] = $f; }
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
$buyer = null; foreach ($buyers as $f) { if ($f['id'] === $tx) $buyer = $f; }
$packets = $buyer ? sg_packets($me, $buyer['id']) : null;
$result = null; $rows = [['', 'buyer1']]; $addr = ''; $pk = '';
if ($buyer && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rows = []; $signers = []; $err = ''; $seen = [];
    $addr = trim(preg_replace('/\s+/', ' ', (string)($_POST['addr'] ?? ''))); $pk = (string)($_POST['pk'] ?? '');
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $err = 'This page expired. Please reload and try again.';
    $_SESSION['sig_n'] = array_filter($_SESSION['sig_n'] ?? [], function ($t) { return $t > time() - 3600; });
    if (!$err && count($_SESSION['sig_n']) >= 20) $err = 'Too many requests this hour. Please try again later.';
    $pick = null; foreach (($packets ?: []) as $p) { if ($p['id'] === $pk) $pick = $p; }
    if (!$err && !$pick) $err = 'Please choose the packet to send.';
    if (!$err && ($addr === '' || strlen($addr) > 120 || preg_match('/[\\\\\/:*?"<>|]/', $addr))) $err = 'Please enter the property address (no slashes or special characters).';
    if (!$err) {
        foreach ((array)($_POST['email'] ?? []) as $i => $e) {
            $e = trim((string)$e); $r = (string)($_POST['role'][$i] ?? '');
            if ($e === '' && $r === '') continue;
            $rows[] = [$e, $r];
            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) { $err = 'Please check the email address: ' . htmlspecialchars($e); break; }
            if (!isset($ROLES[$r])) { $err = 'Please choose a role for each signer.'; break; }
            if (isset($seen[$r])) { $err = 'Each role can only be used once.'; break; }
            $seen[$r] = 1; $signers[] = ['email' => $e, 'role' => $r];
        }
        if (!$err && (count($signers) < 1 || count($signers) > 6)) $err = 'Add between 1 and 6 signers.';
    }
    if (!$rows) $rows = [['', 'buyer1']];
    if ($err) { $result = ['ok' => false, 'msg' => $err]; }
    else {
        $_SESSION['sig_n'][] = time();
        $file = sg_packet_pdf($me, $pick['id']);
        if (!$file) { $result = ['ok' => false, 'msg' => 'Could not load that packet. Please try again.']; }
        else {
            $name = trim($_SESSION['name'] ?? '') ?: 'Your agent';
            $title = 'Contract Packet - ' . $addr;
            $s = sg_send_pdf($file['pdf'], $file['name'], 'Florida Luxury Realty: please sign your contract packet for ' . $addr, $name . ' (Florida Luxury Realty) has sent you a contract packet for ' . $addr . ' to review and sign. If you have questions, reply to ' . $me . '.', $signers);
            if (!$s['ok']) { $result = $s; }
            else {
                sg_save($s['id'], ['id' => $s['id'], 'agent' => $me, 'form' => $title, 'form_id' => $pick['id'], 'doc' => $s['doc'], 'created' => gmdate('c'), 'signers' => $signers, 'folder' => null, 'offer_address' => $addr, 'hint' => $buyer['id'], 'completed' => false, 'filed' => null]);
                sg_dir();
                @file_put_contents(__DIR__ . '/logs/signature-requests.log', json_encode(['t' => gmdate('c'), 'agent' => $me, 'packet' => $file['name'], 'address' => $addr, 'buyer_folder' => $buyer['name'], 'signers' => array_map(function ($x) { return $x['email'] . ' (' . $x['role'] . ')'; }, $signers), 'request' => $s['id']]) . "\n", FILE_APPEND | LOCK_EX);
                $result = ['ok' => true, 'id' => $s['id']];
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Send packet for signature - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="my-transactions.php">My Transactions</a>
    <a href="forms.php" aria-current="page">Forms</a>
    <a href="signed.php">Signed</a>
    <a href="training.php">Training</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1>Send packet for signature</h1><p>Send a contract packet from a buyer's folder. Once the buyers sign, the signed copy is filed in Contract Offers under the property address.</p></section>
<main class="wrap">
<?php if (!$buyers): ?>
  <p class="note">You need a buyer folder first: <strong>My Transactions &rsaquo; 1 BUYERS &rsaquo; 1 Buyers &rsaquo; [buyer name]</strong>. <a href="wizard.php">Contract Forms Wizard</a></p>
<?php elseif ($result && $result['ok']): ?>
  <div class="panel sigbox"><h2>Sent</h2>
    <p>DigiSigner is emailing each signer a link. When everyone has signed, the signed packet is filed in <strong>1 BUYERS / 2 Contract Offers / <?= htmlspecialchars($addr) ?></strong>. Track it on the <a href="signed.php">Signed Documents</a> page.</p>
    <p class="note">Request ID: <?= htmlspecialchars($result['id']) ?></p></div>
<?php else: ?>
  <form method="get" class="panel sigbox"><p><label for="tx"><strong>Which buyer?</strong></label><br>
    <select name="tx" id="tx" class="txsel" onchange="this.form.submit()"><option value="">Choose a buyer...</option>
<?php foreach ($buyers as $f): ?>
      <option value="<?= htmlspecialchars($f['id']) ?>"<?= $tx === $f['id'] ? ' selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
<?php endforeach; ?>
    </select></p></form>
<?php if ($buyer): ?>
  <form method="post" class="panel sigbox" id="sf">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <input type="hidden" name="tx" value="<?= htmlspecialchars($buyer['id']) ?>">
    <?php if ($result): ?><p class="sigerr"><?= $result['msg'] ?></p><?php endif; ?>
    <?php if (!$packets): ?><p class="note">No PDF packets in this buyer's folder yet. Build one with the <a href="wizard.php?tx=<?= urlencode($buyer['id']) ?>">Contract Forms Wizard</a>, fill it in, and save it in this folder.</p>
    <?php else: ?>
    <p><label for="pk"><strong>Which packet?</strong></label><br>
    <select name="pk" id="pk" class="txsel">
      <option value="">Choose a packet...</option>
<?php foreach ($packets as $p): ?>
      <option value="<?= htmlspecialchars($p['id']) ?>"<?= $pk === $p['id'] ? ' selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
<?php endforeach; ?>
    </select><br><span class="note">Fill in the contract details first, then save the packet in this folder before sending.</span></p>
    <p><label for="addr"><strong>Property address</strong></label><br><input type="text" name="addr" id="addr" class="txsel" size="40" maxlength="120" value="<?= htmlspecialchars($addr) ?>" placeholder="e.g. 123 Palm Ave"><br><span class="note">The signed packet is filed in Contract Offers under this address (the folder is created if it is not there).</span></p>
    <p><strong>Who signs?</strong></p>
    <div id="signers">
<?php foreach ($rows as $row): ?>
      <div class="sg"><input type="email" name="email[]" placeholder="Signer's email" value="<?= htmlspecialchars($row[0]) ?>" required>
        <select name="role[]">
<?php foreach ($ROLES as $k => $lbl): ?>
          <option value="<?= $k ?>"<?= $row[1] === $k ? ' selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
<?php endforeach; ?>
        </select></div>
<?php endforeach; ?>
    </div>
    <p><button type="button" class="btn ghost" id="add">+ Add a signer</button></p>
    <p class="note">Check the packet before you send it. The signers' emails go out right away.</p>
    <p><button type="submit" class="btn" id="go">Send for signature</button> <a class="btn ghost" href="forms.php">Cancel</a></p>
    <?php endif; ?>
  </form>
<script>
(function(){var box=document.getElementById("signers"),add=document.getElementById("add"),f=document.getElementById("sf");if(!box||!add)return;
add.addEventListener("click",function(){if(box.children.length>=6)return;var c=box.children[0].cloneNode(true);c.querySelector("input").value="";var sel=c.querySelector("select"),used=[].map.call(box.querySelectorAll("select"),function(x){return x.value;}),order=["buyer2","buyer1","agent","broker","buyeragent","seller1","seller2","listingagent"];for(var i=0;i<order.length;i++){if(used.indexOf(order[i])<0){sel.value=order[i];break;}}box.appendChild(c);});
f.addEventListener("submit",function(){var b=document.getElementById("go");b.disabled=true;b.textContent="Sending...";});})();
</script>
<?php endif; endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
