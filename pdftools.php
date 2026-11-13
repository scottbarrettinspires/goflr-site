<?php
// PDF Tools (opened from Settings): merge, split, rotate, reorganize pages, compress, and turn JPG/PNG pictures into a PDF.
// Everything happens in the person's own browser (pdf-lib and PDF.js), so the files are never uploaded to goflr.com.
require __DIR__ . "/guard.php";
require __DIR__ . "/subpage.php";
sp_head('PDF Tools');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
?>
<style>
.pt{max-width:980px}.pt h1{font-size:1.25rem;margin:0 0 4px}.pt .sub{color:#5a6a6c;margin:0 0 14px;font-size:.93rem}
.ptt{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px}
.ptt button{font:inherit;font-weight:700;padding:10px 16px;border-radius:12px;border:1px solid var(--line);background:var(--white);color:var(--teal);cursor:pointer}
.ptt button.on{background:var(--teal);color:#fff;border-color:var(--teal)}
.tp{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:16px 18px}
.tp h2{font-size:1.05rem;margin:0 0 4px}.tp .d{margin:0 0 12px;color:#5a6a6c;font-size:.92rem}
.pick{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 12px}
.pick label.fb{display:inline-block;padding:9px 16px;border-radius:10px;border:1px dashed var(--teal);color:var(--teal);font-weight:600;cursor:pointer;background:var(--teal-soft)}
.pick input[type=file]{position:absolute;width:1px;height:1px;opacity:0}
.pbtn{font:inherit;font-weight:700;padding:9px 18px;border-radius:10px;border:0;background:var(--teal);color:#fff;cursor:pointer}.pbtn:disabled{opacity:.5;cursor:default}
.gbtn{font:inherit;font-weight:600;padding:5px 10px;border-radius:8px;border:1px solid var(--line);background:#fff;color:var(--teal);cursor:pointer}
.fl{list-style:none;margin:0 0 12px;padding:0;display:grid;gap:6px}
.fl li{display:flex;gap:8px;align-items:center;border:1px solid var(--line);border-radius:10px;padding:6px 10px;background:#fbfdfd}
.fl li .nm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.fl li small{color:#5a6a6c}.fl li img{width:38px;height:38px;object-fit:cover;border-radius:6px}
.opt{display:flex;flex-wrap:wrap;gap:14px;align-items:center;margin:0 0 12px}.opt label{display:flex;gap:6px;align-items:center}.opt input[type=text],.opt select{font:inherit;padding:8px 10px;border:1px solid var(--line);border-radius:10px}
.pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;margin:0 0 14px}
.pgrid.big{grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.pgrid.big .th{height:min(46vw,380px)}.pgrid.big .pc{padding:10px}.pgrid.big .pn{font-size:.9rem}
@media (max-width:520px){.pgrid.big{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.pgrid.big .th{height:48vw}}
.pc .th{cursor:zoom-in}.pc[draggable=true] .th{cursor:grab}.pc.over{outline:3px solid var(--teal);outline-offset:1px}
#pv{position:fixed;inset:0;z-index:1000;background:rgba(10,20,22,.88);display:flex;align-items:center;justify-content:center}#pv[hidden]{display:none}
#pv .pvc{display:flex;flex-direction:column;align-items:center;gap:8px;max-width:calc(100vw - 140px)}#pv canvas{background:#fff;box-shadow:0 4px 24px rgba(0,0,0,.5);display:block}#pv .pvl{color:#fff;font-size:.9rem}
#pv button{font:inherit;border:0;cursor:pointer;color:#fff;background:rgba(255,255,255,.16);border-radius:50%}#pv button:hover{background:rgba(255,255,255,.3)}
#pv .pvn{width:52px;height:52px;font-size:2rem;line-height:1;margin:0 10px;flex:0 0 auto}#pv .pvx{position:absolute;top:14px;right:16px;width:44px;height:44px;font-size:1.8rem;line-height:1}
@media (max-width:520px){#pv .pvn{width:40px;height:40px;margin:0 4px}#pv .pvc{max-width:calc(100vw - 100px)}}
.pc{border:1px solid var(--line);border-radius:12px;padding:8px;background:#fbfdfd;text-align:center}.pc.drag{opacity:.4}
.pc .th{height:140px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#eef3f3;border-radius:8px}.pc .th img{max-width:100%;max-height:100%;transition:transform .15s;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.15)}
.pc .pn{font-size:.8rem;color:#5a6a6c;margin:6px 0 4px}.pc .pb{display:flex;gap:4px;justify-content:center;flex-wrap:wrap}
.pres{margin:14px 0 0;padding:12px;border-radius:12px;background:var(--teal-soft)}.pres ul{margin:6px 0 0;padding:0;list-style:none;display:grid;gap:6px}.pres a{font-weight:700;color:var(--teal)}
.sv{margin:12px 0 0;padding-top:10px;border-top:1px solid rgba(3,111,114,.25)}.sv .row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:6px 0}.sv input[type=search]{font:inherit;padding:8px 10px;border:1px solid var(--line);border-radius:10px;flex:1 1 180px;min-width:0}.sv select{font:inherit;padding:8px 10px;border:1px solid var(--line);border-radius:10px;flex:2 1 260px;min-width:0;max-width:100%}.sv .svm{margin:6px 0 0;font-size:.92rem}.sv .svm a{font-weight:700;color:var(--teal)}.sv .svm .bad{color:#b3261e;font-weight:600}
.pmsg{color:#5a6a6c;margin:8px 0 0;font-size:.92rem}.perr{color:#b3261e;font-weight:600;margin:8px 0 0}
</style>
<div class="pt">
  <h1>PDF Tools</h1>
  <p class="sub">Your files stay on your device. Nothing is uploaded to goflr.com.</p>
  <div class="ptt" id="ptt">
    <button type="button" data-t="merge" class="on">Merge</button><button type="button" data-t="split">Split</button><button type="button" data-t="rotate">Rotate</button><button type="button" data-t="reorder">Reorganize pages</button><button type="button" data-t="compress">Compress</button><button type="button" data-t="img">JPG / PNG to PDF</button>
  </div>

  <section class="tp" data-t="merge">
    <h2>Merge PDFs</h2><p class="d">Choose two or more PDFs, put them in the order you want, and combine them into one.</p>
    <div class="pick"><label class="fb">Choose PDFs<input type="file" accept="application/pdf,.pdf" multiple></label></div>
    <ul class="fl"></ul><button class="pbtn" disabled>Merge into one PDF</button><p class="pmsg"></p><p class="perr"></p><div class="pres" hidden></div>
  </section>

  <section class="tp" data-t="split" hidden>
    <h2>Split a PDF</h2><p class="d">Pull out the pages you want, or break the PDF into several files.</p>
    <div class="pick"><label class="fb">Choose a PDF<input type="file" accept="application/pdf,.pdf"></label><span class="fn"></span></div>
    <div class="opt"><label><input type="radio" name="sm" value="extract" checked> Pull out pages into one PDF</label><label><input type="radio" name="sm" value="parts"> Split into separate files</label></div>
    <div class="opt"><label>Pages <input type="text" class="rg" placeholder="e.g. 1-3, 5, 8-" size="22"></label><span class="pmsg" style="margin:0">Leave blank when splitting to get one file per page. With ranges, each range becomes its own file.</span></div>
    <button class="pbtn" disabled>Split</button><p class="pmsg"></p><p class="perr"></p><div class="pres" hidden></div>
  </section>

  <section class="tp" data-t="rotate" hidden>
    <h2>Rotate pages</h2><p class="d">Turn single pages, or the whole document, then save the corrected PDF.</p>
    <div class="pick"><label class="fb">Choose a PDF<input type="file" accept="application/pdf,.pdf"></label><span class="fn"></span></div>
    <div class="opt all" hidden><span>All pages:</span><button class="gbtn" data-r="-90">&#8634; Left</button><button class="gbtn" data-r="90">Right &#8635;</button><button class="gbtn" data-r="180">Upside down</button></div>
    <div class="pgrid"></div><button class="pbtn" disabled>Save rotated PDF</button><p class="pmsg"></p><p class="perr"></p><div class="pres" hidden></div>
  </section>

  <section class="tp" data-t="reorder" hidden>
    <h2>Reorganize pages</h2><p class="d">Drag pages (or use the arrows) to change the order, and remove pages you don't want.</p>
    <div class="pick"><label class="fb">Choose a PDF<input type="file" accept="application/pdf,.pdf"></label><span class="fn"></span></div>
    <div class="pgrid big"></div><button class="pbtn" disabled>Save reorganized PDF</button><p class="pmsg"></p><p class="perr"></p><div class="pres" hidden></div>
  </section>

  <section class="tp" data-t="compress" hidden>
    <h2>Compress a PDF</h2><p class="d">Make a large PDF small enough to email or upload.</p>
    <div class="pick"><label class="fb">Choose a PDF<input type="file" accept="application/pdf,.pdf"></label><span class="fn"></span></div>
    <div class="opt"><label><input type="radio" name="cm" value="light" checked> Light &mdash; keeps text sharp and selectable (small savings)</label></div>
    <div class="opt"><label><input type="radio" name="cm" value="good"> Good &mdash; pages become pictures (about 115 dpi)</label><label><input type="radio" name="cm" value="small"> Smaller (about 86 dpi)</label><label><input type="radio" name="cm" value="tiny"> Smallest (about 72 dpi)</label></div>
    <p class="pmsg" style="margin:0 0 12px">Good, Smaller and Smallest turn every page into a picture, so text can no longer be selected or searched, and signature fields stop working. Use them for files that are already finished. Keep your original.</p>
    <button class="pbtn" disabled>Compress</button><p class="pmsg"></p><p class="perr"></p><div class="pres" hidden></div>
  </section>

  <section class="tp" data-t="img" hidden>
    <h2>JPG / PNG to PDF</h2><p class="d">Turn pictures or photos into one PDF, one picture per page, in the order you set.</p>
    <div class="pick"><label class="fb">Choose pictures<input type="file" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png" multiple></label></div>
    <div class="opt"><span>Page size:</span><label><input type="radio" name="ps" value="letter" checked> Letter (8.5x11)</label><label><input type="radio" name="ps" value="fit"> Same size as each picture</label></div>
    <ul class="fl"></ul><button class="pbtn" disabled>Create PDF</button><p class="pmsg"></p><p class="perr"></p><div class="pres" hidden></div>
  </section>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function(){
var PL=window.PDFLib;if(window.pdfjsLib&&!pdfjsLib.GlobalWorkerOptions.workerSrc)pdfjsLib.GlobalWorkerOptions.workerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}
function fmt(n){return n>=1048576?(n/1048576).toFixed(1)+" MB":Math.max(1,Math.round(n/1024))+" KB";}
function base(n){return String(n).replace(/\.[^.]+$/,"").replace(/[^\w .()-]+/g,"").trim()||"document";}
function sec(t){return document.querySelector('.tp[data-t="'+t+'"]');}
function $(s,r){return r.querySelector(s);}
function err(r,m){$(".perr",r).textContent=m||"";}
function msg(r,m){$(".pmsg",r).textContent=m||"";}
function lib(){if(!PL||!window.pdfjsLib){throw new Error("The PDF tools could not load. Please check your connection and reload the page.");}}
function nice(e){var m=String(e&&e.message||e);return /encrypt|password/i.test(m)?"This PDF is password-protected. Remove the password first, then try again.":m;}
// results: show a Download link for each file made (made in this browser; nothing is sent anywhere)
function results(r,items,note){var box=$(".pres",r);box.hidden=false;
  box.innerHTML='<b>Done.</b> '+esc(note||"")+'<ul>'+items.map(function(it,i){var u=URL.createObjectURL(new Blob([it.bytes],{type:"application/pdf"}));return '<li><a href="'+u+'" download="'+esc(it.name)+'">Download '+esc(it.name)+'</a> <small>('+fmt(it.bytes.length)+(it.pages?", "+it.pages+" page"+(it.pages===1?"":"s"):"")+')</small></li>';}).join("")+'</ul>'
   +'<div class="sv"><b>Or save '+(items.length>1?"these "+items.length+" files":"it")+' to an Active Business record</b><div class="row"><input type="search" class="svq" placeholder="Search your records by name" aria-label="Search Active Business records"><select class="svs" aria-label="Active Business record"><option value="">Loading your records...</option></select><button type="button" class="gbtn svb" disabled>Save to record</button></div><p class="svm"></p></div>';
  picker(box,items);box.scrollIntoView({behavior:"smooth",block:"nearest"});}
// ---- save to an Active Business record (uses the same folder rules as the rest of the site) ----
var RECS=null;
function loadRecs(){if(RECS)return RECS;RECS=fetch("pdftools_api.php?op=records",{credentials:"same-origin"}).then(function(x){return x.json();}).then(function(j){if(!j.ok)throw new Error(j.msg||"Could not load your records.");return j.records;});RECS.catch(function(){RECS=null;});return RECS;}
function picker(box,items){
  var q=$(".svq",box),sel=$(".svs",box),b=$(".svb",box),m=$(".svm",box),all=[];
  function fill(){var t=q.value.trim().toLowerCase(),keep=sel.value,groups={},order=[];
    all.forEach(function(r){if(t&&(r.name+" "+r.section).toLowerCase().indexOf(t)<0)return;(groups[r.section]=groups[r.section]||(order.push(r.section),[])).push(r);});
    var n=0;sel.innerHTML='<option value="">'+(all.length?"Choose a record...":"No Active Business records found")+'</option>'+order.map(function(g){return '<optgroup label="'+esc(g)+'">'+groups[g].map(function(r){n++;return '<option value="'+esc(r.id)+'"'+(r.id===keep?" selected":"")+'>'+esc(r.name)+'</option>';}).join("")+'</optgroup>';}).join("");
    if(t&&n===1)sel.selectedIndex=1;if(t&&!n)sel.innerHTML='<option value="">Nothing matches "'+esc(q.value.trim())+'"</option>';b.disabled=!sel.value;}
  loadRecs().then(function(r){all=r;fill();}).catch(function(e){sel.innerHTML='<option value="">Records unavailable</option>';m.innerHTML='<span class="bad">'+esc(e.message)+'</span>';});
  q.oninput=fill;sel.onchange=function(){b.disabled=!sel.value;};
  b.onclick=async function(){if(!sel.value)return;b.disabled=true;var name=sel.options[sel.selectedIndex].text,out=[];
    for(var i=0;i<items.length;i++){m.textContent="Saving "+(i+1)+" of "+items.length+"...";
      try{var fd=new FormData();fd.append("csrf",<?= json_encode($_SESSION['csrf']) ?>);fd.append("folder",sel.value);fd.append("name",items[i].name);fd.append("file",new Blob([items[i].bytes],{type:"application/pdf"}),items[i].name);
        var j=await (await fetch("pdftools_api.php",{method:"POST",body:fd,credentials:"same-origin"})).json();
        out.push(j.ok?'Saved "'+esc(j.name)+'" to <b>'+esc(j.folder)+'</b>'+(j.folder_url?' &middot; <a href="'+esc(j.folder_url)+'" target="_blank" rel="noopener">Open the folder</a>':''):'<span class="bad">'+esc(j.msg||"Could not save.")+'</span>');}
      catch(e){out.push('<span class="bad">Could not save "'+esc(items[i].name)+'". Please try again.</span>');}}
    m.innerHTML=out.join("<br>");b.disabled=false;};
}
async function loadDoc(buf){try{return await PL.PDFDocument.load(buf);}catch(e){throw new Error(nice(e));}}
function setBusy(btn,on,label){btn.disabled=on;if(label!==undefined)btn.textContent=label;}

// tabs
var tabs=document.getElementById("ptt");tabs.addEventListener("click",function(e){var b=e.target.closest("button[data-t]");if(!b)return;tabs.querySelectorAll("button").forEach(function(x){x.classList.toggle("on",x===b);});document.querySelectorAll(".tp").forEach(function(s){s.hidden=s.dataset.t!==b.dataset.t;});});

// ---- ordered file lists (Merge, pictures) ----
function listTool(r,kind){
  var files=[],ul=$(".fl",r),btn=$(".pbtn",r),inp=$('input[type=file]',r),label=btn.textContent;
  function draw(){ul.innerHTML=files.map(function(f,i){return '<li>'+(kind==="img"?'<img src="'+f.url+'" alt="">':'')+'<span class="nm">'+esc(f.file.name)+'</span><small>'+fmt(f.file.size)+'</small><button class="gbtn" data-a="up" data-i="'+i+'"'+(i?'':' disabled')+'>&uarr;</button><button class="gbtn" data-a="dn" data-i="'+i+'"'+(i<files.length-1?'':' disabled')+'>&darr;</button><button class="gbtn" data-a="rm" data-i="'+i+'">Remove</button></li>';}).join("");btn.disabled=kind==="img"?files.length<1:files.length<2;}
  inp.onchange=function(){Array.prototype.forEach.call(inp.files,function(f){files.push({file:f,url:kind==="img"?URL.createObjectURL(f):""});});inp.value="";err(r,"");$(".pres",r).hidden=true;draw();};
  ul.onclick=function(e){var b=e.target.closest("button[data-a]");if(!b)return;var i=+b.dataset.i,a=b.dataset.a;if(a==="rm")files.splice(i,1);else{var j=a==="up"?i-1:i+1;var t=files[i];files[i]=files[j];files[j]=t;}draw();};
  return {files:function(){return files;},btn:btn,label:label};
}
var mg=sec("merge"),mgl=listTool(mg,"pdf");
mgl.btn.onclick=async function(){err(mg,"");msg(mg,"");setBusy(mgl.btn,true,"Merging...");
  try{lib();var out=await PL.PDFDocument.create(),fs=mgl.files();
    for(var i=0;i<fs.length;i++){msg(mg,"Adding "+fs[i].file.name+" ("+(i+1)+" of "+fs.length+")...");var d=await loadDoc(await fs[i].file.arrayBuffer());var pg=await out.copyPages(d,d.getPageIndices());pg.forEach(function(p){out.addPage(p);});}
    var bytes=await out.save();msg(mg,"");results(mg,[{name:"merged.pdf",bytes:bytes,pages:out.getPageCount()}],"Your "+fs.length+" PDFs are combined.");}
  catch(e){err(mg,nice(e));msg(mg,"");}
  setBusy(mgl.btn,mgl.files().length<2,mgl.label);};

// ---- pictures to PDF ----
var im=sec("img"),iml=listTool(im,"img");
function jpegOri(buf){var v=new DataView(buf);if(v.getUint16(0)!==0xFFD8)return 1;var o=2;while(o<v.byteLength-4){var m=v.getUint16(o);if(m===0xFFE1){var le=v.getUint16(o+10)===0x4949,n=v.getUint16(o+18,le),p=o+20;for(var i=0;i<n;i++){if(v.getUint16(p+i*12,le)===0x0112)return v.getUint16(p+i*12+8,le);}return 1;}if((m&0xFF00)!==0xFF00)break;o+=2+v.getUint16(o+2);}return 1;}
async function viaCanvas(file,type){var bm=await createImageBitmap(file),c=document.createElement("canvas");c.width=bm.width;c.height=bm.height;var x=c.getContext("2d");if(type==="image/jpeg"){x.fillStyle="#fff";x.fillRect(0,0,c.width,c.height);}x.drawImage(bm,0,0);
  return new Promise(function(ok){c.toBlob(function(b){b.arrayBuffer().then(function(a){ok(a);});},type,0.92);});}
iml.btn.onclick=async function(){err(im,"");msg(im,"");setBusy(iml.btn,true,"Creating...");
  try{lib();var out=await PL.PDFDocument.create(),fs=iml.files(),letter=im.querySelector('input[name=ps]:checked').value==="letter";
    for(var i=0;i<fs.length;i++){msg(im,"Picture "+(i+1)+" of "+fs.length+"...");var f=fs[i].file,t=f.type,buf=await f.arrayBuffer(),pic;
      if(t==="image/jpeg"){pic=jpegOri(buf)>1?await out.embedJpg(await viaCanvas(f,"image/jpeg")):await out.embedJpg(buf);}
      else if(t==="image/png"){pic=await out.embedPng(buf);}
      else{pic=await out.embedPng(await viaCanvas(f,"image/png"));}
      var w=pic.width*0.75,h=pic.height*0.75,pg;
      if(letter){var L=w>h,PW=L?792:612,PH=L?612:792,m=36,s=Math.min((PW-2*m)/pic.width,(PH-2*m)/pic.height);pg=out.addPage([PW,PH]);pg.drawImage(pic,{x:(PW-pic.width*s)/2,y:(PH-pic.height*s)/2,width:pic.width*s,height:pic.height*s});}
      else{pg=out.addPage([w,h]);pg.drawImage(pic,{x:0,y:0,width:w,height:h});}}
    var bytes=await out.save();msg(im,"");results(im,[{name:(fs.length===1?base(fs[0].file.name):"pictures")+".pdf",bytes:bytes,pages:fs.length}],"");}
  catch(e){err(im,nice(e));msg(im,"");}
  setBusy(iml.btn,iml.files().length<1,iml.label);};

// ---- one-PDF tools: pick, load ----
function onePdf(r,ready){var inp=$('input[type=file]',r);inp.onchange=async function(){var f=inp.files[0];inp.value="";if(!f)return;err(r,"");msg(r,"Opening "+f.name+"...");$(".pres",r).hidden=true;
  try{lib();var buf=await f.arrayBuffer();await ready(f,buf);msg(r,"");}catch(e){err(r,nice(e));msg(r,"");}};}

// ---- Split ----
var sp=sec("split"),spS=null;
onePdf(sp,async function(f,buf){var d=await loadDoc(buf);spS={name:f.name,buf:buf,n:d.getPageCount()};$(".fn",sp).textContent=f.name+" ("+d.getPageCount()+" pages)";$(".pbtn",sp).disabled=false;});
function ranges(txt,n){var out=[];String(txt).split(",").forEach(function(p){p=p.trim();if(!p)return;var m=p.match(/^(\d*)\s*-\s*(\d*)$/),a,b;if(m){a=m[1]?+m[1]:1;b=m[2]?+m[2]:n;}else if(/^\d+$/.test(p)){a=b=+p;}else throw new Error('"'+p+'" is not a page or range. Use something like 1-3, 5, 8-');
  if(a<1||b>n||a>b)throw new Error("Pages must be between 1 and "+n+" ("+p+" is not).");out.push([a,b]);});return out;}
$(".pbtn",sp).onclick=async function(){var b=this;err(sp,"");setBusy(b,true,"Working...");
  try{if(!spS)return;var src=await loadDoc(spS.buf),mode=sp.querySelector('input[name=sm]:checked').value,rg=ranges($(".rg",sp).value,spS.n),items=[],nm=base(spS.name);
    async function make(list,label){var o=await PL.PDFDocument.create(),pg=await o.copyPages(src,list.map(function(i){return i-1;}));pg.forEach(function(p){o.addPage(p);});return {name:nm+label+".pdf",bytes:await o.save(),pages:list.length};}
    function span(a,z){var l=[];for(var i=a;i<=z;i++)l.push(i);return l;}
    if(mode==="extract"){if(!rg.length)throw new Error("Type the pages you want, for example 1-3, 5.");var all=[];rg.forEach(function(x){all=all.concat(span(x[0],x[1]));});items.push(await make(all," (pages)"));}
    else if(!rg.length){for(var i=1;i<=spS.n;i++)items.push(await make([i]," - page "+i));}
    else{for(var j=0;j<rg.length;j++){items.push(await make(span(rg[j][0],rg[j][1]),rg[j][0]===rg[j][1]?" - page "+rg[j][0]:" - pages "+rg[j][0]+"-"+rg[j][1]));}}
    results(sp,items,items.length>1?items.length+" files are ready. Download each one below.":"");}
  catch(e){err(sp,nice(e));}
  setBusy(b,!spS,"Split");};

// ---- Click a page to see it larger ----
var PV=null;
function viewer(){if(PV)return PV;var d=document.createElement("div");d.id="pv";d.hidden=true;d.innerHTML='<button class="pvx" type="button" aria-label="Close">&times;</button><button class="pvn" data-d="-1" type="button" aria-label="Previous page">&lsaquo;</button><div class="pvc"><canvas></canvas><div class="pvl"></div></div><button class="pvn" data-d="1" type="button" aria-label="Next page">&rsaquo;</button>';document.body.appendChild(d);
  var st={js:null,list:[],k:0,seq:0},cv=d.querySelector("canvas"),lb=d.querySelector(".pvl");
  async function show(){var my=++st.seq,it=st.list[st.k];lb.textContent="Page "+(it.s+1)+" \u00b7 position "+(st.k+1)+" of "+st.list.length;
    try{var pg=await st.js.getPage(it.s+1),rot=((pg.rotate||0)+it.rot)%360,v1=pg.getViewport({scale:1,rotation:rot}),fit=Math.min((window.innerWidth-150)/v1.width,(window.innerHeight-90)/v1.height),dpr=Math.min(2,window.devicePixelRatio||1),vp=pg.getViewport({scale:fit*dpr,rotation:rot});
      if(my!==st.seq)return;var tmp=document.createElement("canvas");tmp.width=Math.ceil(vp.width);tmp.height=Math.ceil(vp.height);await pg.render({canvasContext:tmp.getContext("2d"),viewport:vp}).promise;if(my!==st.seq)return;
      cv.width=tmp.width;cv.height=tmp.height;cv.style.width=Math.floor(vp.width/dpr)+"px";cv.style.height=Math.floor(vp.height/dpr)+"px";cv.getContext("2d").drawImage(tmp,0,0);}catch(e){}}
  function go(n){st.k=Math.max(0,Math.min(st.list.length-1,st.k+n));show();}
  function close(){d.hidden=true;st.seq++;}
  d.addEventListener("click",function(e){if(e.target.closest(".pvx")||e.target===d){close();return;}var b=e.target.closest(".pvn");if(b)go(+b.dataset.d);});
  document.addEventListener("keydown",function(e){if(d.hidden)return;if(e.key==="Escape")close();else if(e.key==="ArrowRight")go(1);else if(e.key==="ArrowLeft")go(-1);});
  PV={open:function(js,list,k){st.js=js;st.list=list;st.k=k;d.hidden=false;show();}};return PV;}

// ---- Rotate / Reorganize (page grid) ----
function gridTool(r,mode){
  var S=null,pages=[],thumbs={},grid=$(".pgrid",r),btn=$(".pbtn",r),drag=-1;
  async function thumb(i){if(thumbs[i])return thumbs[i];var pg=await S.js.getPage(i+1),v1=pg.getViewport({scale:1}),vp=pg.getViewport({scale:(mode==="reorder"?560:210)/Math.max(v1.width,v1.height)}),c=document.createElement("canvas");c.width=vp.width;c.height=vp.height;await pg.render({canvasContext:c.getContext("2d"),viewport:vp}).promise;thumbs[i]=c.toDataURL("image/jpeg",0.7);return thumbs[i];}
  var io=new IntersectionObserver(function(es){es.forEach(function(en){if(!en.isIntersecting)return;var im=en.target;io.unobserve(im);thumb(+im.dataset.s).then(function(u){im.src=u;}).catch(function(){});});},{rootMargin:"200px"});
  function draw(){grid.innerHTML=pages.map(function(p,k){return '<div class="pc" data-k="'+k+'"'+(mode==="reorder"?' draggable="true"':'')+'><div class="th"><img data-s="'+p.s+'" alt="" style="transform:rotate('+p.rot+'deg)"'+(thumbs[p.s]?' src="'+thumbs[p.s]+'"':'')+'></div><div class="pn">Page '+(p.s+1)+(mode==="reorder"?' &middot; now '+(k+1):'')+'</div><div class="pb">'+(mode==="rotate"?'<button class="gbtn" data-a="l" title="Rotate left">&#8634;</button><button class="gbtn" data-a="r" title="Rotate right">&#8635;</button>':'<button class="gbtn" data-a="b"'+(k?'':' disabled')+' title="Move earlier">&larr;</button><button class="gbtn" data-a="f"'+(k<pages.length-1?'':' disabled')+' title="Move later">&rarr;</button><button class="gbtn" data-a="x" title="Remove this page">Remove</button>')+'</div></div>';}).join("");
    grid.querySelectorAll("img:not([src])").forEach(function(im){io.observe(im);});btn.disabled=!pages.length;}
  grid.onclick=function(e){var b=e.target.closest("button[data-a]");if(!b){var th=e.target.closest(".th");if(th&&S)viewer().open(S.js,pages,+th.closest(".pc").dataset.k);return;}var k=+b.closest(".pc").dataset.k,a=b.dataset.a;
    if(a==="l")pages[k].rot=(pages[k].rot+270)%360;else if(a==="r")pages[k].rot=(pages[k].rot+90)%360;
    else if(a==="x")pages.splice(k,1);else{var j=a==="b"?k-1:k+1,t=pages[k];pages[k]=pages[j];pages[j]=t;}draw();};
  if(mode==="reorder"){grid.ondragstart=function(e){var c=e.target.closest(".pc");if(!c)return;drag=+c.dataset.k;c.classList.add("drag");e.dataTransfer.effectAllowed="move";try{e.dataTransfer.setData("text/plain","p");}catch(x){}};
    grid.ondragover=function(e){if(drag<0)return;e.preventDefault();var c=e.target.closest(".pc");grid.querySelectorAll(".pc.over").forEach(function(x){if(x!==c)x.classList.remove("over");});if(c&&+c.dataset.k!==drag)c.classList.add("over");};grid.ondragend=function(){drag=-1;draw();};
    grid.ondrop=function(e){e.preventDefault();var c=e.target.closest(".pc");if(!c||drag<0)return;var to=+c.dataset.k,m=pages.splice(drag,1)[0];pages.splice(to,0,m);drag=-1;draw();};}
  var all=$(".all",r);if(all)all.onclick=function(e){var b=e.target.closest("button[data-r]");if(!b)return;pages.forEach(function(p){p.rot=((p.rot+ +b.dataset.r)%360+360)%360;});draw();};
  onePdf(r,async function(f,buf){var d=await loadDoc(buf),js=await pdfjsLib.getDocument({data:new Uint8Array(buf.slice(0))}).promise;
    S={name:f.name,buf:buf,js:js};thumbs={};pages=[];for(var i=0;i<d.getPageCount();i++)pages.push({s:i,rot:0});$(".fn",r).textContent=f.name+" ("+pages.length+" pages)";if(all)all.hidden=false;draw();});
  btn.onclick=async function(){err(r,"");setBusy(btn,true,"Saving...");
    try{var src=await loadDoc(S.buf),o=await PL.PDFDocument.create(),cp=await o.copyPages(src,pages.map(function(p){return p.s;}));
      cp.forEach(function(pg,k){if(pages[k].rot)pg.setRotation(PL.degrees((pg.getRotation().angle+pages[k].rot)%360));o.addPage(pg);});
      results(r,[{name:base(S.name)+(mode==="rotate"?" (rotated)":" (reorganized)")+".pdf",bytes:await o.save(),pages:cp.length}],"");}
    catch(e){err(r,nice(e));}
    setBusy(btn,!pages.length,mode==="rotate"?"Save rotated PDF":"Save reorganized PDF");};
}
gridTool(sec("rotate"),"rotate");gridTool(sec("reorder"),"reorder");

// ---- Compress ----
var cm=sec("compress"),cS=null;
onePdf(cm,async function(f,buf){var d=await loadDoc(buf);cS={name:f.name,buf:buf,n:d.getPageCount()};$(".fn",cm).textContent=f.name+" ("+d.getPageCount()+" pages, "+fmt(buf.byteLength)+")";$(".pbtn",cm).disabled=false;});
$(".pbtn",cm).onclick=async function(){var b=this;err(cm,"");setBusy(b,true,"Compressing...");
  try{if(!cS)return;var mode=cm.querySelector('input[name=cm]:checked').value,bytes,pages=cS.n;
    if(mode==="light"){var d=await loadDoc(cS.buf);d.setProducer("");bytes=await d.save({useObjectStreams:true});}
    else{var q={good:[1.6,0.7],small:[1.2,0.55],tiny:[1.0,0.4]}[mode],js=await pdfjsLib.getDocument({data:new Uint8Array(cS.buf.slice(0))}).promise,o=await PL.PDFDocument.create(),c=document.createElement("canvas");
      for(var i=1;i<=js.numPages;i++){msg(cm,"Page "+i+" of "+js.numPages+"...");var pg=await js.getPage(i),v1=pg.getViewport({scale:1}),vp=pg.getViewport({scale:q[0]});c.width=Math.ceil(vp.width);c.height=Math.ceil(vp.height);var x=c.getContext("2d");x.fillStyle="#fff";x.fillRect(0,0,c.width,c.height);await pg.render({canvasContext:x,viewport:vp}).promise;
        var jb=await new Promise(function(ok){c.toBlob(ok,"image/jpeg",q[1]);}),pic=await o.embedJpg(await jb.arrayBuffer()),np=o.addPage([v1.width,v1.height]);np.drawImage(pic,{x:0,y:0,width:v1.width,height:v1.height});}
      bytes=await o.save();if(js.destroy)js.destroy();}
    msg(cm,"");var was=cS.buf.byteLength,note=bytes.length<was?"From "+fmt(was)+" down to "+fmt(bytes.length)+" ("+Math.round(100-bytes.length*100/was)+"% smaller).":"This PDF did not get smaller ("+fmt(was)+" to "+fmt(bytes.length)+"). It is probably already compressed; try a smaller setting or keep the original.";
    results(cm,[{name:base(cS.name)+" (compressed).pdf",bytes:bytes,pages:pages}],note);}
  catch(e){err(cm,nice(e));msg(cm,"");}
  setBusy(b,!cS,"Compress");};
})();
</script>
<?php sp_foot();
