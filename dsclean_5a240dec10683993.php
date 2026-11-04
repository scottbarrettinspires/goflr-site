<?php
// One-time test-data cleanup: removes the signature-request records, draft index, and logs. Deletes itself.
header('Content-Type: text/plain'); @unlink(__FILE__);
$base = __DIR__ . '/logs'; $n = 0;
foreach (['requests', 'drafts'] as $d) { foreach (glob("$base/$d/*") ?: [] as $f) { if (is_file($f) && @unlink($f)) { $n++; echo "removed $d/" . basename($f) . "\n"; } } }
foreach (['signature-requests.log', 'digisigner-errors.log'] as $f) { if (is_file("$base/$f") && @unlink("$base/$f")) { $n++; echo "removed $f\n"; } }
echo "\nDone. $n items removed. This page has deleted itself.\n";
