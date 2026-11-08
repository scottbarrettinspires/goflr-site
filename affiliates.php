<?php
// Settings > Favorite Affiliated Businesses: the agent's go-to title companies, lenders, inspectors, sign post companies,
// photographers and sign post installers. + ADD in each category; edit or remove any. Data: afflib.php.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/afflib.php";
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
$err = ''; $keep = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $err = 'This page expired. Please reload and try again.';
    elseif (($_POST['op'] ?? '') === 'delete') { $err = af_delete($me, (int)($_POST['id'] ?? 0)); if ($err === '') { header('Location: affiliates.php?ok=del#' . urlencode((string)($_POST['cat'] ?? ''))); exit; } }
    else { list($id, $err) = af_save($me, $_POST); if ($err === '') { header('Location: affiliates.php?ok=saved#a' . $id); exit; } $keep = $_POST; }
}
$list = af_list($me);
$h = function ($x) { return htmlspecialchars((string)$x); };
sp_head('Favorite Affiliated Businesses');
?>
<style>
.afw{max-width:900px}
.afintro{color:#5a6a6c;margin:0 0 16px}
.afc{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:16px;margin:0 0 14px;scroll-margin-top:16px}
.afh{display:flex;align-items:center;gap:10px;margin:0 0 10px}.afh h2{margin:0;font-size:1.1rem}.afh .n{font-size:.78rem;font-weight:700;background:#eef3f3;color:#4b5b5d;border-radius:99px;padding:2px 8px}
.afadd{font:inherit;display:inline-flex;align-items:center;line-height:1;padding:4px 9px;border-radius:6px;border:0;background:#e07b39;color:#fff;font-size:.74rem;font-weight:800;letter-spacing:.02em;cursor:pointer}.afadd:hover{background:#c96a2c}
.afr{display:flex;gap:12px;align-items:flex-start;padding:12px;border:1px solid var(--line);border-radius:12px;margin:0 0 8px;scroll-margin-top:16px}
.afr:target{border-color:var(--teal);box-shadow:0 0 0 3px rgba(3,111,114,.12)}
.afr .who{flex:1;min-width:0;display:block}.afr .nm{font-weight:700}.afr .meta{font-size:.85rem;color:#4b5b5d;margin-top:2px;display:flex;flex-wrap:wrap;gap:2px 14px}.afr .meta a{color:var(--teal)}
.afr .nt{font-size:.85rem;color:#5a6a6c;margin-top:4px;white-space:pre-line}
.afq{display:flex;gap:4px;flex:none}.afq a,.afq button{display:inline-flex;width:30px;height:30px;border-radius:9px;align-items:center;justify-content:center;background:var(--teal-soft);color:var(--teal);text-decoration:none;font-size:.85rem;border:0;cursor:pointer;font-family:inherit}
.afempty{color:#6a777a;font-size:.9rem;margin:0;padding:6px 0}
dialog.afd{border:0;border-radius:16px;padding:0;width:min(520px,calc(100vw - 32px));box-shadow:0 20px 60px rgba(0,0,0,.25)}dialog.afd::backdrop{background:rgba(15,30,32,.45)}
.afd form{padding:20px 22px}.afd h2{margin:0 0 12px;font-size:1.2rem}
.afg{display:grid;grid-template-columns:1fr 1fr;gap:10px}.afd label{display:block;font-size:.85rem;color:#455355}.afd .full{grid-column:1/-1}
.afd input,.afd select,.afd textarea{display:block;width:100%;box-sizing:border-box;margin-top:3px;padding:9px 11px;border:1px solid var(--line);border-radius:9px;font:inherit;background:#fff}
.afd textarea{min-height:70px;resize:vertical}
.afd .acts{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:16px}.afd .acts button{font:inherit;font-weight:600;border-radius:10px;padding:9px 18px;cursor:pointer}
.afd .go{background:var(--teal);color:#fff;border:0}.afd .cancel{background:#fff;border:1px solid var(--line);color:#3c4646}.afd .del{background:#fff;border:1px solid #f3c7c4;color:#b71c1c}
@media(max-width:520px){.afg{grid-template-columns:1fr}.afr{flex-wrap:wrap}.afq{margin-left:auto}}
</style>
<div class="afw">
  <p class="afintro">Keep your go-to vendors in one place. Use <b>+ ADD</b> in any category to save another one.</p>
<?php if ($err): ?>  <p class="sigerr"><?= $h($err) ?></p>
<?php elseif (!empty($_GET['ok'])): ?>  <p class="note"><?= $_GET['ok'] === 'del' ? 'Removed.' : 'Saved.' ?></p>
<?php endif; ?>
<?php foreach (AF_CATS as $k => $label): $rows = $list[$k]; ?>
  <div class="afc" id="<?= $h($k) ?>">
    <div class="afh"><h2><?= $h($label) ?></h2><span class="n"><?= count($rows) ?></span><button type="button" class="afadd" data-cat="<?= $h($k) ?>">+ ADD</button></div>
<?php if (!$rows): ?>    <p class="afempty">None saved yet.</p>
<?php endif; foreach ($rows as $r): $dig = preg_replace('/[^0-9+]/', '', (string)$r['phone']); ?>
    <div class="afr" id="a<?= (int)$r['id'] ?>">
      <div class="who"><div class="nm"><?= $h($r['name']) ?></div>
        <div class="meta"><?php if ($r['contact'] !== ''): ?><span><?= $h($r['contact']) ?></span><?php endif; ?><?php if ($r['phone'] !== ''): ?><span><?= $h($r['phone']) ?></span><?php endif; ?><?php if ($r['email'] !== ''): ?><a href="mailto:<?= $h($r['email']) ?>"><?= $h($r['email']) ?></a><?php endif; ?><?php if ($r['website'] !== ''): ?><a href="<?= $h($r['website']) ?>" target="_blank" rel="noopener"><?= $h(preg_replace('~^https?://(www\.)?~i', '', rtrim($r['website'], '/'))) ?></a><?php endif; ?></div>
<?php if ($r['notes'] !== ''): ?>        <div class="nt"><?= $h($r['notes']) ?></div>
<?php endif; ?>
      </div>
      <span class="afq"><?php if ($dig !== ''): ?><a href="tel:<?= $h($dig) ?>" title="Call">&#9742;</a><a href="sms:<?= $h($dig) ?>" title="Text"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg></a><?php endif; ?><?php if ($r['email'] !== ''): ?><a href="mailto:<?= $h($r['email']) ?>" title="Email">&#9993;</a><?php endif; ?><button type="button" class="afedit" title="Edit" aria-label="Edit <?= $h($r['name']) ?>" data-r="<?= $h(json_encode($r)) ?>">&#9998;</button></span>
    </div>
<?php endforeach; ?>
  </div>
<?php endforeach; ?>
</div>
<dialog class="afd" id="afd"><form method="post" id="aff">
  <h2 id="aft">Add a business</h2>
  <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="save"><input type="hidden" name="id" id="af_id">
  <div class="afg">
    <label class="full">Category<select name="cat" id="af_cat"><?php foreach (AF_CATS as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></select></label>
    <label class="full">Business name<input name="name" id="af_name" maxlength="120" required></label>
    <label>Contact person<input name="contact" id="af_contact" maxlength="120"></label>
    <label>Phone<input name="phone" id="af_phone" inputmode="tel" maxlength="40"></label>
    <label>Email<input name="email" id="af_email" type="email" maxlength="160"></label>
    <label>Website<input name="website" id="af_website" maxlength="200" placeholder="example.com"></label>
    <label class="full">Notes<textarea name="notes" id="af_notes" maxlength="600" placeholder="Pricing, turnaround, who to ask for..."></textarea></label>
  </div>
  <div class="acts"><span><button type="button" class="del" id="af_del" hidden>Remove</button></span><span><button type="button" class="cancel" id="af_x">Cancel</button> <button type="submit" class="go">Save</button></span></div>
</form></dialog>
<form method="post" id="afdelf" hidden><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="delete"><input type="hidden" name="id" id="afdel_id"><input type="hidden" name="cat" id="afdel_cat"></form>
<script>(function(){var d=document.getElementById("afd"),F=["id","cat","name","contact","phone","email","website","notes"],del=document.getElementById("af_del");
function open(r){F.forEach(function(f){document.getElementById("af_"+f).value=r[f]||"";});document.getElementById("aft").textContent=r.id?"Edit business":"Add a business";del.hidden=!r.id;del.dataset.sure="";del.textContent="Remove";d.showModal();document.getElementById("af_name").focus();}
document.querySelectorAll(".afadd").forEach(function(b){b.onclick=function(){open({cat:b.dataset.cat});};});
document.querySelectorAll(".afedit").forEach(function(b){b.onclick=function(){open(JSON.parse(b.dataset.r));};});
document.getElementById("af_x").onclick=function(){d.close();};
del.onclick=function(){if(!del.dataset.sure){del.dataset.sure="1";del.textContent="Tap again to remove";return;}document.getElementById("afdel_id").value=document.getElementById("af_id").value;document.getElementById("afdel_cat").value=document.getElementById("af_cat").value;document.getElementById("afdelf").submit();};
<?php if ($keep): ?>open(<?= json_encode(array_intersect_key($keep, array_flip(['id', 'cat', 'name', 'contact', 'phone', 'email', 'website', 'notes']))) ?>);<?php endif; ?>
})();</script>
<?php sp_foot();
