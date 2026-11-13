<?php
// Look up a listing by MLS number for the Make An Offer screen. Same sign-in as the page; any signed-in agent or staff member may look up a listing.
// GET ?mls=T3512345 -> {ok, fields:{street_number, street_name, unit, city, state, zip, year_built, annual_taxes, property_type, oa_first, oa_last, oa_brokerage, oa_email, oa_phone},
//                       list_price, status, address}   or {ok:false, msg}
require __DIR__ . "/guard.php";
require_once __DIR__ . "/mlslib.php";
require_once __DIR__ . "/dlib.php"; // flr_is_broker()
header('Content-Type: application/json'); header('Cache-Control: no-store');
// Until the MLS license for goflr.com is in place, only office staff can use the lookup (Settings on the MLS Data page opens it to agents).
if (!flr_is_broker() && empty(mls_settings()['agents'])) { echo json_encode(['ok' => false, 'msg' => 'MLS lookup is not open to agents yet. Enter the property details by hand for now.']); exit; }
list($ok, $r) = mls_find((string)($_GET['mls'] ?? ''));
if (!$ok) { echo json_encode(['ok' => false, 'msg' => $r]); exit; }
flr_audit(flr_akey($_SESSION['email'] ?? ''), 'mls_lookup', (string)($r['listing_id'] ?: $r['id']), 'Make An Offer lookup');
echo json_encode(['ok' => true, 'fields' => mls_offer_fields($r), 'list_price' => (float)($r['price'] ?? 0), 'status' => (string)($r['status'] ?? ''), 'address' => (string)($r['address'] ?? '')]);
