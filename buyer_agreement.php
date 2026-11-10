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
$self = 'buyer_agreement.php?tx=' . urlencode($tx) . (sp_embed() ? '&embed=1' : '');
$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'upload') {
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) $err = 'That file is too large to upload. Please choose a smaller PDF.';
    elseif (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $err = 'This page expired. Please reload and try again.';
    else { list($rid, $err) = bag_upload($me, $tx, (string)($_POST['kind'] ?? ''), $_FILES['pdf'] ?? [], (string)($_POST['on'] ?? '')); if ($err === '') { header('Location: ' . $self . '&ok=up'); exit; } }
}
// Check DigiSigner for anything still out for signature on this record, so a just-signed copy shows right away.
$all = sg_for_agent($me);
$mine = array_values(array_filter($all, function ($r) use ($tx) { return (string)($r['folder']['id'] ?? '') === $tx && empty($r['completed']) && bag_kind_of($r['form'] ?? ''); }));
if ($mine) { sg_refresh_many($mine); }
$st = bag_state($me, $tx);
$sig = $st['signed']; $kind = $sig ? bag_kind_of($sig['form']) : null;
$k = isset(BAG_FORMS[$_GET['k'] ?? '']) ? $_GET['k'] : 'exclusive';
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
.bagview{width:100%;max-height:820px;overflow:auto;border:1px solid var(--line);border-radius:12px;background:#e9eeee;padding:12px;box-sizing:border-box}.bagview canvas{display:block;width:100%;height:auto;margin:0 auto 12px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.15)}.bagview iframe{width:100%;height:760px;border:0}.bagfill{margin:0 0 12px;padding:9px 12px;border-left:4px solid var(--teal);background:#eef7f7;border-radius:8px;font-size:.88rem;color:#2a3638}.bagload{margin:0;color:#5a6a6c;padding:20px;text-align:center}
.bagmeta{font-size:.88rem;color:#4b5b5d;margin:0 0 12px}
@media(max-width:640px){.bagview{max-height:600px;padding:6px}}
</style>
<div class="bag">
<?php if ($err): ?>  <p class="sigerr"><?= $h($err) ?></p>
<?php elseif (($_GET['ok'] ?? '') === 'up'): ?>  <p class="bagok">Signed copy uploaded and saved to this buyer record.</p>
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
<?php foreach ($st['pending'] as $p): $who = []; foreach (($p['signers'] ?? []) as $s) { $who[] = $h($s['email']) . (!empty($p['signers_status'][$s['email']]) ? ' &#10003;' : ''); } ?>
  <p class="bagpend"><b><?= $h($p['form']) ?></b> was sent for signing on <?= $h($when($p['created'])) ?><?= $who ? ' to ' . implode(', ', $who) : '' ?>. Waiting for signatures. The signed copy shows here as soon as it comes back.</p>
<?php endforeach; ?>
  <div class="bagt" role="tablist"><?php foreach (BAG_FORMS as $kk => $ff): ?><a role="tab" href="buyer_agreement.php?tx=<?= urlencode($tx) ?>&amp;k=<?= $kk ?><?= sp_embed() ? '&amp;embed=1' : '' ?>" class="<?= $kk === $k ? 'on' : '' ?>" aria-selected="<?= $kk === $k ? 'true' : 'false' ?>"><?= $h($ff['title']) ?></a><?php endforeach; ?></div>
  <p class="bagnote"><?= $h($f['note']) ?></p>
  <div class="bagact">
    <a class="btn ghost" id="bagdl" href="<?= $h($src) ?>&amp;dl=1">&#8681; Download</a>
    <a class="btn" id="bagsend" href="sign.php?f=<?= $h($f['id']) ?>&amp;s=b&amp;tx=<?= urlencode($tx) ?>" target="_top">&#9998; Send for Signing</a>
    <button type="button" class="btn up" id="bagupbtn">&#8679; Upload signed copy</button>
  </div>
<?php endif; ?>
  <div class="bagup" id="bagup" hidden>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="upload"><input type="hidden" name="tx" value="<?= $h($tx) ?>">
      <label>Which agreement<select name="kind"><?php foreach (BAG_FORMS as $kk => $ff): ?><option value="<?= $kk ?>"<?= $kk === ($sig ? $kind : $k) ? ' selected' : '' ?>><?= $h($ff['title']) ?></option><?php endforeach; ?></select></label>
      <label>Date signed<input type="date" name="on" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></label>
      <label>Signed PDF<input type="file" name="pdf" accept="application/pdf,.pdf" required></label>
      <button type="submit" class="btn" style="border:0;cursor:pointer;font:inherit;font-weight:700;padding:9px 16px;border-radius:10px">Save signed copy</button>
      <p>Use this for an agreement signed in person or outside DigiSigner. It's saved to this buyer record and filed in the transaction folder.</p>
    </form>
  </div>
<?php if (!$sig): ?>  <p class="bagfill" id="bagfill" hidden></p>
<?php endif; ?>
  <div class="bagview" id="bagview"<?= $sig ? '' : ' data-fill="' . $h('bag_prefill.php?tx=' . urlencode($tx) . '&kind=' . $k) . '" data-name="' . $h(BAG_FORMS[$k]['title'] . ' - ' . date('Y-m-d') . '.pdf') . '"' ?> data-src="<?= $h($src) ?>" role="document" aria-label="<?= $h($sig ? 'Signed ' . $sig['form'] : BAG_FORMS[$k]['title']) ?>"><p class="bagload">Loading the agreement&hellip;</p></div>
  <p style="margin:12px 0 0"><a href="https://drive.google.com/drive/folders/<?= urlencode($tx) ?>" target="_blank" rel="noopener">Open the transaction folder</a></p>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script>(async function(){var v=document.getElementById("bagview");if(!v)return;var src=v.dataset.src,fillUrl=v.dataset.fill,CSRF=<?= json_encode($_SESSION['csrf']) ?>,bytes=null;
function fallback(){v.innerHTML='<iframe src="'+src+'#view=FitH" title="Agreement"></iframe>';}
async function render(data){if(!window.pdfjsLib)throw new Error("no viewer");pdfjsLib.GlobalWorkerOptions.workerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
  var pdf=await pdfjsLib.getDocument(data?{data:data.slice()}:{url:src,withCredentials:true}).promise;v.innerHTML="";var w=Math.max(300,v.clientWidth-24),dpr=Math.min(2,window.devicePixelRatio||1);
  for(var n=1;n<=pdf.numPages;n++){var pg=await pdf.getPage(n),vp=pg.getViewport({scale:1}),v2=pg.getViewport({scale:w/vp.width*dpr}),c=document.createElement("canvas");c.width=v2.width;c.height=v2.height;v.appendChild(c);await pg.render({canvasContext:c.getContext("2d"),viewport:v2}).promise;}}
try{
  if(fillUrl&&window.PDFLib){ // fill in what the site knows about this buyer
    var res=await Promise.all([fetch(src,{credentials:"same-origin"}).then(function(r){if(!r.ok)throw 0;return r.arrayBuffer();}),fetch(fillUrl,{credentials:"same-origin"}).then(function(r){return r.json();}).catch(function(){return null;})]);
    var doc=await PDFLib.PDFDocument.load(res[0]),form=doc.getForm(),done=[],F=(res[1]&&res[1].ok)?res[1].fields:{};
    Object.keys(F).forEach(function(k){try{var t=form.getTextField(k);if(!(t.getText()||"").trim()){t.setText(String(F[k]));done.push(k);}}catch(e){}});
    bytes=await doc.save();
    if(done.length){var fl=document.getElementById("bagfill");fl.innerHTML="<b>Filled in from this buyer's record:</b> "+res[1].filled.join(", ")+". Check it, and add the compensation and termination date before sending.";fl.hidden=false;}
    var dl=document.getElementById("bagdl");dl.addEventListener("click",function(e){e.preventDefault();var u=URL.createObjectURL(new Blob([bytes],{type:"application/pdf"})),a=document.createElement("a");a.href=u;a.download=v.dataset.name||"Buyer Agreement.pdf";document.body.appendChild(a);a.click();a.remove();setTimeout(function(){URL.revokeObjectURL(u);},4000);});
    var sd=document.getElementById("bagsend");sd.addEventListener("click",async function(e){e.preventDefault();var t=sd.textContent;sd.textContent="Preparing...";
      try{var b64="",u8=new Uint8Array(bytes);for(var i=0;i<u8.length;i+=32768)b64+=String.fromCharCode.apply(null,u8.subarray(i,i+32768));b64=btoa(b64);
        var j=await fetch("bag_prefill.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:CSRF,b64:b64})}).then(function(r){return r.json();});
        if(!j.ok)throw new Error(j.msg);window.top.location.href=sd.getAttribute("href")+"&pf="+encodeURIComponent(j.token);}
      catch(x){sd.textContent=t;window.top.location.href=sd.getAttribute("href");}});
  }
  await render(bytes);
}catch(e){fallback();}})();</script>
<script>(function(){var b=document.getElementById("bagupbtn"),u=document.getElementById("bagup");if(b)b.onclick=function(){u.hidden=!u.hidden;if(!u.hidden)u.scrollIntoView({behavior:"smooth",block:"nearest"});};<?php if ($err): ?>u.hidden=false;<?php endif; ?>})();</script>
<?php sp_foot(); ?>
