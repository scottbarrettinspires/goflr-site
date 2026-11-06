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
    return $s + ['base_url' => '', 'token' => '', 'originating_system' => '', 'connected' => false];
}
function mls_save_settings(array $s) {
    $cur = mls_settings();
    $new = ['base_url' => trim((string)($s['base_url'] ?? $cur['base_url'])), 'originating_system' => trim((string)($s['originating_system'] ?? $cur['originating_system'])),
        'token' => array_key_exists('token', $s) && $s['token'] !== '' ? (string)$s['token'] : $cur['token'], 'connected' => !empty($s['connected'])];
    flr_db()->prepare('INSERT INTO kv (k, v) VALUES ("mls_settings", ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')->execute([json_encode($new)]);
    flr_audit('mls', 'mls_settings', '', 'MLS Grid connection settings updated');
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
    if ($code !== 200 || !is_array($j)) return ['ok' => false, 'note' => 'MLS Grid returned HTTP ' . $code];
    return ['ok' => true, 'rows' => (array)($j['value'] ?? []), 'next' => $j['@odata.nextLink'] ?? ''];
}

// Three fake listings in the RESO Web API shape, used only to prove the parse/cache/display pipeline before live data exists.
function mls_sample_raw() {
    $now = date('c');
    return [
        ['ListingKey' => 'SAMPLE-1001', 'StandardStatus' => 'Active', 'StreetNumber' => '100', 'StreetName' => 'Sample Palm', 'StreetSuffix' => 'Dr', 'City' => 'Trinity', 'StateOrProvince' => 'FL', 'PostalCode' => '34655', 'ListPrice' => 549000, 'BedroomsTotal' => 4, 'BathroomsTotalInteger' => 3, 'LivingArea' => 2600, 'ListOfficeName' => 'Florida Luxury Realty', 'ListAgentFullName' => 'Sample Agent', 'ModificationTimestamp' => $now, 'OriginatingSystemName' => 'Stellar MLS'],
        ['ListingKey' => 'SAMPLE-1002', 'StandardStatus' => 'Pending', 'StreetNumber' => '200', 'StreetName' => 'Fixture Harbor', 'StreetSuffix' => 'Way', 'City' => 'New Port Richey', 'StateOrProvince' => 'FL', 'PostalCode' => '34652', 'ListPrice' => 389900, 'BedroomsTotal' => 3, 'BathroomsTotalInteger' => 2, 'LivingArea' => 1850, 'ListOfficeName' => 'Florida Luxury Realty', 'ListAgentFullName' => 'Sample Agent', 'ModificationTimestamp' => $now, 'OriginatingSystemName' => 'Stellar MLS'],
        ['ListingKey' => 'SAMPLE-1003', 'StandardStatus' => 'Closed', 'StreetNumber' => '300', 'StreetName' => 'Test Grove', 'StreetSuffix' => 'Ct', 'City' => 'Odessa', 'StateOrProvince' => 'FL', 'PostalCode' => '33556', 'ListPrice' => 712500, 'BedroomsTotal' => 5, 'BathroomsTotalInteger' => 4, 'LivingArea' => 3400, 'ListOfficeName' => 'Florida Luxury Realty', 'ListAgentFullName' => 'Sample Agent', 'ModificationTimestamp' => $now, 'OriginatingSystemName' => 'Stellar MLS'],
    ];
}
function mls_sample_rows() { return array_map('mls_parse_listing', mls_sample_raw()); }
