<?php
// Streams one form PDF (from the Buyer Forms folder, through the office script) to the signed-in agent's browser.
require __DIR__ . "/guard.php";
@set_time_limit(120);
$id = (string)($_GET['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]{10,80}$/', $id)) { http_response_code(400); exit('Bad request'); }
$ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=file&id=' . urlencode($id));
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 8]);
$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$j = ($body !== false && $code >= 200 && $code < 300) ? json_decode($body, true) : null;
$pdf = (is_array($j) && !empty($j['ok']) && !empty($j['b64'])) ? base64_decode($j['b64'], true) : false;
if ($pdf === false || strlen($pdf) < 500) { http_response_code(502); exit('Could not load the form'); }
header('Content-Type: application/pdf');
header('Cache-Control: private, max-age=300');
echo $pdf;
