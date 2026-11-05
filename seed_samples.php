<?php
// One-time test data: 10 SAMPLE leads for scott@myflroffice.com only. Removed from the site after use.
require __DIR__ . "/guard.php";
require __DIR__ . "/leadlib.php";
header('Content-Type: text/html; charset=utf-8');
$me = strtolower($_SESSION['email'] ?? '');
if ($me !== 'scott@myflroffice.com') { echo '<p>Not available for this account.</p>'; exit; }
$t = ld_today(); $a = function ($n) use ($t) { return ld_add($t, $n); };
$L = function ($id, $f, $ph, $src, $added, $roles, $notes = '') { return ['id' => $id, 'first' => $f, 'last' => 'Sample', 'phone' => $ph, 'email' => strtolower($f) . '.sample@example.com', 'source' => $src, 'added' => $added, 'created' => $added . 'T09:00:00-05:00', 'notes' => trim('SAMPLE lead for testing. ' . $notes), 'roles' => $roles + ['new' => ['since' => $added, 'touches' => 0]], 'log' => []]; };
$samples = [
    $L('s01', 'Jamie', '727-555-0101', 'Website', $t, []),
    $L('s02', 'Taylor', '727-555-0102', 'Facebook', $a(-1), ['new' => ['since' => $a(-1), 'touches' => 1, 'last' => $a(-1)]]),
    $L('s03', 'Chris', '727-555-0103', 'Open house', $a(-6), ['buyer' => ['sub' => 'ready', 'since' => $a(-5), 'touches' => 2, 'last' => $a(-4)]], 'Pre-approved, wants a 3/2 with a pool.'),
    $L('s04', 'Morgan', '727-555-0104', 'Zillow', $a(-20), ['buyer' => ['sub' => 'soon', 'since' => $a(-12), 'touches' => 1, 'last' => $a(-7)]]),
    $L('s05', 'Riley', '727-555-0105', 'Sign call', $a(-3), ['seller' => ['sub' => 'ready', 'since' => $a(-3), 'touches' => 0, 'last' => '']], 'Wants a value on their home.'),
    $L('s06', 'Pat', '727-555-0106', 'Referral', $a(-30), ['buyer' => ['sub' => 'later', 'since' => $a(-30), 'touches' => 1, 'last' => $a(-16)], 'seller' => ['sub' => 'later', 'since' => $a(-30), 'touches' => 1, 'last' => $a(-16)]], 'Buying and selling next year.'),
    $L('s07', 'Jordan', '727-555-0107', 'Landing page', $a(-40), ['buyer' => ['sub' => 'unid', 'since' => $a(-25), 'touches' => 3, 'last' => $a(-10)]]),
    $L('s08', 'Casey', '727-555-0108', 'Sphere', $a(-60), ['sphere' => ['sub' => 'a', 'since' => $a(-60), 'touches' => 1, 'last' => $a(-31)]], 'Neighbor, great referral source.'),
    $L('s09', 'Avery', '727-555-0109', 'Sphere', $a(-60), ['sphere' => ['sub' => 'c', 'since' => $a(-60), 'touches' => 0, 'last' => '']]),
    $L('s10', 'Drew', '727-555-0110', 'Zillow', $a(-90), ['seller' => ['sub' => 'dnc', 'since' => $a(-15), 'touches' => 0, 'last' => '']], 'Asked not to be contacted.'),
];
$added = ld_update($me, function (&$db) use ($samples) {
    $have = []; foreach ($db['leads'] as $x) { $have[$x['id']] = 1; }
    $n = 0; foreach ($samples as $s) { if (empty($have[$s['id']])) { $db['leads'][] = $s; $n++; } }
    return $n;
});
echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;padding:24px"><h2>Sample leads</h2><p>' . (int)$added . ' sample lead' . ($added === 1 ? '' : 's') . ' added (last name "Sample").</p><p><a href="leads.php">Open Leads</a> &middot; <a href="home.php">Home</a></p></body>';
