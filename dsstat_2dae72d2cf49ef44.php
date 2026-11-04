<?php
// One-time: shows who DigiSigner has on the most recent signature requests (names of fields only, no signing links). Deletes itself.
require __DIR__ . '/sigtools.php';
header('Content-Type: text/plain'); @unlink(__FILE__);
$files = glob(sg_dir() . '/*.json') ?: []; usort($files, function ($a, $b) { return filemtime($b) - filemtime($a); });
foreach (array_slice($files, 0, 4) as $f) {
    $rec = json_decode((string)file_get_contents($f), true); if (!is_array($rec)) continue;
    echo "Request " . ($rec['id'] ?? '?') . " created " . ($rec['created'] ?? '?') . " form: " . ($rec['form'] ?? '?') . "\n  our signers: " . json_encode($rec['signers'] ?? []) . "\n";
    $q = sg_api('GET', '/signature_requests/' . rawurlencode((string)$rec['id']));
    if (!is_array($q)) { echo "  (could not read from DigiSigner)\n\n"; continue; }
    echo "  completed: " . json_encode($q['is_completed'] ?? null) . "  bundle: " . json_encode($q['send_documents_as_bundle'] ?? null) . "\n";
    foreach ((array)($q['documents'] ?? []) as $d) {
        echo "  document \"" . ($d['title'] ?? '') . "\" id " . ($d['document_id'] ?? '') . "\n";
        foreach ((array)($d['signers'] ?? []) as $s) { echo "    - " . ($s['email'] ?? '') . " role=" . ($s['role'] ?? '') . " order=" . ($s['order'] ?? '') . " signed=" . json_encode($s['is_signature_completed'] ?? null) . "\n"; }
    }
    echo "\n";
}
echo "Done. This page has deleted itself.\n";
