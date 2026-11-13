<?php
require __DIR__ . "/guard.php";
require_once __DIR__ . "/sigtools.php";
require_once __DIR__ . "/offerlib.php";
@set_time_limit(120);
header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
header('X-Accel-Buffering: no'); while (ob_get_level() > 0) { @ob_end_flush(); }
$emb = !empty($_GET['embed']); // shown inside the Make An Offer panel on a buyer record: no site header or footer
$fullUrl = 'wizard.php' . (!empty($_GET['tx']) ? '?tx=' . urlencode((string)$_GET['tx']) : '');
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
<title>Make A Contract Offer - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=17">
<?php if ($emb): ?><base target="_top"><style>body{margin:0;background:#fff}.embedwrap{padding:14px}.embedwrap .wrap{padding:0;max-width:none}</style><?php endif; ?>
</head>
<body>
<?php if (!$emb): ?>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Virtual Broker Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php" aria-current="page">Active Business</a>
    <a href="performance.php">Performance</a>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>
<?php // This page turns off output buffering (to show its loading screen at once), so guard.php can't add the
      // Notifications / Messages / Settings icons for it; print them here directly, in the usual spot.
?><section class="hero hashi"><?= flr_header_icons($_SESSION['email'] ?? '') ?><h1>Make A Contract Offer</h1><p>Buyer contract packet: answer the questions and the right forms are merged for you.</p></section>
<main class="wrap">
<?php else: ?>
<div class="embedwrap"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px"><h2 style="margin:0;font-size:1.1rem">Make A Contract Offer</h2><a href="<?= htmlspecialchars($fullUrl) ?>" target="_top" style="font-size:.82rem;white-space:nowrap">Open full page &#8599;</a></div>
<main class="wrap">
<?php endif; ?>
<style>#flrload{display:flex;align-items:center;gap:14px;padding:22px 4px;color:#006b68;font-weight:600}#flrload i{width:26px;height:26px;border:4px solid #cfe3e2;border-top-color:#006b68;border-radius:50%;animation:flrspin .8s linear infinite}@keyframes flrspin{to{transform:rotate(360deg)}}</style>
<div id="flrload" role="status"><i></i><span>Opening Make A Contract Offer...</span></div>
<?php echo str_repeat(' ', 1024); @flush();
$rules = sg_wizard_rules();
$all = sg_folders($_SESSION['email'], (string)($_GET['tx'] ?? ''));
$folders = [];
foreach (($all ?: []) as $f) { if ($f['group'] === '1 BUYERS / 1 Buyers') $folders[] = $f; }
$pre = (string)($_GET['tx'] ?? '');
?>
<script>var l=document.getElementById('flrload');if(l)l.parentNode.removeChild(l);</script>
<?php if (!$rules): ?>
  <p class="note">The wizard rules could not be loaded right now. Please try again, or contact the office.<?php if (!empty($GLOBALS['sg_wizard_note'])): ?><br><small>Details: <?= htmlspecialchars($GLOBALS['sg_wizard_note']) ?></small><?php endif; ?></p>
<?php elseif ($all === null): ?>
  <p class="note">Your transaction folders could not be loaded: <?= htmlspecialchars($GLOBALS['sg_folders_note'] ?? '') ?>. Please tell the office.</p>
<?php elseif (!$folders): ?>
  <p class="note">You need a buyer folder first. Create a folder named for the buyer(s) inside <strong>Active Business &rsaquo; 1 BUYERS &rsaquo; 1 Buyers</strong>, then come back to this page.</p>
  <p class="note">Looked in: <?= htmlspecialchars($GLOBALS['sg_folders_root'] ?? '') ?>. Folders found: <?= count($all) ?><?= $all ? ' (' . htmlspecialchars(implode('; ', array_unique(array_map(function ($f) { return $f['group']; }, $all)))) . ')' : '' ?>.</p>
<?php else: ?>
<?php $preOk = false; foreach ($folders as $f) { if ($pre !== '' && $f['id'] === $pre) $preOk = true; }
      if ($emb && $preOk): // inside a client record: the buyer and their info are already shown above, so the picker and summary stay hidden (still in the page so the wizard can read them) ?>
  <style>#wz>p:first-child,#bsum{display:none!important}</style>
<?php endif; ?>
  <div class="panel sigbox wz" id="wz">
    <p><label for="tx"><strong>Which buyer is this for?</strong></label><br>
    <select id="tx" class="txsel">
      <option value="">Choose a buyer...</option>
<?php $g = ''; foreach ($folders as $f): if ($f['group'] !== $g): if ($g !== '') echo "      </optgroup>\n"; $g = $f['group']; echo '      <optgroup label="' . htmlspecialchars($g) . "\">\n"; endif; ?>
      <option value="<?= htmlspecialchars($f['id']) ?>"<?= $pre === $f['id'] ? ' selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
<?php endforeach; if ($g !== '') echo "      </optgroup>\n"; ?>
    </select></p>
    <div id="bsum" class="wsum"></div>
    <style>
    .od{margin:0 0 16px}.od h3{margin:14px 0 8px;font-size:1rem;color:#036f72;display:flex;align-items:center;gap:10px}.od h3:after{content:"";flex:1;height:1px;background:#dfe6e7}
    .odg{display:grid;grid-template-columns:repeat(12,1fr);gap:10px}.odg label{display:block;font-size:.82rem;font-weight:600;color:#455355;margin:0}.odg .s2{grid-column:span 2}.odg .s3{grid-column:span 3}.odg .s4{grid-column:span 4}.odg .s6{grid-column:span 6}.odg .s12{grid-column:span 12}
    .odg input,.odg select{display:block;width:100%;max-width:none!important;box-sizing:border-box;margin-top:3px;padding:9px 11px;border:1px solid #d7e2e2;border-radius:9px;font:inherit;font-weight:400;font-size:.95rem;background:#fff;color:#1f2a2c}
    .odb{background:#f0f8f8;border:1px solid #cfe5e5;border-radius:10px;padding:10px 14px;font-size:.92rem}.odb b{font-weight:700}.odb .m{color:#5a6a6c;font-size:.84rem;margin-top:2px}
    @media(max-width:640px){.odg .s2,.odg .s3,.odg .s4{grid-column:span 6}.odg .s6{grid-column:span 12}}
    </style>
    <div class="od" id="od">
      <h3>Property</h3>
      <div class="odg">
        <label class="s4"><strong>MLS number</strong><input type="text" id="of_mls_id" autocomplete="off" data-lpignore="true" data-form-type="other" placeholder="e.g. T3512345" style="text-transform:uppercase"></label>
        <div class="s2" style="align-self:end"><button type="button" class="btn" id="mls_go" style="width:100%;padding:9px 6px;margin-bottom:0">Look up</button></div>
        <div class="s6 odb" id="mls_note" style="align-self:end" hidden></div>
        <div class="s12 odb" id="mls_card" hidden style="display:none;justify-content:space-between;align-items:center;gap:12px"><div id="mls_card_txt"></div><button type="button" class="btn" id="mls_edit" style="width:auto;padding:8px 18px;margin:0">Edit</button></div>
        <label class="s3">Street number<input type="text" id="of_street_number" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s6">Street name<input type="text" id="of_street_name" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s3">Unit #<input type="text" id="of_unit" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s6">City<input type="text" id="of_city" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s2">State<input type="text" id="of_state" maxlength="2" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">Zip code<input type="text" id="of_zip" inputmode="numeric" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">Year built<input type="text" id="of_year_built" inputmode="numeric" maxlength="4" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">Annual taxes<input type="text" id="of_annual_taxes" inputmode="decimal" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">Property type<select id="of_property_type"><option value="">Select</option><?php foreach (OF_PROP_TYPES as $pt): ?><option><?= htmlspecialchars($pt) ?></option><?php endforeach; ?></select></label>
        <label class="s4"><strong>Offer Price</strong><input type="text" id="pprice" inputmode="decimal" autocomplete="off" data-lpignore="true" data-form-type="other" placeholder="$ 450,000"></label>
        <label class="s4">Closing date<input type="date" id="of_closing_date"></label>
        <label class="s4"><strong>Escrow Deposit Amount</strong><input type="text" id="of_escrow_deposit" inputmode="decimal" autocomplete="off" data-lpignore="true" data-form-type="other" placeholder="$ 5,000"></label>
        <label class="s4" id="of_fin_wrap" style="display:none"><strong>Amount Being Financed</strong><input type="text" id="of_financed_amount" inputmode="decimal" autocomplete="off" data-lpignore="true" data-form-type="other" placeholder="$ 360,000"></label>
        <label class="s4"><strong>Number Of Days For Inspections</strong><input type="text" id="of_inspection_days" inputmode="numeric" maxlength="3" autocomplete="off" data-lpignore="true" data-form-type="other" placeholder="e.g. 15"></label>
      </div>
      <input type="hidden" id="paddr">
      <h3 id="oa_head">Other agent</h3>
      <div class="odg" id="oa_grid">
        <label class="s12" style="font-weight:700;color:#036f72">Other agent (listing side, if known)</label>
        <label class="s4">First name<input type="text" id="of_oa_first" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">Last name<input type="text" id="of_oa_last" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">Brokerage<input type="text" id="of_oa_brokerage" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s6">Email<input type="text" id="of_oa_email" inputmode="email" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
        <label class="s6">Phone<input type="text" id="of_oa_phone" inputmode="tel" autocomplete="off" data-lpignore="true" data-form-type="other"></label>
      </div>
      <div class="odb" id="of_comp" style="margin-top:10px" hidden></div>
    </div>
    <div id="qs"></div>
    <fieldset class="wq" id="xf"><legend>Add Other Contingency Forms to the Package</legend>
      <label class="wr"><input type="checkbox" class="xfc" value="Realtor_Res_Sale_of_Buyers_Property.pdf"> Sale of Buyer&rsquo;s Property (V)</label>
      <label class="wr"><input type="checkbox" class="xfc" value="Realtor_Res_Housing_for_Older_Persons.pdf"> Housing for Older Persons (Q)</label>
      <label class="wr"><input type="checkbox" class="xfc" value="Realtor_Res_Licensee_Personal_Interest_in_Property.pdf"> Licensee Personal Interest (AA)</label>
      <label class="wr" id="xf_F" style="display:none"><input type="checkbox" class="xfc" value="Realtor_Res_Appraisal_Contingency.pdf"> Appraisal (F) &mdash; cash buyer only</label>
    </fieldset>
    <p class="sgerr" id="err" hidden></p>
    <p><button type="button" class="btn" id="build">Build contract packet</button></p>
    <div id="res" hidden></div>
  </div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script src="flrdraft.js?v=1"></script>
<script src="wizlib.js?v=12"></script>
<script>
(function(){
var R=<?= json_encode($rules, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, CSRF=<?= json_encode($_SESSION['csrf']) ?>, AGENT=<?= json_encode(trim((string)($_SESSION['name'] ?? '')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, BROKERAGE='Florida Luxury Realty, Inc.', LICENSE=<?= json_encode(sg_license($_SESSION['email'])) ?>;
var qs=document.getElementById('qs'), err=document.getElementById('err'), res=document.getElementById('res'), btn=document.getElementById('build'), txSel=document.getElementById('tx');
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function title(f){return f.replace(/\.pdf$/i,'').replace(/^(Realtor|Broker)_Res_/,'').replace(/_/g,' ');}
// Group the rule rows by question; hide questions the broker skipped.
var Q=[],byQ={};
R.rules.forEach(function(r){ if(!byQ[r.q]){byQ[r.q]={q:r.q,question:r.question,rows:[]};Q.push(byQ[r.q]);} byQ[r.q].rows.push(r); });
Q=Q.filter(function(x){return !x.rows.every(function(r){return r.skip;});});
function followFor(q,answer){for(var i=0;i<R.follow.length;i++){if(R.follow[i].q===q&&R.follow[i].answer.toLowerCase()===answer.toLowerCase())return R.follow[i];}return null;}
var h='';
Q.forEach(function(x){
  // Seller concessions: instead of Yes / No, two amount fields (default 0); anything above 0 adds the clause, 0 in both leaves it out.
  x.conc=/concession/i.test(x.question)&&x.rows.some(function(r){return !r.skip&&followFor(x.q,r.answer);});
  // Buyer broker compensation: amount fields (default 0) plus who pays, instead of three Yes / No answers.
  x.comp=/buyer broker compensation/i.test(x.question)&&x.rows.filter(function(r){return !r.skip&&/^yes/i.test(r.answer);}).length>=1;
  if(x.comp){var ys=x.rows.filter(function(r){return !r.skip&&/^yes/i.test(r.answer);});
    h+='<fieldset class="wq"><legend>'+esc(x.question)+'</legend><div id="cp'+x.q+'">Percent of Offer Price <input type="text" inputmode="decimal" size="4" class="wp" value="0"> % &nbsp;<strong>OR</strong>&nbsp; Flat Amount $ <input type="text" inputmode="decimal" size="8" class="wa" value="0">';
    ys.forEach(function(r,i){h+='<label class="wr"><input type="radio" name="cpw'+x.q+'" value="'+x.rows.indexOf(r)+'"'+(i===0?' checked':'')+'> '+esc(r.answer.replace(/^yes\s*[-\u2013\u2014:]?\s*/i,''))+'</label>';});
    h+='</div></fieldset>';return;}
  if(x.conc){h+='<fieldset class="wq"><legend>'+esc(x.question)+'</legend><div id="cq'+x.q+'">Percent of Offer Price <input type="text" inputmode="decimal" size="4" class="wp" value="0"> % &nbsp;<strong>OR</strong>&nbsp; Flat Amount $ <input type="text" inputmode="decimal" size="8" class="wa" value="0"></div></fieldset>';return;}
  h+='<fieldset class="wq"><legend>'+esc(x.question)+'</legend>';
  x.rows.forEach(function(r,i){
    if(r.skip)return;
    h+='<label class="wr"><input type="radio" name="q'+x.q+'" value="'+i+'"> '+esc(r.answer)+'</label>';
    if(followFor(x.q,r.answer)){h+='<div class="wf" id="f'+x.q+'_'+i+'" hidden>Percent of Purchase Price <input type="text" inputmode="decimal" size="4" class="wp"> % &nbsp;<strong>OR</strong>&nbsp; Flat Amount $ <input type="text" inputmode="decimal" size="8" class="wa"></div>';}
  });
  h+='</fieldset>';
});
qs.innerHTML=h;
qs.addEventListener('change',function(){ [].forEach.call(qs.querySelectorAll('.wf'),function(d){var m=/^f(\w+)_(\d+)$/.exec(d.id);var c=qs.querySelector('input[name="q'+m[1]+'"]:checked');d.hidden=!(c&&c.value===m[2]);}); });
var INFO=null, bsum=document.getElementById('bsum');
function fillMap(){
  var m={BuyerAgent_Name:AGENT,BuyerBroker_Name:BROKERAGE,Agent_Name:AGENT};if(LICENSE)m.Agent_License=LICENSE;var pa=(document.getElementById('paddr')||{}).value; if(pa&&pa.trim())m.Property_Address=pa.trim(); var pp=String((document.getElementById('pprice')||{}).value||'').replace(/[^0-9.]/g,''); if(pp&&!isNaN(Number(pp)))m.Price_Purchase=Number(pp).toLocaleString('en-US',{maximumFractionDigits:2}); // the agent and brokerage are the cooperating agent and broker on the forms
  if(INFO){var names=[INFO.b1_name,INFO.b2_name].filter(Boolean).join(' and '); if(names)m.Buyer_Names=names; if(INFO.b1_name)m.Buyer1_Name=INFO.b1_name; if(INFO.b2_name)m.Buyer2_Name=INFO.b2_name;} // buyer contact details are never written onto the forms
  return m;
}
function loadInfo(){
  INFO=null;bsum.innerHTML='';var fw0=document.getElementById('of_fin_wrap');if(fw0)fw0.style.display='none';var xF0=document.getElementById('xf_F');if(xF0){xF0.style.display='none';xF0.querySelector('input').checked=false;} // Appraisal (F) is offered to cash buyers only
  if(!txSel.value)return;
  var id=txSel.value;
  bsum.innerHTML='<p class="note" style="display:flex;align-items:center;gap:10px"><i style="width:16px;height:16px;border:3px solid #cfe3e2;border-top-color:#006b68;border-radius:50%;animation:flrspin .8s linear infinite;display:inline-block"></i>Loading this buyer\'s saved info...</p>';
  fetch('wizard_info.php?tx='+encodeURIComponent(id),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
    if(txSel.value!==id)return;
    INFO=(j&&j.ok&&j.info&&Object.keys(j.info).length)?j.info:null;
    var edit='<a href="buyer.php?tx='+encodeURIComponent(id)+'">'+(INFO?'Edit buyer info':'Add buyer info')+'</a>';
    if(!INFO){bsum.innerHTML='<p class="note">No buyer info saved yet. '+edit+' to fill in names, contact details and payment method automatically.</p>';return;}
    var pm={cash:'Cash',conventional:'Conventional financing',fhava:'FHA / VA financing'}[INFO.payment]||'';
    var nm=[INFO.b1_name,INFO.b2_name].filter(Boolean).join(' and ');
    bsum.innerHTML='<p class="note">Using saved buyer info: <strong>'+esc(nm||'(no name)')+'</strong>'+(pm?' &middot; '+esc(pm):'')+'. '+edit+'</p>';
    var fw=document.getElementById('of_fin_wrap');if(fw)fw.style.display=(INFO.payment==='conventional'||INFO.payment==='fhava')?'':'none'; // Amount Being Financed: financing buyers only
    var xF=document.getElementById('xf_F');if(xF&&INFO.payment==='cash')xF.style.display='';
    var re={conventional:/^conventional/i,fhava:/^fha/i}[INFO.payment];
    if(re){Q.forEach(function(x){x.rows.forEach(function(r,i){if(re.test(r.answer)){var el=qs.querySelector('input[name="q'+x.q+'"][value="'+i+'"]');if(el){el.checked=true;var fs=el.closest('fieldset');if(fs)fs.hidden=true;}}});});}
    else if(INFO.payment==='cash'){ // cash: the question is not asked. The "without" choice is used and the Appraisal (F) checkbox below adds the appraisal form.
      Q.forEach(function(x){x.rows.forEach(function(r,k){if(r.skip||!/^cash/i.test(r.answer)||!/without/i.test(r.answer))return;var el=qs.querySelector('input[name="q'+x.q+'"][value="'+k+'"]');if(el){el.checked=true;var fs=el.closest('fieldset');if(fs)fs.hidden=true;}});});}
  }).catch(function(){});
}
txSel.addEventListener('change',loadInfo);
loadInfo();
// Offer Details: the property and other-side agent, prefilled from the record and saved back to it so nothing is typed twice.
var OFK=['escrow_deposit','financed_amount','inspection_days','street_number','street_name','unit','city','state','zip','mls_id','year_built','annual_taxes','closing_date','property_type','oa_first','oa_last','oa_brokerage','oa_email','oa_phone'];
function ofEl(k){return document.getElementById('of_'+k);}
function ofAddr(){var g=function(k){return (ofEl(k).value||'').replace(/\s+/g,' ').trim();};var st=(g('street_number')+' '+g('street_name')).trim()+(g('unit')?' Unit '+g('unit'):'');var tail=g('city')+((g('state')+' '+g('zip')).trim()?', '+(g('state')+' '+g('zip')).trim():'');return (st+(st&&tail?', ':'')+tail).trim();}
function ofSync(){document.getElementById('paddr').value=ofAddr();}
function ofCompText(c){if(!c||!c.agreement)return '';var p=[];function f(k){return c[k]||'';}
  if(f('Comp_Purchase_Percent'))p.push(f('Comp_Purchase_Percent')+'% of the purchase price');if(f('Comp_Purchase_Amount'))p.push('$'+f('Comp_Purchase_Amount'));if(f('Comp_PurchasePlus_FlatFee'))p.push(f('Comp_PurchasePlus_FlatFee'));
  return '<b>Your commission (from the '+(c.agreement==='showing'?'Showing':'Exclusive Buyer')+' Agreement):</b> '+(p.length?esc(p.join(' + ')):'on file')+'<div class="m">Used for the transaction record. Change it on the Buyer Agreement if it differs.</div>';}
function ofLoad(){var id=txSel.value;if(!id)return;
  fetch('offer_api.php?tx='+encodeURIComponent(id),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
    if(!j||!j.ok||txSel.value!==id)return;
    OFK.forEach(function(k){ofEl(k).value=j.offer[k]||'';});document.getElementById('pprice').value=j.offer.price?Number(j.offer.price).toLocaleString('en-US',{maximumFractionDigits:2}):'';
    var b=j.buyer,nm=[b.b1_name,b.b2_name].filter(Boolean).join(' and ');
    var _bd=document.getElementById('of_buyer'); if(_bd) _bd.innerHTML=nm?'<b>'+esc(nm)+'</b>'+(b.b1_phone||b.b1_email?'<div class="m">'+esc([b.b1_phone,b.b1_email].filter(Boolean).join(' · '))+'</div>':'')+(b.b2_name&&(b.b2_phone||b.b2_email)?'<div class="m">'+esc(b.b2_name+': '+[b.b2_phone,b.b2_email].filter(Boolean).join(' · '))+'</div>':''):'No buyer name saved yet. Use Edit contact info on the client record above.';
    var _me=document.getElementById('of_me'); if(_me) _me.textContent=[j.agent.name,j.agent.license?'License '+j.agent.license:'',j.agent.email].filter(Boolean).join(' · ');
    var ct=ofCompText(j.comp),ce=document.getElementById('of_comp');ce.innerHTML=ct;ce.hidden=!ct;ofSync();
  }).catch(function(){});}
OFK.forEach(function(k){ofEl(k).addEventListener('input',ofSync);});
// MLS number first: typing it (then Look up / Enter / leaving the field) fills the rest of the property and the listing agent from the MLS.
var mlsBox=document.getElementById('mls_note'),mlsGo=document.getElementById('mls_go'),mlsLast='';
function mlsSay(h,bad){mlsBox.hidden=!h;mlsBox.innerHTML=h||'';mlsBox.style.color=bad?'#b3261e':'';}
async function mlsLookup(force){var v=(ofEl('mls_id').value||'').trim().toUpperCase();ofEl('mls_id').value=v;if(!v){mlsSay('');return;}if(!force&&v===mlsLast)return;mlsLast=v;mlsGo.disabled=true;mlsGo.textContent='Looking up...';
  try{var j=await fetch('mls_lookup_api.php?mls='+encodeURIComponent(v),{credentials:'same-origin'}).then(function(r){return r.json();});
    if(!j.ok){mlsSay(esc(j.msg||'Could not look up that MLS number.'),true);mlsShowFields(true);[].forEach.call(qs.querySelectorAll('fieldset.mlsq'),function(f){f.hidden=false;f.classList.remove('mlsq');});}
    else{Object.keys(j.fields).forEach(function(k){if(ofEl(k)&&j.fields[k]!=='')ofEl(k).value=j.fields[k];if(ofEl(k))ofEl(k).dispatchEvent(new Event('input'));});ofSync();
      var lp=j.list_price?'$'+Number(j.list_price).toLocaleString('en-US'):'';
      mlsSay('<b>Filled from MLS '+esc(v)+'</b>'+(j.status?' ('+esc(j.status)+')':'')+'. Please check the details.'+(lp?'<div class="m">List price '+lp+' <a href="#" id="mls_lp">Use as offer price</a></div>':''));
      var a=document.getElementById('mls_lp');if(a)a.onclick=function(e){e.preventDefault();var pp=document.getElementById('pprice');pp.value=Number(j.list_price).toLocaleString('en-US',{maximumFractionDigits:2});pp.dispatchEvent(new Event('input'));};MLSX=j.fields||{};mlsCondense();mlsAnswers(j.fields);}
  }catch(e){mlsSay('Could not look up that MLS number. Please try again.',true);}
  mlsGo.disabled=false;mlsGo.textContent='Look up';}
// Questions the MLS record can answer are pre-selected (the agent can still change them); with no MLS record they stay for the agent to answer.
function mlsPick(qre,are){Q.forEach(function(x){if(!qre.test(x.question))return;x.rows.forEach(function(r,i){if(r.skip||!are.test(r.answer))return;var el=qs.querySelector('input[name="q'+x.q+'"][value="'+i+'"]');if(el){el.checked=true;el.dispatchEvent(new Event('change',{bubbles:true}));var fs=el.closest('fieldset');if(fs){fs.classList.add('mlsq');fs.hidden=true;}}});});}
var MLSX={};
function mlsAnswers(x){if(x)MLSX=x;if(!(ofEl('mls_id').value||'').trim())return;var pt=ofEl('property_type').value,yb=parseInt(ofEl('year_built').value,10);
  var pm={'Residential':/residential|single|house|home/i,'Condominium':/condo/i,'Vacant Land':/vacant|land/i}[pt];
  if(pm)mlsPick(/property type/i,pm);
  if(yb>0)mlsPick(/1978|lead/i,yb<1978?/^yes/i:/^no/i);
  // HOA / CDD from the MLS record; stays blank when the MLS does not say.
  var h=MLSX.hoa,c=MLSX.cdd,yes=/^yes/i,no=/^no/i;
  mlsPick(/(\bhoa\b|homeowner|association)(?!.*(\bcdd\b|community development))/i,h==='yes'?yes:h==='no'?no:/^$/);
  mlsPick(/(\bcdd\b|community development)(?!.*(\bhoa\b|homeowner|association))/i,c==='yes'?yes:c==='no'?no:/^$/);
  if(h&&c){var both=h==='yes'&&c==='yes',onlyH=h==='yes'&&c==='no',onlyC=h==='no'&&c==='yes';
    mlsPick(/(\bhoa\b|homeowner|association).*(\bcdd\b|community development)|(\bcdd\b|community development).*(\bhoa\b|homeowner|association)/i,both?/both|hoa.*cdd|cdd.*hoa/i:onlyH?/^(?!.*cdd).*hoa/i:onlyC?/^(?!.*hoa).*cdd/i:/neither|^none|^no\b/i);}}
ofEl('property_type').addEventListener('change',function(){mlsAnswers();});
var MLSF=['street_number','street_name','unit','city','state','zip','year_built','annual_taxes','property_type'];
function mlsShowFields(on,keepAgent){var oa=['oa_first','oa_last','oa_brokerage','oa_email','oa_phone'].some(function(k){return (ofEl(k).value||'').trim();});['oa_head','oa_grid'].forEach(function(i){document.getElementById(i).style.display=(on||!oa)?'':'none';});MLSF.forEach(function(k){var l=ofEl(k).closest('label');if(l)l.style.display=on?'':'none';});document.getElementById('mls_card').hidden=on;document.getElementById('mls_card').style.display=on?'none':'flex';}
function yn(v){return v==='yes'?'Yes':v==='no'?'No':'not in MLS data';}
function mlsCondense(){var g=function(k){return (ofEl(k).value||'').trim();};if(!g('mls_id')||!g('street_name')||!g('city'))return;
  var addr=[[g('street_number'),g('street_name')].filter(Boolean).join(' ')+(g('unit')?' #'+g('unit'):''),g('city'),[g('state'),g('zip')].filter(Boolean).join(' ')].filter(Boolean).join(', ');
  var tx=g('annual_taxes'),sub=[g('year_built')?'Year built '+g('year_built'):'',tx?'Annual taxes $'+tx:'',g('property_type'),'HOA: '+yn(MLSX.hoa),'CDD: '+yn(MLSX.cdd)].filter(Boolean).join(' · ');
  var ag=[[g('oa_first'),g('oa_last')].filter(Boolean).join(' '),g('oa_brokerage'),g('oa_phone'),g('oa_email')].filter(Boolean).join(' · ');
  document.getElementById('mls_card_txt').innerHTML='<b>'+esc(addr)+'</b>'+(sub?'<div class="m">'+esc(sub)+'</div>':'')+(ag?'<div class="m" style="margin-top:6px"><b>Listing agent:</b> '+esc(ag)+'</div>':'');mlsShowFields(false);}
document.getElementById('mls_edit').addEventListener('click',function(){mlsShowFields(true);});
mlsGo.addEventListener('click',function(){mlsLookup(true);});
ofEl('mls_id').addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();mlsLookup(true);}});
ofEl('mls_id').addEventListener('change',function(){mlsLookup(false);});
txSel.addEventListener('change',ofLoad);ofLoad();
async function ofSave(){ofSync();var o={};OFK.forEach(function(k){o[k]=ofEl(k).value;});o.price=document.getElementById('pprice').value;
  var r=await fetch('offer_api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:CSRF,action:'save',tx:txSel.value,offer:o})}).then(function(x){return x.json();}).catch(function(){return {ok:false,msg:'Could not save the offer details. Please try again.'};});
  return r.ok?'':(r.msg||'Could not save the offer details.');}
function fail(m){err.textContent=m;err.hidden=false;btn.disabled=false;btn.textContent='Build contract packet';}
function num(v){return String(v||'').replace(/[$,%\s,]/g,'');}
btn.addEventListener('click',async function(){
  err.hidden=true;res.hidden=true;
  if(!txSel.value)return fail('Please choose the buyer first.');
  ofSync();
  if(!ofEl('street_number').value.trim()||!ofEl('street_name').value.trim()||!ofEl('city').value.trim()||!ofEl('zip').value.trim()){mlsShowFields(true);return fail('Please enter the property address (street number, street name, city and zip).');}
  if(!String(document.getElementById('pprice').value||'').trim())return fail('Please enter the offer price.');
  var oe=await ofSave();if(oe)return fail(oe);
  var pa0=((document.getElementById('paddr')||{}).value||'').replace(/\s+/g,' ').trim();
  if(!pa0)return fail('Please enter the property address first.');
  if(pa0.length>120||/[\\\/:*?"<>|]/.test(pa0))return fail('Please check the property address (no slashes or special characters).');
  var picked=[],warn=[],clauses=[];
  for(var k=0;k<Q.length;k++){
    var x=Q[k];
    if(x.comp){
      var cc=document.getElementById('cp'+x.q), p1=num(cc.querySelector('.wp').value), a1=num(cc.querySelector('.wa').value);
      if((p1&&!/^\d+(\.\d+)?$/.test(p1))||(a1&&!/^\d+(\.\d+)?$/.test(a1)))return fail('Please enter the buyer broker compensation as a number.');
      var pv1=Number(p1||0), av1=Number(a1||0);
      if(pv1>0&&av1>0)return fail('Enter either a percent or a flat amount for buyer broker compensation, not both.');
      if(pv1>0||av1>0){
        var wc=cc.querySelector('input[name="cpw'+x.q+'"]:checked'); if(!wc)return fail('Choose who is to compensate: the seller or the listing brokerage.');
        var rowC=x.rows[Number(wc.value)];picked.push(rowC);var fuC=followFor(x.q,rowC.answer);
        if(fuC){var tC=pv1>0?fuC.percent:fuC.flat; if(!tC)return fail('The clause wording for this answer is missing in the Wizard Rules sheet.'); clauses.push(tC.replace(/\[PERCENT\]/g,p1).replace(/\[AMOUNT\]/g,a1));}
        if(rowC.todo)warn.push('A form for "'+rowC.answer+'" has not been added to the rules yet.');
      }else{var ni1=-1;x.rows.forEach(function(r,i){if(!r.skip&&ni1<0&&!/^yes/i.test(r.answer))ni1=i;});if(ni1>=0)picked.push(x.rows[ni1]);}
      continue;
    }
    if(x.conc){
      var cb=document.getElementById('cq'+x.q), p0=num(cb.querySelector('.wp').value), a0=num(cb.querySelector('.wa').value);
      if((p0&&!/^\d+(\.\d+)?$/.test(p0))||(a0&&!/^\d+(\.\d+)?$/.test(a0)))return fail('Please enter the seller concessions as a number.');
      var pv=Number(p0||0), av=Number(a0||0);
      if(pv>0&&av>0)return fail('Enter either a percent or a flat amount for seller concessions, not both.');
      var yi=-1,ni=-1;x.rows.forEach(function(r,i){if(r.skip)return;if(followFor(x.q,r.answer))yi=i;else if(ni<0)ni=i;});
      if(pv>0||av>0){
        var rowY=x.rows[yi];picked.push(rowY);var fuY=followFor(x.q,rowY.answer),tY=pv>0?fuY.percent:fuY.flat;
        if(!tY)return fail('The clause wording for this answer is missing in the Wizard Rules sheet.');
        clauses.push(tY.replace(/\[PERCENT\]/g,p0).replace(/\[AMOUNT\]/g,a0));
        if(rowY.todo)warn.push('A form for "'+rowY.answer+'" has not been added to the rules yet.');
      }else if(ni>=0){picked.push(x.rows[ni]);}
      continue;
    }
    var c=qs.querySelector('input[name="q'+x.q+'"]:checked');
    if(!c)return fail('Please answer: '+x.question);
    var row=x.rows[Number(c.value)]; picked.push(row);
    var fu=followFor(x.q,row.answer);
    if(fu){
      var box=document.getElementById('f'+x.q+'_'+c.value), p=num(box.querySelector('.wp').value), a=num(box.querySelector('.wa').value);
      if((p&&a)||(!p&&!a))return fail('Enter either a percent or a flat amount for: '+x.question);
      if(!/^\d+(\.\d+)?$/.test(p||a))return fail('Please enter the amount as a number.');
      var t=p?fu.percent:fu.flat;
      if(!t)return fail('The clause wording for this answer is missing in the Wizard Rules sheet.');
      clauses.push(t.replace(/\[PERCENT\]/g,p).replace(/\[AMOUNT\]/g,a));
    }
    if(row.todo)warn.push('A form for "'+row.answer+'" has not been added to the rules yet.');
  }
  var want=[];picked.forEach(function(r){r.forms.forEach(function(f){if(want.indexOf(f)<0)want.push(f);});});
  [].forEach.call(document.querySelectorAll('#xf .xfc:checked'),function(c){if(c.closest('label').style.display!=='none'&&want.indexOf(c.value)<0)want.push(c.value);}); // extra contingency forms the agent ticked
  (R.order||[]).forEach(function(o){if(o.always&&want.indexOf(o.file)<0)want.push(o.file);}); // forms marked Always in the rules sheet go in every packet
  var pos=function(f){for(var i=0;i<R.order.length;i++){if(R.order[i].file===f)return R.order[i].n;}return 9999;};
  want.sort(function(a,b){return pos(a)-pos(b);});
  var use=[];
  want.forEach(function(f){ if(R.files[f]) use.push(f); else warn.push(/\.pdf$/i.test(f)?'The file '+f+' was not found in the Buyer Forms folder, so it is not in the packet.':'Not in the packet yet: '+f+'.'); });
  if(!use.length)return fail('No forms were selected for those answers.');
  btn.disabled=true;btn.innerHTML='<span class="bspin"></span>Building... this can take a minute';
  try{
    await new Promise(function(r){requestAnimationFrame(function(){setTimeout(r,50);});}); // let the spinner paint
    var DISC='Broker_Res_Buyer_Disclosures.pdf';
    var packUse=use.filter(function(f){return f!==DISC;}), discUse=use.filter(function(f){return f===DISC;});
    var load=async function(list){var bufs=await Promise.all(list.map(function(f){return fetch('wizard_pdf.php?id='+encodeURIComponent(R.files[f]),{credentials:'same-origin'}).then(function(r){if(!r.ok)throw new Error('Could not load '+title(f));return r.arrayBuffer();});}));return list.map(function(f,i){return {bytes:new Uint8Array(bufs[i]),isContract:/AS_IS_Residential_Contract|Vacant_Land_Contract/.test(f)};});};
    var toB64=function(bytes){var bin='';for(var o=0;o<bytes.length;o+=32768){bin+=String.fromCharCode.apply(null,bytes.subarray(o,o+32768));}return btoa(bin);};
    var notes=[],packBytes=null,discBytes=null,packB64='',discB64='';
    if(packUse.length){var pd=await FLRWiz.buildPacket(PDFLib,await load(packUse),clauses,fillMap());notes=notes.concat(pd.__notes||[]);packBytes=await pd.save();packB64=toB64(packBytes);}
    if(discUse.length){var dd=await FLRWiz.buildPacket(PDFLib,await load(discUse),[],fillMap());discBytes=await dd.save();discB64=toB64(discBytes);} // Buyer Disclosures stay a separate PDF
    // Nothing is saved to the folder yet: the documents are kept in this browser until the agent completes them. Only the contract offer record is created.
    btn.innerHTML='<span class="bspin"></span>Creating the contract offer record...';
    var j={ok:false};
    try{var r=await fetch('wizard_save.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:CSRF,tx:txSel.value,offerOnly:true,forms:use,property:((document.getElementById('paddr')||{}).value||'').trim(),price:((document.getElementById('pprice')||{}).value||'').trim()})});j=await r.json();}catch(e3){}
    var out='<h2>Your contract packet</h2><ol>'+packUse.map(function(f){return '<li>'+esc(title(f))+'</li>';}).join('')+'</ol>';
    notes.concat(warn).forEach(function(n){out+='<p class="note">'+esc(n)+'</p>';});
    if(j.ok&&j.offer)out+='<p>Contract offer record: <strong>1 BUYERS / 2 Contract Offers / '+esc(j.offer)+'</strong>'+(j.offerNote?' ('+esc(j.offerNote)+')':'')+'.</p>';
    var canEdit=false;
    if(packBytes){try{await FLRDraft.put(txSel.value,{packet:packBytes,disc:discBytes,property:((document.getElementById('paddr')||{}).value||'').trim(),t:Date.now()});canEdit=true;}catch(e4){out+='<p class="sigerr">This browser could not hold the packet for editing. Use Download instead.</p>';}}
    if(canEdit&&!warn.length&&(j.ok||!j.offer)){btn.innerHTML='<span class="bspin"></span>Preparing...';window.top.location.href='editor.php?tx='+encodeURIComponent(txSel.value)+'&draft=1';return;} // nothing to review here: go straight to completing the forms
    out+='<p>';
    if(packBytes)out+='<a class="btn" href="'+URL.createObjectURL(new Blob([packBytes],{type:'application/pdf'}))+'" download="Contract Packet.pdf">Download Packet</a> ';
    if(canEdit)out+='<a class="btn" href="editor.php?tx='+encodeURIComponent(txSel.value)+'&draft=1" id="cfs">Complete For Signing</a>';
    out+='</p>';
    res.innerHTML=out;res.hidden=false;
    var cfs=document.getElementById('cfs');if(cfs)cfs.addEventListener('click',function(){cfs.innerHTML='<span class="bspin"></span>Opening...';cfs.style.pointerEvents='none';});btn.hidden=true;btn.disabled=false;btn.textContent='Build contract packet';
  }catch(e){fail('Something went wrong: '+e.message);}
});
})();
</script>
<?php endif; ?>
</main>
<?php if ($emb): ?></div><?php else: ?><footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer><?php endif; ?>
</body>
</html>
