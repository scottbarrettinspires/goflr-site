<?php
// Assign pages of one signed document to the record's required documents (or to a name the agent chooses).
// Thumbnails 3 across (page 1 left, 2 middle, 3 right, page 4 under page 1...), check the pages, pick the requirement, Assign.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/doclib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? ''); $tx = (string)($_GET['tx'] ?? ''); $ref = (string)($_GET['ref'] ?? '');
list($rec, $err, $p) = dc_rec_for($me, $tx, $ref);
$j = $rec ? dc_list($me, $tx) : ['ok' => false];
$title = $rec ? (string)($p[1] === 'm' ? ($rec['form'] ?? 'Document') : ($rec['extra'][(int)$p[1]]['form'] ?? 'Document')) : '';
$mine = []; // existing assignments of this document
if ($rec) foreach ((array)($rec['contents'] ?? []) as $c) { if (($c['ref'] ?? '') === $ref || (($c['ref'] ?? '') === '' && $p[1] === 'm')) $mine[] = ['key' => (string)$c['key'], 'label' => (string)($c['label'] ?? ''), 'name' => (string)$c['name'], 'pages' => $c['pages'] ?: [(int)$c['page']], 'ref' => (string)($c['ref'] ?? '')]; }
$req = !empty($j['ok']) ? $j['required'] : [];
$back = 'docs_view.php?tx=' . urlencode($tx) . '&embed=1';
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Assign pages - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=20">
<style>
body{margin:0;background:#eef3f3}
.abar{position:sticky;top:0;z-index:5;background:#fff;border-bottom:1px solid #dbe6e5;padding:10px 14px;display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.abar select,.abar input[type=text]{padding:8px 10px;border:1px solid #c9d9d8;border-radius:8px;font:inherit;max-width:100%}
.agrid{display:grid;grid-template-columns:repeat(var(--cols,4),1fr);gap:12px;padding:14px;max-width:1500px;margin:0 auto}
#zoom{position:fixed;z-index:20;top:70px;bottom:10px;background:#fff;border:2px solid #006b68;border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.35);padding:6px;display:none;overflow:auto;max-width:min(720px,96vw)}#zoom canvas{display:block;width:100%;height:auto}#zoom.on{display:block}
.zbtn{position:absolute;top:6px;right:6px;border:1px solid #c9d9d8;background:rgba(255,255,255,.9);border-radius:6px;padding:2px 7px;font-size:.8rem;cursor:pointer}
.acell{background:#fff;border:2px solid #dbe6e5;border-radius:10px;padding:8px;cursor:pointer;position:relative}
.acell.sel{border-color:#006b68;box-shadow:0 0 0 2px rgba(0,107,104,.25)}
.acell canvas{display:block;width:100%;height:auto;background:#fff}
.acell label{display:flex;gap:6px;align-items:center;font-weight:700;margin-top:6px;cursor:pointer}
.acell .tag{font-size:.74rem;background:#e3f4e8;color:#1b5e20;border-radius:6px;padding:2px 6px;margin:3px 3px 0 0;display:inline-block;font-weight:700}
.amine{max-width:1500px;margin:0 auto;padding:10px 14px}
.amine .pdr{display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center;background:#fff;border:1px solid #dfe9e9;border-radius:10px;padding:8px 12px;margin:0 0 6px}
@media(max-width:560px){.agrid{gap:8px;padding:8px}}
</style></head><body>
<?php if (!$rec): ?>
<div class="abar"><b><?= htmlspecialchars($err ?: 'Document not found.') ?></b> <a class="btn ghost" href="<?= htmlspecialchars($back) ?>">Back</a></div>
<?php else: ?>
<div class="abar"><b style="flex:1 1 240px">Assign pages: <?= htmlspecialchars($title) ?></b>
  <a class="btn ghost" href="<?= htmlspecialchars($back) ?>" target="_top">Done</a></div>
<div class="abar" style="top:52px">
  <span id="cnt">0 pages selected</span>
  <label style="display:inline-flex;gap:6px;align-items:center;font-weight:400">Across <select id="cols"><option>3</option><option selected>4</option><option>5</option><option>6</option></select></label>
  <button type="button" class="btn ghost" id="all">Select all</button><button type="button" class="btn ghost" id="none">Clear</button>
  <select id="target"><option value="">Assign to...</option>
<?php if ($req): ?><optgroup label="Required documents"><?php foreach ($req as $m): ?><option value="key:<?= htmlspecialchars($m['key']) ?>"><?= htmlspecialchars($m['label']) ?><?= $m['status'] === 'ok' ? ' (on file)' : '' ?></option><?php endforeach; ?></optgroup><?php endif; ?>
    <option value="custom">Other document (type a name)...</option></select>
  <input type="text" id="custom" maxlength="80" placeholder="What is this document?" hidden>
  <button type="button" class="btn" id="go">Assign selected pages</button>
  <span id="msg" class="note" hidden style="margin:0"></span>
</div>
<?php if ($mine): ?><div class="amine"><strong>Already assigned from this document</strong>
<?php foreach ($mine as $m): ?><div class="pdr"><b><?= htmlspecialchars($m['name']) ?></b><small>page<?= count($m['pages']) === 1 ? '' : 's' ?> <?= htmlspecialchars(implode(', ', $m['pages'])) ?></small><button type="button" class="btn ghost un" data-key="<?= htmlspecialchars($m['key']) ?>" data-label="<?= htmlspecialchars($m['label']) ?>">Remove</button></div><?php endforeach; ?></div><?php endif; ?>
<div id="zoom" aria-hidden="true"></div>
<div class="agrid" id="grid"><p class="note" id="load" style="grid-column:1/-1">Loading the pages...</p></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function(){
var CSRF=<?= json_encode($_SESSION['csrf']) ?>,TX=<?= json_encode($tx) ?>,REF=<?= json_encode($ref) ?>,MINE=<?= json_encode($mine) ?>,sel={},cells=[];
var pdfDoc=null,zoomEl=document.getElementById("zoom"),zcache={},ztimer=null,zpin=false,colsEl=document.getElementById("cols");
try{var sc0=localStorage.getItem("flrAssignCols");if(sc0&&/^[3-6]$/.test(sc0))colsEl.value=sc0;}catch(e){}
function setCols(){document.documentElement.style.setProperty("--cols",colsEl.value);try{localStorage.setItem("flrAssignCols",colsEl.value);}catch(e){}}
colsEl.addEventListener("change",setCols);setCols();
async function showZoom(n,el,pin){
  clearTimeout(ztimer);zpin=!!pin;var r=el.getBoundingClientRect(),right=(r.left+r.width/2)<window.innerWidth/2;
  var w=Math.min(720,Math.floor(window.innerWidth*(pin?0.94:0.46)));zoomEl.style.width=w+"px";zoomEl.style.left=pin?"3vw":(right?"auto":"14px");zoomEl.style.right=pin?"auto":(right?"14px":"auto");
  zoomEl.innerHTML="";var b=document.createElement("button");b.type="button";b.className="zbtn";b.textContent="Close";b.addEventListener("click",hideZoom);zoomEl.appendChild(b);
  var c=zcache[n];if(!c){var page=await pdfDoc.getPage(n),v0=page.getViewport({scale:1}),vp=page.getViewport({scale:Math.min(2.2,900/v0.width)});c=document.createElement("canvas");c.width=vp.width;c.height=vp.height;await page.render({canvasContext:c.getContext("2d"),viewport:vp}).promise;zcache[n]=c;}
  var cc=document.createElement("canvas");cc.width=c.width;cc.height=c.height;cc.getContext("2d").drawImage(c,0,0);zoomEl.appendChild(cc);zoomEl.classList.add("on");
}
function hideZoom(){zoomEl.classList.remove("on");zpin=false;}
var grid=document.getElementById("grid"),cnt=document.getElementById("cnt"),msg=document.getElementById("msg"),tg=document.getElementById("target"),cu=document.getElementById("custom");
function esc(t){return String(t).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}
function sync(){var n=Object.keys(sel).length;cnt.textContent=n+" page"+(n===1?"":"s")+" selected";cells.forEach(function(c,i){c.el.classList.toggle("sel",!!sel[i+1]);c.cb.checked=!!sel[i+1];});}
function say(t,bad){msg.textContent=t;msg.className="note"+(bad?" sigerr":"");msg.hidden=!t;}
tg.addEventListener("change",function(){cu.hidden=tg.value!=="custom";if(!cu.hidden)cu.focus();});
document.getElementById("all").addEventListener("click",function(){cells.forEach(function(_,i){sel[i+1]=1;});sync();});
document.getElementById("none").addEventListener("click",function(){sel={};sync();});
async function post(o){var f=new URLSearchParams(Object.assign({csrf:CSRF,tx:TX,ref:REF},o));var r=await fetch("docs_assign_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:f.toString()});return r.json();}
document.getElementById("go").addEventListener("click",async function(){
  var pages=Object.keys(sel).map(Number).sort(function(a,b){return a-b;});
  if(!pages.length){say("Check at least one page first.",1);return;}
  if(!tg.value){say("Choose which required document these pages are.",1);return;}
  this.disabled=true;say("Saving...");
  try{var j=await post({action:"assign",pages:pages.join(","),target:tg.value,custom:cu.value});if(!j.ok){say(j.msg||"Could not save.",1);this.disabled=false;return;}location.reload();}
  catch(e){say("Something went wrong: "+e.message,1);this.disabled=false;}
});
[].forEach.call(document.querySelectorAll(".un"),function(b){b.addEventListener("click",async function(){b.disabled=true;var j=await post({action:"unassign",key:b.dataset.key,label:b.dataset.label});if(j.ok)location.reload();else{say(j.msg||"Could not remove.",1);b.disabled=false;}});});
(async function(){
  try{
    pdfjsLib.GlobalWorkerOptions.workerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
    var r=await fetch("doc_file.php?view=1&ref="+encodeURIComponent(REF),{credentials:"same-origin"});if(!r.ok)throw new Error(await r.text());
    var pdf=await pdfjsLib.getDocument({data:new Uint8Array(await r.arrayBuffer())}).promise;pdfDoc=pdf;document.getElementById("load").remove();
    for(var n=1;n<=pdf.numPages;n++){
      var el=document.createElement("div");el.className="acell";var cv=document.createElement("canvas");el.appendChild(cv);
      var lb=document.createElement("label"),cb=document.createElement("input");cb.type="checkbox";lb.appendChild(cb);lb.appendChild(document.createTextNode("Page "+n));el.appendChild(lb);
      var tags=MINE.filter(function(m){return m.pages.indexOf(n)>=0;});tags.forEach(function(m){var t=document.createElement("span");t.className="tag";t.textContent=m.name;el.appendChild(t);});
      var zb=document.createElement("button");zb.type="button";zb.className="zbtn";zb.textContent="Zoom";el.style.position="relative";el.appendChild(zb);
      (function(k,e,z){z.addEventListener("click",function(ev){ev.stopPropagation();showZoom(k,e,true);});
        e.addEventListener("mouseenter",function(){if(zpin||!window.matchMedia("(hover:hover)").matches)return;clearTimeout(ztimer);ztimer=setTimeout(function(){showZoom(k,e,false);},200);});
        e.addEventListener("mouseleave",function(){clearTimeout(ztimer);if(!zpin)zoomEl.classList.remove("on");});})(n,el,zb);
      grid.appendChild(el);cells.push({el:el,cb:cb});
      (function(k,e,c){e.addEventListener("click",function(ev){if(ev.target===c||ev.target.tagName==="LABEL"||ev.target.tagName==="BUTTON")return;if(sel[k])delete sel[k];else sel[k]=1;sync();});c.addEventListener("change",function(){if(c.checked)sel[k]=1;else delete sel[k];sync();});})(n,el,cb);
      var page=await pdf.getPage(n),v0=page.getViewport({scale:1}),sc=360/v0.width,vp=page.getViewport({scale:sc});cv.width=vp.width;cv.height=vp.height;
      await page.render({canvasContext:cv.getContext("2d"),viewport:vp}).promise;
    }
  }catch(e){var l=document.getElementById("load");if(l){l.className="note sigerr";l.textContent="Could not open the document: "+e.message;}}
})();
})();
</script>
<?php endif; ?>
</body></html>
