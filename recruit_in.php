<?php
// Public recruiting intake. GET ?k=KEY shows a short form; POST (form fields or JSON) adds the person to the
// shared recruiting pipeline as New. Also where an agent hands off a co-op/outside agent they met during a
// transaction: pass ref=Agent Name (or referred_by) so the broker sees who introduced them.
require_once __DIR__ . '/pushlib.php';
require_once __DIR__ . '/rcrlib.php';
require_once __DIR__ . '/dlib.php';
$GLOBALS['FLR_ACTOR'] = 'recruiting form';
header('X-Robots-Tag: noindex, nofollow');
$key = (string)($_GET['k'] ?? '');
$intake = rcr_intake_find($key);
$isJson = stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false;
$isApi = $isJson || (($_GET['format'] ?? '') === 'json');
if ($isApi) { header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type'); header('Content-Type: application/json'); }
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }
function ri_fail($msg, $api, $code = 400) { http_response_code($code); if ($api) { echo json_encode(['ok' => false, 'msg' => $msg]); exit; } ri_page('<h1>Something went wrong</h1><p>' . htmlspecialchars($msg) . '</p>'); }
function ri_page($inner) {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Join Florida Luxury Realty</title>'
       . '<style>:root{--teal:#036f72}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#f4f8f8;color:#1f2a2c;line-height:1.5}'
       . '.top{background:var(--teal);padding:18px 16px;text-align:center}.top img{height:54px}.box{max-width:520px;margin:-10px auto 30px;background:#fff;border-radius:16px;padding:22px 20px;box-shadow:0 8px 30px rgba(0,0,0,.08)}'
       . 'h1{font-size:1.45rem;margin:0 0 6px}p{margin:0 0 14px}label{display:block;font-size:.85rem;font-weight:600;margin:10px 0 4px}input,textarea,select{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #cfdcdc;border-radius:10px;font:inherit}'
       . '.row{display:flex;gap:10px}.row>div{flex:1}'
       . '.consent{display:flex;gap:10px;align-items:flex-start;font-size:.8rem;font-weight:400;color:#4b5b5d}.consent input{width:auto;margin-top:3px}button{margin-top:16px;width:100%;background:var(--teal);color:#fff;border:0;border-radius:12px;padding:14px;font:inherit;font-weight:700;font-size:1.05rem;cursor:pointer}'
       . '.hp{position:absolute;left:-9999px}.err{background:#fde2e1;color:#8c1c13;padding:10px 12px;border-radius:10px;font-size:.9rem}.foot{text-align:center;font-size:.78rem;color:#6a777a;margin:0 0 24px}</style></head><body>'
       . '<div class="top"><img src="logo-white.png" alt="Florida Luxury Realty"></div><div class="box">' . $inner . '</div><p class="foot">Florida Luxury Realty</p></body></html>';
    exit;
}
if (!$intake) ri_fail('This link is not active. Please contact Florida Luxury Realty directly.', $isApi, 404);
$src = mb_substr(trim(preg_replace('/[^\w .&\'-]/u', '', (string)($_GET['src'] ?? $_POST['source'] ?? ''))), 0, 40);
$ref = mb_substr(trim(preg_replace('/[^\w .&\'-]/u', '', (string)($_GET['ref'] ?? $_POST['referred_by'] ?? ''))), 0, 80);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in = $isJson ? (json_decode((string)file_get_contents('php://input'), true) ?: []) : $_POST;
    $g = function ($k, $max = 120) use ($in) { return rcr_clean(is_scalar($in[$k] ?? null) ? $in[$k] : '', $max); };
    if ($g('website') !== '' || $g('company_url') !== '') { if ($isApi) { echo json_encode(['ok' => true]); exit; } ri_page('<h1>Thank you!</h1><p>Your message was sent.</p>'); } // honeypot
    $ip = sha1((string)($_SERVER['REMOTE_ADDR'] ?? '') . 'flrrcr'); $rl = rcr_dir() . '/rate-rcr-' . substr($ip, 0, 20) . '.json'; $now = time();
    $t = array_filter((array)json_decode((string)@file_get_contents($rl), true), function ($x) use ($now) { return $x > $now - 3600; });
    if (count($t) >= 10) ri_fail('Too many submissions. Please try again later.', $isApi, 429);
    $first = $g('first_name', 60) ?: $g('first', 60); $last = $g('last_name', 60) ?: $g('last', 60);
    if ($first === '' && $last === '' && $g('name') !== '') { $p = preg_split('/\s+/', $g('name', 120), 2); $first = $p[0]; $last = $p[1] ?? ''; }
    $phone = $g('phone', 30); $email = $g('email', 120);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) ri_fail('Please check the email address.', $isApi);
    if ($first === '' && $last === '') ri_fail('Please enter your name.', $isApi);
    if ($phone === '' && $email === '') ri_fail('Please enter a phone number or email address.', $isApi);
    $typeIn = $g('type', 40);
    $typeMap = ['new' => 'new_prelicense', 'new licensee' => 'new_prelicense', 'licensed' => 'other_brokerage', 'licensed - other brokerage' => 'other_brokerage'];
    $type = array_key_exists($typeIn, RCR_TYPES) ? $typeIn : ($typeMap[strtolower($typeIn)] ?? 'other_brokerage');
    $referredBy = $ref !== '' ? $ref : $g('referred_by', 80);
    $consentGiven = !empty($in['consent']) || $isApi;
    if (!$isApi && !$consentGiven) ri_fail('Please check the box to agree to be contacted.', false);
    $data = ['first' => $first, 'last' => $last, 'phone' => $phone, 'email' => $email, 'message' => $g('message', 1500),
        'current_brokerage' => $g('current_brokerage', 80), 'type' => $type, 'referred_by' => $referredBy,
        'source' => $src !== '' ? $src : (mb_substr(trim(preg_replace('/[^\w .&\'-]/u', '', $g('source', 40))), 0, 40) ?: ($isApi ? 'Website' : 'Recruiting form'))];
    $r = rcr_intake_add($data);
    $who = trim($first . ' ' . $last) ?: ($phone ?: $email);
    // Pushed to whichever broker/staff accounts have push notifications turned on in their own PWA -- notifications
    // are keyed per signed-in user, so this fans out to everyone in FLR_BROKER_EMAILS rather than one shared inbox.
    foreach (FLR_BROKER_EMAILS as $bemail) {
        @px_notify(flr_akey($bemail) . '.json', (!empty($r['dup']) ? 'Recruit reached out again: ' : 'New recruiting contact: ') . $who, $data['source'] . ($referredBy ? ' · Handed off by ' . $referredBy : '') . ($phone !== '' ? ' · Tap to open, then call' : ' · Tap to open'), '/recruiting.php?id=' . urlencode($r['id'] ?? ''));
    }
    $t[] = $now; @file_put_contents($rl, json_encode(array_values($t)), LOCK_EX);
    if ($isApi) { echo json_encode(['ok' => true, 'duplicate' => !empty($r['dup'])]); exit; }
    ri_page('<h1>Thank you, ' . htmlspecialchars($first ?: 'friend') . '!</h1><p>Someone from Florida Luxury Realty will be in touch shortly. Your success is our mission.</p>');
}
if ($isApi) { echo json_encode(['ok' => true, 'fields' => ['first_name', 'last_name', 'name', 'phone', 'email', 'current_brokerage', 'type', 'referred_by', 'message', 'source']]); exit; }
$form = '<h1>Let\'s talk about joining FLR</h1><p>Send us a quick note and we\'ll be in touch soon.</p>'
  . '<form method="post" action="recruit_in.php?k=' . htmlspecialchars($key) . ($src !== '' ? '&amp;src=' . urlencode($src) : '') . ($ref !== '' ? '&amp;ref=' . urlencode($ref) : '') . '">'
  . '<div class="row"><div><label for="fn">First name</label><input id="fn" name="first_name" required autocomplete="given-name"></div><div><label for="ln">Last name</label><input id="ln" name="last_name" autocomplete="family-name"></div></div>'
  . '<label for="ph">Phone</label><input id="ph" name="phone" type="tel" autocomplete="tel"><label for="em">Email</label><input id="em" name="email" type="email" autocomplete="email">'
  . '<label for="cb">Current brokerage (if any)</label><input id="cb" name="current_brokerage" autocomplete="organization">'
  . '<label for="ty">Where are you in your real estate career?</label><select id="ty" name="type"><option value="other_brokerage">Licensed, at another brokerage</option><option value="new_prelicense">New or working on my license</option></select>'
  . '<label for="ms">Anything you want us to know (optional)</label><textarea id="ms" name="message" rows="3"></textarea>'
  . '<div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>'
  . '<label class="consent"><input type="checkbox" name="consent" value="1" required> <span>By submitting, I agree that Florida Luxury Realty may contact me by call, text or email about joining the brokerage. Message and data rates may apply. Reply STOP to opt out of texts. Consent is not a condition of anything.</span></label>'
  . '<button type="submit">Send</button></form>'
  . '<script>document.querySelector("form").addEventListener("submit",function(e){var p=document.getElementById("ph").value.trim(),m=document.getElementById("em").value.trim();if(!p&&!m){e.preventDefault();alert("Please enter a phone number or email address.");}});</script>';
ri_page($form);
