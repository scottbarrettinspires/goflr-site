<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/leadlib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
list($agent, $phone) = ld_me();
ld_autosort($me);
$leads = ld_view($me, $agent, $phone);
$intake = ld_intake($me, $agent, $phone);
$intakeUrl = 'https://goflr.com/lead_in.php?k=' . ($intake['key'] ?? '');
$ldb = ld_load($me); $mySources = (array)($ldb['sources'] ?? []); $myInterests = (array)($ldb['interests'] ?? []);
$cats = [];
foreach (LD_CATS as $k => $c) { $cats[$k] = ['label' => $c['label'], 'subs' => $c['subs'], 'stage' => LD_STAGE[$k]]; }
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
<title>Leads - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=16">
<style>
.ltabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}
.ltabs button{font:inherit;font-weight:700;padding:11px 18px;border-radius:12px;border:1px solid var(--line);background:var(--white);color:var(--teal);cursor:pointer;display:flex;gap:8px;align-items:center}
.ltabs button.on{background:var(--teal);color:#fff;border-color:var(--teal)}
.ltabs button .n{font-size:.78rem;font-weight:700;background:rgba(0,0,0,.08);border-radius:99px;padding:1px 8px}
.ltabs button.on .n{background:rgba(255,255,255,.25)}
.ltabs .ov{color:#5a6a6c;font-weight:600}
.lwrap{display:grid;grid-template-columns:220px 1fr;gap:18px;align-items:start}
.lside{display:flex;flex-direction:column;gap:8px;position:sticky;top:12px}
.sb{font:inherit;font-weight:700;text-align:left;padding:12px 14px;border-radius:12px;border:2px solid rgba(0,0,0,.08);cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:8px}
.sb .n{font-size:.8rem;opacity:.85}
.sb.on{outline:3px solid #1f2a2c;outline-offset:2px}
.h0{background:#b71c1c;color:#fff}.h1{background:#e53935;color:#fff}.h2{background:#f6b1aa;color:#5c1410}.h3{background:#fff;color:#2b3a3c}.h4{background:#a9cbe9;color:#0f3558}.h5{background:#1f5fa8;color:#fff}.h6{background:#036f72;color:#fff}
.lpanel{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:16px;min-height:200px}
.lpanel h2{margin:0 0 12px;font-size:1.15rem}
.lrow{display:flex;gap:12px;align-items:center;padding:12px;border:1px solid var(--line);border-radius:12px;margin:0 0 8px;cursor:pointer;background:var(--white)}
.lrow:hover{border-color:var(--teal)}
.lrow .who{flex:1;min-width:0}.lrow .nm{font-weight:700}.lrow .meta{font-size:.82rem;color:#5a6a6c;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dot{width:12px;height:12px;border-radius:50%;flex:0 0 12px;border:1px solid rgba(0,0,0,.15)}
.due{font-size:.78rem;font-weight:700;padding:3px 9px;border-radius:99px;white-space:nowrap}
.due.over{background:#fde2e1;color:#b71c1c}.due.today{background:#fff1d6;color:#8a5a00}.due.later{background:#eef3f3;color:#4b5b5d}.due.off{background:#e8eef7;color:#1f5fa8}
.qa a{display:inline-flex;width:34px;height:34px;border-radius:9px;align-items:center;justify-content:center;background:var(--teal-soft);color:var(--teal);text-decoration:none;font-size:.95rem;margin-left:4px}
.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin:0 0 18px}
.stat{background:var(--white);border:1px solid var(--line);border-radius:12px;padding:12px 14px}.stat b{display:block;font-size:1.6rem;line-height:1.1}.stat span{font-size:.82rem;color:#5a6a6c}
.stat.hot{border-left:5px solid #e53935}.stat.cold{border-left:5px solid #1f5fa8}
.empty{color:#6a777a;padding:18px;text-align:center}
.mback{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;z-index:50;overflow:auto;padding:24px 12px}
.mback.show{display:block}
.modal{background:var(--white);max-width:760px;margin:0 auto;border-radius:16px;padding:20px}
.modal h2{margin:0 0 12px}
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.fgrid label{font-size:.82rem;font-weight:600;color:#4b5b5d;display:block}
.fgrid input,.fgrid select,.fgrid textarea,.touch textarea,.touch input{width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:9px;font:inherit;box-sizing:border-box}
.fgrid .full{grid-column:1/-1}
.touch{border:1px solid var(--line);border-radius:12px;padding:12px;margin:12px 0}
.touch .hd{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;font-weight:700;margin:0 0 6px}
.touch .goal{font-size:.82rem;color:#5a6a6c;margin:6px 0}
.btns{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.btn.sm{padding:8px 12px;font-size:.9rem}
.hist{font-size:.85rem;color:#4b5b5d;max-height:160px;overflow:auto;border-top:1px solid var(--line);margin-top:10px;padding-top:8px}
.mvbox{border:1px solid var(--line);border-radius:12px;padding:12px;margin:12px 0}.mvbox h3{margin:0 0 8px;font-size:1rem}
.mvrow{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:0 0 8px}.mvrow b{width:64px;font-size:.88rem}
.mvb{font:inherit;font-size:.85rem;font-weight:700;padding:7px 11px;border-radius:9px;border:2px solid rgba(0,0,0,.1);cursor:pointer;opacity:.55}
.mvb.on{opacity:1;outline:3px solid #1f2a2c;outline-offset:1px}.mvb.none{background:#f1f4f4;color:#4b5b5d}.mvb:hover{opacity:1}
.chips{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0}.picksum{display:contents}.picksum .goal{font-size:.88rem;color:#6a777a}.chip{font:inherit;font-size:.85rem;font-weight:600;padding:7px 12px;border-radius:99px;border:1px solid var(--line);background:#fff;color:#4b5b5d;cursor:pointer}
.chip.on{background:var(--teal);border-color:var(--teal);color:#fff}.chip .n{font-weight:700;opacity:.8;margin-left:4px}
.ltabs .srcbtn{margin-left:auto;font-weight:600;color:#4b5b5d}
.bulk{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#f4f8f8;border:1px solid var(--line);border-radius:12px;padding:10px 12px;margin:0 0 12px}
.lsearch{width:100%;max-width:340px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;font:inherit;margin:0 0 12px}
@media(max-width:800px){.lwrap{grid-template-columns:1fr}.lside{position:static;flex-direction:row;flex-wrap:wrap}.sb{flex:1 1 45%}.fgrid{grid-template-columns:1fr}}
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="my-transactions.php">My Transactions</a>
    <a href="leads.php" aria-current="page">Leads</a>
    <a href="forms.php">Forms</a>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1>Leads</h1><p>Hot leads are red, cold leads are blue. Work the reds first.</p></section>
<main class="wrap">
  <div class="ltabs" id="tabs"></div>
  <div id="body"></div>
</main>
<div class="mback" id="mback"><div class="modal" id="modal"></div></div>
<footer>Florida Luxury Realty, Inc. &middot; Your leads are private to your account.</footer>
<script>
(function(){
var CSRF=<?= json_encode($_SESSION['csrf']) ?>, ALLSRC=<?= json_encode(LD_SOURCES) ?>, MYSRC=<?= json_encode(array_values($mySources)) ?>, ALLINT=<?= json_encode(LD_INTERESTS) ?>, MYINT=<?= json_encode(array_values($myInterests)) ?>, CATS=<?= json_encode($cats) ?>, LEADS=<?= json_encode($leads) ?>, TODAY=<?= json_encode(ld_today()) ?>, INTAKE=<?= json_encode($intakeUrl) ?>;
var HEAT={new:0,ready:1,a:1,unid:1,soon:2,b:2,later:3,c:3,d:4,dnc:5,client:6};
var SOURCES=ALLSRC;
var view={tab:"overview",sub:null,q:""};
var tabs=document.getElementById("tabs"),body=document.getElementById("body"),mback=document.getElementById("mback"),modal=document.getElementById("modal");
function esc(t){return String(t==null?"":t).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}
function nm(L){return ((L.first||"")+" "+(L.last||"")).trim()||L.phone||L.email||"(no name)";}
function fmtD(d){if(!d)return "";var x=new Date(d+"T12:00:00");return x.toLocaleDateString(undefined,{month:"short",day:"numeric"});}
function digits(p){return String(p||"").replace(/[^0-9+]/g,"");}
var ICON={new:"\u2728",ready:"\uD83D\uDD25",unid:"\u2754",dnc:"\u274C",a:"\uD83D\uDD25",client:"\uD83E\uDD1D"};
var THERMO={soon:0.75,b:0.75,later:0.25,c:0.25,d:0.08};
// A small thermometer filled to show how close the lead is to hot.
function thermo(f){var top=2,bot=16,h=Math.round((bot-top)*f),y=bot-h;
  return '<svg width="14" height="22" viewBox="0 0 14 22" aria-hidden="true" style="vertical-align:-5px;margin-right:3px"><rect x="4" y="1" width="6" height="16" rx="3" fill="#fff" stroke="#7a2a24" stroke-width="1.2"/>'
    +(h>0?'<rect x="5.5" y="'+y+'" width="3" height="'+(h+2)+'" fill="#d32f2f"/>':'')+'<circle cx="7" cy="18" r="3.4" fill="#d32f2f" stroke="#7a2a24" stroke-width="1.2"/></svg>';}
function ico(sub){if(THERMO[sub]!=null)return thermo(THERMO[sub])+" ";return ICON[sub]?ICON[sub]+" ":"";}
function subLabel(role,sub){if(role==="new")return "NEW";if(sub==="client")return "In My Transactions";return (CATS[role]&&CATS[role].subs[sub])||sub;}
function heatClass(sub){return "h"+({new:0,ready:1,a:1,soon:2,b:2,later:3,c:3,unid:4,d:4,dnc:5,client:6}[sub]);}
function inRole(L,role){return role==="new"?!L.view.some(function(v){return v.role!=="new";}):!!(L.roles&&L.roles[role]);}
function vfor(L,role){for(var i=0;i<L.view.length;i++){if(L.view[i].role===role)return L.view[i];}return null;}
function dueBadge(v){
  if(!v)return "";
  if(v.sub==="client")return '<span class="due" style="background:#e1f3f2;color:#036f72">In My Transactions</span>';
  if(v.sub==="dnc")return '<span class="due off">Do not contact</span>';
  if(v.done)return '<span class="due later">Plan finished</span>';
  if(v.over>0)return '<span class="due over">Overdue '+v.over+' day'+(v.over===1?"":"s")+' · '+esc(v.ch)+'</span>';
  if(v.over===0)return '<span class="due today">Due today · '+esc(v.ch)+'</span>';
  return '<span class="due later">'+esc(fmtD(v.due))+' · '+esc(v.ch)+'</span>';
}
function quick(L){var h='<span class="qa">';if(L.phone){h+='<a href="tel:'+esc(digits(L.phone))+'" title="Call" onclick="event.stopPropagation()">&#9742;</a><a href="sms:'+esc(digits(L.phone))+'" title="Text" onclick="event.stopPropagation()">&#9993;</a>';}
  if(L.email){h+='<a href="mailto:'+esc(L.email)+'" title="Email" onclick="event.stopPropagation()">@</a>';}return h+'</span>';}
function row(L,v){
  return '<div class="lrow" data-id="'+esc(L.id)+'"><span class="dot '+heatClass(v.sub)+'"></span><div class="who"><div class="nm">'+esc(nm(L))+'</div><div class="meta">'+esc((v.role==="new"?"NEW":CATS[v.role].label+" · "+subLabel(v.role,v.sub))+(L.source?" · "+L.source:"")+(L.phone?" · "+L.phone:""))+'</div></div>'+dueBadge(v)+quick(L)+'</div>';
}
function counts(){var c={new:0,buyer:0,seller:0,sphere:0};LEADS.forEach(function(L){["new","buyer","seller","sphere"].forEach(function(r){if(inRole(L,r))c[r]++;});});return c;}
function renderTabs(){
  var c=counts(),h='<button class="ov'+(view.tab==="overview"?" on":"")+'" data-t="overview">Overview</button>';
  h+='<button data-t="new" class="'+(view.tab==="new"?"on":"")+'">NEW <span class="n">'+c.new+'</span></button>';
  ["buyer","seller","sphere"].forEach(function(k){h+='<button data-t="'+k+'" class="'+(view.tab===k?"on":"")+'">'+CATS[k].label+' <span class="n">'+c[k]+'</span></button>';});
  h+='<button data-t="tags" class="'+(view.tab==="tags"?"on":"")+'">By Source</button>';
  h+='<button class="srcbtn" id="mysrc" type="button">&#9881; My Lead Sources</button>';
  tabs.innerHTML=h;
}
tabs.addEventListener("click",function(e){var b=e.target.closest("button");if(!b)return;if(b.id==="mysrc"){openSources();return;}view.tab=b.dataset.t;view.sub=null;view.q="";render();});
function hotList(){
  var items=[];LEADS.forEach(function(L){L.view.forEach(function(v){if(v.due&&v.over>=0&&v.sub!=="dnc")items.push([L,v]);});});
  items.sort(function(a,b){return (HEAT[a[1].sub]-HEAT[b[1].sub])||(b[1].over-a[1].over);});return items;
}
function soonList(){var items=[];LEADS.forEach(function(L){L.view.forEach(function(v){if(v.due&&v.over<0&&v.over>=-7&&v.sub!=="dnc")items.push([L,v]);});});items.sort(function(a,b){return b[1].over-a[1].over;});return items;}
function renderOverview(){
  var c=counts(),hot=hotList(),soon=soonList(),wk=0,newWk=0,readyB=0,readyS=0,sa=0,cold=0,unr=0;
  var wkAgo=new Date(TODAY+"T12:00:00");wkAgo.setDate(wkAgo.getDate()-7);var wkS=wkAgo.toISOString().slice(0,10);
  LEADS.forEach(function(L){(L.log||[]).forEach(function(g){if(g.d>=wkS)wk++;});if((L.added||"")>=wkS)newWk++;
    if(L.roles&&L.roles.buyer&&L.roles.buyer.sub==="ready")readyB++;if(L.roles&&L.roles.seller&&L.roles.seller.sub==="ready")readyS++;if(L.roles&&L.roles.sphere&&L.roles.sphere.sub==="a")sa++;
    L.view.forEach(function(v){if(v.sub==="unid")unr++;if(v.sub==="dnc"||v.sub==="d")cold++;});});
  var h='<div class="stats">'
   +'<div class="stat hot"><b>'+hot.length+'</b><span>Touches due now</span></div>'
   +'<div class="stat hot"><b>'+c.new+'</b><span>NEW leads to sort</span></div>'
   +'<div class="stat hot"><b>'+unr+'</b><span>Not reached yet (Undetermined)</span></div>'
   +'<div class="stat hot"><b>'+readyB+'</b><span>Ready buyers</span></div>'
   +'<div class="stat hot"><b>'+readyS+'</b><span>Ready sellers</span></div>'
   +'<div class="stat"><b>'+sa+'</b><span>Sphere A Yes</span></div>'
   +'<div class="stat" style="border-left:5px solid #036f72"><b>'+LEADS.filter(function(L){return L.roles&&((L.roles.buyer&&L.roles.buyer.sub==="client")||(L.roles.seller&&L.roles.seller.sub==="client"));}).length+'</b><span>Clients in My Transactions</span></div>'
   +'<div class="stat"><b>'+wk+'</b><span>Touches done, last 7 days</span></div>'
   +'<div class="stat"><b>'+newWk+'</b><span>Leads added, last 7 days</span></div>'
   +'<div class="stat cold"><b>'+cold+'</b><span>Cold (D Maybe Not, Do Not Contact)</span></div></div>';
  h+='<div class="lpanel"><h2>Hot leads: do these today</h2>'+(hot.length?hot.map(function(x){return row(x[0],x[1]);}).join(""):'<p class="empty">Nothing due. Nice work.</p>')+'</div>';
  h+='<div class="lpanel" style="margin-top:14px"><h2>Coming up in the next 7 days</h2>'+(soon.length?soon.map(function(x){return row(x[0],x[1]);}).join(""):'<p class="empty">Nothing scheduled this week.</p>')+'</div>';
  h+='<div class="lpanel" style="margin-top:14px"><h2>Your lead link</h2><p style="color:#5a6a6c;margin:-4px 0 10px">Share this link anywhere people find you. Every person who fills it in lands in your NEW tab and on your home page.</p>'
    +[["Your contact form",""],["Your FLR agent app","Agent App"],["Facebook","Facebook"],["Instagram","Instagram"],["Landing page","Landing page"],["Email signature","Email signature"]].map(function(x){var u=INTAKE+(x[1]?"&src="+encodeURIComponent(x[1]):"");
      return '<div class="lrow" style="cursor:default"><div class="who"><div class="nm">'+esc(x[0])+'</div><div class="meta">'+esc(u)+'</div></div><button class="btn sm ghost cp" data-u="'+esc(u)+'">Copy</button><a class="btn sm ghost" href="'+esc(u)+'" target="_blank" rel="noopener">Open</a></div>';}).join("")
    +'<details style="margin-top:8px"><summary style="cursor:pointer;font-weight:600">For website builders, landing pages and Zapier</summary><p style="font-size:.88rem;color:#4b5b5d;margin:8px 0">Send a POST (form fields or JSON) to the link above. Fields: <code>first_name</code>, <code>last_name</code> (or <code>name</code>), <code>phone</code>, <code>email</code>, <code>message</code>, <code>interest</code>, <code>source</code>. A phone or email is required. The form on that tool must collect the person\'s consent to be contacted.</p></details>'
    +'<p style="margin:10px 0 0"><button class="btn sm ghost" id="ikreset">Make a new link</button> <span class="goal" style="font-size:.82rem;color:#5a6a6c">Use this if your link is getting spam. The old link stops working.</span></p></div>';
  body.innerHTML=h;
  body.querySelectorAll(".cp").forEach(function(b){b.onclick=function(){var u=b.dataset.u;(navigator.clipboard?navigator.clipboard.writeText(u):Promise.reject()).then(function(){b.textContent="Copied";},function(){prompt("Copy this link:",u);});};});
  var rk=document.getElementById("ikreset");rk.onclick=async function(){if(!rk.dataset.sure){rk.dataset.sure="1";rk.textContent="Tap again: the old link stops working";return;}rk.disabled=true;try{var j=await api({action:"intake_reset"});INTAKE=j.intake;render();}catch(e){rk.textContent=e.message;}};
}
function renderCat(){
  var role=view.tab,subs=role==="new"?{}:CATS[role].subs,keys=Object.keys(subs);
  if(view.sub===null)view.sub=role==="new"?"all":keys[0];
  var side=role==="new"?"":'<button class="sb h0" id="addHere">\u2795 NEW <span class="n">add</span></button>';
  if(role==="new"){side+='<button class="sb h1 on">'+ico("new")+'All NEW <span class="n">'+counts().new+'</span></button>';}
  keys.forEach(function(k,i){var n=LEADS.filter(function(L){return L.roles&&L.roles[role]&&L.roles[role].sub===k;}).length;
    side+='<button class="sb h'+(i+1)+(view.sub===k?" on":"")+'" data-s="'+k+'"><span>'+ico(k)+esc(subs[k])+'</span> <span class="n">'+n+'</span></button>';});
  if(role==="buyer"||role==="seller"){var nc=LEADS.filter(function(L){return L.roles&&L.roles[role]&&L.roles[role].sub==="client";}).length;
    side+='<button class="sb h6'+(view.sub==="client"?" on":"")+'" data-s="client" style="margin-top:10px">'+ico("client")+'In My Transactions <span class="n">'+nc+'</span></button>';}
  var list=LEADS.filter(function(L){return role==="new"?inRole(L,"new"):(L.roles&&L.roles[role]&&L.roles[role].sub===view.sub);});
  if(view.q){var q=view.q.toLowerCase();list=list.filter(function(L){return (nm(L)+" "+(L.phone||"")+" "+(L.email||"")+" "+(L.source||"")).toLowerCase().indexOf(q)>-1;});}
  list.sort(function(a,b){var va=vfor(a,role),vb=vfor(b,role);return ((vb&&vb.over!=null?vb.over:-999)-(va&&va.over!=null?va.over:-999));});
  var title=role==="new"?"NEW leads: call, then sort into Buyers, Sellers or Sphere":CATS[role].label+" · "+(view.sub==="client"?"In My Transactions":subs[view.sub]);
  if(view.sub==="client")CATS[role].stage.client=role==="buyer"?"Signed or signing a buyer broker agreement. The record lives in My Transactions until the sale closes.":"Signed or signing a listing agreement. The record lives in My Transactions until the sale closes.";
  var main='<div class="lpanel"><h2>'+esc(title)+'</h2>'+(role!=="new"&&CATS[role].stage[view.sub]?'<p style="margin:-6px 0 12px;color:#5a6a6c;font-size:.9rem">'+esc(CATS[role].stage[view.sub])+'</p>':(role==="new"?'<p style="margin:-6px 0 12px;color:#5a6a6c;font-size:.9rem">Leads from your website, landing pages and social media. Reach them, learn what they need, then move them to Buyers, Sellers or Sphere.</p>':''))+'<input class="lsearch" id="lq" placeholder="Search name, phone, email" value="'+esc(view.q)+'">'
    +(list.length?list.map(function(L){return row(L,vfor(L,role));}).join(""):'<p class="empty">No leads here yet.</p>')+'</div>';
  body.innerHTML='<div class="lwrap"><div class="lside">'+side+'</div><div>'+main+'</div></div>';
  body.querySelectorAll(".sb[data-s]").forEach(function(b){b.onclick=function(){view.sub=b.dataset.s;render();};});
  var ah=document.getElementById("addHere");if(ah)ah.onclick=function(){var r={};r[role]=view.sub;openLead(null,r);};
  var lq=document.getElementById("lq");lq.oninput=function(){view.q=lq.value;var p=lq.selectionStart;renderCat();var n=document.getElementById("lq");n.focus();n.setSelectionRange(p,p);};
}
function render(){renderTabs();if(view.tab==="overview")renderOverview();else if(view.tab==="tags")renderTags();else renderCat();}
function isDnc(L){return L.view.some(function(v){return v.sub==="dnc";});}
function tagCounts(){var c={};LEADS.forEach(function(L){(L.tags||[]).forEach(function(t){c[t]=(c[t]||0)+1;});});return c;}
function renderTags(){
  var c=tagCounts(),keys=Object.keys(c).sort(function(a,b){return c[b]-c[a]||a.localeCompare(b);}),untagged=LEADS.filter(function(L){return !(L.tags||[]).length;}).length;
  if(view.tag==null)view.tag=keys[0]||"";
  var h='<div class="lpanel"><h2>Leads by source</h2><p style="color:#5a6a6c;margin:-4px 0 10px">Pick a source, interest or referrer to see, email or call everyone in it.</p><div class="chips">'
    +keys.map(function(k){return '<button class="chip'+(view.tag===k?" on":"")+'" data-tag="'+esc(k)+'">'+esc(k)+'<span class="n">'+c[k]+'</span></button>';}).join("")
    +(untagged?'<button class="chip'+(view.tag==="__none"?" on":"")+'" data-tag="__none">No source yet<span class="n">'+untagged+'</span></button>':'')+'</div>';
  var ic={};LEADS.forEach(function(L){(L.interests||[]).forEach(function(t){ic[t]=(ic[t]||0)+1;});});var ik=Object.keys(ic).sort(function(a,b){return ic[b]-ic[a]||a.localeCompare(b);});
  if(ik.length)h+='<h3 style="margin:12px 0 4px;font-size:1rem">Interests and connections</h3><div class="chips">'+ik.map(function(k){return '<button class="chip'+(view.tag==="i:"+k?" on":"")+'" data-tag="i:'+esc(k)+'">'+esc(k)+'<span class="n">'+ic[k]+'</span></button>';}).join("")+'</div>';
  var rc={};LEADS.forEach(function(L){if(L.ref)rc[L.ref]=(rc[L.ref]||0)+1;});var rk=Object.keys(rc).sort(function(a,b){return rc[b]-rc[a];});
  if(rk.length)h+='<h3 style="margin:12px 0 4px;font-size:1rem">Top referrers</h3><div class="chips">'+rk.slice(0,12).map(function(id){var p=LEADS.filter(function(x){return x.id===id;})[0];return p?'<button class="chip'+(view.tag==="r:"+id?" on":"")+'" data-tag="r:'+esc(id)+'">'+esc(nm(p))+'<span class="n">'+rc[id]+'</span></button>':"";}).join("")+'</div>';
  if(!keys.length&&!untagged){h+='<p class="empty">No leads yet.</p></div>';body.innerHTML=h;return;}
  var list=LEADS.filter(function(L){if(view.tag.indexOf("i:")===0)return (L.interests||[]).indexOf(view.tag.slice(2))>-1;if(view.tag.indexOf("r:")===0)return L.ref===view.tag.slice(2);return view.tag==="__none"?!(L.tags||[]).length:(L.tags||[]).indexOf(view.tag)>-1;});
  var ok=list.filter(function(L){return !isDnc(L);}),dn=list.length-ok.length,em=ok.map(function(L){return L.email;}).filter(Boolean),ph=ok.map(function(L){return L.phone;}).filter(Boolean);
  h+='<div class="bulk"><strong>'+list.length+' lead'+(list.length===1?"":"s")+'</strong>'+(dn?'<span style="font-size:.85rem;color:#1f5fa8">('+dn+' Do Not Contact left out)</span>':'')
    +(em.length?'<button class="btn sm" id="b_mail">Email all ('+em.length+')</button><button class="btn sm ghost" id="b_cem">Copy emails</button>':'')
    +(ph.length?'<button class="btn sm ghost" id="b_cph">Copy phone numbers</button>':'')+'<button class="btn sm ghost" id="b_csv">Download list</button></div>';
  h+=list.map(function(L){var v=L.view.filter(function(x){return x.role!=="new";})[0]||L.view[0];return row(L,v);}).join("")+'</div>';
  body.innerHTML=h;
  body.querySelectorAll(".chip[data-tag]").forEach(function(b){b.onclick=function(){view.tag=b.dataset.tag;render();};});
  function cp(t,b){(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(function(){b.textContent="Copied";},function(){prompt("Copy:",t);});}
  var bm=document.getElementById("b_mail");if(bm)bm.onclick=function(){if(em.length>45){cp(em.join(", "),bm);bm.textContent="Too many for one email: addresses copied, paste into BCC";return;}location.href="mailto:?bcc="+encodeURIComponent(em.join(","));};
  var ce=document.getElementById("b_cem");if(ce)ce.onclick=function(){cp(em.join(", "),ce);};
  var cph=document.getElementById("b_cph");if(cph)cph.onclick=function(){cp(ph.join("\n"),cph);};
  document.getElementById("b_csv").onclick=function(){var q=function(x){return '"'+String(x==null?"":x).replace(/"/g,'""')+'"';};
    var rows=[["First","Last","Phone","Email","Source","Tags","Interests","Referred by","Categories","Added","Do Not Contact"]].concat(list.map(function(L){return [L.first,L.last,L.phone,L.email,L.source,(L.tags||[]).join("; "),(L.interests||[]).join("; "),(function(){var p=L.ref?LEADS.filter(function(x){return x.id===L.ref;})[0]:null;return p?nm(p):"";})(),L.view.map(function(v){return v.role==="new"?"NEW":CATS[v.role].label+" "+subLabel(v.role,v.sub);}).join("; "),L.added,isDnc(L)?"Yes":""];}));
    var blob=new Blob([rows.map(function(r){return r.map(q).join(",");}).join("\r\n")],{type:"text/csv"}),a=document.createElement("a");a.href=URL.createObjectURL(blob);a.download="leads-"+(view.tag==="__none"?"no-source":view.tag.replace(/^[ir]:/,"")).replace(/[^A-Za-z0-9]+/g,"-")+".csv";document.body.appendChild(a);a.click();a.remove();};
}
function openSources(){
  var all=ALLSRC.slice();MYSRC.forEach(function(t){if(all.indexOf(t)<0)all.push(t);});
  var alli=ALLINT.slice();MYINT.forEach(function(t){if(alli.indexOf(t)<0)alli.push(t);});
  var sel=MYSRC.slice(),seli=MYINT.slice();
  function chips(list,on,grp){return '<div class="chips">'+list.map(function(t){return '<button type="button" class="chip'+(on.indexOf(t)>-1?" on":"")+'" data-g="'+grp+'" data-s="'+esc(t)+'">'+esc(t)+'</button>';}).join("")+'</div>';}
  function addRow(id,ph){return '<div style="display:flex;gap:8px;margin:8px 0 16px"><input id="'+id+'" placeholder="'+ph+'" style="flex:1;padding:9px 11px;border:1px solid var(--line);border-radius:9px;font:inherit"><button class="btn sm ghost" data-add="'+id+'" type="button">Add</button></div>';}
  function draw(){modal.innerHTML='<h2>My Lead Sources</h2><p style="color:#5a6a6c">Tap every way you get leads. These become tags on each lead, and the <strong>By Source</strong> tab shows, emails or calls everyone from one source.</p>'
    +chips(all,sel,"s")+addRow("src_new","Add your own, separated by commas (e.g. Builder partner)")
    +'<h3 style="margin:6px 0 4px">Interests and connections</h3><p style="color:#5a6a6c;margin:0 0 6px">Mostly for your Sphere: how you know people and what you share. Tag contacts with them to remember the connection and to reach everyone who golfs, plays pickleball and so on.</p>'
    +chips(alli,seli,"i")+addRow("int_new","Add your own, separated by commas (e.g. Rotary Club, Kayaking)")
    +'<div class="btns"><button class="btn" id="src_save">Save</button><button class="btn ghost" id="m_close">Close</button></div><p class="note" id="m_msg" hidden></p>';
    modal.querySelectorAll(".chip[data-g]").forEach(function(b){b.onclick=function(){var arr=b.dataset.g==="s"?sel:seli,t=b.dataset.s,i=arr.indexOf(t);if(i>-1)arr.splice(i,1);else arr.push(t);b.classList.toggle("on");};});
    modal.querySelectorAll("[data-add]").forEach(function(b){b.onclick=function(){var isS=b.dataset.add==="src_new",inp=document.getElementById(b.dataset.add);
      inp.value.split(",").map(function(x){return x.trim().slice(0,40);}).filter(Boolean).forEach(function(t){var L1=isS?all:alli,S1=isS?sel:seli;if(L1.indexOf(t)<0)L1.push(t);if(S1.indexOf(t)<0)S1.push(t);});draw();};});
    document.getElementById("m_close").onclick=close;
    document.getElementById("src_save").onclick=async function(){var b=this;b.disabled=true;try{var j=await api({action:"sources",sources:sel,interests:seli});MYSRC=j.sources;MYINT=j.interests;close();render();}catch(e){var m=document.getElementById("m_msg");m.textContent=e.message;m.hidden=false;b.disabled=false;}};
  }
  draw();mback.classList.add("show");
}

body.addEventListener("click",function(e){var r=e.target.closest(".lrow");if(!r)return;var L=LEADS.filter(function(x){return x.id===r.dataset.id;})[0];if(L)openLead(L);});
async function api(payload){payload.csrf=CSRF;var r=await fetch("leads_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)});var j=await r.json();if(!j.ok)throw new Error(j.msg||"Could not save.");LEADS=j.leads;return j;}
function mv(role,cur,withNone){if(cur==="client")return '<div class="mvrow" data-role="'+role+'" data-val="client"><b>'+esc(CATS[role].label)+'</b><span class="due" style="background:#e1f3f2;color:#036f72">In My Transactions</span></div>';var h='<div class="mvrow" data-role="'+role+'" data-val="'+esc(cur)+'"><b>'+esc(CATS[role].label)+'</b>'+(MV_ACT&&role==="buyer"?'<button type="button" class="mvb h6" style="opacity:1" data-act="execute" title="Buyer consultation: start the buyer record in My Transactions and get the buyer broker agreement signed">\uD83D\uDE0A Buyer Consultation</button>':'')+(MV_ACT&&role==="seller"?'<button type="button" class="mvb h6" style="opacity:1" data-act="prelist" title="Listing appointment: start the Pre Listing record in My Transactions and prepare your listing documents">\uD83D\uDE0A Listing Appointment</button>':'')+(withNone?'<button type="button" class="mvb none'+(cur===""?" on":"")+'" data-v="">'+(role==="sphere"?"Not in Sphere":"None")+'</button>':'');
  Object.keys(CATS[role].subs).forEach(function(k,i){h+='<button type="button" class="mvb h'+(i+1)+(cur===k?" on":"")+'" data-v="'+k+'" title="'+esc(CATS[role].stage[k]||"")+'">'+ico(k)+esc(CATS[role].subs[k])+'</button>';});return h+'</div>';}
// Move lead: only the stages of the lead's own main category, with small buttons for the occasional cross-over.
// Chosen tags show as pills beside a Choose button; the button opens the agent's own options from My Lead Sources.
function pickBlock(kind,sel,all,label){
  if(!all.length)return '<p class="goal">'+(kind==="tag"?'Set up':'Choose your interests and connections in')+' <a href="#" class="opensrc">My Lead Sources</a>'+(kind==="tag"?' to tag leads by how you got them.':' to tag people with them.')+'</p>';
  var box=kind==="tag"?"tagbox":"intbox",attr=kind==="tag"?"data-tag":"data-int";
  return '<div class="chips"><span class="picksum" data-for="'+box+'">'+pills(sel)+'</span><button type="button" class="chip pickbtn" data-box="'+box+'" style="border-style:dashed">'+(sel.length?"Change":"+ "+label)+'</button></div>'
    +'<div class="pickwrap" data-box="'+box+'" hidden style="border:1px solid var(--line);border-radius:12px;padding:10px;margin:4px 0 8px"><div class="chips" id="'+box+'">'+all.map(function(x){return '<button type="button" class="chip'+(sel.indexOf(x)>-1?" on":"")+'" '+attr+'="'+esc(x)+'">'+esc(x)+'</button>';}).join("")+'</div>'
    +'<button type="button" class="btn sm" data-done="'+box+'">Done</button> <a href="#" class="opensrc" style="font-size:.82rem">Edit my choices</a></div>';
}
function pills(sel){return sel.length?sel.map(function(x){return '<span class="chip on" style="cursor:default">'+esc(x)+'</span>';}).join(""):'<span class="goal" style="align-self:center">None chosen</span>';}
function tagChips(L,isNew){var t=(L.tags||[]).slice(),all=MYSRC.slice();t.forEach(function(x){if(all.indexOf(x)<0)all.push(x);});return pickBlock("tag",t,all,"Choose lead source");}
function intChips(L){var t=(L.interests||[]).slice(),all=MYINT.slice();t.forEach(function(x){if(all.indexOf(x)<0)all.push(x);});return pickBlock("int",t,all,"Choose interests");}

function sphereList(L){return LEADS.filter(function(x){return x.id!==L.id&&x.roles&&x.roles.sphere;}).sort(function(a,b){return nm(a).localeCompare(nm(b));});}
function refLabel(x){return nm(x)+(x.phone?" · "+x.phone:"");}
function refPicker(L){var cur=L.ref?LEADS.filter(function(x){return x.id===L.ref;})[0]:null;
  return '<input id="f_ref" list="refopts" placeholder="Start typing a Sphere contact\'s name" value="'+esc(cur?refLabel(cur):"")+'" autocomplete="off"><datalist id="refopts">'+sphereList(L).map(function(x){return '<option value="'+esc(refLabel(x))+'">';}).join("")+'</datalist>';}
function readRef(L){var v=(document.getElementById("f_ref")||{value:""}).value.trim();if(!v)return "";var m=sphereList(L).filter(function(x){return refLabel(x)===v||nm(x)===v;})[0];return m?m.id:null;}
function readInts(){var o=[];modal.querySelectorAll("#intbox .chip.on").forEach(function(b){o.push(b.dataset.int);});return o;}
function readTags(){var o=[];modal.querySelectorAll("#tagbox .chip.on").forEach(function(b){o.push(b.dataset.tag);});return o;}
var MV_ACT=false;
function mvBox(L,isNew,rs){MV_ACT=!isNew;
  var cur=["buyer","seller","sphere"].filter(function(k){return rs[k];});
  if(!cur.length){return '<h3>Category</h3>'+mv("buyer","",true)+mv("seller","",true)+mv("sphere","",true)+'<div style="font-size:.82rem;color:#5a6a6c">'+(isNew?'Choose at least one. A lead can be both a buyer and a seller.':'Choose where this lead belongs. It leaves NEW right away.')+'</div>';}
  var h='<h3>'+(isNew?'Category':'Move lead')+'</h3>'+cur.map(function(k){return mv(k,rs[k],false);}).join("");
  var extra=[],has=function(k){return cur.indexOf(k)>-1;};
  if(!has("seller")&&(has("buyer")||has("sphere")))extra.push(["seller","add",has("buyer")?"Add As Seller Also":"Add As Seller"]);
  if(!has("buyer")&&(has("seller")||has("sphere")))extra.push(["buyer","add",has("seller")?"Add As Buyer Also":"Add As Buyer"]);
  if(!has("sphere")&&(has("buyer")||has("seller")))extra.push(["sphere","move","Move To Sphere"]);
  h+=["buyer","seller","sphere"].filter(function(k){return !has(k);}).map(function(k){return '<div class="mvh" data-role="'+k+'" hidden>'+mv(k,"",false)+'</div>';}).join("");
  if(extra.length){h+='<div class="btns" style="margin-top:4px">'+extra.map(function(x){return '<button type="button" class="btn sm ghost mvx" data-role="'+x[0]+'" data-mode="'+x[1]+'">'+esc(x[2])+'</button>';}).join("")+'</div>';}
  h+='<div class="mvpre" hidden style="margin:8px 0;padding:10px;border:1px dashed var(--line);border-radius:10px"><label style="font-size:.82rem;font-weight:600">Property address for the listing appointment</label><input id="pre_addr" placeholder="e.g. 123 Palm Ave, Trinity FL" style="width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:9px;font:inherit;box-sizing:border-box;margin:4px 0 8px"><button type="button" class="btn sm" id="pre_go">Set up listing appointment</button> <span class="goal">The record goes in My Transactions › SELLERS › Pre Listing with the seller\'s details. Prepare your listing documents there for the appointment.</span></div>';
  h+='<div class="mvtip" style="font-size:.82rem;color:#5a6a6c;margin-top:6px">'+(isNew?'':'Tap a stage to move the lead right away. Moving restarts that plan.')+'</div>';
  return h;
}
function openLead(L,preset){
  var isNew=!L;L=L||{first:"",last:"",phone:"",email:"",source:"",notes:"",roles:{},view:[],log:[]};
  var rs={};["buyer","seller","sphere"].forEach(function(k){rs[k]=(L.roles&&L.roles[k]&&L.roles[k].sub)||(preset&&preset[k])||"";});
  var h='<h2>'+(isNew?"Add a lead":esc(nm(L)))+'</h2><div class="fgrid">'
   +'<div><label>First name</label><input id="f_first" value="'+esc(L.first)+'"></div><div><label>Last name</label><input id="f_last" value="'+esc(L.last)+'"></div>'
   +'<div><label>Phone</label><input id="f_phone" type="tel" value="'+esc(L.phone)+'"></div><div><label>Email</label><input id="f_email" type="email" value="'+esc(L.email)+'"></div>'
   +'<div><label>Source</label><input id="f_source" list="srcs" value="'+esc(L.source)+'"><datalist id="srcs">'+SOURCES.map(function(s){return '<option value="'+esc(s)+'">';}).join("")+'</datalist></div>'
   +'<div><label>Added</label><input value="'+esc(L.added?fmtD(L.added):"Today")+'" disabled></div>'
   +'<div class="full mvbox" id="mvbox">'+mvBox(L,isNew,rs)+'</div>'
   +'<div class="full"><label>How you got this lead</label>'+tagChips(L,isNew)+'</div>'
   +'<div class="full"><label>Referred by</label>'+refPicker(L)+'</div>'
   +'<div class="full"><label>Interests and connections</label>'+intChips(L)+'</div>'
   +'<div class="full"><label>Notes</label><textarea id="f_notes" rows="3">'+esc(L.notes)+'</textarea></div></div>'
   +'<div class="btns"><button class="btn" id="m_save">'+(isNew?"Add lead":"Save changes")+'</button><button class="btn ghost" id="m_close">Close</button>'+(isNew?"":'<button class="btn ghost" id="m_del" style="margin-left:auto;color:#b71c1c;border-color:#e3b4b4">Delete</button>')+'</div><p class="note" id="m_msg" hidden></p>';
  if(!isNew){
    L.view.forEach(function(v){
      var head=ico(v.sub)+esc(v.role==="new"?"NEW":CATS[v.role].label+" · "+subLabel(v.role,v.sub));
      h+='<div class="touch"><div class="hd"><span><span class="dot '+heatClass(v.sub)+'" style="display:inline-block;vertical-align:middle;margin-right:6px"></span>'+head+'</span>'+dueBadge(v)+'</div>';
      if(v.sub==="client"){
        var isB=v.role==="buyer",open=v.folder?(isB?'buyer.php?tx='+encodeURIComponent(v.folder):'https://drive.google.com/drive/folders/'+encodeURIComponent(v.folder)):"";
        h+='<p class="goal">'+(isB?'Buyer':'Listing')+' record: <strong>'+esc(v.record||"")+'</strong> · Stage: <strong>'+esc(v.stagename)+'</strong>. '+(v.stage===3?'Closing check-ins run every 5 days until closing.':'Nurture messages pause while you work with this client directly.')+'</p><div class="btns">';
        if(open)h+='<a class="btn sm" href="'+esc(open)+'"'+(isB?'':' target="_blank" rel="noopener"')+'>Open '+(isB?'buyer':'listing')+' record</a>';
        if(isB&&v.folder&&v.stage===1)h+='<a class="btn sm ghost" href="wizard.php?tx='+encodeURIComponent(v.folder)+'">Make a contract offer</a><button class="btn sm ghost t_mv" data-role="buyer" data-st="3">Contract executed: move to Pending</button>';
        if(!isB&&v.stage===1)h+='<button class="btn sm ghost t_mv" data-role="seller" data-st="2">Listing signed: move to Active Listings</button>';
        if(!isB&&v.stage===2)h+='<button class="btn sm ghost t_mv" data-role="seller" data-st="3">Under contract: move to Pending</button>';
        if(v.stage===3)h+='<button class="btn sm ghost t_close" data-role="'+v.role+'">Closed sale: archive and move to Sphere</button>';
        h+='</div><div class="t_sph" hidden><p class="goal">Which Sphere category?</p><div class="btns">'
          +Object.keys(CATS.sphere.subs).filter(function(k){return k!=="dnc";}).map(function(k,i){return '<button class="mvb h'+(i+1)+' t_sp" style="opacity:1" data-sp="'+k+'">'+esc(CATS.sphere.subs[k])+'</button>';}).join("")+'</div></div>';}
      if(v.sub==="client"&&!v.text){}
      else if(v.sub==="dnc"){h+='<p class="goal">This person asked not to be contacted. No messages are scheduled.</p>';}
      else if(v.done){h+='<p class="goal">This plan is finished. Sort the lead into Buyers, Sellers or Sphere so it keeps getting follow-up.</p>';}
      else if(v.text){
        h+='<div class="goal">'+(v.step>v.of?'Touch '+v.step+' (ongoing)':'Step '+v.step+' of '+v.of)+' · '+esc(v.ch)+'</div>';
        if(/Email/.test(v.ch))h+='<input class="t_subj" value="'+esc(v.subj)+'" placeholder="Subject">';
        h+='<textarea class="t_text" rows="4" style="margin-top:6px">'+esc(v.text)+'</textarea><div class="goal">'+esc(v.goal)+'</div><div class="btns">';
        if(L.phone&&/Call/.test(v.ch))h+='<a class="btn sm" href="tel:'+esc(digits(L.phone))+'">Call</a>';
        if(L.phone&&/Text|Call/.test(v.ch))h+='<a class="btn sm t_sms" href="#">Text</a>';
        if(L.email&&/Email/.test(v.ch))h+='<a class="btn sm t_mail" href="#">Email</a>';
        h+='<button class="btn sm ghost t_copy">Copy message</button><button class="btn sm t_done" data-role="'+v.role+'" data-mid="'+esc(v.mid)+'">Mark done</button>'+(v.touches?'<button class="btn sm ghost t_undo" data-role="'+v.role+'">Undo last</button>':"")+'</div>';
      }
      h+='</div>';
    });
    var lg=(L.log||[]).slice().reverse();
    h+='<div class="hist"><strong>History</strong><br>'+(lg.length?lg.map(function(g){return esc(fmtD(g.d))+" · "+esc(g.role==="new"?"NEW":CATS[g.role]?CATS[g.role].label:g.role)+(g.mid?" · "+esc(g.mid):"")+(g.note?" · "+esc(g.note):"");}).join("<br>"):"No touches logged yet.")+'</div>';
  }
  modal.innerHTML=h;mback.classList.add("show");
  var fg=modal.querySelector(".fgrid");modal.querySelectorAll(".touch").forEach(function(t){modal.insertBefore(t,fg);}); // the next touch comes first; details below
  if(!isNew){modal.insertBefore(document.getElementById("mvbox"),fg);}
  var pg=document.getElementById("pre_go");if(pg)pg.onclick=async function(){pg.disabled=true;pg.innerHTML='<span class="bspin"></span>Creating...';
    try{await api({action:"startseller",id:L.id,address:document.getElementById("pre_addr").value.trim()});reopen(L.id);render();}catch(e){alertIn(e.message);pg.disabled=false;pg.textContent="Set up listing appointment";}};
  function alertIn(t){var pp=modal.querySelector(".mvpre");var n=pp.querySelector(".sigerr");if(!n){n=document.createElement("p");n.className="sigerr";pp.appendChild(n);}n.textContent=t;}
  function quickSave(){return api({action:"save",id:L.id,lead:{first:val("f_first"),last:val("f_last"),phone:val("f_phone"),email:val("f_email"),source:val("f_source"),notes:val("f_notes")},roles:readRoles(),tags:modal.querySelector("#tagbox")?readTags():undefined,interests:readInts()});}
  modal.querySelectorAll("#intbox .chip").forEach(function(b){b.onclick=async function(){b.classList.toggle("on");if(isNew)return;try{await quickSave();render();}catch(e){b.classList.toggle("on");say(e.message,true);}};});
  var ia=document.getElementById("f_intadd");if(ia)ia.onclick=async function(){var inp=document.getElementById("f_intnew"),box=document.getElementById("intbox");
    inp.value.split(",").map(function(x){return x.trim().slice(0,40);}).filter(Boolean).forEach(function(t){var ex=[].filter.call(box.querySelectorAll(".chip"),function(c){return c.dataset.int===t;})[0];
      if(ex){ex.classList.add("on");}else{var c=document.createElement("button");c.type="button";c.className="chip on";c.dataset.int=t;c.textContent=t;c.onclick=function(){c.classList.toggle("on");if(!isNew)quickSave().then(render);};box.appendChild(c);}});
    inp.value="";if(!isNew){try{await quickSave();render();}catch(e){say(e.message,true);}}};
  var fr=document.getElementById("f_ref");if(fr&&!isNew)fr.onchange=async function(){var r=readRef(L);if(r===null){say("Pick a name from the list (Sphere contacts only).",true);return;}
    try{await api({action:"save",id:L.id,lead:{first:val("f_first"),last:val("f_last"),phone:val("f_phone"),email:val("f_email"),source:val("f_source"),notes:val("f_notes")},roles:readRoles(),ref:r});reopen(L.id);render();}catch(e){say(e.message,true);}};
  if(!isNew&&L.roles&&L.roles.sphere){var refd=LEADS.filter(function(x){return x.ref===L.id;});
    var rb=document.createElement("div");rb.className="touch";rb.innerHTML='<div class="hd"><span>Referrals from '+esc(nm(L))+'</span><span class="due later">'+refd.length+'</span></div>'+(refd.length?refd.map(function(x){return '<div><a href="#" data-open="'+esc(x.id)+'">'+esc(nm(x))+'</a> <span class="goal">'+esc(x.view.map(function(v){return v.role==="new"?"NEW":CATS[v.role].label+" · "+subLabel(v.role,v.sub);}).join(", "))+'</span></div>';}).join(""):'<p class="goal">No referrals yet. When a lead names this person in Referred by, it shows here.</p>');
    modal.insertBefore(rb,document.getElementById("mvbox"));rb.querySelectorAll("[data-open]").forEach(function(a){a.onclick=function(e){e.preventDefault();reopen(a.dataset.open);};});}
  function refreshPills(box){var sum=modal.querySelector('.picksum[data-for="'+box+'"]'),sel=[];modal.querySelectorAll("#"+box+" .chip.on").forEach(function(c){sel.push(c.dataset.tag||c.dataset.int);});
    if(sum)sum.innerHTML=pills(sel);var pb=modal.querySelector('.pickbtn[data-box="'+box+'"]');if(pb)pb.textContent=sel.length?"Change":(box==="tagbox"?"+ Choose lead source":"+ Choose interests");}
  modal.querySelectorAll(".pickbtn").forEach(function(b){b.onclick=function(){var w=modal.querySelector('.pickwrap[data-box="'+b.dataset.box+'"]');w.hidden=!w.hidden;};});
  modal.querySelectorAll("[data-done]").forEach(function(b){b.onclick=function(){modal.querySelector('.pickwrap[data-box="'+b.dataset.done+'"]').hidden=true;};});
  modal.querySelectorAll("#tagbox .chip, #intbox .chip").forEach(function(c){c.addEventListener("click",function(){setTimeout(function(){refreshPills(c.parentNode.id);},0);});});
  modal.querySelectorAll(".opensrc").forEach(function(os){os.onclick=function(e){e.preventDefault();openSources();};});
  modal.querySelectorAll("#tagbox .chip").forEach(function(b){b.onclick=async function(){b.classList.toggle("on");if(isNew)return;
    try{await api({action:"save",id:L.id,lead:{first:val("f_first"),last:val("f_last"),phone:val("f_phone"),email:val("f_email"),source:val("f_source"),notes:val("f_notes")},roles:readRoles(),tags:readTags()});render();}catch(e){b.classList.toggle("on");say(e.message,true);}};});
  var sphereMove=false;
  modal.querySelectorAll(".mvx").forEach(function(b){b.onclick=function(){var w=modal.querySelector('.mvh[data-role="'+b.dataset.role+'"]');if(w)w.hidden=false;
    if(b.dataset.mode==="move"){sphereMove=true;modal.querySelector(".mvtip").textContent="Pick the Sphere category. The lead leaves Buyers and Sellers and starts the Sphere plan.";}
    else{modal.querySelector(".mvtip").textContent="Pick the "+(b.dataset.role==="seller"?"seller":"buyer")+" stage. Both plans then run side by side.";}
    b.remove();};});
  modal.querySelectorAll(".mvrow").forEach(function(r){r.addEventListener("click",async function(e){var b=e.target.closest(".mvb");if(!b||b.classList.contains("on"))return;
    if(b.dataset.act==="execute"){location.href="buyer.php?new=1&lead="+encodeURIComponent(L.id);return;}
    if(b.dataset.act==="prelist"){var pp=modal.querySelector(".mvpre");if(pp){pp.hidden=false;var ia=document.getElementById("pre_addr");ia.focus();}return;}
    var prev=r.dataset.val;r.dataset.val=b.dataset.v;r.querySelectorAll(".mvb").forEach(function(x){x.classList.toggle("on",x===b);});
    if(sphereMove&&r.dataset.role==="sphere"){modal.querySelectorAll('.mvrow[data-role="buyer"],.mvrow[data-role="seller"]').forEach(function(o){if(o.dataset.val!=="client")o.dataset.val="";});}
    if(isNew)return;
    try{await api({action:"save",id:L.id,lead:{first:val("f_first"),last:val("f_last"),phone:val("f_phone"),email:val("f_email"),source:val("f_source"),notes:val("f_notes")},roles:readRoles()});var lbl=CATS[r.dataset.role].label+" · "+(b.dataset.v?CATS[r.dataset.role].subs[b.dataset.v]:"none");reopen(L.id);render();var hh=document.querySelector("#mvbox h3");if(hh)hh.innerHTML="Move lead <span style=\"font-weight:500;color:#2e8b57\">· Saved: "+esc(lbl)+"</span>";}
    catch(err){r.dataset.val=prev;r.querySelectorAll(".mvb").forEach(function(x){x.classList.toggle("on",x.dataset.v===prev);});say(err.message,true);}
  });});
  var msg=document.getElementById("m_msg");function say(t,bad){msg.textContent=t;msg.className=bad?"note sigerr":"note";msg.hidden=false;}
  document.getElementById("m_close").onclick=close;
  document.getElementById("m_save").onclick=async function(){var b=this;b.disabled=true;
    var roles=readRoles(),any=Object.keys(roles).some(function(k){return roles[k];});
    if(isNew&&!any){say("Choose Buyer, Seller or Sphere for this lead.",true);b.disabled=false;return;}
    var tg=modal.querySelector("#tagbox")?readTags():undefined;if(isNew&&tg&&!tg.length&&val("f_source"))tg=undefined;
    try{await api({action:"save",id:isNew?"":L.id,lead:{first:val("f_first"),last:val("f_last"),phone:val("f_phone"),email:val("f_email"),source:val("f_source"),notes:val("f_notes")},roles:roles,tags:tg,interests:readInts(),ref:(function(){var r=readRef(L);return r===null?(L.ref||""):r;})()});close();render();}
    catch(e){say(e.message,true);b.disabled=false;}};
  if(!isNew){document.getElementById("m_del").onclick=async function(){if(!confirmDel(this))return;try{await api({action:"delete",id:L.id});close();render();}catch(e){say(e.message,true);}};}
  modal.querySelectorAll(".touch").forEach(function(t){
    var tx=t.querySelector(".t_text"),sj=t.querySelector(".t_subj");
    var s=t.querySelector(".t_sms");if(s)s.onclick=function(e){e.preventDefault();location.href="sms:"+digits(L.phone)+"?&body="+encodeURIComponent(tx.value);};
    var m=t.querySelector(".t_mail");if(m)m.onclick=function(e){e.preventDefault();location.href="mailto:"+encodeURIComponent(L.email)+"?subject="+encodeURIComponent(sj?sj.value:"")+"&body="+encodeURIComponent(tx.value);};
    var c=t.querySelector(".t_copy");if(c)c.onclick=function(){var v=(sj&&sj.value?sj.value+"\n\n":"")+tx.value;(navigator.clipboard?navigator.clipboard.writeText(v):Promise.reject()).then(function(){c.textContent="Copied";},function(){tx.select();document.execCommand("copy");c.textContent="Copied";});};
    t.querySelectorAll(".t_mv").forEach(function(b){b.onclick=async function(){b.disabled=true;var o=b.textContent;b.innerHTML='<span class="bspin"></span>Moving...';try{var jr=await api({action:"txmove",id:L.id,role:b.dataset.role,stage:+b.dataset.st});reopen(L.id);render();if(jr.warn){var mm=document.getElementById("m_msg");mm.textContent=jr.warn;mm.className="note";mm.hidden=false;var tc=document.querySelector(".touch");if(tc)tc.appendChild(mm);}}catch(e){say(e.message,true);b.disabled=false;b.textContent=o;}};});
    var cl=t.querySelector(".t_close");if(cl)cl.onclick=function(){t.querySelector(".t_sph").hidden=false;cl.disabled=true;};
    t.querySelectorAll(".t_sp").forEach(function(b){b.onclick=async function(){b.disabled=true;try{await api({action:"closeclient",id:L.id,role:(t.querySelector(".t_close")||{dataset:{}}).dataset.role||"",sphere:b.dataset.sp});reopen(L.id);render();}catch(e){say(e.message,true);b.disabled=false;}};});
    var d=t.querySelector(".t_done");if(d)d.onclick=async function(){d.disabled=true;try{await api({action:"touch",id:L.id,role:d.dataset.role,mid:d.dataset.mid});reopen(L.id);render();}catch(e){say(e.message,true);d.disabled=false;}};
    var u=t.querySelector(".t_undo");if(u)u.onclick=async function(){u.disabled=true;try{await api({action:"untouch",id:L.id,role:u.dataset.role});reopen(L.id);render();}catch(e){say(e.message,true);u.disabled=false;}};
  });
}
function confirmDel(b){if(b.dataset.sure)return true;b.dataset.sure="1";b.textContent="Tap again to delete";setTimeout(function(){delete b.dataset.sure;b.textContent="Delete";},4000);return false;}
function reopen(id){var L=LEADS.filter(function(x){return x.id===id;})[0];if(L)openLead(L);}
function readRoles(){var o={};modal.querySelectorAll(".mvrow").forEach(function(r){o[r.dataset.role]=r.dataset.val||"";});return o;}
function val(id){return document.getElementById(id).value.trim();}
function close(){mback.classList.remove("show");modal.innerHTML="";}
mback.addEventListener("click",function(e){if(e.target===mback)close();});
document.addEventListener("keydown",function(e){if(e.key==="Escape")close();});
render();
var op=new URLSearchParams(location.search).get("open");if(op){var oL=LEADS.filter(function(x){return x.id===op;})[0];if(oL){view.tab=["buyer","seller","sphere"].filter(function(k){return oL.roles&&oL.roles[k];})[0]||"new";if(view.tab!=="new")view.sub=oL.roles[view.tab].sub;render();openLead(oL);}}
})();
</script>
</body>
</html>
