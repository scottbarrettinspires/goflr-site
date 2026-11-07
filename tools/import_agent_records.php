<?php
// CLI only: imports a fresh pull of the Agent Records sheet's "Agents" tab into the server-side agent_records
// table. Same logic as the dashboard's "Import sheet data" button (ar_import() in arlib.php). Never touches the
// Google Sheet or the Apps Script, and never deletes a server record.
// Usage: php tools/import_agent_records.php /path/to/agent_records.json [--by=email@x.com]
if (php_sapi_name() !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../arlib.php';
$path = $argv[1] ?? ''; $by = 'import-script';
foreach (array_slice($argv, 2) as $a) { if (strpos($a, '--by=') === 0) $by = substr($a, 5); }
if ($path === '' || !is_file($path)) { fwrite(STDERR, "Usage: php tools/import_agent_records.php /path/to/agent_records.json [--by=email]\n"); exit(1); }
$s = ar_import(json_decode((string)file_get_contents($path), true), $by);
if (isset($s['error'])) { fwrite(STDERR, $s['error'] . "\n"); exit(1); }
echo "Added: " . count($s['added']) . "\nChanged: " . count($s['changed']) . "\nUnchanged: {$s['unchanged']}\nSkipped (no Agent ID): {$s['skipped']}\n";
foreach ($s['changed'] as $c) echo "  changed: $c\n";
if ($s['unmatched_columns']) echo "Sheet columns ignored: " . implode(', ', $s['unmatched_columns']) . "\n";
if ($s['missing_columns']) echo "Columns not in this pull: " . implode(', ', $s['missing_columns']) . "\n";
if ($s['not_in_pull']) echo "On the server but not in this pull (kept): " . implode(', ', $s['not_in_pull']) . "\n";
