<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
@set_time_limit(120);
header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$rules = sg_wizard_rules();
$all = sg_folders($_SESSION['email'], (string)($_GET['tx'] ?? ''));
$folders = [];
foreach (($all ?: []) as $f) { if ($f['group'] === '1 BUYERS / 1 Buyers') $folders[] = $f; }
$pre = (string)($_GET['tx'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Contract Forms Wizard - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
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
    <div id="qs"></div>
    <p class="sgerr" id="err" hidden></p>
    <p><button type="button" class="btn" id="build">Build contract packet</button></p>
    <div id="res" hidden></div>
  </div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script src="wizlib.js?v=4"></script>
<script>
(function(){
var R=<?= json_encode($rules, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, CSRF=<?= json_encode($_SESSION['csrf']) ?>;
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
function fail(m){err.textContent=m;err.hidden=false;btn.disabled=false;btn.textContent='Build contract packet';}
function num(v){return String(v||'').replace(/[$,%\s,]/g,'');}
btn.addEventListener('click',async function(){
  err.hidden=true;res.hidden=true;
  if(!txSel.value)return fail('Please choose the buyer first.');
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
  var pos=function(f){for(var i=0;i<R.order.length;i++){if(R.order[i].file===f)return R.order[i].n;}return 9999;};
  want.sort(function(a,b){return pos(a)-pos(b);});
  var use=[];
  want.forEach(function(f){ if(R.files[f]) use.push(f); else warn.push(/\.pdf$/i.test(f)?'The file '+f+' was not found in the Buyer Forms folder, so it is not in the packet.':'Not in the packet yet: '+f+'.'); });
  if(!use.length)return fail('No forms were selected for those answers.');
  btn.disabled=true;btn.textContent='Building... this can take a minute';
  try{
    var bufs=await Promise.all(use.map(function(f){return fetch('wizard_pdf.php?id='+encodeURIComponent(R.files[f]),{credentials:'same-origin'}).then(function(r){if(!r.ok)throw new Error('Could not load '+title(f));return r.arrayBuffer();});}));
    var src=use.map(function(f,i){return {bytes:new Uint8Array(bufs[i]),isContract:/AS_IS_Residential_Contract|Vacant_Land_Contract/.test(f)};});
    var doc=await FLRWiz.buildPacket(PDFLib,src,clauses);
    var notes=doc.__notes||[];
    var bytes=await doc.save();
    var bin='';for(var o=0;o<bytes.length;o+=32768){bin+=String.fromCharCode.apply(null,bytes.subarray(o,o+32768));}
    btn.textContent='Saving to the buyer folder...';
    var r=await fetch('wizard_save.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:CSRF,tx:txSel.value,b64:btoa(bin),forms:use})});
    var j=await r.json();
    var url=URL.createObjectURL(new Blob([bytes],{type:'application/pdf'}));
    var out='<h2>Your contract packet</h2><ol>'+use.map(function(f){return '<li>'+esc(title(f))+'</li>';}).join('')+'</ol>';
    notes.concat(warn).forEach(function(n){out+='<p class="note">'+esc(n)+'</p>';});
    out+= j.ok ? '<p><strong>Saved</strong> in '+esc(j.folder)+' as <a href="'+esc(j.url)+'" target="_blank" rel="noopener">'+esc(j.name)+'</a>.</p>' : '<p class="sigerr">'+esc(j.msg||'The packet was built but could not be saved.')+' You can still download it below.</p>';
    out+='<p><a class="btn" href="'+url+'" download="Contract Packet.pdf">Download the packet</a>'+(j.ok?' <a class="btn ghost" href="sign_packet.php?tx='+encodeURIComponent(txSel.value)+'">Next: send it for signature</a>':'')+'</p>';
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
