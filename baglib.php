<?php
// Buyer Agreement stage: the blank forms (Exclusive Buyer Brokerage Agreement, Showing Agreement), the signed copy on
// file for a buyer record (from DigiSigner or uploaded by the agent), and requests still out for signature.
// Plain functions so the page, its PDF endpoint (bagdoc.php) and assistants share one set of rules.
require_once __DIR__ . '/sigtools.php';
require_once __DIR__ . '/pstage.php';
require_once __DIR__ . '/leadlib.php';
require_once __DIR__ . '/flrdb.php';
const AG_EXT_TITLE = 'Buyer Agreement Extension';

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
function bag_upload($me, $tx, $kind, array $file, $signedOn = '', $expires = '', $homes = '') {
    $title = $kind === 'extension' ? AG_EXT_TITLE : (BAG_FORMS[$kind]['title'] ?? '');
    if ($title === '') return ['', 'Choose which agreement this is.'];
    $terr = ag_check_terms($kind, $signedOn !== '' ? $signedOn : date('Y-m-d'), $expires); if ($terr !== '') return ['', $terr];
    $own = null; foreach (flr_tx_list($me, false) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') $own = $t; }
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
    $rec = ['id' => $id, 'agent' => $me, 'form' => $title, 'form_id' => (BAG_FORMS[$kind]['id'] ?? ''), 'doc' => '', 'created' => $at, 'completed' => true,
        'completed_at' => $at, 'uploaded' => true, 'local' => $id . '.pdf', 'signers' => [], 'seen' => true,
        'folder' => ['id' => $tx, 'label' => trim((string)$own['grp'] . ' / ' . (string)$own['name'], ' /')]];
    list($ok, $r) = sg_save_bytes($rec, $pdf, 'Signed - ' . $title . ' - ' . ($signedOn ?: date('Y-m-d')) . '.pdf');
    $rec['filed'] = $ok ? $r : ['name' => 'Signed - ' . $title . '.pdf', 'url' => '', 'at' => gmdate('c'), 'folder' => 'Saved here only (' . (is_string($r) ? $r : 'Drive filing failed') . ')'];
    sg_save($id, $rec);
    flr_audit(flr_akey($me), 'agreement', $tx, 'Uploaded signed ' . $title . ($ok ? ' and filed it to the transaction folder' : ' (not filed to Drive)'));
    if ($expires !== '' || $homes !== '') ag_set_terms_raw($tx, $id, $expires, $homes);
    ag_activate($me, $tx);
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
    $f = []; $used = []; $lab = []; $force = [];
    $set = function ($k, $v, $label) use (&$f, &$used, &$lab) { if ($v !== '') { $f[$k] = $v; $used[$label] = true; $lab[$k] = $label; } };
    $set('Buyer_Names', implode(' and ', array_filter([$n1, $n2])), 'Buyer names');
    $set('Buyer1_Name', $n1, 'Buyer names'); $set('Buyer1_Phone', $p1, 'Phone'); $set('Buyer1_Email', $e1, 'Email');
    $set('Buyer1_Address', $street, 'Mailing address'); $set('Buyer1_Zip', $zip, 'Mailing address');
    if ($n2 !== '') { $set('Buyer2_Name', $n2, 'Buyer names'); $set('Buyer2_Phone', $p2, 'Phone'); $set('Buyer2_Email', $e2, 'Email'); $set('Buyer2_Address', $street, 'Mailing address'); $set('Buyer2_Zip', $zip, 'Mailing address'); }
    $set('Date_Effective', date('m/d/Y'), 'Effective date (today)');
    // The agent and brokerage (standard FLR field names; a form without that field simply skips it)
    $agent = trim((string)($_SESSION['name'] ?? ''));
    $set('Agent_Name', $agent, 'Agent'); $set('BuyerAgent_Name', $agent, 'Agent'); $set('BuyerBroker_Name', 'Florida Luxury Realty, Inc.', 'Brokerage');
    try { $lic = function_exists('sg_license') ? (string)sg_license($me) : ''; $set('Agent_License', trim($lic), 'Agent license'); } catch (Throwable $e) {}
    // Compensation and term already captured on this record from an earlier version of the agreement
    try { require_once __DIR__ . '/offerlib.php'; $oc = (array)((of_raw($tx)['comp'] ?? [])); foreach ($oc as $k => $v) { if (preg_match('/^(Comp_[A-Za-z0-9_]+|Price_Retainer|Price_CancellationFee|Term_ProtectionDays|Date_Termination)$/', (string)$k) && trim((string)$v) !== '') $set($k, trim((string)$v), 'Compensation and term from this record'); } } catch (Throwable $e) {}
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
    // The agreement setup questions (type, location, commission, admin fee, special terms, expiration) win over everything above.
    $T = bag_terms($me, $tx); $fs = function ($k, $v, $label) use ($set, &$force) { if ($v !== '') { $set($k, $v, $label); $force[] = $k; } };
    if ($kind === 'exclusive') {
        $fs('Search_PropertyType', implode(', ', array_map(function ($t) { return BAG_TYPE_FORM[$t] ?? $t; }, $T['types'])), 'Property type');
        $fs('Search_Location', $T['areas'], 'Location');
        $fs('Date_Termination', $T['expires'] !== '' ? date('m/d/Y', strtotime($T['expires'])) : '', 'Expiration date');
    } else {
        $hl = array_values(array_filter(array_map('trim', preg_split('/[\r\n;]+/', $T['homes']))));
        foreach (array_slice($hl, 0, 7) as $i => $a) $fs('Property_List_' . ($i + 1), $a, 'Homes it covers');
    }
    $fs('Comp_Purchase_Percent', $T['comm'], 'Commission rate');
    $fs('Comp_PurchasePlus_FlatFee', $T['admin'] !== '' ? $T['admin'] . '. Admin FLR Fee' : '', 'FLR admin fee');
    $lines = bag_wrap($T['special'], 85); foreach (array_slice($lines, 0, $kind === 'exclusive' ? 6 : 4) as $i => $ln) $fs('Additional_Terms_' . ($i + 1), $ln, 'Special terms');
    return ['fields' => $f, 'filled' => array_keys($used), 'labels' => $lab, 'force' => $force];
}
const BAG_TYPES = ['Single family', 'Condo', 'Townhome', 'Villa', 'Manufactured', 'Multi-family', 'Land / lot', 'New construction'];
const BAG_TYPE_FORM = ['Single family' => 'Single family home', 'Condo' => 'Condominium', 'Manufactured' => 'Mobile/modular home'];
function bag_wrap($t, $w) { $t = trim(preg_replace('/\s+/', ' ', (string)$t)); return $t === '' ? [] : array_map('trim', explode("\n", wordwrap($t, $w, "\n", true))); }
// The agreement setup answers for a buyer record: property type and location (Property Details on the lead), commission rate,
// FLR admin fee and special terms (Client Details on the lead), expiration date and homes (this record). Defaults: 3% and $325.
// Returns ['types'=>[], 'areas'=>'', 'comm'=>'3', 'admin'=>'325', 'special'=>'', 'expires'=>'', 'homes'=>'', 'admin_ok'=>bool].
function bag_terms($me, $tx) {
    $kv = json_decode((string)ag_kv('bagq_' . $tx), true); $kv = is_array($kv) ? $kv : [];
    $lead = null; try { $lead = ld_find_by_folder($me, $tx); } catch (Throwable $e) {}
    $w = (array)($lead['wants'] ?? []); $cd = (array)($lead['client'] ?? []);
    $pick = function ($a, $b, $d) { foreach ([$a, $b] as $v) { if (is_scalar($v) && trim((string)$v) !== '') return trim((string)$v); } return $d; };
    return ['types' => array_values(array_filter((array)($w['types'] ?? $kv['types'] ?? []), 'strlen')), 'areas' => $pick($w['areas'] ?? '', $kv['areas'] ?? '', ''),
        'comm' => $pick($cd['comm_rate'] ?? '', $kv['comm'] ?? '', '3'), 'admin' => $pick($cd['admin_fee'] ?? '', $kv['admin'] ?? '', '325'),
        'special' => $pick($cd['special_terms'] ?? '', $kv['special'] ?? '', ''), 'expires' => (string)($kv['expires'] ?? ''), 'homes' => (string)($kv['homes'] ?? ''), 'admin_ok' => !empty($kv['admin_ok'])];
}
// Save the answers. '' or a readable error. Keeps them on the record and on the lead so later forms can use them.
function bag_terms_save($me, $tx, array $a) {
    $own = false; foreach (flr_tx_list($me, false) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') $own = true; }
    if (!$own) return 'That buyer record was not found in your business.';
    $types = array_values(array_intersect(array_map('strval', (array)($a['types'] ?? [])), BAG_TYPES));
    $areas = mb_substr(trim((string)($a['areas'] ?? '')), 0, 200);
    $comm = trim(str_replace('%', '', (string)($a['comm'] ?? ''))); $admin = trim(str_replace(['$', ','], '', (string)($a['admin'] ?? '')));
    if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $comm) || (float)$comm <= 0 || (float)$comm > 100) return 'Enter the commission rate as a percent, for example 3.';
    if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $admin)) return 'Enter the FLR admin fee as a dollar amount, for example 325.';
    if (empty($a['admin_ok'])) return 'Please confirm the FLR admin fee (check the box) before applying.';
    $special = trim((string)($a['special'] ?? '')); if (mb_strlen($special) > 340) return 'The special terms are too long for the lines on the form (340 characters). Shorten them, or add the rest on the form.';
    $expires = trim((string)($a['expires'] ?? '')); $homes = mb_substr(trim((string)($a['homes'] ?? '')), 0, 400);
    if (($a['kind'] ?? 'exclusive') === 'exclusive') { $e = ag_check_terms('exclusive', date('Y-m-d'), $expires); if ($e !== '') return $e; }
    ag_kv_put('bagq_' . $tx, json_encode(['types' => $types, 'areas' => $areas, 'comm' => $comm, 'admin' => $admin, 'admin_ok' => true, 'special' => $special, 'expires' => $expires, 'homes' => $homes, 'saved' => date('c')]));
    ld_update($me, function (&$db) use ($tx, $types, $areas, $comm, $admin, $special) {
        foreach ($db['leads'] as &$L) { if ((string)($L['roles']['buyer']['folder'] ?? '') !== $tx) continue;
            if ($types) $L['wants']['types'] = $types; if ($areas !== '') $L['wants']['areas'] = $areas;
            $L['client']['comm_rate'] = $comm; $L['client']['admin_fee'] = $admin; $L['client']['special_terms'] = $special; }
    });
    flr_audit(flr_akey($me), 'agreement_setup', $tx, 'Commission ' . $comm . '%, admin fee $' . $admin . ($expires !== '' ? ', expires ' . $expires : ''));
    return '';
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
    $own = null; foreach (flr_tx_list($me, false) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') $own = $t; }
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

// ---- Agreements on a buyer record ------------------------------------------------------------------------------
// A buyer stays a LEAD until a signed buyer agreement is on file (DigiSigner returned it, or the agent uploaded it).
// Then the record converts to Active Business. A record can hold several agreements: Showing Agreements (cover only
// the homes named on the form) until an Exclusive Buyer Brokerage Agreement (a term agreement: expiration date tracked),
// and Extensions that push the expiration date out. When the term runs out with no extension, the buyer is expired.
function ag_kv($k) { $pdo = flr_db(); $pdo->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT)'); $q = $pdo->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute([$k]); $v = $q->fetchColumn(); return $v === false ? null : (string)$v; }
function ag_kv_put($k, $v) { $pdo = flr_db(); $pdo->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT)'); if ($v === null) $pdo->prepare('DELETE FROM kv WHERE k = ?')->execute([$k]); else $pdo->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)')->execute([$k, $v]); }
function ag_prep($tx) { return ag_kv('prep_' . $tx) !== null; }
function ag_set_prep($tx, $on) { ag_kv_put('prep_' . $tx, $on ? gmdate('c') : null); }
function ag_kind($form) {
    $t = strtolower((string)$form);
    if (strpos($t, 'extension') !== false && strpos($t, 'buyer') !== false) return 'extension';
    return bag_kind_of($form);
}
function ag_meta($tx) { $j = json_decode((string)ag_kv('bag_agr_' . $tx), true); return is_array($j) ? $j : []; }
function ag_set_terms_raw($tx, $recId, $expires, $homes) { $m = ag_meta($tx); $m[$recId] = ['expires' => (string)$expires, 'homes' => trim((string)$homes)]; ag_kv_put('bag_agr_' . $tx, json_encode($m)); }
// '' if fine, else a readable message. Exclusive agreements and extensions are term agreements and need an expiration date.
function ag_check_terms($kind, $signedOn, $expires) {
    if ($kind === 'showing') return '';
    if ($expires === '') return $kind === 'extension' ? 'Enter the new expiration date from the extension.' : 'Enter the expiration date of the Exclusive Buyer Brokerage Agreement.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires) || !strtotime($expires)) return 'Enter the expiration date as a real date.';
    if ($expires <= $signedOn) return 'The expiration date must be after the date the agreement was signed.';
    return '';
}
// Signed buyer agreements on one record, newest first: id, kind, title, signed_on, source (esign|upload), expires, homes.
function ag_list($me, $tx, $recs = null) {
    $recs = $recs === null ? sg_for_agent($me) : $recs; $meta = ag_meta($tx); $out = [];
    foreach ($recs as $r) {
        if ((string)($r['folder']['id'] ?? '') !== $tx || empty($r['completed'])) continue;
        $k = ag_kind($r['form'] ?? ''); if (!$k) continue;
        $m = (array)($meta[$r['id']] ?? []);
        $out[] = ['id' => $r['id'], 'kind' => $k, 'title' => $r['form'], 'signed_on' => substr((string)($r['completed_at'] ?? $r['created'] ?? ''), 0, 10),
            'source' => !empty($r['uploaded']) ? 'upload' : 'esign', 'expires' => (string)($m['expires'] ?? ''), 'homes' => (string)($m['homes'] ?? '')];
    }
    usort($out, function ($a, $b) { return strcmp($b['signed_on'], $a['signed_on']); });
    return $out;
}
// Where the buyer stands: state none|showing|exclusive|expired, expires (latest term end), days left, needs_date, a one-line message.
function ag_standing($me, $tx, $recs = null) {
    $items = ag_list($me, $tx, $recs); $today = ld_today();
    $st = ['state' => 'none', 'expires' => '', 'days' => null, 'soon' => false, 'needs_date' => false, 'message' => 'No signed buyer agreement yet. The buyer stays a lead until one is signed or uploaded.', 'items' => $items, 'prep' => ag_prep($tx)];
    if (!$items) return $st;
    $exp = ''; $hasEx = false; $lastExpiry = '';
    foreach ($items as $i) {
        if ($i['kind'] === 'showing') continue;
        $hasEx = true; if ($i['expires'] === '') $st['needs_date'] = true; elseif ($i['expires'] > $exp) $exp = $i['expires'];
    }
    $newest = $items[0];
    if (!$hasEx) { $st['state'] = 'showing'; $st['message'] = 'Showing Agreement only. It covers just the home(s) named on the form. For another home, get a new Showing Agreement or an Exclusive Buyer Brokerage Agreement signed.'; return $st; }
    $st['expires'] = $exp;
    if ($st['needs_date'] && $exp === '') { $st['state'] = 'exclusive'; $st['message'] = 'Exclusive Buyer Brokerage Agreement on file. Enter its expiration date so it can be tracked.'; return $st; }
    $days = (int)round((strtotime($exp) - strtotime($today)) / 86400); $st['days'] = $days;
    if ($exp < $today) {
        if ($newest['kind'] === 'showing' && $newest['signed_on'] > $exp) { $st['state'] = 'showing'; $st['message'] = 'The Exclusive agreement expired ' . $exp . '. A newer Showing Agreement covers only the home(s) it names.'; return $st; }
        $st['state'] = 'expired'; $st['message'] = 'The Exclusive Buyer Brokerage Agreement expired ' . $exp . '. Get an extension or a new agreement signed, or this buyer is expired.'; return $st;
    }
    $st['state'] = 'exclusive'; $st['soon'] = $days <= 30;
    $st['message'] = 'Exclusive Buyer Brokerage Agreement in force through ' . $exp . ' (' . $days . ' day' . ($days === 1 ? '' : 's') . ' left).' . ($st['soon'] ? ' Expiring soon: get an extension signed to keep working with this buyer.' : '');
    return $st;
}
// Lead -> Active Business: once a signed agreement is on file, the record leaves "prep" and the lead becomes a client.
function ag_activate($me, $tx, $recs = null) {
    if (!ag_prep($tx) || !ag_list($me, $tx, $recs)) return false;
    ag_set_prep($tx, false);
    $q = json_decode((string)ag_kv('bagq_' . $tx), true); $qe = is_array($q) ? (string)($q['expires'] ?? '') : '';
    if ($qe !== '') foreach (ag_list($me, $tx, $recs) as $i) { if ($i['kind'] === 'exclusive' && $i['expires'] === '' && ag_check_terms('exclusive', $i['signed_on'], $qe) === '') { ag_set_terms_raw($tx, $i['id'], $qe, ''); break; } }
    ld_update($me, function (&$db) use ($tx) {
        foreach ($db['leads'] as &$L) { if ((string)($L['roles']['buyer']['folder'] ?? '') !== $tx) continue;
            $L['roles']['buyer']['sub'] = 'client'; $L['roles']['buyer']['since'] = ld_today(); $L['roles']['buyer']['touches'] = 0; $L['roles']['buyer']['last'] = ''; unset($L['roles']['buyer']['prep']);
            $L['log'][] = ['d' => ld_today(), 'role' => 'buyer', 'mid' => '', 'note' => 'Signed buyer agreement received. Moved to Active Business'];
        }
    });
    flr_tx_dirty($me);
    flr_audit(flr_akey($me), 'buyer_converted', $tx, 'Signed buyer agreement on file: lead became Active Business');
    return true;
}
// Check every lead still waiting on a signed agreement (cheap: only leads with a prep record).
function ag_sync($me) {
    $pending = []; foreach (ld_load($me)['leads'] as $L) { $f = (string)($L['roles']['buyer']['folder'] ?? ''); if ($f !== '' && !empty($L['roles']['buyer']['prep'])) $pending[] = $f; }
    if (!$pending) return 0; $recs = sg_for_agent($me); $n = 0;
    foreach ($pending as $f) if (ag_activate($me, $f, $recs)) $n++;
    return $n;
}
// Enter or change the expiration date (and, for a Showing Agreement, the home(s) it covers) of a signed agreement. '' or an error message.
function ag_set_terms($me, $tx, $recId, $expires, $homes = '') {
    $own = false; foreach (flr_tx_list($me, false) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') $own = true; }
    if (!$own) return 'That buyer record was not found in your business.';
    $it = null; foreach (ag_list($me, $tx) as $i) { if ($i['id'] === $recId) $it = $i; }
    if (!$it) return 'That agreement was not found on this record.';
    $e = ag_check_terms($it['kind'], $it['signed_on'], trim((string)$expires)); if ($e !== '') return $e;
    ag_set_terms_raw($tx, $recId, trim((string)$expires), $homes);
    flr_audit(flr_akey($me), 'agreement_terms', $tx, $it['title'] . ': expires ' . ($expires ?: 'n/a'));
    return '';
}
