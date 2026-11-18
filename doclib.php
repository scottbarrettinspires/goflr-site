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

// Documents the property information makes required: [label, title regex, reason]. Needs the property saved on the offer (Make A Contract Offer).
// Flood Disclosure: every transaction. HOA rider: the property has an HOA. Lead-Based Paint: built before 1978. Condominium Rider: a condominium. CDD Disclosure: the property has a CDD.
function dc_required_rules(array $of) {
    $r = [['Flood Disclosure', '/flood/i', 'Required on every transaction']];
    if (($of['hoa'] ?? '') === 'yes') $r[] = ['HOA / Community Disclosure (HOA Rider)', '/hoa|community disclosure/i', 'Required: the property has an HOA'];
    $yb = (int)($of['year_built'] ?? 0); if ($yb > 0 && $yb < 1978) $r[] = ['Lead-Based Paint Disclosure', '/lead.?based|lead paint/i', 'Required: built in ' . $yb . ' (before 1978)'];
    if (($of['property_type'] ?? '') === 'Condominium') $r[] = ['Condominium Rider', '/condominium rider|condo rider/i', 'Required: the property is a condominium'];
    if (($of['cdd'] ?? '') === 'yes') $r[] = ['CDD Disclosure (CDD Addendum)', '/\bcdd\b|community development/i', 'Required: the property has a CDD'];
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
    $waiting = 0; $folderUrl = ''; $n = 0; $pending = []; $needUp = [];
    foreach ($recs as $r) {
        if (empty($r['completed'])) {
            $waiting++; $ss = (array)($r['signers_status'] ?? []); $sg = [];
            foreach ((array)($r['signers'] ?? []) as $s) $sg[] = ['email' => (string)($s['email'] ?? ''), 'name' => (string)($s['name'] ?? ''), 'signed' => !empty($ss[$s['email'] ?? ''])];
            $pending[] = ['id' => (string)$r['id'], 'title' => (string)($r['form'] ?? 'Document'), 'sent' => substr((string)($r['created'] ?? ''), 0, 10), 'signers' => $sg];
            continue;
        }
        $when = substr((string)($r['completed_at'] ?? $r['created'] ?? ''), 0, 10);
        $add = function ($title, $filed, $isMain) use (&$groups, &$folderUrl, &$n, $r, $when) {
            $f = is_array($filed) ? $filed : [];
            if (!empty($f['folder_url']) && $folderUrl === '') $folderUrl = (string)$f['folder_url'];
            $g = dc_group($title); $n++;
            $groups[$g]['items'][] = ['id' => (string)$r['id'], 'title' => (string)$title, 'signed_on' => $when, 'source' => !empty($r['uploaded']) ? 'upload' : 'esign',
                'drive_url' => (string)($f['url'] ?? ''), 'folder' => (string)($f['folder'] ?? ''), 'download_url' => $isMain ? 'signed.php?dl=' . urlencode((string)$r['id']) : '',
                'note' => ($f && empty($f['url'])) ? 'Saved on the site only. It has not been filed in Google Drive.' : (!$f ? 'Filing in Google Drive...' : '')];
        };
        $add((string)($r['form'] ?? 'Document'), $r['filed'] ?? null, true);
        foreach ((array)($r['extra'] ?? []) as $x) $add((string)($x['filed']['name'] ?? $x['name'] ?? 'Document'), $x['filed'] ?? null, false);
    }
    if ($own['side'] === 'buyer') { // downloaded for signing in person, nothing signed or out for e-signature since
        $dl = ag_kv('bag_dl_' . $tx); $dlDay = $dl !== null ? substr((string)$dl, 0, 10) : '';
        if ($dlDay !== '' && !$pending) { $back = false; foreach (ag_list($me, $tx, $recs) as $a) { if ($a['signed_on'] >= $dlDay) $back = true; }
            if (!$back) $needUp[] = ['title' => 'Buyer agreement', 'downloaded_on' => $dlDay, 'upload_url' => 'buyer_agreement.php?tx=' . urlencode($tx) . '&embed=1&bare=1']; }
    }
    $missing = []; // required documents with no executed or pending copy yet
    $of = of_raw($tx);
    if (trim((string)($of['street_name'] ?? '')) !== '' || trim((string)($of['mls_id'] ?? '')) !== '') {
        $have = []; foreach ($groups as $g) foreach ($g['items'] as $i) $have[] = $i['title'];
        foreach ($recs as $r) { if (!empty($r['completed'])) continue; $have[] = (string)($r['form'] ?? ''); foreach ((array)($r['extra'] ?? []) as $x) $have[] = (string)($x['name'] ?? ($x['filed']['name'] ?? '')); }
        foreach (dc_required_rules($of) as $q) { $found = false; foreach ($have as $t) { if (preg_match($q[1], $t)) { $found = true; break; } } if (!$found) $missing[] = ['label' => $q[0], 'reason' => $q[2]]; }
    }
    $out = []; foreach ($groups as $g) { usort($g['items'], function ($a, $b) { return strcmp($b['signed_on'], $a['signed_on']); }); if ($g['items']) $out[] = $g; }
    return ['ok' => true, 'tx' => $tx, 'name' => (string)$own['name'], 'groups' => $out, 'waiting' => $waiting, 'pending' => $pending, 'needs_upload' => $needUp, 'required_missing' => $missing, 'folder_url' => $folderUrl, 'count' => $n];
}
