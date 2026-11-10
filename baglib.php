<?php
// Buyer Agreement stage: the blank forms (Exclusive Buyer Brokerage Agreement, Showing Agreement), the signed copy on
// file for a buyer record (from DigiSigner or uploaded by the agent), and requests still out for signature.
// Plain functions so the page, its PDF endpoint (bagdoc.php) and assistants share one set of rules.
require_once __DIR__ . '/sigtools.php';
require_once __DIR__ . '/pstage.php';

const BAG_FORMS = [
    'exclusive' => ['id' => BAG_EXCLUSIVE_FORM_ID, 'title' => 'Exclusive Buyer Brokerage Agreement', 'note' => 'Covers every home shown to this buyer from here on. No further agreement needed.'],
    'showing'   => ['id' => BAG_SHOWING_FORM_ID, 'title' => 'Showing Agreement', 'note' => 'Covers only the home(s) named on the signed form. A home it doesn\'t name needs a new Showing Agreement, or an upgrade to Exclusive.'],
];
function bag_kind_of($form) {
    $t = strtolower((string)$form);
    if (strpos($t, 'exclusive buyer brokerage') !== false) return 'exclusive';
    if (strpos($t, 'showing agreement') !== false && strpos($t, 'amendment') === false) return 'showing';
    return null;
}
// Where uploaded signed copies are kept on the server (outside the web root, next to the database).
function bag_updir() {
    $d = flr_db_dir() . '/signed_uploads'; if (!is_dir($d)) @mkdir($d, 0750, true); return $d;
}
// The blank form as PDF bytes, cached on the server for a day (fetched through the office script from the Forms folder).
function bag_blank_pdf($kind) {
    if (!isset(BAG_FORMS[$kind])) return null;
    $d = flr_db_dir() . '/formcache'; if (!is_dir($d)) @mkdir($d, 0750, true);
    $p = $d . '/' . BAG_FORMS[$kind]['id'] . '.pdf';
    if (is_file($p) && filemtime($p) > time() - 86400) return (string)file_get_contents($p);
    $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=file&id=' . urlencode(BAG_FORMS[$kind]['id']));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode($b, true);
    $pdf = (is_array($j) && !empty($j['ok']) && !empty($j['b64'])) ? base64_decode($j['b64'], true) : false;
    if ($pdf === false || strlen($pdf) < 500 || substr($pdf, 0, 4) !== '%PDF') return is_file($p) ? (string)file_get_contents($p) : null;
    @file_put_contents($p, $pdf, LOCK_EX);
    return $pdf;
}
// For one buyer record: ['signed' => newest signed agreement record or null, 'all_signed' => [...], 'pending' => [...]].
function bag_state($me, $tx) {
    $signed = []; $pending = [];
    foreach (sg_for_agent($me) as $r) {
        if ((string)($r['folder']['id'] ?? '') !== $tx || !bag_kind_of($r['form'] ?? '')) continue;
        if (!empty($r['completed'])) $signed[] = $r;
        elseif (strtotime($r['created'] ?? '') > time() - 45 * 86400) $pending[] = $r;
    }
    usort($signed, function ($a, $b) { return strcmp($b['completed_at'] ?? $b['created'] ?? '', $a['completed_at'] ?? $a['created'] ?? ''); });
    return ['signed' => $signed[0] ?? null, 'all_signed' => $signed, 'pending' => $pending];
}
// The signed copy as PDF bytes (uploaded copy kept here, or DigiSigner's signed document).
function bag_signed_pdf(array $rec) {
    if (!empty($rec['local'])) { $p = bag_updir() . '/' . basename($rec['local']); return is_file($p) ? (string)file_get_contents($p) : null; }
    return sg_signed_pdf($rec);
}
// Save a signed copy the agent uploads ($file = one $_FILES entry). It becomes this record's signed agreement, is filed
// to the transaction folder in Drive when possible, and shows everywhere a DigiSigner-signed copy does.
// Returns [record id, ''] or ['', error message].
function bag_upload($me, $tx, $kind, array $file, $signedOn = '') {
    if (!isset(BAG_FORMS[$kind])) return ['', 'Choose which agreement this is.'];
    $own = null; foreach (flr_tx_list($me) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') $own = $t; }
    if (!$own) return ['', 'That buyer record was not found in your business.'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['', 'Choose the signed PDF to upload.'];
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) return ['', 'The upload did not finish. Please try again with a smaller file.'];
    if ((int)($file['size'] ?? 0) > 25 * 1024 * 1024) return ['', 'That file is larger than 25 MB. Please upload a smaller PDF.'];
    $pdf = (string)@file_get_contents((string)$file['tmp_name']);
    if (strlen($pdf) < 500 || substr($pdf, 0, 4) !== '%PDF') return ['', 'Please upload the signed copy as a PDF.'];
    if ($signedOn !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $signedOn) || $signedOn > date('Y-m-d'))) return ['', 'Please enter the date it was signed (today or earlier).'];
    $id = 'UP' . bin2hex(random_bytes(8));
    if (!@file_put_contents(bag_updir() . '/' . $id . '.pdf', $pdf, LOCK_EX)) return ['', 'The signed copy could not be saved. Please try again.'];
    $at = $signedOn !== '' ? $signedOn . 'T12:00:00' . date('P') : date('c');
    $rec = ['id' => $id, 'agent' => $me, 'form' => BAG_FORMS[$kind]['title'], 'form_id' => BAG_FORMS[$kind]['id'], 'doc' => '', 'created' => $at, 'completed' => true,
        'completed_at' => $at, 'uploaded' => true, 'local' => $id . '.pdf', 'signers' => [], 'seen' => true,
        'folder' => ['id' => $tx, 'label' => trim((string)$own['grp'] . ' / ' . (string)$own['name'], ' /')]];
    list($ok, $r) = sg_save_bytes($rec, $pdf, 'Signed - ' . BAG_FORMS[$kind]['title'] . ' - ' . ($signedOn ?: date('Y-m-d')) . '.pdf');
    $rec['filed'] = $ok ? $r : ['name' => 'Signed - ' . BAG_FORMS[$kind]['title'] . '.pdf', 'url' => '', 'at' => gmdate('c'), 'folder' => 'Saved here only (' . (is_string($r) ? $r : 'Drive filing failed') . ')'];
    sg_save($id, $rec);
    flr_audit(flr_akey($me), 'agreement', $tx, 'Uploaded signed ' . BAG_FORMS[$kind]['title'] . ($ok ? ' and filed it to the transaction folder' : ' (not filed to Drive)'));
    return [$id, ''];
}

// What the site already knows about this buyer, mapped onto the agreement's form fields (same field names on both
// forms where they exist). Buyer Info first, then the linked lead. Never fills compensation, retainer, fees or the
// termination date: those are the agent's call. Returns ['fields' => [name => text], 'filled' => [labels used]].
function bag_prefill($me, $tx, $kind) {
    require_once __DIR__ . '/leadlib.php';
    $info = []; $r = sg_buyer_info($me, $tx); if (!empty($r['ok']) && is_array($r['info'] ?? null)) $info = $r['info'];
    $lead = null; try { $lead = ld_find_by_folder($me, $tx); } catch (Throwable $e) {}
    $g = function ($k) use ($info) { return trim((string)($info[$k] ?? '')); };
    $n1 = $g('b1_name'); $p1 = $g('b1_phone'); $e1 = $g('b1_email');
    if ($lead) { if ($n1 === '') $n1 = trim(($lead['first'] ?? '') . ' ' . ($lead['last'] ?? '')); if ($p1 === '') $p1 = trim((string)($lead['phone'] ?? '')); if ($e1 === '') $e1 = trim((string)($lead['email'] ?? '')); }
    $n2 = $g('b2_name'); $p2 = $g('b2_phone'); $e2 = $g('b2_email');
    $cd = (array)($lead['client'] ?? []); // Client Details on the lead fill any gaps
    if ($n2 === '') { $n2 = trim((string)($cd['co_name'] ?? '')); $p2 = $p2 !== '' ? $p2 : trim((string)($cd['co_phone'] ?? '')); $e2 = $e2 !== '' ? $e2 : trim((string)($cd['co_email'] ?? '')); }
    $mail = $g('mailing'); if ($mail === '') $mail = trim((string)($cd['mailing'] ?? '')); $street = $mail; $zip = '';
    if (preg_match('/^(.*?)[,\s]*(\d{5})(?:-\d{4})?\s*$/', $mail, $m)) { $street = trim($m[1], " ,"); $zip = $m[2]; }
    $f = []; $used = [];
    $set = function ($k, $v, $label) use (&$f, &$used) { if ($v !== '') { $f[$k] = $v; $used[$label] = true; } };
    $set('Buyer_Names', implode(' and ', array_filter([$n1, $n2])), 'Buyer names');
    $set('Buyer1_Name', $n1, 'Buyer names'); $set('Buyer1_Phone', $p1, 'Phone'); $set('Buyer1_Email', $e1, 'Email');
    $set('Buyer1_Address', $street, 'Mailing address'); $set('Buyer1_Zip', $zip, 'Mailing address');
    if ($n2 !== '') { $set('Buyer2_Name', $n2, 'Buyer names'); $set('Buyer2_Phone', $p2, 'Phone'); $set('Buyer2_Email', $e2, 'Email'); $set('Buyer2_Address', $street, 'Mailing address'); $set('Buyer2_Zip', $zip, 'Mailing address'); }
    $set('Date_Effective', date('m/d/Y'), 'Effective date (today)');
    $w = (array)($lead['wants'] ?? []);
    $types = array_values(array_filter((array)($w['types'] ?? []), 'strlen'));
    $set('Search_PropertyType', implode(', ', $types), 'Property type');
    $set('Search_Location', trim((string)($w['areas'] ?? '')), 'Location');
    if ($kind === 'showing') { // homes scheduled to show, newest first
        require_once __DIR__ . '/flrdb.php';
        try { $q = flr_db()->prepare("SELECT address FROM shop_notes WHERE folder = ? AND akey = ? AND kind = 'scheduled' AND address <> '' ORDER BY id DESC"); $q->execute([$tx, flr_akey($me)]);
            $i = 0; $seen = []; foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $a) { $a = trim($a); if ($a === '' || isset($seen[strtolower($a)]) || $i >= 7) continue; $seen[strtolower($a)] = 1; $set('Property_List_' . (++$i), $a, 'Homes scheduled to show'); }
        } catch (Throwable $e) {}
    }
    return ['fields' => $f, 'filled' => array_keys($used)];
}
// A filled agreement handed from the Buyer Agreement screen to the send-for-signature page (sign.php ?pf=token).
function bag_pf_dir() { $d = flr_db_dir() . '/prefill'; if (!is_dir($d)) @mkdir($d, 0750, true); return $d; }
function bag_pf_put($me, $pdf) {
    foreach (glob(bag_pf_dir() . '/*') ?: [] as $old) { if (filemtime($old) < time() - 86400) @unlink($old); }
    $t = bin2hex(random_bytes(12)); @file_put_contents(bag_pf_dir() . '/' . $t . '.pdf', $pdf); @file_put_contents(bag_pf_dir() . '/' . $t . '.who', strtolower($me)); return $t;
}
function bag_pf_get($me, $t) {
    if (!preg_match('/^[a-f0-9]{24}$/', (string)$t)) return null; $p = bag_pf_dir() . '/' . $t;
    if (!is_file($p . '.pdf') || trim((string)@file_get_contents($p . '.who')) !== strtolower($me)) return null;
    return (string)file_get_contents($p . '.pdf');
}
function bag_pf_drop($t) { if (preg_match('/^[a-f0-9]{24}$/', (string)$t)) { @unlink(bag_pf_dir() . '/' . $t . '.pdf'); @unlink(bag_pf_dir() . '/' . $t . '.who'); } }

// Who signs the Buyer Agreement, from the buyer's record: Buyer 1 (and Buyer 2 when there is one), then the agent.
// Roles match the text tags on both forms ([s:buyer1], [s:buyer2], [s:agent]). Returns ['signers' => [...], 'missing' => msg|''].
function bag_signers($me, $tx) {
    require_once __DIR__ . '/leadlib.php';
    $info = []; $r = sg_buyer_info($me, $tx); if (!empty($r['ok']) && is_array($r['info'] ?? null)) $info = $r['info'];
    $lead = null; try { $lead = ld_find_by_folder($me, $tx); } catch (Throwable $e) {}
    $n1 = trim((string)($info['b1_name'] ?? '')); $e1 = trim((string)($info['b1_email'] ?? ''));
    if ($lead) { if ($n1 === '') $n1 = trim(($lead['first'] ?? '') . ' ' . ($lead['last'] ?? '')); if ($e1 === '') $e1 = trim((string)($lead['email'] ?? '')); }
    $n2 = trim((string)($info['b2_name'] ?? '')); $e2 = trim((string)($info['b2_email'] ?? ''));
    $out = []; $missing = '';
    if (!filter_var($e1, FILTER_VALIDATE_EMAIL)) $missing = 'Add ' . ($n1 !== '' ? $n1 . '\'s' : 'the buyer\'s') . ' email address to send this for signing.';
    else $out[] = ['email' => strtolower($e1), 'role' => 'buyer1', 'name' => $n1, 'label' => 'Buyer 1'];
    if ($n2 !== '') {
        if (!filter_var($e2, FILTER_VALIDATE_EMAIL)) $missing = $missing ?: 'Add ' . $n2 . '\'s email address so both buyers can sign.';
        elseif (strtolower($e2) !== strtolower($e1)) $out[] = ['email' => strtolower($e2), 'role' => 'buyer2', 'name' => $n2, 'label' => 'Buyer 2'];
    }
    $out[] = ['email' => strtolower($me), 'role' => 'agent', 'name' => trim((string)($_SESSION['name'] ?? '')) ?: $me, 'label' => 'You (agent, signs last)', 'order' => 2];
    return ['signers' => $out, 'missing' => $missing];
}
// Send the (filled) agreement to DigiSigner with the buyer's own contact details as the signers. Returns [request id, ''] or ['', error].
function bag_send($me, $tx, $kind, $pdf) {
    if (!isset(BAG_FORMS[$kind])) return ['', 'Choose which agreement to send.'];
    $own = null; foreach (flr_tx_list($me) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') $own = $t; }
    if (!$own) return ['', 'That buyer record was not found in your business.'];
    if (!is_string($pdf) || strlen($pdf) < 500 || substr($pdf, 0, 4) !== '%PDF') $pdf = bag_blank_pdf($kind);
    if (!$pdf) return ['', 'The agreement could not be loaded. Please try again.'];
    foreach (bag_state($me, $tx)['pending'] as $p) { if (bag_kind_of($p['form']) === $kind) return ['', 'This agreement is already out for signature. Wait for it, or upload a signed copy.']; }
    $sg = bag_signers($me, $tx); if ($sg['missing'] !== '') return ['', $sg['missing']];
    $title = BAG_FORMS[$kind]['title']; $agent = trim((string)($_SESSION['name'] ?? '')) ?: 'Your agent';
    $signers = array_map(function ($s) { return ['email' => $s['email'], 'role' => $s['role'], 'name' => $s['name'], 'order' => $s['order'] ?? 1]; }, $sg['signers']);
    $r = sg_send_pdf($pdf, $title . '.pdf', 'Florida Luxury Realty: please sign ' . $title,
        $agent . ' (Florida Luxury Realty) has sent you "' . $title . '" to review and sign. If you have questions, reply to ' . $me . '.', $signers);
    if (empty($r['ok'])) return ['', $r['msg'] ?? 'DigiSigner could not send it. Please try again.'];
    sg_save($r['id'], ['id' => $r['id'], 'agent' => $me, 'form' => $title, 'form_id' => BAG_FORMS[$kind]['id'], 'doc' => $r['doc'], 'created' => gmdate('c'), 'signers' => $signers,
        'folder' => ['id' => $tx, 'label' => trim((string)$own['grp'] . ' / ' . (string)$own['name'], ' /')], 'completed' => false]);
    @file_put_contents(sg_dir() . '/../signature-requests.log', json_encode(['t' => gmdate('c'), 'agent' => $me, 'form' => $title, 'tx' => $tx, 'signers' => array_column($signers, 'email'), 'id' => $r['id']]) . "\n", FILE_APPEND | LOCK_EX);
    flr_audit(flr_akey($me), 'agreement', $tx, 'Sent ' . $title . ' for signature to ' . implode(', ', array_column($signers, 'email')));
    return [$r['id'], ''];
}
