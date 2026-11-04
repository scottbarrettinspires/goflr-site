<?php
// One-time DigiSigner request-format diagnostic (no emails are sent; deletes itself).
require __DIR__ . '/config.php';
header('Content-Type: text/plain'); @unlink(__FILE__);
function mk($txt) { $o = [1 => "<< /Type /Catalog /Pages 2 0 R >>", 2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>", 3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>", 4 => "<< /Length " . strlen($txt) . " >>\nstream\n$txt\nendstream", 5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"];
  $p = "%PDF-1.4\n"; $off = []; foreach ($o as $n => $b) { $off[$n] = strlen($p); $p .= "$n 0 obj\n$b\nendobj\n"; } $x = strlen($p);
  $p .= "xref\n0 6\n0000000000 65535 f \n"; foreach ($off as $f) { $p .= sprintf("%010d 00000 n \n", $f); }
  return $p . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$x\n%%EOF"; }
function up($pdf, $name) { $t = tempnam(sys_get_temp_dir(), 'dg'); file_put_contents($t, $pdf); $ch = curl_init('https://api.digisigner.com/v1/documents');
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_POSTFIELDS => ['file' => new CURLFile($t, 'application/pdf', $name)], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
  $r = curl_exec($ch); curl_close($ch); @unlink($t); $d = json_decode((string)$r, true); return $d['document_id'] ?? null; }
function rq($req) { $ch = curl_init('https://api.digisigner.com/v1/signature_requests');
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($req), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
  $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); return "HTTP $c " . substr((string)$r, 0, 700); }
$tag = up(mk("BT /F1 12 Tf 72 700 Td ([s:buyer1] [d:buyer1]) Tj ET"), 'tagged.pdf');
$plain = up(mk("BT /F1 12 Tf 72 700 Td (No tags here) Tj ET"), 'plain.pdf');
$em = 'scott@myflroffice.com';
$fld = [['type' => 'SIGNATURE', 'page' => 0, 'rectangle' => [100, 100, 270, 130], 'required' => true]];
$fldNoReq = [['type' => 'SIGNATURE', 'page' => 0, 'rectangle' => [100, 100, 270, 130]]];
$base = ['embedded' => false, 'send_emails' => false];
$T = [
 'A tag doc only (text tags on)' => $base + ['use_text_tags' => true, 'hide_text_tags' => true, 'documents' => [['document_id' => $tag, 'subject' => 's', 'message' => 'm', 'signers' => [['email' => $em, 'role' => 'buyer1', 'order' => 1]]]]],
 'B placed fields + role, text tags on' => $base + ['use_text_tags' => true, 'hide_text_tags' => true, 'documents' => [['document_id' => $plain, 'subject' => 's', 'message' => 'm', 'signers' => [['email' => $em, 'role' => 'buyer1', 'order' => 1, 'fields' => $fld]]]]],
 'C placed fields, no role, text tags on' => $base + ['use_text_tags' => true, 'hide_text_tags' => true, 'documents' => [['document_id' => $plain, 'subject' => 's', 'message' => 'm', 'signers' => [['email' => $em, 'order' => 1, 'fields' => $fld]]]]],
 'D placed fields, no role, no required, text tags on' => $base + ['use_text_tags' => true, 'hide_text_tags' => true, 'documents' => [['document_id' => $plain, 'subject' => 's', 'message' => 'm', 'signers' => [['email' => $em, 'order' => 1, 'fields' => $fldNoReq]]]]],
 'E placed fields, no text-tag options at all' => $base + ['documents' => [['document_id' => $plain, 'subject' => 's', 'message' => 'm', 'signers' => [['email' => $em, 'order' => 1, 'fields' => $fldNoReq]]]]],
 'F tagged + placed together (text tags on, placed has no role)' => $base + ['use_text_tags' => true, 'hide_text_tags' => true, 'documents' => [['document_id' => $tag, 'subject' => 's', 'message' => 'm', 'signers' => [['email' => $em, 'role' => 'buyer1', 'order' => 1]]], ['document_id' => $plain, 'subject' => 's', 'message' => 'm', 'signers' => [['email' => $em, 'order' => 1, 'fields' => $fldNoReq]]]]],
 'G same as F, bundled' => $base + ['use_text_tags' => true, 'hide_text_tags' => true, 'send_documents_as_bundle' => true, 'bundle_subject' => 'b', 'bundle_message' => 'm', 'documents' => [['document_id' => $tag, 'subject' => 's', 'message' => 'm', 'signers' => [['email' => $em, 'role' => 'buyer1', 'order' => 1]]], ['document_id' => $plain, 'subject' => 's', 'message' => 'm', 'signers' => [['email' => $em, 'order' => 1, 'fields' => $fldNoReq]]]]],
];
foreach ($T as $k => $req) { echo $k . "\n  " . rq($req) . "\n\n"; }
echo "Done. This page has deleted itself.\n";
