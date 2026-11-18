<?php
// Bytes of one signed document: ref "<request id>.m" (the main document) or "<request id>.<n>" (extra document n of the request).
// Library function used by doc_file.php (download / inline view) and doc_open.php (viewer). Returns ['ok'=>true,'pdf'=>bytes,'name'=>filename] or ['ok'=>false,'msg'=>...].
require_once __DIR__ . '/sigtools.php';
function dcf_ref($ref) {
    if (!preg_match('/^([A-Za-z0-9_-]{6,80})\.(m|\d{1,2})$/', (string)$ref, $m)) return null;
    return [$m[1], $m[2]];
}
function dcf_bytes($me, $ref, $skipOwner = false) { // $skipOwner only for a share link the agent created (sharelib.php)
    $p = dcf_ref($ref); if (!$p) return ['ok' => false, 'msg' => 'That document link is not valid.'];
    $rec = sg_load($p[0]);
    if (!$rec || (!$skipOwner && strtolower((string)($rec['agent'] ?? '')) !== strtolower(trim((string)$me)))) return ['ok' => false, 'msg' => 'That document was not found in your business.'];
    $x = $p[1] === 'm' ? null : (($rec['extra'][(int)$p[1]] ?? null));
    if ($p[1] !== 'm' && !$x) return ['ok' => false, 'msg' => 'That document was not found.'];
    $title = $x ? (string)($x['form'] ?? 'Document') : (string)($rec['form'] ?? 'Document');
    $name = 'Signed - ' . trim(preg_replace('/[^A-Za-z0-9 ._()&,\'-]/', ' ', $title)) . '.pdf';
    if (!empty($rec['local']) && !$x) { require_once __DIR__ . '/baglib.php'; $pdf = bag_signed_pdf($rec); return $pdf === null ? ['ok' => false, 'msg' => 'The uploaded copy is missing.'] : ['ok' => true, 'pdf' => $pdf, 'name' => $name]; }
    if (empty($rec['completed'])) return ['ok' => false, 'msg' => 'The signed copy is not ready yet.'];
    $docId = $x ? (string)($x['doc'] ?? '') : (string)($rec['doc'] ?? '');
    $zn = $x ? (string)($x['zname'] ?? ($x['filed']['zname'] ?? '')) : (string)($rec['filed']['zname'] ?? '');
    $ents = sg_signed_entries(['doc' => $docId]);
    if (!$ents) return ['ok' => false, 'msg' => 'The signed copy could not be loaded right now. Please try again in a moment.'];
    foreach ($ents as $e) { if ($zn === '' || $e[0] === $zn) return ['ok' => true, 'pdf' => $e[1], 'name' => $name]; }
    return ['ok' => false, 'msg' => 'That document is not in the signed copy.'];
}
