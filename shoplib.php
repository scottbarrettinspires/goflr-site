<?php
// Shop Homes: find MLS listings that match what the buyer wants, so the agent can pick homes to show. Plain-argument functions;
// shop_search_api.php and shop_notes.php only call these.
require_once __DIR__ . '/mlslib.php';
require_once __DIR__ . '/leadlib.php';
require_once __DIR__ . '/dlib.php'; // flr_is_broker()

// What the buyer wants, from the Buyer Property details on the linked lead (Leads & Contacts). Returns a criteria array (maybe empty).
function shop_criteria($me, $tx) {
    $L = ld_find_by_folder($me, $tx); $w = $L ? (array)($L['wants'] ?? []) : [];
    $num = function ($v) { $n = preg_replace('/[^0-9.]/', '', (string)$v); return $n === '' ? '' : $n; };
    $c = ['price_min' => $num($w['price_min'] ?? ''), 'price_max' => $num($w['price_max'] ?? ''), 'beds' => $num($w['beds'] ?? ''), 'baths' => $num($w['baths'] ?? ''),
          'sqft' => $num($w['sqft'] ?? ''), 'sqft_max' => $num($w['sqft_max'] ?? ''), 'areas' => trim((string)($w['areas'] ?? '')),
          'types' => array_values(array_filter((array)($w['types'] ?? []))), 'feats' => array_values(array_filter((array)($w['feats'] ?? []))),
          'pool' => (string)(((array)($w['pool'] ?? []))[0] ?? ''), 'linked' => (bool)$L];
    return $c;
}
function shop_summary(array $c) {
    $p = [];
    if ($c['price_min'] !== '' || $c['price_max'] !== '') $p[] = '$' . number_format((float)($c['price_min'] ?: 0)) . ' to ' . ($c['price_max'] !== '' ? '$' . number_format((float)$c['price_max']) : 'open');
    if ($c['beds'] !== '') $p[] = $c['beds'] . '+ beds'; if ($c['baths'] !== '') $p[] = $c['baths'] . '+ baths';
    if ($c['sqft'] !== '') $p[] = $c['sqft'] . '+ sq ft'; if ($c['types']) $p[] = implode('/', $c['types']); if ($c['areas'] !== '') $p[] = 'in ' . $c['areas'];
    if ($c['feats']) $p[] = implode(', ', $c['feats']);
    return implode(' · ', $p);
}
// Search Active listings. $c as from shop_criteria (the agent may adjust values). Returns [true, ['rows'=>[...], 'note'=>'']] or [false, message].
// MLS Grid does the price/beds/baths/place filtering; type, size and must-haves are checked here on what comes back.
function shop_search($me, array $c, $limit = 40) {
    $s = mls_settings();
    if (!flr_is_broker() && empty($s['agents'])) return [false, 'MLS search is not open to agents yet.'];
    if (empty($s['connected']) || $s['token'] === '') return [false, 'MLS Grid is not connected yet.'];
    $sys = preg_replace('/[^A-Za-z0-9 _-]/', '', $s['originating_system']);
    $base = "OriginatingSystemName eq '" . $sys . "' and MlgCanView eq true and StandardStatus eq 'Active'";
    $n = function ($k) use ($c) { $v = preg_replace('/[^0-9.]/', '', (string)($c[$k] ?? '')); return $v === '' ? null : $v; };
    if ($n('price_min') !== null) $base .= ' and ListPrice ge ' . $n('price_min');
    if ($n('price_max') !== null) $base .= ' and ListPrice le ' . $n('price_max');
    if ($n('beds') !== null) $base .= ' and BedroomsTotal ge ' . (int)$n('beds');
    if ($n('baths') !== null) $base .= ' and BathroomsTotalInteger ge ' . (int)floor((float)$n('baths'));
    $places = [];
    foreach (preg_split('/[,;\n]+/', (string)($c['areas'] ?? '')) as $a) { $a = trim($a); if ($a !== '' && count($places) < 4) $places[] = $a; }
    $queries = [];
    if (!$places) $queries[] = $base;
    foreach ($places as $a) {
        if (preg_match('/^\d{5}$/', $a)) $queries[] = $base . " and PostalCode eq '" . $a . "'";
        else $queries[] = $base . " and City eq '" . str_replace("'", "''", strtoupper(preg_replace("/[^A-Za-z0-9 .'-]/", '', $a))) . "'";
    }
    $rows = []; $seen = []; $note = '';
    foreach ($queries as $f) {
        $r = mls_fetch_page('Property', ['$filter' => $f, '$top' => 100]);
        if (empty($r['ok'])) { $note = (string)($r['note'] ?? ''); if (!$rows) return [false, $note !== '' ? $note : 'Could not search the MLS.']; continue; }
        foreach ($r['rows'] as $x) { $k = (string)($x['ListingKey'] ?? $x['ListingId'] ?? ''); if ($k === '' || isset($seen[$k])) continue; $seen[$k] = 1; $rows[] = $x; }
    }
    if ($rows) mls_cache_listings($rows);
    $typeRx = ['Single family' => '/single/i', 'Condo' => '/condo/i', 'Townhome' => '/town/i', 'Villa' => '/villa/i', 'Manufactured' => '/manufactured|mobile/i', 'Multi-family' => '/multi|duplex|triplex|quadruplex/i', 'Land / lot' => '/land|lot|vacant/i'];
    $out = [];
    foreach ($rows as $raw) {
        $l = mls_parse_listing($raw); $all = (string)$l['property_type'] . ' ' . (string)$l['property_sub_type'];
        if (!empty($c['types'])) { $ok = false; foreach ($c['types'] as $t) { if ($t === 'New construction') { if (!empty($raw['NewConstructionYN'])) $ok = true; } elseif (isset($typeRx[$t]) && preg_match($typeRx[$t], $all)) $ok = true; } if (!$ok) continue; }
        if ($n('sqft') !== null && $l['sqft'] > 0 && $l['sqft'] < (float)$n('sqft')) continue;
        if ($n('sqft_max') !== null && $l['sqft'] > (float)$n('sqft_max')) continue;
        $of = mls_offer_fields($l);
        if (in_array('No HOA', (array)($c['feats'] ?? []), true) && $of['hoa'] === 'yes') continue;
        if (in_array('No CDD', (array)($c['feats'] ?? []), true) && $of['cdd'] === 'yes') continue;
        if (in_array('Waterfront', (array)($c['feats'] ?? []), true) && empty($raw['WaterfrontYN'])) continue;
        if (($c['pool'] ?? '') === 'Must have' && empty($raw['PoolPrivateYN'])) continue;
        if (($c['pool'] ?? '') === 'No pool' && !empty($raw['PoolPrivateYN'])) continue;
        $mls = preg_replace('/^MFR/i', '', (string)$l['listing_id']);
        $out[] = ['mls' => $mls, 'address' => trim($l['address'] . ($l['unit'] !== '' ? ' #' . $l['unit'] : '')), 'city' => $l['city'], 'state' => $l['state'], 'zip' => $l['zip'], 'price' => $l['price'],
                  'beds' => $l['beds'], 'baths' => $l['baths'], 'sqft' => $l['sqft'], 'year_built' => $l['year_built'], 'type' => $l['property_sub_type'] ?: $l['property_type'],
                  'hoa' => $of['hoa'], 'cdd' => $of['cdd'], 'office' => $l['office'], 'agent' => $l['agent'], 'phone' => $l['list_agent_phone'], 'email' => $l['list_agent_email']];
    }
    usort($out, function ($a, $b) { return $a['price'] <=> $b['price']; });
    $total = count($out); $out = array_slice($out, 0, $limit);
    return [true, ['rows' => $out, 'total' => $total, 'note' => $note]];
}
