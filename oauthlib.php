<?php
// Connectors: real "connect your account" sign-ins (OAuth 2.0) for Gmail, Google Calendar, Google Drive, Google
// Business Profile, Facebook Pages, Instagram, LinkedIn, X and TikTok.
// The brokerage registers ONE app with each company (Google, Meta, LinkedIn, X, TikTok) and staff paste that app's
// Client ID and Client Secret into Settings > Connectors > Office setup. They're kept on the server outside the web
// root (flrdata/oauth.json, owner-only). Each agent's tokens are stored encrypted (AES-256-GCM, key in flrdata/oauth.key).
// Every service sends people back to one address: https://goflr.com/oauth_cb.php (register it with each app).
require_once __DIR__ . '/flrdb.php';

const OA_REDIRECT = 'https://goflr.com/oauth_cb.php';
// The app each connector signs in through, with where staff create it.
const OA_APPS = [
    'google'   => ['Google', 'https://console.cloud.google.com/apis/credentials', ['Create a project in Google Cloud, then APIs & Services > OAuth consent screen: External, add the FLR name, logo and support email.', 'Enable the Gmail API, Google Calendar API, Google Drive API and the Business Profile APIs (Business Profile access needs Google\'s approval request).', 'Credentials > Create credentials > OAuth client ID > Web application. Add the redirect address shown here.', 'Copy the Client ID and Client secret here. While the app is in Testing, add each agent\'s Google email as a test user; publishing it for everyone needs Google\'s verification (Gmail especially).']],
    'meta'     => ['Meta (Facebook and Instagram)', 'https://developers.facebook.com/apps', ['Create an app at developers.facebook.com (type: Business) owned by the FLR Business Manager.', 'Add Facebook Login for Business, and the Instagram Graph API. Add the redirect address shown here under Valid OAuth Redirect URIs.', 'Request the permissions pages_show_list, pages_read_engagement, pages_manage_posts, instagram_basic and instagram_content_publish (App Review).', 'Copy the App ID and App secret here.']],
    'linkedin' => ['LinkedIn', 'https://www.linkedin.com/developers/apps', ['Create an app at linkedin.com/developers linked to the FLR company page.', 'Products: add "Sign In with LinkedIn using OpenID Connect" and "Share on LinkedIn".', 'Auth tab: add the redirect address shown here.', 'Copy the Client ID and Client secret here.']],
    'x'        => ['X (Twitter)', 'https://developer.x.com/en/portal/dashboard', ['Sign up for an X developer account (posting needs a paid API plan).', 'Create a project and app; User authentication settings: OAuth 2.0, type Web App, read and write.', 'Add the redirect address shown here as the Callback URI.', 'Copy the OAuth 2.0 Client ID and Client secret here.']],
    'tiktok'   => ['TikTok', 'https://developers.tiktok.com/apps', ['Create an app at developers.tiktok.com.', 'Add Login Kit and Content Posting API; request the user.info.basic and video.publish scopes (TikTok reviews the app).', 'Add the redirect address shown here.', 'Copy the Client key and Client secret here.']],
];
// key => [name, use, group, app, scopes, what the agent will be asked to do, what they need first]
const OA_CONNECTORS = [
    'gmail'     => ['Gmail', 'Send client emails from the back office.', 'Google', 'google', 'openid email https://www.googleapis.com/auth/gmail.send', 'Sign in with the Google account you email clients from, then allow FLR Office to send email for you.', ''],
    'gcal'      => ['Google Calendar', 'Put showings, deadlines and closings on your calendar.', 'Google', 'google', 'openid email https://www.googleapis.com/auth/calendar.events', 'Sign in with your Google account, then allow FLR Office to add and update events on your calendar.', ''],
    'gdrive'    => ['Google Drive', 'Save forms, signed documents and marketing to your Drive.', 'Google', 'google', 'openid email https://www.googleapis.com/auth/drive.file', 'Sign in with your Google account, then allow FLR Office to save files it creates to your Drive. It can only see files it saved.', ''],
    'gbp'       => ['Google Business Profile', 'Post listings and updates to your Google Business Profile.', 'Google', 'google', 'openid email https://www.googleapis.com/auth/business.manage', 'Sign in with the Google account that manages your Business Profile, then allow FLR Office to manage posts.', 'A verified Google Business Profile that you own or manage.'],
    'facebook'  => ['Facebook Business Page', 'Share listings, open houses and closings to your Page.', 'Social media', 'meta', 'pages_show_list,pages_read_engagement,pages_manage_posts', 'Log in to Facebook, choose the Business Page(s) to connect, then allow FLR Office to post to them.', 'A Facebook Business Page where you are an admin.'],
    'instagram' => ['Instagram', 'Post listing photos and reels to your professional account.', 'Social media', 'meta', 'instagram_basic,instagram_content_publish,pages_show_list', 'Log in to Facebook, choose the Page linked to your Instagram, pick your Instagram account, then allow posting.', 'An Instagram Professional (Business or Creator) account linked to your Facebook Page.'],
    'linkedin'  => ['LinkedIn', 'Share wins and market updates with your network.', 'Social media', 'linkedin', 'openid profile email w_member_social', 'Sign in to LinkedIn, then allow FLR Office to post on your behalf.', ''],
    'x'         => ['X (Twitter)', 'Post listings and quick market updates.', 'Social media', 'x', 'tweet.read tweet.write users.read offline.access', 'Sign in to X, then authorize FLR Office to post for you.', ''],
    'tiktok'    => ['TikTok', 'Share listing videos and tours.', 'Social media', 'tiktok', 'user.info.basic,video.publish', 'Log in to TikTok, then authorize FLR Office to post videos to your account.', ''],
];

// ---- Office setup (app keys) ----
function oa_cfg_file() { return flr_db_dir() . '/oauth.json'; }
function oa_cfg() { $j = json_decode((string)@file_get_contents(oa_cfg_file()), true); return is_array($j) ? $j : []; }
function oa_cfg_save(array $c) { $f = oa_cfg_file(); file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT), LOCK_EX); @chmod($f, 0600); }
function oa_app_ready($app) { $c = oa_cfg()[$app] ?? []; return !empty($c['id']) && !empty($c['secret']); }
function oa_ready($p) { return isset(OA_CONNECTORS[$p]) && oa_app_ready(OA_CONNECTORS[$p][3]); }

// ---- Token encryption ----
function oa_key() {
    static $k = null; if ($k !== null) return $k;
    $f = flr_db_dir() . '/oauth.key';
    if (!is_file($f)) { file_put_contents($f, base64_encode(random_bytes(32)), LOCK_EX); @chmod($f, 0600); }
    return $k = base64_decode(trim((string)file_get_contents($f)));
}
function oa_enc(array $data) { $iv = random_bytes(12); $tag = ''; $c = openssl_encrypt(json_encode($data), 'aes-256-gcm', oa_key(), OPENSSL_RAW_DATA, $iv, $tag); return base64_encode($iv . $tag . $c); }
function oa_dec($s) { $b = base64_decode((string)$s); if (strlen($b) < 29) return null; $p = openssl_decrypt(substr($b, 28), 'aes-256-gcm', oa_key(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16)); return $p === false ? null : json_decode($p, true); }

// ---- Agent connections ----
function oa_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS connectors (email TEXT NOT NULL, provider TEXT NOT NULL, on_flag INTEGER DEFAULT 0, updated TEXT, PRIMARY KEY (email, provider))');
    static $done = false;
    if (!$done) { $done = true; $cols = array_column($pdo->query('PRAGMA table_info(connectors)')->fetchAll(), 'name');
        foreach (['account' => 'TEXT', 'tokens' => 'TEXT', 'connected_at' => 'TEXT'] as $c => $t) if (!in_array($c, $cols, true)) $pdo->exec("ALTER TABLE connectors ADD COLUMN $c $t"); }
    return $pdo;
}
function oa_rows($email) { $q = oa_db()->prepare('SELECT * FROM connectors WHERE email = ?'); $q->execute([strtolower($email)]); $o = []; foreach ($q->fetchAll() as $r) $o[$r['provider']] = $r; return $o; }
function oa_set($email, $p, array $set) {
    $set += ['updated' => date('c')]; $cols = array_keys($set);
    oa_db()->prepare('INSERT INTO connectors (email, provider, ' . implode(', ', $cols) . ') VALUES (?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ') ON CONFLICT(email, provider) DO UPDATE SET ' . implode(', ', array_map(function ($c) { return "$c = excluded.$c"; }, $cols)))
        ->execute(array_merge([strtolower($email), $p], array_values($set)));
}
function oa_disconnect($email, $p) {
    $r = oa_rows($email)[$p] ?? null; $t = $r ? oa_dec($r['tokens'] ?? '') : null;
    if ($t && OA_CONNECTORS[$p][3] === 'google' && !empty($t['refresh_token'] ?? $t['access_token'] ?? '')) oa_http('POST', 'https://oauth2.googleapis.com/revoke', ['token' => $t['refresh_token'] ?? $t['access_token']]);
    oa_set($email, $p, ['on_flag' => 0, 'account' => null, 'tokens' => null, 'connected_at' => null]);
    flr_audit(flr_akey($email), 'connector_off', $p, 'Disconnected');
}

// ---- HTTP ----
function oa_http($method, $url, array $form = [], array $headers = []) {
    $ch = curl_init($method === 'GET' && $form ? $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($form) : $url);
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers)];
    if ($method === 'POST') { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = http_build_query($form); }
    curl_setopt_array($ch, $o); $b = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = json_decode((string)$b, true); return [$code, is_array($j) ? $j : []];
}
function oa_b64url($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function oa_jwt_payload($jwt) { $p = explode('.', (string)$jwt); return count($p) === 3 ? (json_decode(base64_decode(strtr($p[1], '-_', '+/')), true) ?: []) : []; }

// ---- Sign-in: where to send the agent ----
function oa_authorize_url($p) {
    $c = OA_CONNECTORS[$p]; $app = $c[3]; $cfg = oa_cfg()[$app];
    $state = bin2hex(random_bytes(16)); $verifier = oa_b64url(random_bytes(48));
    $_SESSION['oa'][$state] = ['p' => $p, 'v' => $verifier, 't' => time()];
    $q = ['response_type' => 'code', 'redirect_uri' => OA_REDIRECT, 'state' => $state, 'scope' => $c[4]];
    switch ($app) {
        case 'google': $u = 'https://accounts.google.com/o/oauth2/v2/auth'; $q += ['client_id' => $cfg['id'], 'access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true']; break;
        case 'meta': $u = 'https://www.facebook.com/' . ($cfg['version'] ?? 'v21.0') . '/dialog/oauth'; $q += ['client_id' => $cfg['id']]; break;
        case 'linkedin': $u = 'https://www.linkedin.com/oauth/v2/authorization'; $q += ['client_id' => $cfg['id']]; break;
        case 'x': $u = 'https://twitter.com/i/oauth2/authorize'; $q += ['client_id' => $cfg['id'], 'code_challenge' => oa_b64url(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256']; break;
        case 'tiktok': $u = 'https://www.tiktok.com/v2/auth/authorize/'; $q += ['client_key' => $cfg['id']]; break;
    }
    return $u . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
}
// Back from the service: swap the code for tokens, find the account name, save. Returns [ok, message].
function oa_finish($email, $state, $code) {
    $s = $_SESSION['oa'][$state] ?? null; unset($_SESSION['oa'][$state]);
    if (!$s || time() - $s['t'] > 900) return [false, 'That sign-in expired. Please turn the connector on again.'];
    $p = $s['p']; $app = OA_CONNECTORS[$p][3]; $cfg = oa_cfg()[$app] ?? []; if (empty($cfg['id'])) return [false, 'This connection is not set up yet.'];
    $tok = []; $acct = '';
    switch ($app) {
        case 'google':
            list($h, $tok) = oa_http('POST', 'https://oauth2.googleapis.com/token', ['code' => $code, 'client_id' => $cfg['id'], 'client_secret' => $cfg['secret'], 'redirect_uri' => OA_REDIRECT, 'grant_type' => 'authorization_code']);
            $acct = (string)(oa_jwt_payload($tok['id_token'] ?? '')['email'] ?? ''); break;
        case 'meta':
            $base = 'https://graph.facebook.com/' . ($cfg['version'] ?? 'v21.0');
            list($h, $tok) = oa_http('GET', $base . '/oauth/access_token', ['client_id' => $cfg['id'], 'client_secret' => $cfg['secret'], 'redirect_uri' => OA_REDIRECT, 'code' => $code]);
            if (!empty($tok['access_token'])) { list($h2, $long) = oa_http('GET', $base . '/oauth/access_token', ['grant_type' => 'fb_exchange_token', 'client_id' => $cfg['id'], 'client_secret' => $cfg['secret'], 'fb_exchange_token' => $tok['access_token']]); if (!empty($long['access_token'])) $tok = $long + $tok;
                list($h3, $me) = oa_http('GET', $base . '/me', ['fields' => 'name', 'access_token' => $tok['access_token']]); $acct = (string)($me['name'] ?? ''); }
            break;
        case 'linkedin':
            list($h, $tok) = oa_http('POST', 'https://www.linkedin.com/oauth/v2/accessToken', ['code' => $code, 'client_id' => $cfg['id'], 'client_secret' => $cfg['secret'], 'redirect_uri' => OA_REDIRECT, 'grant_type' => 'authorization_code']);
            $pl = oa_jwt_payload($tok['id_token'] ?? ''); $acct = (string)($pl['name'] ?? ($pl['email'] ?? '')); break;
        case 'x':
            list($h, $tok) = oa_http('POST', 'https://api.twitter.com/2/oauth2/token', ['code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => OA_REDIRECT, 'code_verifier' => $s['v'], 'client_id' => $cfg['id']], ['Authorization: Basic ' . base64_encode($cfg['id'] . ':' . $cfg['secret'])]);
            if (!empty($tok['access_token'])) { list($h2, $me) = oa_http('GET', 'https://api.twitter.com/2/users/me', [], ['Authorization: Bearer ' . $tok['access_token']]); $acct = !empty($me['data']['username']) ? '@' . $me['data']['username'] : ''; }
            break;
        case 'tiktok':
            list($h, $tok) = oa_http('POST', 'https://open.tiktokapis.com/v2/oauth/token/', ['client_key' => $cfg['id'], 'client_secret' => $cfg['secret'], 'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => OA_REDIRECT]);
            if (!empty($tok['access_token'])) { list($h2, $me) = oa_http('GET', 'https://open.tiktokapis.com/v2/user/info/', ['fields' => 'display_name'], ['Authorization: Bearer ' . $tok['access_token']]); $acct = (string)($me['data']['user']['display_name'] ?? ''); }
            break;
    }
    if (empty($tok['access_token'])) return [false, OA_CONNECTORS[$p][0] . ' did not finish connecting' . (!empty($tok['error_description']) ? ': ' . $tok['error_description'] : (!empty($tok['error']['message']) ? ': ' . $tok['error']['message'] : '.')) . ' Please try again.'];
    $tok['obtained'] = time();
    oa_set($email, $p, ['on_flag' => 1, 'account' => mb_substr($acct, 0, 120), 'tokens' => oa_enc($tok), 'connected_at' => date('c')]);
    flr_audit(flr_akey($email), 'connector_on', $p, 'Connected' . ($acct !== '' ? ' as ' . $acct : ''));
    return [true, $p];
}
