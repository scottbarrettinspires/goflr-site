<?php
// Nightly backup: the office script (Apps Script time trigger) posts the shared office key and receives a consistent copy
// of the database, which it saves to Drive. Nothing is served without the key.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/flrdb.php';
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
$j = json_decode((string)file_get_contents('php://input'), true);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_array($j) || !hash_equals(ROSTER_KEY, (string)($j['key'] ?? ''))) { http_response_code(403); echo 'Not allowed'; exit; }
$tmp = flr_db_dir() . '/export-' . bin2hex(random_bytes(6)) . '.sqlite';
try {
    flr_db()->exec('VACUUM INTO ' . flr_db()->quote($tmp));
    header('Content-Type: application/octet-stream'); header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="goflr-' . date('Y-m-d') . '.sqlite"');
    readfile($tmp);
    $GLOBALS['FLR_ACTOR'] = 'nightly backup'; flr_audit('', 'backup_export', '', (string)filesize($tmp) . ' bytes');
} catch (Throwable $e) { http_response_code(500); echo 'Export failed'; }
@unlink($tmp);
