<?php
// Buyer Agreement PDFs, shown inside the Buyer Agreement stage screen (or downloaded with &dl=1).
// ?blank=exclusive|showing  -> the blank form       ?rid=ID -> a signed copy on file (the agent's own only)
require __DIR__ . "/guard.php";
require_once __DIR__ . "/baglib.php";
$me = strtolower($_SESSION['email'] ?? '');
$pdf = null; $fn = 'Buyer Agreement.pdf';
if (isset($_GET['blank'])) {
    $k = (string)$_GET['blank'];
    if (isset(BAG_FORMS[$k])) { $pdf = bag_blank_pdf($k); $fn = BAG_FORMS[$k]['title'] . '.pdf'; }
} elseif (isset($_GET['rid'])) {
    $rec = sg_load((string)$_GET['rid']);
    if ($rec && strtolower((string)($rec['agent'] ?? '')) === $me && !empty($rec['completed'])) { $pdf = bag_signed_pdf($rec); $fn = 'Signed - ' . $rec['form'] . '.pdf'; }
}
if ($pdf === null || $pdf === '') { http_response_code(404); header('Content-Type: text/html; charset=utf-8'); exit('<p style="font-family:sans-serif;color:#5a6a6c;padding:16px">This PDF could not be loaded right now. Please try again in a minute.</p>'); }
while (ob_get_level()) @ob_end_clean();
$fn = preg_replace('/[^A-Za-z0-9 ._()\'-]/', '', $fn);
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . $fn . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
echo $pdf;
