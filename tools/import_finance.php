<?php
// CLI only: imports a pull of the Agent Compensation / FLR Revenue Share sheets (same logic as the staff page's import button).
// Usage: php tools/import_finance.php /path/to/finance_pull.json [--by=email@x.com]
if (php_sapi_name() !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../complib.php';
$path = $argv[1] ?? ''; $by = 'import-script';
foreach (array_slice($argv, 2) as $a) { if (strpos($a, '--by=') === 0) $by = substr($a, 5); }
if ($path === '' || !is_file($path)) { fwrite(STDERR, "Usage: php tools/import_finance.php /path/to/finance_pull.json [--by=email]\n"); exit(1); }
$r = fm_import(json_decode((string)file_get_contents($path), true), $by);
if (isset($r['error'])) { fwrite(STDERR, $r['error'] . "\n"); exit(1); }
foreach ($r['tabs'] as $t => $n) echo "$t: $n rows\n";
