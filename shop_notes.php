<?php
// Reached from the "Shop Homes" step: a running log of notes while the buyer is shopping, including listings scheduled
// or shown. Stored per transaction folder, newest first.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/pstage.php"; // pulls in flrdb.php
require_once __DIR__ . "/subpage.php";
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

function shop_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS shop_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, folder TEXT NOT NULL, akey TEXT NOT NULL, kind TEXT, address TEXT, note TEXT, created TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS shop_notes_folder ON shop_notes(folder)');
    return $pdo;
}
$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $kind = in_array($_POST['kind'] ?? '', ['scheduled', 'shown', 'note'], true) ? $_POST['kind'] : 'note';
        $addr = mb_substr(trim((string)($_POST['address'] ?? '')), 0, 200);
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 2000);
        if ($kind !== 'note' && $addr === '') { $err = 'Please enter the listing address.'; }
        elseif ($kind === 'note' && $note === '') { $err = 'Please enter a note.'; }
        else {
            shop_db()->prepare('INSERT INTO shop_notes (folder, akey, kind, address, note, created) VALUES (?,?,?,?,?,?)')
                ->execute([$tx, flr_akey($me), $kind, $addr, $note, date('c')]);
            header('Location: shop_notes.php?tx=' . urlencode($tx) . '&saved=1'); exit;
        }
    }
}
$q = shop_db()->prepare('SELECT * FROM shop_notes WHERE folder = ? ORDER BY id DESC'); $q->execute([$tx]); $rows = $q->fetchAll();
$kindLabel = ['scheduled' => 'Showing scheduled', 'shown' => 'Shown', 'note' => 'Note'];
$bagType = bag_agreement_type($tx);
sp_head('Shop Homes', $tx);
?>
<?php if ($bagType === 'showing'): ?>
<div class="panel sigbox" style="background:#fff4e5;border-left:3px solid #b26a00">
  <p class="note" style="margin:0">Only a Showing Agreement is on file for this buyer &mdash; it covers just the home(s) named on that signed form. Before scheduling or showing an address it doesn't name, get a new Showing Agreement signed that names it (the address still has to be written onto the form by hand or by the buyer when signing), or move up to an Exclusive Buyer Brokerage Agreement so this isn't needed again.</p>
  <p style="margin:8px 0 0"><a class="btn" href="sign.php?f=<?= BAG_SHOWING_FORM_ID ?>&s=b&tx=<?= urlencode($tx) ?>" target="_top">Send a new Showing Agreement</a> <a class="btn ghost" href="sign.php?f=<?= BAG_EXCLUSIVE_FORM_ID ?>&s=b&tx=<?= urlencode($tx) ?>" target="_top">Move up to Exclusive</a></p>
</div>
<?php elseif ($bagType === null): ?>
<div class="panel sigbox" style="background:#fff4e5;border-left:3px solid #b26a00">
  <p class="note" style="margin:0">No buyer agreement is on file for this buyer yet. Get the Exclusive Buyer Brokerage Agreement or a Showing Agreement signed from <a href="buyer_agreement.php?tx=<?= urlencode($tx) ?>" target="_top">Buyer Agreement</a> before showing homes.</p>
</div>
<?php endif; ?>
<?php require_once __DIR__ . "/shoplib.php"; $sc = shop_criteria($me, $tx); ?>
<div class="panel sigbox" id="findbox">
  <h2 style="margin-top:0">Find homes for this buyer</h2>
<?php if (!$sc['linked'] || shop_summary($sc) === ''): ?>
  <p class="note">No Buyer Property details are saved for this buyer yet. Open the buyer in Leads &amp; Contacts, fill in Buyer Property (price, beds, baths, areas), then come back. You can also type what to search for below.</p>
<?php else: ?>
  <p class="note" style="margin-top:0">From Buyer Property: <strong><?= htmlspecialchars(shop_summary($sc)) ?></strong></p>
<?php endif; ?>
  <div class="wq" style="display:flex;flex-wrap:wrap;gap:10px">
    <label>Min price<input type="text" id="fs_pmin" inputmode="numeric" value="<?= htmlspecialchars($sc['price_min']) ?>" size="9"></label>
    <label>Max price<input type="text" id="fs_pmax" inputmode="numeric" value="<?= htmlspecialchars($sc['price_max']) ?>" size="9"></label>
    <label>Beds (min)<input type="text" id="fs_beds" inputmode="numeric" value="<?= htmlspecialchars($sc['beds']) ?>" size="3"></label>
    <label>Baths (min)<input type="text" id="fs_baths" inputmode="decimal" value="<?= htmlspecialchars($sc['baths']) ?>" size="3"></label>
    <label style="flex:1;min-width:220px">Cities or zip codes (comma separated)<input type="text" id="fs_areas" value="<?= htmlspecialchars($sc['areas']) ?>"></label>
  </div>
  <p><button type="button" class="btn" id="fs_go">Search MLS</button> <span class="note" id="fs_msg"></span></p>
  <div id="fs_res"></div>
</div>
<script>
(function(){
var TX=<?= json_encode($tx) ?>,CSRF=<?= json_encode($_SESSION['csrf']) ?>,go=document.getElementById('fs_go'),msg=document.getElementById('fs_msg'),res=document.getElementById('fs_res');
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function yn(v){return v==='yes'?'HOA/CDD: ':'';}
function money(n){return n?'$'+Number(n).toLocaleString('en-US'):'';}
go.addEventListener('click',function(){
  var q='tx='+encodeURIComponent(TX)+'&price_min='+encodeURIComponent(document.getElementById('fs_pmin').value)+'&price_max='+encodeURIComponent(document.getElementById('fs_pmax').value)+'&beds='+encodeURIComponent(document.getElementById('fs_beds').value)+'&baths='+encodeURIComponent(document.getElementById('fs_baths').value)+'&areas='+encodeURIComponent(document.getElementById('fs_areas').value);
  go.disabled=true;msg.textContent='Searching...';res.innerHTML='';
  fetch('shop_search_api.php?'+q,{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
    go.disabled=false;
    if(!j.ok){msg.textContent=j.msg||'Could not search.';return;}
    msg.textContent=j.total?(j.total+' active home'+(j.total===1?'':'s')+' match'+(j.total>j.rows.length?' (showing the '+j.rows.length+' lowest priced)':'')+'.'):'No active homes match. Try widening the price or area.';
    res.innerHTML=j.rows.map(function(h){
      var addr=[h.address,h.city,[h.state,h.zip].filter(Boolean).join(' ')].filter(Boolean).join(', ');
      var facts=[money(h.price),h.beds?h.beds+' bd':'',h.baths?h.baths+' ba':'',h.sqft?Number(h.sqft).toLocaleString('en-US')+' sq ft':'',h.year_built?'built '+h.year_built:'',h.type,'HOA: '+(h.hoa==='yes'?'Yes':h.hoa==='no'?'No':'?'),'CDD: '+(h.cdd==='yes'?'Yes':h.cdd==='no'?'No':'?')].filter(Boolean).join(' · ');
      var who=[h.agent,h.office,h.phone,h.email].filter(Boolean).join(' · ');
      return '<form method="post" class="wq" style="margin:0 0 10px"><input type="hidden" name="csrf" value="'+esc(CSRF)+'"><input type="hidden" name="tx" value="'+esc(TX)+'"><input type="hidden" name="kind" value="scheduled"><input type="hidden" name="address" value="'+esc(h.address+(h.city?', '+h.city:''))+'"><input type="hidden" name="note" value="MLS '+esc(h.mls)+' '+esc(money(h.price))+(who?'. Listing agent: '+esc(who):'')+'">'
        +'<strong>'+esc(addr)+'</strong> <span class="note">MLS '+esc(h.mls)+'</span><div>'+esc(facts)+'</div><div class="note">'+esc(who)+'</div><button type="submit" class="btn sm">Log a scheduled showing</button></form>';
    }).join('');
  }).catch(function(){go.disabled=false;msg.textContent='Could not search. Please try again.';});
});
})();
</script>
<div class="panel sigbox">
<?php if (!empty($_GET['saved'])): ?><p class="note">Saved.<?= $bagType === 'showing' ? ' If this address isn\'t already named on the Showing Agreement on file, send a new one for it above.' : '' ?></p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <h2 style="margin-top:0">Log a showing or a note</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>">
    <div class="wq">
      <label>Listing address (for a scheduled or shown entry)<input type="text" name="address" placeholder="123 Main St"></label>
      <label>Note<textarea name="note" rows="3" placeholder="What the buyer thought, next steps, etc."></textarea></label>
    </div>
    <p>
      <button type="submit" class="btn" name="kind" value="scheduled">Log a scheduled showing</button>
      <button type="submit" class="btn" name="kind" value="shown">Log a home shown</button>
      <button type="submit" class="btn ghost" name="kind" value="note">Add a note only</button>
    </p>
  </form>
</div>
<div class="panel sigbox">
  <h2 style="margin-top:0">History</h2>
<?php if (!$rows): ?><p class="note">Nothing logged yet.</p>
<?php else: foreach ($rows as $r): ?>
  <p style="margin:0 0 10px"><strong><?= htmlspecialchars($kindLabel[$r['kind']] ?? 'Note') ?></strong><?= $r['address'] !== '' ? ' &middot; ' . htmlspecialchars($r['address']) : '' ?><br>
  <span style="color:#6a777a;font-size:.85rem"><?= htmlspecialchars(date('M j, Y g:ia', strtotime($r['created']))) ?></span>
<?php if ($r['note'] !== ''): ?><br><?= nl2br(htmlspecialchars($r['note'])) ?><?php endif; ?>
  </p>
<?php endforeach; endif; ?>
</div>
<?php sp_foot(); ?>
