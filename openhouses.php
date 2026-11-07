<?php
// Upcoming Open Houses: the full list, plus the post / edit form (opened from the "+ Post" button on the Home card).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php"; // flr_is_broker()
require_once __DIR__ . "/ohlib.php";
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = co_me();
$err = ''; $form = null;
$editId = (int)($_GET['edit'] ?? ($_POST['id'] ?? 0));
$showForm = isset($_GET['new']) || $editId > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    elseif (($_POST['a'] ?? '') === 'delete') { $err = oh_delete((int)($_POST['id'] ?? 0)); if ($err === '') { header('Location: openhouses.php?removed=1'); exit; } }
    else {
        $showForm = true; $form = $_POST;
        $r = oh_clean($_POST);
        if (is_string($r)) { $err = $r; }
        else {
            list($photo, $pe) = co_save_image($_FILES['photo'] ?? []);
            if ($pe) { $err = $pe; }
            else {
                $res = oh_save($editId, $r, $photo);
                if (is_int($res)) { header('Location: ' . (($_POST['back'] ?? '') === 'home' ? 'home.php#ohcard' : 'openhouses.php?saved=1#oh' . $res)); exit; }
                $err = $res; if ($photo !== '') @unlink(co_img_dir() . '/' . $photo);
            }
        }
    }
}
// An upload bigger than the server allows arrives with no form fields at all.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { $err = 'That photo is too large. Please choose a smaller one.'; $showForm = true; }

$editing = $editId ? oh_get($editId) : null;
if ($editId && (!$editing || $editing['agent_email'] !== $me['email'])) { $showForm = false; if (!$err) $err = 'That open house can only be edited by the agent who posted it.'; }
if ($form === null && $editing) $form = $editing;
$f = function ($k, $d = '') use ($form) { return htmlspecialchars((string)($form[$k] ?? $d)); };
$listings = $showForm ? oh_my_listings($me['email']) : [];
$all = oh_upcoming();

sp_head($showForm ? ($editing ? 'Edit Open House' : 'Post an Open House') : 'Upcoming Open Houses');
echo oh_css();
?>
<style>
.ohform{max-width:640px;background:var(--white);border:1px solid var(--line);border-radius:14px;padding:18px 20px;margin:0 0 22px}
.ohform label{display:block;font-size:.88rem;color:#455355;margin:0 0 12px}.ohform input,.ohform select{display:block;width:100%;box-sizing:border-box;margin-top:4px;padding:9px 11px;border:1px solid var(--line);border-radius:9px;font:inherit;background:#fff}
.ohrow{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:0 14px}
.ohform .btn{border:0;cursor:pointer;font:inherit;font-weight:600}
#ohprev{display:none;max-width:220px;border-radius:10px;margin:6px 0 0}
.ohor{display:flex;align-items:center;gap:10px;color:#5a6a6c;font-size:.82rem;margin:-2px 0 12px}.ohor:before,.ohor:after{content:"";flex:1;border-top:1px solid var(--line)}
</style>
<?php if (!empty($_GET['saved'])): ?><p class="note">Posted. It's on every agent's Home page now.</p><?php endif; ?>
<?php if (!empty($_GET['removed'])): ?><p class="note">Open house removed.</p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>

<?php if ($showForm): ?>
  <form class="ohform" method="post" enctype="multipart/form-data" action="openhouses.php">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <input type="hidden" name="id" value="<?= (int)$editId ?>">
    <input type="hidden" name="back" value="<?= htmlspecialchars((string)($_GET['back'] ?? ($_POST['back'] ?? ''))) ?>">
    <input type="hidden" name="tx_folder" id="ohtx" value="<?= $f('tx_folder') ?>">
<?php if ($listings): ?>
    <label>Choose one of your listings
      <select id="ohpick"><option value="">— Pick a listing —</option><?php foreach ($listings as $fid => $nm): ?><option value="<?= htmlspecialchars($fid) ?>" data-addr="<?= htmlspecialchars($nm) ?>"<?= ($form['tx_folder'] ?? '') === $fid ? ' selected' : '' ?>><?= htmlspecialchars($nm) ?></option><?php endforeach; ?></select></label>
    <div class="ohor">or enter it manually</div>
<?php endif; ?>
    <label>Property address <input type="text" name="address" id="ohaddr" value="<?= $f('address') ?>" required maxlength="160" placeholder="123 Main St, Trinity, FL 34655"></label>
    <div class="ohrow">
      <label>Date <input type="date" name="date" value="<?= $f('date') ?>" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+120 days')) ?>" required></label>
      <label>Starts <input type="time" name="start" value="<?= $f('start', '13:00') ?>" required></label>
      <label>Ends <input type="time" name="end" value="<?= $f('end', '16:00') ?>" required></label>
    </div>
    <div class="ohrow">
      <label>Price <input type="text" name="price" inputmode="numeric" value="<?= isset($form['price']) && $form['price'] !== '' && $form['price'] !== null ? htmlspecialchars(is_numeric($form['price']) ? number_format((int)$form['price']) : (string)$form['price']) : '' ?>" placeholder="425,000"></label>
      <label>MLS # <input type="text" name="mls" value="<?= $f('mls') ?>" maxlength="20" placeholder="TB8312345"></label>
    </div>
    <div class="ohrow">
      <label>Beds <input type="text" name="beds" inputmode="decimal" value="<?= $f('beds') ?>" maxlength="4" placeholder="3"></label>
      <label>Baths <input type="text" name="baths" inputmode="decimal" value="<?= $f('baths') ?>" maxlength="4" placeholder="2.5"></label>
      <label>Garage <input type="text" name="garage" value="<?= $f('garage') ?>" maxlength="20" placeholder="2"></label>
    </div>
    <label>Photo <?= $editing && $editing['photo'] ? '(leave empty to keep the current one)' : '(one, optional)' ?> <input type="file" name="photo" id="ohfile" accept="image/*"><img id="ohprev" alt=""></label>
    <p style="margin:6px 0 0"><button class="btn" type="submit"><?= $editing ? 'Save changes' : 'Post open house' ?></button> <a href="<?= ($_GET['back'] ?? '') === 'home' ? 'home.php' : 'openhouses.php' ?>" style="margin-left:10px;font-size:.9rem">Cancel</a></p>
  </form>
<script>
(function(){var p=document.getElementById("ohpick"),a=document.getElementById("ohaddr"),t=document.getElementById("ohtx");
if(p){p.addEventListener("change",function(){var o=p.options[p.selectedIndex];t.value=p.value;if(p.value)a.value=o.dataset.addr||"";});
  a.addEventListener("input",function(){if(p.value&&a.value!==p.options[p.selectedIndex].dataset.addr){p.value="";t.value="";}});}
var f=document.getElementById("ohfile"),v=document.getElementById("ohprev");f.addEventListener("change",function(){var x=f.files[0];if(!x){v.style.display="none";return;}
  if(x.size>15000000){alert("That photo is too large (15 MB max).");f.value="";return;}v.src=URL.createObjectURL(x);v.style.display="block";});})();
</script>
<?php else: ?>
  <p><a class="btn" href="openhouses.php?new=1">+ Post an open house</a></p>
<?php endif; ?>

<?php if (!$showForm): ?>
<?php if ($all): ?>
  <div class="ohgrid"><?php foreach ($all as $o) echo oh_tile($o, true); ?></div>
<?php else: ?>
  <p class="note">No open houses posted yet. Be the first!</p>
<?php endif; ?>
<?php endif; ?>
<?php sp_foot();
