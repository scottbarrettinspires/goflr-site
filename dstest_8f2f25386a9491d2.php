<?php
// One-time DigiSigner size test (deletes itself after it runs).
require __DIR__ . '/config.php';
@set_time_limit(280); @ini_set('memory_limit', '512M');
header('Content-Type: text/plain');
$MB = max(1, min(40, (int)($_GET['mb'] ?? 40)));
@unlink(__FILE__);
$junk = random_bytes($MB * 1024 * 1024);
$objs = [];
$objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
$objs[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
$objs[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
$txt = "BT /F1 18 Tf 72 700 Td (FLR size test - please ignore. About $MB MB.) Tj ET\nBT /F1 12 Tf 72 400 Td ([s:buyer1]  [d:buyer1]) Tj ET";
$objs[4] = "<< /Length " . strlen($txt) . " >>\nstream\n$txt\nendstream";
$objs[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
$objs[6] = "<< /Length " . strlen($junk) . " >>\nstream\n" . $junk . "\nendstream";
$pdf = "%PDF-1.4\n"; $off = [];
foreach ($objs as $n => $o) { $off[$n] = strlen($pdf); $pdf .= "$n 0 obj\n$o\nendobj\n"; }
$x = strlen($pdf);
$pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
foreach ($off as $o) { $pdf .= sprintf("%010d 00000 n \n", $o); }
$pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$x\n%%EOF";
echo "PDF built: " . round(strlen($pdf) / 1048576, 1) . " MB\n";
$tmp = tempnam(sys_get_temp_dir(), 'dst'); file_put_contents($tmp, $pdf);
$ch = curl_init('https://api.digisigner.com/v1/documents');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_POSTFIELDS => ['file' => new CURLFile($tmp, 'application/pdf', 'FLR size test.pdf')], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 250, CURLOPT_CONNECTTIMEOUT => 10]);
$r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); echo "Upload: HTTP $code " . ($r === false ? curl_error($ch) : substr($r, 0, 300)) . "\n"; curl_close($ch); @unlink($tmp);
$d = json_decode((string)$r, true);
if ($code >= 200 && $code < 300 && !empty($d['document_id'])) {
    $req = ['embedded' => false, 'use_text_tags' => true, 'hide_text_tags' => true, 'send_emails' => true, 'documents' => [['document_id' => $d['document_id'], 'subject' => 'FLR size test - please ignore (' . $MB . ' MB)', 'message' => 'Throwaway test of DigiSigner file size. No need to sign.', 'signers' => [['email' => 'scott@myflroffice.com', 'role' => 'buyer1', 'order' => 1]]]]];
    $ch = curl_init('https://api.digisigner.com/v1/signature_requests');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($req), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
    $r2 = curl_exec($ch); echo "Request: HTTP " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . " " . substr((string)$r2, 0, 300) . "\n"; curl_close($ch);
}
echo "Done. This test file has deleted itself.\n";
