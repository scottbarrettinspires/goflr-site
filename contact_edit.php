<?php
// Reached from the single "Edit contact info" pencil above the Pipeline Stage ladder: one form with both contacts
// (Buyer 1 & 2, or Seller 1 & 2) at once, saved in place -- works even once the main info form elsewhere is locked.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/sigtools.php";
require_once __DIR__ . "/flrdb.php";
require_once __DIR__ . "/leadbox.php";
require_once __DIR__ . "/subpage.php";
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
$side = (($_GET['side'] ?? ($_POST['side'] ?? '')) === 'seller') ? 'seller' : 'buyer';
$label = $side === 'seller' ? 'Seller' : 'Buyer';
$p1 = $side === 'seller' ? 's1' : 'b1'; $p2 = $side === 'seller' ? 's2' : 'b2';
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$r = $side === 'seller' ? sg_seller_info($me, $tx) : sg_buyer_info($me, $tx);
$info = (!empty($r['ok']) && is_array($r['info'] ?? null)) ? $r['info'] : [];
$msg = ''; $err = '';
$n = ((int)($_GET['n'] ?? ($_POST['n'] ?? 1))) === 2 ? 2 : 1; $px = $n === 2 ? $p2 : $p1; $po = $n === 2 ? $p1 : $p2; // which client this form edits
$people = $side === 'seller' ? 'seller' : 'buyer';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $new = $info;
        if (($_POST['op'] ?? '') === 'remove') { // takes the client off this Active Business record only; the lead stays in Leads & Contacts
            $has2 = trim((string)($info[$p2 . '_name'] ?? '')) !== '';
            if ($n === 1 && !$has2) { $err = 'This is the only ' . $people . ' on the record, so there is no one to remove. To remove the whole record, use Mark cancelled or expired.'; }
            else {
                if ($n === 1) { foreach (['_name', '_phone', '_email'] as $f) $new[$p1 . $f] = (string)($info[$p2 . $f] ?? ''); } // the second client moves up to first
                foreach (['_name', '_phone', '_email'] as $f) $new[$p2 . $f] = '';
                $new['updated'] = gmdate('c');
                $r = $side === 'seller' ? sg_seller_info_save($me, $tx, $new) : sg_buyer_info_save($me, $tx, $new);
                if (!empty($r['ok'])) { $info = $new; $msg = 'Removed from this record.'; flr_audit(flr_akey($me), 'contact_removed', $tx, $side . ' client ' . $n); }
                else { $err = 'Could not save: ' . ($r['note'] ?? ''); }
            }
        } else {
            $new[$px . '_name'] = mb_substr(trim((string)($_POST['f_name'] ?? '')), 0, 200);
            $new[$px . '_phone'] = mb_substr(trim((string)($_POST['f_phone'] ?? '')), 0, 200);
            $new[$px . '_email'] = mb_substr(trim((string)($_POST['f_email'] ?? '')), 0, 200);
            if ($new[$px . '_name'] === '') { $err = 'Please enter the ' . strtolower($label) . '\'s name.'; }
            if (!$err && $new[$px . '_email'] !== '' && !filter_var($new[$px . '_email'], FILTER_VALIDATE_EMAIL)) { $err = 'Please check the email address: ' . $new[$px . '_email']; }
            if (!$err && $n === 2 && $new[$px . '_email'] === '') { $err = 'The second ' . strtolower($label) . ' needs an email address (phone is optional).'; }
            if (!$err) {
                $new['updated'] = gmdate('c');
                $r = $side === 'seller' ? sg_seller_info_save($me, $tx, $new) : sg_buyer_info_save($me, $tx, $new);
                if (!empty($r['ok'])) { $info = $new; $msg = 'Saved.'; lb_fill_contact($me, $tx, $side, $new); }
                else { $err = 'Could not save: ' . ($r['note'] ?? ''); }
            } else { $info = $new; }
        }
    }
}
$v = function ($k) use ($info) { return htmlspecialchars((string)($info[$k] ?? '')); };
sp_head('Edit Contact Info', $tx);
?>
<div class="panel sigbox">
<?php if ($msg): ?><p class="note"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
<?php if (!($msg && ($_POST['op'] ?? '') === 'remove')): ?>
  <form method="post" id="cef">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>">
    <input type="hidden" name="side" value="<?= htmlspecialchars($side) ?>">
    <input type="hidden" name="n" value="<?= $n ?>">
    <label><?= htmlspecialchars($label) ?> full name<input type="text" name="f_name" value="<?= $v($px . '_name') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
    <label>Phone<input type="text" name="f_phone" value="<?= $v($px . '_phone') ?>" inputmode="tel" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
    <label>Email<input type="email" name="f_email" value="<?= $v($px . '_email') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
    <p style="display:flex;gap:12px;flex-wrap:wrap;align-items:center"><button type="submit" class="btn">Save</button>
    <button type="button" class="btn ghost" id="cerm" style="color:#b71c1c;border-color:#e6b4b0">Remove client</button><span class="goal" id="cernote">Removes them from this Active Business record only. They stay in Leads &amp; Contacts.</span></p>
  </form>
  <form method="post" id="cerf" hidden>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>"><input type="hidden" name="side" value="<?= htmlspecialchars($side) ?>"><input type="hidden" name="n" value="<?= $n ?>"><input type="hidden" name="op" value="remove">
  </form>
<script>(function(){var b=document.getElementById("cerm");if(!b)return;b.onclick=function(){if(!b.dataset.sure){b.dataset.sure="1";b.textContent="Tap again to remove";setTimeout(function(){delete b.dataset.sure;b.textContent="Remove client";},4000);return;}document.getElementById("cerf").submit();};})();</script>
<?php endif; ?>
</div>
<?php sp_foot(); ?>
<?php if (!empty($_GET['embed'])): ?><script>(function(){function t(s){try{parent.postMessage({flrcontact:1,h:document.documentElement.scrollHeight,saved:s||false},location.origin);}catch(e){}}t(<?= $msg ? 'true' : 'false' ?>);try{new ResizeObserver(function(){t(false);}).observe(document.body);}catch(e){}})();</script><?php endif; ?>
