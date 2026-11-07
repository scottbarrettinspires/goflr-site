<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/formlist.php";
require_once __DIR__ . "/sigtools.php";
header('Cache-Control: no-store');

$ROLES = ['buyer1' => 'Buyer 1', 'buyer2' => 'Buyer 2', 'seller1' => 'Seller 1', 'seller2' => 'Seller 2', 'agent' => 'Agent (my signature)', 'broker' => 'Broker', 'buyeragent' => 'Buyer agent', 'listingagent' => 'Listing agent'];
$fid = $_GET['f'] ?? ($_POST['f'] ?? '');
$title = $FLR_FORMS[$fid] ?? null;
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

function sign_fail($m) { return ['ok' => false, 'msg' => $m]; }

function sign_send($fid, $title, $signers, $agentName, $agentEmail, $tx) {
    // 1. Get the PDF from the Forms folder through the office script.
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=file&id=' . urlencode($fid));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8]);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = ($body !== false && $code >= 200 && $code < 300) ? json_decode($body, true) : null;
    if (!is_array($j) || empty($j['ok']) || empty($j['b64'])) return sign_fail('Could not load that form from the Forms folder. Please try again, or contact the office.');
    $pdf = base64_decode($j['b64'], true);
    if ($pdf === false || strlen($pdf) < 500) return sign_fail('That form could not be read. Please contact the office.');
    $tmp = tempnam(sys_get_temp_dir(), 'flrsig');
    file_put_contents($tmp, $pdf);
    // 2. Upload to DigiSigner.
    $ch = curl_init('https://api.digisigner.com/v1/documents');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_POSTFIELDS => ['file' => new CURLFile($tmp, 'application/pdf', $j['name'] ?: 'form.pdf')], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    @unlink($tmp);
    $d = json_decode((string)$r, true);
    if ($code < 200 || $code >= 300 || empty($d['document_id'])) return sign_fail('DigiSigner did not accept the form (code ' . $code . '). Please contact the office.');
    // 3. Create the signature request. Hidden text tags in the form place the signature, initial and date fields by role.
    $sg = [];
    foreach ($signers as $i => $s) { $sg[] = ['email' => $s['email'], 'role' => $s['role'], 'order' => 1]; }
    $req = ['embedded' => false, 'use_text_tags' => true, 'hide_text_tags' => true, 'send_emails' => true, 'documents' => [[
        'document_id' => $d['document_id'],
        'subject' => 'Florida Luxury Realty: please sign ' . $title,
        'message' => $agentName . ' (Florida Luxury Realty) has sent you "' . $title . '" to review and sign. If you have questions, reply to ' . $agentEmail . '.',
        'signers' => $sg]]];
    $ch = curl_init('https://api.digisigner.com/v1/signature_requests');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($req), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $q = json_decode((string)$r, true);
    if ($code < 200 || $code >= 300 || empty($q['signature_request_id'])) return sign_fail('DigiSigner could not create the request (code ' . $code . '). Check that each role matches the form, or contact the office.');
    sg_save($q['signature_request_id'], ['id' => $q['signature_request_id'], 'agent' => $agentEmail, 'form' => $title, 'form_id' => $fid, 'doc' => $d['document_id'], 'created' => gmdate('c'), 'signers' => $signers, 'folder' => $tx, 'completed' => false, 'filed' => null]);
    // 4. Audit log (kept on the server, web access denied).
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    if (!is_file($dir . '/.htaccess')) { @file_put_contents($dir . '/.htaccess', "Require all denied\n"); }
    @file_put_contents($dir . '/signature-requests.log', json_encode(['t' => gmdate('c'), 'agent' => $agentEmail, 'form' => $title, 'form_id' => $fid, 'signers' => array_map(function ($s) { return $s['email'] . ' (' . $s['role'] . ')'; }, $signers), 'request' => $q['signature_request_id']]) . "\n", FILE_APPEND | LOCK_EX);
    return ['ok' => true, 'id' => $q['signature_request_id']];
}

$result = null;
$folders = [];
if ($title !== null) {
    if (!empty($_SESSION['txf']) && ($_SESSION['txf_t'] ?? 0) > time() - 600) { $folders = $_SESSION['txf']; }
    else { $f = sg_folders($_SESSION['email']); $fok = is_array($f); if (is_array($f)) { $folders = $f; $_SESSION['txf'] = $f; $_SESSION['txf_t'] = time(); } }
}
$txsel = (string)($_GET['tx'] ?? '');
$side = (($_GET['s'] ?? ($_POST['s'] ?? 'b')) === 's') ? 's' : 'b';
$first = $side === 's' ? 'seller1' : 'buyer1';
$rows = [['', $first]];
if ($title !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rows = []; $signers = []; $err = '';
    $em = $_POST['email'] ?? []; $ro = $_POST['role'] ?? [];
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    $_SESSION['sig_n'] = array_filter($_SESSION['sig_n'] ?? [], function ($t) { return $t > time() - 3600; });
    if (!$err && count($_SESSION['sig_n']) >= 20) { $err = 'Too many requests this hour. Please try again later.'; }
    if (!$err) {
        $seen = [];
        foreach ((array)$em as $i => $e) {
            $e = trim((string)$e); $r = (string)($ro[$i] ?? '');
            if ($e === '' && $r === '') continue;
            $rows[] = [$e, $r];
            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) { $err = 'Please check the email address: ' . htmlspecialchars($e); break; }
            if (!isset($ROLES[$r])) { $err = 'Please choose a role for each signer.'; break; }
            if (isset($seen[$r])) { $err = 'Each role can only be used once.'; break; }
            $seen[$r] = 1; $signers[] = ['email' => $e, 'role' => $r];
        }
        if (!$err && (count($signers) < 1 || count($signers) > 6)) { $err = 'Add between 1 and 6 signers.'; }
    }
    $tx = null; $txsel = (string)($_POST['tx'] ?? '');
    if (!$err && $txsel !== '') {
        foreach ($folders as $f) { if ($f['id'] === $txsel) { $tx = ['id' => $f['id'], 'label' => $f['group'] . ' / ' . $f['name']]; break; } }
        if ($tx === null) { $err = 'Please choose a transaction from the list.'; }
    }
    if ($err) { $result = sign_fail($err); }
    else {
        $_SESSION['sig_n'][] = time();
        $result = sign_send($fid, $title, $signers, trim($_SESSION['name'] ?? '') ?: 'Your agent', $_SESSION['email'], $tx);
    }
    if (!$rows) $rows = [['', $first]];
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
<title>Send for signature - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php" aria-current="page">Active Business</a>
    <a href="directory.php">Directory</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>

<section class="hero">
  <h1>Send for signature</h1>
  <p><?= $title !== null ? htmlspecialchars($title) : 'Choose a form first.' ?></p>
</section>

<main class="wrap">
<?php if ($title === null): ?>
  <p class="note">That form was not found. <a href="forms.php">Back to Forms</a></p>
<?php elseif ($result && $result['ok']): ?>
  <div class="panel sigbox">
    <h2>Sent</h2>
    <p>DigiSigner is emailing each signer a link to sign. Track it on the <a href="signed.php">Signed Documents</a> page; the signed copy shows up there when everyone has signed.</p>
    <p class="note">Request ID: <?= htmlspecialchars($result['id']) ?></p>
    <p><a class="btn" href="forms.php">Back to Forms</a></p>
  </div>
<?php else: ?>
  <form method="post" class="panel sigbox" id="sf">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <input type="hidden" name="f" value="<?= htmlspecialchars($fid) ?>">
    <input type="hidden" name="s" value="<?= $side ?>">
    <?php if ($result): ?><p class="sigerr"><?= $result['msg'] ?></p><?php endif; ?>
    <p>Enter each person who must sign and the role they hold on this form. Each one gets an email from DigiSigner with a link to sign. The signature, initial and date spots are already placed in the form.</p>
    <div id="signers">
<?php foreach ($rows as $row): ?>
      <div class="sg">
        <input type="email" name="email[]" placeholder="Signer's email" value="<?= htmlspecialchars($row[0]) ?>" required>
        <select name="role[]">
<?php foreach ($ROLES as $k => $lbl): ?>
          <option value="<?= $k ?>"<?= $row[1] === $k ? ' selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
<?php endforeach; ?>
        </select>
      </div>
<?php endforeach; ?>
    </div>
    <p><button type="button" class="btn ghost" id="add">+ Add a signer</button></p>
    <p><label for="tx"><strong>Which transaction is this for?</strong></label><br>
    <select name="tx" id="tx" class="txsel">
      <option value="">Not yet in a transaction (assign later)</option>
<?php $g = ''; foreach ($folders as $f): if ($f['group'] !== $g): if ($g !== '') echo "      </optgroup>\n"; $g = $f['group']; echo '      <optgroup label="' . htmlspecialchars($g) . "\">\n"; endif; ?>
      <option value="<?= htmlspecialchars($f['id']) ?>"<?= $txsel === $f['id'] ? ' selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
<?php endforeach; if ($g !== '') echo "      </optgroup>\n"; ?>
    </select><br><span class="note"><?= $folders ? 'When everyone has signed, the signed copy is filed in this transaction folder.' : (!empty($fok) ? 'You have no transaction folders yet, so the signed copy will wait on the Signed Documents page until you assign it.' : 'Your transaction folders could not be loaded, so the signed copy will wait on the Signed Documents page.') ?></span></p>
    <p class="note">Review the form before you send it. You are sending this on behalf of Florida Luxury Realty, so check names, price and dates. Once it is sent, the signers' emails go out right away.</p>
    <p><button type="submit" class="btn" id="go">Send for signature</button> <a class="btn ghost" href="forms.php">Cancel</a></p>
  </form>
<script>
(function(){var box=document.getElementById("signers"),add=document.getElementById("add"),f=document.getElementById("sf");
add.addEventListener("click",function(){if(box.children.length>=6)return;var c=box.children[0].cloneNode(true);c.querySelector("input").value="";var sel=c.querySelector("select"),used=[].map.call(box.querySelectorAll("select"),function(x){return x.value;}),order=["buyer2","seller2","buyer1","seller1","agent","broker","buyeragent","listingagent"];if(used.some(function(u){return u.indexOf("seller")===0;}))order.unshift("seller2");else order.unshift("buyer2");for(var i=0;i<order.length;i++){if(used.indexOf(order[i])<0){sel.value=order[i];break;}}box.appendChild(c);});
f.addEventListener("submit",function(){var b=document.getElementById("go");b.disabled=true;b.textContent="Sending...";});})();
</script>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
