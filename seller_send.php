<?php
// "Send To Listing Agent": review the email, choose the signed documents, send a download link to the listing agent.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/sharelib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? ''); $tx = (string)($_GET['tx'] ?? '');
$P = sh_plan($me, $tx); $h = function ($x) { return htmlspecialchars((string)$x); };
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Send to listing agent - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=20">
<style>body{background:#f4f8f8}.shw{max-width:760px;margin:0 auto;padding:20px 14px}.shc{background:#fff;border:1px solid #dbe6e5;border-radius:14px;padding:20px}.shc label{display:block;font-weight:700;margin:14px 0 4px}.shc input[type=text],.shc input[type=email],.shc textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #c9d9d8;border-radius:8px;font:inherit}.shc textarea{min-height:260px}.sdoc{display:flex;gap:8px;align-items:center;font-weight:400!important;margin:6px 0!important}</style></head><body>
<div class="shw"><div class="shc">
<h2 style="margin-top:0">Send To Listing Agent</h2>
<?php if (empty($P['ok'])): ?><p class="sigerr"><?= $h($P['msg'] ?? 'Could not load this record.') ?></p>
<?php else: ?>
<p>Buyer: <strong><?= $h($P['name']) ?></strong><?= $P['address'] !== '' ? '<br>Property: <strong>' . $h($P['address']) . '</strong>' : '' ?></p>
<p class="note">The contract is not valid until the seller signs it. The listing agent gets a secure download link (good for <?= (int)SH_DAYS ?> days), not attachments.</p>
<?php if ($P['waiting']): ?><p class="note" style="background:#fff7e0;border-color:#e0a000">Still waiting for signatures on: <?= $h(implode('; ', $P['waiting'])) ?>. Send once the buyers have signed.</p><?php endif; ?>
<?php if (!$P['documents']): ?><p class="sigerr">There is no signed contract packet or seller disclosure on this record yet.</p>
<?php else: ?>
<form id="f" onsubmit="return false">
  <label for="to">Listing agent's email<?= $P['to_name'] !== '' ? ' (' . $h($P['to_name']) . ($P['brokerage'] !== '' ? ', ' . $h($P['brokerage']) : '') . ')' : '' ?></label>
  <input type="email" id="to" value="<?= $h($P['to']) ?>" placeholder="listing agent email" autocomplete="off">
  <label>Documents to send</label>
<?php foreach ($P['documents'] as $d): ?>
  <label class="sdoc"><input type="checkbox" class="dc" value="<?= $h($d['ref']) ?>" checked> <?= $h($d['title']) ?><?= $d['signed_on'] !== '' ? ' (signed ' . $h(date('M j, Y', strtotime($d['signed_on']))) . ')' : '' ?></label>
<?php endforeach; ?>
  <label for="sub">Subject</label><input type="text" id="sub" maxlength="200" value="<?= $h($P['subject']) ?>">
  <label for="msg">Message <span class="note" style="padding:1px 8px;font-weight:400">Keep {LINK} where the download link should go.</span></label>
  <textarea id="msg"><?= $h($P['body']) ?></textarea>
  <label class="sdoc"><input type="checkbox" id="cc" checked> Send me a copy</label>
  <p id="out" class="note" hidden></p>
  <p style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px">
    <button type="button" class="btn" id="send"<?= $P['gmail'] ? '' : ' disabled' ?>>Send Email</button>
    <button type="button" class="btn ghost" id="app">Open In My Email App</button>
    <a class="btn ghost" href="docs_view.php?tx=<?= urlencode($tx) ?>">Back To Compliance Document Records</a></p>
<?php if (!$P['gmail']): ?><p class="note">To send straight from here, connect Gmail in Settings (Connectors). Until then, use <strong>Open In My Email App</strong>: it opens an email to the listing agent with the download link already in it.</p><?php endif; ?>
<?php if (!empty($P['last_sent'])): ?><p class="note">Last sent to <?= $h($P['last_sent']['to']) ?> on <?= $h(date('M j, Y', strtotime($P['last_sent']['at']))) ?>.</p><?php endif; ?>
</form>
<script>
(function(){
var CSRF=<?= json_encode($_SESSION['csrf']) ?>,TX=<?= json_encode($tx) ?>,out=document.getElementById("out");
function say(t,bad){out.textContent=t;out.className="note"+(bad?" sigerr":"");out.hidden=!t;}
function refs(){return [].filter.call(document.querySelectorAll(".dc"),function(c){return c.checked;}).map(function(c){return c.value;});}
async function post(a,extra){var f=new URLSearchParams();f.append("csrf",CSRF);f.append("tx",TX);f.append("action",a);refs().forEach(function(r){f.append("refs[]",r);});f.append("to",document.getElementById("to").value.trim());
  Object.keys(extra||{}).forEach(function(k){f.append(k,extra[k]);});var r=await fetch("seller_send_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:f.toString()});return r.json();}
document.getElementById("send").addEventListener("click",async function(){
  var b=this;b.disabled=true;say("Sending...");
  try{var j=await post("send",{subject:document.getElementById("sub").value,body:document.getElementById("msg").value,copy:document.getElementById("cc").checked?"1":""});
    if(j.ok){say("Sent. The listing agent has a download link that works until "+j.expires+".");}else{say(j.msg||"Could not send.",1);b.disabled=false;}}
  catch(e){say("Something went wrong: "+e.message,1);b.disabled=false;}
});
document.getElementById("app").addEventListener("click",async function(){
  var b=this;b.disabled=true;say("Making the link...");
  try{var j=await post("link");if(!j.ok){say(j.msg||"Could not make the link.",1);b.disabled=false;return;}
    var body=document.getElementById("msg").value.split("{LINK}").join(j.url+"\n(This link works until "+j.expires+".)");
    var to=document.getElementById("to").value.trim();
    await post("marksent",{to:to});
    say("Opening your email app...");
    location.href="mailto:"+encodeURIComponent(to)+"?subject="+encodeURIComponent(document.getElementById("sub").value)+"&body="+encodeURIComponent(body);
    setTimeout(function(){b.disabled=false;},1500);}
  catch(e){say("Something went wrong: "+e.message,1);b.disabled=false;}
});
})();
</script>
<?php endif; endif; ?>
</div></div></body></html>
