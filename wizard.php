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
<link rel="stylesheet" href="style.css?v=19">
<?php if ($emb): ?><base target="_top"><style>body{margin:0;background:#fff}.embedwrap{padding:18px}.embedwrap .wrap{padding:0;max-width:none}.embedwrap .panel,.embedwrap .sigbox{border:0!important;box-shadow:none!important;background:transparent!important;padding:0!important;border-radius:0!important;margin-left:0!important;margin-right:0!important}</style><?php endif; ?>
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
if ($pre !== '' && $all !== null) { // a buyer record the site holds (e.g. a sample buyer, or one not yet in the Drive folder list) still opens here
    $have = false; foreach ($folders as $f) { if ($f['id'] === $pre) $have = true; }
    if (!$have) { try { require_once __DIR__ . '/flrdb.php'; foreach (flr_tx_list($_SESSION['email'] ?? '', false) as $t) { if ($t['folder'] === $pre && $t['side'] === 'buyer') { $folders[] = ['id' => $pre, 'name' => (string)$t['name'], 'group' => '1 BUYERS / 1 Buyers']; break; } } } catch (Throwable $e) {} }
}
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
    .mw{position:relative;display:block}.mw .msym{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#455355;pointer-events:none;font-size:.95rem}.mw input{padding-left:22px!important}
    .cdq{display:flex;gap:6px;margin-top:5px}.cdq .btn.sm{padding:1px 10px!important;font-size:.76rem!important;width:auto}.cdq .btn.on{background:#036f72!important;color:#fff!important;border-color:#036f72!important}
    .odb{background:#f0f8f8;border:1px solid #cfe5e5;border-radius:10px;padding:10px 14px;font-size:.92rem}.odb b{font-weight:700}.odb .m{color:#5a6a6c;font-size:.84rem;margin-top:2px}
    @media(max-width:640px){.odg .s2,.odg .s3,.odg .s4{grid-column:span 6}.odg .s6{grid-column:span 12}}
    @media(max-width:640px){.odg .sn1{grid-column:span 4}.odg .sn2{grid-column:span 8}}
    </style>
    <div class="od" id="od">
      <h3>Property</h3>
      <div class="odg">
        <label class="s4"><strong>MLS number</strong><input type="text" id="of_mls_id" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other" placeholder="e.g. T3512345" style="text-transform:uppercase"></label>
        <div class="s2" style="align-self:end"><button type="button" class="btn" id="mls_go" style="width:100%;padding:9px 6px;margin-bottom:0">Look up</button></div>
        <div class="s6 odb" id="mls_note" style="align-self:end" hidden></div>
        <div class="s12" id="mls_miss" hidden style="color:#9a2b2b;font-size:.85rem;font-weight:600"></div>
        <div class="s12 odb" id="mls_card" hidden style="display:none;justify-content:space-between;align-items:center;gap:12px"><div id="mls_card_txt"></div><button type="button" class="btn" id="mls_edit" style="width:auto;padding:8px 18px;margin:0">Edit</button></div>
        <label class="s3 sn1">Street number<input type="text" id="of_street_number" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s6 sn2">Street name<input type="text" id="of_street_name" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s3">Unit #<input type="text" id="of_unit" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s6">City<input type="text" id="of_city" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s2">State<input type="text" id="of_state" maxlength="2" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">Zip code<input type="text" id="of_zip" inputmode="numeric" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">Year built<input type="text" id="of_year_built" inputmode="numeric" maxlength="4" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">HOA / community association?<select id="of_hoa"><option value="">Select</option><option value="yes">Yes</option><option value="no">No</option></select></label>
        <label class="s4">CDD (Community Development District)?<select id="of_cdd"><option value="">Select</option><option value="yes">Yes</option><option value="no">No</option></select></label>
        <label class="s4">Annual taxes<input type="text" id="of_annual_taxes" inputmode="decimal" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">Property type<select id="of_property_type"><option value="">Select</option><?php foreach (OF_PROP_TYPES as $pt): ?><option><?= htmlspecialchars($pt) ?></option><?php endforeach; ?></select></label>
        <label class="s6">Seller name(s)<input type="text" id="of_seller_names" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s3">County<input type="text" id="of_county" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s3">Property tax ID (parcel)<input type="text" id="of_parcel_id" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s12">Legal description<textarea id="of_legal_desc" rows="3" maxlength="800" autocomplete="nope" data-lpignore="true" data-form-type="other" style="display:block;width:100%;box-sizing:border-box;margin-top:3px;padding:9px 11px;border:1px solid #d7e2e2;border-radius:9px;font:inherit;font-weight:400;font-size:.95rem"></textarea></label>
        <label class="s12">Other personal property included (for example washer, dryer, microwave)<input type="text" id="of_personal_prop" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s4"><strong>Purchase Price</strong><input type="text" id="pprice" inputmode="decimal" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other" placeholder="450,000"></label>
        <label class="s4"><strong>Escrow Deposit Amount</strong><input type="text" id="of_escrow_deposit" inputmode="decimal" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other" placeholder="5,000"><span class="cdq" id="esq"><button type="button" class="btn ghost sm" data-a="1000">$1,000</button><button type="button" class="btn ghost sm" data-a="2000">$2,000</button><button type="button" class="btn ghost sm" data-p="1">1%</button><button type="button" class="btn ghost sm" data-p="2">2%</button></span></label>
        <label class="s4" id="of_fin_wrap" style="display:none"><strong>Amount Being Financed</strong><input type="text" id="of_financed_amount" inputmode="decimal" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other" placeholder="360,000"><span class="cdq" id="fnq"><button type="button" class="btn ghost sm" data-d="3.5">3.5% down</button><button type="button" class="btn ghost sm" data-d="5">5% down</button><button type="button" class="btn ghost sm" data-d="10">10% down</button><button type="button" class="btn ghost sm" data-d="20">20% down</button></span></label>
        <div class="s4" style="grid-column:1/span 4"><label style="display:block;font-size:.82rem;font-weight:600;color:#455355;margin:0">Closing date<input type="date" id="of_closing_date"></label><span class="cdq" id="cdq"><button type="button" class="btn ghost sm" data-n="30">30 days</button><button type="button" class="btn ghost sm" data-n="45">45 days</button><button type="button" class="btn ghost sm" data-n="60">60 days</button></span></div>
        <label class="s4"><strong>Number Of Days For Inspections</strong><input type="text" id="of_inspection_days" inputmode="numeric" maxlength="3" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other" placeholder="e.g. 15"><span class="cdq" id="idq"><button type="button" class="btn ghost sm" data-n="5">5 Days</button><button type="button" class="btn ghost sm" data-n="7">7 Days</button><button type="button" class="btn ghost sm" data-n="10">10 Days</button><button type="button" class="btn ghost sm" data-n="15">15 Days</button></span></label>
      </div>
      <h3 id="esc_head">Escrow deposit holder (title company)</h3>
      <div class="odg" id="esc_grid">
        <label class="s6">Title company name<input type="text" id="of_esc_name" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s6">Phone<input type="text" id="of_esc_phone" inputmode="tel" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s6">Address<input type="text" id="of_esc_address" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other" placeholder="Street, city, state zip"></label>
        <label class="s6">Email<input type="text" id="of_esc_email" inputmode="email" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
      </div>
      <input type="hidden" id="paddr">
      <h3 id="oa_head">Other agent</h3>
      <div class="odg" id="oa_grid">
        <label class="s12" style="font-weight:700;color:#036f72">Other agent (listing side, if known)</label>
        <label class="s4">First name<input type="text" id="of_oa_first" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">Last name<input type="text" id="of_oa_last" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s4">Brokerage<input type="text" id="of_oa_brokerage" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s6">Email<input type="text" id="of_oa_email" inputmode="email" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
        <label class="s6">Phone<input type="text" id="of_oa_phone" inputmode="tel" autocomplete="nope" aria-autocomplete="none" data-lpignore="true" data-form-type="other"></label>
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
<script src="wizlib.js?v=27"></script>
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
Q=Q.filter(function(x){return !/association riders/i.test(x.question);}); // not asked: the HOA, CDD and condominium riders come from the property information below
function followFor(q,answer){for(var i=0;i<R.follow.length;i++){if(R.follow[i].q===q&&R.follow[i].answer.toLowerCase()===answer.toLowerCase())return R.follow[i];}return null;}
var h='';
Q.forEach(function(x){
  // Seller concessions: instead of Yes / No, two amount fields (default 0); anything above 0 adds the clause, 0 in both leaves it out.
  x.conc=/concession/i.test(x.question)&&x.rows.some(function(r){return !r.skip&&followFor(x.q,r.answer);});
  // Buyer broker compensation: amount fields (default 0) plus who pays, instead of three Yes / No answers.
  x.comp=/buyer broker compensation/i.test(x.question)&&x.rows.filter(function(r){return !r.skip&&/^yes/i.test(r.answer);}).length>=1;
  if(x.comp){var ys=x.rows.filter(function(r){return !r.skip&&/^yes/i.test(r.answer);});
    h+='<fieldset class="wq"><legend>'+esc(x.question)+'</legend><div id="cp'+x.q+'">Percent of Purchase Price <input type="text" inputmode="decimal" size="4" class="wp" value="0"> % &nbsp;<strong>OR</strong>&nbsp; Flat Amount $ <input type="text" inputmode="decimal" size="8" class="wa" value="0">';
    ys.forEach(function(r,i){h+='<label class="wr"><input type="radio" name="cpw'+x.q+'" value="'+x.rows.indexOf(r)+'"'+(i===0?' checked':'')+'> '+esc(r.answer.replace(/^yes\s*[-\u2013\u2014:]?\s*/i,''))+'</label>';});
    h+='</div></fieldset>';return;}
  if(x.conc){h+='<fieldset class="wq"><legend>'+esc(x.question)+'</legend><div id="cq'+x.q+'">Percent of Purchase Price <input type="text" inputmode="decimal" size="4" class="wp" value="0"> % &nbsp;<strong>OR</strong>&nbsp; Flat Amount $ <input type="text" inputmode="decimal" size="8" class="wa" value="0"></div></fieldset>';return;}
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
  var m={BuyerAgent_Name:AGENT,BuyerBroker_Name:BROKERAGE,Agent_Name:AGENT};if(LICENSE)m.Agent_License=LICENSE;var pa=(document.getElementById('paddr')||{}).value; if(pa&&pa.trim())m.Property_Address=pa.trim(); var pp=String((document.getElementById('pprice')||{}).value||'').replace(/[^0-9.]/g,''); if(pp&&!isNaN(Number(pp)))m.Price_Purchase=Number(pp).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); // the agent and brokerage are the cooperating agent and broker on the forms
  // Make An Offer answers onto the contract (As Is and Vacant Land use the same field names)
  var nz=function(id){return parseFloat(String(((document.getElementById(id)||{}).value)||'').replace(/[^0-9.]/g,''))||0;},mo=function(n){return n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});};
  var pr=nz('pprice'),dep=nz('of_escrow_deposit'),loan=ofEl('financed_amount')&&document.getElementById('of_fin_wrap')&&document.getElementById('of_fin_wrap').style.display!=='none'?nz('of_financed_amount'):0;
  if(dep>0)m.Price_Deposit=mo(dep); if(loan>0)m.Price_LoanAmount=mo(loan); if(pr>0&&(dep>0||loan>0))m.Price_BalanceToClose=mo(Math.max(0,pr-dep-loan));
  var ev=function(k){return String((ofEl(k)||{}).value||'').trim();};
  if(ev('esc_name'))m.Escrow_Agent=ev('esc_name'); if(ev('esc_address'))m.Escrow_Address=ev('esc_address'); if(ev('esc_phone'))m.Escrow_Phone=ev('esc_phone'); if(ev('esc_email'))m.Escrow_Email=ev('esc_email');
  var cd=/^(\d{4})-(\d{2})-(\d{2})$/.exec(ev('closing_date')); if(cd)m.Date_Closing=cd[2]+'/'+cd[3]+'/'+cd[1];
  if(/^\d+$/.test(ev('inspection_days')))m.Term_InspectionDays=ev('inspection_days');
  // FF addendum (credit related to buyer's broker compensation): same commission as the Buyer Agreement / Showing Agreement
  (function(){var c=OFCOMP||{},g=function(k){return String(c[k]||'').trim();},pl=g('Comp_PurchasePlus_Percent'),fl=g('Comp_PurchasePlus_FlatFee')||g('Comp_PurchasePlus_Amount'),pc=g('Comp_Purchase_Percent'),am=g('Comp_Purchase_Amount');
    var money=function(s){var x=/\$?\s*([\d,]+(?:\.\d+)?)/.exec(s);if(!x)return '';var n=parseFloat(x[1].replace(/,/g,''));return isNaN(n)?'':mo(n);};
    var pct=(pl&&parseFloat(pl)>0)?pl:((pc&&parseFloat(pc)>0)?pc:'');
    if(pct){m.Rad_FF_Credit='Percent';m.FF_Credit_Percent=pct;} // the buyer-broker commission percent, first option
    else if(money(am)){m.Rad_FF_Credit='Amount';m.FF_Credit_Amount=money(am);}
    m.Rad_FF_Excess='PaidToBuyerBroker'; // if the credit exceeds what the lender allows: the balance is paid by Seller directly to Buyer Broker (always)
  })();
  (function(){var p=new Intl.DateTimeFormat('en-US',{timeZone:'America/New_York',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()),o={};p.forEach(function(x){o[x.type]=x.value;});var d=new Date(Date.UTC(+o.year,+o.month-1,+o.day+1));m.Date_OfferExpires=('0'+(d.getUTCMonth()+1)).slice(-2)+'/'+('0'+d.getUTCDate()).slice(-2)+'/'+d.getUTCFullYear()+' at 5:00 PM';})(); // contract 3(a): offer expires tomorrow (Eastern time) at 5:00 PM
  // contract paragraph 8: cash buyers get 8(a); financing buyers get 8(b) with the loan type from the client record, a fixed or adjustable rate, 30-year term, 5 days to apply
  if(INFO&&INFO.payment==='cash'){m.Chk_Fin_Cash='Yes';}
  else if(INFO&&(INFO.payment==='conventional'||INFO.payment==='fhava')){m.Chk_Fin_Contingent='Yes';m.Rad_LoanRate='FixedOrAdjustable';m.Loan_TermYears='30';m.Term_LoanApplicationDays='5';if(INFO.loan_type)m.Rad_LoanType=INFO.loan_type;}
  m.Rad_ClosingAgent='SellerDesignates'; m.Rad_HomeWarranty='NA'; // contract paragraph 9(c)(i): Seller designates Closing Agent and pays for owner's policy; 9(e): no home warranty
  m.Rad_SpecialAssessment='OptionB'; // contract special assessment installments: (b) Seller pays in full at or before Closing
  // paragraph 19 ADDENDA: check the boxes for the riders that apply (As Is contract letters; the Vacant Land contract has its own few boxes)
  (function(){var chk=function(k){m['Chk_Addenda_'+k]='Yes';},ev2=function(k){return String((ofEl(k)||{}).value||'').trim();};
    if(ev2('property_type')==='Condominium')chk('A'); if(ev2('hoa')==='yes'){chk('B');chk('HOAAddendum');}
    if(INFO&&(INFO.payment==='fhava'||INFO.loan_type==='FHA'||INFO.loan_type==='VA'))chk('E');
    var yb=parseInt(ev2('year_built'),10);if(yb>0&&yb<1978)chk('P');
    var MAP=[[/Sale_of_Buyers_Property/i,'V'],[/Housing_for_Older/i,'Q'],[/Licensee_Personal/i,'AA'],[/Appraisal_Contingency/i,'F'],[/Credit_Related|Buyers_Broker_Compensation/i,'FF'],[/Sellers?_Agreement.*Compensation/i,'GG'],
      [/Seller_Financing/i,'C'],[/Mortgage_Assumption/i,'D'],[/Short_Sale/i,'G'],[/Flood_Ins/i,'H'],[/Interest.?Bearing/i,'J'],[/Defective_Drywall/i,'M'],[/Coastal_Construction/i,'N'],[/Insulation/i,'O'],[/Rezoning/i,'R'],[/Lease_(Purchase|Option)/i,'S'],
      [/Pre.?Closing_Occupancy/i,'T'],[/Post.?Closing_Occupancy/i,'U'],[/Back.?up/i,'W','BackUpContract'],[/Kick.?out/i,'X','KickOutClause'],[/Seller.*Attorney/i,'Y'],[/Buyer.*Attorney/i,'Z'],[/Binding_Arbitration/i,'BB'],[/Miami.?Dade.*Special/i,'CC'],[/Seasonal|Vacation_Rental/i,'DD'],[/Qualifying_Improvements/i,'EE']];
    (typeof LASTWANT!=='undefined'?LASTWANT:[]).forEach(function(f){MAP.forEach(function(r){if(r[0].test(f)){chk(r[1]);if(r[2])chk(r[2]);}});});
    m.Chk_Addenda_Other='Yes';m.Addenda_Other_1='Flood Disclosure';if(ev2('cdd')==='yes')m.Addenda_Other_2='CDD Disclosure'; // Other: Flood Disclosure on every contract, plus the CDD Disclosure when the property has a CDD
  })();
  // paragraph 20 signature block: the listing agent and listing brokerage from the MLS / agent entry (never the customers' notice addresses, phones or emails)
  (function(){var s=function(k){return String((ofEl(k)||{}).value||'').trim();},la=[s('oa_first'),s('oa_last')].filter(Boolean).join(' ');
    if(la){m.ListingAgent_Name=la;m.ListingAgent_NameLicense=la;} if(s('oa_brokerage'))m.ListingBroker_Name=s('oa_brokerage'); if(s('oa_email'))m.ListingAgent_Email=s('oa_email'); if(s('oa_phone'))m.ListingAgent_Phone=s('oa_phone');})();
  m.Price_AdditionalDeposit='0.00'; m.Price_Other='0.00'; // 2(b) additional deposit and 2(d) other: default $0.00
  if(String((ofEl('personal_prop')||{}).value||'').trim())m.Property_Included=String(ofEl('personal_prop').value).trim(); // wizlib spreads it over the contract's lines
  m.Rad_Assignability='MayNotAssign'; // contract paragraph 7: default to "may not assign"
  m.Rad_DepositTiming='WithinDays'; m.Term_DepositDays='3'; // contract 2(a): always default to option (ii), deposit due within 3 days after the Effective Date
  var sv=function(k){return String((ofEl(k)||{}).value||'').trim();};
  if(sv('seller_names'))m.Seller_Names=sv('seller_names'); if(sv('county'))m.Property_County=sv('county').replace(/\s+county$/i,''); if(sv('parcel_id'))m.Property_ParcelID=sv('parcel_id');
  if(sv('legal_desc')){var lw=sv('legal_desc').split(/\s+/),caps=[55,90,90,90,90],li=0,cur='';lw.forEach(function(x){while(li<5&&(cur?cur+' '+x:x).length>caps[li]&&cur){m['Property_LegalDescription_'+(li+1)]=cur;li++;cur='';}cur=cur?cur+' '+x:x;});if(li<5&&cur)m['Property_LegalDescription_'+(li+1)]=cur;} // one contract line per field: 55 characters on the first line, 90 on the rest
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
// A cash buyer (from the record, or chosen in the Method of purchase question) gets the Appraisal Contingency choice under Add Other Contingency Forms.
qs.addEventListener('change',function(e){var t=e.target;if(!t||t.type!=='radio')return;var lb=t.closest('label'),tx=lb?lb.textContent:'';if(!/purchase|payment|cash|financ/i.test(tx))return;var xF=document.getElementById('xf_F');if(!xF)return;if(/^\s*cash/i.test(tx))xF.style.display='';else if(/financ/i.test(tx)){xF.style.display='none';var c=xF.querySelector('input');if(c)c.checked=false;}});
loadInfo();
// Offer Details: the property and other-side agent, prefilled from the record and saved back to it so nothing is typed twice.
var OFK=['personal_prop','seller_names','county','parcel_id','legal_desc','esc_name','esc_address','esc_phone','esc_email','escrow_deposit','financed_amount','inspection_days','street_number','street_name','unit','city','state','zip','mls_id','year_built','annual_taxes','closing_date','property_type','hoa','cdd','oa_first','oa_last','oa_brokerage','oa_email','oa_phone'];
var CDH=<?= json_encode((function(){require_once __DIR__.'/dlib.php';$o=[];$y=(int)date('Y');for($i=0;$i<=3;$i++)$o+=dl_holidays($y+$i);return $o;})(), JSON_UNESCAPED_SLASHES) ?>;
function ofPrice(){return parseFloat(String(document.getElementById('pprice').value).replace(/[^0-9.]/g,''))||0;}
function ofMoney(n){return (Math.round(n*100)/100).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
var MNY=['pprice','of_escrow_deposit','of_financed_amount','of_annual_taxes'];
function mnyLive(el){var s=el.value,c=el.selectionStart,d=s.slice(0,c).replace(/[^0-9.]/g,'').length,t=s.replace(/[^0-9.]/g,''),p=t.indexOf('.');if(p>=0)t=t.slice(0,p+1)+t.slice(p+1).replace(/\./g,'').slice(0,2);var ip=p>=0?t.slice(0,p):t,fp=p>=0?t.slice(p):'';ip=ip.replace(/^0+(?=\d)/,'').replace(/\B(?=(\d{3})+(?!\d))/g,',');var out=ip+fp;el.value=out;var n=0,i=0;for(;i<out.length&&n<d;i++){if(/[0-9.]/.test(out[i]))n++;}try{el.setSelectionRange(i,i);}catch(e){}}
function mnyFix(el){var t=String(el.value).replace(/[^0-9.]/g,'');if(t===''||isNaN(parseFloat(t))){el.value='';return;}el.value=ofMoney(parseFloat(t));}
function mnyAll(){MNY.forEach(function(id){var e=document.getElementById(id);if(e&&String(e.value).trim()!=='')mnyFix(e);});}
MNY.forEach(function(id){var e=document.getElementById(id);if(!e)return;e.addEventListener('input',function(){mnyLive(e);});e.addEventListener('blur',function(){mnyFix(e);});});
['pprice','of_escrow_deposit','of_financed_amount','of_annual_taxes'].forEach(function(id){var i=document.getElementById(id);if(!i||i.parentNode.className==='mw')return;var s=document.createElement('span');s.className='mw';i.parentNode.insertBefore(s,i);s.appendChild(i);var m=document.createElement('span');m.className='msym';m.textContent='$';s.insertBefore(m,i);});
if(!document.getElementById('pprice').value)document.getElementById('pprice').value='0.00';
function ofQuick(){var pr=ofPrice();
[].forEach.call(document.querySelectorAll('#esq .btn'),function(b){b.onclick=function(){var v=b.dataset.a?+b.dataset.a:(pr=ofPrice())?pr*(+b.dataset.p)/100:0;if(!v){alert('Enter the purchase price first.');return;}ofEl('escrow_deposit').value=ofMoney(v);};});
[].forEach.call(document.querySelectorAll('#fnq .btn'),function(b){b.onclick=function(){var p=ofPrice();if(!p){alert('Enter the purchase price first.');return;}ofEl('financed_amount').value=ofMoney(p*(1-(+b.dataset.d)/100));};});}
function idMark(){var v=ofEl('inspection_days').value.trim();[].forEach.call(document.querySelectorAll('#idq .btn'),function(b){b.classList.toggle('on',b.dataset.n===v);});}
[].forEach.call(document.querySelectorAll('#idq .btn'),function(b){b.onclick=function(){ofEl('inspection_days').value=b.dataset.n;idMark();};});ofEl('inspection_days').addEventListener('input',idMark);
function ofNum(id){return parseFloat(String(ofEl(id).value).replace(/[^0-9.]/g,''))||0;}
function ofMarkQ(){var pr=ofPrice(),e=ofNum('escrow_deposit'),f=ofNum('financed_amount');
[].forEach.call(document.querySelectorAll('#esq .btn'),function(b){var v=b.dataset.a?+b.dataset.a:pr*(+b.dataset.p)/100;b.classList.toggle('on',v>0&&Math.round(v)===Math.round(e));});
[].forEach.call(document.querySelectorAll('#fnq .btn'),function(b){var v=pr*(1-(+b.dataset.d)/100);b.classList.toggle('on',pr>0&&Math.round(v)===Math.round(f));});}
['escrow_deposit','financed_amount'].forEach(function(k){ofEl(k).addEventListener('input',ofMarkQ);});document.getElementById('pprice').addEventListener('input',ofMarkQ);
[].forEach.call(document.querySelectorAll('#esq .btn,#fnq .btn'),function(b){b.addEventListener('click',function(){setTimeout(ofMarkQ,0);});});
ofQuick();
function cdIso(d){return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2);}
function cdOff(s){var p=s.split('-'),d=new Date(+p[0],+p[1]-1,+p[2]),w=d.getDay();if(w===6)return 'a Saturday';if(w===0)return 'a Sunday';return CDH[s]?CDH[s]:'';}
function cdNext(s){var p=s.split('-'),d=new Date(+p[0],+p[1]-1,+p[2]);for(var i=0;i<10&&cdOff(cdIso(d));i++)d.setDate(d.getDate()+1);return cdIso(d);}
function cdAdd(n){var d=new Date();d.setDate(d.getDate()+n);return cdNext(cdIso(d));} // at least n days: never fewer, rolls forward off weekends and holidays
function cdWarn(){var v=ofEl('closing_date').value,n=document.getElementById('cdwarn');if(!n){n=document.createElement('div');n.id='cdwarn';n.style.cssText='font-size:.8rem;color:#9a2b2b;margin-top:4px';document.getElementById('cdq').after(n);}var w=v?cdOff(v):'';if(!w){n.innerHTML='';return;}var nx=cdNext(v);n.innerHTML='A closing cannot fall on '+w+'. <a href="#" id="cdfix" style="color:#036f72;font-weight:600">Use next business day ('+nx+')</a>';document.getElementById('cdfix').onclick=function(e){e.preventDefault();ofEl('closing_date').value=nx;cdMark();cdWarn();};}
function cdMark(){cdWarn();var v=ofEl('closing_date').value;[].forEach.call(document.querySelectorAll('#cdq .btn'),function(b){b.classList.toggle('on',cdAdd(+b.dataset.n)===v);});} // highlight the quick button that matches the closing date (30 days is the default)
function ofEl(k){return document.getElementById('of_'+k);}
function ofAddr(){var g=function(k){return (ofEl(k).value||'').replace(/\s+/g,' ').trim();};var st=(g('street_number')+' '+g('street_name')).trim()+(g('unit')?' Unit '+g('unit'):'');var tail=g('city')+((g('state')+' '+g('zip')).trim()?', '+(g('state')+' '+g('zip')).trim():'');return (st+(st&&tail?', ':'')+tail).trim();}
var ESCV={
 phone:function(v){var d=v.replace(/\D/g,'');if(d.length===11&&d[0]==='1')d=d.slice(1);return d.length===10&&!/^[01]/.test(d)&&!/^.{3}[01]/.test(d)?d.slice(0,3)+'-'+d.slice(3,6)+'-'+d.slice(6):null;},
 email:function(v){return /^[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/.test(v)?v.toLowerCase():null;},
 address:function(v){var m=/^(\d+[A-Za-z]?)\s+\S.*?,?\s+([A-Za-z]{2})\s+(\d{5})(-\d{4})?$/.exec(v.trim());return m&&/[A-Za-z]/.test(v.replace(/\s[A-Za-z]{2}\s+\d{5}.*$/,''))?v.trim().replace(/\s+/g,' '):null;}
};
var ESCM={esc_phone:['phone','Phone must be 10 digits, like 727-372-4637.'],esc_email:['email','Email must look like name@company.com.'],esc_address:['address','Address must be a street address with city, state and zip, like 2150 Seven Springs Blvd, Trinity, FL 34655.']};
function escCheck(k,fix){var el=ofEl(k),v=el.value.trim(),n=el.parentNode.querySelector('.escerr');if(!n){n=document.createElement('div');n.className='escerr';n.style.cssText='font-size:.78rem;color:#9a2b2b;margin-top:3px;font-weight:400';el.after(n);}
 if(v===''){n.textContent='';el.style.borderColor='';return true;}var r=ESCV[ESCM[k][0]](v);if(r===null){n.textContent=ESCM[k][1];el.style.borderColor='#c0392b';return false;}n.textContent='';el.style.borderColor='';if(fix)el.value=r;return true;}
function escAll(){var ok=true;['esc_phone','esc_email','esc_address'].forEach(function(k){if(!escCheck(k,true))ok=false;});return ok;}
['esc_phone','esc_email','esc_address'].forEach(function(k){ofEl(k).addEventListener('blur',function(){escCheck(k,true);});ofEl(k).addEventListener('input',function(){if(ofEl(k).parentNode.querySelector('.escerr')&&ofEl(k).parentNode.querySelector('.escerr').textContent)escCheck(k,false);});});
function ofSync(){document.getElementById('paddr').value=ofAddr();}
var OFCOMP={},LASTWANT=[];
function ofCompText(c){if(!c||!c.agreement)return '';var p=[];function f(k){return c[k]||'';}
  if(f('Comp_Purchase_Percent'))p.push(f('Comp_Purchase_Percent')+'% of the purchase price');if(f('Comp_PurchasePlus_Percent'))p.push(f('Comp_PurchasePlus_Percent')+'% of the purchase price');if(f('Comp_Purchase_Amount'))p.push('$'+f('Comp_Purchase_Amount'));if(f('Comp_PurchasePlus_FlatFee'))p.push(f('Comp_PurchasePlus_FlatFee'));
  return '<b>Your commission (from the '+(c.agreement==='showing'?'Showing':'Exclusive Buyer')+' Agreement):</b> '+(p.length?esc(p.join(' + ')):'on file')+'<div class="m">Used for the transaction record. Change it on the Buyer Agreement if it differs.</div>';}
function ofLoad(){var id=txSel.value;if(!id)return;
  fetch('offer_api.php?tx='+encodeURIComponent(id),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
    if(!j||!j.ok||txSel.value!==id)return;
    OFK.forEach(function(k){if(!mlsLast||!ofEl(k).value)ofEl(k).value=j.offer[k]||'';}); // a lookup that started first keeps what it filled
    if(!ofEl('closing_date').value)ofEl('closing_date').value=cdAdd(30);cdMark();if(!ofEl('inspection_days').value)ofEl('inspection_days').value='10';idMark();if(!ofEl('escrow_deposit').value)ofEl('escrow_deposit').value='2,000';if(!ofEl('financed_amount').value)ofEl('financed_amount').value='0';mnyAll();ofMarkQ();
    var ppEl=document.getElementById('pprice');if(!mlsLast||!ppEl.value||ppEl.value==='0'||ppEl.value==='0.00')ppEl.value=j.offer.price?Number(j.offer.price).toLocaleString('en-US',{maximumFractionDigits:2}):'0';mnyAll();ofMarkQ();
    var b=j.buyer,nm=[b.b1_name,b.b2_name].filter(Boolean).join(' and ');
    var _bd=document.getElementById('of_buyer'); if(_bd) _bd.innerHTML=nm?'<b>'+esc(nm)+'</b>'+(b.b1_phone||b.b1_email?'<div class="m">'+esc([b.b1_phone,b.b1_email].filter(Boolean).join(' · '))+'</div>':'')+(b.b2_name&&(b.b2_phone||b.b2_email)?'<div class="m">'+esc(b.b2_name+': '+[b.b2_phone,b.b2_email].filter(Boolean).join(' · '))+'</div>':''):'No buyer name saved yet. Use Edit contact info on the client record above.';
    var _me=document.getElementById('of_me'); if(_me) _me.textContent=[j.agent.name,j.agent.license?'License '+j.agent.license:'',j.agent.email].filter(Boolean).join(' · ');
    OFCOMP=j.comp||{};var ct=ofCompText(j.comp),ce=document.getElementById('of_comp');ce.innerHTML=ct;ce.hidden=!ct;ofSync();
  }).catch(function(){});}
OFK.forEach(function(k){ofEl(k).addEventListener('input',ofSync);});
// MLS number first: typing it (then Look up / Enter / leaving the field) fills the rest of the property and the listing agent from the MLS.
var mlsBox=document.getElementById('mls_note'),mlsGo=document.getElementById('mls_go'),mlsLast='';
function mlsSay(h,bad){mlsBox.hidden=!h;mlsBox.innerHTML=h||'';mlsBox.style.color=bad?'#b3261e':'';}
async function mlsLookup(force){var v=(ofEl('mls_id').value||'').trim().toUpperCase();ofEl('mls_id').value=v;if(!v){mlsSay('');return;}if(!force&&v===mlsLast)return;mlsLast=v;mlsGo.disabled=true;mlsGo.textContent='Looking up...';
  try{var j=await fetch('mls_lookup_api.php?mls='+encodeURIComponent(v),{credentials:'same-origin'}).then(function(r){return r.json();});
    if(!j.ok){mlsSay(esc(j.msg||'Could not look up that MLS number.'),true);mlsShowFields(true);[].forEach.call(qs.querySelectorAll('fieldset.mlsq'),function(f){f.hidden=false;f.classList.remove('mlsq');});}
    else{ofEl('mls_id').value=v;Object.keys(j.fields).forEach(function(k){if(ofEl(k)&&j.fields[k]!=='')ofEl(k).value=j.fields[k];if(ofEl(k))ofEl(k).dispatchEvent(new Event('input'));});mnyAll();ofSync();
      var lp=j.list_price?'$'+Number(j.list_price).toLocaleString('en-US'):'';
      mlsSay('<b>Filled from MLS '+esc(v)+'</b>'+(j.status?' ('+esc(j.status)+')':'')+'. Please check the details.'+(lp?'<div class="m">List price '+lp+' <a href="#" id="mls_lp">Use as offer price</a></div>':''));
      var a=document.getElementById('mls_lp');if(a)a.onclick=function(e){e.preventDefault();var pp=document.getElementById('pprice');pp.value=Number(j.list_price).toLocaleString('en-US',{maximumFractionDigits:2});pp.dispatchEvent(new Event('input'));mnyAll();};MLSX=j.fields||{};mlsCondense();mlsAnswers(j.fields);}
  }catch(e){mlsSay('Could not look up that MLS number. Please try again.',true);}
  mlsGo.disabled=false;mlsGo.textContent='Look up';}
// Questions the MLS record can answer are pre-selected (the agent can still change them); with no MLS record they stay for the agent to answer.
function mlsPick(qre,are){Q.forEach(function(x){if(!qre.test(x.question))return;x.rows.forEach(function(r,i){if(r.skip||!are.test(r.answer))return;var el=qs.querySelector('input[name="q'+x.q+'"][value="'+i+'"]');if(el){el.checked=true;el.dispatchEvent(new Event('change',{bubbles:true}));var fs=el.closest('fieldset');if(fs){fs.classList.add('mlsq');fs.hidden=true;}}});});}
var MLSX={};
function mlsAnswers(x){if(x)MLSX=x;if(!(ofEl('mls_id').value||'').trim())return;var pt=ofEl('property_type').value,yb=parseInt(ofEl('year_built').value,10);
  var pm={'Residential':/residential|single|house|home/i,'Condominium':/condo/i,'Vacant Land':/vacant|land/i}[pt];
  if(pm)mlsPick(/property type/i,pm);
  if(MLSX.hoa&&!ofEl('hoa').value)ofEl('hoa').value=MLSX.hoa;if(MLSX.cdd&&!ofEl('cdd').value)ofEl('cdd').value=MLSX.cdd;hcVis();
  if(yb>0)mlsPick(/1978|lead/i,yb<1978?/^yes/i:/^no/i);
  // HOA / CDD from the MLS record; stays blank when the MLS does not say.
  var h=MLSX.hoa,c=MLSX.cdd,yes=/^yes/i,no=/^no/i;
  mlsPick(/(\bhoa\b|homeowner|association)(?!.*(\bcdd\b|community development))/i,h==='yes'?yes:h==='no'?no:/^$/);
  mlsPick(/(\bcdd\b|community development)(?!.*(\bhoa\b|homeowner|association))/i,c==='yes'?yes:c==='no'?no:/^$/);
  if(h&&c){var both=h==='yes'&&c==='yes',onlyH=h==='yes'&&c==='no',onlyC=h==='no'&&c==='yes';
    mlsPick(/(\bhoa\b|homeowner|association).*(\bcdd\b|community development)|(\bcdd\b|community development).*(\bhoa\b|homeowner|association)/i,both?/both|hoa.*cdd|cdd.*hoa/i:onlyH?/^(?!.*cdd).*hoa/i:onlyC?/^(?!.*hoa).*cdd/i:/neither|^none|^no\b/i);}}
ofEl('property_type').addEventListener('change',function(){mlsAnswers();});
var MLSF=['street_number','street_name','unit','city','state','zip','year_built','annual_taxes','property_type','seller_names','county','parcel_id','legal_desc','personal_prop'];
// HOA and CDD are answered by the MLS lookup: once it has filled them they are hidden (Edit brings them back); a blank one stays so the agent can answer it.
function hcVis(){var collapsed=!document.getElementById('mls_card').hidden;['hoa','cdd'].forEach(function(k){var l=ofEl(k).closest('label');if(l)l.style.display=(collapsed&&ofEl(k).value)?'none':'';});}
function mlsShowFields(on,keepAgent){var oa=['oa_first','oa_last','oa_brokerage','oa_email','oa_phone'].some(function(k){return (ofEl(k).value||'').trim();});['oa_head','oa_grid'].forEach(function(i){document.getElementById(i).style.display=(on||!oa)?'':'none';});MLSF.forEach(function(k){var l=ofEl(k).closest('label');if(l)l.style.display=on?'':'none';});var offAg=!(on||!oa);MLSF.forEach(function(k){ofEl(k).disabled=!on;});['oa_first','oa_last','oa_brokerage','oa_email','oa_phone'].forEach(function(k){ofEl(k).disabled=offAg;}); // hidden address/agent inputs are disabled so Chrome does not treat hiding them as a submitted form and offer to save an address
document.getElementById('mls_card').hidden=on;hcVis();document.getElementById('mls_card').style.display=on?'none':'flex';}
function yn(v){return v==='yes'?'Yes':v==='no'?'No':'not in MLS data';}
function spCard(){var g=function(k){return (ofEl(k).value||'').trim();},nf='<span style="color:#9a6a00">not in MLS data: click Edit to add</span>';
  var t=function(l,v,opt){return '<div class="m"><b>'+l+':</b> '+(v?esc(v):nf)+'</div>';};
  var cp=[g('county')?g('county')+' County':'',g('parcel_id')?'Tax ID '+g('parcel_id'):''].filter(Boolean).join(' · ');
  return '<div style="margin-top:6px">'+t('Seller(s)',g('seller_names'))+'<div class="m"><b>County / parcel:</b> '+(cp?esc(cp):nf)+'</div>'+t('Legal description',g('legal_desc'))+'</div>';}
var MLSREQ=[['street_number','Street number'],['street_name','Street name'],['city','City'],['state','State'],['zip','Zip code'],['property_type','Property type'],['seller_names','Seller name(s)'],['county','County'],['parcel_id','Property tax ID (parcel)'],['legal_desc','Legal description'],['year_built','Year built'],['annual_taxes','Annual taxes'],['hoa','HOA'],['cdd','CDD']];
function mlsMissing(){var vl=ofEl('property_type').value==='Vacant Land',out=[];MLSREQ.forEach(function(r){var k=r[0];if(vl&&(k==='year_built'||k==='hoa'||k==='cdd'))return;if(k==='street_number'&&vl)return;var el=ofEl(k),v=String(el.value||'').trim(),bad=(v==='');if(k==='annual_taxes'&&v!==''&&parseFloat(v.replace(/,/g,''))===0)bad=false;el.style.borderColor=bad?'#c0392b':'';if(bad)out.push(r[1]);});return out;}
function mlsGate(){var box=document.getElementById('mls_miss'),miss=mlsMissing();if(miss.length){box.hidden=false;box.textContent='The MLS did not have: '+miss.join(', ')+'. Please fill these in; the listing summary appears when they are complete.';mlsShowFields(true);return false;}box.hidden=true;box.textContent='';return true;}
MLSREQ.forEach(function(r){var e=ofEl(r[0]);if(e)e.addEventListener('change',function(){if(MLSDONE&&mlsGate())mlsCondense();});});
var MLSDONE=false;
function mlsCondense(){var g=function(k){return (ofEl(k).value||'').trim();};if(!g('mls_id')||!g('street_name')||!g('city'))return;MLSDONE=true;if(!mlsGate())return;
  var addr=[[g('street_number'),g('street_name')].filter(Boolean).join(' ')+(g('unit')?' #'+g('unit'):''),g('city'),[g('state'),g('zip')].filter(Boolean).join(' ')].filter(Boolean).join(', ');
  var tx=g('annual_taxes'),sub=[g('year_built')?'Year built '+g('year_built'):'',tx?'Annual taxes $'+tx:'',g('property_type'),'HOA: '+yn(MLSX.hoa),'CDD: '+yn(MLSX.cdd)].filter(Boolean).join(' · ');
  var ag=[[g('oa_first'),g('oa_last')].filter(Boolean).join(' '),g('oa_brokerage'),g('oa_phone'),g('oa_email')].filter(Boolean).join(' · ');
  document.getElementById('mls_card_txt').innerHTML='<b>'+esc(addr)+'</b>'+(sub?'<div class="m">'+esc(sub)+'</div>':'')+(ag?'<div class="m" style="margin-top:6px"><b>Listing agent:</b> '+esc(ag)+'</div>':'')+spCard();mlsShowFields(false);}
document.getElementById('mls_edit').addEventListener('click',function(){mlsShowFields(true);});
mlsGo.addEventListener('click',function(){mlsLookup(true);});
ofEl('mls_id').addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();mlsLookup(true);}});
ofEl('mls_id').addEventListener('change',function(){mlsLookup(false);});
[].forEach.call(document.querySelectorAll('#cdq .btn'),function(b){b.onclick=function(){ofEl('closing_date').value=cdAdd(+b.dataset.n);cdMark();};});ofEl('closing_date').addEventListener('input',cdMark);ofEl('closing_date').addEventListener('change',cdMark);
txSel.addEventListener('change',ofLoad);ofLoad();
async function ofSave(){ofSync();var o={};OFK.forEach(function(k){o[k]=ofEl(k).value;});o.price=document.getElementById('pprice').value;
  var r=await fetch('offer_api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:CSRF,action:'save',tx:txSel.value,offer:o})}).then(function(x){return x.json();}).catch(function(){return {ok:false,msg:'Could not save the offer details. Please try again.'};});
  return r.ok?'':(r.msg||'Could not save the offer details.');}
function fail(m){err.textContent=m;err.hidden=false;btn.disabled=false;btn.textContent='Build contract packet';}
function num(v){return String(v||'').replace(/[$,%\s,]/g,'');}
btn.addEventListener('click',async function(){
  err.hidden=true;res.hidden=true;
  btn.disabled=true;btn.innerHTML='<span class="bspin"></span>Building... this can take a minute'; // show the spinner the instant the button is pressed, before the saving and checking below
  await new Promise(function(r){requestAnimationFrame(function(){setTimeout(r,30);});});
  if(!txSel.value)return fail('Please choose the buyer first.');
  ofSync();
  if(!ofEl('street_number').value.trim()||!ofEl('street_name').value.trim()||!ofEl('city').value.trim()||!ofEl('zip').value.trim()){mlsShowFields(true);return fail('Please enter the property address (street number, street name, city and zip).');}
  if(!(parseFloat(String(document.getElementById('pprice').value||'').replace(/[^0-9.]/g,''))>0))return fail('Please enter the purchase price.');
  if(ofEl('property_type').value!=='Vacant Land'&&(!ofEl('hoa').value||!ofEl('cdd').value))return fail('Please tell me whether the property has an HOA and whether it has a CDD.');
  if(!escAll())return fail('Please correct the title company information (phone, address or email) before building the packet.');
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
  // HOA rider, condominium rider, lead-based paint and CDD addendum come from the seller, already completed, before the buyer signs: they are never merged into the buyer's packet.
  // They are tracked as required documents (Document Records) from the MLS / agent-entered property data instead (doclib.php dc_required_rules).
  var sv2=function(k){return String((ofEl(k)||{}).value||'').trim();};
  var SELLER_FORMS=['Realtor_Res_HOA_Community_Disclosure.pdf','Realtor_Res_Community_Development_District_Addendum.pdf','Realtor_Res_Condominium_Rider.pdf','Realtor_Res_Lead_Based_Paint_Disclosure.pdf'];
  [].forEach.call(document.querySelectorAll('#xf .xfc:checked'),function(c){if(c.closest('label').style.display!=='none'&&want.indexOf(c.value)<0)want.push(c.value);}); // extra contingency forms the agent ticked
  (R.order||[]).forEach(function(o){if(o.always&&want.indexOf(o.file)<0)want.push(o.file);}); // forms marked Always in the rules sheet go in every packet
  var pos=function(f){for(var i=0;i<R.order.length;i++){if(R.order[i].file===f)return R.order[i].n;}return 9999;};
  if(sv2('legal_desc').length>235)warn.push('The legal description is long; the As Is contract has room for about 235 characters. Please check it fits.');
  want=want.filter(function(f){return SELLER_FORMS.indexOf(f)<0;});
  want.sort(function(a,b){return pos(a)-pos(b);});
  LASTWANT=want.slice();
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
