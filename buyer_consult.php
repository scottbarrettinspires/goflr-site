<?php
// Reached from a lead's "Buyer Consultation" action: choose and prepare the buyer-side agreement before, or during, the
// consultation, then continue on to start the buyer record in My Transactions.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/leadlib.php";
require_once __DIR__ . "/pstage.php"; // pulls in sigtools.php for the Send-for-signature links
require_once __DIR__ . "/subpage.php";
$me = $_SESSION['email'];
$leadId = preg_match('/^[a-z0-9]{2,20}$/', (string)($_GET['lead'] ?? '')) ? (string)$_GET['lead'] : '';
$lead = null;
if ($leadId !== '') { foreach (ld_load($me)['leads'] as $x) { if (($x['id'] ?? '') === $leadId) { $lead = $x; break; } } }
$name = $lead ? trim(($lead['first'] ?? '') . ' ' . ($lead['last'] ?? '')) : '';
sp_head('Buyer Consultation');
?>
<div class="panel sigbox">
  <h2 style="margin-top:0"><?= $name !== '' ? htmlspecialchars($name) : 'Buyer Consultation' ?></h2>
  <p class="note">Send the buyer's agreement for e-signature ahead of time, or download a copy to get it signed in person at the consultation. An Exclusive Buyer Brokerage Agreement covers every home shown from here on; a Showing Agreement only covers the home(s) named on that signed form &mdash; a new one (or moving up to Exclusive) is needed for anything else.</p>
</div>
<?= bag_choice_cards('') ?>
<div class="panel sigbox">
<?php if ($lead): ?>
  <p class="note">Once it's signed, file it to the buyer's record from <a href="signed.php">Signed Documents</a> once the record below exists &mdash; the transaction can be picked when you send it, or assigned afterward.</p>
  <p><a class="btn" href="buyer.php?new=1&lead=<?= urlencode($leadId) ?>">Start the buyer record in My Transactions</a></p>
<?php else: ?>
  <p class="sigerr">That lead could not be found. <a href="leads.php">Back to Leads</a></p>
<?php endif; ?>
</div>
<?php sp_foot(); ?>
