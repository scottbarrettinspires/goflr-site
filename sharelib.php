<?php
// Send the buyer-signed offer documents to the listing agent as a secure download link (not attachments).
// The contract is not valid until the seller signs, so the email asks the listing agent to have the seller sign and return it.
// Library functions: sh_plan (what can be sent and to whom), sh_create (make the link), sh_send (email it through the agent's Gmail connector),
// sh_get / sh_bytes (public side, by token). Dates YYYY-MM-DD. Links expire after SH_DAYS days.
require_once __DIR__ . '/doclib.php';
require_once __DIR__ . '/oauthlib.php';
const SH_DAYS = 30;
function sh_kv_get($k) { $pdo = flr_db(); $pdo->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT)'); $q = $pdo->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute([$k]); $j = json_decode((string)$q->fetchColumn(), true); return is_array($j) ? $j : null; }
function sh_kv_put($k, array $v) { $pdo = flr_db(); $pdo->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT)'); $pdo->prepare('INSERT INTO kv (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')->execute([$k, json_encode($v)]); }
// What can be sent for this buyer record: the listing agent from the offer data, the signed documents, the draft email.
function sh_plan($me, $tx) {
    $me = strtolower(trim((string)$me)); $j = dc_list($me, $tx);
    if (empty($j['ok'])) return ['ok' => false, 'msg' => $j['msg'] ?? 'That record was not found in your business.'];
    $o = of_get($me, $tx); $of = !empty($o['ok']) ? $o['offer'] : [];
    $addr = !empty($o['ok']) ? (string)$o['address'] : '';
    $docs = [];
    foreach ($j['groups'] as $g) foreach ($g['items'] as $i) {
        $isC = (bool)preg_match('/contract packet|contract documents|purchase contract/i', $i['title']); $isS = (bool)preg_match('/seller disclosure/i', $i['title']);
        if ($isC || $isS) $docs[] = ['ref' => $i['ref'], 'title' => $i['title'], 'signed_on' => $i['signed_on'], 'checked' => true, 'kind' => $isC ? 'contract' : 'seller'];
    }
    $waiting = []; foreach ($j['pending'] as $p) $waiting[] = $p['title'];
    $la = trim(($of['oa_first'] ?? '') . ' ' . ($of['oa_last'] ?? ''));
    $me_name = trim((string)($_SESSION['name'] ?? '')) ?: $me;
    $subject = 'Offer' . ($addr !== '' ? ' on ' . $addr : '') . ' - buyer-signed contract and seller disclosures';
    $body = 'Hello ' . (trim($of['oa_first'] ?? '') !== '' ? trim($of['oa_first']) : 'there') . ",\n\n"
        . 'Attached by secure link are the buyer-signed contract offer and the seller disclosures signed by the buyer' . ($addr !== '' ? ' for ' . $addr : '') . ".\n\n"
        . "{LINK}\n\n"
        . "The contract is not valid until the seller signs it. Please have your seller review and sign, and send the fully signed copy back to me.\n\n"
        . "Thank you,\n" . $me_name . "\nFlorida Luxury Realty\n" . $me;
    $sent = sh_kv_get('sellersent:' . $tx);
    return ['ok' => true, 'tx' => $tx, 'name' => $j['name'], 'address' => $addr, 'to' => trim((string)($of['oa_email'] ?? '')), 'to_name' => $la, 'brokerage' => (string)($of['oa_brokerage'] ?? ''),
        'documents' => $docs, 'waiting' => $waiting, 'subject' => $subject, 'body' => $body, 'gmail' => !empty(oa_rows($me)['gmail']['on_flag']), 'last_sent' => $sent['last'] ?? null];
}
// Make the download link for the chosen documents (refs from sh_plan). Returns ['ok'=>true,'url'=>..,'token'=>..,'expires'=>YYYY-MM-DD] or ['ok'=>false,'msg'=>..].
function sh_create($me, $tx, array $refs, $to = '') {
    $me = strtolower(trim((string)$me)); $plan = sh_plan($me, $tx); if (!$plan['ok']) return $plan;
    $ok = array_column($plan['documents'], 'title', 'ref'); $use = [];
    foreach ($refs as $r) { $r = (string)$r; if (isset($ok[$r]) && !isset($use[$r])) $use[$r] = $ok[$r]; }
    if (!$use) return ['ok' => false, 'msg' => $plan['documents'] ? 'Check at least one document to send.' : 'There is no signed contract packet or seller disclosure on this record yet. Wait until the buyers have signed.'];
    $tok = bin2hex(random_bytes(20)); $exp = date('Y-m-d', strtotime('+' . SH_DAYS . ' days'));
    sh_kv_put('share:' . $tok, ['agent' => $me, 'tx' => $tx, 'docs' => array_map(function ($r, $t) { return ['ref' => $r, 'title' => $t]; }, array_keys($use), array_values($use)), 'address' => $plan['address'], 'agent_name' => (string)($_SESSION['name'] ?? ''), 'to' => (string)$to, 'created' => date('c'), 'expires' => $exp]);
    flr_audit(flr_akey($me), 'share_link', $tx, 'Download link created for ' . count($use) . ' document(s), expires ' . $exp);
    return ['ok' => true, 'token' => $tok, 'url' => 'https://goflr.com/share.php?t=' . $tok, 'expires' => $exp];
}
// Create the link and email it to the listing agent through the agent's own Gmail. $body must contain {LINK}. Returns ['ok'=>true,...] or ['ok'=>false,'msg'=>..].
function sh_send($me, $tx, array $refs, $to, $subject, $body, $copyMe = true) {
    $to = trim((string)$to); if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'msg' => 'Please enter a valid email address for the listing agent.'];
    $subject = trim(preg_replace('/\s+/', ' ', (string)$subject)); if ($subject === '' || strlen($subject) > 200) return ['ok' => false, 'msg' => 'Please enter a subject (up to 200 characters).'];
    $body = (string)$body; if (strpos($body, '{LINK}') === false) return ['ok' => false, 'msg' => 'Keep {LINK} in the message so the listing agent gets the download link.']; if (strlen($body) > 5000) return ['ok' => false, 'msg' => 'The message is too long.'];
    $l = sh_create($me, $tx, $refs, $to); if (empty($l['ok'])) return $l;
    $e = oa_gmail_send($me, $to, $subject, str_replace('{LINK}', $l['url'] . "\n(This link works until " . date('M j, Y', strtotime($l['expires'])) . '.)', $body), $copyMe ? strtolower(trim((string)$me)) : '');
    if ($e !== '') return ['ok' => false, 'msg' => $e, 'url' => $l['url']];
    $s = sh_kv_get('sellersent:' . $tx) ?: ['log' => []]; $s['last'] = ['at' => date('c'), 'to' => $to, 'docs' => count($refs)]; $s['log'][] = $s['last']; sh_kv_put('sellersent:' . $tx, $s);
    flr_audit(flr_akey($me), 'sent_to_listing_agent', $tx, 'Emailed download link to ' . $to);
    return ['ok' => true, 'url' => $l['url'], 'expires' => $l['expires']];
}
// Record that the agent sent it from their own email app (mailto) so the record shows it.
function sh_mark_sent($me, $tx, $to) {
    $s = sh_kv_get('sellersent:' . $tx) ?: ['log' => []]; $s['last'] = ['at' => date('c'), 'to' => (string)$to, 'docs' => 0, 'via' => 'email app']; $s['log'][] = $s['last']; sh_kv_put('sellersent:' . $tx, $s);
    flr_audit(flr_akey(strtolower($me)), 'sent_to_listing_agent', $tx, 'Opened the email to ' . $to . ' in their email app');
}
// Public side: the link record, or null when missing / expired.
function sh_get($token) {
    if (!preg_match('/^[a-f0-9]{40}$/', (string)$token)) return null;
    $r = sh_kv_get('share:' . $token); if (!$r || ($r['expires'] ?? '') < date('Y-m-d')) return null; return $r;
}
function sh_bytes($token, $n) {
    $r = sh_get($token); if (!$r || !isset($r['docs'][(int)$n])) return ['ok' => false, 'msg' => 'This link has expired or is not valid. Please ask the sender for a new one.'];
    $d = dcf_bytes($r['agent'], $r['docs'][(int)$n]['ref'], true);
    if (!empty($d['ok'])) flr_audit(flr_akey($r['agent']), 'share_download', $r['tx'], $r['docs'][(int)$n]['title']);
    return $d;
}
