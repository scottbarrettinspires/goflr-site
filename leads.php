<?php
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/leadlib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
list($agent, $phone) = ld_me();
ld_autosort($me);
$leads = ld_view($me, $agent, $phone);
$intake = ld_intake($me, $agent, $phone);
$intakeUrl = 'https://goflr.com/lead_in.php?k=' . ($intake['key'] ?? '');
$ldb = ld_load($me); $mySources = ld_src_names((array)($ldb['sources'] ?? [])); $myInterests = (array)($ldb['interests'] ?? []);
$emb = !empty($_GET['embed']);
$recTx = $emb ? (string)($_GET['tx'] ?? '') : ''; $recSide = ($_GET['side'] ?? '') === 'seller' ? 'seller' : 'buyer'; $recCd = [];
if ($recTx !== '' && preg_match('/^[A-Za-z0-9_-]{10,80}$/', $recTx)) { // opened from an Active Business record: that record's own details come first
    require_once __DIR__ . '/sigtools.php'; $ri = $recSide === 'seller' ? sg_seller_info($me, $recTx) : sg_buyer_info($me, $recTx); $ri = (!empty($ri['ok']) && is_array($ri['info'] ?? null)) ? $ri['info'] : []; $q = $recSide === 'seller' ? 's2' : 'b2';
    $recCd = array_filter(['co_name' => $ri[$q . '_name'] ?? '', 'co_phone' => $ri[$q . '_phone'] ?? '', 'co_email' => $ri[$q . '_email'] ?? '', 'mailing' => $ri['mailing'] ?? '', 'lender' => $ri['lender'] ?? '', 'lender_rep' => $ri['lender_rep'] ?? '', 'lender_phone' => $ri['lender_phone'] ?? '', 'lender_email' => $ri['lender_email'] ?? '', 'preamt' => $ri['preapproval'] ?? '',
        'fin' => isset($ri['payment']) && $ri['payment'] !== '' ? [['cash' => 'Cash', 'conventional' => 'Conventional', 'fhava' => 'FHA/VA'][$ri['payment']] ?? ''] : ''], function ($v) { return $v !== '' && $v !== [''] ; });
} else { $recTx = ''; }
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
<title>Leads &amp; Contacts - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=17">
<style>
.ltabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}
.ltabs button{font:inherit;font-weight:700;padding:11px 18px;border-radius:12px;border:1px solid var(--line);background:var(--white);color:var(--teal);cursor:pointer;display:flex;gap:8px;align-items:center}
.ltabs button.on{background:var(--teal);color:#fff;border-color:var(--teal)}
.ltabs button .n{font-size:.78rem;font-weight:700;background:rgba(0,0,0,.08);border-radius:99px;padding:1px 8px}
.ltabs button.on .n{background:rgba(255,255,255,.25)}
.ltabs .ov{color:#5a6a6c;font-weight:600}
@keyframes newpulse{0%{box-shadow:0 0 0 0 rgba(229,57,53,.55)}70%{box-shadow:0 0 0 12px rgba(229,57,53,0)}100%{box-shadow:0 0 0 0 rgba(229,57,53,0)}}
@keyframes newbeat{0%,100%{transform:scale(1)}15%{transform:scale(1.35)}30%{transform:scale(1)}45%{transform:scale(1.2)}60%{transform:scale(1)}}
.ltabs .newtab.alert:not(.on){animation:newpulse 1.8s ease-out infinite}.ltabs .newtab.alert .n{display:inline-block;animation:newbeat 1.8s ease-in-out infinite;background:#fff;color:#c62828}
@media (prefers-reduced-motion:reduce){.ltabs .newtab.alert,.ltabs .newtab.alert .n{animation:none}}

.lwrap{display:flex;flex-direction:column;gap:14px}
.lside{display:flex;flex-direction:row;gap:6px;overflow-x:auto;padding:7px 8px 9px;scroll-snap-type:x proximity}.lside>.sb{border:1px solid rgba(255,255,255,.35);box-shadow:0 1px 3px rgba(20,40,50,.18);letter-spacing:.01em}.lside>.hall{background:#fff;border:1px solid #cfdcdc;color:#2b3a3c}
.lside>.h1{background:linear-gradient(135deg,#b3261e,#e5534b);color:#fff}.lside>.h2{background:linear-gradient(135deg,#e5614f,#f2a064);color:#fff}
.lside>.h3{background:linear-gradient(135deg,#f3d6a6,#d3e2ee);color:#3d3a2e}.lside>.h4{background:linear-gradient(135deg,#cfe0ee,#9fc1e0);color:#143a5c}
.lside>.h5{background:linear-gradient(135deg,#2f6db3,#1b4a85);color:#fff}.lside>.h6{background:linear-gradient(135deg,#0a8a86,#036f72);color:#fff}
.qm{display:inline-flex;align-items:center;justify-content:center;width:1.25em;height:1.25em;border-radius:50%;background:#143a5c;color:#fff;font-weight:800;font-size:.85em;line-height:1;vertical-align:-1px}
.mvb.h1{background:linear-gradient(135deg,#b3261e,#e5534b);color:#fff}.mvb.h2{background:linear-gradient(135deg,#e5614f,#f2a064);color:#fff}
.mvb.h3{background:linear-gradient(135deg,#f3d6a6,#d3e2ee);color:#3d3a2e}.mvb.h4{background:linear-gradient(135deg,#cfe0ee,#9fc1e0);color:#143a5c}
.mvb.h5{background:linear-gradient(135deg,#2f6db3,#1b4a85);color:#fff}.mvb.h6{background:linear-gradient(135deg,#0a8a86,#036f72);color:#fff}
.mvb{border:1px solid rgba(255,255,255,.35);box-shadow:0 1px 2px rgba(20,40,50,.15)}
.mvsel{font:inherit;font-weight:700;padding:7px 12px;border-radius:10px;border:1px solid var(--line);background:#fff;color:#2b3a3c;min-width:200px;margin-right:8px}
.lwrap.inrec>.lside{display:none}
.lside{align-items:center}.lside>.sb[data-s="dnc"]{margin-left:auto}.lside>.sb{flex:0 0 auto;width:auto;min-width:0;scroll-snap-align:start;margin-top:0!important;padding:6px 12px;line-height:1.2}
.sb{font:inherit;font-weight:700;text-align:left;padding:6px 12px;border-radius:12px;border:2px solid rgba(0,0,0,.08);cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:8px}
.sb .n{font-size:.8rem;opacity:.85}
.sb.on{outline:3px solid #1f2a2c;outline-offset:2px}
.wk{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border:1px solid var(--line);border-radius:12px;overflow:hidden}
.wkd{border-left:1px solid var(--line);min-height:150px;padding:8px 6px;display:flex;flex-direction:column;gap:6px;min-width:0;background:#fff}.wkd:first-child{border-left:0}
.wkd.off{background:#f6f8f8}.wkd.today{background:#f0f8f8}
.wkh{display:flex;flex-direction:column;align-items:center;padding:0 0 6px;border-bottom:1px solid #edf1f1;margin-bottom:2px}
.wkh .dn{font-size:.72rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#5a6a6c}
.wkh .dd{font-size:1.45rem;font-weight:500;line-height:1.25;color:#1f2a2c;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.wkh .dm{font-size:.7rem;color:#8a9a9c;text-align:center}.wkd.today .wkh .dn{color:var(--teal)}.wkd.today .wkh .dd{background:var(--teal);color:#fff}
.wki{display:flex;flex-direction:column;gap:6px;min-width:0}
.wkit{display:flex;flex-direction:column;gap:1px;padding:6px 7px;border-radius:7px;background:#e1f3f2;border-left:3px solid #036f72;color:#1f2a2c;font-size:.78rem;line-height:1.3;overflow-wrap:anywhere;cursor:pointer}
.wkit:hover{filter:brightness(.96)}.wkit .wd{font-weight:700}.wkit .wn{font-weight:600;color:#2a3638}.wkit .wa{color:#5a6a6c}
.h0w,.h1w{background:#fde2e1;border-left-color:#e53935}.h2w{background:#fdecea;border-left-color:#f6b1aa}.h3w{background:#f3f6f6;border-left-color:#b9c4c6}.h4w{background:#e8f1fa;border-left-color:#a9cbe9}.h5w{background:#e3ecf7;border-left-color:#1f5fa8}.h6w{background:#e1f3f2;border-left-color:#036f72}
.wnone{color:#c4cfd0;text-align:center;margin-top:8px}
@media(max-width:760px){.wkd.none{display:none}.wk.wkempty{display:none}.wk .wkd{border-top:0}.wk .wkd:not(.none)~.wkd:not(.none){border-top:1px solid var(--line)}}
@media(max-width:760px){.wk{grid-template-columns:1fr}.wkd{border-left:0;border-top:1px solid var(--line);min-height:0;flex-direction:row;align-items:flex-start;gap:10px}.wkd:first-child{border-top:0}
  .wkh{flex:0 0 58px;border-bottom:0;margin:0}.wki{flex:1}.wnone{margin:10px 0 0;text-align:left}}
.hall{background:#fff;color:#2b3a3c}.h0{background:#b71c1c;color:#fff}.h1{background:#e53935;color:#fff}.h2{background:#f6b1aa;color:#5c1410}.h3{background:#fff;color:#2b3a3c}.h4{background:#a9cbe9;color:#0f3558}.h5{background:#1f5fa8;color:#fff}.h6{background:#036f72;color:#fff}
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
.fgrid .full{grid-column:1/-1}.cmprow{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}.cmprow>div{min-width:0}.cmprow label{white-space:nowrap}.mmg{border:1px solid var(--line);border-radius:12px;padding:6px 8px 8px;background:#f7fafa}.mmg>label{margin:0 0 3px 2px}.mmr{display:flex;align-items:center;gap:5px}.mmr i{font-style:normal;font-size:.78rem;color:#6a777a}.mmr select{width:74px}.mmg.wide .mmr input{width:76px}.cmprow select,.cmprow input{padding:9px 8px!important;background-color:#fff}
.touch{border:1px solid #9fd3cf;border-radius:14px;padding:14px 16px;margin:12px 0;background:linear-gradient(135deg,#c6e9e7 0%,#ddf2f0 50%,#fde6cf 100%);box-shadow:0 6px 20px rgba(3,111,114,.12)}.touch .hd{font-size:1.08rem}.touch .t_done{background:#e07b39;color:#fff;border-color:#e07b39}.touch .t_done:hover{background:#c96a2c}.touch .t_copy{background:#fff}.touch .t_subj,.touch .t_text{background:#fff}
.touch .hd{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;font-weight:700;margin:0 0 6px}
.touch .goal{font-size:.82rem;color:#5a6a6c;margin:6px 0}
.btns{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.btn.sm{padding:8px 12px;font-size:.9rem}
.hist{font-size:.85rem;color:#4b5b5d;max-height:160px;overflow:auto;border-top:1px solid var(--line);margin-top:10px;padding-top:8px}
.mvbox{border:1px solid var(--line);border-radius:12px;padding:12px;margin:12px 0}.mvbox h3{margin:0 0 8px;font-size:1rem}
.mvrow{display:flex;flex-wrap:wrap;gap:5px;align-items:center;margin:0 0 8px}.mvrow b{width:58px;font-size:.82rem}
.mvb{font:inherit;font-size:.76rem;font-weight:700;padding:4px 8px;border-radius:8px;border:1.5px solid rgba(0,0,0,.1);cursor:pointer;opacity:.55;line-height:1.3}.mvb svg{width:10px;height:16px;vertical-align:-3px}#mvbox .btn.sm{padding:5px 10px;font-size:.78rem}#mvbox h3{font-size:.95rem}
.mvb.on{opacity:1;outline:3px solid #1f2a2c;outline-offset:1px}.mvb.none{background:#f1f4f4;color:#4b5b5d}.mvb:hover{opacity:1}
.chips{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0}.picksum{display:contents}.picksum .goal{font-size:.88rem;color:#6a777a}.chip{font:inherit;font-size:.85rem;font-weight:600;padding:7px 12px;border-radius:99px;border:1px solid var(--line);background:#fff;color:#4b5b5d;cursor:pointer}
.chip.on{background:var(--teal);border-color:var(--teal);color:#fff}.chip .n{font-weight:700;opacity:.8;margin-left:4px}
.ltabs .srcbtn{margin-left:auto;font-weight:600;color:#4b5b5d}
.bulk{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#f4f8f8;border:1px solid var(--line);border-radius:12px;padding:10px 12px;margin:0 0 12px}
.lsearch{width:100%;max-width:340px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;font:inherit;margin:0 0 12px}
@media(max-width:800px){.fgrid{grid-template-columns:1fr}}

/* compact controls across the Leads page */
.chip{font-size:.76rem;padding:4px 9px;border-radius:99px}.chip .n{margin-left:3px}
.btn.sm{padding:5px 10px;font-size:.78rem;border-radius:8px}
.modal .btns .btn,.modal .btns a.btn{padding:7px 13px;font-size:.84rem;border-radius:9px}
.ltabs button{padding:8px 13px;font-size:.88rem;border-radius:10px}.ltabs button .n{font-size:.72rem;padding:1px 7px}
.sb{padding:6px 10px;font-size:.9rem;border-radius:10px}.lside>.sb{padding:6px 10px}
.qa a{width:30px;height:30px;font-size:.85rem}
.due{font-size:.72rem;padding:2px 8px}
.bulk .btn{padding:5px 10px;font-size:.78rem}
.modal .btns .btn.sm,.modal .btns a.btn.sm{padding:5px 10px;font-size:.78rem;border-radius:8px}
.touch textarea,.touch input,.fgrid input,.fgrid textarea,.fgrid select{font-size:.92rem;padding:8px 10px}
.ltabs button.addtab{background:#e07b39;border-color:#e07b39;color:#fff;font-weight:800}.ltabs button.addtab:hover{background:#c96a2c}
.ltabs button.newtab{background:#d32f2f;border-color:#d32f2f;color:#fff}.ltabs button.newtab .n{background:rgba(255,255,255,.28);color:#fff}.ltabs button.newtab.on{background:#b71c1c;border-color:#b71c1c;box-shadow:0 0 0 3px rgba(183,28,28,.25)}
.mtabs{display:flex;gap:6px;border-bottom:1px solid var(--line);margin:0 0 12px}.mtabs button{font:inherit;font-size:.86rem;font-weight:700;padding:8px 14px;border:0;border-bottom:3px solid transparent;background:none;color:#5a6a6c;cursor:pointer}
.mtabs button.on{color:var(--teal);border-bottom-color:var(--teal)}.mtabs{flex-wrap:wrap}.mtabs button.mtx{color:var(--teal);font-weight:600;text-decoration:underline;padding:8px 8px}.mtabs .n{font-size:.72rem;background:#eef3f3;border-radius:99px;padding:1px 7px;margin-left:4px}
.hist.pane{max-height:none;border-top:0;margin-top:0;padding-top:0;font-size:.9rem;line-height:1.7}
#mvbox.mvnew{border:2px solid #d32f2f;background:#fff6f5;box-shadow:0 2px 10px rgba(211,47,47,.12)}
.stat.urgent{background:#d32f2f;border-color:#d32f2f;color:#fff;cursor:pointer;box-shadow:0 4px 14px rgba(211,47,47,.25)}.stat.urgent span{color:#fff;font-weight:600}.stat.urgent b{font-size:2rem}.stat.urgent:hover{background:#b71c1c}
body.emb .top,body.emb .hero,body.emb .ltabs,body.emb .lside,body.emb .mclose,body.emb .mclose2,body.emb .mclose3,body.emb .modal.inl>:not([data-pane]){display:none!important}
body.emb{background:transparent}body.emb .wrap{padding:0;margin:0;max-width:none}body.emb .modal.inl{border:0!important;padding:0!important;box-shadow:none!important;background:transparent!important}
</style>
</head>
<body<?= $emb ? ' class="emb"' : '' ?>>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Virtual Broker Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php" aria-current="page">Leads &amp; Contacts</a>
    <a href="my-transactions.php">Active Business</a>
    <a href="performance.php">Performance</a>
    <a href="learn.php">Learn</a>
    <?php endif; ?>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a><?php endif; ?>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>
<section class="hero"><h1>Leads &amp; Contacts</h1><p>Hot leads are red, cold leads are blue. Work the reds first.</p></section>
<main class="wrap">
  <div class="ltabs" id="tabs"></div>
  <div id="body"></div>
</main>
<div class="mback" id="mback"><div class="modal" id="modal"></div></div>
<footer>Florida Luxury Realty, Inc. &middot; Your leads are private to your account.</footer>
<script>
(function(){
var CSRF=<?= json_encode($_SESSION['csrf']) ?>, ALLSRC=<?= json_encode(LD_SOURCES) ?>, MYSRC=<?= json_encode(array_values($mySources)) ?>, ALLINT=<?= json_encode(LD_INTERESTS) ?>, MYINT=<?= json_encode(array_values($myInterests)) ?>, CATS=<?= json_encode($cats) ?>, LEADS=<?= json_encode($leads) ?>, TODAY=<?= json_encode(ld_today()) ?>, INTAKE=<?= json_encode($intakeUrl) ?>;
var WEEK=<?php $wk = []; for ($i = 0; $i < 7; $i++) { $dd = date('Y-m-d', strtotime(ld_today() . " +$i days")); $o = dl_offday($dd); $wk[] = ['d' => $dd, 'dn' => date('D', strtotime($dd)), 'n' => date('j', strtotime($dd)), 'm' => date('M', strtotime($dd)), 'off' => $o, 'hol' => in_array($o, ['Saturday', 'Sunday'], true) ? '' : $o]; } echo json_encode($wk); ?>;
var NEWALERT=true;setTimeout(function(){NEWALERT=false;[].forEach.call(document.querySelectorAll(".newtab.alert"),function(b){b.classList.remove("alert");});},10000); // the NEW tab animates for 10 seconds each time the page opens
var HEAT={new:0,ready:1,a:1,unid:1,soon:2,b:2,later:3,c:3,d:4,dnc:5,client:6};
var SOURCES=ALLSRC;
var view={tab:"overview",sub:null,q:""};
var EMB=<?= $emb ? "true" : "false" ?>,RECTX=<?= json_encode($recTx) ?>,RECSIDE=<?= json_encode($recSide) ?>,RECCD=<?= json_encode((object)$recCd) ?>;var tabs=document.getElementById("tabs"),body=document.getElementById("body"),mback=document.getElementById("mback"),modal=document.getElementById("modal"),MODAL=modal;
function esc(t){return String(t==null?"":t).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}
function nm(L){return ((L.first||"")+" "+(L.last||"")).trim()||L.phone||L.email||"(no name)";}
function fmtD(d){if(!d)return "";var x=new Date(d+"T12:00:00");return x.toLocaleDateString(undefined,{month:"short",day:"numeric"});}
function digits(p){return String(p||"").replace(/[^0-9+]/g,"");}
var ICON={new:"\u2728",ready:"\uD83D\uDD25",unid:'<span class="qm">?</span>',dnc:"\u274C",a:"\uD83D\uDD25",client:"\uD83E\uDD1D"};
var THERMO={soon:0.75,b:0.75,later:0.25,c:0.25,d:0.08};
// A small thermometer filled to show how close the lead is to hot.
function thermo(f){var top=2,bot=16,h=Math.round((bot-top)*f),y=bot-h;
  return '<svg width="14" height="22" viewBox="0 0 14 22" aria-hidden="true" style="vertical-align:-5px;margin-right:3px"><rect x="4" y="1" width="6" height="16" rx="3" fill="#fff" stroke="#7a2a24" stroke-width="1.2"/>'
    +(h>0?'<rect x="5.5" y="'+y+'" width="3" height="'+(h+2)+'" fill="#d32f2f"/>':'')+'<circle cx="7" cy="18" r="3.4" fill="#d32f2f" stroke="#7a2a24" stroke-width="1.2"/></svg>';}
function ico(sub){if(THERMO[sub]!=null)return thermo(THERMO[sub])+" ";return ICON[sub]?ICON[sub]+" ":"";}
function subLabel(role,sub){if(role==="new")return "NEW";if(sub==="client")return "In Active Business";return (CATS[role]&&CATS[role].subs[sub])||sub;}
function heatClass(sub){return "h"+({new:0,ready:1,a:1,soon:2,b:2,later:3,c:3,unid:4,d:4,dnc:5,client:6}[sub]);}
function inRole(L,role){return role==="new"?!L.view.some(function(v){return v.role!=="new";}):!!(L.roles&&L.roles[role]);}
function vfor(L,role){for(var i=0;i<L.view.length;i++){if(L.view[i].role===role)return L.view[i];}return null;}
function dueBadge(v){
  if(!v)return "";
  if(v.sub==="client")return '<span class="due" style="background:#e1f3f2;color:#036f72">In Active Business</span>';
  if(v.sub==="dnc")return '<span class="due off">Do not contact</span>';
  if(v.done)return '<span class="due later">Plan finished</span>';
  if(v.over>0)return '<span class="due over">Overdue '+v.over+' day'+(v.over===1?"":"s")+' · '+esc(v.ch)+'</span>';
  if(v.over===0)return '<span class="due today">Due today · '+esc(v.ch)+'</span>';
  return '<span class="due later">'+esc(fmtD(v.due))+' · '+esc(v.ch)+'</span>';
}
function quick(L){var h='<span class="qa">';if(L.phone){h+='<a href="tel:'+esc(digits(L.phone))+'" title="Call" onclick="event.stopPropagation()">&#9742;</a><a href="sms:'+esc(digits(L.phone))+'" title="Text" onclick="event.stopPropagation()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg></a>';}
  if(L.email){h+='<a href="mailto:'+esc(L.email)+'" title="Email" onclick="event.stopPropagation()">&#9993;</a>';}return h+'</span>';}
function row(L,v){
  return '<div class="lrow" data-id="'+esc(L.id)+'"><span class="dot '+heatClass(v.sub)+'"></span><div class="who"><div class="nm">'+esc(nm(L))+'</div><div class="meta">'+esc((v.role==="new"?"NEW":CATS[v.role].label+" · "+subLabel(v.role,v.sub))+(L.source?" · "+L.source:"")+(L.phone?" · "+L.phone:""))+'</div></div>'+dueBadge(v)+quick(L)+'</div>';
}
function counts(){var c={new:0,buyer:0,seller:0,sphere:0};LEADS.forEach(function(L){["new","buyer","seller","sphere"].forEach(function(r){if(inRole(L,r))c[r]++;});});return c;}
function renderTabs(){
  var c=counts(),h='<button class="ov'+(view.tab==="overview"?" on":"")+'" data-t="overview">Overview</button>';
  h+='<button data-t="new" class="newtab'+(view.tab==="new"?" on":"")+(c.new>=1&&NEWALERT?" alert":"")+'">NEW <span class="n">'+c.new+'</span></button>';
  h+='<button type="button" class="addtab" id="addlead" title="Add a lead by hand">+ ADD</button>';
  ["buyer","seller","sphere"].forEach(function(k){h+='<button data-t="'+k+'" class="'+(view.tab===k?"on":"")+'">'+CATS[k].label+' <span class="n">'+c[k]+'</span></button>';});
  h+='<button data-t="tags" class="'+(view.tab==="tags"?"on":"")+'">By Source</button>';
  h+='<button class="srcbtn" id="mysrc" type="button">&#9881; My Lead Sources</button>';
  tabs.innerHTML=h;
}
tabs.addEventListener("click",function(e){var b=e.target.closest("button");if(!b)return;if(b.id==="mysrc"){openSources();return;}if(b.id==="addlead"){openLead(null,{});return;}view.tab=b.dataset.t;view.sub=null;view.q="";render();autoOpenNew();});
function hotList(){
  var items=[];LEADS.forEach(function(L){L.view.forEach(function(v){if(v.due&&v.over>=0&&v.sub!=="dnc")items.push([L,v]);});});
  items.sort(function(a,b){return (HEAT[a[1].sub]-HEAT[b[1].sub])||(b[1].over-a[1].over);});return items;
}
// "Coming up in the next 7 days" as a calendar strip (same look as Active Business): today + 6 days, each lead's
// next touch under its day with the name, category and what to do. Overdue touches stay in Hot leads below.
function weekStrip(){var by={};WEEK.forEach(function(w){by[w.d]=[];});
  LEADS.forEach(function(L){L.view.forEach(function(v){if(v.due&&v.sub!=="dnc"&&by[v.due])by[v.due].push([L,v]);});});
  var any=0,h='<div class="lpanel"><h2>Coming up in the next 7 days</h2><div class="wk">';
  WEEK.forEach(function(w,i){var it=by[w.d];it.sort(function(a,b){return HEAT[a[1].sub]-HEAT[b[1].sub];});any+=it.length;
    h+='<div class="wkd'+(i===0?' today':'')+(w.off?' off':'')+(it.length?'':' none')+'"><div class="wkh"><span class="dn">'+(i===0?'Today':w.dn)+'</span><span class="dd">'+w.n+'</span><span class="dm">'+w.m+(w.hol?'<br>'+esc(w.hol):'')+'</span></div><div class="wki">';
    h+=it.length?it.map(function(x){var L=x[0],v=x[1];return '<div class="wkit '+heatClass(v.sub)+'w" data-id="'+esc(L.id)+'" role="button" tabindex="0"><span class="wd">'+esc(v.ch||"Touch")+'</span><span class="wn">'+esc(nm(L))+'</span><span class="wa">'+esc(v.role==="new"?"NEW":CATS[v.role].label+" · "+subLabel(v.role,v.sub))+'</span></div>';}).join(""):'<span class="wnone">&mdash;</span>';
    h+='</div></div>';});
  if(!any)h=h.replace('<div class="wk">','<div class="wk wkempty">');
  return h+'</div>'+(any?'':'<p class="empty" style="padding:10px 0 0">Nothing scheduled this week.</p>')+'</div>';}
function soonList(){var items=[];LEADS.forEach(function(L){L.view.forEach(function(v){if(v.due&&v.over<0&&v.over>=-7&&v.sub!=="dnc")items.push([L,v]);});});items.sort(function(a,b){return b[1].over-a[1].over;});return items;}
function renderOverview(){
  var c=counts(),hot=hotList(),soon=soonList(),wk=0,newWk=0,readyB=0,readyS=0,sa=0,cold=0,unr=0;
  var wkAgo=new Date(TODAY+"T12:00:00");wkAgo.setDate(wkAgo.getDate()-7);var wkS=wkAgo.toISOString().slice(0,10);
  LEADS.forEach(function(L){(L.log||[]).forEach(function(g){if(g.d>=wkS)wk++;});if((L.added||"")>=wkS)newWk++;
    if(L.roles&&L.roles.buyer&&L.roles.buyer.sub==="ready")readyB++;if(L.roles&&L.roles.seller&&L.roles.seller.sub==="ready")readyS++;if(L.roles&&L.roles.sphere&&L.roles.sphere.sub==="a")sa++;
    L.view.forEach(function(v){if(v.sub==="unid")unr++;if(v.sub==="dnc"||v.sub==="d")cold++;});});
  var h='<div class="stats">'
   +'<div class="stat urgent" id="st_due" role="button" title="See them"><b>'+hot.length+'</b><span>Touches due now</span></div>'
   +'<div class="stat urgent" id="st_new" role="button" title="Open NEW"><b>'+c.new+'</b><span>NEW leads to sort</span></div>'
   +'<div class="stat hot"><b>'+unr+'</b><span>Not reached yet (Undetermined)</span></div>'
   +'<div class="stat hot"><b>'+readyB+'</b><span>Ready buyers</span></div>'
   +'<div class="stat hot"><b>'+readyS+'</b><span>Ready sellers</span></div>'
   +'<div class="stat"><b>'+sa+'</b><span>Sphere A Always</span></div>'
   +'<div class="stat" style="border-left:5px solid #036f72"><b>'+LEADS.filter(function(L){return L.roles&&((L.roles.buyer&&L.roles.buyer.sub==="client")||(L.roles.seller&&L.roles.seller.sub==="client"));}).length+'</b><span>Clients in Active Business</span></div>'
   +'<div class="stat"><b>'+wk+'</b><span>Touches done, last 7 days</span></div>'
   +'<div class="stat"><b>'+newWk+'</b><span>Leads added, last 7 days</span></div>'
   +'<div class="stat cold"><b>'+cold+'</b><span>Cold (D Maybe Not, Do Not Contact)</span></div></div>';
  h+=weekStrip();
  h+='<div class="lpanel" style="margin-top:14px"><h2>Hot leads: do these today</h2>'+(hot.length?hot.map(function(x){return row(x[0],x[1]);}).join(""):'<p class="empty">Nothing due. Nice work.</p>')+'</div>';
  body.innerHTML=h;
  var sd=document.getElementById("st_due");if(sd)sd.onclick=function(){var hp=body.querySelector(".lpanel");if(hp)hp.scrollIntoView({behavior:"smooth",block:"start"});};
  var sn=document.getElementById("st_new");if(sn)sn.onclick=function(){view.tab="new";view.sub=null;render();autoOpenNew();};
}
function renderCat(){
  var role=view.tab,subs=role==="new"?{}:CATS[role].subs,keys=Object.keys(subs);
  if(view.sub===null)view.sub="all"; // main categories open on every lead in that category; the + ADD button adds a new one
  var side="";
  if(role==="new"){side+='<button class="sb h1 on">'+ico("new")+'All NEW <span class="n">'+counts().new+'</span></button>';}
  if(role!=="new"){var na=LEADS.filter(function(L){return L.roles&&L.roles[role];}).length;
    side+='<button class="sb hall'+(view.sub==="all"?" on":"")+'" data-s="all"><span>All '+esc(CATS[role].label)+'</span> <span class="n">'+na+'</span></button>';}
  keys.forEach(function(k,i){var n=LEADS.filter(function(L){return L.roles&&L.roles[role]&&L.roles[role].sub===k;}).length;
    side+='<button class="sb h'+(i+1)+(view.sub===k?" on":"")+'" data-s="'+k+'"'+(k==="dnc"?' title="Do Not Contact" aria-label="Do Not Contact"':'')+'><span>'+ico(k)+(k==="dnc"?"":esc(subs[k]))+'</span> <span class="n">'+n+'</span></button>';});
  if(role==="buyer"||role==="seller"){var nc=LEADS.filter(function(L){return L.roles&&L.roles[role]&&L.roles[role].sub==="client";}).length;
    side+='<button class="sb h6'+(view.sub==="client"?" on":"")+'" data-s="client">'+ico("client")+'Active <span class="n">'+nc+'</span></button>';}
  var list=LEADS.filter(function(L){return role==="new"?inRole(L,"new"):(view.sub==="all"?!!(L.roles&&L.roles[role]):(L.roles&&L.roles[role]&&L.roles[role].sub===view.sub));});
  if(view.q){var q=view.q.toLowerCase();list=list.filter(function(L){return (nm(L)+" "+(L.phone||"")+" "+(L.email||"")+" "+(L.source||"")).toLowerCase().indexOf(q)>-1;});}
  list.sort(function(a,b){var va=vfor(a,role),vb=vfor(b,role);return ((vb&&vb.over!=null?vb.over:-999)-(va&&va.over!=null?va.over:-999));});
  var title=role==="new"?"NEW leads: call, then sort into Buyers, Sellers or Sphere":CATS[role].label+" · "+(view.sub==="all"?"All":(view.sub==="client"?"In Active Business":subs[view.sub]));
  if(view.sub==="client")CATS[role].stage.client=role==="buyer"?"Signed or signing a buyer broker agreement. The record lives in Active Business until the sale closes.":"Signed or signing a listing agreement. The record lives in Active Business until the sale closes.";
  var main=view.sub==="add"?'<div class="lpanel">'+(view.flash?'<p class="note" style="margin:0 0 10px">'+esc(view.flash)+'</p>':'')+'<p style="margin:0 0 10px;color:#5a6a6c;font-size:.9rem">'+(role==="sphere"?"Add a new Sphere contact below":"Add a new "+esc(CATS[role].label.replace(/s$/,"").toLowerCase())+" lead below")+', or tap a category to see who is in it.</p><div class="modal inl" id="inlAdd" style="padding:0;max-width:none;margin:0;background:none"></div></div>':'<div class="lpanel"><h2>'+esc(title)+'</h2>'+(role!=="new"&&CATS[role].stage[view.sub]?'<p style="margin:-6px 0 12px;color:#5a6a6c;font-size:.9rem">'+esc(CATS[role].stage[view.sub])+'</p>':(role==="new"?'<p style="margin:-6px 0 12px;color:#5a6a6c;font-size:.9rem">Leads from your website, landing pages and social media. Reach them, learn what they need, then move them to Buyers, Sellers or Sphere.</p>':''))+'<input class="lsearch" id="lq" placeholder="Search name, phone, email" value="'+esc(view.q)+'">'
    +(list.length?list.map(function(L){return row(L,vfor(L,role));}).join(""):'<p class="empty">No leads here yet. Use + ADD to add one.</p>')+'</div>';
  body.innerHTML='<div class="lwrap"><div class="lside">'+side+'</div><div>'+main+'</div></div>';
  body.querySelectorAll(".sb[data-s]").forEach(function(b){b.onclick=function(){view.sub=b.dataset.s;render();};});
  view.flash="";
  var ia=document.getElementById("inlAdd");if(ia){var r={};r[role]="-";openLead(null,r,ia);var mc=document.getElementById("m_close");if(mc)mc.textContent="Clear";}
  var lq=document.getElementById("lq");if(lq)lq.oninput=function(){view.q=lq.value;var p=lq.selectionStart;renderCat();var n=document.getElementById("lq");n.focus();n.setSelectionRange(p,p);};
}
// With exactly one NEW lead, opening the NEW tab goes straight to that lead's record (runs only when the agent navigates to the tab, so closing the record does not reopen it).
function autoOpenNew(){if(view.tab!=="new")return;var l=LEADS.filter(function(L){return inRole(L,"new");});if(l.length===1)openLead(l[0]);}
function render(){modal=MODAL;renderTabs();if(view.tab==="overview")renderOverview();else if(view.tab==="tags")renderTags();else renderCat();}
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
function openSources(){modal=MODAL;
  var all=ALLSRC.slice();MYSRC.forEach(function(t){if(all.indexOf(t)<0)all.push(t);});
  var alli=ALLINT.slice();MYINT.forEach(function(t){if(alli.indexOf(t)<0)alli.push(t);});
  var sel=MYSRC.slice(),seli=MYINT.slice();
  function chips(list,on,grp){return '<div class="chips">'+list.map(function(t){return '<button type="button" class="chip'+(on.indexOf(t)>-1?" on":"")+'" data-g="'+grp+'" data-s="'+esc(t)+'">'+esc(t)+'</button>';}).join("")+'</div>';}
  function addRow(id,ph){return '<div style="display:flex;gap:8px;margin:8px 0 16px"><input id="'+id+'" placeholder="'+ph+'" style="flex:1;padding:9px 11px;border:1px solid var(--line);border-radius:9px;font:inherit"><button class="btn sm ghost" data-add="'+id+'" type="button">Add</button></div>';}
  function draw(){modal.innerHTML='<h2>My Lead Sources</h2><p style="color:#5a6a6c">Tap every way you get leads. These become tags on each lead, and the <strong>By Source</strong> tab shows, emails or calls everyone from one source.</p>'
    +chips(all,sel,"s")+addRow("src_new","Add your own, separated by commas (e.g. Builder partner)")
    +'<h3 style="margin:6px 0 4px">Interests and connections</h3><p style="color:#5a6a6c;margin:0 0 6px">Mostly for your Sphere: how you know people and what you share. Tag contacts with them to remember the connection and to reach everyone who golfs, plays pickleball and so on.</p>'
    +chips(alli,seli,"i")+addRow("int_new","Add your own, separated by commas (e.g. Rotary Club, Kayaking)")
    +'<div class="btns"><button class="btn" id="src_save">Save</button><button class="btn ghost" id="s_close">Close</button></div><p class="note" id="s_msg" hidden></p>';
    modal.querySelectorAll(".chip[data-g]").forEach(function(b){b.onclick=function(){var arr=b.dataset.g==="s"?sel:seli,t=b.dataset.s,i=arr.indexOf(t);if(i>-1)arr.splice(i,1);else arr.push(t);b.classList.toggle("on");};});
    modal.querySelectorAll("[data-add]").forEach(function(b){b.onclick=function(){var isS=b.dataset.add==="src_new",inp=document.getElementById(b.dataset.add);
      inp.value.split(",").map(function(x){return x.trim().slice(0,40);}).filter(Boolean).forEach(function(t){var L1=isS?all:alli,S1=isS?sel:seli;if(L1.indexOf(t)<0)L1.push(t);if(S1.indexOf(t)<0)S1.push(t);});draw();};});
    document.getElementById("s_close").onclick=close;
    document.getElementById("src_save").onclick=async function(){var b=this;b.disabled=true;try{var j=await api({action:"sources",sources:sel,interests:seli});MYSRC=j.sources;MYINT=j.interests;close();render();}catch(e){var m=document.getElementById("s_msg");m.textContent=e.message;m.hidden=false;b.disabled=false;}};
  }
  draw();mback.classList.add("show");
}

body.addEventListener("click",function(e){var r=e.target.closest(".lrow,.wkit[data-id]");if(!r)return;var L=LEADS.filter(function(x){return x.id===r.dataset.id;})[0];if(L)openLead(L);});
async function api(payload){payload.csrf=CSRF;var r=await fetch("leads_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)});var j=await r.json();if(!j.ok)throw new Error(j.msg||"Could not save.");LEADS=j.leads;return j;}
function mv(role,cur,withNone){if(cur==="client")return '<div class="mvrow" data-role="'+role+'" data-val="client">'+(MV_LBL?'<b>'+esc(CATS[role].label)+'</b>':'')+'<span class="due" style="background:#e1f3f2;color:#036f72">In Active Business</span></div>';var h='<div class="mvrow" data-role="'+role+'" data-val="'+esc(cur)+'">'+(MV_LBL?'<b>'+esc(CATS[role].label)+'</b>':'')+''+(MV_ACT&&role==="buyer"?'<button type="button" class="mvb h6" style="opacity:1" data-act="execute" title="Buyer consultation: opens the buyer record in Active Business at the Buyer Agreement, ready to send, download or upload the signed agreement">\uD83D\uDE0A Buyer Consultation</button>':'')+(MV_ACT&&role==="seller"?'<button type="button" class="mvb h6" style="opacity:1" data-act="prelist" title="Listing appointment: start the Pre Listing record in Active Business and prepare your listing documents">\uD83D\uDE0A Listing Appointment</button>':'')+(withNone?'<button type="button" class="mvb none'+(cur===""?" on":"")+'" data-v="">'+(role==="sphere"?"Not in Sphere":"None")+'</button>':'');
  Object.keys(CATS[role].subs).forEach(function(k,i){h+='<button type="button" class="mvb h'+(i+1)+(cur===k?" on":"")+'" data-v="'+k+'" title="'+esc(CATS[role].stage[k]||"")+'">'+ico(k)+esc(CATS[role].subs[k])+'</button>';});return h+'</div>';}
// Move lead: only the stages of the lead's own main category, with small buttons for the occasional cross-over.
// Chosen tags show as pills beside a Choose button; the button opens the agent's own options from My Lead Sources.
function pickBlock(kind,sel,all,label){
  if(!all.length)return '<p class="goal">'+(kind==="tag"?'Set up':'Choose your interests and connections in')+' <a href="#" class="opensrc">My Lead Sources</a>'+(kind==="tag"?' to tag leads by how you got them.':' to tag people with them.')+'</p>';
  var box=kind==="tag"?"tagbox":"intbox",attr=kind==="tag"?"data-tag":"data-int";
  return '<div class="chips"><span class="picksum" data-for="'+box+'" data-label="'+esc(label)+'">'+pills(sel,box,label)+'</span></div>'
    +'<div class="pickwrap" data-box="'+box+'" hidden style="border:1px solid var(--line);border-radius:12px;padding:10px;margin:4px 0 8px"><div class="chips" id="'+box+'">'+all.map(function(x){return '<button type="button" class="chip'+(sel.indexOf(x)>-1?" on":"")+'" '+attr+'="'+esc(x)+'">'+esc(x)+'</button>';}).join("")+'</div>'
    +'<button type="button" class="btn sm" data-done="'+box+'">Done</button> <a href="#" class="opensrc" style="font-size:.82rem">Edit my choices</a></div>';
}
// The chosen tags themselves are the buttons: tap one to change. With none chosen, one "+ Choose" tag shows.
function pills(sel,box,label){return sel.length?sel.map(function(x){return '<button type="button" class="chip on pickopen" data-box="'+box+'" title="Tap to change">'+esc(x)+'</button>';}).join(""):'<button type="button" class="chip pickopen" data-box="'+box+'" style="border-style:dashed">+ '+esc(label||"Choose")+'</button>';}
function tagChips(L,isNew){var t=(L.tags||[]).slice(),all=MYSRC.slice();t.forEach(function(x){if(all.indexOf(x)<0)all.push(x);});return pickBlock("tag",t,all,"Choose lead source");}
function intChips(L){var t=(L.interests||[]).slice(),all=MYINT.slice();t.forEach(function(x){if(all.indexOf(x)<0)all.push(x);});return pickBlock("int",t,all,"Choose interests");}

function sphereList(L){return LEADS.filter(function(x){return x.id!==L.id&&x.roles&&x.roles.sphere;}).sort(function(a,b){return nm(a).localeCompare(nm(b));});}
function refLabel(x){return nm(x)+(x.phone?" · "+x.phone:"");}
function refPicker(L){var cur=L.ref?LEADS.filter(function(x){return x.id===L.ref;})[0]:null;
  return '<input id="f_ref" list="refopts" placeholder="Start typing a Sphere contact\'s name" value="'+esc(cur?refLabel(cur):"")+'" autocomplete="off"><datalist id="refopts">'+sphereList(L).map(function(x){return '<option value="'+esc(refLabel(x))+'">';}).join("")+'</datalist>';}
function readRef(L){var v=(document.getElementById("f_ref")||{value:""}).value.trim();if(!v)return "";var m=sphereList(L).filter(function(x){return refLabel(x)===v||nm(x)===v;})[0];return m?m.id:null;}
function readInts(){var o=[];modal.querySelectorAll("#intbox .chip.on").forEach(function(b){o.push(b.dataset.int);});return o;}
function readTags(){var o=[];modal.querySelectorAll("#tagbox .chip.on").forEach(function(b){o.push(b.dataset.tag);});return o;}

// ---- Buyer "Home Wants": the buyer's ideal property, kept on the lead ----
var HW={types:["Single family","Condo","Townhome","Villa","Manufactured","Multi-family","Land / lot","New construction"],
  fin:["Cash","Conventional","FHA","VA","USDA","Not sure"],pre:["Pre-approved","In progress","Not yet"],
  garage:["No garage needed","1 car","2 car","3+ car","Carport ok"],pool:["Must have","Nice to have","No pool"],
  home:["Renting","Owns, needs to sell first","Owns, keeping it","Living with family","Other"],
  feats:["Waterfront","Water view","Boat dock / lift","Golf course","Gated community","55+ community","No HOA","Low HOA","No CDD","Single story","Split bedroom plan",
    "Office / den","Bonus room","Guest suite / in-law","Open floor plan","Updated kitchen","Screened lanai","Fenced yard","Large yard","RV / boat parking",
    "Workshop","Impact windows / shutters","Newer roof","Solar","Fireplace","Spa / hot tub","Horse property","Near beaches","Near shopping","Easy commute","Top schools","Move-in ready","Fixer-upper ok"]};
function hwSel(id,opts,cur,ph){return '<select id="'+id+'"><option value="">'+(ph||"Any")+'</option>'+opts.map(function(o){return '<option'+(String(cur)===String(o)?" selected":"")+'>'+esc(o)+'</option>';}).join("")+'</select>';}
function hwChips(grp,opts,cur,multi){cur=cur||[];if(!Array.isArray(cur))cur=cur?[cur]:[];return '<div class="chips" data-grp="'+grp+'" data-multi="'+(multi?1:0)+'">'+opts.map(function(o){return '<button type="button" class="chip'+(cur.indexOf(o)>-1?" on":"")+'" data-v="'+esc(o)+'">'+esc(o)+'</button>';}).join("")+'</div>';}
function hwSummary(w){if(!w)return "";var p=[];
  if(w.price_min||w.price_max)p.push("$"+(w.price_min||"0")+" to $"+(w.price_max||"open"));var rg=function(lo,hi,u){return lo&&hi?(lo===hi?lo:lo+" to "+hi)+" "+u:lo?lo+"+ "+u:hi?"up to "+hi+" "+u:"";};
  [rg(w.beds,w.beds_max,"beds"),rg(w.baths,w.baths_max,"baths"),rg(w.sqft,w.sqft_max,"sq ft")].forEach(function(x){if(x)p.push(x);});if((w.types||[]).length)p.push(w.types.join("/"));if((w.garage||[]).length)p.push(w.garage.join("/")+" garage");
  if((w.pool||[]).length&&w.pool[0]!=="No pool")p.push("pool: "+w.pool[0].toLowerCase());if(w.areas)p.push("in "+w.areas);if((w.feats||[]).length)p.push(w.feats.join(", "));
  if((w.fin||[]).length)p.push((w.fin||[]).join("/")+((w.pre||[]).length?" ("+w.pre[0].toLowerCase()+")":""));if(w.move_by)p.push("move by "+w.move_by);return p.join(" · ");}
function hwForm(w){w=w||{};var n=[1,2,3,4,5,6,7],b=["1","1.5","2","2.5","3","3.5","4+"];
  return '<div class="fgrid">'
   +'<div class="full"><label>Areas, cities or ZIP codes</label><input id="hw_areas" value="'+esc(w.areas||"")+'" placeholder="e.g. Trinity, Land O Lakes, 34655"></div>'
   +'<div><label>Price from</label><input id="hw_price_min" inputmode="numeric" value="'+esc(w.price_min||"")+'" placeholder="e.g. 350,000"></div><div><label>Price to</label><input id="hw_price_max" inputmode="numeric" value="'+esc(w.price_max||"")+'" placeholder="e.g. 500,000"></div>'
   +'<div class="full"><label>Property type</label>'+hwChips("types",HW.types,w.types,true)+'</div>'
   +'<div class="full cmprow"><div class="mmg"><label>Beds</label><div class="mmr">'+hwSel("hw_beds",n,w.beds,"Min")+'<i>to</i>'+hwSel("hw_beds_max",n,w.beds_max,"Max")+'</div></div>'
   +'<div class="mmg"><label>Baths</label><div class="mmr">'+hwSel("hw_baths",b,w.baths,"Min")+'<i>to</i>'+hwSel("hw_baths_max",b,w.baths_max,"Max")+'</div></div>'
   +'<div class="mmg wide"><label>Square feet</label><div class="mmr"><input id="hw_sqft" inputmode="numeric" value="'+esc(w.sqft||"")+'" placeholder="Min"><i>to</i><input id="hw_sqft_max" inputmode="numeric" value="'+esc(w.sqft_max||"")+'" placeholder="Max"></div></div>'
   +'<div style="width:110px"><label>Lot (min)</label><input id="hw_lot" value="'+esc(w.lot||"")+'" placeholder="1/4 acre"></div>'
   +'<div style="width:88px"><label>Built after</label><input id="hw_year" inputmode="numeric" value="'+esc(w.year||"")+'" placeholder="Year"></div><div style="width:100px"><label>Stories</label>'+hwSel("hw_stories",["1 story","2 story","Either"],w.stories,"-")+'</div></div>'
   +'<div class="full"><label>Garage</label>'+hwChips("garage",HW.garage,w.garage,false)+'</div>'
   +'<div class="full"><label>Pool</label>'+hwChips("pool",HW.pool,w.pool,false)+'</div>'
   +'<div class="full"><label>Features and lifestyle</label>'+hwChips("feats",HW.feats,w.feats,true)+'</div>'
   +'<div class="full"><label>Must-haves</label><textarea id="hw_must" rows="2">'+esc(w.must||"")+'</textarea></div>'
   +'<div class="full"><label>Nice to have</label><textarea id="hw_nice" rows="2">'+esc(w.nice||"")+'</textarea></div>'
   +'<div class="full"><label>Deal-breakers</label><textarea id="hw_no" rows="2">'+esc(w.no||"")+'</textarea></div>'
   +'<div><label>Move by</label><input id="hw_move_by" type="date" value="'+esc(w.move_by||"")+'"></div><div><label>Lease ends (if renting)</label><input id="hw_lease" type="date" value="'+esc(w.lease||"")+'"></div>'
   +'<div class="full"><label>Current home</label>'+hwChips("home",HW.home,w.home,false)+'</div>'
   +'<div class="full"><label>Why they are moving</label><input id="hw_why" value="'+esc(w.why||"")+'"></div>'
   +'<div class="full"><label>Who else decides</label><input id="hw_who" value="'+esc(w.who||"")+'" placeholder="e.g. spouse, parents"></div></div>';}
function hwRead(root){var o={};["areas","price_min","price_max","beds","beds_max","baths","baths_max","sqft","sqft_max","lot","year","stories","must","nice","no","lender","preamt","move_by","lease","why","who"].forEach(function(k){var el=root.querySelector("#hw_"+k);if(el)o[k]=el.value.trim();});
  root.querySelectorAll(".chips[data-grp]").forEach(function(c){o[c.dataset.grp]=[].map.call(c.querySelectorAll(".chip.on"),function(b){return b.dataset.v;});});return o;}

// ---- Seller "Property Details": the seller's home, for pricing and the listing appointment ----
var SP={types:["Single family","Condo","Townhome","Villa","Manufactured","Multi-family","Land / lot"],garage:["No garage","1 car","2 car","3+ car","Carport"],
  pool:["No pool","In-ground","Screened enclosure","Heated","Salt water","Spa / hot tub","Above ground","Community pool"],
  water:["Not waterfront","Gulf access canal","Fresh water canal","Gulf / bay front","Lake","Pond","River","Preserve view","Golf course view"],
  dock:["Dock","Boat lift","Seawall"],roof:["Shingle","Tile","Metal","Flat"],windows:["Impact windows","Hurricane shutters","Standard windows"],
  plumb:["Copper","PEX","CPVC","Galvanized","Polybutylene","Not sure"],cond:["Move-in ready","Updated","Needs cosmetic work","Needs major repair","Investor / fixer"],
  upd:["Kitchen","Bathrooms","Flooring","Interior paint","Exterior paint","Roof","AC / HVAC","Water heater","Windows / doors","Electrical panel","Plumbing","Pool resurfaced","Solar (owned)","Solar (leased)","Generator","Smart home"],
  hoa:["No HOA","HOA","CDD","55+ community","Gated"],occ:["Owner lives there","Tenant","Vacant","Second home"],insp:["Wind mitigation","4-point inspection","Recent home inspection","Survey on hand","Elevation certificate"],
  flood:["Flood zone X","Flood zone AE / VE","Not sure"],next:["Buying with me","Buying elsewhere","Renting next","Not sure"]};
function spForm(w){w=w||{};var n=[1,2,3,4,5,6,7],b=["1","1.5","2","2.5","3","3.5","4+"];
  var t=function(id,lab,ph,full,im){return '<div'+(full?' class="full"':'')+'><label>'+lab+'</label><input id="sp_'+id+'"'+(im?' inputmode="numeric"':'')+' value="'+esc(w[id]||"")+'" placeholder="'+esc(ph||"")+'"></div>';};
  var c=function(grp,lab,opts,multi){return '<div class="full"><label>'+lab+'</label>'+hwChips(grp,opts,w[grp],multi)+'</div>';};
  return '<div class="fgrid">'+t("addr","Property address","Street, city, ZIP",true)
   +c("types","Property type",SP.types,false)
   +'<div class="full cmprow"><div style="width:76px"><label>Beds</label>'+hwSel("sp_beds",n,w.beds,"-")+'</div><div style="width:84px"><label>Baths</label>'+hwSel("sp_baths",b,w.baths,"-")+'</div>'
   +'<div style="width:104px"><label>Sq ft</label><input id="sp_sqft" inputmode="numeric" value="'+esc(w.sqft||"")+'"></div><div style="width:150px"><label>Lot size</label><input id="sp_lot" value="'+esc(w.lot||"")+'" placeholder="e.g. 0.25 acre"></div>'
   +'<div style="width:96px"><label>Year built</label><input id="sp_year" inputmode="numeric" value="'+esc(w.year||"")+'"></div><div style="width:112px"><label>Stories</label>'+hwSel("sp_stories",["1 story","2 story","3+"],w.stories,"-")+'</div></div>'
   +c("garage","Garage",SP.garage,false)+c("pool","Pool",SP.pool,true)+c("water","Waterfront and view",SP.water,true)+c("dock","On the water",SP.dock,true)
   +c("roof","Roof type",SP.roof,false)+t("roof_year","Roof installed (year)","",false,1)+t("ac_year","AC / HVAC installed (year)","",false,1)
   +t("wh_year","Water heater installed (year)","",false,1)+t("panel","Electrical panel (brand / year)","")
   +c("plumb","Plumbing",SP.plumb,false)+c("windows","Windows",SP.windows,true)
   +c("cond","Condition",SP.cond,false)+c("upd","Updated or replaced",SP.upd,true)+t("upd_note","Update details","e.g. kitchen 2022, new floors 2023",true)
   +c("hoa","HOA and community",SP.hoa,true)+t("hoa_fee","HOA fee (amount and how often)","e.g. $150 a month")+t("cdd_fee","CDD (amount per year)","")
   +c("flood","Flood zone",SP.flood,false)+c("insp","Reports on hand",SP.insp,true)
   +c("occ","Who lives there",SP.occ,false)+'<div><label>Lease ends (if tenant)</label><input id="sp_lease" type="date" value="'+esc(w.lease||"")+'"></div><div></div>'
   +t("price_hope","Price they hope for","",false,1)+t("payoff","Mortgage payoff (estimate)","",false,1)
   +'<div><label>Need to sell by</label><input id="sp_sell_by" type="date" value="'+esc(w.sell_by||"")+'"></div>'+t("why","Why they are selling","")
   +c("next","After the sale",SP.next,false)+t("who","Who else decides","e.g. spouse, co-owner, estate",true)
   +'<div class="full"><label>Known issues or repairs needed</label><textarea id="sp_issues" rows="2">'+esc(w.issues||"")+'</textarea></div>'
   +'<div class="full"><label>Best features to market</label><textarea id="sp_best" rows="2">'+esc(w.best||"")+'</textarea></div></div>';}
function spRead(root){var o={};["addr","beds","baths","sqft","lot","year","stories","roof_year","ac_year","wh_year","panel","upd_note","hoa_fee","cdd_fee","lease","price_hope","payoff","sell_by","why","who","issues","best"].forEach(function(k){var el=root.querySelector("#sp_"+k);if(el)o[k]=el.value.trim();});
  root.querySelectorAll(".chips[data-grp]").forEach(function(c){o[c.dataset.grp]=[].map.call(c.querySelectorAll(".chip.on"),function(b){return b.dataset.v;});});return o;}
function spSummary(w){if(!w)return "";var p=[],cy=new Date(TODAY+"T12:00:00").getFullYear(),age=function(y){y=parseInt(y,10);return y>1900?(cy-y)+" yrs":"";};
  if(w.addr)p.push(w.addr);if((w.types||[]).length)p.push(w.types[0]);if(w.beds||w.baths)p.push((w.beds||"?")+" bd / "+(w.baths||"?")+" ba");if(w.sqft)p.push(w.sqft+" sq ft");if(w.lot)p.push("lot "+w.lot);
  if(w.year)p.push("built "+w.year);if((w.garage||[]).length)p.push(w.garage[0]);if((w.pool||[]).length&&w.pool[0]!=="No pool")p.push("pool: "+w.pool.join(", "));
  if((w.water||[]).length&&w.water[0]!=="Not waterfront")p.push(w.water.join(", "));if((w.dock||[]).length)p.push(w.dock.join(", "));
  if(w.roof_year)p.push((((w.roof||[])[0]||"")+" roof "+age(w.roof_year)).trim().replace(/^roof/,"Roof"));if(w.ac_year)p.push("AC "+age(w.ac_year));if(w.wh_year)p.push("water heater "+age(w.wh_year));
  if((w.cond||[]).length)p.push(w.cond[0]);if((w.upd||[]).length)p.push("updated: "+w.upd.join(", "));if((w.hoa||[]).length)p.push(w.hoa.join(", ")+(w.hoa_fee?" ("+w.hoa_fee+")":""));
  if((w.flood||[]).length)p.push(w.flood[0]);if((w.occ||[]).length)p.push(w.occ[0]);if(w.sell_by)p.push("sell by "+w.sell_by);return p.join(" · ");}
var MV_ACT=false,MV_LBL=true;
function mvBox(L,isNew,rs){MV_ACT=!isNew;
  var cur=["buyer","seller","sphere"].filter(function(k){return rs[k];});
  MV_LBL=!cur.length; // category names only while the lead has no category yet (NEW)
  if(!cur.length){ // NEW (online) leads: buyer, seller or both, then the stage. Sphere does not apply to these.
    return '<div class="mvrow" id="mvside" style="margin:0 0 10px"><h3 style="margin:0 8px 0 0;font-size:1.05rem;color:#b71c1c">Is this lead a</h3>'
      +[["buyer","Buyer"],["seller","Seller"],["both","Buyer and Seller"]].map(function(x){return '<button type="button" class="mvb mvside" style="opacity:1;background:#fff;font-size:.86rem;padding:6px 12px;border-color:#d32f2f;color:#b71c1c" data-side="'+x[0]+'">'+x[1]+'</button>';}).join("")+'</div>'
      +'<div class="mvh" data-role="buyer" hidden>'+mv("buyer","",false)+'</div><div class="mvh" data-role="seller" hidden>'+mv("seller","",false)+'</div>'
      +'<div class="mvtip" style="font-size:.82rem;color:#5a6a6c">Is this person buying, selling or both? Then tap how soon.</div>';}
  var bs=cur.some(function(k){return k==="buyer"||k==="seller";});
  var h=(isNew?'<h3>Category</h3>':'')+cur.map(function(k){
    return (k==="sphere"&&!isNew?'<h3'+(bs?' style="margin-top:10px"':'')+'>How Likely To Refer Me Real Estate Leads:</h3>':'')+mv(k,rs[k],false);}).join("");
  var extra=[],has=function(k){return cur.indexOf(k)>-1;};
  if(!has("seller")&&(has("buyer")||has("sphere")))extra.push(["seller","add",has("buyer")?"Add As Seller Also":"Add As Seller"]);
  if(!has("buyer")&&(has("seller")||has("sphere")))extra.push(["buyer","add",has("seller")?"Add As Buyer Also":"Add As Buyer"]);
  if(!has("sphere")&&(has("buyer")||has("seller")))extra.push(["sphere","move","Move To Sphere"]);
  h+=["buyer","seller","sphere"].filter(function(k){return !has(k);}).map(function(k){return '<div class="mvh" data-role="'+k+'" hidden>'+mv(k,"",false)+'</div>';}).join("");
  if(extra.length){h+='<div style="margin:6px 0 2px;font-size:.84rem;display:flex;gap:16px;flex-wrap:wrap">'+extra.map(function(x){return '<a href="#" class="mvx" data-role="'+x[0]+'" data-mode="'+x[1]+'" style="color:var(--teal);font-weight:600">'+esc(x[2])+'</a>';}).join("")+'</div>';}
  h+='<div class="mvpre" hidden style="margin:8px 0;padding:10px;border:1px dashed var(--line);border-radius:10px"><label style="font-size:.82rem;font-weight:600">Property address for the listing appointment</label><input id="pre_addr" value="'+esc((L.home&&L.home.addr)||"")+'" placeholder="e.g. 123 Palm Ave, Trinity FL" style="width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:9px;font:inherit;box-sizing:border-box;margin:4px 0 8px"><button type="button" class="btn sm" id="pre_go">Set up listing appointment</button> <span class="goal">The record goes in Active Business › SELLERS › Pre Listing with the seller\'s details, Property Details, notes and source. Prepare your listing documents there for the appointment.</span></div>';
  h+='<div class="mvtip" style="font-size:.82rem;color:#5a6a6c;margin-top:6px">'+''+'</div>';
  return h;
}
function openLead(L,preset,inl){
  if(!inl){ // a lead (or + ADD) opens in the page body, below the buttons, instead of in a popup
    var host=body.querySelector(".lwrap")?body.querySelector(".lwrap").lastElementChild:body;
    var lw0=body.querySelector(".lwrap");if(lw0)lw0.classList.add("inrec"); // the pipeline button row is hidden while a lead record is open
    host.innerHTML='<div class="modal inl" id="inlDetail" style="max-width:none;margin:0;border:1px solid var(--line)"></div>';
    inl=document.getElementById("inlDetail");
    if(!EMB)setTimeout(function(){var t=document.getElementById("tabs");if(t)window.scrollTo({top:Math.max(0,t.getBoundingClientRect().top+window.pageYOffset-12),behavior:"smooth"});},30);
  }
  modal=inl;
  var isNew=!L;L=L||{first:"",last:"",phone:"",email:"",source:"",notes:"",roles:{},view:[],log:[]};
  var rs={};["buyer","seller","sphere"].forEach(function(k){rs[k]=(L.roles&&L.roles[k]&&L.roles[k].sub)||(preset&&preset[k])||"";});
  var h='<h2 style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 0"><span>'+(isNew?"Add a lead":esc(nm(L))+(L.phone?'<a href="tel:'+esc(digits(L.phone))+'" style="margin-left:1.2em;color:var(--teal);text-decoration:none;white-space:nowrap">'+esc(L.phone)+'</a>':''))+'</span>'
+'</h2><div class="fgrid">'
   +'<div><label>First name</label><input id="f_first" value="'+esc(L.first)+'"></div><div><label>Last name</label><input id="f_last" value="'+esc(L.last)+'"></div>'
   +'<div><label>Phone</label><input id="f_phone" type="tel" value="'+esc(L.phone)+'"></div><div><label>Email</label><input id="f_email" type="email" value="'+esc(L.email)+'"></div>'
   +'<div><label>Source</label><input id="f_source" list="srcs" value="'+esc(L.source)+'"><datalist id="srcs">'+SOURCES.map(function(s){return '<option value="'+esc(s)+'">';}).join("")+'</datalist></div>'
   +'<div><label>Added</label><input value="'+esc(L.added?fmtD(L.added):"Today")+'" disabled></div>'
   +'<div class="full mvbox" id="mvbox">'+mvBox(L,isNew,rs)+'</div>'
   +'<div class="full" id="srcblock"><label>How you got this lead</label>'+tagChips(L,isNew)+'</div>'
   +'<div class="full"><label>Referred by</label>'+refPicker(L)+'</div>'
   +'<div class="full"><label>Interests and connections</label>'+intChips(L)+'</div>'
   +'<div class="full"><label>Notes</label><textarea id="f_notes" rows="3">'+esc(L.notes)+'</textarea></div></div>'
   +'<div class="btns"><button class="btn" id="m_save">'+(isNew?"Add lead":"Save changes")+'</button><button class="btn ghost" id="m_close">Close</button>'+(isNew?"":'<button class="btn ghost" id="m_del" style="margin-left:auto;color:#b71c1c;border-color:#e3b4b4">Delete</button>')+'</div><p class="note" id="m_msg" hidden></p>';
  if(!isNew){
    L.view.forEach(function(v){
      var head=L.view.length>1?ico(v.sub)+esc(v.role==="new"?"NEW":CATS[v.role].label+" · "+subLabel(v.role,v.sub)):"Next step"; // the category itself now shows at the top right
      h+='<div class="touch"><div class="hd"><span><span class="dot '+heatClass(v.sub)+'" style="display:inline-block;vertical-align:middle;margin-right:6px"></span>'+head+'</span>'+dueBadge(v)+'</div>';
      if(v.sub==="client"){
        var isB=v.role==="buyer",onPg=!isB||(v.stage||1)===1,open=v.folder?(onPg?(isB?'buyer.php':'seller.php')+'?tx='+encodeURIComponent(v.folder):'https://drive.google.com/drive/folders/'+encodeURIComponent(v.folder)):"";
        h+='<p class="goal">'+(isB?'Buyer':'Listing')+' record: <strong>'+esc(v.record||"")+'</strong> · Stage: <strong>'+esc(v.stagename)+'</strong>. '+(v.stage===3?'Closing check-ins run every 5 days until closing.':'Nurture messages pause while you work with this client directly.')+'</p><div class="btns">';
        if(open)h+='<a class="btn sm" href="'+esc(open)+'"'+(onPg?'':' target="_blank" rel="noopener"')+'>Open '+(isB?'buyer':'listing')+' record</a>';
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
    h+='<div class="hist" id="histbox"><strong>History</strong><br>'+(lg.length?lg.map(function(g){return esc(fmtD(g.d))+" · "+esc(g.role==="new"?"NEW":CATS[g.role]?CATS[g.role].label:g.role)+(g.mid?" · "+esc(g.mid):"")+(g.note?" · "+esc(g.note):"");}).join("<br>"):"No touches logged yet.")+'</div>';
  }
  modal.innerHTML=h;if(!inl)mback.classList.add("show");
  var fg=modal.querySelector(".fgrid");modal.querySelectorAll(".touch").forEach(function(t){modal.insertBefore(t,fg);}); // the next touch comes first; details below
  if(!isNew){var mvbx=document.getElementById("mvbox"),ft=modal.querySelector(".touch");modal.insertBefore(mvbx,ft||fg);} // stage buttons first, then the next touch
  if(!isNew){ // the stage buttons stay in the page but hidden; the pipeline label at the top right opens the choices and presses them
    [].forEach.call(modal.querySelectorAll("#mvbox .mvrow[data-role] .mvb[data-v]"),function(b){b.style.display="none";});
    var mvb0=document.getElementById("mvbox");if(mvb0)mvb0.style.display="none"; // nothing left to show in this box (the Listing Appointment address box re-opens it)
  }
  if(!isNew){ // Lead / Notes / History tabs inside the lead window
    // Lead source tags sit at the top right, beside the name and phone; the chooser opens just below the header.
    var sb=document.getElementById("srcblock"),hd=modal.querySelector("h2");
    if(sb&&hd){var lbl=sb.querySelector("label");if(lbl)lbl.remove();var row=sb.querySelector(".chips"),pw=sb.querySelector(".pickwrap");
      var slot=document.createElement("span");slot.title="How you got this lead";slot.style.cssText="margin-left:auto;font-size:1rem;font-weight:400;display:flex;align-items:center";
      if(row){row.style.margin="0";row.style.justifyContent="flex-end";slot.appendChild(row);}else{slot.appendChild(sb.firstElementChild||document.createTextNode(""));}
      slot.style.gap="8px";slot.style.flexWrap="wrap";slot.style.justifyContent="flex-end";
      (L.view||[]).slice().reverse().forEach(function(v){var rowEl=modal.querySelector('#mvbox .mvrow[data-role="'+v.role+'"]'),opts=rowEl?[].slice.call(rowEl.querySelectorAll(".mvb[data-v]")):[];
        var wrap=document.createElement("span");wrap.style.cssText="position:relative;display:inline-block";
        var p=document.createElement(opts.length?"button":"span");if(opts.length)p.type="button";p.className="mvb "+heatClass(v.sub);p.style.cssText="opacity:1;cursor:"+(opts.length?"pointer":"default")+";font-size:.85rem;padding:5px 12px;border-radius:99px;white-space:nowrap";p.title=opts.length?"Click to change where this lead is in your pipeline":"Where this lead is in your pipeline";
        p.innerHTML=ico(v.sub)+esc(v.role==="new"?"NEW":CATS[v.role].label+" · "+subLabel(v.role,v.sub))+(opts.length?' <span style="font-size:.7em">\u25BE</span>':"");wrap.appendChild(p);
        if(opts.length){var menu=document.createElement("div");menu.hidden=true;menu.style.cssText="position:absolute;right:0;top:calc(100% + 6px);z-index:30;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 6px 18px rgba(20,40,50,.18);padding:8px;display:flex;flex-direction:column;gap:6px;min-width:190px";
          opts.forEach(function(b){var o=document.createElement("button");o.type="button";o.className="mvb h"+(opts.indexOf(b)+1)+(b.dataset.v===(rowEl.dataset.val||"")?" on":"");o.style.cssText="opacity:1;text-align:left;font-size:.85rem;padding:7px 12px";o.innerHTML=b.innerHTML;
            o.onclick=function(e){e.stopPropagation();menu.hidden=true;if(b.dataset.v!==(rowEl.dataset.val||""))b.click();};menu.appendChild(o);});
          wrap.appendChild(menu);p.onclick=function(e){e.stopPropagation();menu.hidden=!menu.hidden;};document.addEventListener("click",function(){menu.hidden=true;});}
        slot.insertBefore(wrap,slot.firstChild);}); // current pipeline category beside the source; click it to change
      [].forEach.call(modal.querySelectorAll("#mvbox .mvb[data-act]"),function(b){if(b.closest(".mvh"))return;var c=document.createElement("button");c.type="button";c.className="mvb h6";c.style.cssText="opacity:1;font-size:.85rem;padding:5px 12px;border-radius:99px;white-space:nowrap";c.innerHTML=b.innerHTML;c.title=b.title||"";c.onclick=function(){b.click();};slot.insertBefore(c,slot.firstChild);b.style.display="none";}); // Buyer Consultation / Listing Appointment sit beside the pipeline label
      hd.appendChild(slot);if(pw){hd.parentNode.insertBefore(pw,hd.nextSibling);}sb.remove();}
    var h2=modal.querySelector("h2"),nl=(L.notes||"").trim()?(L.notes||"").trim().split(/\n/).length:0,hl=(L.log||[]).length;
    var bar=document.createElement("div");bar.className="mtabs";var sphOnly=L.roles&&L.roles.sphere&&!L.roles.buyer&&!L.roles.seller;bar.innerHTML='<button type="button" data-p="lead" class="on">Nurture</button><button type="button" data-p="addnote">Add Note</button>';
    h2.parentNode.insertBefore(bar,h2.nextSibling);
    [].forEach.call(modal.querySelectorAll("#mvbox .mvx"),function(x,i){var t=document.createElement("button");t.type="button";t.className="mtx";t.textContent=x.textContent;if(i===0)t.style.marginLeft="auto";t.onclick=function(){x.click();};bar.appendChild(t);x.parentNode.style.display="none";}); // Add As Seller Also / Move To Sphere sit in the tab row, after Notes
    var pLead=document.createElement("div"),pNotes=document.createElement("div");
    while(bar.nextSibling){pLead.appendChild(bar.nextSibling);}
    modal.appendChild(pLead);
    var ta=document.getElementById("f_notes"),nwrap=ta.closest(".full");
    pNotes.style.marginTop="14px";pNotes.innerHTML='<h3 style="margin:0 0 6px">Notes</h3><p class="goal" style="margin:0 0 6px">Dated notes about this person. Tap <strong>Add Note</strong> to start a new line with today\'s date.</p>';
    var nlist=document.createElement("div");nlist.className="hist pane";nlist.id="noteList";nlist.style.cssText="margin:0 0 6px";pNotes.appendChild(nlist);
    pNotes.appendChild(nwrap);nwrap.hidden=true;ta.rows=5;var lb=nwrap.querySelector("label");if(lb)lb.remove();
    function renderNotes(){var ls=(ta.value||"").split(/\n/).map(function(x){return x.trim();}).filter(Boolean).reverse();
      nlist.innerHTML=ls.length?ls.map(function(x){var m=/^([A-Z][a-z]{2,8}\.? \d{1,2}(?:,? \d{4})?)\s*[:\-\u2013]\s*(.*)$/.exec(x);return m?esc(m[1])+" · "+esc(m[2]):esc(x);}).join("<br>"):'<span style="color:#6a777a">No notes yet.</span>';}
    renderNotes();
    ta.style.cssText="width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid var(--line);border-radius:10px;font:inherit;font-size:.92rem;line-height:1.5";
    var nb=document.createElement("div");nb.className="btns";nb.innerHTML='<button type="button" class="btn sm" id="noteAdd">Add Note</button><button type="button" class="btn sm ghost" id="noteEdit">Edit notes</button><button type="button" class="btn sm" id="noteSave" hidden>Save notes</button><button type="button" class="btn sm ghost" id="noteCancel" hidden>Cancel</button><span class="goal" id="noteMsg"></span>';pNotes.appendChild(nb);
    var hb=document.getElementById("histbox");pLead.appendChild(pNotes);if(hb){hb.classList.add("pane");hb.style.marginTop="14px";pLead.appendChild(hb);} // Notes, then History, at the bottom of the Nurture tab
    modal.querySelectorAll(".mclose").forEach(function(b){b.onclick=close;});
    pLead.dataset.pane="lead";
    function showPane(p){modal.querySelectorAll("[data-pane]").forEach(function(x){x.hidden=x.dataset.pane!==p;});bar.querySelectorAll("button").forEach(function(b){b.classList.toggle("on",b.dataset.p===p);});}
    bar.addEventListener("click",function(e){var b=e.target.closest("button[data-p]");if(!b)return;if(b.dataset.p==="addnote"){showPane("lead");var na=document.getElementById("noteAdd");if(na)na.click();if(ta.scrollIntoView)ta.scrollIntoView({behavior:"smooth",block:"center"});return;}showPane(b.dataset.p);});
    var noteOrig=ta.value;function noteEditor(on){nwrap.hidden=!on;document.getElementById("noteSave").hidden=!on;document.getElementById("noteCancel").hidden=!on;document.getElementById("noteAdd").hidden=on;document.getElementById("noteEdit").hidden=on;nlist.hidden=on;}
    document.getElementById("noteEdit").onclick=function(){noteOrig=ta.value;noteEditor(true);ta.focus();};
    document.getElementById("noteCancel").onclick=function(){ta.value=noteOrig;noteEditor(false);renderNotes();};
    document.getElementById("noteAdd").onclick=function(){noteOrig=ta.value;noteEditor(true);var d=new Date(TODAY+"T12:00:00").toLocaleDateString(undefined,{month:"short",day:"numeric"});
      ta.value=(ta.value.trim()?ta.value.replace(/\s+$/,"")+"\n":"")+d+": ";ta.focus();ta.setSelectionRange(ta.value.length,ta.value.length);ta.scrollTop=ta.scrollHeight;};
    document.getElementById("noteSave").onclick=async function(){var b=this,m=document.getElementById("noteMsg");b.disabled=true;
      try{await api({action:"save",id:L.id,lead:{first:val("f_first"),last:val("f_last"),phone:val("f_phone"),email:val("f_email"),source:val("f_source"),notes:ta.value.trim()},roles:readRoles()});
        L.notes=ta.value.trim();m.textContent="Saved";noteEditor(false);renderNotes();renderTabs();}catch(e){m.textContent=e.message;}b.disabled=false;};
    if(L.roles&&L.roles.buyer){ // Property Details tab for buyers
      var pw2=document.createElement("div");pw2.dataset.pane="wants";pw2.hidden=true;var sum=hwSummary(L.wants);
      pw2.innerHTML='<div class="touch" style="margin-top:0"><div class="hd"><span>Ideal home</span>'+(L.wants&&L.wants.updated?'<span class="due later">Updated '+esc(fmtD(L.wants.updated))+'</span>':'')+'</div><p class="goal" id="hw_sum">'+(sum?esc(sum):'Nothing recorded yet. Fill this in during your buyer consultation.')+'</p>'
        +'<div class="btns"><button type="button" class="btn sm ghost" id="hw_copy">Copy summary for your MLS search</button></div></div>'+hwForm(L.wants)
        +'<div class="btns"><button type="button" class="btn" id="hw_save">Save property details</button><button type="button" class="btn ghost mclose2">Close</button><span class="goal" id="hw_msg"></span></div>';
      modal.appendChild(pw2);var wb=document.createElement("button");wb.type="button";wb.dataset.p="wants";wb.textContent="Property Details";bar.insertBefore(wb,bar.children[1]);
      pw2.querySelectorAll(".chips[data-grp] .chip").forEach(function(c){c.onclick=function(){var g=c.parentNode;if(g.dataset.multi!=="1"&&!c.classList.contains("on"))g.querySelectorAll(".chip").forEach(function(x){x.classList.remove("on");});c.classList.toggle("on");};});
      pw2.querySelector(".mclose2").onclick=close;
      document.getElementById("hw_copy").onclick=function(){var t=hwSummary(hwRead(pw2));var b=this;(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(function(){b.textContent="Copied";},function(){prompt("Copy:",t);});};
      document.getElementById("hw_save").onclick=async function(){var b=this,m=document.getElementById("hw_msg");b.disabled=true;var w=hwRead(pw2);["lender","preamt","fin","pre"].forEach(function(k){if(L.wants&&L.wants[k]!=null&&w[k]==null)w[k]=L.wants[k];});
        try{await api({action:"wants",id:L.id,wants:w});L.wants=w;document.getElementById("hw_sum").textContent=hwSummary(w)||"Nothing recorded yet.";m.textContent="Saved";render();}catch(e){m.textContent=e.message;}b.disabled=false;};
    }
    if(L.roles&&L.roles.seller){ // Property Details tab for sellers
      var ps=document.createElement("div");ps.dataset.pane="home";ps.hidden=true;var ssum=spSummary(L.home);
      ps.innerHTML='<div class="touch" style="margin-top:0"><div class="hd"><span>Seller\'s home</span>'+(L.home&&L.home.updated?'<span class="due later">Updated '+esc(fmtD(L.home.updated))+'</span>':'')+'</div><p class="goal" id="sp_sum">'+(ssum?esc(ssum):'Nothing recorded yet. Fill this in before or during your listing appointment.')+'</p>'
        +'<div class="btns"><button type="button" class="btn sm ghost" id="sp_copy">Copy summary for your CMA</button></div></div>'+spForm(L.home)
        +'<div class="btns"><button type="button" class="btn" id="sp_save">Save property details</button><button type="button" class="btn ghost mclose3">Close</button><span class="goal" id="sp_msg"></span></div>';
      modal.appendChild(ps);var sbt=document.createElement("button");sbt.type="button";sbt.dataset.p="home";sbt.textContent=(L.roles.buyer?"Seller Property":"Property Details");bar.insertBefore(sbt,bar.children[L.roles.buyer?2:1]);
      if(L.roles.buyer){var wbx=bar.querySelector('[data-p="wants"]');if(wbx)wbx.textContent="Buyer Property";}
      ps.querySelectorAll(".chips[data-grp] .chip").forEach(function(c){c.onclick=function(){var g=c.parentNode;if(g.dataset.multi!=="1"&&!c.classList.contains("on"))g.querySelectorAll(".chip").forEach(function(x){x.classList.remove("on");});c.classList.toggle("on");};});
      ps.querySelector(".mclose3").onclick=close;
      document.getElementById("sp_copy").onclick=function(){var t=spSummary(spRead(ps));var b=this;(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(function(){b.textContent="Copied";},function(){prompt("Copy:",t);});};
      document.getElementById("sp_save").onclick=async function(){var b=this,m=document.getElementById("sp_msg");b.disabled=true;var w=spRead(ps);
        try{await api({action:"wants",field:"home",id:L.id,wants:w});L.home=w;document.getElementById("sp_sum").textContent=spSummary(w)||"Nothing recorded yet.";m.textContent="Saved";render();}catch(e){m.textContent=e.message;}b.disabled=false;};
    }
    if(L.roles&&(L.roles.buyer||L.roles.seller)){ // Client Details tab: the facts the contract screens need, kept once on the lead
      var isBuy=RECTX?RECSIDE==='buyer':!!L.roles.buyer,cd=Object.assign({},L.client||{},RECCD),w0=L.wants||{};
      var hasCo=!!(cd.co_name&&String(cd.co_name).trim());
      function cdv(k,alt){return cd[k]!=null&&cd[k]!==""?cd[k]:(alt!=null?alt:"");}
      var pc=document.createElement("div");pc.dataset.pane="client";pc.hidden=true;
      pc.innerHTML='<p class="goal" style="margin:0 0 8px">Enter these once. They fill the buyer agreement, the offer and the transaction record for you.</p><div class="fgrid">'
        +'<div class="full"><label>Current mailing address (street, city, state, zip)</label><input id="cd_mailing" value="'+esc(cdv("mailing"))+'" placeholder="e.g. 123 Palm Ave, Trinity, FL 34655"></div>'
        +'<div class="full" id="cd_coslot">'+(hasCo?'':'<button type="button" class="btn sm ghost" id="cd_addco">+ Add a second '+(isBuy?'buyer':'seller')+'</button>')+'</div>'
        +'<div class="full" id="cd_cobox"'+(hasCo&&!RECTX?'':' hidden')+'><div class="fgrid"><div class="full"><label>'+(isBuy?"Second buyer":"Second seller")+'</label></div>'
        +'<div><label>Name</label><input id="cd_co_name" value="'+esc(cdv("co_name"))+'"></div><div><label>Phone</label><input id="cd_co_phone" inputmode="tel" value="'+esc(cdv("co_phone"))+'"></div><div class="full"><label>Email</label><input id="cd_co_email" type="email" value="'+esc(cdv("co_email"))+'"></div></div></div>'
        +(isBuy?'<div class="full">'+hwChips("fin",["Cash","Conventional","FHA/VA"],[].concat(cd.fin||w0.fin||[]).map(function(x){return x==="FHA"||x==="VA"?"FHA/VA":x;}),false)+'</div><div class="full"><label>Loan status</label>'+hwChips("pre",HW.pre,cd.pre||w0.pre,false)+'</div>'
          +'<div class="cdlend"><label>Lender company name</label><input id="cd_lender" value="'+esc(cdv("lender",w0.lender))+'"></div><div class="cdlend"><label>Lender representative</label><input id="cd_lender_rep" value="'+esc(cdv("lender_rep"))+'"></div><div class="cdlend"><label>Lender phone</label><input id="cd_lender_phone" inputmode="tel" value="'+esc(cdv("lender_phone"))+'"></div><div class="cdlend"><label>Lender email</label><input id="cd_lender_email" type="email" value="'+esc(cdv("lender_email"))+'"></div><div class="cdlend"><label>Pre-approved amount</label><input id="cd_preamt" inputmode="numeric" value="'+esc(cdv("preamt",w0.preamt))+'"></div>':'')
        +'</div><div class="btns"><button type="button" class="btn" id="cd_save">Save client details</button><span class="goal" id="cd_msg"></span></div>';
      var lf=pLead.querySelector(":scope > .fgrid"),lbt=pLead.querySelector(":scope > .btns"),lmsg=document.getElementById("m_msg"); // the contact fields (name, phone, email, source...) live here
      if(lf){var ch=document.createElement("h3");ch.style.margin="0 0 6px";ch.textContent="Contact";pc.insertBefore(lf,pc.firstChild);pc.insertBefore(ch,pc.firstChild);var ch2=document.createElement("h3");ch2.style.margin="14px 0 6px";ch2.textContent="Details for contracts";pc.insertBefore(ch2,lf.nextSibling);}
      if(lbt){pc.appendChild(lbt);if(lmsg)pc.appendChild(lmsg);}
      // Condensed summary card (like the MLS card in Make An Offer); the fields open with Edit
      var cdEd=document.createElement("div");cdEd.id="cd_edit";cdEd.style.display="none";while(pc.firstChild)cdEd.appendChild(pc.firstChild);
      var cdCard=document.createElement("div");cdCard.id="cd_card";cdCard.style.cssText="display:flex;justify-content:space-between;align-items:center;gap:12px;background:#f0f8f8;border:1px solid #cfe5e5;border-radius:10px;padding:12px 16px;font-size:.92rem";
      cdCard.innerHTML='<div id="cd_card_txt"></div><button type="button" class="btn" id="cd_editbtn" style="width:auto;padding:8px 18px;margin:0">Edit</button>';
      pc.appendChild(cdCard);pc.appendChild(cdEd);
      function cdArr(v){return Array.isArray(v)?v.join(", "):(v||"");}
      function cdCardRefresh(){var q=function(id){var e=document.getElementById(id)||pc.querySelector("#"+id);return e?e.value.trim():"";},nm=(q("f_first")+" "+q("f_last")).trim()||"Client",c1=[q("f_phone"),q("f_email"),q("f_source")?"Source: "+q("f_source"):""].filter(Boolean).join(" · ");
        var cur=[],ch=function(g){var c=pc.querySelector('.chips[data-grp="'+g+'"]');return c?[].map.call(c.querySelectorAll(".chip.on"),function(x){return x.dataset.v;}).join(", "):"";};
        var pay=[];if(isBuy&&cdHasPay()){var f=ch("fin"),p=ch("pre"),ld=q("cd_lender"),lr=q("cd_lender_rep"),lp=q("cd_lender_phone"),le=q("cd_lender_email"),am=q("cd_preamt");pay.push("Method of payment: "+(f||"not set"));if(p)pay.push("Loan: "+p);if(p==="Pre-approved"){var lc=[ld,lr,lp,le].filter(Boolean).join(" · ");if(lc)pay.push("Lender: "+lc);if(am)pay.push("Pre-approved for $"+am.replace(/^\$/,""));}}
        var ml=q("cd_mailing"),co=[q("cd_co_name"),q("cd_co_phone"),q("cd_co_email")].filter(Boolean).join(" · ");
        pc.querySelector("#cd_card_txt").innerHTML='<div style="font-size:1.05rem"><b>'+esc(nm)+'</b></div>'+(c1?'<div class="m" style="color:#5a6a6c;margin-top:2px">'+esc(c1)+'</div>':'')
          +(ml?'<div style="margin-top:6px"><b>Mailing:</b> '+esc(ml)+'</div>':'<div style="margin-top:6px;color:#8a6d1f">No mailing address yet. Click Edit to add the details for contracts.</div>')
          +(co?'<div style="margin-top:4px"><b>Second '+(isBuy?'buyer':'seller')+':</b> '+esc(co)+'</div>':'')+(pay.length?'<div style="margin-top:4px">'+esc(pay.join(" · "))+'</div>':'');}
      // Payment fields sit in their own section under the card; they stay open until payment details have been added
      var cdPay=document.createElement("div");cdPay.id="cd_pay";cdPay.style.cssText="margin-top:12px;display:none";
      if(isBuy){var finC=cdEd.querySelector('.chips[data-grp="fin"]'),preC=cdEd.querySelector('.chips[data-grp="pre"]');
        if(finC&&preC){var pg=document.createElement("div");pg.className="fgrid";pg.appendChild(finC.parentNode);pg.appendChild(preC.parentNode);[].slice.call(cdEd.querySelectorAll(".cdlend")).forEach(function(e){pg.appendChild(e);});
          var ph=document.createElement("h3");ph.style.margin="0 0 6px";ph.textContent="Payment";cdPay.appendChild(ph);cdPay.appendChild(pg);
          var pb=document.createElement("div");pb.className="btns";pb.innerHTML='<button type="button" class="btn" id="cd_paysave">Save payment details</button>';cdPay.appendChild(pb);}}
      pc.insertBefore(cdPay,cdEd);
      function cdHasPay(){if(!isBuy)return false;var on=pc.querySelector('.chips[data-grp="fin"] .chip.on,.chips[data-grp="pre"] .chip.on');if(on)return true;return ["cd_lender","cd_lender_rep","cd_lender_phone","cd_lender_email","cd_preamt"].some(function(i){var e=pc.querySelector("#"+i);return e&&e.value.trim();});}
      function cdLayout(editing){cdPay.style.display=(isBuy&&cdPay.firstChild&&(editing||!cdHasPay()))?"":"none";}
      function cdEditor(on){cdEd.style.display=on?"":"none";cdCard.style.display=on?"none":"flex";if(!on)cdCardRefresh();cdLayout(on);}
      cdCardRefresh();cdLayout(false);pc.querySelector("#cd_editbtn").onclick=function(){cdEditor(true);};
      modal.appendChild(pc);var cbt=document.createElement("button");cbt.type="button";cbt.dataset.p="client";cbt.textContent="Client Details";bar.insertBefore(cbt,bar.children[1]);
      var ac=pc.querySelector("#cd_addco");if(ac)ac.onclick=function(){pc.querySelector("#cd_cobox").hidden=false;ac.remove();var n=pc.querySelector("#cd_co_name");if(n)n.focus();};
      pc.querySelectorAll(".chips[data-grp] .chip").forEach(function(c){c.onclick=function(){var g=c.parentNode;if(!c.classList.contains("on"))g.querySelectorAll(".chip").forEach(function(x){x.classList.remove("on");});c.classList.toggle("on");};});
      var psv=pc.querySelector("#cd_paysave");if(psv)psv.onclick=function(){document.getElementById("cd_save").click();};
      function cdLendVis(){var pc2=pc.querySelector('.chips[data-grp="pre"] .chip.on'),on=pc2&&pc2.dataset.v==="Pre-approved";pc.querySelectorAll(".cdlend").forEach(function(e){e.style.display=on?"":"none";});}
      pc.querySelectorAll('.chips[data-grp="pre"] .chip').forEach(function(c){c.addEventListener("click",cdLendVis);});cdLendVis();
      document.getElementById("cd_save").onclick=async function(){var b=this,m=document.getElementById("cd_msg");b.disabled=true;var o={};
        ["mailing","co_name","co_phone","co_email","lender","lender_rep","lender_phone","lender_email","preamt"].forEach(function(k){var el=pc.querySelector("#cd_"+k);if(el)o[k]=el.value.trim();});
        pc.querySelectorAll(".chips[data-grp]").forEach(function(c){o[c.dataset.grp]=[].map.call(c.querySelectorAll(".chip.on"),function(x){return x.dataset.v;});});
        try{var rj=await api({action:"wants",field:"client",id:L.id,wants:o,tx:RECTX,side:RECSIDE});L.client=o;m.textContent=rj.note||"Saved";if(RECTX&&!rj.note)setTimeout(function(){try{parent.postMessage({flrcontact:1,saved:true},location.origin);}catch(e){}},500);cdEditor(false);if(!EMB)renderTabs();}catch(e){m.textContent=e.message;}b.disabled=false;};
    }
    if(preset&&preset.pane)showPane(preset.pane);
  }
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
    var tb=modal.querySelector(".mtabs");
    if(tb){var pr=document.createElement("div");pr.dataset.pane="refs";pr.hidden=true;rb.querySelector(".hd").innerHTML='<span>Referrals received from '+esc(nm(L))+'</span>';pr.appendChild(rb);
      var rc=document.createElement("div");rc.className="btns";rc.innerHTML='<button type="button" class="btn ghost">Close</button>';rc.firstChild.onclick=close;pr.appendChild(rc);modal.appendChild(pr);
      var rbtn=document.createElement("button");rbtn.type="button";rbtn.dataset.p="refs";rbtn.innerHTML='Referrals Received<span class="n">'+refd.length+'</span>';tb.appendChild(rbtn);}
    else{var mvb=document.getElementById("mvbox");mvb.parentNode.insertBefore(rb,mvb);}rb.querySelectorAll("[data-open]").forEach(function(a){a.onclick=function(e){e.preventDefault();reopen(a.dataset.open);};});}
  function refreshPills(box){var sum=modal.querySelector('.picksum[data-for="'+box+'"]'),sel=[];modal.querySelectorAll("#"+box+" .chip.on").forEach(function(c){sel.push(c.dataset.tag||c.dataset.int);});
    if(sum){sum.innerHTML=pills(sel,box,sum.dataset.label);bindOpen(sum);}}
  function bindOpen(root){root.querySelectorAll(".pickopen").forEach(function(b){b.onclick=function(){var w=modal.querySelector('.pickwrap[data-box="'+b.dataset.box+'"]');if(w)w.hidden=!w.hidden;};});}
  bindOpen(modal);
  modal.querySelectorAll("[data-done]").forEach(function(b){b.onclick=function(){modal.querySelector('.pickwrap[data-box="'+b.dataset.done+'"]').hidden=true;};});
  modal.querySelectorAll("#tagbox .chip, #intbox .chip").forEach(function(c){c.addEventListener("click",function(){setTimeout(function(){refreshPills(c.parentNode.id);},0);});});
  modal.querySelectorAll(".opensrc").forEach(function(os){os.onclick=function(e){e.preventDefault();openSources();};});
  modal.querySelectorAll("#tagbox .chip").forEach(function(b){b.onclick=async function(){b.classList.toggle("on");if(isNew)return;
    try{await api({action:"save",id:L.id,lead:{first:val("f_first"),last:val("f_last"),phone:val("f_phone"),email:val("f_email"),source:val("f_source"),notes:val("f_notes")},roles:readRoles(),tags:readTags()});render();}catch(e){b.classList.toggle("on");say(e.message,true);}};});
  var sideMode="";if(modal.querySelector("#mvside")){var mb=document.getElementById("mvbox");if(mb)mb.classList.add("mvnew");}
  modal.querySelectorAll(".mvside").forEach(function(b){b.onclick=function(){var sd=b.dataset.side;sideMode=sd;
    modal.querySelectorAll(".mvside").forEach(function(x){x.classList.toggle("on",x===b);x.style.background=x===b?"#d32f2f":"#fff";x.style.color=x===b?"#fff":"#b71c1c";});
    modal.querySelectorAll(".mvh").forEach(function(w){var show=sd==="both"||w.dataset.role===sd;w.hidden=!show;if(!show){var r=w.querySelector(".mvrow");if(r){r.dataset.val="";r.querySelectorAll(".mvb").forEach(function(x){x.classList.remove("on");});}}});
    var tip=modal.querySelector(".mvtip");if(tip)tip.textContent=sd==="both"?"Tap how soon for buying and for selling. Each saves right away.":"Tap how soon. The lead leaves NEW right away.";};});
  var sphereMove=false;
  modal.querySelectorAll(".mvx").forEach(function(b){b.onclick=function(ev){if(ev)ev.preventDefault();var w=modal.querySelector('.mvh[data-role="'+b.dataset.role+'"]');if(w)w.hidden=false;
    if(b.dataset.mode==="move"){sphereMove=true;modal.querySelector(".mvtip").textContent="Pick the Sphere category. The lead leaves Buyers and Sellers and starts the Sphere plan.";}
    else{modal.querySelector(".mvtip").textContent="Pick the "+(b.dataset.role==="seller"?"seller":"buyer")+" stage. Both plans then run side by side.";}
    b.remove();};});
  modal.querySelectorAll(".mvrow").forEach(function(r){r.addEventListener("click",async function(e){var b=e.target.closest(".mvb");if(!b||b.classList.contains("on")||b.classList.contains("mvside"))return;
    if(b.dataset.act==="execute"){location.href="buyer.php?new=1&auto=1&lead="+encodeURIComponent(L.id);return;}
    if(b.dataset.act==="prelist"){var pp=modal.querySelector(".mvpre");if(pp){pp.hidden=false;var mbx0=document.getElementById("mvbox");if(mbx0)mbx0.style.display="";var ia=document.getElementById("pre_addr");ia.focus();}return;}
    var prev=r.dataset.val;r.dataset.val=b.dataset.v;r.querySelectorAll(".mvb").forEach(function(x){x.classList.toggle("on",x===b);});
    if(sideMode==="both"){var oth=r.dataset.role==="buyer"?"seller":"buyer",orow=modal.querySelector('.mvh[data-role="'+oth+'"] .mvrow');
      if(orow&&!orow.dataset.val){var tp=modal.querySelector(".mvtip");if(tp)tp.textContent="Now tap how soon for "+(oth==="seller"?"selling":"buying")+".";return;}}
    if(sphereMove&&r.dataset.role==="sphere"){modal.querySelectorAll('.mvrow[data-role="buyer"],.mvrow[data-role="seller"]').forEach(function(o){if(o.dataset.val!=="client")o.dataset.val="";});}
    if(isNew)return;
    try{await api({action:"save",id:L.id,lead:{first:val("f_first"),last:val("f_last"),phone:val("f_phone"),email:val("f_email"),source:val("f_source"),notes:val("f_notes")},roles:readRoles()});var lbl=CATS[r.dataset.role].label+" · "+(b.dataset.v?CATS[r.dataset.role].subs[b.dataset.v]:"none");reopen(L.id);renderTabs();var rr=document.querySelector('#mvbox .mvrow[data-role="'+r.dataset.role+'"]'),pv=rr&&rr.previousElementSibling,hh=(pv&&pv.tagName==="H3")?pv:document.querySelector("#mvbox h3"),sv='<span style="font-weight:500;color:#2e8b57;font-size:.85rem"> Saved: '+esc(lbl)+'</span>';if(hh){hh.insertAdjacentHTML("beforeend",sv);}else{var mbx=document.getElementById("mvbox");if(mbx)mbx.insertAdjacentHTML("afterbegin",'<div style="margin:0 0 6px">'+sv+'</div>');}}
    catch(err){r.dataset.val=prev;r.querySelectorAll(".mvb").forEach(function(x){x.classList.toggle("on",x.dataset.v===prev);});say(err.message,true);}
  });});
  var msg=document.getElementById("m_msg");function say(t,bad){msg.textContent=t;msg.className=bad?"note sigerr":"note";msg.hidden=false;}
  document.getElementById("m_close").onclick=close;
  document.getElementById("m_save").onclick=async function(){var b=this;b.disabled=true;
    var roles=readRoles(),any=Object.keys(roles).some(function(k){return roles[k];});
    if(isNew&&!any){say(inl?"Tap a category for this lead (for example Ready, Soon or Later).":"Choose Buyer, Seller or Sphere for this lead.",true);b.disabled=false;return;}
    var tg=modal.querySelector("#tagbox")?readTags():undefined;if(isNew&&tg&&!tg.length&&val("f_source"))tg=undefined;
    try{await api({action:"save",id:isNew?"":L.id,lead:{first:val("f_first"),last:val("f_last"),phone:val("f_phone"),email:val("f_email"),source:val("f_source"),notes:val("f_notes")},roles:roles,tags:tg,interests:readInts(),ref:(function(){var r=readRef(L);return r===null?(L.ref||""):r;})()});if(inl){var nn=(val("f_first")+" "+val("f_last")).trim()||"the lead";var where=Object.keys(roles).filter(function(k){return roles[k];}).map(function(k){return CATS[k].label+" · "+CATS[k].subs[roles[k]];}).join(" and ");view.flash="Added "+nn+(where?" to "+where:"")+".";}close();if(!inl)render();}
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
function readRoles(){var o={};modal.querySelectorAll(".mvrow").forEach(function(r){var v=r.dataset.val||"";o[r.dataset.role]=v==="-"?"":v;});return o;}
function val(id){return document.getElementById(id).value.trim();}
function close(){mback.classList.remove("show");if(modal!==MODAL){modal=MODAL;render();return;}MODAL.innerHTML="";var ia=document.getElementById("inlAdd");if(ia)modal=ia;} // a popup over the inline add form hands control back to it
mback.addEventListener("click",function(e){if(e.target===mback)close();});
document.addEventListener("keydown",function(e){if(e.key==="Escape")close();});
render();
var op=new URLSearchParams(location.search).get("open");if(op){var oL=LEADS.filter(function(x){return x.id===op;})[0];if(oL){view.tab=["buyer","seller","sphere"].filter(function(k){return oL.roles&&oL.roles[k];})[0]||"new";if(view.tab!=="new")view.sub=oL.roles[view.tab].sub;render();openLead(oL,EMB?{pane:new URLSearchParams(location.search).get("pane")||"notes"}:undefined);
  if(EMB){render=function(){};var tell=function(){try{parent.postMessage({flrpane:op,pane:new URLSearchParams(location.search).get("pane"),h:Math.ceil((document.querySelector('.modal.inl')||document.body).getBoundingClientRect().height)+4},location.origin);}catch(e){}};
    tell();try{new ResizeObserver(tell).observe(document.querySelector('.modal.inl')||document.body);}catch(e){setInterval(tell,600);}}}}
})();
</script>
<script>try{fetch("tx_sync.php",{credentials:"same-origin"}).catch(function(){});}catch(e){}</script>
</body>
</html>
