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
$dr = trim(preg_replace('/\s+/', ' ', (string)($_GET['dr'] ?? ($_POST['dr'] ?? ''))));
if ($dr !== '' && (strlen($dr) > 120 || preg_match('/[\\\\\/:*?"<>|]/', $dr))) $dr = '';
$drRes = ($buyer && $dr !== '') ? sg_drafts($me, $buyer['id'], $dr) : null;
$allpk = $drRes ? $drRes['files'] : ($buyer ? sg_packets($me, $buyer['id']) : null);
$isD = function ($n) { return stripos((string)$n, 'Buyer Disclosures') === 0; };
$packets = []; $discs = [];
foreach (($allpk ?: []) as $p) { if ($isD($p['name'])) $discs[] = $p; else $packets[] = $p; }
$dk = '';
foreach ($discs as $p) { if ($dk === '' || stripos($p['name'], 'completed') !== false) $dk = $p['id']; } // preselect the completed copy when there is one
$pk = '';
foreach ($packets as $p) { if (stripos($p['name'], 'completed') !== false) { $pk = $p['id']; break; } } // preselect the completed packet
if ($drRes) { $pk = $packets ? $packets[0]['id'] : ''; $dk = $discs ? $discs[0]['id'] : ''; } // saved drafts: newest of each
$draft = $buyer && ((string)($_GET['draft'] ?? ($_POST['draft'] ?? '')) === '1'); // documents come from the agent's browser, not from the folder
$SIG = $buyer ? sg_signers($me) : null;
$result = null; $rows = [['', 'buyer1', '']]; $addr = $drRes ? $dr : '';
if ($buyer && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $bi = sg_buyer_info($me, $buyer['id']); $bi = (!empty($bi['ok']) && is_array($bi['info'] ?? null)) ? $bi['info'] : [];
    $pa = trim(preg_replace('/\s+/', ' ', (string)($bi['property'] ?? ''))); if ($addr === '' && $pa !== '' && strlen($pa) <= 120 && !preg_match('/[\\\\\/:*?"<>|]/', $pa)) $addr = $pa;
    $mine = strtolower(trim((string)($_SESSION['name'] ?? ''))); $pre = [];
    $n1 = trim((string)($bi['b1_name'] ?? '')); $n2 = trim((string)($bi['b2_name'] ?? ''));
    if ($mine !== '' && strtolower($n1) === $mine) $n1 = ''; if ($mine !== '' && strtolower($n2) === $mine) $n2 = '';
    if ($n1 !== '' || !empty($bi['b1_email'])) $pre[] = [(string)($bi['b1_email'] ?? ''), 'buyer1', $n1];
    if ($n2 !== '' || !empty($bi['b2_email'])) $pre[] = [(string)($bi['b2_email'] ?? ''), 'buyer2', $n2];
    if ($pre) $rows = $pre;
}
if ($buyer && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rows = []; $signers = []; $err = ''; $seen = [];
    $addr = trim(preg_replace('/\s+/', ' ', (string)($_POST['addr'] ?? ''))); $pk = (string)($_POST['pk'] ?? ''); $dk = (string)($_POST['dk'] ?? '');
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $err = 'This page expired. Please reload and try again.';
    $_SESSION['sig_n'] = array_filter($_SESSION['sig_n'] ?? [], function ($t) { return $t > time() - 3600; });
    if (!$err && count($_SESSION['sig_n']) >= 20) $err = 'Too many requests this hour. Please try again later.';
    $pick = null; foreach ($packets as $p) { if ($p['id'] === $pk) $pick = $p; }
    $dpick = null; foreach ($discs as $p) { if ($p['id'] === $dk) $dpick = $p; }
    if ($draft) {
        $pick = strlen((string)($_POST['pkb64'] ?? '')) > 100 ? ['id' => '', 'name' => 'Contract Packet.pdf'] : null;
        $dpick = strlen((string)($_POST['dkb64'] ?? '')) > 100 ? ['id' => '', 'name' => 'Buyer Disclosures.pdf'] : null;
    }
    if (!$err && !$pick && !$dpick) $err = 'Please choose the contract packet and/or the buyer disclosures to send.';
    if (!$err && ($addr === '' || strlen($addr) > 120 || preg_match('/[\\\\\/:*?"<>|]/', $addr))) $err = 'Please enter the property address (no slashes or special characters).';
    if (!$err) {
        foreach ((array)($_POST['email'] ?? []) as $i => $e) {
            $e = trim((string)$e); $r = (string)($_POST['role'][$i] ?? '');
            if ($e === '' && $r === '') continue;
            $rows[] = [$e, $r, trim((string)($_POST['name'][$i] ?? ''))];
            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) { $err = 'Please check the email address: ' . htmlspecialchars($e); break; }
            if (!isset($ROLES[$r])) { $err = 'Please choose a role for each signer.'; break; }
            if (isset($seen[$r])) { $err = 'Each role can only be used once.'; break; }
            $seen[$r] = 1; $signers[] = ['email' => $e, 'role' => $r];
        }
        if (!$err && (count($signers) < 1 || count($signers) > 6)) $err = 'Add between 1 and 6 signers.';
    }
    if (!$rows) $rows = [['', 'buyer1', '']];
    if ($err) { $result = ['ok' => false, 'msg' => $err]; }
    else {
        $_SESSION['sig_n'][] = time();
        $name = trim($_SESSION['name'] ?? '') ?: 'Your agent';
        // Contract packet and buyer disclosures: ONE request and ONE email, but two documents that come back and are filed as two separate PDFs.
        $jobs = [];
        if ($pick) $jobs[] = [$pick, 'Contract Packet', 'contract packet', 'pk'];
        if ($dpick) $jobs[] = [$dpick, 'Buyer Disclosures', 'buyer disclosures', 'dk'];
        $items = []; $meta = []; $fail = null;
        // Brokerage-side signers: whatever roles each document's signature tags call for (agent / broker / buyer agent).
        $needs = json_decode((string)($_POST['needs'] ?? ''), true); if (!is_array($needs)) $needs = [];
        $extraOk = ['agent', 'broker', 'buyeragent'];
        $docSigners = function ($which) use ($needs, $extraOk, $signers, &$fail) {
            $sg = $signers; $seenE = [];
            foreach ($signers as $x) { $seenE[strtolower($x['email'])] = 1; }
            foreach ((array)($needs[$which] ?? []) as $r) {
                if (!in_array($r, $extraOk, true)) continue;
                $e = trim((string)($_POST['xemail'][$r] ?? ''));
                if (!filter_var($e, FILTER_VALIDATE_EMAIL)) { $fail = ['ok' => false, 'msg' => 'Please enter the email address for the ' . $r . ' signature.']; return $sg; }
                $sg[] = ['email' => $e, 'role' => $r];
            }
            return $sg;
        };
        foreach ($jobs as $jb) {
            list($pp, $label, $what, $which) = $jb;
            if ($draft) { $raw = base64_decode((string)($_POST[$which . 'b64'] ?? ''), true); $file = ($raw !== false && substr($raw, 0, 4) === '%PDF') ? ['name' => $pp['name'], 'pdf' => $raw] : null; }
            else { $file = sg_packet_pdf($me, $pp['id']); }
            if (!$file) { $fail = ['ok' => false, 'msg' => 'Could not load the ' . $what . '. Please try again.']; break; }
            $items[] = [$file['pdf'], $file['name'], 'Florida Luxury Realty: please sign your ' . $what . ' for ' . $addr, $name . ' (Florida Luxury Realty) has sent you ' . ($what === 'contract packet' ? 'a contract packet' : 'your buyer disclosures') . ' for ' . $addr . ' to review and sign. If you have questions, reply to ' . $me . '.', $docSigners($which)];
            if ($fail) break;
            $meta[] = ['form' => $label . ' - ' . $addr, 'form_id' => $pp['id'], 'what' => $what, 'file' => $file['name']];
        }
        $sdj = json_decode((string)($_POST['sdjson'] ?? ''), true);
        if (!$fail && is_array($sdj) && $sdj) { // seller-provided disclosures the buyers also sign, in the same email
            $em = []; foreach ($signers as $x) { $em[$x['role']] = $x['email']; }
            $si = sg_seller_items($sdj, $em, $addr, $me, $name);
            if (!empty($si['err'])) { $fail = ['ok' => false, 'msg' => htmlspecialchars($si['err'])]; }
            else { foreach ($si['items'] as $x) { $items[] = $x; } foreach ($si['meta'] as $x) { $x['form_id'] = ''; $meta[] = $x; } }
        }
        if (!$fail) {
            $s = sg_send_multi($items, $signers, ['subject' => 'Florida Luxury Realty: please sign your documents for ' . $addr, 'message' => $name . ' (Florida Luxury Realty) has sent you documents for ' . $addr . ' to review and sign. If you have questions, reply to ' . $me . '.']);
            if (!$s['ok']) { $fail = $s; }
            else {
                $rec = ['id' => $s['id'], 'agent' => $me, 'form' => $meta[0]['form'], 'form_id' => $meta[0]['form_id'], 'doc' => $s['docs'][0], 'created' => gmdate('c'), 'signers' => $signers, 'folder' => null, 'offer_address' => $addr, 'hint' => $buyer['id'], 'completed' => false, 'filed' => null];
                for ($mi = 1; $mi < count($meta); $mi++) { $rec['extra'][] = ['form' => $meta[$mi]['form'], 'form_id' => $meta[$mi]['form_id'], 'doc' => $s['docs'][$mi], 'filed' => null]; }
                sg_save($s['id'], $rec);
                sg_draft_mark($me, $addr, $buyer['id'], true);
                if ($drRes || $draft) { sg_script_post(['op' => 'trashdrafts', 'email' => $me, 'hint' => $buyer['id'], 'address' => $addr]); }
                sg_dir();
                @file_put_contents(__DIR__ . '/logs/signature-requests.log', json_encode(['t' => gmdate('c'), 'agent' => $me, 'packet' => implode(' + ', array_map(function ($m) { return $m['file']; }, $meta)), 'address' => $addr, 'buyer_folder' => $buyer['name'], 'signers' => array_map(function ($x) { return $x['email'] . ' (' . $x['role'] . ')'; }, $signers), 'request' => $s['id']]) . "\n", FILE_APPEND | LOCK_EX);
            }
        }
        $result = $fail ?: ['ok' => true, 'id' => $s['id'], 'sent' => array_map(function ($m) { return $m['what']; }, $meta)];
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
<link rel="stylesheet" href="style.css?v=16">
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
<section class="hero"><h1>Send packet for signature</h1><p>Send a contract packet and the buyer disclosures from a buyer's folder in one email. Once the buyers sign, each signed copy is filed in Contract Offers under the property address.</p></section>
<main class="wrap">
<?php if (!$buyers): ?>
  <p class="note">You need a buyer folder first: <strong>My Transactions &rsaquo; 1 BUYERS &rsaquo; 1 Buyers &rsaquo; [buyer name]</strong>. <a href="wizard.php">Contract Forms Wizard</a></p>
<?php elseif ($result && $result['ok']): ?>
  <div class="panel sigbox"><h2>Sent</h2>
    <p>DigiSigner is emailing each signer a link for: <strong><?= htmlspecialchars(implode(' and ', $result['sent'] ?? ['contract packet'])) ?></strong> in one email. When everyone has signed, each signed document is filed separately in <strong>1 BUYERS / 2 Contract Offers / <?= htmlspecialchars($addr) ?></strong>. Track it on the <a href="signed.php">Signed Documents</a> page.</p>
    <?php if ($draft || $dr !== ''): ?><script src="flrdraft.js?v=1"></script><script>FLRDraft.del(<?= json_encode($buyer['id']) ?>).catch(function(){});FLRDraft.del(<?= json_encode($buyer['id'] . '|seller') ?>).catch(function(){});</script><?php endif; ?>
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
    <?php if ($drRes): ?><input type="hidden" name="dr" value="<?= htmlspecialchars($dr) ?>"><?php endif; ?>
    <?php if ($result): ?><p class="sigerr"><?= $result['msg'] ?></p><?php endif; ?>
    <?php if (!$draft && !$packets && !$discs): ?><p class="note">No PDF packets in this buyer's folder yet. Build one with the <a href="wizard.php?tx=<?= urlencode($buyer['id']) ?>">Contract Forms Wizard</a>, fill it in, and save it in this folder.</p>
    <?php else: ?>
<?php if ($draft): ?>
    <input type="hidden" name="draft" value="1"><textarea name="pkb64" id="pkb64" hidden></textarea><textarea name="dkb64" id="dkb64" hidden></textarea>
<?php else: ?>
    <p><label for="pk"><strong>Contract packet</strong></label><br>
    <select name="pk" id="pk" class="txsel">
      <option value="">Choose a packet...</option>
<?php foreach ($packets as $p): ?>
      <option value="<?= htmlspecialchars($p['id']) ?>"<?= $pk === $p['id'] ? ' selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
<?php endforeach; ?>
    </select></p>
    <p><label for="dk"><strong>Buyer Disclosures</strong> (sent in the same email, signed and filed as a separate document)</label><br>
    <select name="dk" id="dk" class="txsel">
      <option value="">Do not send disclosures</option>
<?php foreach ($discs as $p): ?>
      <option value="<?= htmlspecialchars($p['id']) ?>"<?= $dk === $p['id'] ? ' selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
<?php endforeach; ?>
    </select></p>
<?php endif; ?>
    <p><label for="addr"><strong>Property address</strong></label><br><input type="text" name="addr" id="addr" class="txsel" size="40" maxlength="120" value="<?= htmlspecialchars($addr) ?>" placeholder="e.g. 123 Palm Ave"></p>
    <p><strong>Who signs?</strong></p>
    <div id="signers">
<?php foreach ($rows as $row): $rl = $ROLES[$row[1]] ?? ''; ?>
      <div class="sg" style="display:block;margin:0 0 12px">
        <div style="font-weight:600;margin-bottom:4px"><?= htmlspecialchars($row[2] !== '' ? $row[2] . ' (' . $rl . ')' : $rl) ?></div>
        <input type="email" name="email[]" placeholder="<?= htmlspecialchars($rl) ?>'s email" value="<?= htmlspecialchars($row[0]) ?>" required style="width:100%;max-width:480px">
        <input type="hidden" name="role[]" value="<?= htmlspecialchars($row[1]) ?>"><input type="hidden" name="name[]" value="<?= htmlspecialchars($row[2]) ?>">
      </div>
<?php endforeach; ?>
    </div>
    <div id="xsign"></div>
    <input type="hidden" name="needs" id="needs" value=""><textarea name="sdjson" id="sdjson" hidden></textarea>
    <p id="sdnote" class="note" hidden></p>
    <div id="choice" hidden>
      <p><strong>Did the seller provide any disclosures the buyers need to sign?</strong></p>
      <p><a class="btn" href="seller_docs.php?tx=<?= urlencode($buyer['id']) ?>&amp;ret=<?= urlencode($drRes ? 'dr=' . rawurlencode($dr) : 'draft=1') ?>">Upload Seller Disclosures</a> <button type="button" class="btn ghost" id="noadd">No Additional Documents</button></p>
    </div>
    <p id="actions" hidden><button type="submit" class="btn" id="go">Email To Buyers For Signing</button> <a class="btn ghost" href="forms.php">Cancel</a></p>
    <?php endif; ?>
  </form>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="flrdraft.js?v=1"></script>
<script>
(function(){
var f=document.getElementById("sf");if(!f)return;
var DRAFT=<?= $draft ? "true" : "false" ?>,dd=null,DR=<?= json_encode($drRes ? $dr : "") ?>;
var TX=<?= json_encode($buyer['id']) ?>, SIG=<?= json_encode($SIG) ?>, LBL={agent:"Agent",broker:"Broker",buyeragent:"Agent"};
var go=document.getElementById("go"),choice=document.getElementById("choice"),actions=document.getElementById("actions"),noadd=document.getElementById("noadd"),xs=document.getElementById("xsign"),needs=document.getElementById("needs"),pk=document.getElementById("pk"),dk=document.getElementById("dk"),seq=0;
function esc(t){return String(t).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}
if(window.pdfjsLib)pdfjsLib.GlobalWorkerOptions.workerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
// Which brokerage-side signatures does this saved PDF ask for? Read the DigiSigner text tags, e.g. [s:agent], [s:broker], [s:buyeragent].
async function roles(id){
  var out={};if(!id||!window.pdfjsLib)return out;
  var data;
  if(DRAFT&&typeof id!=="string"){data=new Uint8Array(id).slice();}
  else{var r=await fetch("editor_pdf.php?tx="+encodeURIComponent(TX)+"&id="+encodeURIComponent(id)+(DR?"&dr="+encodeURIComponent(DR):""),{credentials:"same-origin"});
  if(!r.ok)throw new Error("Could not read the document");data=new Uint8Array(await r.arrayBuffer());}
  var doc=await pdfjsLib.getDocument({data:data}).promise;
  for(var n=1;n<=doc.numPages;n++){
    var tc=await (await doc.getPage(n)).getTextContent(), txt=tc.items.map(function(i){return i.str;}).join(" ").replace(/\s+/g," ");
    var re=/\[\s*[a-z]\s*:\s*(agent|broker|buyeragent)\s*\]/gi, m;
    while((m=re.exec(txt))){var role=m[1].toLowerCase();
      // a broker line that says "Broker or Authorized Associate" is signed by the agent
      var assoc=role==="broker"&&/authorized\s+associate/i.test(txt);
      if(out[role]!=="broker")out[role]=(role==="broker"&&!assoc)?"broker":"agent";}
  }
  return out;
}
async function refresh(){
  var my=++seq;go.disabled=true;choice.hidden=true;actions.hidden=true;xs.innerHTML='<p class="note" style="display:flex;align-items:center;gap:10px"><i class="bspin" style="border-color:rgba(0,107,104,.3);border-top-color:#006b68"></i>Checking who needs to sign...</p>';
  var want={pk:{},dk:{}},all={};
  try{
    var a,b;
    if(DRAFT){if(!dd)dd=await FLRDraft.get(TX);if(!dd||!(dd.completed||dd.packet))throw new Error("The documents are no longer in this browser. Please start again from the wizard");a=await roles(dd.completed||dd.packet);b=dd.disc?await roles(dd.disc):{};}
    else{a=await roles(pk&&pk.value);b=await roles(dk&&dk.value);}
    if(my!==seq)return;
    want.pk=a;want.dk=b;
    Object.keys(a).forEach(function(k){all[k]=a[k];});Object.keys(b).forEach(function(k){if(!all[k]||b[k]==="broker")all[k]=b[k];});
  }catch(e){if(my!==seq)return;xs.innerHTML='<p class="sgerr">'+esc(e.message)+'. The buyers can still be sent the documents, but check whether the agent or broker must sign.</p>';go.disabled=false;needs.value="{}";choice.hidden=false;return;}
  var h="",keys=Object.keys(all);
  if(keys.length)h+='<p><strong>Also signing for Florida Luxury Realty</strong></p>';
  keys.forEach(function(role){var src=all[role]==="broker"?SIG.broker:SIG.agent,title=all[role]==="broker"?"Broker":(role==="broker"?"Broker or Authorized Associate (the agent)":LBL[role]);
    h+='<div class="sg" style="display:block;margin:0 0 12px"><div style="font-weight:600;margin-bottom:4px">'+esc((src.name||title)+" ("+title+")")+'</div><input type="email" name="xemail['+role+']" value="'+esc(src.email)+'" placeholder="'+esc(title)+' email" required style="width:100%;max-width:480px"></div>';});
  xs.innerHTML=h;needs.value=JSON.stringify({pk:Object.keys(want.pk),dk:Object.keys(want.dk)});go.disabled=false;choice.hidden=false;
}
async function loadSd(){
  if(<?= isset($_GET['sd']) ? 'false' : 'true' ?>)return;
  try{var sd=await FLRDraft.get(TX+"|seller");if(sd&&sd.docs&&sd.docs.length){window.__sd=sd;
    var n=document.getElementById("sdnote");n.innerHTML="<strong>Seller disclosures included in this email:</strong> "+sd.docs.map(function(d){return esc(d.name);}).join(", ")+" &nbsp; <a href=\"#\" id=\"sdrm\">Remove</a>";n.hidden=false;
    document.getElementById("sdrm").addEventListener("click",async function(e){e.preventDefault();window.__sd=null;await FLRDraft.del(TX+"|seller");n.hidden=true;choice.hidden=false;actions.hidden=true;});
    choice.hidden=true;actions.hidden=false;}}catch(e){}
}
var _r0=refresh;refresh=async function(){await _r0();await loadSd();};
noadd.addEventListener("click",function(){choice.hidden=true;actions.hidden=false;});
if(pk)pk.addEventListener("change",refresh);if(dk)dk.addEventListener("change",refresh);refresh();
function b64(u8){var x="",u=new Uint8Array(u8);for(var q=0;q<u.length;q+=32768){x+=String.fromCharCode.apply(null,u.subarray(q,q+32768));}return btoa(x);}
f.addEventListener("submit",function(){if(window.__sd){document.getElementById("sdjson").value=JSON.stringify(window.__sd.docs.map(function(d){return {name:d.name,b64:b64(d.bytes),fields:d.fields};}));}
  if(DRAFT&&dd){document.getElementById("pkb64").value=b64(dd.completed||dd.packet);document.getElementById("dkb64").value=dd.disc?b64(dd.disc):"";}
  go.disabled=true;go.innerHTML='<span class="bspin"></span>Sending...';});
})();
</script>

<?php endif; endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
