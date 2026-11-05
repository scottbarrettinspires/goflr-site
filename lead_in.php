<?php
// Public lead intake. GET ?k=KEY shows the agent's contact form; POST ?k=KEY (form fields or JSON) adds the lead to that agent's NEW tab.
// Website builders, landing pages and Zapier can POST the same fields: first_name, last_name, name, phone, email, message, interest, source.
require_once __DIR__ . '/pushlib.php';
header('X-Robots-Tag: noindex, nofollow');
$key = (string)($_GET['k'] ?? '');
$hit = ld_intake_find($key);
$isJson = stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false;
$isApi = $isJson || (($_GET['format'] ?? '') === 'json');
if ($isApi) { header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Content-Type'); header('Content-Type: application/json'); }
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit; }
function li_fail($msg, $api, $code = 400) { http_response_code($code); if ($api) { echo json_encode(['ok' => false, 'msg' => $msg]); exit; } li_page('<h1>Something went wrong</h1><p>' . htmlspecialchars($msg) . '</p>'); }
function li_page($inner, $agent = null) {
    $who = $agent ? htmlspecialchars($agent['name'] ?? '') : '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . ($who ? $who . ' - ' : '') . 'Florida Luxury Realty</title>'
       . '<style>:root{--teal:#036f72}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#f4f8f8;color:#1f2a2c;line-height:1.5}'
       . '.top{background:var(--teal);padding:18px 16px;text-align:center}.top img{height:54px}.box{max-width:520px;margin:-10px auto 30px;background:#fff;border-radius:16px;padding:22px 20px;box-shadow:0 8px 30px rgba(0,0,0,.08)}'
       . 'h1{font-size:1.45rem;margin:0 0 6px}p{margin:0 0 14px}label{display:block;font-size:.85rem;font-weight:600;margin:10px 0 4px}input,textarea{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #cfdcdc;border-radius:10px;font:inherit}'
       . '.row{display:flex;gap:10px}.row>div{flex:1}.opts{display:flex;flex-wrap:wrap;gap:8px}.opts label{font-weight:500;display:flex;gap:6px;align-items:center;border:1px solid #cfdcdc;border-radius:99px;padding:7px 12px;margin:0;cursor:pointer}.opts input{width:auto}'
       . '.consent{display:flex;gap:10px;align-items:flex-start;font-size:.8rem;font-weight:400;color:#4b5b5d}.consent input{width:auto;margin-top:3px}button{margin-top:16px;width:100%;background:var(--teal);color:#fff;border:0;border-radius:12px;padding:14px;font:inherit;font-weight:700;font-size:1.05rem;cursor:pointer}'
       . '.hp{position:absolute;left:-9999px}.err{background:#fde2e1;color:#8c1c13;padding:10px 12px;border-radius:10px;font-size:.9rem}.foot{text-align:center;font-size:.78rem;color:#6a777a;margin:0 0 24px}</style></head><body>'
       . '<div class="top"><img src="logo-white.png" alt="Florida Luxury Realty"></div><div class="box">' . $inner . '</div><p class="foot">Florida Luxury Realty</p></body></html>';
    exit;
}
if (!$hit) li_fail('This contact link is not active. Please contact the agent directly.', $isApi, 404);
$agent = $hit['agent'];
$src = mb_substr(trim(preg_replace('/[^\w .&\'-]/u', '', (string)($_GET['src'] ?? $_POST['source'] ?? ''))), 0, 40);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $in = $isJson ? (json_decode((string)file_get_contents('php://input'), true) ?: []) : $_POST;
    $g = function ($k, $max = 120) use ($in) { return ld_clean(is_scalar($in[$k] ?? null) ? $in[$k] : '', $max); };
    if ($g('website') !== '' || $g('company_url') !== '') { if ($isApi) { echo json_encode(['ok' => true]); exit; } li_page('<h1>Thank you!</h1><p>Your message was sent.</p>', $agent); } // honeypot
    // Simple flood limits: 10 per hour from one address, 200 per day into one agent.
    $ip = sha1((string)($_SERVER['REMOTE_ADDR'] ?? '') . 'flr'); $rl = ld_dir() . '/rate-' . substr($ip, 0, 20) . '.json'; $now = time();
    $t = array_filter((array)json_decode((string)@file_get_contents($rl), true), function ($x) use ($now) { return $x > $now - 3600; });
    if (count($t) >= 10) li_fail('Too many submissions. Please try again later.', $isApi, 429);
    $first = $g('first_name', 60) ?: $g('first', 60); $last = $g('last_name', 60) ?: $g('last', 60);
    if ($first === '' && $last === '' && $g('name') !== '') { $p = preg_split('/\s+/', $g('name', 120), 2); $first = $p[0]; $last = $p[1] ?? ''; }
    $phone = $g('phone', 30); $email = $g('email', 120);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) li_fail('Please check the email address.', $isApi);
    if ($first === '' && $last === '') li_fail('Please enter your name.', $isApi);
    if ($phone === '' && $email === '') li_fail('Please enter a phone number or email address.', $isApi);
    $interest = $g('interest', 40); $okI = ['Buying', 'Selling', 'Buying and selling', 'Just exploring']; if (!in_array($interest, $okI, true)) $interest = $interest !== '' ? mb_substr($interest, 0, 40) : '';
    $consentGiven = !empty($in['consent']) || $isApi;
    if (!$isApi && !$consentGiven) li_fail('Please check the box to agree to be contacted.', false);
    $lead = ['first' => $first, 'last' => $last, 'phone' => $phone, 'email' => $email, 'message' => $g('message', 1500), 'interest' => $interest,
        'source' => $src !== '' ? $src : (mb_substr(trim(preg_replace('/[^\w .&\'-]/u', '', $g('source', 40))), 0, 40) ?: ($isApi ? 'Website' : 'Lead form')),
        'consent' => $consentGiven ? ['date' => date('c'), 'how' => $isApi ? 'Submitted through a connected website or landing page' : 'Checked the contact consent box on the FLR lead form'] : null];
    $r = ld_intake_add($hit['file'], $lead);
    $who = trim($first . ' ' . $last) ?: ($phone ?: $email);
    $bits = array_filter([$lead['source'], $interest]);
    @px_notify($hit['file'], (!empty($r['dup']) ? 'Lead reached out again: ' : 'New lead: ') . $who, implode(' · ', $bits) . ($phone !== '' ? ' · Tap to open, then call' : ' · Tap to open'), '/leads.php?open=' . urlencode($r['id'] ?? ''));
    $t[] = $now; @file_put_contents($rl, json_encode(array_values($t)), LOCK_EX);
    if ($isApi) { echo json_encode(['ok' => true, 'duplicate' => !empty($r['dup'])]); exit; }
    li_page('<h1>Thank you, ' . htmlspecialchars($first ?: 'friend') . '!</h1><p>' . htmlspecialchars($agent['name'] ?? 'Your agent') . ' will be in touch shortly.' . (!empty($agent['phone']) ? ' Need help right away? Call or text <a href="tel:' . htmlspecialchars(preg_replace('/[^0-9+]/', '', $agent['phone'])) . '">' . htmlspecialchars($agent['phone']) . '</a>.' : '') . '</p>', $agent);
}
if ($isApi) { echo json_encode(['ok' => true, 'agent' => $agent['name'] ?? '', 'fields' => ['first_name', 'last_name', 'name', 'phone', 'email', 'message', 'interest', 'source']]); exit; }
$an = htmlspecialchars($agent['name'] ?? 'your agent');
$form = '<h1>Let\'s talk real estate</h1><p>Send ' . $an . ' a quick note and you\'ll hear back soon.</p>'
  . '<form method="post" action="lead_in.php?k=' . htmlspecialchars($key) . ($src !== '' ? '&amp;src=' . urlencode($src) : '') . '">'
  . '<div class="row"><div><label for="fn">First name</label><input id="fn" name="first_name" required autocomplete="given-name"></div><div><label for="ln">Last name</label><input id="ln" name="last_name" autocomplete="family-name"></div></div>'
  . '<label for="ph">Phone</label><input id="ph" name="phone" type="tel" autocomplete="tel"><label for="em">Email</label><input id="em" name="email" type="email" autocomplete="email">'
  . '<label>I\'m interested in</label><div class="opts">' . implode('', array_map(function ($o) { return '<label><input type="radio" name="interest" value="' . $o . '"> ' . $o . '</label>'; }, ['Buying', 'Selling', 'Buying and selling', 'Just exploring'])) . '</div>'
  . '<label for="ms">Message (optional)</label><textarea id="ms" name="message" rows="3" placeholder="Area, price range, timing, or any question"></textarea>'
  . '<div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>'
  . '<label class="consent"><input type="checkbox" name="consent" value="1" required> <span>By submitting, I agree that ' . $an . ' with Florida Luxury Realty may contact me by call, text or email about real estate. Message and data rates may apply. Reply STOP to opt out of texts. Consent is not a condition of any purchase.</span></label>'
  . '<button type="submit">Send</button></form>'
  . '<script>document.querySelector("form").addEventListener("submit",function(e){var p=document.getElementById("ph").value.trim(),m=document.getElementById("em").value.trim();if(!p&&!m){e.preventDefault();alert("Please enter a phone number or email address.");}});</script>';
li_page($form, $agent);
