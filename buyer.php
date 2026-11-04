<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/sigtools.php";
@set_time_limit(120);
header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
$all = sg_folders($me, $tx) ?: [];
$buyers = []; foreach ($all as $f) { if ($f['group'] === '1 BUYERS / 1 Buyers') $buyers[] = $f; }
$buyer = null; foreach ($buyers as $f) { if ($f['id'] === $tx) $buyer = $f; }
$isNew = (($_GET['new'] ?? ($_POST['new'] ?? '')) === '1');
$hint = (string)($_GET['hint'] ?? ($_POST['hint'] ?? ''));
if ($isNew && !preg_match('/^[A-Za-z0-9_-]{10,80}$/', $hint)) { $isNew = false; }
function bi_missing($n) {
    if (($n['b1_name'] ?? '') === '') return 'Please enter the buyer\'s name.';
    if (($n['b1_phone'] ?? '') === '') return 'Please enter Buyer 1\'s phone number.';
    if (($n['b1_email'] ?? '') === '') return 'Please enter Buyer 1\'s email address.';
    if (($n['b2_name'] ?? '') !== '' && ($n['b2_email'] ?? '') === '') return 'Buyer 2 needs an email address (phone is optional).';
    return '';
}
$FIELDS = ['b1_name', 'b1_phone', 'b1_email', 'b2_name', 'b2_phone', 'b2_email', 'mailing', 'lender', 'preapproval'];
$info = []; $msg = ''; $err = '';
if ($buyer) {
    $r = sg_buyer_info($me, $tx);
    if (!empty($r['ok'])) { $info = is_array($r['info']) ? $r['info'] : []; } else { $err = 'Could not load the saved information: ' . ($r['note'] ?? ''); }
}
if ($isNew && !$buyer && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $new = [];
        foreach ($FIELDS as $k) { $new[$k] = mb_substr(trim((string)($_POST['f_' . $k] ?? '')), 0, 200); }
        $pm = (string)($_POST['payment'] ?? '');
        $new['payment'] = in_array($pm, ['cash', 'conventional', 'fhava'], true) ? $pm : '';
        $mine = strtolower(trim((string)($_SESSION['name'] ?? '')));
        $own = false;
        foreach (['b1', 'b2'] as $p) { if ($mine !== '' && strtolower($new[$p . '_name']) === $mine) { $own = true; $new[$p . '_name'] = ''; $new[$p . '_phone'] = ''; $new[$p . '_email'] = ''; } }
        foreach (['b1_email', 'b2_email'] as $k) { if ($new[$k] !== '' && !filter_var($new[$k], FILTER_VALIDATE_EMAIL)) { $err = 'Please check the email address: ' . $new[$k]; } }
        if (!$err) { $err = bi_missing($new); if ($err && $own && $new['b1_name'] === '') { $err = 'That name is the signed-in agent, not a buyer, so it was removed. Please enter the buyer\'s name.'; } }
        $new['updated'] = gmdate('c');
        $info = $new;
        if (!$err) {
            $label = preg_replace('/\s+/', ' ', trim($new['b1_name'] . ($new['b2_name'] !== '' ? ' & ' . $new['b2_name'] : '')));
            $r = sg_script_post(['op' => 'createbuyer', 'email' => $me, 'hint' => $hint, 'name' => $label, 'info' => $new]);
            if (is_array($r) && !empty($r['ok']) && !empty($r['id'])) { header('Location: buyer.php?tx=' . urlencode($r['id']) . '&created=1'); exit; }
            $err = 'Could not create the buyer record' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.';
        }
    }
}
if ($buyer && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $new = [];
        foreach ($FIELDS as $k) { $new[$k] = mb_substr(trim((string)($_POST['f_' . $k] ?? '')), 0, 200); }
        $pm = (string)($_POST['payment'] ?? '');
        $new['payment'] = in_array($pm, ['cash', 'conventional', 'fhava'], true) ? $pm : '';
        foreach (['b1_email', 'b2_email'] as $k) { if ($new[$k] !== '' && !filter_var($new[$k], FILTER_VALIDATE_EMAIL)) { $err = 'Please check the email address: ' . $new[$k]; } }
        // Browsers sometimes autofill the agent's own name into a buyer field; never keep that.
        $mine = strtolower(trim((string)($_SESSION['name'] ?? '')));
        foreach (['b1', 'b2'] as $p) { if ($mine !== '' && strtolower($new[$p . '_name']) === $mine) { $new[$p . '_name'] = ''; $new[$p . '_phone'] = ''; $new[$p . '_email'] = ''; $msg = 'Your own name was removed from a buyer line. '; } }
        $new['updated'] = gmdate('c');
        if (!$err) { $err = bi_missing($new); }
        if (!$err) {
            $r = sg_buyer_info_save($me, $tx, $new);
            if (!empty($r['ok'])) { $info = $new; $msg .= 'Saved.'; } else { $err = 'Could not save: ' . ($r['note'] ?? ''); }
            $info = $new;
        } else { $info = $new; }
    }
}
$v = function ($k) use ($info) { return htmlspecialchars((string)($info[$k] ?? '')); };
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Buyer Info - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=13">
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
<section class="hero"><h1><?= $isNew && !$buyer ? 'Create New Buyer Record' : 'Buyer Info' ?></h1><p>Enter the buyer once. The Contract Forms Wizard fills it into the packet for you.</p></section>
<main class="wrap">
<?php if (!$buyer && !$isNew): ?>
  <div class="panel sigbox">
    <form method="get"><p><label for="tx"><strong>Which buyer?</strong></label><br>
    <select id="tx" name="tx" class="txsel" onchange="this.form.submit()">
      <option value="">Choose a buyer...</option>
<?php foreach ($buyers as $f): ?>
      <option value="<?= htmlspecialchars($f['id']) ?>"><?= htmlspecialchars($f['name']) ?></option>
<?php endforeach; ?>
    </select></p>
<?php if (!$buyers): ?><p class="note">No buyer folders found yet. Create one in My Transactions &rsaquo; 1 BUYERS &rsaquo; 1 Buyers first.</p><?php endif; ?>
    <noscript><button class="btn" type="submit">Open</button></noscript></form>
  </div>
<?php else: ?>
  <form method="post" class="panel sigbox bi" autocomplete="off" data-lpignore="true" data-form-type="other">
    <h2><?= htmlspecialchars($buyer['name']) ?></h2>
<?php if ($msg): ?><p class="note"><?= htmlspecialchars($msg) ?> <a href="wizard.php?tx=<?= urlencode($tx) ?>">Make a contract offer &rarr;</a></p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <?php if ($buyer): ?><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>"><?php else: ?><input type="hidden" name="new" value="1"><input type="hidden" name="hint" value="<?= htmlspecialchars($hint) ?>"><?php endif; ?>
    <fieldset class="wq"><legend>Buyer 1 (all required)</legend>
      <label>Buyer full name<input type="text" name="f_b1_name" required value="<?= $v('b1_name') ?>" autocomplete="off" data-lpignore="true" data-form-type="other" data-lpignore="true" data-1p-ignore></label>
      <label>Phone<input type="text" name="f_b1_phone" required value="<?= $v('b1_phone') ?>" inputmode="tel" autocomplete="off" data-lpignore="true" data-form-type="other" data-lpignore="true" data-1p-ignore></label>
      <label>Email<input type="email" name="f_b1_email" required value="<?= $v('b1_email') ?>" autocomplete="off" data-lpignore="true" data-form-type="other" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
    <fieldset class="wq"><legend>Buyer 2 (if any: name and email required, phone optional)</legend>
      <label>Buyer full name<input type="text" name="f_b2_name" value="<?= $v('b2_name') ?>" autocomplete="off" data-lpignore="true" data-form-type="other" data-lpignore="true" data-1p-ignore></label>
      <label>Phone<input type="text" name="f_b2_phone" value="<?= $v('b2_phone') ?>" inputmode="tel" autocomplete="off" data-lpignore="true" data-form-type="other" data-lpignore="true" data-1p-ignore></label>
      <label>Email<input type="email" name="f_b2_email" value="<?= $v('b2_email') ?>" autocomplete="off" data-lpignore="true" data-form-type="other" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
    <fieldset class="wq"><legend>Buyer mailing address (used as the address for notices)</legend>
      <label>Street, city, state, zip<input type="text" name="f_mailing" value="<?= $v('mailing') ?>" autocomplete="off" data-lpignore="true" data-form-type="other" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
    <fieldset class="wq"><legend>Method of payment</legend>
<?php foreach (['cash' => 'Cash', 'conventional' => 'Conventional financing', 'fhava' => 'FHA / VA financing'] as $k => $l): ?>
      <label class="wr"><input type="radio" name="payment" value="<?= $k ?>"<?= ($info['payment'] ?? '') === $k ? ' checked' : '' ?>> <?= $l ?></label>
<?php endforeach; ?>
      <label>Lender (if financing)<input type="text" name="f_lender" value="<?= $v('lender') ?>" autocomplete="off" data-lpignore="true" data-form-type="other" data-lpignore="true" data-1p-ignore></label>
      <label>Pre-approval amount<input type="text" name="f_preapproval" value="<?= $v('preapproval') ?>" inputmode="decimal" autocomplete="off" data-lpignore="true" data-form-type="other" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
    <p><button type="submit" class="btn"><?= $buyer ? 'Save buyer info' : 'Create buyer record' ?></button><?php if ($buyer): ?> <a class="btn ghost" href="wizard.php?tx=<?= urlencode($tx) ?>">Make a contract offer</a><?php endif; ?></p>
  </form>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
