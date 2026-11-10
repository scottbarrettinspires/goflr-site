<?php
// Shared helpers for DigiSigner requests: storage, status checks, signed-PDF download and filing into a transaction folder.
require_once __DIR__ . '/config.php';

function sg_dir() {
    $d = __DIR__ . '/logs/requests';
    if (!is_dir($d)) { @mkdir($d, 0755, true); }
    $h = __DIR__ . '/logs/.htaccess';
    if (!is_file($h)) { @file_put_contents($h, "Require all denied\n"); }
    return $d;
}
function sg_path($id) { return sg_dir() . '/' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$id) . '.json'; }
function sg_load($id) { $p = sg_path($id); if (!is_file($p)) return null; $j = json_decode((string)file_get_contents($p), true); return is_array($j) ? $j : null; }
function sg_save($id, $rec) { @file_put_contents(sg_path($id), json_encode($rec), LOCK_EX); }
function sg_for_agent($email) {
    $out = [];
    foreach (glob(sg_dir() . '/*.json') ?: [] as $f) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j) && strtolower($j['agent'] ?? '') === strtolower($email)) $out[] = $j;
    }
    usort($out, function ($a, $b) { return strcmp($b['created'] ?? '', $a['created'] ?? ''); });
    return $out;
}

function sg_api($method, $path, $raw = false) {
    $ch = curl_init('https://api.digisigner.com/v1' . $path);
    curl_setopt_array($ch, [CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_CUSTOMREQUEST => $method]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($r === false || $code < 200 || $code >= 300) return null;
    return $raw ? $r : json_decode($r, true);
}

// Ask DigiSigner (never trust a callback body) whether the request is finished. Updates the stored record.
function sg_refresh(&$rec) {
    if (!empty($rec['completed']) || strpos((string)($rec['id'] ?? ''), 'SAMPLE-') === 0) return true;
    $q = sg_api('GET', '/signature_requests/' . rawurlencode($rec['id']));
    if (!is_array($q)) return false;
    $rec['signers_status'] = [];
    foreach (($q['documents'][0]['signers'] ?? []) as $s) { $rec['signers_status'][$s['email'] ?? ''] = !empty($s['is_signature_completed']); }
    if (!empty($q['is_completed'])) { $rec['completed'] = true; if (empty($rec['completed_at'])) $rec['completed_at'] = gmdate('c'); $rec['doc'] = $q['documents'][0]['document_id'] ?? ($rec['doc'] ?? ''); }
    sg_save($rec['id'], $rec);
    return true;
}

function sg_signed_pdf($rec) {
    if (empty($rec['completed']) || empty($rec['doc'])) return null;
    $pdf = sg_api('GET', '/documents/' . rawurlencode($rec['doc']), true);
    return ($pdf !== null && strlen($pdf) > 500) ? $pdf : null;
}

function sg_script_post($payload) {
    $payload['key'] = ROSTER_KEY;
    $ch = curl_init(ROSTER_URL);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => ['Content-Type: text/plain'], CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    return is_array($j) ? $j : null;
}

function sg_folders($email, $hint = '') {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=folders&email=' . urlencode(strtolower($email)) . ($hint !== '' ? '&hint=' . urlencode($hint) : ''));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    $GLOBALS['sg_folders_root'] = is_array($j) ? (string)($j['root'] ?? '') . (empty($j['tops']) ? '' : ' [' . implode(', ', $j['tops']) . ']') : '';
    $GLOBALS['sg_folders_note'] = is_array($j) ? (string)($j['note'] ?? '') : 'The office script did not answer (is the newest version deployed?).';
    return (is_array($j) && !empty($j['ok'])) ? $j['folders'] : null;
}

// File the signed PDF into the chosen transaction folder (brokerage copy). Never overwrites. Returns [ok, message].
// Save PDF bytes into the transaction record (offer-address folder, or the chosen folder). Never overwrites.
function sg_save_bytes($rec, $pdf, $name) {
    if (strlen($pdf) < 500 || substr($pdf, 0, 4) !== '%PDF') return [false, 'The signed copy is not a readable PDF.'];
    if (strlen($pdf) > 32 * 1024 * 1024) return [false, '"' . $name . '" is ' . round(strlen($pdf) / 1048576) . ' MB, too large to file automatically. Use Download signed copy and add it to the transaction folder by hand.'];
    $post = ['op' => 'save', 'email' => $rec['agent'], 'name' => $name, 'b64' => base64_encode($pdf)];
    if (!empty($rec['offer_address'])) { $post['offerAddress'] = $rec['offer_address']; if (!empty($rec['hint'])) { $post['hint'] = $rec['hint']; } } else { $post['folderId'] = $rec['folder']['id']; }
    $r = sg_script_post($post);
    if (!is_array($r) || empty($r['ok'])) return [false, 'Could not save to the transaction folder' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.'];
    $label = !empty($rec['offer_address']) ? '1 BUYERS / 2 Contract Offers / ' . ($r['folder'] ?? $rec['offer_address']) : ($rec['folder']['label'] ?? '');
    return [true, ['name' => $r['name'], 'url' => $r['url'], 'folder_url' => (string)($r['folderUrl'] ?? ''), 'at' => gmdate('c'), 'folder' => $label]];
}
function sg_file_pdf($rec, $docId, $form) {
    $pdf = sg_api('GET', '/documents/' . rawurlencode($docId), true);
    if ($pdf === null || strlen($pdf) < 500) return [false, 'The signed copy is not ready to download yet.'];
    return sg_save_bytes($rec, $pdf, 'Signed - ' . $form . ' - ' . gmdate('Y-m-d') . '.pdf');
}
// DigiSigner returns ONE signed PDF for a single document, or a ZIP of the separate signed PDFs when several documents were sent together.
// Returns a list of [entry name or null, bytes], or null when it is not available.
function sg_signed_entries($rec) {
    $raw = sg_api('GET', '/documents/' . rawurlencode((string)($rec['doc'] ?? '')), true);
    if ($raw === null || strlen($raw) < 500) return null;
    if (substr($raw, 0, 2) !== 'PK') return [[null, $raw]];
    if (!class_exists('ZipArchive')) return null;
    $tmp = tempnam(sys_get_temp_dir(), 'flrz'); file_put_contents($tmp, $raw); unset($raw);
    $z = new ZipArchive(); $out = [];
    if ($z->open($tmp) === true) {
        for ($i = 0; $i < $z->numFiles; $i++) { $n = $z->getNameIndex($i); if (preg_match('/\.pdf$/i', $n)) { $out[] = [basename($n), $z->getFromIndex($i)]; } }
        $z->close();
    }
    @unlink($tmp);
    usort($out, function ($a, $b) { $pa = stripos($a[0], 'Contract Packet') === 0 ? 0 : (stripos($a[0], 'Buyer Disclosures') === 0 ? 1 : 2); $pb = stripos($b[0], 'Contract Packet') === 0 ? 0 : (stripos($b[0], 'Buyer Disclosures') === 0 ? 1 : 2); return $pa - $pb; });
    return $out ?: null;
}
function sg_entry_title($zname) {
    $b = preg_replace('/\.pdf$/i', '', $zname);
    $b = preg_replace('/\s*\((draft|ready to sign)[^)]*\)/i', '', $b);
    $b = trim(preg_replace('/\s*-\s*\d{4}-\d{2}-\d{2}(\s*\(\d+\))?\s*$/', '', $b));
    return $b !== '' ? $b : 'Document';
}

// A request can carry extra documents (e.g. Buyer Disclosures sent with the packet); each is filed as its own PDF.
function sg_file(&$rec) {
    // Only one filing at a time per request (several page loads can ask at once, which used to file the same document repeatedly).
    $lk = @fopen(sg_path($rec['id']) . '.lock', 'c');
    if ($lk && !@flock($lk, LOCK_EX | LOCK_NB)) { @fclose($lk); return [false, 'Filing is already in progress.']; }
    $fresh = sg_load($rec['id']); if (is_array($fresh)) { $rec = array_merge($rec, array_intersect_key($fresh, array_flip(['filed', 'extra', 'completed', 'doc', 'zip']))); }
    $res = sg_file_inner($rec);
    if ($lk) { @flock($lk, LOCK_UN); @fclose($lk); }
    return $res;
}
function sg_log_filed($rec, $r) { @file_put_contents(__DIR__ . '/logs/signature-requests.log', json_encode(['t' => gmdate('c'), 'filed' => $rec['id'], 'agent' => $rec['agent'], 'folder' => $r['folder'], 'file' => $r['name']]) . "\n", FILE_APPEND | LOCK_EX); }
function sg_file_inner(&$rec) {
    if (!empty($rec['local'])) { // uploaded signed copy: file the stored PDF (no DigiSigner document)
        if (!empty($rec['filed']['url'])) return [true, 'Already filed.'];
        require_once __DIR__ . '/baglib.php'; $pdf = bag_signed_pdf($rec); if ($pdf === null) return [false, 'The uploaded copy is missing.'];
        list($ok, $r) = sg_save_bytes($rec, $pdf, 'Signed - ' . $rec['form'] . ' - ' . gmdate('Y-m-d') . '.pdf'); if (!$ok) return [false, $r];
        $rec['filed'] = $r; sg_save($rec['id'], $rec); sg_log_filed($rec, $r); return [true, 'Filed in ' . $r['folder'] . '.'];
    }
    $pending = false; foreach (($rec['extra'] ?? []) as $x) { if (empty($x['filed'])) $pending = true; }
    if (!empty($rec['filed']) && !$pending) return [true, 'Already filed.'];
    if (!sg_can_file($rec)) return [false, 'No transaction chosen.'];
    $ents = sg_signed_entries($rec);
    if (!$ents) return [false, 'The signed copy is not ready to download yet.'];
    $date = gmdate('Y-m-d'); $addr = (string)($rec['offer_address'] ?? '');
    if (count($ents) > 1 || $ents[0][0] !== null) { // several documents were signed together: file each as its own PDF
        $rec['zip'] = true; $first = true; $seen = [];
        foreach ($rec['extra'] ?? [] as $x) { if (!empty($x['zname'])) $seen[$x['zname']] = 1; }
        foreach ($ents as $en) {
            list($zn, $bytes) = $en; $title = sg_entry_title($zn);
            $isMain = $first; $first = false;
            $done = $isMain ? (!empty($rec['filed']) && ($rec['filed']['zname'] ?? '') === $zn) : isset($seen[$zn]);
            if ($isMain && !empty($rec['filed']) && empty($rec['filed']['zname'])) { $done = true; } // an older single filing
            if ($done) continue;
            list($ok, $r) = sg_save_bytes($rec, $bytes, 'Signed - ' . $title . ($addr !== '' ? ' - ' . $addr : '') . ' - ' . $date . '.pdf');
            if (!$ok) { sg_save($rec['id'], $rec); return [false, $r]; }
            $r['zname'] = $zn;
            if ($isMain) { $rec['filed'] = $r; $rec['form'] = $title . ($addr !== '' ? ' - ' . $addr : ''); }
            else { $rec['extra'][] = ['form' => $title . ($addr !== '' ? ' - ' . $addr : ''), 'doc' => $rec['doc'], 'zname' => $zn, 'filed' => $r]; }
            sg_save($rec['id'], $rec); sg_log_filed($rec, $r);
        }
        return [true, 'Filed in ' . ($rec['filed']['folder'] ?? 'the transaction record') . '.'];
    }
    if (empty($rec['filed'])) {
        list($ok, $r) = sg_save_bytes($rec, $ents[0][1], 'Signed - ' . $rec['form'] . ' - ' . $date . '.pdf');
        if (!$ok) return [false, $r];
        $rec['filed'] = $r; sg_save($rec['id'], $rec); sg_log_filed($rec, $r);
    }
    foreach (($rec['extra'] ?? []) as $i => $x) {
        if (!empty($x['filed'])) continue;
        list($ok, $r) = sg_file_pdf($rec, $x['doc'], $x['form']);
        if (!$ok) return [false, $r];
        $rec['extra'][$i]['filed'] = $r; sg_save($rec['id'], $rec); sg_log_filed($rec, $r);
    }
    return [true, 'Filed in ' . $rec['filed']['folder'] . '.'];
}

// One signature request carrying several documents. $items = [[pdf, filename, subject, message], ...]. Returns ids and the document ids in order.
if (!defined('SG_RECT_FROM_TOP')) { define('SG_RECT_FROM_TOP', false); } // DigiSigner rectangle = [x1,y1,x2,y2]; flip to true if boxes land upside down on a test
// Seller-provided PDFs with signature boxes placed in the browser -> DigiSigner items. Returns ['err' => msg] or ['items' => ..., 'meta' => ...].
function sg_seller_items($docsIn, $emails, $addr, $me, $name) {
    $ROLE = ['buyer1' => 1, 'buyer2' => 1, 'agent' => 1, 'broker' => 1]; $SGN = null; $TYPES = ['SIGNATURE' => 1, 'INITIALS' => 1, 'DATE' => 1];
    if (!$docsIn || count($docsIn) > 8) return ['err' => 'Add between 1 and 8 seller documents.'];
    $items = []; $meta = [];
    foreach ($docsIn as $d) {
        $title = trim(preg_replace('/[^A-Za-z0-9 ._()&,\'-]/', ' ', preg_replace('/\.pdf$/i', '', (string)($d['name'] ?? 'Seller Disclosure'))));
        $title = trim(preg_replace('/\s+/', ' ', $title)) ?: 'Seller Disclosure';
        $pdf = base64_decode((string)($d['b64'] ?? ''), true);
        if ($pdf === false || strlen($pdf) < 500 || substr($pdf, 0, 4) !== '%PDF') return ['err' => '"' . $title . '" is not a readable PDF.'];
        $by = [];
        foreach ((array)($d['fields'] ?? []) as $f) {
            $r = (string)($f['role'] ?? ''); $ty = (string)($f['type'] ?? '');
            if (!isset($ROLE[$r]) || !isset($TYPES[$ty])) continue;
            $pg = (int)($f['page'] ?? 0); $H = (float)($f['ph'] ?? 792);
            $x = (float)($f['x'] ?? 0); $y = (float)($f['y'] ?? 0); $w = (float)($f['w'] ?? 0); $h = (float)($f['h'] ?? 0);
            if ($w < 5 || $h < 5 || $pg < 0 || $pg > 400) continue;
            $y1 = SG_RECT_FROM_TOP ? $y : $H - ($y + $h); $y2 = SG_RECT_FROM_TOP ? $y + $h : $H - $y;
            $by[$r][] = ['type' => $ty, 'page' => $pg, 'rectangle' => [(int)round($x), (int)round($y1), (int)round($x + $w), (int)round($y2)], 'required' => true];
        }
        if (!$by) return ['err' => 'Place at least one signature box on "' . $title . '".'];
        $sg = [];
        foreach ($by as $r => $fields) {
            $e = trim((string)($emails[$r] ?? ''));
            if (($r === 'agent' || $r === 'broker') && !filter_var($e, FILTER_VALIDATE_EMAIL)) { if ($SGN === null) $SGN = sg_signers($me); $e = trim((string)($SGN[$r]['email'] ?? '')); }
            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) return ['err' => 'Please check the email address for ' . ['buyer1' => 'Buyer 1', 'buyer2' => 'Buyer 2', 'agent' => 'the agent', 'broker' => 'the broker'][$r] . '.'];
            $sg[] = ['email' => $e, 'role' => $r, 'fields' => $fields];
        }
        $items[] = [$pdf, $title . '.pdf', 'Florida Luxury Realty: please sign ' . $title . ' for ' . $addr, $name . ' (Florida Luxury Realty) has sent you ' . $title . ' for ' . $addr . ' to review and sign. If you have questions, reply to ' . $me . '.', $sg];
        $meta[] = ['form' => $title . ' - ' . $addr, 'what' => $title, 'file' => $title . '.pdf'];
    }
    return ['items' => $items, 'meta' => $meta];
}

function sg_send_multi($items, $signers, $bundle = null) {
    $docs = []; $ids = [];
    foreach ($items as $it) {
        $sg = []; foreach (($it[4] ?? $signers) as $s) { $o = ['email' => $s['email'], 'role' => $s['role'], 'order' => 1]; if (!empty($s['fields'])) { $o['fields'] = $s['fields']; } $sg[] = $o; } // each document can have its own signers
        $tmp = tempnam(sys_get_temp_dir(), 'flrsig'); file_put_contents($tmp, $it[0]);
        $ch = curl_init('https://api.digisigner.com/v1/documents');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_POSTFIELDS => ['file' => new CURLFile($tmp, 'application/pdf', $it[1])], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 8]);
        $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); @unlink($tmp);
        $d = json_decode((string)$r, true);
        if ($code < 200 || $code >= 300 || empty($d['document_id'])) return ['ok' => false, 'msg' => 'DigiSigner did not accept "' . $it[1] . '" (code ' . $code . ').'];
        $ids[] = $d['document_id'];
        $docs[] = ['document_id' => $d['document_id'], 'subject' => $it[2], 'message' => $it[3], 'signers' => $sg];
    }
    $req = ['embedded' => false, 'use_text_tags' => true, 'hide_text_tags' => true, 'send_emails' => true, 'documents' => $docs];
    $try = [$req];
    if (count($docs) > 1) { // one email; DigiSigner merges a bundle into ONE document that is signed and filed as one PDF // one email that carries every document (signed and returned as separate documents); fall back to one email per document if DigiSigner refuses
        $b = $req; $b['send_documents_as_bundle'] = true;
        $b['bundle_subject'] = $bundle['subject'] ?? 'Florida Luxury Realty: please sign your documents';
        $b['bundle_message'] = $bundle['message'] ?? 'Please review and sign the attached documents.';
        array_unshift($try, $b);
    }
    $code = 0; $q = null;
    foreach ($try as $rq) {
        $ch = curl_init('https://api.digisigner.com/v1/signature_requests');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($rq), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8]);
        $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $q = json_decode((string)$r, true);
        if ($code >= 200 && $code < 300 && !empty($q['signature_request_id'])) break;
    }
    if ($code < 200 || $code >= 300 || empty($q['signature_request_id'])) {
        $why = substr(trim(strip_tags((string)$r)), 0, 500); if ($why === '') $why = 'no details returned';
        sg_dir(); @file_put_contents(__DIR__ . '/logs/digisigner-errors.log', gmdate('c') . ' ' . $code . ' ' . substr((string)$r, 0, 600) . "\n", FILE_APPEND | LOCK_EX);
        return ['ok' => false, 'msg' => 'DigiSigner could not create the request (code ' . $code . ')' . ($why !== '' ? ': ' . htmlspecialchars($why) : '') . '. Check that each role matches a signature tag in the documents.'];
    }
    $rids = []; foreach ((array)($q['documents'] ?? []) as $dd) { if (!empty($dd['document_id'])) $rids[] = $dd['document_id']; }
    return ['ok' => true, 'id' => $q['signature_request_id'], 'docs' => $rids ?: $ids, 'merged' => count($rids) > 0 && count($rids) < count($ids)];
}

// Brokerage-side signers (the signed-in agent and the broker) from the Agents tab through the office script.
function sg_signers($email) {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=signers&email=' . urlencode(strtolower($email)));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    $o = ['agent' => ['name' => '', 'email' => ''], 'broker' => ['name' => '', 'email' => '']];
    if (is_array($j) && !empty($j['ok'])) { foreach (['agent', 'broker'] as $k) { if (is_array($j[$k] ?? null)) { $o[$k] = ['name' => (string)($j[$k]['name'] ?? ''), 'email' => (string)($j[$k]['email'] ?? '')]; } } }
    if ($o['agent']['name'] === '') $o['agent']['name'] = trim((string)($_SESSION['name'] ?? ''));
    if ($o['agent']['email'] === '') $o['agent']['email'] = strtolower($email);
    return $o;
}

// Contract Forms Wizard rules, read from the Wizard Rules sheet through the office script.
function sg_wizard_rules() {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=wizard');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    $GLOBALS['sg_wizard_note'] = $b === false ? 'no answer from the office script (' . $cerr . ')' : (is_array($j) ? (string)($j['note'] ?? '') : 'the office script answered with something that is not data (HTTP ' . $code . '): ' . substr(trim(strip_tags((string)$b)), 0, 160));
    return (is_array($j) && !empty($j['ok'])) ? $j : null;
}

// A request can be filed automatically when it names a destination: a chosen folder, or an address under Contract Offers.
function sg_can_file($rec) { return !empty($rec['folder']['id']) || !empty($rec['offer_address']); }

// Upload a PDF to DigiSigner and create a signature request using the hidden text tags. Returns ['ok'=>true,'id'=>..,'doc'=>..] or ['ok'=>false,'msg'=>..].
function sg_send_pdf($pdf, $filename, $subject, $message, $signers) {
    $tmp = tempnam(sys_get_temp_dir(), 'flrsig'); file_put_contents($tmp, $pdf);
    $ch = curl_init('https://api.digisigner.com/v1/documents');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_POSTFIELDS => ['file' => new CURLFile($tmp, 'application/pdf', $filename)], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 8]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); @unlink($tmp);
    $d = json_decode((string)$r, true);
    if ($code < 200 || $code >= 300 || empty($d['document_id'])) return ['ok' => false, 'msg' => 'DigiSigner did not accept the packet (code ' . $code . ').'];
    $sg = []; foreach ($signers as $s) { $sg[] = ['email' => $s['email'], 'role' => $s['role'], 'order' => (int)($s['order'] ?? 1)]; }
    $req = ['embedded' => false, 'use_text_tags' => true, 'hide_text_tags' => true, 'send_emails' => true, 'documents' => [['document_id' => $d['document_id'], 'subject' => $subject, 'message' => $message, 'signers' => $sg]]];
    $ch = curl_init('https://api.digisigner.com/v1/signature_requests');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($req), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $q = json_decode((string)$r, true);
    if ($code < 200 || $code >= 300 || empty($q['signature_request_id'])) return ['ok' => false, 'msg' => 'DigiSigner could not create the request (code ' . $code . '). Check that each role matches a signature tag in the packet.'];
    return ['ok' => true, 'id' => $q['signature_request_id'], 'doc' => $d['document_id']];
}

function sg_packets($email, $folderId) {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=packets&email=' . urlencode(strtolower($email)) . '&folderId=' . urlencode($folderId));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    return (is_array($j) && !empty($j['ok'])) ? $j['files'] : null;
}

function sg_packet_pdf($email, $fileId) {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=packetfile&email=' . urlencode(strtolower($email)) . '&id=' . urlencode($fileId));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    $pdf = (is_array($j) && !empty($j['ok']) && !empty($j['b64'])) ? base64_decode($j['b64'], true) : false;
    return ($pdf !== false && strlen($pdf) > 500) ? ['name' => $j['name'], 'pdf' => $pdf] : null;
}

// Buyer Info: one data file per buyer folder, read and written through the office script.
function sg_buyer_info($email, $folderId) {
    if (strpos((string)$folderId, 'SAMPLE-') === 0) { require_once __DIR__ . '/samplelib.php'; return ['ok' => true, 'info' => sd_info($folderId)]; } // sample data (Settings > Sample data)
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=buyerinfo&email=' . urlencode(strtolower($email)) . '&folderId=' . urlencode($folderId));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    return is_array($j) ? $j : ['ok' => false, 'note' => 'The office script did not answer (is the newest version deployed?).'];
}
// Seller records keep "Seller Info.json" in the listing folder (office script v26 reads and saves it).
function sg_seller_info($email, $folderId) {
    if (strpos((string)$folderId, 'SAMPLE-') === 0) { require_once __DIR__ . '/samplelib.php'; return ['ok' => true, 'info' => sd_info($folderId)]; } // sample data (Settings > Sample data)
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=sellerinfo&email=' . urlencode(strtolower($email)) . '&folderId=' . urlencode($folderId));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch); $j = $b === false ? null : json_decode($b, true);
    if (is_array($j) && isset($j['active']) && !isset($j['ok'])) return ['ok' => false, 'old' => true, 'note' => 'The office script needs the v26 update to read seller records.'];
    return is_array($j) ? $j : ['ok' => false, 'note' => 'The office script did not answer.'];
}
// A saved record's details changed: drop the Notifications cache for it so "missing info" updates right away.
function sg_info_changed($folderId) { try { require_once __DIR__ . '/flrdb.php'; flr_db()->prepare('DELETE FROM kv WHERE k = ?')->execute(['nt_info_' . $folderId]); } catch (Throwable $e) {} }
function sg_seller_info_save($email, $folderId, $info) {
    sg_info_changed($folderId);
    $r = sg_script_post(['op' => 'savesellerinfo', 'email' => $email, 'folderId' => $folderId, 'info' => $info]);
    if (is_array($r) && (!isset($r['ok']) || ($r['note'] ?? '') === 'not allowed')) return ['ok' => false, 'note' => 'The office script needs the v26 update to save seller records.'];
    return is_array($r) ? $r : ['ok' => false, 'note' => 'The office script did not answer.'];
}
function sg_buyer_info_save($email, $folderId, $info) {
    sg_info_changed($folderId);
    $r = sg_script_post(['op' => 'savebuyerinfo', 'email' => $email, 'folderId' => $folderId, 'info' => $info]);
    return is_array($r) ? $r : ['ok' => false, 'note' => 'The office script did not answer (is the newest version deployed?).'];
}

// The agent's license number (from the Agents tab), cached in the session.
function sg_license($email) {
    if (isset($_SESSION['license'])) return (string)$_SESSION['license'];
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=license&email=' . urlencode(strtolower($email)));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    $lic = (is_array($j) && !empty($j['ok'])) ? preg_replace('/[^A-Za-z0-9 \-]/', '', (string)($j['license'] ?? '')) : '';
    if (is_array($j) && !empty($j['ok'])) $_SESSION['license'] = $lic;
    return $lic;
}

// Merge a few fields (like the property address) into a buyer's saved info. Best effort.
function sg_buyer_info_merge($email, $folderId, $fields) {
    sg_info_changed($folderId);
    $r = sg_buyer_info($email, $folderId);
    if (empty($r['ok'])) return false;
    $info = is_array($r['info'] ?? null) ? $r['info'] : [];
    foreach ($fields as $k => $v) { if ($v !== '' && $v !== null) $info[$k] = mb_substr((string)$v, 0, 200); }
    $w = sg_buyer_info_save($email, $folderId, $info);
    return !empty($w['ok']);
}

// ---- Drafts: unsigned documents kept in Contract Offers / [address] / Drafts - Not Signed (index here so Home can list unsent ones) ----
function sg_ddir() { $d = dirname(sg_dir()) . '/drafts'; if (!is_dir($d)) { @mkdir($d, 0755, true); } return $d; }
function sg_dpath($agent, $addr) { return sg_ddir() . '/' . md5(strtolower($agent) . '|' . strtolower($addr)) . '.json'; }
function sg_draft_mark($agent, $addr, $tx, $sent) {
    $p = sg_dpath($agent, $addr); $j = is_file($p) ? json_decode((string)file_get_contents($p), true) : null;
    if (!is_array($j)) $j = [];
    $j = array_merge($j, ['agent' => strtolower($agent), 'addr' => $addr, 'sent' => $sent, 'tx' => $tx ?: ($j['tx'] ?? ''), 't' => gmdate('c')]);
    @file_put_contents($p, json_encode($j), LOCK_EX);
}
function sg_draft_unsent($agent) {
    $out = [];
    foreach (glob(sg_ddir() . '/*.json') ?: [] as $f) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j) && ($j['agent'] ?? '') === strtolower($agent) && empty($j['sent'])) $out[] = $j;
    }
    usort($out, function ($a, $b) { return strcmp($b['t'] ?? '', $a['t'] ?? ''); });
    return $out;
}
function sg_drafts($email, $hint, $addr) {
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=drafts&email=' . urlencode(strtolower($email)) . '&hint=' . urlencode($hint) . '&address=' . urlencode($addr));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    return (is_array($j) && !empty($j['ok'])) ? $j : null;
}

// Check several open requests with DigiSigner at the same time (newest first, recent ones only), so pages stay fast.
function sg_refresh_many(&$recs, $max = 8) {
    $idx = []; $cut = time() - 45 * 86400;
    foreach ($recs as $i => $r) { if (empty($r['completed']) && strpos((string)($r['id'] ?? ''), 'SAMPLE-') !== 0 && strtotime($r['created'] ?? '') > $cut) { $idx[] = $i; if (count($idx) >= $max) break; } }
    if (!$idx) return;
    $mh = curl_multi_init(); $hs = [];
    foreach ($idx as $i) {
        $ch = curl_init('https://api.digisigner.com/v1/signature_requests/' . rawurlencode($recs[$i]['id']));
        curl_setopt_array($ch, [CURLOPT_USERPWD => DIGISIGNER_KEY . ':', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 6]);
        curl_multi_add_handle($mh, $ch); $hs[$i] = $ch;
    }
    do { $st = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1.0); } while ($run && $st === CURLM_OK);
    foreach ($hs as $i => $ch) {
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $q = json_decode((string)curl_multi_getcontent($ch), true);
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
        if ($code < 200 || $code >= 300 || !is_array($q)) continue;
        $r = $recs[$i]; $r['signers_status'] = [];
        foreach (($q['documents'][0]['signers'] ?? []) as $s) { $r['signers_status'][$s['email'] ?? ''] = !empty($s['is_signature_completed']); }
        if (!empty($q['is_completed'])) { $r['completed'] = true; if (empty($r['completed_at'])) $r['completed_at'] = gmdate('c'); $r['doc'] = $q['documents'][0]['document_id'] ?? ($r['doc'] ?? ''); }
        sg_save($r['id'], $r); $recs[$i] = $r;
    }
    curl_multi_close($mh);
}
