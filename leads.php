<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/leadlib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
list($agent, $phone) = ld_me();
$leads = ld_view($me, $agent, $phone);
$cats = [];
foreach (LD_CATS as $k => $c) { $cats[$k] = ['label' => $c['label'], 'subs' => $c['subs']]; }
?>
<!doctype html>
<html lang="en">
<head>
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
.h0{background:#b71c1c;color:#fff}.h1{background:#e53935;color:#fff}.h2{background:#f6b1aa;color:#5c1410}.h3{background:#fff;color:#2b3a3c}.h4{background:#a9cbe9;color:#0f3558}.h5{background:#1f5fa8;color:#fff}
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
var CSRF=<?= json_encode($_SESSION['csrf']) ?>, CATS=<?= json_encode($cats) ?>, LEADS=<?= json_encode($leads) ?>, TODAY=<?= json_encode(ld_today()) ?>;
var HEAT={new:0,ready:1,a:1,soon:2,b:2,later:3,c:3,unid:4,d:4,dnc:5};
var SOURCES=["Website","Landing page","Facebook","Instagram","Google","Zillow","Realtor.com","Open house","Sign call","Referral","Sphere","Other"];
var view={tab:"overview",sub:null,q:""};
var tabs=document.getElementById("tabs"),body=document.getElementById("body"),mback=document.getElementById("mback"),modal=document.getElementById("modal");
function esc(t){return String(t==null?"":t).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}
function nm(L){return ((L.first||"")+" "+(L.last||"")).trim()||L.phone||L.email||"(no name)";}
function fmtD(d){if(!d)return "";var x=new Date(d+"T12:00:00");return x.toLocaleDateString(undefined,{month:"short",day:"numeric"});}
function digits(p){return String(p||"").replace(/[^0-9+]/g,"");}
function subLabel(role,sub){if(role==="new")return "NEW";return (CATS[role]&&CATS[role].subs[sub])||sub;}
function heatClass(sub){return "h"+(sub==="new"?0:HEAT[sub]);}
function inRole(L,role){return role==="new"?!L.view.some(function(v){return v.role!=="new";}):!!(L.roles&&L.roles[role]);}
function vfor(L,role){for(var i=0;i<L.view.length;i++){if(L.view[i].role===role)return L.view[i];}return null;}
function dueBadge(v){
  if(!v)return "";
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
  tabs.innerHTML=h;
}
tabs.addEventListener("click",function(e){var b=e.target.closest("button");if(!b)return;view.tab=b.dataset.t;view.sub=null;view.q="";render();});
function hotList(){
  var items=[];LEADS.forEach(function(L){L.view.forEach(function(v){if(v.due&&v.over>=0&&v.sub!=="dnc")items.push([L,v]);});});
  items.sort(function(a,b){return (HEAT[a[1].sub]-HEAT[b[1].sub])||(b[1].over-a[1].over);});return items;
}
function soonList(){var items=[];LEADS.forEach(function(L){L.view.forEach(function(v){if(v.due&&v.over<0&&v.over>=-7&&v.sub!=="dnc")items.push([L,v]);});});items.sort(function(a,b){return b[1].over-a[1].over;});return items;}
function renderOverview(){
  var c=counts(),hot=hotList(),soon=soonList(),wk=0,newWk=0,readyB=0,readyS=0,sa=0,cold=0;
  var wkAgo=new Date(TODAY+"T12:00:00");wkAgo.setDate(wkAgo.getDate()-7);var wkS=wkAgo.toISOString().slice(0,10);
  LEADS.forEach(function(L){(L.log||[]).forEach(function(g){if(g.d>=wkS)wk++;});if((L.added||"")>=wkS)newWk++;
    if(L.roles&&L.roles.buyer&&L.roles.buyer.sub==="ready")readyB++;if(L.roles&&L.roles.seller&&L.roles.seller.sub==="ready")readyS++;if(L.roles&&L.roles.sphere&&L.roles.sphere.sub==="a")sa++;
    L.view.forEach(function(v){if(v.sub==="unid"||v.sub==="dnc"||v.sub==="d")cold++;});});
  var h='<div class="stats">'
   +'<div class="stat hot"><b>'+hot.length+'</b><span>Touches due now</span></div>'
   +'<div class="stat hot"><b>'+c.new+'</b><span>NEW leads to sort</span></div>'
   +'<div class="stat hot"><b>'+readyB+'</b><span>Ready buyers</span></div>'
   +'<div class="stat hot"><b>'+readyS+'</b><span>Ready sellers</span></div>'
   +'<div class="stat"><b>'+sa+'</b><span>Sphere A Yes</span></div>'
   +'<div class="stat"><b>'+wk+'</b><span>Touches done, last 7 days</span></div>'
   +'<div class="stat"><b>'+newWk+'</b><span>Leads added, last 7 days</span></div>'
   +'<div class="stat cold"><b>'+cold+'</b><span>Cold (Undetermined, D, Do Not Contact)</span></div></div>';
  h+='<div class="lpanel"><h2>Hot leads: do these today</h2>'+(hot.length?hot.map(function(x){return row(x[0],x[1]);}).join(""):'<p class="empty">Nothing due. Nice work.</p>')+'</div>';
  h+='<div class="lpanel" style="margin-top:14px"><h2>Coming up in the next 7 days</h2>'+(soon.length?soon.map(function(x){return row(x[0],x[1]);}).join(""):'<p class="empty">Nothing scheduled this week.</p>')+'</div>';
  body.innerHTML=h;
}
function renderCat(){
  var role=view.tab,subs=role==="new"?{}:CATS[role].subs,keys=Object.keys(subs);
  if(view.sub===null)view.sub=role==="new"?"all":keys[0];
  var side=role==="new"?"":'<button class="sb h0" id="addHere">+ NEW <span class="n">add</span></button>';
  if(role==="new"){side+='<button class="sb h1 on">All NEW <span class="n">'+counts().new+'</span></button>';}
  keys.forEach(function(k,i){var n=LEADS.filter(function(L){return L.roles&&L.roles[role]&&L.roles[role].sub===k;}).length;
    side+='<button class="sb h'+(i+1)+(view.sub===k?" on":"")+'" data-s="'+k+'">'+esc(subs[k])+' <span class="n">'+n+'</span></button>';});
  var list=LEADS.filter(function(L){return role==="new"?inRole(L,"new"):(L.roles&&L.roles[role]&&L.roles[role].sub===view.sub);});
  if(view.q){var q=view.q.toLowerCase();list=list.filter(function(L){return (nm(L)+" "+(L.phone||"")+" "+(L.email||"")+" "+(L.source||"")).toLowerCase().indexOf(q)>-1;});}
  list.sort(function(a,b){var va=vfor(a,role),vb=vfor(b,role);return ((vb&&vb.over!=null?vb.over:-999)-(va&&va.over!=null?va.over:-999));});
  var title=role==="new"?"NEW leads: call, then sort into Buyers, Sellers or Sphere":CATS[role].label+" · "+subs[view.sub];
  var main='<div class="lpanel"><h2>'+esc(title)+'</h2><input class="lsearch" id="lq" placeholder="Search name, phone, email" value="'+esc(view.q)+'">'
    +(list.length?list.map(function(L){return row(L,vfor(L,role));}).join(""):'<p class="empty">No leads here yet.</p>')+'</div>';
  body.innerHTML='<div class="lwrap"><div class="lside">'+side+'</div><div>'+main+'</div></div>';
  body.querySelectorAll(".sb[data-s]").forEach(function(b){b.onclick=function(){view.sub=b.dataset.s;render();};});
  var ah=document.getElementById("addHere");if(ah)ah.onclick=function(){var r={};r[role]=view.sub;openLead(null,r);};
  var lq=document.getElementById("lq");lq.oninput=function(){view.q=lq.value;var p=lq.selectionStart;renderCat();var n=document.getElementById("lq");n.focus();n.setSelectionRange(p,p);};
}
function render(){renderTabs();if(view.tab==="overview")renderOverview();else renderCat();}
body.addEventListener("click",function(e){var r=e.target.closest(".lrow");if(!r)return;var L=LEADS.filter(function(x){return x.id===r.dataset.id;})[0];if(L)openLead(L);});
async function api(payload){payload.csrf=CSRF;var r=await fetch("leads_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)});var j=await r.json();if(!j.ok)throw new Error(j.msg||"Could not save.");LEADS=j.leads;return j;}
function mv(role,cur){var h='<div class="mvrow" data-role="'+role+'" data-val="'+esc(cur)+'"><b>'+esc(CATS[role].label)+'</b><button type="button" class="mvb none'+(cur===""?" on":"")+'" data-v="">'+(role==="sphere"?"Not in Sphere":"None")+'</button>';
  Object.keys(CATS[role].subs).forEach(function(k,i){h+='<button type="button" class="mvb h'+(i+1)+(cur===k?" on":"")+'" data-v="'+k+'">'+esc(CATS[role].subs[k])+'</button>';});return h+'</div>';}
function openLead(L,preset){
  var isNew=!L;L=L||{first:"",last:"",phone:"",email:"",source:"",notes:"",roles:{},view:[],log:[]};
  var rs={};["buyer","seller","sphere"].forEach(function(k){rs[k]=(L.roles&&L.roles[k]&&L.roles[k].sub)||(preset&&preset[k])||"";});
  var h='<h2>'+(isNew?"Add a lead":esc(nm(L)))+'</h2><div class="fgrid">'
   +'<div><label>First name</label><input id="f_first" value="'+esc(L.first)+'"></div><div><label>Last name</label><input id="f_last" value="'+esc(L.last)+'"></div>'
   +'<div><label>Phone</label><input id="f_phone" type="tel" value="'+esc(L.phone)+'"></div><div><label>Email</label><input id="f_email" type="email" value="'+esc(L.email)+'"></div>'
   +'<div><label>Source</label><input id="f_source" list="srcs" value="'+esc(L.source)+'"><datalist id="srcs">'+SOURCES.map(function(s){return '<option value="'+esc(s)+'">';}).join("")+'</datalist></div>'
   +'<div><label>Added</label><input value="'+esc(L.added?fmtD(L.added):"Today")+'" disabled></div>'
   +'<div class="full mvbox" id="mvbox"><h3>'+(isNew?'Category':'Move lead')+'</h3>'+mv("buyer",rs.buyer)+mv("seller",rs.seller)+mv("sphere",rs.sphere)
   +'<div style="font-size:.82rem;color:#5a6a6c">'+(isNew?'Choose at least one: Buyer, Seller or Sphere. A lead can be both a buyer and a seller.':'Tap a button to move the lead right away. Choosing None in all three sends it back to NEW. Moving restarts that plan.')+'</div></div>'
   +'<div class="full"><label>Notes</label><textarea id="f_notes" rows="3">'+esc(L.notes)+'</textarea></div></div>'
   +'<div class="btns"><button class="btn" id="m_save">'+(isNew?"Add lead":"Save changes")+'</button><button class="btn ghost" id="m_close">Close</button>'+(isNew?"":'<button class="btn ghost" id="m_del" style="margin-left:auto;color:#b71c1c;border-color:#e3b4b4">Delete</button>')+'</div><p class="note" id="m_msg" hidden></p>';
  if(!isNew){
    L.view.forEach(function(v){
      var head=(v.role==="new"?"NEW":CATS[v.role].label+" · "+subLabel(v.role,v.sub));
      h+='<div class="touch"><div class="hd"><span><span class="dot '+heatClass(v.sub)+'" style="display:inline-block;vertical-align:middle;margin-right:6px"></span>'+esc(head)+'</span>'+dueBadge(v)+'</div>';
      if(v.sub==="dnc"){h+='<p class="goal">This person asked not to be contacted. No messages are scheduled.</p>';}
      else if(v.done){h+='<p class="goal">This plan is finished. Sort the lead into Buyers, Sellers or Sphere so it keeps getting follow-up.</p>';}
      else if(v.text){
        h+='<div class="goal">'+(v.step>v.of?'Touch '+v.step+' (ongoing)':'Step '+v.step+' of '+v.of)+' · '+esc(v.ch)+' · Lofty stage: '+esc(v.lofty)+'</div>';
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
  modal.querySelectorAll(".mvrow").forEach(function(r){r.addEventListener("click",async function(e){var b=e.target.closest(".mvb");if(!b||b.classList.contains("on"))return;
    var prev=r.dataset.val;r.dataset.val=b.dataset.v;r.querySelectorAll(".mvb").forEach(function(x){x.classList.toggle("on",x===b);});
    if(isNew)return;
    try{await api({action:"save",id:L.id,lead:{first:val("f_first"),last:val("f_last"),phone:val("f_phone"),email:val("f_email"),source:val("f_source"),notes:val("f_notes")},roles:readRoles()});var lbl=CATS[r.dataset.role].label+" · "+(b.dataset.v?CATS[r.dataset.role].subs[b.dataset.v]:"none");reopen(L.id);render();var hh=document.querySelector("#mvbox h3");if(hh)hh.innerHTML="Move lead <span style=\"font-weight:500;color:#2e8b57\">· Saved: "+esc(lbl)+"</span>";}
    catch(err){r.dataset.val=prev;r.querySelectorAll(".mvb").forEach(function(x){x.classList.toggle("on",x.dataset.v===prev);});say(err.message,true);}
  });});
  var msg=document.getElementById("m_msg");function say(t,bad){msg.textContent=t;msg.className=bad?"note sigerr":"note";msg.hidden=false;}
  document.getElementById("m_close").onclick=close;
  document.getElementById("m_save").onclick=async function(){var b=this;b.disabled=true;
    var roles=readRoles(),any=Object.keys(roles).some(function(k){return roles[k];});
    if(isNew&&!any){say("Choose Buyer, Seller or Sphere for this lead.",true);b.disabled=false;return;}
    try{await api({action:"save",id:isNew?"":L.id,lead:{first:val("f_first"),last:val("f_last"),phone:val("f_phone"),email:val("f_email"),source:val("f_source"),notes:val("f_notes")},roles:roles});close();render();}
    catch(e){say(e.message,true);b.disabled=false;}};
  if(!isNew){document.getElementById("m_del").onclick=async function(){if(!confirmDel(this))return;try{await api({action:"delete",id:L.id});close();render();}catch(e){say(e.message,true);}};}
  modal.querySelectorAll(".touch").forEach(function(t){
    var tx=t.querySelector(".t_text"),sj=t.querySelector(".t_subj");
    var s=t.querySelector(".t_sms");if(s)s.onclick=function(e){e.preventDefault();location.href="sms:"+digits(L.phone)+"?&body="+encodeURIComponent(tx.value);};
    var m=t.querySelector(".t_mail");if(m)m.onclick=function(e){e.preventDefault();location.href="mailto:"+encodeURIComponent(L.email)+"?subject="+encodeURIComponent(sj?sj.value:"")+"&body="+encodeURIComponent(tx.value);};
    var c=t.querySelector(".t_copy");if(c)c.onclick=function(){var v=(sj&&sj.value?sj.value+"\n\n":"")+tx.value;(navigator.clipboard?navigator.clipboard.writeText(v):Promise.reject()).then(function(){c.textContent="Copied";},function(){tx.select();document.execCommand("copy");c.textContent="Copied";});};
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
