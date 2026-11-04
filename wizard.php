<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
@set_time_limit(120);
header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
header('X-Accel-Buffering: no'); while (ob_get_level() > 0) { @ob_end_flush(); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Contract Forms Wizard - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=14">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="my-transactions.php">My Transactions</a>
    <a href="forms.php" aria-current="page">Forms</a>
    <a href="signed.php">Signed</a>
    <a href="training.php">Training</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1>Contract Forms Wizard</h1><p>Buyer contract packet: answer the questions and the right forms are merged for you.</p></section>
<main class="wrap">
<style>#flrload{display:flex;align-items:center;gap:14px;padding:22px 4px;color:#006b68;font-weight:600}#flrload i{width:26px;height:26px;border:4px solid #cfe3e2;border-top-color:#006b68;border-radius:50%;animation:flrspin .8s linear infinite}@keyframes flrspin{to{transform:rotate(360deg)}}</style>
<div id="flrload" role="status"><i></i><span>Opening the Contract Forms Wizard... loading your buyers and the form rules.</span></div>
<?php echo str_repeat(' ', 1024); @flush();
$rules = sg_wizard_rules();
$all = sg_folders($_SESSION['email'], (string)($_GET['tx'] ?? ''));
$folders = [];
foreach (($all ?: []) as $f) { if ($f['group'] === '1 BUYERS / 1 Buyers') $folders[] = $f; }
$pre = (string)($_GET['tx'] ?? '');
?>
<script>var l=document.getElementById('flrload');if(l)l.parentNode.removeChild(l);</script>
<?php if (!$rules): ?>
  <p class="note">The wizard rules could not be loaded right now. Please try again, or contact the office.</p>
<?php elseif ($all === null): ?>
  <p class="note">Your transaction folders could not be loaded: <?= htmlspecialchars($GLOBALS['sg_folders_note'] ?? '') ?>. Please tell the office.</p>
<?php elseif (!$folders): ?>
  <p class="note">You need a buyer folder first. Create a folder named for the buyer(s) inside <strong>My Transactions &rsaquo; 1 BUYERS &rsaquo; 1 Buyers</strong>, then come back to this page.</p>
  <p class="note">Looked in: <?= htmlspecialchars($GLOBALS['sg_folders_root'] ?? '') ?>. Folders found: <?= count($all) ?><?= $all ? ' (' . htmlspecialchars(implode('; ', array_unique(array_map(function ($f) { return $f['group']; }, $all)))) . ')' : '' ?>.</p>
<?php else: ?>
  <div class="panel sigbox wz" id="wz">
    <p><label for="tx"><strong>Which buyer is this for?</strong></label><br>
    <select id="tx" class="txsel">
      <option value="">Choose a buyer...</option>
<?php $g = ''; foreach ($folders as $f): if ($f['group'] !== $g): if ($g !== '') echo "      </optgroup>\n"; $g = $f['group']; echo '      <optgroup label="' . htmlspecialchars($g) . "\">\n"; endif; ?>
      <option value="<?= htmlspecialchars($f['id']) ?>"<?= $pre === $f['id'] ? ' selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
<?php endforeach; if ($g !== '') echo "      </optgroup>\n"; ?>
    </select></p>
    <div id="bsum" class="wsum"></div>
    <div class="bi wprop">
      <label for="paddr"><strong>Property address</strong> (required: fills the forms and creates the contract offer record)<input type="text" id="paddr" autocomplete="off" data-lpignore="true" data-form-type="other" placeholder="Street, city, state, zip"></label>
      <label for="pprice"><strong>Purchase price</strong> (fills the contract)<input type="text" id="pprice" inputmode="decimal" autocomplete="off" data-lpignore="true" data-form-type="other" placeholder="$ 450,000" style="max-width:260px"></label>
    </div>
    <div id="qs"></div>
    <p class="sgerr" id="err" hidden></p>
    <p><button type="button" class="btn" id="build">Build contract packet</button></p>
    <div id="res" hidden></div>
  </div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
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
  INFO=null;bsum.innerHTML='';
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
    var re={conventional:/^conventional/i,fhava:/^fha/i}[INFO.payment];
    if(re){Q.forEach(function(x){x.rows.forEach(function(r,i){if(re.test(r.answer)){var el=qs.querySelector('input[name="q'+x.q+'"][value="'+i+'"]');if(el)el.checked=true;}});});}
    else if(INFO.payment==='cash'){bsum.innerHTML+='<p class="note">Cash purchase: choose with or without the appraisal contingency below.</p>';}
  }).catch(function(){});
}
txSel.addEventListener('change',loadInfo);
loadInfo();
function fail(m){err.textContent=m;err.hidden=false;btn.disabled=false;btn.textContent='Build contract packet';}
function num(v){return String(v||'').replace(/[$,%\s,]/g,'');}
btn.addEventListener('click',async function(){
  err.hidden=true;res.hidden=true;
  if(!txSel.value)return fail('Please choose the buyer first.');
  var pa0=((document.getElementById('paddr')||{}).value||'').replace(/\s+/g,' ').trim();
  if(!pa0)return fail('Please enter the property address first.');
  if(pa0.length>120||/[\\\/:*?"<>|]/.test(pa0))return fail('Please check the property address (no slashes or special characters).');
  var picked=[],warn=[],clauses=[];
  for(var k=0;k<Q.length;k++){
    var x=Q[k],c=qs.querySelector('input[name="q'+x.q+'"]:checked');
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
  (R.order||[]).forEach(function(o){if(o.always&&want.indexOf(o.file)<0)want.push(o.file);}); // forms marked Always in the rules sheet go in every packet
  var pos=function(f){for(var i=0;i<R.order.length;i++){if(R.order[i].file===f)return R.order[i].n;}return 9999;};
  want.sort(function(a,b){return pos(a)-pos(b);});
  var use=[];
  want.forEach(function(f){ if(R.files[f]) use.push(f); else warn.push(/\.pdf$/i.test(f)?'The file '+f+' was not found in the Buyer Forms folder, so it is not in the packet.':'Not in the packet yet: '+f+'.'); });
  if(!use.length)return fail('No forms were selected for those answers.');
  btn.disabled=true;btn.textContent='Building... this can take a minute';
  try{
    var DISC='Broker_Res_Buyer_Disclosures.pdf';
    var packUse=use.filter(function(f){return f!==DISC;}), discUse=use.filter(function(f){return f===DISC;});
    var load=async function(list){var bufs=await Promise.all(list.map(function(f){return fetch('wizard_pdf.php?id='+encodeURIComponent(R.files[f]),{credentials:'same-origin'}).then(function(r){if(!r.ok)throw new Error('Could not load '+title(f));return r.arrayBuffer();});}));return list.map(function(f,i){return {bytes:new Uint8Array(bufs[i]),isContract:/AS_IS_Residential_Contract|Vacant_Land_Contract/.test(f)};});};
    var toB64=function(bytes){var bin='';for(var o=0;o<bytes.length;o+=32768){bin+=String.fromCharCode.apply(null,bytes.subarray(o,o+32768));}return btoa(bin);};
    var notes=[],packBytes=null,discBytes=null,packB64='',discB64='';
    if(packUse.length){var pd=await FLRWiz.buildPacket(PDFLib,await load(packUse),clauses,fillMap());notes=notes.concat(pd.__notes||[]);packBytes=await pd.save();packB64=toB64(packBytes);}
    if(discUse.length){var dd=await FLRWiz.buildPacket(PDFLib,await load(discUse),[],fillMap());discBytes=await dd.save();discB64=toB64(discBytes);} // Buyer Disclosures stay a separate PDF
    btn.textContent='Saving to the buyer folder...';
    var r=await fetch('wizard_save.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:CSRF,tx:txSel.value,b64:packB64,b64d:discB64,forms:use,property:((document.getElementById('paddr')||{}).value||'').trim(),price:((document.getElementById('pprice')||{}).value||'').trim()})});
    var j=await r.json();
    var out='<h2>Your contract packet</h2><ol>'+packUse.map(function(f){return '<li>'+esc(title(f))+'</li>';}).join('')+'</ol>';
    if(discUse.length)out+='<h2>Buyer Disclosures (separate document)</h2><ol>'+discUse.map(function(f){return '<li>'+esc(title(f))+'</li>';}).join('')+'</ol>';
    notes.concat(warn).forEach(function(n){out+='<p class="note">'+esc(n)+'</p>';});
    out+= j.ok ? '<p><strong>Saved</strong> in '+esc(j.folder)+':</p><ul>'+(j.name?'<li><a href="'+esc(j.url)+'" target="_blank" rel="noopener">'+esc(j.name)+'</a></li>':'')+(j.dname?'<li><a href="'+esc(j.durl)+'" target="_blank" rel="noopener">'+esc(j.dname)+'</a></li>':'')+'</ul>'+(j.offer?'<p>Contract offer record: <strong>1 BUYERS / 2 Contract Offers / '+esc(j.offer)+'</strong>'+(j.offerNote?' ('+esc(j.offerNote)+')':'')+'.</p>':'') : '<p class="sigerr">'+esc(j.msg||'The documents were built but could not be saved.')+' You can still download them below.</p>';
    out+='<p>';
    if(packBytes)out+='<a class="btn" href="'+URL.createObjectURL(new Blob([packBytes],{type:'application/pdf'}))+'" download="Contract Packet.pdf">Download the packet</a> ';
    if(discBytes)out+='<a class="btn" href="'+URL.createObjectURL(new Blob([discBytes],{type:'application/pdf'}))+'" download="Buyer Disclosures.pdf">Download the disclosures</a> ';
    out+=(j.ok?'<a class="btn" href="editor.php?tx='+encodeURIComponent(txSel.value)+'">Review and complete</a> <a class="btn ghost" href="sign_packet.php?tx='+encodeURIComponent(txSel.value)+'">Send for signature</a>':'')+'</p>';
    res.innerHTML=out;res.hidden=false;btn.disabled=false;btn.textContent='Build another packet';
  }catch(e){fail('Something went wrong: '+e.message);}
});
})();
</script>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
