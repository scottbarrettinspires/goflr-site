<?php
// CLI only: imports a fresh pull of the Agent Records sheet's "Agents" tab into the server-side agent_records
// table (arlib.php). Does not touch the Google Sheet, the Apps Script, or anything Lindsay uses day to day --
// this feeds the standalone server-side dashboard only.
//
// Input: a JSON file shaped {"header": [...sheet column headers in order...], "keys": [...matching snake_case
// keys...], "records": [ {key: value, ...}, ... ]} -- the shape Claude produces from a fresh Google Sheets API
// read of the sheet (header/keys stay zipped together so columns are matched by header TEXT below, not by
// position, in case the sheet's column order ever changes between runs).
//
// Usage: php tools/import_agent_records.php /path/to/agent_records_normalized.json [--by=email@x.com]
//
// Safe to re-run: every row is an upsert keyed on Agent ID, so running this again with a newer pull just
// updates changed fields. Prints a before/after summary; makes no changes if the file can't be read or parsed.
if (php_sapi_name() !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../arlib.php';

$path = $argv[1] ?? '';
$by = 'import-script';
foreach (array_slice($argv, 2) as $a) { if (strpos($a, '--by=') === 0) $by = substr($a, 5); }
if ($path === '' || !is_file($path)) { fwrite(STDERR, "Usage: php tools/import_agent_records.php /path/to/agent_records_normalized.json [--by=email]\n"); exit(1); }

$data = json_decode((string)file_get_contents($path), true);
if (!is_array($data) || empty($data['header']) || empty($data['keys']) || !isset($data['records'])) {
    fwrite(STDERR, "Not the expected {header, keys, records} shape.\n"); exit(1);
}
$header = $data['header']; $jsonKeys = $data['keys']; $records = $data['records'];
if (count($header) !== count($jsonKeys)) { fwrite(STDERR, "header/keys length mismatch.\n"); exit(1); }

// Map sheet header text -> our AR_FIELDS key, so a reordered sheet still matches correctly.
$headerToArKey = array_flip(AR_FIELDS); // 'Agent ID' => 'agent_id', etc.
$posToArKey = []; $unmatched = [];
foreach ($header as $i => $h) {
    $h = trim((string)$h);
    if (isset($headerToArKey[$h])) { $posToArKey[$i] = $headerToArKey[$h]; }
    else { $unmatched[] = $h; }
}
$missingCols = array_diff(array_keys(AR_FIELDS), array_values($posToArKey));
if ($unmatched) { echo "Sheet columns with no matching AR_FIELDS entry (ignored): " . implode(', ', $unmatched) . "\n"; }
if ($missingCols) { echo "AR_FIELDS entries not found in this sheet pull (left blank): " . implode(', ', $missingCols) . "\n"; }

$before = []; foreach (ar_all() as $r) { $before[$r['agent_id']] = $r['fields']; }

$added = 0; $changed = 0; $unchanged = 0; $skipped = 0;
foreach ($records as $rec) {
    $fields = [];
    foreach ($posToArKey as $i => $arKey) { $fields[$arKey] = trim((string)($rec[$jsonKeys[$i]] ?? '')); }
    $agentId = trim((string)($fields['agent_id'] ?? ''));
    if ($agentId === '') { $skipped++; continue; } // no Agent ID -- can't key a row without one
    if (!isset($before[$agentId])) { $added++; }
    elseif ($before[$agentId] != $fields) { $changed++; }
    else { $unchanged++; }
    ar_save($agentId, $fields, $by);
}
$gone = array_diff(array_keys($before), array_column($records, 'agent_id'));

echo "Import complete.\n";
echo "  Added:     $added\n";
echo "  Changed:   $changed\n";
echo "  Unchanged: $unchanged\n";
echo "  Skipped (no Agent ID): $skipped\n";
if ($gone) { echo "  In server DB but not in this pull (left as-is, not deleted): " . implode(', ', $gone) . "\n"; }
