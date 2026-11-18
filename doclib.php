<?php
// Documents on one client record: every executed (fully signed or uploaded-signed) document for a buyer or seller record,
// grouped Agreements / Contract / Addenda / Disclosures / Other, each with where it is filed in Google Drive.
// Plain function so the page, docs_api.php and assistants (FLR MCP) share it.
// dc_list($me, $tx) => ['ok'=>true,'tx'=>id,'groups'=>[['key','label','items'=>[...]]],'waiting'=>n,'folder_url'=>url|'','count'=>n] or ['ok'=>false,'msg'=>...]
// 'pending' = sent for e-signature, not complete (title, sent, signers[{email,name,signed}]); 'needs_upload' = a buyer agreement was downloaded for
// signing and no signed copy has come back yet (title, downloaded_on, upload_url).
// item: id, title, kind, signed_on (YYYY-MM-DD), source (esign|upload), drive_url ('' when only saved on the site), folder, download_url, note
require_once __DIR__ . '/flrdb.php';
require_once __DIR__ . '/sigtools.php';
require_once __DIR__ . '/signed_panel.php';
require_once __DIR__ . '/baglib.php';
require_once __DIR__ . '/offerlib.php';
require_once __DIR__ . '/doclib_file.php';

// Documents the property information makes required: [label, title regex, reason]. Needs the property saved on the offer (Make A Contract Offer).
// Flood Disclosure: every transaction. HOA rider: the property has an HOA. Lead-Based Paint: built before 1978. Condominium Rider: a condominium. CDD Disclosure: the property has a CDD.
function dc_required_rules(array $of) {
    // The purchase contract is never matched automatically: the agent assigns its exact pages from the contract packet (Assign). '/(?!)/' never matches.
    // [label, title regex (used only for documents whose contents were not recorded), reason, content keys that satisfy it]
    $pt = (string)($of['property_type'] ?? '');
    if ($pt === 'Vacant Land') $r = [['Purchase Contract (Vacant Land)', '/(?!)/', 'Required on every purchase: vacant land', ['contract_land']]];
    elseif ($pt !== '') $r = [['Purchase Contract (As-Is Residential)', '/(?!)/', 'Required on every purchase: property with a dwelling or structure', ['contract_res']]];
    else $r = [['Purchase Contract (As-Is Residential or Vacant Land)', '/(?!)/', 'Required on every purchase', ['contract_res', 'contract_land']]];
    // Seller's disclosure of the property's condition, provided by the seller: the form depends on the property type (MLS, or what the agent entered in Make A Contract Offer).
    if ($pt === 'Vacant Land') $r[] = ['Vacant Land Disclosure', '/vacant land disclosure/i', 'Required on every purchase: vacant land (provided by the seller)', ['spd_land']];
    elseif ($pt === 'Condominium' || $pt === 'Condo / Townhome') $r[] = ['Condominium Disclosure', '/condominium disclosure/i', 'Required on every purchase: condominium (provided by the seller)', ['spd_condo']];
    elseif ($pt !== '') $r[] = ["Seller's Property Disclosure", '/property disclosure/i', 'Required on every purchase: property with a dwelling (provided by the seller)', ['spd_res']];
    else $r[] = ["Seller's Property Disclosure (Residential, Condominium or Vacant Land)", '/property disclosure|condominium disclosure|vacant land disclosure/i', 'Required on every purchase (provided by the seller)', ['spd_res', 'spd_condo', 'spd_land']];
    $r[] = ['Flood Disclosure', '/flood/i', 'Required on every transaction (provided by the seller)', ['flood']];
    if (($of['hoa'] ?? '') === 'yes') $r[] = ['HOA / Community Disclosure (HOA Rider)', '/hoa|community disclosure/i', 'Required: the property has an HOA', ['hoa']];
    $yb = (int)($of['year_built'] ?? 0); if ($yb > 0 && $yb < 1978) $r[] = ['Lead-Based Paint Disclosure', '/lead.?based|lead paint/i', 'Required: built in ' . $yb . ' (before 1978)', ['lead']];
    if ($pt === 'Condominium') $r[] = ['Condominium Rider', '/condominium rider|condo rider/i', 'Required: the property is a condominium', ['condo']];
    if (($of['cdd'] ?? '') === 'yes') $r[] = ['CDD Disclosure (CDD Addendum)', '/\bcdd\b|community development/i', 'Required: the property has a CDD', ['cdd']];
    return $r;
}
const DC_GROUPS = ['agreement' => 'Buyer agreements', 'contract' => 'Contract', 'addendum' => 'Addenda and amendments', 'disclosure' => 'Disclosures', 'other' => 'Other signed documents'];
function dc_group($title) {
    $t = strtolower((string)$title);
    if (preg_match('/buyer.*(brokerage|broker).*agreement|showing agreement|buyer.*extension|extension.*buyer|listing agreement/', $t)) return 'agreement';
    if (preg_match('/addend|amendment|rider|counter|release|extension/', $t)) return 'addendum';
    if (preg_match('/disclosure|lead.?based|flood|radon|hoa|condo.*(questionnaire|rider)|inspection|wire|notice/', $t)) return 'disclosure';
    if (preg_match('/contract|purchase|offer|as is|far\b|bar\b/', $t)) return 'contract';
    return 'other';
}
function dc_list($me, $tx) {
    $me = strtolower(trim((string)$me)); $tx = (string)$tx;
    $own = null; foreach (flr_tx_list($me, false) as $t) { if ($t['folder'] === $tx) $own = $t; }
    if (!$own) return ['ok' => false, 'msg' => 'That record was not found in your business.'];
    $recs = sg_record_docs($me, $tx, (string)$own['name']);
    $groups = []; foreach (DC_GROUPS as $k => $l) $groups[$k] = ['key' => $k, 'label' => $l, 'items' => []];
    $waiting = 0; $folderUrl = ''; $n = 0; $pending = []; $needUp = []; $recItems = []; $keyWait = [];
    foreach ($recs as $r) {
        if (empty($r['completed'])) {
            $waiting++; foreach ((array)($r['contents'] ?? []) as $c) $keyWait[(string)($c['key'] ?? '')] = true;
            $ss = (array)($r['signers_status'] ?? []); $sg = [];
            foreach ((array)($r['signers'] ?? []) as $s) $sg[] = ['email' => (string)($s['email'] ?? ''), 'name' => (string)($s['name'] ?? ''), 'signed' => !empty($ss[$s['email'] ?? ''])];
            $pending[] = ['id' => (string)$r['id'], 'title' => (string)($r['form'] ?? 'Document'), 'sent' => substr((string)($r['created'] ?? ''), 0, 10), 'signers' => $sg];
            continue;
        }
        $when = substr((string)($r['completed_at'] ?? $r['created'] ?? ''), 0, 10);
        $add = function ($title, $filed, $isMain, $xi = null) use (&$groups, &$folderUrl, &$n, &$recItems, $r, $when, $tx) {
            $f = is_array($filed) ? $filed : [];
            if (!empty($f['folder_url']) && $folderUrl === '') $folderUrl = (string)$f['folder_url'];
            $g = dc_group($title); $n++;
            $ref = (string)$r['id'] . '.' . ($isMain ? 'm' : (int)$xi);
            $item = ['id' => (string)$r['id'], 'ref' => $ref, 'title' => (string)$title, 'signed_on' => $when, 'source' => !empty($r['uploaded']) ? 'upload' : 'esign', 'assign_url' => 'doc_assign.php?ref=' . urlencode($ref) . '&tx=' . urlencode($tx),
                'drive_url' => (string)($f['url'] ?? ''), 'folder' => (string)($f['folder'] ?? ''),
                'view_url' => 'doc_open.php?ref=' . urlencode($ref) . '&tx=' . urlencode($tx) . '&t=' . urlencode((string)$title), 'download_url' => 'doc_file.php?ref=' . urlencode($ref),
                'note' => ($f && empty($f['url'])) ? 'Saved on the site only. It has not been filed in Google Drive.' : (!$f ? 'Filing in Google Drive...' : '')];
            $groups[$g]['items'][] = $item; $recItems[(string)$r['id']][] = $item;
        };
        $add((string)($r['form'] ?? 'Document'), $r['filed'] ?? null, true);
        foreach ((array)($r['extra'] ?? []) as $xi => $x) $add((string)($x['filed']['name'] ?? $x['name'] ?? 'Document'), $x['filed'] ?? null, false, $xi);
    }
    if ($own['side'] === 'buyer') { // downloaded for signing in person, nothing signed or out for e-signature since
        $dl = ag_kv('bag_dl_' . $tx); $dlDay = $dl !== null ? substr((string)$dl, 0, 10) : '';
        if ($dlDay !== '' && !$pending) { $back = false; foreach (ag_list($me, $tx, $recs) as $a) { if ($a['signed_on'] >= $dlDay) $back = true; }
            if (!$back) $needUp[] = ['title' => 'Buyer agreement', 'downloaded_on' => $dlDay, 'upload_url' => 'buyer_agreement.php?tx=' . urlencode($tx) . '&embed=1&bare=1']; }
    }
    // Required documents: matched to the signed forms by what each signed document CONTAINS (recorded when it was sent), falling back to its title.
    $customs = []; $keyFound = []; // content key => ['item' => item, 'page' => n, 'file' => name]
    foreach ($recs as $r) {
        if (empty($r['completed'])) continue; $its = $recItems[(string)$r['id']] ?? [];
        foreach ((array)($r['contents'] ?? []) as $c) {
            $k = (string)($c['key'] ?? ''); $doc = (string)($c['doc'] ?? ''); $item = $its[0] ?? null;
            if ($k === 'custom') { if (!empty($c['ref'])) { foreach ($its as $it) { if ($it['ref'] === $c['ref']) $customs[] = ['label' => (string)($c['label'] ?? ''), 'item' => $it, 'page' => (int)($c['page'] ?? 1), 'pages' => (array)($c['pages'] ?? [])]; } } continue; }
            if (!empty($c['ref'])) { foreach ($its as $it) { if ($it['ref'] === $c['ref']) $item = $it; } }
            else foreach ($its as $it) { if ($doc !== '' && stripos($it['title'], $doc === 'Contract Packet' ? 'Contract' : $doc) !== false) { $item = $it; break; } }
            if ($item && (!isset($keyFound[$k]) || strcmp($item['signed_on'], $keyFound[$k]['item']['signed_on']) >= 0)) $keyFound[$k] = ['item' => $item, 'page' => (int)($c['page'] ?? 1), 'pages' => (array)($c['pages'] ?? []), 'file' => (string)($c['file'] ?? '')];
        }
    }
    $plain = []; foreach ($recs as $r) { if (empty($r['completed']) || !empty($r['contents'])) continue; foreach (($recItems[(string)$r['id']] ?? []) as $it) $plain[] = $it; } // signed before contents were recorded
    $pendTitles = []; foreach ($recs as $r) { if (!empty($r['completed']) || !empty($r['contents'])) continue; $pendTitles[] = (string)($r['form'] ?? ''); foreach ((array)($r['extra'] ?? []) as $x) $pendTitles[] = (string)($x['name'] ?? ($x['form'] ?? '')); }
    $missing = []; $required = [];
    $of = of_raw($tx);
    if (trim((string)($of['street_name'] ?? '')) !== '' || trim((string)($of['mls_id'] ?? '')) !== '') {
        foreach (dc_required_rules($of) as $q) {
            $row = ['label' => $q[0], 'key' => $q[3][0], 'reason' => $q[2], 'status' => 'missing', 'item' => null, 'page' => 0, 'pages' => [], 'in' => '', 'view_url' => '', 'download_url' => '', 'note' => ''];
            $hit = null; foreach ($q[3] as $k) { if (isset($keyFound[$k])) { $hit = $keyFound[$k]; break; } }
            if ($hit) { $it = $hit['item']; $row['status'] = 'ok'; $row['item'] = $it; $row['page'] = $hit['page']; $row['in'] = $it['title'] . ($hit['file'] !== '' && $hit['file'] !== $it['title'] ? ' (' . $hit['file'] . ')' : '');
                $row['pages'] = $hit['pages']; $row['view_url'] = $it['view_url'] . '&p=' . $hit['page'] . ($hit['pages'] ? '&only=' . implode(',', $hit['pages']) : ''); $row['download_url'] = $it['download_url']; }
            else { foreach ($plain as $it) { if (preg_match($q[1], $it['title'])) { $row['status'] = 'ok'; $row['item'] = $it; $row['in'] = $it['title']; $row['view_url'] = $it['view_url']; $row['download_url'] = $it['download_url']; break; } } }
            if ($row['status'] === 'missing') {
                $w = false; foreach ($q[3] as $k) { if (!empty($keyWait[$k])) $w = true; } foreach ($pendTitles as $t) { if (preg_match($q[1], $t)) $w = true; }
                if ($w) $row['status'] = 'waiting';
                elseif ($q[3][0] === 'contract_res' || $q[3][0] === 'contract_land') { // the other kind of purchase contract is on file instead of the one this property needs
                    $other = $q[3][0] === 'contract_land' ? 'contract_res' : 'contract_land';
                    if (count($q[3]) === 1 && isset($keyFound[$other])) { $row['status'] = 'review'; $row['note'] = 'A ' . SG_KEYS[$other] . ' is on file, but this property is ' . ($q[3][0] === 'contract_land' ? 'vacant land' : 'not vacant land') . '. Check the property type or the contract.'; $row['item'] = $keyFound[$other]['item']; $row['view_url'] = $keyFound[$other]['item']['view_url'] . '&p=' . $keyFound[$other]['page']; $row['download_url'] = $keyFound[$other]['item']['download_url']; }
                }
            }
            if ($row['status'] === 'missing') $missing[] = ['label' => $q[0], 'reason' => $q[2]];
            $required[] = $row;
        }
    }
    $others = []; foreach ($customs as $c) { $it = $c['item']; $others[] = ['label' => $c['label'], 'in' => $it['title'], 'pages' => $c['pages'], 'ref' => $it['ref'], 'view_url' => $it['view_url'] . '&p=' . $c['page'] . ($c['pages'] ? '&only=' . implode(',', $c['pages']) : ''), 'download_url' => $it['download_url']]; }
    $out = []; foreach ($groups as $g) { usort($g['items'], function ($a, $b) { return strcmp($b['signed_on'], $a['signed_on']); }); if ($g['items']) $out[] = $g; }
    return ['ok' => true, 'tx' => $tx, 'name' => (string)$own['name'], 'groups' => $out, 'waiting' => $waiting, 'pending' => $pending, 'needs_upload' => $needUp, 'required' => $required, 'other_records' => $others, 'required_missing' => $missing, 'folder_url' => $folderUrl, 'count' => $n];
}

// Which signed document, on this client record, does $ref point to? Returns [rec, error, ref parts].
function dc_rec_for($me, $tx, $ref) {
    $me = strtolower(trim((string)$me)); $p = dcf_ref($ref); if (!$p) return [null, 'Choose the signed document.', null];
    $own = null; foreach (flr_tx_list($me, false) as $t) { if ($t['folder'] === (string)$tx) $own = $t; }
    if (!$own) return [null, 'That record was not found in your business.', null];
    $rec = sg_load($p[0]);
    if (!$rec || strtolower((string)($rec['agent'] ?? '')) !== $me || empty($rec['completed']) || !sg_belongs($rec, (string)$tx, (string)$own['name'])) return [null, 'That signed document was not found on this record.', null];
    return [$rec, '', $p];
}
function dc_docname($rec, $p) {
    $x = $p[1] === 'm' ? null : ($rec['extra'][(int)$p[1]] ?? null); $title = $x ? (string)($x['form'] ?? '') : (string)($rec['form'] ?? '');
    return stripos($title, 'seller') !== false ? 'Seller Disclosures' : (stripos($title, 'buyer disclosure') !== false ? 'Buyer Disclosures' : (stripos($title, 'contract') !== false ? 'Contract Packet' : 'Other'));
}
// $target = "key:flood" (one of SG_KEYS) or "custom" with $custom = the name the agent gives it. Returns [key, label, error].
function dc_target($target, $custom) {
    $target = (string)$target;
    if (strpos($target, 'key:') === 0) { $k = substr($target, 4); return isset(SG_KEYS[$k]) ? [$k, '', ''] : ['', '', 'Choose which required document these pages are.']; }
    if ($target === 'custom') { $l = sg_clean_label($custom); return $l === '' ? ['', '', 'Type a name for what this document is.'] : ['custom', $l, '']; }
    return ['', '', 'Choose a required document, or Other and type a name.'];
}
// Assign pages of a signed document to a required document (or a named record). Replaces an earlier assignment of the same thing from the same document.
// $pages = list of 1-based page numbers; [] = the whole document. Returns '' on success or a readable error.
function dc_assign($me, $tx, $ref, array $pages, $target, $custom = '') {
    list($rec, $err, $p) = dc_rec_for($me, $tx, $ref); if (!$rec) return $err;
    list($key, $label, $err) = dc_target($target, $custom); if ($err !== '') return $err;
    $pg = []; foreach ($pages as $n) { $n = (int)$n; if ($n >= 1 && $n <= 999) $pg[$n] = $n; } $pg = array_values($pg); sort($pg);
    $list = array_values(array_filter((array)($rec['contents'] ?? []), function ($c) use ($key, $label, $ref) { return !($c['key'] === $key && ($c['label'] ?? '') === $label && ($c['ref'] ?? '') === $ref); }));
    $list[] = ['doc' => dc_docname($rec, $p), 'key' => $key, 'label' => $label, 'file' => '', 'page' => $pg ? $pg[0] : 1, 'end' => 0, 'pages' => $pg, 'ref' => $ref];
    $rec['contents'] = sg_clean_contents($list); sg_save($rec['id'], $rec);
    flr_audit(flr_akey(strtolower(trim((string)$me))), 'doc_assigned', $rec['id'], ($label !== '' ? $label : SG_KEYS[$key]) . ' <- ' . $ref . ($pg ? ' pages ' . implode(',', $pg) : ' all pages'));
    return '';
}
function dc_unassign($me, $tx, $ref, $key, $label = '') {
    list($rec, $err, $p) = dc_rec_for($me, $tx, $ref); if (!$rec) return $err;
    $rec['contents'] = array_values(array_filter((array)($rec['contents'] ?? []), function ($c) use ($key, $label, $ref) { return !($c['key'] === $key && ($c['label'] ?? '') === (string)$label && ($c['ref'] ?? '') === $ref); }));
    sg_save($rec['id'], $rec);
    flr_audit(flr_akey(strtolower(trim((string)$me))), 'doc_unassigned', $rec['id'], $key . ($label !== '' ? ' ' . $label : '') . ' <- ' . $ref);
    return '';
}
// Older single-page link kept for assistants: same as assigning one page.
function dc_link($me, $tx, $key, $ref, $page) { return dc_assign($me, $tx, $ref, [(int)$page], 'key:' . $key); }
// Upload another document to this client record and assign it. $file = one $_FILES entry. Returns [record id, ''] or ['', error].
function dc_upload($me, $tx, array $file, $target, $custom = '') {
    $me = strtolower(trim((string)$me));
    $own = null; foreach (flr_tx_list($me, false) as $t) { if ($t['folder'] === (string)$tx) $own = $t; }
    if (!$own) return ['', 'That record was not found in your business.'];
    list($key, $label, $err) = dc_target($target, $custom); if ($err !== '') return ['', $err];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['', 'Choose the PDF to upload.'];
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) return ['', 'The upload did not finish. Please try again with a smaller file.'];
    if ((int)($file['size'] ?? 0) > 25 * 1024 * 1024) return ['', 'That file is larger than 25 MB. Please upload a smaller PDF.'];
    $pdf = (string)@file_get_contents((string)$file['tmp_name']);
    if (strlen($pdf) < 500 || substr($pdf, 0, 4) !== '%PDF') return ['', 'Please upload the document as a PDF.'];
    $title = $label !== '' ? $label : SG_KEYS[$key];
    $id = 'UP' . bin2hex(random_bytes(8));
    if (!@file_put_contents(bag_updir() . '/' . $id . '.pdf', $pdf, LOCK_EX)) return ['', 'The document could not be saved. Please try again.'];
    $at = date('c');
    $rec = ['id' => $id, 'agent' => $me, 'form' => $title, 'form_id' => '', 'doc' => '', 'created' => $at, 'completed' => true, 'completed_at' => $at, 'uploaded' => true, 'local' => $id . '.pdf', 'signers' => [], 'seen' => true,
        'folder' => ['id' => (string)$tx, 'label' => trim((string)$own['grp'] . ' / ' . (string)$own['name'], ' /')],
        'contents' => sg_clean_contents([['doc' => 'Other', 'key' => $key, 'label' => $label, 'pages' => [], 'ref' => $id . '.m']])];
    list($ok, $r) = sg_save_bytes($rec, $pdf, $title . ' - ' . date('Y-m-d') . '.pdf');
    $rec['filed'] = $ok ? $r : ['name' => $title . '.pdf', 'url' => '', 'at' => gmdate('c'), 'folder' => 'Saved here only'];
    sg_save($id, $rec);
    flr_audit(flr_akey($me), 'doc_uploaded', $tx, 'Uploaded ' . $title . ($ok ? ' (filed to Drive)' : ' (not filed to Drive)'));
    return [$id, ''];
}
