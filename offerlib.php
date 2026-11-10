<?php
// Offer Details: the property, buyer-side agents and agreed compensation for one buyer record, entered once (in Make A Contract
// Offer, or captured from the Buyer Agreement) and then reused: it fills the contract forms and becomes the data on the
// transaction record. Stored on the server (kv key "offer:<transaction folder id>"). Every action takes plain arguments and
// returns data or an error string; pages and offer_api.php only call these.
require_once __DIR__ . '/flrdb.php';
require_once __DIR__ . '/sigtools.php';

const OF_PROP = ['street_number', 'street_name', 'unit', 'city', 'state', 'zip', 'price', 'mls_id', 'year_built', 'annual_taxes', 'closing_date', 'property_type'];
const OF_AGENT = ['oa_first', 'oa_last', 'oa_brokerage', 'oa_email', 'oa_phone'];
const OF_PROP_TYPES = ['Residential', 'Condo / Townhome', 'Vacant Land', 'Mobile / Manufactured', 'Multi-Family', 'Commercial'];

function of_own_buyer($me, $tx) {
    foreach (flr_tx_list($me) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') return $t; }
    return null;
}
function of_raw($tx) {
    $q = flr_db()->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute(['offer:' . $tx]); $j = json_decode((string)$q->fetchColumn(), true);
    return is_array($j) ? $j : [];
}
function of_put($tx, array $d) {
    $d['updated'] = date('c');
    flr_db()->prepare('INSERT INTO kv (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')->execute(['offer:' . $tx, json_encode($d)]);
}
// "123 Main St Unit 4, City, FL 34655" from the parts.
function of_address(array $p) {
    $g = function ($k) use ($p) { return trim((string)($p[$k] ?? '')); };
    $st = trim(trim($g('street_number') . ' ' . $g('street_name')) . ($g('unit') !== '' ? ' Unit ' . $g('unit') : ''));
    $sz = trim($g('state') . ' ' . $g('zip'));
    $tail = ($g('city') !== '' || $g('zip') !== '') ? trim($g('city') . ($sz !== '' ? ', ' . $sz : ''), ', ') : '';
    return trim($st . ($st !== '' && $tail !== '' ? ', ' : '') . $tail);
}
// The record's Offer Details: what was saved, with blanks filled from what the site already knows (the buyer's saved info,
// and the agent's own details). Returns ['ok'=>true, 'offer'=>[...], 'buyer'=>[...], 'agent'=>[...], 'comp'=>[...]] or ['ok'=>false,'msg'=>...].
function of_get($me, $tx) {
    if (!of_own_buyer($me, $tx)) return ['ok' => false, 'msg' => 'That buyer record was not found in your business.'];
    $raw = of_raw($tx);
    $info = []; $r = sg_buyer_info($me, $tx); if (!empty($r['ok']) && is_array($r['info'] ?? null)) $info = $r['info'];
    $off = []; foreach (array_merge(OF_PROP, OF_AGENT) as $k) $off[$k] = trim((string)($raw[$k] ?? ''));
    $prefilled = [];
    if ($off['price'] === '' && trim((string)($info['price'] ?? '')) !== '') { $off['price'] = trim((string)$info['price']); $prefilled[] = 'price'; }
    if ($off['street_name'] === '' && trim((string)($info['property'] ?? '')) !== '') { // an address typed earlier as one line: keep it findable
        $off['street_name'] = trim((string)$info['property']); $prefilled[] = 'street_name';
    }
    if ($off['state'] === '') { $off['state'] = 'FL'; }
    $buyer = ['b1_name' => trim((string)($info['b1_name'] ?? '')), 'b1_phone' => trim((string)($info['b1_phone'] ?? '')), 'b1_email' => trim((string)($info['b1_email'] ?? '')),
              'b2_name' => trim((string)($info['b2_name'] ?? '')), 'b2_phone' => trim((string)($info['b2_phone'] ?? '')), 'b2_email' => trim((string)($info['b2_email'] ?? '')),
              'payment' => trim((string)($info['payment'] ?? ''))];
    $agent = ['name' => trim((string)($_SESSION['name'] ?? '')), 'email' => strtolower($me), 'license' => function_exists('sg_license') ? (string)@sg_license($me) : ''];
    return ['ok' => true, 'tx' => $tx, 'offer' => $off, 'address' => of_address($off), 'prefilled' => $prefilled, 'buyer' => $buyer, 'agent' => $agent, 'comp' => is_array($raw['comp'] ?? null) ? $raw['comp'] : [], 'updated' => (string)($raw['updated'] ?? '')];
}
// Save property and other-side agent details. Returns '' or a readable error.
function of_save($me, $tx, array $in) {
    $own = of_own_buyer($me, $tx); if (!$own) return 'That buyer record was not found in your business.';
    $raw = of_raw($tx); $d = [];
    foreach (array_merge(OF_PROP, OF_AGENT) as $k) $d[$k] = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($in[$k] ?? ($raw[$k] ?? '')))), 0, 120);
    if ($d['state'] !== '' && !preg_match('/^[A-Za-z]{2}$/', $d['state'])) return 'State must be two letters, like FL.';
    $d['state'] = strtoupper($d['state']);
    if ($d['zip'] !== '' && !preg_match('/^\d{5}(-\d{4})?$/', $d['zip'])) return 'Zip code must be 5 digits.';
    if ($d['price'] !== '') { $n = preg_replace('/[^0-9.]/', '', $d['price']); if ($n === '' || !is_numeric($n)) return 'Purchase price must be a number.'; $d['price'] = rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.'); }
    if ($d['annual_taxes'] !== '') { $n = preg_replace('/[^0-9.]/', '', $d['annual_taxes']); if ($n === '' || !is_numeric($n)) return 'Annual taxes must be a number.'; $d['annual_taxes'] = rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.'); }
    if ($d['year_built'] !== '' && (!preg_match('/^\d{4}$/', $d['year_built']) || (int)$d['year_built'] < 1700 || (int)$d['year_built'] > (int)date('Y') + 2)) return 'Year built must be a four-digit year.';
    if ($d['closing_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['closing_date'])) return 'Closing date must be a date (YYYY-MM-DD).';
    if ($d['oa_email'] !== '' && !filter_var($d['oa_email'], FILTER_VALIDATE_EMAIL)) return 'The other agent\'s email address does not look right.';
    if ($d['property_type'] !== '' && !in_array($d['property_type'], OF_PROP_TYPES, true)) return 'Choose a property type from the list.';
    if (preg_match('/[\\\\\/:*?"<>|]/', of_address($d))) return 'Please check the property address (no slashes or special characters).';
    if (mb_strlen(of_address($d)) > 120) return 'That address is too long.';
    if ($raw && isset($raw['comp'])) $d['comp'] = $raw['comp'];
    of_put($tx, $d);
    $addr = of_address($d);
    if ($addr !== '' || $d['price'] !== '') { sg_buyer_info_merge($me, $tx, ['property' => $addr, 'price' => $d['price']]); } // existing screens read these two from the buyer's info
    flr_audit(flr_akey($me), 'offer', $tx, 'Offer details saved' . ($addr !== '' ? ' for ' . $addr : ''));
    return '';
}
// Compensation agreed in the Buyer Agreement (Exclusive or Showing), captured when it is downloaded or sent for signing so the
// offer and the transaction record use the same figures. $comp: field name => value, limited to the compensation/term fields.
function of_save_comp($me, $tx, $kind, array $comp) {
    if (!of_own_buyer($me, $tx)) return 'That buyer record was not found in your business.';
    $keep = [];
    foreach ($comp as $k => $v) { if (preg_match('/^(Comp_[A-Za-z0-9_]+|Price_Retainer|Price_CancellationFee|Term_ProtectionDays|Date_Termination)$/', (string)$k)) { $v = mb_substr(trim((string)$v), 0, 200); if ($v !== '') $keep[$k] = $v; } }
    $raw = of_raw($tx); $old = (string)($raw['comp']['expires'] ?? '');
    $exp = of_parse_date((string)($keep['Date_Termination'] ?? ''));
    $raw['comp'] = $keep + ['agreement' => (string)$kind, 'captured' => date('c'), 'expires' => $exp !== '' ? $exp : $old];
    of_put($tx, $raw);
    flr_audit(flr_akey($me), 'offer', $tx, 'Compensation captured from the ' . ($kind === 'showing' ? 'Showing' : 'Exclusive Buyer') . ' Agreement');
    return '';
}

// The agreement's termination date as typed on the form (03/04/2027, March 4 2027, 2027-03-04) -> YYYY-MM-DD, or '' if it is not a clear date.
function of_parse_date($s) {
    $s = trim((string)$s); if ($s === '') return '';
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2}|\d{4})$/', $s, $m)) { $y = (int)$m[3]; if ($y < 100) $y += 2000; return checkdate((int)$m[1], (int)$m[2], $y) ? sprintf('%04d-%02d-%02d', $y, $m[1], $m[2]) : ''; }
    $t = strtotime($s); return $t ? date('Y-m-d', $t) : '';
}
// When this buyer's agreement expires (taken from the agreement the agent sent or downloaded), and the days left from today (America/New_York).
function of_expiration($tx) {
    $d = (string)(of_raw($tx)['comp']['expires'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
    $today = (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d');
    return ['date' => $d, 'left' => (int)round((strtotime($d . ' 12:00') - strtotime($today . ' 12:00')) / 86400)];
}
const OF_EXPIRE_REMIND_DAYS = 14;
function of_expire_text($left) { return $left < 0 ? 'Expired ' . -$left . ' day' . ($left === -1 ? '' : 's') . ' ago' : ($left === 0 ? 'Expires today' : ($left === 1 ? 'Expires tomorrow' : 'Expires in ' . $left . ' days')); }
// The agent's live buyer records whose agreement expires within the reminder window (or has expired), soonest first.
function of_expiring($me, $days = OF_EXPIRE_REMIND_DAYS) {
    $out = [];
    foreach (flr_tx_live($me) as $t) { if (($t['side'] ?? '') !== 'buyer') continue; $e = of_expiration($t['folder']); if ($e && $e['left'] <= $days) $out[] = $e + ['folder' => $t['folder'], 'name' => (string)($t['name'] ?? '')]; }
    usort($out, function ($a, $b) { return $a['left'] - $b['left']; });
    return $out;
}
