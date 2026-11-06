<?php
// Reached from the "Contingencies" step: the full Contract Deadlines panel (Effective Date, deposits, loan application,
// inspection/appraisal/loan/title/HOA contingencies, closing date, and any custom dates) -- this is the only place that
// panel lives now; it used to also show on the buyer record page itself, which was dropped as redundant.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
dl_handle_save($me, 'buyer', $tx, 'contingencies.php?tx=' . urlencode($tx));
require_once __DIR__ . "/subpage.php";
sp_head('Contingencies', $tx);
?>
<?= dl_box($me, 'buyer', $tx, 'contingencies.php?tx=' . urlencode($tx)) ?>
<p><?= dl_contingencies_satisfied($tx) ? '<strong style="color:#1b5e20">All entered contingencies are satisfied.</strong>' : 'The Pipeline Stage ladder will switch to "Contingencies Satisfied" once every contingency (inspection, appraisal, loan approval, title and HOA docs) entered above is checked Done/waived.' ?></p>
<?php sp_foot(); ?>
