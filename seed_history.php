<?php
// One-time test data: sample history on the 10 SAMPLE leads for scott@myflroffice.com only. Removed from the site after use.
require __DIR__ . "/guard.php";
require __DIR__ . "/leadlib.php";
header('Content-Type: text/html; charset=utf-8');
$me = strtolower($_SESSION['email'] ?? '');
if ($me !== 'scott@myflroffice.com') { echo '<p>Not available for this account.</p>'; exit; }
$t = ld_today(); $a = function ($n) use ($t) { return ld_add($t, $n); };
$e = function ($d, $role, $mid, $note) { return ['d' => $d, 'role' => $role, 'mid' => $mid, 'note' => $note]; };
$H = [
  's01' => [$e($a(0), 'new', '', 'Lead came in via Website')],
  's02' => [$e($a(-1), 'new', '', 'Lead came in via Facebook'), $e($a(-1), 'new', 'P1-1', 'Called, no answer. Sent the intro text.')],
  's03' => [$e($a(-6), 'new', '', 'Met at the open house on Elm St. Signed in on the sheet.'), $e($a(-6), 'new', 'P1-1', 'Talked 10 minutes. Pre-approved, wants a 3/2 with a pool.'),
            $e($a(-5), 'buyer', '', 'Moved to Buyers > Ready'), $e($a(-5), 'buyer', 'P3-B-1', 'Sent the "dream property" email with 4 listings.'), $e($a(-4), 'buyer', 'P3-B-2', 'Call: confirmed lender, wants showings Saturday.')],
  's04' => [$e($a(-20), 'new', '', 'Lead came in via Zillow'), $e($a(-20), 'new', 'P1-1', 'Called, no answer. Left voicemail and texted.'), $e($a(-19), 'new', 'P1-2', 'Sent the qualifying email.'),
            $e($a(-12), 'buyer', '', 'Reached by phone: buying in the spring. Moved to Buyers > Soon.'), $e($a(-7), 'buyer', 'P4-B-1', 'Sent market update for New Port Richey.')],
  's05' => [$e($a(-3), 'new', '', 'Sign call from 12 Oak St. Wants to know their home value.'), $e($a(-3), 'seller', '', 'Moved to Sellers > Ready')],
  's06' => [$e($a(-30), 'new', '', 'Referred by a neighbor. Buying and selling next year.'), $e($a(-30), 'buyer', '', 'Moved to Buyers > Later'), $e($a(-30), 'seller', '', 'Moved to Sellers > Later'),
            $e($a(-16), 'buyer', 'P5-B-1', 'Sent "staying in touch" email.'), $e($a(-16), 'seller', 'P5-S-1', 'Sent "staying in touch" email.')],
  's07' => [$e($a(-40), 'new', '', 'Lead came in via Landing page'), $e($a(-40), 'new', 'P1-1', 'Called twice, no voicemail set up. Texted.'), $e($a(-38), 'new', 'P1-3', 'Second call, no answer.'),
            $e($a(-25), 'new', '', 'Not reached yet: moved to Buyers > Undetermined'), $e($a(-24), 'buyer', 'U-1', 'Called in the evening, no answer. Texted.'), $e($a(-22), 'buyer', 'U-2', 'Sent "still here to help" email.'), $e($a(-10), 'buyer', 'U-3', 'Saturday morning call, left voicemail.')],
  's08' => [$e($a(-60), 'sphere', '', 'Added to Sphere: neighbor, plays pickleball on Tuesdays.'), $e($a(-31), 'sphere', 'P7-1', 'Texted after pickleball. Asked who is thinking of moving.')],
  's09' => [$e($a(-60), 'sphere', '', 'Added to Sphere: met at the Chamber mixer.')],
  's10' => [$e($a(-90), 'new', '', 'Lead came in via Zillow'), $e($a(-15), 'seller', '', 'Asked not to be contacted. Moved to Do Not Contact.')],
];
$n = ld_update($me, function (&$db) use ($H) {
    $c = 0;
    foreach ($db['leads'] as &$L) {
        $id = $L['id'] ?? ''; if (!isset($H[$id]) || !empty($L['sample_hist'])) continue;
        $log = array_merge($H[$id], (array)($L['log'] ?? []));
        usort($log, function ($x, $y) { return strcmp($x['d'], $y['d']); });
        $L['log'] = $log; $L['sample_hist'] = 1; $c++;
    }
    return $c;
});
echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;padding:24px"><h2>Sample history</h2><p>History added to ' . (int)$n . ' sample lead' . ($n === 1 ? '' : 's') . '.</p><p><a href="leads.php">Open Leads</a></p></body>';
