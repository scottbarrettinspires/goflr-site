<?php
// GET ?tx=<buyer record folder id> (optional overrides: price_min, price_max, beds, baths, sqft, areas)
//  -> {ok, criteria:{...}, summary, rows:[{mls,address,city,state,zip,price,beds,baths,sqft,year_built,type,hoa,cdd,office,agent,phone,email}], total}  or {ok:false, msg}
// Same sign-in as the page; searches only the buyer records of the signed-in agent.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/shoplib.php";
require_once __DIR__ . "/dlib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$me = $_SESSION['email']; $tx = (string)($_GET['tx'] ?? '');
$own = false; foreach (flr_tx_list($me) as $t) { if ($t['folder'] === $tx && $t['side'] === 'buyer') $own = true; }
if (!$own) { echo json_encode(['ok' => false, 'msg' => 'That buyer record was not found in your business.']); exit; }
$c = shop_criteria($me, $tx);
foreach (['price_min', 'price_max', 'beds', 'baths', 'sqft', 'areas'] as $k) { if (isset($_GET[$k])) $c[$k] = trim((string)$_GET[$k]); }
list($ok, $r) = shop_search($me, $c);
if (!$ok) { echo json_encode(['ok' => false, 'msg' => $r, 'criteria' => $c, 'summary' => shop_summary($c)]); exit; }
flr_audit(flr_akey($me), 'shop_search', $tx, 'MLS search: ' . shop_summary($c));
echo json_encode(['ok' => true, 'criteria' => $c, 'summary' => shop_summary($c), 'rows' => $r['rows'], 'total' => $r['total'], 'note' => $r['note']]);
