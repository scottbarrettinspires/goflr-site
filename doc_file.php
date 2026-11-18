<?php
// Download (default) or inline view (?view=1) of one signed document. Own requests only.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/doclib_file.php";
@set_time_limit(120);
$r = dcf_bytes($_SESSION['email'] ?? '', (string)($_GET['ref'] ?? ''));
if (empty($r['ok'])) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit($r['msg'] ?? 'Not found'); }
while (ob_get_level()) { @ob_end_clean(); }
header('Cache-Control: private, no-store');
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['view']) ? 'inline' : 'attachment') . '; filename="' . $r['name'] . '"');
header('Content-Length: ' . strlen($r['pdf']));
echo $r['pdf'];
