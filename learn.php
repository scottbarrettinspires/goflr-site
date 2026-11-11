<?php
// Learn The Business: slides and playbooks from past Round Tables, viewed right on the page.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/learnlib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$isStaff = flr_is_broker(); $by = strtolower($_SESSION['email'] ?? '');
$msg = ''; $err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!$isStaff) { $err = 'Only office staff can add or remove items.'; }
    elseif (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    elseif (($_POST['a'] ?? '') === 'add') { $err = ln_add((string)($_POST['kind'] ?? ''), (string)($_POST['title'] ?? ''), (string)($_POST['series'] ?? ''), (string)($_POST['session'] ?? ''), $_FILES['pdf'] ?? [], $by); if ($err === '') $msg = 'Added.'; }
    elseif (($_POST['a'] ?? '') === 'delete') { $err = ln_delete((int)($_POST['id'] ?? 0), $by); if ($err === '') $msg = 'Removed.'; }
}
$h = function ($s) { return htmlspecialchars((string)$s); };
$tab = ($_GET['tab'] ?? '') === 'playbooks' ? 'playbook' : 'presentation';
$open = (int)($_GET['id'] ?? 0); if ($open && ($oi = ln_item($open))) $tab = $oi['kind'];
$items = ['presentation' => ln_list('presentation'), 'playbook' => ln_list('playbook')];
$csrf = '<input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '">';
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
<title>Learn The Business - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=17">
<style>
.lt{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}
.lt button{font:inherit;font-weight:700;padding:11px 20px;border-radius:12px;border:1px solid var(--line);background:var(--white);color:var(--teal);cursor:pointer;display:flex;gap:8px;align-items:center}
.lt button.on{background:var(--teal);color:#fff;border-color:var(--teal)}.lt .n{font-size:.78rem;background:rgba(0,0,0,.08);border-radius:99px;padding:1px 8px}.lt button.on .n{background:rgba(255,255,255,.25)}
.lgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px}
.lcard{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:14px 16px;display:flex;flex-direction:column;gap:6px;cursor:pointer;text-align:left;font:inherit;color:inherit;width:100%}
.lcard:hover{border-color:var(--teal);box-shadow:0 2px 10px rgba(3,111,114,.12)}.lcard.on{border-color:var(--teal);background:#f1f9f9}
.lcard b{font-size:1.02rem;line-height:1.25}.lcard small{color:#5a6a6c}.lcard .tg{font-size:.72rem;font-weight:700;color:var(--teal);text-transform:uppercase;letter-spacing:.04em}
.lview{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:14px;margin:0 0 18px}
.lbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:0 0 10px}.lbar h2{margin:0;font-size:1.1rem;flex:1 1 220px}
.lbtn{font:inherit;font-weight:700;font-size:.9rem;padding:7px 16px;border-radius:10px;border:1px solid var(--teal);background:var(--teal);color:#fff;cursor:pointer;text-decoration:none;display:inline-block}
.lbtn.ghost{background:#fff;color:var(--teal)}.lbtn[disabled]{opacity:.4;cursor:default}
.lstage{background:#10191a;border-radius:10px;display:flex;justify-content:center;align-items:center;min-height:200px;position:relative}
.lstage canvas{max-width:100%;display:block}
.lnav{display:flex;gap:10px;align-items:center;justify-content:center;margin:10px 0 0}.lcount{font-weight:700;min-width:110px;text-align:center}
.lpb{max-height:80vh;overflow:auto;background:#e9eeee;border-radius:10px;padding:12px;text-align:center}.lpb canvas{max-width:100%;display:block;margin:0 auto 12px;box-shadow:0 1px 6px rgba(0,0,0,.2);background:#fff}
.lmsg{color:#b71c1c}.lhint{color:#5a6a6c;font-size:.9rem;margin:0 0 14px}
.ladm{margin:26px 0 0;border:1px dashed var(--line);border-radius:12px;padding:12px 14px}.ladm summary{cursor:pointer;font-weight:700;color:var(--teal)}
.ladm form{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:10px}.ladm label{font-size:.82rem;font-weight:600;color:#4b5b5d;display:flex;flex-direction:column;gap:3px}
.ladm input,.ladm select{font:inherit;padding:9px 11px;border:1px solid var(--line);border-radius:10px}
.lx{font:inherit;font-size:.78rem;color:#b71c1c;background:none;border:0;cursor:pointer;padding:0;margin-top:4px;align-self:flex-start}
.lfs:fullscreen{background:#10191a;padding:10px;display:flex;flex-direction:column;justify-content:center}
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Virtual Broker Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php">Active Business</a>
    <a href="performance.php">Performance</a>
    <a href="learn.php" aria-current="page">Learn</a>
    <?php endif; ?>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a><?php endif; ?>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>
<section class="hero"><h1>Learn The Business</h1><p>Slides and playbooks from our Round Tables. Watch the slides here, scroll through a playbook, or download it to keep.</p></section>
<main class="wrap">
<?php if ($msg): ?><p class="note"><?= $h($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= $h($err) ?></p><?php endif; ?>
  <div class="lt" id="tabs">
    <button type="button" data-t="presentation" class="<?= $tab === 'presentation' ? 'on' : '' ?>">Presentations <span class="n"><?= count($items['presentation']) ?></span></button>
    <button type="button" data-t="playbook" class="<?= $tab === 'playbook' ? 'on' : '' ?>">Playbooks <span class="n"><?= count($items['playbook']) ?></span></button>
  </div>
  <div id="viewer" class="lview" hidden></div>
<?php foreach ($items as $kind => $list): ?>
  <section class="lsec" data-k="<?= $kind ?>"<?= $kind === $tab ? '' : ' hidden' ?>>
<?php if (!$list): ?><p class="lhint">Nothing here yet. Check back after the next Round Table.</p><?php endif; ?>
    <div class="lgrid">
<?php foreach ($list as $r): ?>
      <div><button type="button" class="lcard" data-id="<?= (int)$r['id'] ?>" data-kind="<?= $kind ?>" data-title="<?= $h($r['title']) ?>">
        <span class="tg"><?= $h($r['series'] ?: ($kind === 'playbook' ? 'Playbook' : 'Presentation')) ?></span><b><?= $h($r['title']) ?></b><small><?= $h(ln_when($r['session'])) ?><?= $kind === 'playbook' ? ' · scroll and download' : ' · view slides' ?></small></button>
<?php if ($isStaff): ?><form method="post" onsubmit="return confirm('Remove this from Learn The Business?')"><?= $csrf ?><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="lx" type="submit">Remove</button></form><?php endif; ?>
      </div>
<?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
<?php if ($isStaff): ?>
  <details class="ladm"><summary>Add to Learn The Business (office staff)</summary>
    <form method="post" enctype="multipart/form-data"><?= $csrf ?><input type="hidden" name="a" value="add">
      <label>Type<select name="kind"><option value="presentation">Presentation (shown slide by slide)</option><option value="playbook">Playbook (scroll and download)</option></select></label>
      <label>Title<input name="title" maxlength="140" required></label>
      <label>Session<input name="series" maxlength="60" placeholder="Round Table, Zoom, Special"></label>
      <label>Session date<input type="date" name="session"></label>
      <label>PDF file<input type="file" name="pdf" accept="application/pdf,.pdf" required></label>
      <label>&nbsp;<button class="lbtn" type="submit">Add</button></label>
    </form>
    <p class="lhint" style="margin:8px 0 0">In Google Slides or Docs, use File &rsaquo; Download &rsaquo; PDF first. Slides should be a 16:9 deck so they fill the screen.</p>
  </details>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function(){
var tabs=document.getElementById("tabs"),view=document.getElementById("viewer"),OPEN=<?= (int)$open ?>;
var cur={doc:null,page:1,kind:"",id:0,busy:false,pend:0};
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}
tabs.addEventListener("click",function(e){var b=e.target.closest("button[data-t]");if(!b)return;tabs.querySelectorAll("button").forEach(function(x){x.classList.toggle("on",x===b);});
  document.querySelectorAll(".lsec").forEach(function(s){s.hidden=s.dataset.k!==b.dataset.t;});closeView();});
document.querySelectorAll(".lcard").forEach(function(c){c.onclick=function(){openItem(c);};});
function closeView(){view.hidden=true;view.innerHTML="";cur.doc=null;document.querySelectorAll(".lcard.on").forEach(function(x){x.classList.remove("on");});}
function fail(m){var st=view.querySelector(".lstage,.lpb");if(st)st.innerHTML='<p class="lmsg" style="color:#fff;padding:20px">'+esc(m)+'</p>';}
async function openItem(c){
  if(!window.pdfjsLib){view.hidden=false;view.innerHTML='<p class="lmsg">The viewer could not load. Please check your connection and try again, or download the file.</p>';return;}
  if(!pdfjsLib.GlobalWorkerOptions.workerSrc)pdfjsLib.GlobalWorkerOptions.workerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
  document.querySelectorAll(".lcard.on").forEach(function(x){x.classList.remove("on");});c.classList.add("on");
  var id=c.dataset.id,kind=c.dataset.kind,url="learn_file.php?id="+id,dl=url+"&dl=1";
  cur={doc:null,page:1,kind:kind,id:id};
  view.hidden=false;
  view.innerHTML='<div class="lbar"><h2>'+esc(c.dataset.title)+'</h2><a class="lbtn ghost" href="'+dl+'">Download PDF</a>'+(kind==="presentation"?'<button type="button" class="lbtn ghost" id="lfs">Full screen</button>':'')+'<button type="button" class="lbtn ghost" id="lclose">Close</button></div>'
   +(kind==="presentation"?'<div class="lfs" id="lfsbox"><div class="lstage" id="lstage"><p style="color:#fff">Opening the slides&hellip;</p></div><div class="lnav"><button type="button" class="lbtn" id="lprev">&larr; Back</button><span class="lcount" id="lcount"></span><button type="button" class="lbtn" id="lnext">Next &rarr;</button></div></div>':'<div class="lpb" id="lpb"><p>Opening the playbook&hellip;</p></div>');
  document.getElementById("lclose").onclick=closeView;
  view.scrollIntoView({behavior:"smooth",block:"start"});
  try{cur.doc=await pdfjsLib.getDocument({url:url}).promise;}catch(e){fail("This file could not be opened. Try Download PDF instead.");return;}
  if(kind==="presentation")slides();else scroller();
}
// ---- Presentations: one slide at a time, Back / Next, arrow keys, swipe, full screen ----
function slides(){
  var st=document.getElementById("lstage"),box=document.getElementById("lfsbox"),cnt=document.getElementById("lcount"),pv=document.getElementById("lprev"),nx=document.getElementById("lnext"),n=cur.doc.numPages;
  st.innerHTML="";var cv=document.createElement("canvas");st.appendChild(cv);
  async function show(p){p=Math.max(1,Math.min(n,p));cur.page=p;cnt.textContent="Slide "+p+" of "+n;pv.disabled=p<=1;nx.disabled=p>=n;
    if(cur.busy){cur.pend=p;return;}cur.busy=true;
    try{var pg=await cur.doc.getPage(p),v1=pg.getViewport({scale:1}),fs=!!document.fullscreenElement,W=Math.max(280,st.clientWidth||box.clientWidth),H=fs?window.innerHeight-90:Math.max(260,window.innerHeight*0.74);
      var sc=Math.min(W/v1.width,H/v1.height),dpr=Math.min(2,window.devicePixelRatio||1),vp=pg.getViewport({scale:sc*dpr});
      cv.width=vp.width;cv.height=vp.height;cv.style.width=Math.floor(vp.width/dpr)+"px";cv.style.height=Math.floor(vp.height/dpr)+"px";
      await pg.render({canvasContext:cv.getContext("2d"),viewport:vp}).promise;}catch(e){}
    cur.busy=false;if(cur.pend&&cur.pend!==cur.page){var q=cur.pend;cur.pend=0;show(q);}else cur.pend=0;}
  pv.onclick=function(){show(cur.page-1);};nx.onclick=function(){show(cur.page+1);};
  document.getElementById("lfs").onclick=function(){if(document.fullscreenElement)document.exitFullscreen();else if(box.requestFullscreen)box.requestFullscreen();};
  document.onkeydown=function(e){if(view.hidden||cur.kind!=="presentation")return;if(e.key==="ArrowRight"||e.key==="PageDown"||e.key===" "){e.preventDefault();show(cur.page+1);}else if(e.key==="ArrowLeft"||e.key==="PageUp"){e.preventDefault();show(cur.page-1);}};
  var x0=null;st.addEventListener("touchstart",function(e){x0=e.touches[0].clientX;},{passive:true});st.addEventListener("touchend",function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;x0=null;if(Math.abs(dx)>40)show(cur.page+(dx<0?1:-1));});
  st.addEventListener("click",function(e){var r=st.getBoundingClientRect();show(cur.page+((e.clientX-r.left)<r.width*0.3?-1:1));});
  document.addEventListener("fullscreenchange",function(){if(!view.hidden&&cur.kind==="presentation")show(cur.page);});
  window.addEventListener("resize",function(){if(!view.hidden&&cur.kind==="presentation")show(cur.page);});
  show(1);
}
// ---- Playbooks: every page in a scrollable window, drawn as it scrolls into view ----
function scroller(){
  var box=document.getElementById("lpb"),n=cur.doc.numPages;box.innerHTML="";var W=Math.max(280,Math.min(900,box.clientWidth-24)),dpr=Math.min(2,window.devicePixelRatio||1),slots=[];
  (async function(){var p1=await cur.doc.getPage(1);
    for(var i=1;i<=n;i++){var cv=document.createElement("canvas"),v1=p1.getViewport({scale:1}),sc=W/v1.width;cv.style.width=W+"px";cv.style.height=Math.floor(v1.height*sc)+"px";cv.width=1;cv.height=1;cv.dataset.p=i;box.appendChild(cv);slots.push(cv);}
    var io=new IntersectionObserver(function(es){es.forEach(function(en){if(!en.isIntersecting)return;var c=en.target;io.unobserve(c);draw(c);});},{root:box,rootMargin:"600px 0px"});
    slots.forEach(function(c){io.observe(c);});
    async function draw(c){var pg=await cur.doc.getPage(+c.dataset.p),v=pg.getViewport({scale:(W/pg.getViewport({scale:1}).width)*dpr});c.width=v.width;c.height=v.height;c.style.width=Math.floor(v.width/dpr)+"px";c.style.height=Math.floor(v.height/dpr)+"px";await pg.render({canvasContext:c.getContext("2d"),viewport:v}).promise;}
  })();
}
if(OPEN){var oc=document.querySelector('.lcard[data-id="'+OPEN+'"]');if(oc)openItem(oc);}
})();
</script>
</body>
</html>
