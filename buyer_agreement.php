<?php
// Buyer Agreement stage screen (opens under the Pipeline stages on a buyer record). Before it's signed: the agreement
// itself, open right here, with Download, Send for Signing (DigiSigner) and Upload signed copy. Once DigiSigner returns
// the signed copy, or the agent uploads one, this screen shows the signed copy instead. Data: baglib.php.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/baglib.php";
require_once __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
$self = 'buyer_agreement.php?tx=' . urlencode($tx) . (sp_embed() ? '&embed=1' : '') . (!empty($_GET['bare']) ? '&bare=1' : '');
$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'upload') {
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) $err = 'That file is too large to upload. Please choose a smaller PDF.';
    elseif (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $err = 'This page expired. Please reload and try again.';
    else { list($rid, $err) = bag_upload($me, $tx, (string)($_POST['kind'] ?? ''), $_FILES['pdf'] ?? [], (string)($_POST['on'] ?? ''), (string)($_POST['expires'] ?? ''), (string)($_POST['homes'] ?? '')); if ($err === '') { header('Location: ' . $self . '&ok=up'); exit; } }
}
// Check DigiSigner for anything still out for signature on this record, so a just-signed copy shows right away.
$all = sg_for_agent($me);
$mine = array_values(array_filter($all, function ($r) use ($tx) { return (string)($r['folder']['id'] ?? '') === $tx && empty($r['completed']) && bag_kind_of($r['form'] ?? ''); }));
if ($mine) { sg_refresh_many($mine); }
$st = bag_state($me, $tx);
$sig = $st['signed']; $kind = $sig ? bag_kind_of($sig['form']) : null;
$advanced = $sig ? ps_advance_after_agreement($me, $tx) : false; // signed: the record's current stage moves on to Shop Homes
$k = isset(BAG_FORMS[$_GET['k'] ?? '']) ? $_GET['k'] : 'exclusive';
$pend = $sig ? null : ($st['pending'][0] ?? null); // out for signature: the screen waits for the signers
if ($pend) $k = bag_kind_of($pend['form']) ?: $k;
$sgn = (!$sig && !$pend) ? bag_signers($me, $tx) : null;
$h = function ($x) { return htmlspecialchars((string)$x); };
$when = function ($iso) { $t = strtotime((string)$iso); return $t ? date('M j, Y', $t) : ''; };
sp_head('Buyer Agreement', $tx);
?>
<style>
.bag{max-width:none}
.bagh{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;margin:0 0 12px}.bagh h2{margin:0;font-size:1.2rem}
.bagt{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 10px}.bagt a{font-weight:700;font-size:.85rem;padding:4px 12px;border-radius:10px;border:1px solid var(--line);background:#fff;color:var(--teal);text-decoration:none}
.bagt a.on{background:var(--teal);color:#fff;border-color:var(--teal)}
.bagnote{margin:0 0 12px;font-size:.9rem;color:#4b5b5d}
.bagact{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 12px}
.bagact .btn{border:0;cursor:pointer;font:inherit;font-size:.88rem;font-weight:700;padding:4px 14px;line-height:1.5;border-radius:10px;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.bagact .btn.ghost{background:#fff;color:var(--teal);border:1.5px solid var(--teal);padding:2.5px 12.5px}
.bagact .btn.up{background:#e07b39;color:#fff}
.bagup{margin:0 0 12px;padding:12px 14px;border:1px solid #f0d2bd;background:#fff8f2;border-radius:12px}
.bagup .btn{padding:4px 14px!important;font-size:.88rem}.bagup form{display:flex;flex-wrap:wrap;gap:10px 14px;align-items:flex-end}.bagup label{display:flex;flex-direction:column;font-size:.8rem;font-weight:700;color:#4b5b5d;gap:3px}
.bagup select,.bagup input{font:inherit;font-size:.9rem;padding:7px 9px;border:1px solid var(--line);border-radius:8px;background:#fff}
.bagup p{flex-basis:100%;margin:0;font-size:.82rem;color:#5a6a6c}
.bagpend{margin:0 0 12px;padding:10px 12px;border-left:4px solid #d49a00;background:#fdf6e3;border-radius:8px;font-size:.9rem}
.bagok{margin:0 0 12px;padding:10px 12px;border-left:4px solid #2e7d32;background:#e8f5ea;border-radius:8px;font-size:.92rem}
.bagwarn{margin:0 0 12px;padding:10px 12px;border-left:4px solid #b26a00;background:#fff4e5;border-radius:8px;font-size:.9rem}
.bagview{width:100%;max-height:820px;overflow:auto;border:1px solid var(--line);border-radius:12px;background:#e9eeee;padding:12px;box-sizing:border-box}.bagfb{margin:0 0 8px;font-size:.9rem;color:#5a6a6c}.bagpg{position:relative;margin:0 0 12px}.bagview .bagpg canvas{margin:0!important}.bagfld{position:absolute;box-sizing:border-box;margin:0;padding:0 3px;border:1px solid rgba(3,111,114,.45);background:rgba(224,244,244,.55);font-family:Helvetica,Arial,sans-serif;color:#111;border-radius:2px;resize:none;line-height:1.15}.bagfld:focus{outline:2px solid var(--teal);background:#fff}textarea.bagfld{padding:2px 3px}input[type=checkbox].bagfld,input[type=radio].bagfld{padding:0;accent-color:var(--teal)}.bagview canvas{display:block;width:100%;height:auto;margin:0 auto 12px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.15)}.bagview iframe{width:100%;height:760px;border:0}.bagfill{margin:0 0 12px;padding:9px 12px;border-left:4px solid var(--teal);background:#eef7f7;border-radius:8px;font-size:.88rem;color:#2a3638}.bagload{margin:0;color:#5a6a6c;padding:20px;text-align:center}
.bagmeta{font-size:.88rem;color:#4b5b5d;margin:0 0 12px}
.bagwait{margin:0 0 12px;padding:12px 14px;border:1px solid #f0dca8;background:#fdf8ea;border-radius:12px}.bagwh{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 6px}.bagwh h2{margin:0;font-size:1.1rem}
.bagdot{width:10px;height:10px;border-radius:50%;background:#d49a00;box-shadow:0 0 0 0 rgba(212,154,0,.6);animation:bagp 1.6s infinite}@keyframes bagp{70%{box-shadow:0 0 0 8px rgba(212,154,0,0)}100%{box-shadow:0 0 0 0 rgba(212,154,0,0)}}
.bagcnt{font-size:.78rem;font-weight:700;background:#fff;border:1px solid #f0dca8;border-radius:99px;padding:2px 9px;color:#8a5a00}
.bagsl{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:4px}.bagsl li{font-size:.9rem;display:flex;gap:8px;align-items:baseline;flex-wrap:wrap}.bagsl li span{font-size:.75rem;font-weight:700;color:#8a5a00;min-width:90px}.bagsl li.ok span{color:#2e7d32}
.bagsend{margin:0 0 12px;padding:12px 14px;border:1px solid #cfe5e5;background:#f0f8f8;border-radius:12px}.bagsend .bagsl li span{color:var(--teal)}
.bagact .btn[hidden]{display:none}
.bagq{margin:0 0 12px;padding:12px 14px;border:1.5px solid #9fd3cf;background:linear-gradient(135deg,#eef9f8,#fff);border-radius:12px}.bagq h3{margin:0 0 8px;font-size:1rem}.bagq h3 small{display:block;font-weight:400;font-size:.8rem;color:#5a6a6c}
.bagqg{display:flex;flex-wrap:wrap;gap:10px 16px}.bagqg>div{display:flex;flex-direction:column;font-size:.82rem;font-weight:600}.bagqg>.full{flex-basis:100%}.bagqg label small{font-weight:400;color:#5a6a6c}
.bagq input[type=text],.bagq input[type=date],.bagq textarea{font:inherit;font-size:.9rem;padding:7px 9px;border:1px solid var(--line);border-radius:8px;background:#fff}
.bagch{display:flex;flex-wrap:wrap;gap:6px}.bagq .chip{display:inline-flex;gap:5px;align-items:center;font-weight:600;font-size:.84rem;border:1px solid #cfe5e5;border-radius:99px;padding:4px 10px;background:#fff;cursor:pointer}
.bagqq{display:flex;gap:6px;margin-top:5px}.bagqq .btn.sm{padding:1px 10px!important;font-size:.76rem!important}.bagqq .btn.on{background:var(--teal,#036f72)!important;color:#fff!important;border-color:var(--teal,#036f72)!important}
@media(max-width:640px){.bagview{max-height:600px;padding:6px}}
</style>
<div class="bag">
<?php if ($err): ?>  <p class="sigerr"><?= $h($err) ?></p>
<?php elseif (($_GET['ok'] ?? '') === 'up'): ?>  <p class="bagok">Signed copy uploaded and saved to this buyer record.</p>
<?php elseif (($_GET['ok'] ?? '') === 'sent' && $pend): ?>  <p class="bagok">Sent. Each signer gets an email from DigiSigner with a link to sign.</p>
<?php endif; ?>
<?php if ($sig): $src = 'bagdoc.php?rid=' . urlencode($sig['id']); ?>
  <div class="bagh"><h2>Signed <?= $h($sig['form']) ?></h2><span class="due" style="background:#e3f4e8;color:#2e7d32;font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:99px">&#10003; Signed <?= $h($when($sig['completed_at'] ?? $sig['created'])) ?></span></div>
  <p class="bagmeta"><?= !empty($sig['uploaded']) ? 'Uploaded by you' : 'Signed through DigiSigner' ?><?php if (!empty($sig['filed']['url'])): ?> &middot; Filed in <a href="<?= $h($sig['filed']['url']) ?>" target="_blank" rel="noopener">the transaction folder</a><?php endif; ?></p>
<?php if ($kind === 'exclusive'): ?>  <p class="bagok">Exclusive Buyer Brokerage Agreement on file. This buyer doesn't need to sign anything further to see more homes.</p>
<?php elseif ($kind === 'showing'): ?>  <p class="bagwarn">Showing Agreement on file. It only covers the home(s) named on the signed form. Before showing a home it doesn't name, get a new Showing Agreement signed, or move up to an Exclusive Buyer Brokerage Agreement.</p>
<?php endif; ?>
  <div class="bagact">
    <a class="btn ghost" href="<?= $h($src) ?>&amp;dl=1">&#8681; Download signed copy</a>
    <a class="btn ghost" href="<?= $h($src) ?>" target="_blank" rel="noopener">Open full size</a>
<?php if ($kind === 'showing'): ?>    <a class="btn" href="sign.php?f=<?= BAG_SHOWING_FORM_ID ?>&amp;s=b&amp;tx=<?= urlencode($tx) ?>" target="_top">Send a new Showing Agreement</a>
    <a class="btn ghost" href="sign.php?f=<?= BAG_EXCLUSIVE_FORM_ID ?>&amp;s=b&amp;tx=<?= urlencode($tx) ?>" target="_top">Move up to Exclusive</a>
<?php endif; ?>
    <button type="button" class="btn ghost" id="bagupbtn">Upload a different signed copy</button>
  </div>
<?php else: $f = BAG_FORMS[$k]; $src = 'bagdoc.php?blank=' . $k; ?>
<?php if ($pend): $ss = (array)($pend['signers_status'] ?? []); $nDone = count(array_filter($ss)); $nAll = count($pend['signers'] ?? []); ?>
  <div class="bagwait">
    <div class="bagwh"><span class="bagdot"></span><h2>Waiting for signers to complete</h2><span class="bagcnt"><?= $nDone ?> of <?= $nAll ?> signed</span></div>
    <p class="bagmeta" style="margin:0 0 8px"><b><?= $h($pend['form']) ?></b> sent through DigiSigner on <?= $h($when($pend['created'])) ?>. The signed agreement shows here as soon as everyone has signed, and you'll get a notice.</p>
    <ul class="bagsl"><?php foreach (($pend['signers'] ?? []) as $sg): $done = !empty($ss[$sg['email']]); ?><li class="<?= $done ? 'ok' : '' ?>"><span><?= $done ? '&#10003; Signed' : 'Waiting' ?></span><b><?= $h(($sg['name'] ?? '') ?: $sg['email']) ?></b> <?= $h($sg['email']) ?></li><?php endforeach; ?></ul>
    <div class="bagact" style="margin:10px 0 0"><a class="btn ghost" href="<?= $h($self) ?>">&#8635; Check again</a><button type="button" class="btn up" id="bagupbtn">&#8679; Upload signed copy instead</button></div>
  </div>
<?php else: ?>
  <div class="bagt" role="tablist"><?php foreach (BAG_FORMS as $kk => $ff): ?><a role="tab" href="buyer_agreement.php?tx=<?= urlencode($tx) ?>&amp;k=<?= $kk ?><?= sp_embed() ? '&amp;embed=1' : '' ?><?= !empty($_GET['bare']) ? '&amp;bare=1' : '' ?>" class="<?= $kk === $k ? 'on' : '' ?>" aria-selected="<?= $kk === $k ? 'true' : 'false' ?>"><?= $h($ff['title']) ?></a><?php endforeach; ?></div>
  <p class="bagnote"><?= $h($f['note']) ?></p>
  <?php $T = bag_terms($me, $tx); $isEx = $k === 'exclusive'; ?>
  <div class="bagq" id="bagq">
    <h3>Agreement setup <small>Answer these first. They fill the form below and are kept on the record for later forms.</small></h3>
    <div class="bagqg">
<?php if ($isEx): ?>      <div class="full"><label>Property type</label><div class="bagch"><?php foreach (BAG_TYPES as $ty): ?><label class="chip"><input type="checkbox" name="types" value="<?= $h($ty) ?>"<?= in_array($ty, $T['types'], true) ? ' checked' : '' ?>> <?= $h(BAG_TYPE_FORM[$ty] ?? $ty) ?></label><?php endforeach; ?></div></div>
      <div class="full"><label for="bq_areas">Location <small>(counties or cities)</small></label><input id="bq_areas" type="text" maxlength="200" value="<?= $h($T['areas']) ?>" placeholder="e.g. Pasco County, Hernando County, Trinity"></div>
<?php else: ?>      <div class="full"><label for="bq_homes">Home(s) this agreement covers <small>(one per line; scheduled showings fill in automatically if left blank)</small></label><textarea id="bq_homes" rows="3" maxlength="400"><?= $h($T['homes']) ?></textarea></div>
<?php endif; ?>
      <div><label for="bq_comm">Commission rate (% of purchase price)</label><input id="bq_comm" type="text" inputmode="decimal" value="<?= $h($T['comm']) ?>" size="6"></div>
      <div><label for="bq_admin">FLR admin fee ($)</label><input id="bq_admin" type="text" inputmode="decimal" value="<?= $h($T['admin']) ?>" size="8"><label class="chip" style="margin-top:4px"><input type="checkbox" id="bq_adminok"<?= $T['admin_ok'] ? ' checked' : '' ?>> Admin fee confirmed (FLR default is $325)</label></div>
<?php if ($isEx): ?>      <div><label for="bq_exp">Expiration date</label><input id="bq_exp" type="date" value="<?= $h($T['expires']) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>"><span class="bagqq"><?php foreach ([['90 days', '+90 days'], ['6 months', '+6 months'], ['1 year', '+1 year']] as $q): ?><button type="button" class="btn ghost sm" data-d="<?= date('Y-m-d', strtotime($q[1])) ?>"><?= $q[0] ?></button><?php endforeach; ?></span></div>
<?php endif; ?>
      <div class="full"><label for="bq_special">Special terms <small>(optional, printed on the Additional Terms lines)</small></label><textarea id="bq_special" rows="2" maxlength="340"><?= $h($T['special']) ?></textarea></div>
    </div>
    <div class="bagact" style="margin:8px 0 0"><button type="button" class="btn" id="bq_apply">Apply to the agreement</button><span class="bagmeta" id="bq_msg" style="margin:0"></span></div>
  </div>
<?php endif; ?>
<?php endif; ?>
<?php if (!$sig && !$pend): ?>  <p class="bagfill" id="bagfill" hidden></p>
<?php endif; ?>
  <div class="bagview" id="bagview"<?= $pend ? " hidden" : "" ?><?= ($sig || $pend) ? '' : ' data-fill="' . $h('bag_prefill.php?tx=' . urlencode($tx) . '&kind=' . $k) . '" data-name="' . $h(BAG_FORMS[$k]['title'] . ' - ' . date('Y-m-d') . '.pdf') . '"' ?> data-src="<?= $h($src) ?>" role="document" aria-label="<?= $h($sig ? 'Signed ' . $sig['form'] : BAG_FORMS[$k]['title']) ?>"><p class="bagload">Loading the agreement&hellip;</p></div>
<?php if (!$sig && !$pend): ?>
  <div class="bagact">
    <a class="btn ghost" id="bagdl" href="<?= $h($src) ?>&amp;dl=1">&#8681; Download</a>
    <button type="button" class="btn" id="bagsend">&#9998; Send for Signing</button>
    <button type="button" class="btn up" id="bagupbtn"<?= ag_kv('bag_dl_' . $tx) === null ? ' hidden' : '' ?>>&#8679; Upload signed copy</button>
  </div>
  <div class="bagsend" id="bagsendbox" hidden>
<?php if ($sgn['missing'] !== ''): ?>    <p class="sigerr" style="margin:0 0 8px"><?= $h($sgn['missing']) ?></p>
    <div class="bagact" style="margin:0"><a class="btn" href="contact_edit.php?tx=<?= urlencode($tx) ?>&amp;side=buyer&amp;embed=1">&#9998; Edit contact info</a><a class="btn ghost" href="sign.php?f=<?= $h($f['id']) ?>&amp;s=b&amp;tx=<?= urlencode($tx) ?>" target="_top">Choose signers myself</a></div>
<?php else: ?>    <p style="margin:0 0 6px;font-weight:700">Send the <?= $h($f['title']) ?> to:</p>
    <ul class="bagsl"><?php foreach ($sgn['signers'] as $sg): ?><li><span><?= $h($sg['label']) ?></span><b><?= $h($sg['name']) ?></b> <?= $h($sg['email']) ?></li><?php endforeach; ?></ul>
    <p class="bagmeta" style="margin:6px 0 8px">From the client's contact info. You sign first and the buyer signs last; the "copy returned" date fills in when the buyer signs, and everyone is emailed the fully signed copy right away. Each signer gets an email from DigiSigner with a link to sign.</p>
    <div class="bagact" style="margin:0"><button type="button" class="btn" id="bagsendgo">Send now</button><button type="button" class="btn ghost" id="bagsendx">Cancel</button><a href="sign.php?f=<?= $h($f['id']) ?>&amp;s=b&amp;tx=<?= urlencode($tx) ?>" target="_top" style="font-size:.85rem">Use different signers</a></div>
    <p class="sigerr" id="bagsenderr" hidden style="margin:8px 0 0"></p>
<?php endif; ?>
  </div>
<?php endif; ?>
  <div class="bagup" id="bagup" hidden>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="upload"><input type="hidden" name="tx" value="<?= $h($tx) ?>">
      <label>Which agreement<select name="kind"><?php foreach (BAG_FORMS as $kk => $ff): ?><option value="<?= $kk ?>"<?= $kk === ($sig ? $kind : $k) ? ' selected' : '' ?>><?= $h($ff['title']) ?></option><?php endforeach; ?><option value="extension">Extension of the Buyer Agreement</option></select></label>
      <label>Date signed<input type="date" name="on" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></label>
      <label id="bagexpl">Expiration date <small>(from the agreement)</small><input type="date" name="expires" value="<?= date('Y-m-d', strtotime('+6 months')) ?>"></label>
      <label id="baghomel" hidden>Home(s) it covers <small>(optional)</small><input type="text" name="homes" maxlength="200" placeholder="e.g. 123 Main St"></label>
      <label>Signed PDF<input type="file" name="pdf" accept="application/pdf,.pdf" required></label>
      <button type="submit" class="btn" style="border:0;cursor:pointer;font:inherit;font-weight:700;padding:9px 16px;border-radius:10px">Save signed copy</button>
      <p>Use this for an agreement signed in person or outside DigiSigner. It's saved to this buyer record and filed in the transaction folder.</p>
    </form>
  </div>
  <p style="margin:12px 0 0"><a href="https://drive.google.com/drive/folders/<?= urlencode($tx) ?>" target="_blank" rel="noopener">Open the transaction folder</a></p>
</div>
<script>(function(){var a=document.getElementById("bq_apply");if(!a)return;var $=function(i){return document.getElementById(i);};
function qqMark(){if(!$("bq_exp"))return;var v=$("bq_exp").value;document.querySelectorAll(".bagqq .btn").forEach(function(x){x.classList.toggle("on",x.dataset.d===v);});} // highlight the quick button that matches the date in the box (6 months is the default)
document.querySelectorAll(".bagqq .btn").forEach(function(x){x.onclick=function(){$("bq_exp").value=x.dataset.d;qqMark();};});if($("bq_exp")){$("bq_exp").addEventListener("input",qqMark);$("bq_exp").addEventListener("change",qqMark);}qqMark();
a.onclick=async function(){var m=$("bq_msg");m.style.color="";m.textContent="Saving...";a.disabled=true;
  var o={csrf:<?= json_encode($_SESSION['csrf']) ?>,tx:<?= json_encode($tx) ?>,kind:<?= json_encode($k) ?>,comm:$("bq_comm").value,admin:$("bq_admin").value,admin_ok:$("bq_adminok").checked,special:$("bq_special").value};
  if($("bq_areas")){o.areas=$("bq_areas").value;o.expires=$("bq_exp").value;o.types=[].map.call(document.querySelectorAll('input[name=types]:checked'),function(c){return c.value;});}else{o.homes=$("bq_homes").value;}
  try{var r=await fetch("bag_terms_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(o)}),j=await r.json();
    if(!j.ok){m.style.color="#b3261e";m.textContent=j.msg||"Could not save.";a.disabled=false;return;}
    m.textContent="Saved. Updating the form...";location.reload();}catch(e){m.style.color="#b3261e";m.textContent="Could not save. Please try again.";a.disabled=false;}};})();</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script>(async function(){var v=document.getElementById("bagview");if(!v)return;var src=v.dataset.src,fillUrl=v.dataset.fill,bytes=null,doc=null,form=null,editable=false;
function fallback(why){v.innerHTML='<p class="bagfb">The page viewer could not load here'+(why?' ('+String(why).replace(/[<>&]/g,"")+')':'')+'. <a href="'+src+'" target="_blank" rel="noopener">Open the PDF in a new tab</a> or use Download above.</p><iframe src="'+src+'#view=FitH" title="Agreement"></iframe>';}
function nice(n){return n.replace(/[_.]+/g," ").replace(/([a-z])([A-Z])/g,"$1 $2").trim();}
function today(){var d=new Date();return ("0"+(d.getMonth()+1)).slice(-2)+"/"+("0"+d.getDate()).slice(-2)+"/"+d.getFullYear();}
// "Copy returned to Consumer" section at the bottom: a download is handed over in person today; a DigiSigner send goes by email and
// the date is the last signer's signing date (the buyer signs last, so a date tag is placed on that line for DigiSigner to fill).
async function getBytes(mode){if(!editable)return bytes;var raw=await doc.save();if(!mode)return raw;
  var d2=await PDFLib.PDFDocument.load(raw),f2=d2.getForm();
  try{var comp={};f2.getFields().forEach(function(f){var n=f.getName();if(/^(Comp_|Price_Retainer$|Price_CancellationFee$|Term_ProtectionDays$|Date_Termination$)/.test(n)&&f.getText){var t=(f.getText()||"").trim();if(t)comp[n]=t;}});
    var cp=fetch("offer_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:<?= json_encode($_SESSION['csrf']) ?>,action:"savecomp",tx:<?= json_encode($tx) ?>,kind:<?= json_encode($k) ?>,comp:comp})}).catch(function(){});
    if(mode==="send")await cp;}catch(e){} // the commission agreed here is kept on the record for the offer and the transaction
  try{var g=f2.getRadioGroup("Rad_ReturnedBy");g.select(mode==="send"?"Email":"PersonalDelivery");}catch(e){}
  try{var t=f2.getTextField("Date_CopyReturned");
    if(mode==="send"){var w=t.acroField.getWidgets()[0],r=w.getRectangle(),pg=d2.getPages().find(function(x){return x.ref===w.P();})||d2.getPages()[d2.getPageCount()-1];f2.removeField(t);pg.drawText("[d:buyer1]",{x:r.x+2,y:r.y+3,size:8});}
    else{t.setFontSize(10);t.setText(today());}}catch(e){}
  return await d2.save();}
window.BAG_GET=getBytes;
function sizeInputs(){v.querySelectorAll(".bagpg").forEach(function(w){var k=w.clientWidth/parseFloat(w.dataset.pw);w.querySelectorAll("[data-h]").forEach(function(i){var h=parseFloat(i.dataset.h)*k;i.style.fontSize=Math.max(8,Math.min(h*0.62,15*k+2))+"px";});});}
function widgetsOf(){var out=[],pages=doc.getPages();form.getFields().forEach(function(f){var t=(f instanceof PDFLib.PDFTextField)?"PDFTextField":(f instanceof PDFLib.PDFCheckBox)?"PDFCheckBox":(f instanceof PDFLib.PDFRadioGroup)?"PDFRadioGroup":"";if(!t)return;f.acroField.getWidgets().forEach(function(w){var r=w.getRectangle(),pr=w.P(),pi=pages.findIndex(function(pg){return pg.ref===pr;});if(pi<0&&pages.length===1)pi=0;if(pi>=0)out.push({f:f,t:t,r:r,pi:pi,on:t==="PDFRadioGroup"?w.getOnValue():null});});});return out;}
async function render(data,withFields){if(!window.pdfjsLib)throw new Error("no viewer");pdfjsLib.GlobalWorkerOptions.workerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
  if(!data){var rr=await fetch(src,{credentials:"same-origin"});if(!rr.ok)throw new Error("file "+rr.status);data=new Uint8Array(await rr.arrayBuffer());}
  var pdf=await pdfjsLib.getDocument({data:data.slice()}).promise;v.innerHTML="";var w=Math.max(300,v.clientWidth-24),dpr=Math.min(2,window.devicePixelRatio||1),W=withFields?widgetsOf():[];
  for(var n=1;n<=pdf.numPages;n++){var pg=await pdf.getPage(n),vp=pg.getViewport({scale:1}),v2=pg.getViewport({scale:w/vp.width*dpr}),wrap=document.createElement("div"),c=document.createElement("canvas");wrap.className="bagpg";wrap.dataset.pw=vp.width;c.width=v2.width;c.height=v2.height;wrap.appendChild(c);v.appendChild(wrap);
    await pg.render({canvasContext:c.getContext("2d"),viewport:v2,annotationMode:withFields?0:1}).promise;
    W.filter(function(x){return x.pi===n-1;}).forEach(function(x){var r=x.r,i;
      if(x.t==="PDFRadioGroup"){i=document.createElement("input");i.type="radio";i.name=x.f.getName();i.checked=x.f.getSelected()===(x.on&&x.on.decodeText?x.on.decodeText():String(x.on));i.onclick=function(){try{x.f.select(x.on.decodeText?x.on.decodeText():String(x.on));}catch(e){}};}
      else if(x.t==="PDFCheckBox"){i=document.createElement("input");i.type="checkbox";i.checked=x.f.isChecked();i.onchange=function(){i.checked?x.f.check():x.f.uncheck();};}
      else{var ml=x.f.isMultiline()||r.height>26;i=document.createElement(ml?"textarea":"input");if(!ml)i.type="text";i.value=x.f.getText()||"";i.oninput=function(){try{x.f.setText(i.value);}catch(e){}};}
      i.className="bagfld";i.title=nice(x.f.getName());i.setAttribute("aria-label",nice(x.f.getName()));i.dataset.h=r.height;
      i.style.left=(r.x/vp.width*100)+"%";i.style.top=((vp.height-r.y-r.height)/vp.height*100)+"%";i.style.width=(r.width/vp.width*100)+"%";i.style.height=(r.height/vp.height*100)+"%";wrap.appendChild(i);});}
  sizeInputs();}
try{
  if(fillUrl&&window.PDFLib){ // fill in what the site knows about this buyer, then let the agent edit every field
    var res=await Promise.all([fetch(src,{credentials:"same-origin"}).then(function(r){if(!r.ok)throw 0;return r.arrayBuffer();}),fetch(fillUrl,{credentials:"same-origin"}).then(function(r){return r.json();}).catch(function(){return null;})]);
    doc=await PDFLib.PDFDocument.load(res[0]);form=doc.getForm();var done=[],F=(res[1]&&res[1].ok)?res[1].fields:{};
    var LB=(res[1]&&res[1].labels)||{},doneL=[];var FC=(res[1]&&res[1].force)||[];Object.keys(F).forEach(function(k){try{var t=form.getTextField(k);if(FC.indexOf(k)>=0||!(t.getText()||"").trim()){t.setText(String(F[k]));done.push(k);if(LB[k]&&doneL.indexOf(LB[k])<0)doneL.push(LB[k]);}}catch(e){}});
    bytes=await doc.save();editable=true;
    var fl=document.getElementById("bagfill");fl.innerHTML=(done.length?"<b>Filled in from this buyer's record:</b> "+(doneL.length?doneL:res[1].filled).join(", ")+". ":"")+"<b>Click any field on the form to type or change it</b>, such as the compensation and termination date. Download and Send for Signing use what you see here. At the bottom, a download marks the copy returned today by personal delivery; a DigiSigner send marks it by email with the last signer's date.";fl.hidden=false;
    var dl=document.getElementById("bagdl");dl.addEventListener("click",async function(e){e.preventDefault();try{fetch("bag_terms_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:<?= json_encode($_SESSION['csrf']) ?>,tx:<?= json_encode($tx) ?>,action:"downloaded"})});var ub=document.getElementById("bagupbtn");if(ub)ub.hidden=false;setTimeout(function(){try{parent.postMessage({bagdl:1},location.origin);}catch(y){}},600);}catch(x){}var u=URL.createObjectURL(new Blob([await getBytes("download")],{type:"application/pdf"})),a=document.createElement("a");a.href=u;a.download=v.dataset.name||"Buyer Agreement.pdf";document.body.appendChild(a);a.click();a.remove();setTimeout(function(){URL.revokeObjectURL(u);},4000);});
    await render(bytes,true);window.addEventListener("resize",sizeInputs);
  } else await render(null,false);
}catch(e){console.error("BAGERR "+(e&&e.message||e));editable=false;fallback(e&&e.message||e);}})();</script>
<script>(function(){var sd=document.getElementById("bagsend"),box=document.getElementById("bagsendbox");if(!sd||!box)return;
sd.onclick=function(){box.hidden=!box.hidden;if(!box.hidden)box.scrollIntoView({behavior:"smooth",block:"nearest"});};
var x=document.getElementById("bagsendx");if(x)x.onclick=function(){box.hidden=true;};
var go=document.getElementById("bagsendgo"),er=document.getElementById("bagsenderr");if(!go)return;
go.onclick=async function(){go.disabled=true;go.textContent="Sending...";er.hidden=true;
  try{var b64="";if(window.BAG_GET){var u8=new Uint8Array(await window.BAG_GET("send"));for(var i=0;i<u8.length;i+=32768)b64+=String.fromCharCode.apply(null,u8.subarray(i,i+32768));b64=btoa(b64);}
    var j=await fetch("bag_prefill.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:<?= json_encode($_SESSION['csrf']) ?>,action:"send",tx:<?= json_encode($tx) ?>,kind:<?= json_encode($k) ?>,b64:b64})}).then(function(r){return r.json();});
    if(!j.ok)throw new Error(j.msg||"Could not send it.");location.href=<?= json_encode($self) ?>+"&ok=sent";}
  catch(e){er.textContent=e.message;er.hidden=false;go.disabled=false;go.textContent="Send now";}};})();</script>
<script>(function(){var s=document.querySelector('#bagup select[name=kind]');function sync(){var sh=s&&s.value==="showing",e=document.getElementById("bagexpl"),h=document.getElementById("baghomel"),i=e&&e.querySelector("input");if(e){e.hidden=sh;if(i)i.required=!sh;}if(h)h.hidden=!sh;}if(s){s.addEventListener("change",sync);sync();}})();</script>
<script>(function(){var b=document.getElementById("bagupbtn"),u=document.getElementById("bagup");if(b)b.onclick=function(){u.hidden=!u.hidden;if(!u.hidden)u.scrollIntoView({behavior:"smooth",block:"nearest"});};<?php if ($err): ?>u.hidden=false;<?php endif; ?>})();</script>
<?php if ($advanced && sp_embed()): ?><script>try{window.top.location.reload();}catch(e){}</script><?php endif; ?>
<?php sp_foot(); ?>
