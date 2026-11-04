<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
@set_time_limit(120);
header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? '');
$all = sg_folders($me, $tx) ?: [];
$buyers = []; foreach ($all as $f) { if ($f['group'] === '1 BUYERS / 1 Buyers') $buyers[] = $f; }
$buyer = null; foreach ($buyers as $f) { if ($f['id'] === $tx) $buyer = $f; }
$bi = []; $addr = '';
if ($buyer) {
    $r = sg_buyer_info($me, $buyer['id']); $bi = (!empty($r['ok']) && is_array($r['info'] ?? null)) ? $r['info'] : [];
    $pa = trim(preg_replace('/\s+/', ' ', (string)($bi['property'] ?? ''))); if ($pa !== '' && strlen($pa) <= 120 && !preg_match('/[\\\\\/:*?"<>|]/', $pa)) $addr = $pa;
    $mine = strtolower(trim((string)($_SESSION['name'] ?? '')));
    foreach (['b1', 'b2'] as $k) { if ($mine !== '' && strtolower(trim((string)($bi[$k . '_name'] ?? ''))) === $mine) { $bi[$k . '_name'] = ''; } }
}
$B = [];
if ($buyer) {
    if (!empty($bi['b1_name']) || !empty($bi['b1_email'])) $B[] = ['role' => 'buyer1', 'label' => 'Buyer 1', 'name' => (string)($bi['b1_name'] ?? ''), 'email' => (string)($bi['b1_email'] ?? '')];
    if (!empty($bi['b2_name']) || !empty($bi['b2_email'])) $B[] = ['role' => 'buyer2', 'label' => 'Buyer 2', 'name' => (string)($bi['b2_name'] ?? ''), 'email' => (string)($bi['b2_email'] ?? '')];
    if (!$B) $B[] = ['role' => 'buyer1', 'label' => 'Buyer 1', 'name' => '', 'email' => ''];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Seller Disclosures for Signature - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=17">
<style>
.sdbar{position:sticky;top:0;z-index:5;background:#fff;border:1px solid #dbe6e5;border-radius:12px;padding:10px 14px;margin:0 0 14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.sdbar .tb{border:1px solid #006b68;background:#fff;color:#006b68;border-radius:8px;padding:6px 12px;font-weight:600;cursor:pointer}
.sdbar .tb.on{background:#006b68;color:#fff}
.sdpage{position:relative;margin:0 auto 16px;box-shadow:0 1px 6px rgba(0,0,0,.18);background:#fff;cursor:crosshair;max-width:100%}
.sdpage canvas{display:block;width:100%;height:auto}
.sdbox{position:absolute;border:2px solid;border-radius:4px;font:600 11px/1.1 Arial,sans-serif;display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden}
.sdbox.r1{border-color:#006b68;background:rgba(0,107,104,.18);color:#00514f}.sdbox.r2{border-color:#c2570c;background:rgba(236,130,40,.2);color:#8a3e08}
.sddoc h3{margin:18px 0 8px}
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="my-transactions.php">My Transactions</a>
    <a href="forms.php">Forms</a>
    <a href="signed.php">Signed</a>
    <a href="training.php">Training</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1>Seller Disclosures for Signature</h1><p>Upload the PDFs the seller side sent, mark where the buyers sign and initial, and send them through DigiSigner.</p></section>
<main class="wrap">
<?php if (!$buyers): ?>
  <p class="note">You need a buyer folder first: <strong>My Transactions &rsaquo; 1 BUYERS &rsaquo; 1 Buyers &rsaquo; [buyer name]</strong>.</p>
<?php elseif (!$buyer): ?>
  <form method="get" class="panel sigbox"><p><label for="tx"><strong>Which buyer?</strong></label><br>
    <select name="tx" id="tx" class="txsel" onchange="this.form.submit()"><option value="">Choose a buyer...</option>
<?php foreach ($buyers as $f): ?><option value="<?= htmlspecialchars($f['id']) ?>"><?= htmlspecialchars($f['name']) ?></option><?php endforeach; ?>
    </select></p></form>
<?php else: ?>
  <div class="panel sigbox" id="setup">
    <p><strong><?= htmlspecialchars($buyer['name']) ?></strong></p>
    <p><label for="addr"><strong>Property address</strong></label><br><input type="text" id="addr" class="txsel" size="40" maxlength="120" value="<?= htmlspecialchars($addr) ?>" placeholder="e.g. 123 Palm Ave"></p>
    <p><strong>Who signs?</strong></p>
<?php foreach ($B as $b): ?>
    <div style="margin:0 0 12px"><div style="font-weight:600;margin-bottom:4px"><?= htmlspecialchars(($b['name'] !== '' ? $b['name'] . ' (' : '(') . $b['label'] . ')') ?></div>
      <input type="email" class="bemail" data-role="<?= $b['role'] ?>" value="<?= htmlspecialchars($b['email']) ?>" placeholder="<?= $b['label'] ?> email" style="width:100%;max-width:480px"></div>
<?php endforeach; ?>
    <p><label for="files"><strong>Seller disclosure PDFs</strong></label><br><input type="file" id="files" accept="application/pdf,.pdf" multiple></p>
  </div>
  <div id="work" hidden>
    <div class="sdbar" id="bar">
<?php foreach ($B as $b): ?><button type="button" class="tb" data-role="<?= $b['role'] ?>"><?= $b['label'] ?></button><?php endforeach; ?>
      <span style="width:8px"></span>
      <button type="button" class="tb" data-type="SIGNATURE">Signature</button>
      <button type="button" class="tb" data-type="INITIALS">Initials</button>
      <button type="button" class="tb" data-type="DATE">Date</button>
      <span class="note" id="hint" style="margin:0 0 0 auto;padding:4px 10px">Pick a buyer and a field, then click on the page. Click a box to remove it.</span>
    </div>
    <div id="docs"></div>
    <div class="panel sigbox">
      <p id="msg" class="note" hidden></p>
      <p><button type="button" class="btn" id="send">Send for signature</button> <a class="btn ghost" href="my-transactions.php">Cancel</a></p>
    </div>
  </div>
  <div class="panel sigbox" id="done" hidden></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="flrdraft.js?v=1"></script>
<script>
(function(){
var CSRF=<?= json_encode($_SESSION['csrf']) ?>, TX=<?= json_encode($buyer['id']) ?>, BUY=<?= json_encode($B) ?>, SCALE=1.25;
pdfjsLib.GlobalWorkerOptions.workerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
var SIZE={SIGNATURE:[170,30],INITIALS:[42,20],DATE:[84,18]}, LBL={SIGNATURE:"Sign",INITIALS:"Init",DATE:"Date"};
var role=BUY[0].role, type="SIGNATURE", docs=[];
var bar=document.getElementById("bar"),docsEl=document.getElementById("docs"),work=document.getElementById("work"),msg=document.getElementById("msg");
function esc(t){return String(t).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}
function syncBar(){[].forEach.call(bar.querySelectorAll(".tb"),function(b){b.classList.toggle("on",b.dataset.role?b.dataset.role===role:b.dataset.type===type);});}
bar.addEventListener("click",function(e){var b=e.target.closest(".tb");if(!b)return;if(b.dataset.role)role=b.dataset.role;else type=b.dataset.type;syncBar();});syncBar();
function draw(d,pi){var pg=d.pages[pi];pg.ov.innerHTML="";d.fields.forEach(function(f,fi){if(f.page!==pi)return;var el=document.createElement("div");el.className="sdbox "+(f.role==="buyer2"?"r2":"r1");
  el.style.cssText="left:"+(f.x/pg.w*100)+"%;top:"+(f.y/pg.h*100)+"%;width:"+(f.w/pg.w*100)+"%;height:"+(f.h/pg.h*100)+"%";el.textContent=(f.role==="buyer2"?"B2 ":"B1 ")+LBL[f.type];el.title="Click to remove";
  el.addEventListener("click",function(ev){ev.stopPropagation();d.fields.splice(d.fields.indexOf(f),1);redrawAll(d);});pg.ov.appendChild(el);});}
function redrawAll(d){d.pages.forEach(function(_,i){draw(d,i);});}
function place(d,pi,cx,cy){var sz=SIZE[type],every=false,pg=d.pages[pi];
  var x=Math.max(0,Math.min(pg.w-sz[0],cx-sz[0]/2)),y=Math.max(0,Math.min(pg.h-sz[1],cy-sz[1]/2));
  (every?d.pages.map(function(_,i){return i;}):[pi]).forEach(function(i){var p=d.pages[i];d.fields.push({page:i,x:x,y:Math.min(y,p.h-sz[1]),w:sz[0],h:sz[1],type:type,role:role});});
  redrawAll(d);}
async function addDoc(file){
  var bytes=new Uint8Array(await file.arrayBuffer());
  var pdf=await pdfjsLib.getDocument({data:bytes.slice()}).promise;
  var d={name:file.name,bytes:bytes,fields:[],pages:[],text:[]};docs.push(d);
  var sec=document.createElement("div");sec.className="sddoc";sec.innerHTML="<h3>"+esc(file.name)+"</h3>";docsEl.appendChild(sec);
  for(var n=1;n<=pdf.numPages;n++){
    var page=await pdf.getPage(n),vp=page.getViewport({scale:SCALE}),w0=vp.width/SCALE,h0=vp.height/SCALE;
    var wrap=document.createElement("div");wrap.className="sdpage";wrap.style.width=vp.width+"px";
    var cv=document.createElement("canvas");cv.width=vp.width;cv.height=vp.height;wrap.appendChild(cv);
    var ov=document.createElement("div");ov.style.cssText="position:absolute;inset:0";wrap.appendChild(ov);sec.appendChild(wrap);
    d.pages.push({w:w0,h:h0,ov:ov});
    try{d.text.push((await page.getTextContent()).items);}catch(e0){d.text.push([]);}
    (function(pi,wrapEl,w,h){wrapEl.addEventListener("click",function(ev){var r=wrapEl.getBoundingClientRect();place(d,pi,(ev.clientX-r.left)/r.width*w,(ev.clientY-r.top)/r.height*h);});})(n-1,wrap,w0,h0);
    await page.render({canvasContext:cv.getContext("2d"),viewport:vp}).promise;
  }
}
document.getElementById("files").addEventListener("change",async function(e){
  var list=[].slice.call(e.target.files||[]);if(!list.length)return;
  msg.hidden=true;work.hidden=false;docsEl.innerHTML="";docs=[];
  try{for(var i=0;i<list.length;i++){if(!/\.pdf$/i.test(list[i].name)){continue;}if(list[i].size>12*1024*1024)throw new Error(list[i].name+" is larger than 12 MB.");await addDoc(list[i]);}}
  catch(err){msg.textContent="Could not open the file: "+err.message;msg.className="note sigerr";msg.hidden=false;}
});

function b64(u){var s="";for(var o=0;o<u.length;o+=32768){s+=String.fromCharCode.apply(null,u.subarray(o,o+32768));}return btoa(s);}
var RET=<?= json_encode(preg_match('/^(dr=[^&]{1,300}|draft=1)$/', (string)($_GET['ret'] ?? '')) ? (string)$_GET['ret'] : '') ?>;
if(RET)document.getElementById("send").textContent="Add To Email For Signing";
document.getElementById("send").addEventListener("click",async function(){
  var btn=this;msg.className="note";msg.hidden=true;
  if(!docs.length){msg.textContent="Choose the PDF(s) first.";msg.hidden=false;return;}
  var emails={};[].forEach.call(document.querySelectorAll(".bemail"),function(i){emails[i.dataset.role]=i.value.trim();});
  for(var i=0;i<docs.length;i++){if(!docs[i].fields.length){msg.textContent="Place at least one signature box on "+docs[i].name+".";msg.className="note sigerr";msg.hidden=false;return;}}
  if(RET){ // came from the send page: hand the documents back so everything goes in ONE email
    btn.disabled=true;btn.innerHTML='<span class="bspin"></span>Adding...';
    try{await FLRDraft.put(TX+"|seller",{docs:docs.map(function(d){return {name:d.name,bytes:d.bytes,fields:d.fields.map(function(f){return {page:f.page,x:f.x,y:f.y,w:f.w,h:f.h,type:f.type,role:f.role,ph:d.pages[f.page].h};})};})});
      location.href="sign_packet.php?tx="+encodeURIComponent(TX)+"&sd=1&"+RET;return;}
    catch(err){msg.textContent="This browser could not hold the documents: "+err.message;msg.className="note sigerr";msg.hidden=false;btn.disabled=false;btn.textContent="Add To Email For Signing";return;}
  }
  btn.disabled=true;btn.innerHTML='<span class="bspin"></span>Sending...';
  await new Promise(function(r){requestAnimationFrame(function(){setTimeout(r,50);});});
  try{
    var payload={csrf:CSRF,tx:TX,addr:document.getElementById("addr").value.trim(),emails:emails,docs:docs.map(function(d){return {name:d.name,b64:b64(d.bytes),fields:d.fields.map(function(f){return {page:f.page,x:f.x,y:f.y,w:f.w,h:f.h,type:f.type,role:f.role,ph:d.pages[f.page].h};})};})};
    var r=await fetch("seller_docs_send.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)});
    var j=await r.json();
    if(!j.ok){msg.textContent=j.msg||"Could not send.";msg.className="note sigerr";msg.hidden=false;btn.disabled=false;btn.textContent="Send for signature";return;}
    work.hidden=true;document.getElementById("setup").hidden=true;var dn=document.getElementById("done");
    dn.innerHTML="<h2>Sent</h2><p>DigiSigner is emailing the buyers "+j.count+" document"+(j.count===1?"":"s")+" in one email. When they sign, each signed copy is filed in <strong>1 BUYERS / 2 Contract Offers / "+esc(j.addr)+"</strong>. Track it on the <a href=\"signed.php\">Signed Documents</a> page.</p>";dn.hidden=false;window.scrollTo(0,0);
  }catch(err){msg.textContent="Something went wrong: "+err.message;msg.className="note sigerr";msg.hidden=false;btn.disabled=false;btn.textContent="Send for signature";}
});
})();
</script>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
