<?php
require __DIR__ . "/guard.php";
require_once __DIR__ . "/sigtools.php";
require_once __DIR__ . "/leadlib.php";
@set_time_limit(120);
header('Cache-Control: no-store');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
require_once __DIR__ . "/flrdb.php";
require_once __DIR__ . "/pstage.php";
$all = sg_folders($me, $tx) ?: [];
$buyers = []; foreach ($all as $f) { if ($f['group'] === '1 BUYERS / 1 Buyers') $buyers[] = $f; } // stage-1 folders: where buyer info is entered
$buyer = null; foreach ($buyers as $f) { if ($f['id'] === $tx) $buyer = $f; }
// Every buyer-side record, any stage, from the server's transaction index — for the pipeline-stage list and for viewing
// records that have moved past the Buyer Agreement stage (their info fields stay locked here; the pipeline stage doesn't).
$buyerTx = array_values(array_filter(flr_tx_list($me), function ($t) { return $t['side'] === 'buyer'; }));
$recAny = null; foreach ($buyerTx as $t) { if ($t['folder'] === $tx) $recAny = $t; }
if (!$recAny && !$buyer && preg_match('/^[A-Za-z0-9_-]{10,80}$/', $tx)) {
    foreach ($all as $f) { if ($f['id'] === $tx && flr_tx_side($f['group']) === 'buyer') { list($st, $stn) = flr_tx_stage($f['group']); $recAny = ['folder' => $f['id'], 'name' => $f['name'], 'stage' => $st, 'stage_name' => $stn]; } }
}
$pastStage1 = $recAny && !$buyer;
$isNew = (($_GET['new'] ?? ($_POST['new'] ?? '')) === '1');
$hint = (string)($_GET['hint'] ?? ($_POST['hint'] ?? ''));
// Started from a lead: carry the lead's details into the new buyer record and link the two.
$leadId = preg_match('/^[a-z0-9]{2,20}$/', (string)($_GET['lead'] ?? ($_POST['lead'] ?? ''))) ? (string)($_GET['lead'] ?? ($_POST['lead'] ?? '')) : '';
$lead = null;
if ($leadId !== '') {
    require_once __DIR__ . "/leadlib.php";
    foreach (ld_load($me)['leads'] as $x) { if (($x['id'] ?? '') === $leadId) $lead = $x; }
    if (!$lead) { $leadId = ''; }
    elseif ($isNew && !preg_match('/^[A-Za-z0-9_-]{10,80}$/', $hint)) {
        $jr = flr_get_json(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=mytxroot&email=' . urlencode(strtolower($me)));
        if (is_array($jr) && !empty($jr['ok']) && !empty($jr['id'])) { $hint = (string)$jr['id']; }
    }
}
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
if ($isNew && $lead && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $info = ['b1_name' => trim(($lead['first'] ?? '') . ' ' . ($lead['last'] ?? '')), 'b1_phone' => (string)($lead['phone'] ?? ''), 'b1_email' => (string)($lead['email'] ?? '')];
    $w = (array)($lead['wants'] ?? []); $fin = (array)($w['fin'] ?? []);
    if (!empty($w['lender'])) $info['lender'] = (string)$w['lender'];
    if (!empty($w['preamt'])) $info['preapproval'] = (string)$w['preamt'];
    $pm = ['Cash' => 'cash', 'Conventional' => 'conventional', 'FHA' => 'fhava', 'VA' => 'fhava'];
    if (!empty($fin[0]) && isset($pm[$fin[0]])) $info['payment'] = $pm[$fin[0]];
}
if ($buyer || $pastStage1) {
    $r = sg_buyer_info($me, $tx);
    if (!empty($r['ok'])) { $info = is_array($r['info']) ? $r['info'] : []; } elseif ($buyer) { $err = 'Could not load the saved information: ' . ($r['note'] ?? ''); }
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
            if ($lead) { $new += ld_pack_for_record($lead, ld_load($me)['leads']); } // the whole lead file travels into the record
            $r = sg_script_post(['op' => 'createbuyer', 'email' => $me, 'hint' => $hint, 'name' => $label, 'info' => $new]);
            if (is_array($r) && !empty($r['ok']) && !empty($r['id'])) {
                if ($lead) {
                    $fid = (string)$r['id'];
                    ld_update($me, function (&$db) use ($leadId, $fid, $label) {
                        foreach ($db['leads'] as &$L) { if (($L['id'] ?? '') !== $leadId) continue;
                            $L['roles']['buyer'] = ['sub' => 'client', 'since' => ld_today(), 'touches' => 0, 'last' => '', 'folder' => $fid, 'record' => $label, 'stage' => 1];
                            $L['log'][] = ['d' => ld_today(), 'role' => 'buyer', 'mid' => '', 'note' => 'Buyer record created in Active Business'];
                        }
                    });
                }
                flr_tx_dirty($me); header('Location: buyer.php?tx=' . urlencode($r['id']) . '&created=1'); exit;
            }
            $err = 'Could not create the buyer record' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.';
        }
    }
}
// The lead behind this record: shown in a box at the top; records made before the lead system can be linked or added.
require_once __DIR__ . "/leadbox.php";
require_once __DIR__ . "/dlib.php";
if ($buyer) { lb_handle_link($me, 'buyer', $tx, (string)$buyer['name'], 1, $info, 'buyer.php?tx=' . urlencode($tx)); }
if ($buyer || $pastStage1) { ps_handle_save($me, 'buyer', $tx, 'buyer.php?tx=' . urlencode($tx)); }
if ($pastStage1) { lb_handle_link($me, 'buyer', $tx, (string)$recAny['name'], (int)$recAny['stage'], $info, 'buyer.php?tx=' . urlencode($tx)); }
// Fixing a name/phone/email/payment detail now happens on contact_edit.php, reached from the single pencil above the
// Pipeline Stage ladder -- there is no inline edit form on this page anymore for an existing record.
$v = function ($k) use ($info) { return htmlspecialchars((string)($info[$k] ?? '')); };
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
<title>Buyer Info - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=13">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (!flr_is_broker()): ?>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php" aria-current="page">Active Business</a>
    <?php endif; ?>
    <a href="directory.php">Directory</a>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a> <a href="mls_data.php">MLS Data</a><?php endif; ?>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1><?= $isNew && !$buyer ? 'Create New Buyer Record' : 'Buyer Info' ?></h1><p>Enter the buyer once. The Contract Forms Wizard fills it into the packet for you.</p></section>
<main class="wrap">
<?php if (!$buyer && !$pastStage1 && !$isNew): ?>
  <div class="panel sigbox">
    <h2 style="margin-top:0">Your buyer records</h2>
<?php if (!$buyerTx): ?><p class="note">No buyer folders found yet. Create one in Active Business &rsaquo; 1 BUYERS &rsaquo; 1 Buyers first.</p>
<?php else: ?>
    <?= ps_group_box('buyer', $buyerTx, function ($t) { return 'buyer.php?tx=' . urlencode($t['folder']); }) ?>
<?php endif; ?>
    <p style="margin-top:14px"><a class="btn ghost" href="my-transactions.php">Create a new buyer record in Active Business</a></p>
  </div>
<?php else: ?>
<?php if ($buyer || $pastStage1) echo lb_box($me, 'buyer', $tx, $info); ?>
<?php if ($buyer || $pastStage1) echo ps_box('buyer', $tx, (int)($recAny['stage'] ?? ($buyer ? 1 : 0)), (string)($buyer['name'] ?? $recAny['name'] ?? ''), $info); ?>
<?php if ($buyer || $pastStage1) { require_once __DIR__ . '/signed_panel.php'; echo sg_panel($me, $tx, (string)($buyer['name'] ?? $recAny['name'] ?? '')); } ?>
<?php if ($buyer || $pastStage1): ?>
  <div class="panel sigbox">
    <h2 style="margin-top:0"><?= htmlspecialchars($recAny['name'] ?? $buyer['name'] ?? '') ?> <span style="font-size:.85rem;font-weight:600;color:#036f72;background:#e1f3f2;border-radius:99px;padding:3px 10px;vertical-align:middle"><?= htmlspecialchars($recAny['stage_name'] ?: 'Buyer') ?></span></h2>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
    <p class="note">Use the pencil next to the buyer's name above to edit contact info, mailing address, or payment/lender details. Use the Pipeline Stage ladder to move the deal forward (including Make An Offer) and the Contract Deadlines panel to keep tracking it.</p>
    <p style="margin-top:10px"><a class="btn ghost" href="https://drive.google.com/drive/folders/<?= urlencode($tx) ?>" target="_blank" rel="noopener">Open the transaction folder</a> <a class="btn ghost" href="buyer.php">All buyer records</a></p>
  </div>
<?php else: ?>
  <form method="post" class="panel sigbox bi" autocomplete="off" data-lpignore="true" data-form-type="other">
    <h2><?= htmlspecialchars($lead ? 'From your lead: ' . trim(($lead['first'] ?? '') . ' ' . ($lead['last'] ?? '')) : 'New buyer') ?></h2>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <input type="hidden" name="new" value="1"><?php if ($leadId): ?><input type="hidden" name="lead" value="<?= htmlspecialchars($leadId) ?>"><?php endif; ?><input type="hidden" name="hint" value="<?= htmlspecialchars($hint) ?>">
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
      <div style="display:flex;flex-wrap:wrap;gap:18px;margin-bottom:10px">
<?php foreach (['cash' => 'Cash', 'conventional' => 'Conventional financing', 'fhava' => 'FHA / VA financing'] as $k => $l): ?>
        <label class="wr" style="margin:0"><input type="radio" name="payment" value="<?= $k ?>"<?= ($info['payment'] ?? '') === $k ? ' checked' : '' ?>> <?= $l ?></label>
<?php endforeach; ?>
      </div>
      <label>Lender (if financing)<input type="text" name="f_lender" value="<?= $v('lender') ?>" autocomplete="off" data-lpignore="true" data-form-type="other" data-lpignore="true" data-1p-ignore></label>
      <label>Pre-approval amount<input type="text" name="f_preapproval" value="<?= $v('preapproval') ?>" inputmode="decimal" autocomplete="off" data-lpignore="true" data-form-type="other" data-lpignore="true" data-1p-ignore></label>
    </fieldset>
    <p><button type="submit" class="btn">Create buyer record</button></p>
  </form>
<?php endif; ?>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
