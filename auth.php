<?php
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
function out($ok, $msg = '') { echo json_encode(['ok' => $ok, 'msg' => $msg]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); out(false); }
$in = json_decode(file_get_contents('php://input'), true);
$cred = is_array($in) ? ($in['credential'] ?? '') : '';
if ($cred === '' || strlen($cred) > 4096) out(false, 'bad');
$info = flr_get_json('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($cred));
if (!$info) out(false, 'bad');
$okIss = in_array($info['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
if (($info['aud'] ?? '') !== GOOGLE_CLIENT_ID || !$okIss || (int)($info['exp'] ?? 0) < time() || ($info['email_verified'] ?? '') !== 'true' || empty($info['email'])) out(false, 'bad');
$email = strtolower($info['email']);
$ri = flr_roster_info($email);
$staff = null;
if (!is_array($ri) || ($ri['active'] ?? false) !== true) { $staff = flr_staff_info($email); if (!$staff) out(false, 'notlisted'); $ri = $staff; }
flr_session();
session_regenerate_id(true);
$pic = '';
if (flr_store_photo($email, $ri['photo'] ?? '')) { $pic = 'photo.php'; }
elseif (isset($info['picture']) && preg_match('#^https://[a-z0-9.-]+\\.googleusercontent\\.com/#i', $info['picture'])) { $pic = $info['picture']; }
if ($staff && !empty($staff['photo_key'])) { $pic = 'dirphoto.php?k=' . $staff['photo_key']; }
$_SESSION = ['email' => $email, 'name' => ($staff && $staff['name'] !== '') ? $staff['name'] : ($info['name'] ?? ''), 'pic' => $pic, 'checked' => time(), 'perm' => flr_perms($ri), 'staff' => $staff ? ($staff['position'] ?: 'Staff') : ''];
out(true);
