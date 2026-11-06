<?php
// Reached from the "Contingencies" step: the inspection, appraisal, loan approval, title and HOA-docs deadlines only,
// with Done/waived toggles. The ladder's label switches to "Contingencies Satisfied" once every one entered here is done.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
dl_handle_save($me, 'buyer', $tx, 'contingencies.php?tx=' . urlencode($tx), DL_CONTINGENCY_KEYS);
require_once __DIR__ . "/subpage.php";
sp_head('Contingencies', $tx);
?>
<?= dl_box($me, 'buyer', $tx, 'contingencies.php?tx=' . urlencode($tx), DL_CONTINGENCY_KEYS) ?>
<p><?= dl_contingencies_satisfied($tx) ? '<strong style="color:#1b5e20">All entered contingencies are satisfied.</strong>' : 'The Pipeline Stage ladder will switch to "Contingencies Satisfied" once every contingency entered above is checked Done/waived.' ?></p>
<?php sp_foot(); ?>
