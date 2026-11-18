<?php
// In-site viewer for one signed document (works on phones and tablets), optionally opened at a page. Download button included.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/doclib_file.php";
$ref = (string)($_GET['ref'] ?? ''); $tx = (string)($_GET['tx'] ?? ''); $pg = max(1, (int)($_GET['p'] ?? 1));
$title = trim(preg_replace('/\s+/', ' ', (string)($_GET['t'] ?? 'Signed document'))); if (strlen($title) > 160) $title = substr($title, 0, 160);
$ok = dcf_ref($ref) !== null;
$only = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['only'] ?? ''))), function ($n) { return $n >= 1 && $n <= 999; })));
$back = preg_match('/^[A-Za-z0-9_-]{6,80}$/', $tx) ? 'buyer.php?tx=' . urlencode($tx) : 'my-transactions.php';
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>View document - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=20">
<style>.vbar{position:sticky;top:0;z-index:5;background:#fff;border-bottom:1px solid #dbe6e5;padding:10px 14px;display:flex;flex-wrap:wrap;gap:10px;align-items:center}.vbar b{flex:1 1 220px}.vpg{margin:0 auto 14px;box-shadow:0 1px 6px rgba(0,0,0,.18);background:#fff;max-width:100%}.vpg canvas{display:block;width:100%;height:auto}#vwrap{padding:14px;background:#eef3f3}</style>
</head><body>
<div class="vbar"><b><?= htmlspecialchars($title) ?></b>
<?php if ($ok): ?><a class="btn" href="doc_file.php?ref=<?= urlencode($ref) ?>">Download<?= $only ? ' whole document' : '' ?></a><?php if ($only): ?><button type="button" class="btn" id="dlpages">Download these pages</button><?php endif; endif; ?>
<a class="btn ghost" href="<?= htmlspecialchars($back) ?>">Back to client record</a></div>
<div id="vwrap"><p id="vmsg" class="note"><?= $ok ? 'Loading the document...' : 'That document link is not valid.' ?></p></div>
<?php if ($ok): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script>
(async function(){
  var wrap=document.getElementById("vwrap"),msg=document.getElementById("vmsg"),GO=<?= (int)$pg ?>,ONLY=<?= json_encode($only) ?>,RAW=null;
  try{
    pdfjsLib.GlobalWorkerOptions.workerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
    var r=await fetch("doc_file.php?view=1&ref="+encodeURIComponent(<?= json_encode($ref) ?>),{credentials:"same-origin"});
    if(!r.ok)throw new Error(await r.text());
    RAW=new Uint8Array(await r.arrayBuffer());var pdf=await pdfjsLib.getDocument({data:RAW.slice()}).promise,els=[];
    msg.remove();
    var w=Math.min(wrap.clientWidth-28,900);
    for(var n=1;n<=pdf.numPages;n++){
      if(ONLY.length&&ONLY.indexOf(n)<0)continue;
      var page=await pdf.getPage(n),v0=page.getViewport({scale:1}),sc=Math.max(0.5,w/v0.width),vp=page.getViewport({scale:sc*(window.devicePixelRatio>1?1.5:1)});
      var d=document.createElement("div");d.className="vpg";d.style.width=w+"px";d.id="p"+n;var c=document.createElement("canvas");c.width=vp.width;c.height=vp.height;d.appendChild(c);wrap.appendChild(d);
      await page.render({canvasContext:c.getContext("2d"),viewport:vp}).promise;
      if(n===GO)d.scrollIntoView();
    }
  }catch(e){msg.className="note sigerr";msg.textContent="Could not open the document: "+e.message;}
})();
document.getElementById("dlpages")&&document.getElementById("dlpages").addEventListener("click",async function(){
  var b=this;b.disabled=true;try{var PL=PDFLib,src=await PL.PDFDocument.load(RAW,{ignoreEncryption:true}),out=await PL.PDFDocument.create();
    (await out.copyPages(src,ONLY.filter(function(n){return n<=src.getPageCount();}).map(function(n){return n-1;}))).forEach(function(p){out.addPage(p);});
    var u=URL.createObjectURL(new Blob([await out.save()],{type:"application/pdf"})),a=document.createElement("a");a.href=u;a.download="Pages "+ONLY.join("-")+".pdf";document.body.appendChild(a);a.click();a.remove();}catch(e){var m=document.createElement("p");m.className="note sigerr";m.textContent="Could not prepare the pages: "+e.message;wrap.prepend(m);}b.disabled=false;});
</script>
<?php endif; ?>
</body></html>
