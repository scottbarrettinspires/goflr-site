<?php
// Reached from the single "Edit contact info" pencil above the Pipeline Stage ladder: one form with both contacts
// (Buyer 1 & 2, or Seller 1 & 2) at once, saved in place -- works even once the main info form elsewhere is locked.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/sigtools.php";
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
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $new = $info;
        foreach ([$p1, $p2] as $p) {
            $new[$p . '_name'] = mb_substr(trim((string)($_POST['f_' . $p . '_name'] ?? '')), 0, 200);
            $new[$p . '_phone'] = mb_substr(trim((string)($_POST['f_' . $p . '_phone'] ?? '')), 0, 200);
            $new[$p . '_email'] = mb_substr(trim((string)($_POST['f_' . $p . '_email'] ?? '')), 0, 200);
        }
        if ($side === 'buyer') {
            $new['mailing'] = mb_substr(trim((string)($_POST['f_mailing'] ?? '')), 0, 200);
            $pm = (string)($_POST['payment'] ?? '');
            $new['payment'] = in_array($pm, ['cash', 'conventional', 'fhava'], true) ? $pm : '';
            $new['lender'] = mb_substr(trim((string)($_POST['f_lender'] ?? '')), 0, 200);
            $new['preapproval'] = mb_substr(trim((string)($_POST['f_preapproval'] ?? '')), 0, 200);
        }
        if ($new[$p1 . '_name'] === '') { $err = 'Please enter ' . $label . ' 1\'s name.'; }
        if (!$err) { foreach ([$p1 . '_email', $p2 . '_email'] as $k) { if ($new[$k] !== '' && !filter_var($new[$k], FILTER_VALIDATE_EMAIL)) { $err = 'Please check the email address: ' . $new[$k]; break; } } }
        if (!$err && $new[$p2 . '_name'] !== '' && $new[$p2 . '_email'] === '') { $err = $label . ' 2 needs an email address (phone is optional).'; }
        if (!$err) {
            $new['updated'] = gmdate('c');
            $r = $side === 'seller' ? sg_seller_info_save($me, $tx, $new) : sg_buyer_info_save($me, $tx, $new);
            if (!empty($r['ok'])) { $info = $new; $msg = 'Saved.'; lb_fill_contact($me, $tx, $side, $new); }
            else { $err = 'Could not save: ' . ($r['note'] ?? ''); }
        } else { $info = $new; }
    }
}
$v = function ($k) use ($info) { return htmlspecialchars((string)($info[$k] ?? '')); };
sp_head('Edit Contact Info', $tx);
?>
<div class="panel sigbox">
<?php if ($msg): ?><p class="note"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>">
    <input type="hidden" name="side" value="<?= htmlspecialchars($side) ?>">
    <fieldset class="wq"><legend><?= htmlspecialchars($label) ?> 1 (name required)</legend>
      <label><?= htmlspecialchars($label) ?> full name<input type="text" name="f_<?= $p1 ?>_name" value="<?= $v($p1 . '_name') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
      <label>Phone<input type="text" name="f_<?= $p1 ?>_phone" value="<?= $v($p1 . '_phone') ?>" inputmode="tel" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
      <label>Email<input type="email" name="f_<?= $p1 ?>_email" value="<?= $v($p1 . '_email') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
    <fieldset class="wq"><legend><?= htmlspecialchars($label) ?> 2 (if any: name and email required, phone optional)</legend>
      <label><?= htmlspecialchars($label) ?> full name<input type="text" name="f_<?= $p2 ?>_name" value="<?= $v($p2 . '_name') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
      <label>Phone<input type="text" name="f_<?= $p2 ?>_phone" value="<?= $v($p2 . '_phone') ?>" inputmode="tel" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
      <label>Email<input type="email" name="f_<?= $p2 ?>_email" value="<?= $v($p2 . '_email') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
<?php if ($side === 'buyer'): ?>
    <fieldset class="wq"><legend>Buyer mailing address (used as the address for notices)</legend>
      <label>Street, city, state, zip<input type="text" name="f_mailing" value="<?= $v('mailing') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
    <fieldset class="wq"><legend>Method of payment</legend>
      <div style="display:flex;flex-wrap:wrap;gap:18px;margin-bottom:10px">
<?php foreach (['cash' => 'Cash', 'conventional' => 'Conventional financing', 'fhava' => 'FHA / VA financing'] as $k => $l): ?>
        <label class="wr" style="margin:0"><input type="radio" name="payment" value="<?= $k ?>"<?= ($info['payment'] ?? '') === $k ? ' checked' : '' ?>> <?= $l ?></label>
<?php endforeach; ?>
      </div>
      <label>Lender (if financing)<input type="text" name="f_lender" value="<?= $v('lender') ?>" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
      <label>Pre-approval amount<input type="text" name="f_preapproval" value="<?= $v('preapproval') ?>" inputmode="decimal" autocomplete="off" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
<?php endif; ?>
    <p><button type="submit" class="btn">Save contact info</button></p>
  </form>
</div>
<?php sp_foot(); ?>
