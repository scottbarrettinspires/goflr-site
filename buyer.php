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
            if ($lead) { $new += ld_pack_for_record($lead, ld_load($me)['leads']); } // the whole lead file travels into the record
            $r = sg_script_post(['op' => 'createbuyer', 'email' => $me, 'hint' => $hint, 'name' => $label, 'info' => $new]);
            if (is_array($r) && !empty($r['ok']) && !empty($r['id'])) {
                if ($lead) {
                    $fid = (string)$r['id'];
                    ld_update($me, function (&$db) use ($leadId, $fid, $label) {
                        foreach ($db['leads'] as &$L) { if (($L['id'] ?? '') !== $leadId) continue;
                            $L['roles']['buyer'] = ['sub' => 'client', 'since' => ld_today(), 'touches' => 0, 'last' => '', 'folder' => $fid, 'record' => $label, 'stage' => 1];
                            $L['log'][] = ['d' => ld_today(), 'role' => 'buyer', 'mid' => '', 'note' => 'Buyer record created in My Transactions'];
                        }
                    });
                }
                header('Location: buyer.php?tx=' . urlencode($r['id']) . '&created=1'); exit;
            }
            $err = 'Could not create the buyer record' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.';
        }
    }
}
// The lead behind this record: show it here, or link / add one for records made before the lead system.
require_once __DIR__ . "/leadlib.php";
$link = $buyer ? ld_find_by_folder($me, $tx) : null;
if ($buyer && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'linklead' && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? '')) && !$link) {
    $lid = (string)($_POST['lid'] ?? ''); $bi0 = sg_buyer_info($me, $tx); $bi0 = (!empty($bi0['ok']) && is_array($bi0['info'] ?? null)) ? $bi0['info'] : [];
    $rec = (string)$buyer['name']; $today = ld_today();
    ld_update($me, function (&$db) use ($lid, $tx, $rec, $today, $bi0) {
        $role = ['sub' => 'client', 'since' => $today, 'touches' => 0, 'last' => '', 'folder' => $tx, 'record' => $rec, 'stage' => 1];
        if ($lid === 'new') {
            $p = preg_split('/\s+/', trim((string)($bi0['b1_name'] ?? $rec)), 2);
            array_unshift($db['leads'], ['id' => bin2hex(random_bytes(6)), 'first' => $p[0] ?? '', 'last' => $p[1] ?? '', 'phone' => (string)($bi0['b1_phone'] ?? ''), 'email' => (string)($bi0['b1_email'] ?? ''),
                'source' => '', 'notes' => '', 'added' => $today, 'created' => date('c'), 'roles' => ['buyer' => $role], 'log' => [['d' => $today, 'role' => 'buyer', 'mid' => '', 'note' => 'Added from the buyer record in My Transactions']]]);
            return null;
        }
        foreach ($db['leads'] as &$L) { if (($L['id'] ?? '') === $lid) { unset($L['roles']['new']); $L['roles']['buyer'] = $role; $L['log'][] = ['d' => $today, 'role' => 'buyer', 'mid' => '', 'note' => 'Linked to the buyer record in My Transactions']; } }
        return null;
    });
    header('Location: buyer.php?tx=' . urlencode($tx) . '&linked=1'); exit;
}
if ($buyer && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') !== 'linklead') {
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
            if (!empty($r['ok'])) { $info = $new; $msg .= 'Saved.';
                if ($link) { // fill a missing phone or email on the linked lead from the record
                    $lid = $link['id']; ld_update($me, function (&$db) use ($lid, $new) { foreach ($db['leads'] as &$L) { if (($L['id'] ?? '') !== $lid) continue;
                        if (trim((string)($L['phone'] ?? '')) === '' && $new['b1_phone'] !== '') $L['phone'] = $new['b1_phone'];
                        if (trim((string)($L['email'] ?? '')) === '' && $new['b1_email'] !== '') $L['email'] = $new['b1_email']; } return null; }); }
            } else { $err = 'Could not save: ' . ($r['note'] ?? ''); }
            $info = $new;
        } else { $info = $new; }
    }
}
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
    <a href="my-transactions.php">My Transactions</a>
    <a href="leads.php">Leads</a>
    <a href="forms.php" aria-current="page">Forms</a>
    <a href="training.php">Training</a>
    <a href="directory.php">Directory</a>
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
<?php if ($buyer):
  if ($link) { $pk = ld_pack_for_record($link, ld_load($me)['leads']); $wt = ld_wants_text($link['wants'] ?? []); $nl = array_slice(array_values(array_filter(preg_split('/\r?\n/', (string)($link['notes'] ?? '')), 'strlen')), -3); }
  else { $bi1 = $info; $match = ld_find_match($me, 'buyer', $bi1['b1_email'] ?? '', $bi1['b1_phone'] ?? ''); } ?>
  <div class="panel" style="margin-bottom:14px;border-left:5px solid var(--teal,#036f72)">
<?php if ($link): ?>
    <h2 style="margin:0 0 6px;font-size:1.1rem">From Leads and Contacts</h2>
<?php if (!empty($_GET['linked'])): ?><p class="note">Linked. This buyer now shows in Leads and Contacts under Buyers &rsaquo; In My Transactions.</p><?php endif; ?>
    <p style="margin:0 0 6px;color:#4b5b5d;font-size:.92rem"><?= htmlspecialchars(implode(' · ', array_filter([($pk['lead_source'] ? 'Source: ' . $pk['lead_source'] : ''), ($pk['lead_ref'] ? 'Referred by ' . $pk['lead_ref'] : ''), ($pk['lead_added'] ? 'Lead since ' . date('M j, Y', strtotime($pk['lead_added'])) : ''), (($nh = count($link['log'] ?? [])) . ' history ' . ($nh === 1 ? 'entry' : 'entries'))]))) ?></p>
    <p style="margin:0 0 6px"><strong>Looking for:</strong> <?= $wt !== '' ? htmlspecialchars($wt) : '<span style="color:#6a777a">Nothing recorded yet. Add it on the lead\'s Property Details tab.</span>' ?></p>
<?php if ($nl): ?><p style="margin:0 0 6px;font-size:.9rem"><strong>Latest notes:</strong><br><?= implode('<br>', array_map('htmlspecialchars', $nl)) ?></p><?php endif; ?>
    <p style="margin:8px 0 0"><a class="btn sm ghost" href="leads.php?open=<?= urlencode($link['id']) ?>">Open the lead: notes, history, property details</a></p>
<?php elseif (!empty($match)): ?>
    <h2 style="margin:0 0 6px;font-size:1.1rem">Is this your lead <?= htmlspecialchars(trim(($match['first'] ?? '') . ' ' . ($match['last'] ?? ''))) ?>?</h2>
    <p style="margin:0 0 8px;color:#4b5b5d;font-size:.92rem">A lead in Leads and Contacts has the same <?= strtolower(trim((string)($match['email'] ?? ''))) === strtolower(trim((string)($bi1['b1_email'] ?? ''))) ? 'email' : 'phone' ?>. Link them so the lead's notes and history show here, follow-up pauses while you work the deal, and the buyer moves to your Sphere after closing.</p>
    <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>"><input type="hidden" name="op" value="linklead"><input type="hidden" name="lid" value="<?= htmlspecialchars($match['id']) ?>"><button class="btn sm" type="submit">Yes, link them</button></form>
    <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>"><input type="hidden" name="op" value="linklead"><input type="hidden" name="lid" value="new"><button class="btn sm ghost" type="submit">No, add as a new contact</button></form>
<?php else: ?>
    <h2 style="margin:0 0 6px;font-size:1.1rem">Not in Leads and Contacts yet</h2>
    <p style="margin:0 0 8px;color:#4b5b5d;font-size:.92rem">This record was made outside the lead system. Add the buyer as a contact so their history lives in one place and they move to your Sphere after closing.</p>
    <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>"><input type="hidden" name="op" value="linklead"><input type="hidden" name="lid" value="new"><button class="btn sm" type="submit">Add to Leads and Contacts</button></form>
<?php endif; ?>
  </div>
<?php endif; ?>
  <form method="post" class="panel sigbox bi\" autocomplete="off" data-lpignore="true" data-form-type="other">
    <h2><?= htmlspecialchars($buyer['name'] ?? ($lead ? 'From your lead: ' . trim(($lead['first'] ?? '') . ' ' . ($lead['last'] ?? '')) : 'New buyer')) ?></h2>
<?php if ($msg): ?><p class="note"><?= htmlspecialchars($msg) ?> <a href="wizard.php?tx=<?= urlencode($tx) ?>">Make a contract offer &rarr;</a></p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <?php if ($buyer): ?><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>"><?php else: ?><input type="hidden" name="new" value="1"><?php if ($leadId): ?><input type="hidden" name="lead" value="<?= htmlspecialchars($leadId) ?>"><?php endif; ?><input type="hidden" name="hint" value="<?= htmlspecialchars($hint) ?>"><?php endif; ?>
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
