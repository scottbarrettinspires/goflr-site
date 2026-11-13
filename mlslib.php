<?php
// MLS Grid RESO Web API client, kept separate from the rest of the site until Stellar MLS approves a back-office license.
// Nothing here calls app.mlsgrid.com with real credentials yet — mls_settings() holds the base URL/token once issued, and
// mls_sample_rows() exercises the same parsing code against three fake, clearly-labeled listings so the pipeline (fetch ->
// parse -> cache -> display) can be proven and reviewed before it ever touches live data.
require_once __DIR__ . '/flrdb.php';

function mls_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS mls_listings (id TEXT PRIMARY KEY, status TEXT, data TEXT NOT NULL, updated TEXT)');
    return $pdo;
}
// Settings are stored in the shared kv table (k='mls_settings') as JSON: base_url, token (never displayed back), originating_system.
function mls_settings() {
    $q = flr_db()->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute(['mls_settings']);
    $r = $q->fetchColumn(); $s = $r ? (json_decode($r, true) ?: []) : [];
    $s += ['base_url' => '', 'token' => '', 'originating_system' => '', 'connected' => false, 'agents' => false];
    if (trim((string)$s['base_url']) === '') $s['base_url'] = 'https://api.mlsgrid.com/v2'; // MLS Grid v2 and Stellar's source name are the defaults
    if (trim((string)$s['originating_system']) === '') $s['originating_system'] = 'mfrmls';
    return $s;
}
function mls_save_settings(array $s) {
    $cur = mls_settings();
    $new = ['base_url' => trim((string)($s['base_url'] ?? $cur['base_url'])), 'originating_system' => trim((string)($s['originating_system'] ?? $cur['originating_system'])),
        'token' => array_key_exists('token', $s) && $s['token'] !== '' ? (string)$s['token'] : $cur['token'], 'connected' => !empty($s['connected']),
        'agents' => array_key_exists('agents', $s) ? !empty($s['agents']) : !empty($cur['agents'])];
    flr_db()->prepare('INSERT INTO kv (k, v) VALUES ("mls_settings", ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')->execute([json_encode($new)]);
    flr_audit('mls', 'mls_settings', '', 'MLS Grid connection settings updated (connected=' . (int)$new['connected'] . ', agents=' . (int)$new['agents'] . ')');
}

// Map one RESO Web API "Property" resource record into the flat shape the rest of goflr.com would use.
function mls_parse_listing(array $r) {
    return [
        'id' => (string)($r['ListingKey'] ?? $r['ListingId'] ?? ''),
        'status' => (string)($r['StandardStatus'] ?? ''),
        'address' => trim(($r['StreetNumber'] ?? '') . ' ' . ($r['StreetName'] ?? '') . ' ' . ($r['StreetSuffix'] ?? '')),
        'city' => (string)($r['City'] ?? ''), 'state' => (string)($r['StateOrProvince'] ?? ''), 'zip' => (string)($r['PostalCode'] ?? ''),
        'price' => (float)($r['ListPrice'] ?? 0), 'beds' => (int)($r['BedroomsTotal'] ?? 0), 'baths' => (float)($r['BathroomsTotalInteger'] ?? 0),
        'sqft' => (int)($r['LivingArea'] ?? 0), 'office' => (string)($r['ListOfficeName'] ?? ''), 'agent' => (string)($r['ListAgentFullName'] ?? ''),
        'listing_id' => (string)($r['ListingId'] ?? ''), 'can_use' => implode(',', (array)($r['MlgCanUse'] ?? [])), 'street_number' => trim((string)($r['StreetNumber'] ?? '')),
        'street_name' => trim(preg_replace('/\s+/', ' ', ($r['StreetDirPrefix'] ?? '') . ' ' . ($r['StreetName'] ?? '') . ' ' . ($r['StreetSuffix'] ?? '') . ' ' . ($r['StreetDirSuffix'] ?? ''))),
        'unit' => trim((string)($r['UnitNumber'] ?? '')), 'year_built' => (int)($r['YearBuilt'] ?? 0), 'taxes' => (float)($r['TaxAnnualAmount'] ?? 0),
        'property_type' => (string)($r['PropertyType'] ?? ''), 'property_sub_type' => (string)($r['PropertySubType'] ?? ''),
        'list_agent_first' => (string)($r['ListAgentFirstName'] ?? ''), 'list_agent_last' => (string)($r['ListAgentLastName'] ?? ''),
        'list_agent_email' => (string)($r['ListAgentEmail'] ?? ''), 'list_agent_phone' => (string)($r['ListAgentPreferredPhone'] ?? $r['ListAgentDirectPhone'] ?? ''),
        'modified' => (string)($r['ModificationTimestamp'] ?? ''), 'mls' => (string)($r['OriginatingSystemName'] ?? ''), 'raw' => $r,
    ];
}
function mls_cache_listings(array $rows) {
    $pdo = mls_db(); $ins = $pdo->prepare('INSERT INTO mls_listings (id, status, data, updated) VALUES (?,?,?,?) ON CONFLICT(id) DO UPDATE SET status=excluded.status, data=excluded.data, updated=excluded.updated');
    foreach ($rows as $r) { $p = mls_parse_listing($r); if ($p['id'] === '') continue; $ins->execute([$p['id'], $p['status'], json_encode($p), date('c')]); }
}

// A single page from the RESO Web API's $expand/$filter/$select query shape. Not called until mls_settings()['connected'] is true.
function mls_fetch_page($resourcePath, $query = []) {
    $s = mls_settings(); if ($s['base_url'] === '' || $s['token'] === '') return ['ok' => false, 'note' => 'MLS Grid is not connected yet.'];
    $url = rtrim($s['base_url'], '/') . '/' . ltrim($resourcePath, '/') . ($query ? '?' . http_build_query($query) : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $s['token'], 'Accept: application/json']]);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $errv = curl_error($ch); curl_close($ch);
    if ($body === false) return ['ok' => false, 'note' => 'Request failed: ' . $errv];
    $j = json_decode($body, true);
    if ($code === 401 || $code === 403) return ['ok' => false, 'note' => 'MLS Grid did not accept the access token (HTTP ' . $code . '). Check the token on the MLS Data page.'];
    if ($code === 429) return ['ok' => false, 'note' => 'MLS Grid asked us to slow down (HTTP 429). Try again in a minute.'];
    if ($code !== 200 || !is_array($j)) return ['ok' => false, 'note' => 'MLS Grid returned HTTP ' . $code];
    return ['ok' => true, 'rows' => (array)($j['value'] ?? []), 'next' => $j['@odata.nextLink'] ?? ''];
}

// ---- Look up one listing by MLS number (RESO ListingId) for the Make An Offer screen ----
// Order: live MLS Grid when the connection is switched on (and cache what comes back), then anything already cached. Returns [true, listing] or [false, message].
function mls_find($mls) {
    $mls = trim((string)$mls);
    if (!preg_match('/^[A-Za-z0-9_-]{4,20}$/', $mls)) return [false, 'Enter the MLS number using letters and digits only (for example T3512345).'];
    $s = mls_settings(); $live = '';
    if (!empty($s['connected']) && $s['base_url'] !== '' && $s['token'] !== '') {
        // MLS Grid stores the MLS number with the source prefix (Stellar: MFR + T3512345); agents type it without.
        $pre = 'MFR'; // Stellar MLS (mfrmls)
        $full = strpos(strtoupper($mls), $pre) === 0 ? strtoupper($mls) : $pre . strtoupper($mls);
        $r = mls_fetch_page('Property', ['$filter' => "OriginatingSystemName eq '" . preg_replace('/[^A-Za-z0-9 _-]/', '', $s['originating_system']) . "' and ListingId eq '" . $full . "'", '$top' => 1]);
        if (!empty($r['ok'])) { if (!empty($r['rows'])) { mls_cache_listings($r['rows']); return [true, mls_parse_listing($r['rows'][0])]; } return [false, 'No listing with MLS number ' . $mls . ' was found. Check the number and try again.']; }
        $live = (string)($r['note'] ?? '');
    }
    $q = mls_db()->prepare("SELECT data FROM mls_listings WHERE UPPER(json_extract(data, '$.listing_id')) IN (UPPER(?), UPPER(?)) OR UPPER(id) = UPPER(?) LIMIT 1"); $q->execute([$mls, 'MFR' . $mls, $mls]);
    $d = $q->fetchColumn();
    if ($d) { $j = json_decode($d, true); if (is_array($j)) return [true, $j]; }
    if ($live !== '') return [false, $live];
    return [false, empty($s['connected']) ? 'The MLS connection is not turned on yet, so this cannot fill in the property for you. Enter the details below by hand. Once MLS is connected, typing the MLS number fills them in.' : 'No listing with MLS number ' . $mls . ' was found. Check the number and try again.'];
}
// The Make An Offer screen's fields, filled from one parsed listing. Keys match the offer form (street_number, street_name, ... oa_phone).
function mls_offer_fields(array $l) {
    $pt = (string)($l['property_type'] ?? ''); $sub = strtolower((string)($l['property_sub_type'] ?? '')); $type = '';
    if (preg_match('/manufactured|mobile/', $sub)) $type = 'Mobile / Manufactured';
    elseif (preg_match('/condo|townhouse|townhome|villa/', $sub)) $type = 'Condo / Townhome';
    elseif (preg_match('/land|lot|vacant/i', $pt . ' ' . $sub)) $type = 'Vacant Land';
    elseif (preg_match('/income|multi|duplex|triplex|quadruplex/i', $pt . ' ' . $sub)) $type = 'Multi-Family';
    elseif (preg_match('/commercial/i', $pt)) $type = 'Commercial';
    elseif (preg_match('/residential/i', $pt)) $type = 'Residential';
    return [
        'street_number' => (string)($l['street_number'] ?? ''), 'street_name' => (string)($l['street_name'] ?? ''), 'unit' => (string)($l['unit'] ?? ''),
        'city' => (string)($l['city'] ?? ''), 'state' => strtoupper((string)($l['state'] ?? '')), 'zip' => substr((string)($l['zip'] ?? ''), 0, 5),
        'year_built' => !empty($l['year_built']) ? (string)(int)$l['year_built'] : '', 'annual_taxes' => !empty($l['taxes']) ? (string)round((float)$l['taxes'], 2) : '',
        'property_type' => $type,
        'oa_first' => (string)($l['list_agent_first'] ?? ''), 'oa_last' => (string)($l['list_agent_last'] ?? ''), 'oa_brokerage' => (string)($l['office'] ?? ''),
        'oa_email' => (string)($l['list_agent_email'] ?? ''), 'oa_phone' => (string)($l['list_agent_phone'] ?? ''),
    ];
}
// Three fake listings in the RESO Web API shape, used only to prove the parse/cache/display pipeline before live data exists.
function mls_sample_raw() {
    $now = date('c');
    return [
        ['ListingKey' => 'SAMPLE-1001', 'ListingId' => 'S1001', 'UnitNumber' => '', 'YearBuilt' => 2004, 'TaxAnnualAmount' => 5120.5, 'PropertyType' => 'Residential', 'PropertySubType' => 'Single Family Residence', 'ListAgentFirstName' => 'Sample', 'ListAgentLastName' => 'Agent', 'ListAgentEmail' => 'sample.agent@example.com', 'ListAgentPreferredPhone' => '(727) 555-0100', 'StandardStatus' => 'Active', 'StreetNumber' => '100', 'StreetName' => 'Sample Palm', 'StreetSuffix' => 'Dr', 'City' => 'Trinity', 'StateOrProvince' => 'FL', 'PostalCode' => '34655', 'ListPrice' => 549000, 'BedroomsTotal' => 4, 'BathroomsTotalInteger' => 3, 'LivingArea' => 2600, 'ListOfficeName' => 'Florida Luxury Realty', 'ListAgentFullName' => 'Sample Agent', 'ModificationTimestamp' => $now, 'OriginatingSystemName' => 'Stellar MLS'],
        ['ListingKey' => 'SAMPLE-1002', 'ListingId' => 'S1002', 'UnitNumber' => '204', 'YearBuilt' => 1998, 'TaxAnnualAmount' => 3380, 'PropertyType' => 'Residential', 'PropertySubType' => 'Condominium', 'ListAgentFirstName' => 'Sample', 'ListAgentLastName' => 'Agent', 'ListAgentEmail' => 'sample.agent@example.com', 'ListAgentPreferredPhone' => '(727) 555-0100', 'StandardStatus' => 'Pending', 'StreetNumber' => '200', 'StreetName' => 'Fixture Harbor', 'StreetSuffix' => 'Way', 'City' => 'New Port Richey', 'StateOrProvince' => 'FL', 'PostalCode' => '34652', 'ListPrice' => 389900, 'BedroomsTotal' => 3, 'BathroomsTotalInteger' => 2, 'LivingArea' => 1850, 'ListOfficeName' => 'Florida Luxury Realty', 'ListAgentFullName' => 'Sample Agent', 'ModificationTimestamp' => $now, 'OriginatingSystemName' => 'Stellar MLS'],
        ['ListingKey' => 'SAMPLE-1003', 'ListingId' => 'S1003', 'YearBuilt' => 2016, 'TaxAnnualAmount' => 8810, 'PropertyType' => 'Residential', 'PropertySubType' => 'Single Family Residence', 'ListAgentFirstName' => 'Sample', 'ListAgentLastName' => 'Agent', 'ListAgentEmail' => 'sample.agent@example.com', 'ListAgentPreferredPhone' => '(727) 555-0100', 'StandardStatus' => 'Closed', 'StreetNumber' => '300', 'StreetName' => 'Test Grove', 'StreetSuffix' => 'Ct', 'City' => 'Odessa', 'StateOrProvince' => 'FL', 'PostalCode' => '33556', 'ListPrice' => 712500, 'BedroomsTotal' => 5, 'BathroomsTotalInteger' => 4, 'LivingArea' => 3400, 'ListOfficeName' => 'Florida Luxury Realty', 'ListAgentFullName' => 'Sample Agent', 'ModificationTimestamp' => $now, 'OriginatingSystemName' => 'Stellar MLS'],
    ];
}
function mls_sample_rows() { return array_map('mls_parse_listing', mls_sample_raw()); }
