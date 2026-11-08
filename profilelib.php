<?php
// Profile (Settings > Profile): each person's headshot and, for agents, their personal logo in color and in white.
// Uploads are stored on the server outside the web root (flrdata/profile) and served by pfimg.php to signed-in users.
// An uploaded headshot replaces the Agent Records / roster photo everywhere the site shows people (Home, Directory,
// Messages, Collaboration) via flr_headshot_url(). The agent's details come from Agent Records and are read-only here.
require_once __DIR__ . '/flrdb.php';

const PF_KINDS = ['headshot' => 'Headshot', 'logo' => 'Logo (color)', 'logow' => 'Logo (white)'];
const PF_MAX_UPLOAD = 15000000;

function pf_dir() { $d = flr_db_dir() . '/profile'; if (!is_dir($d)) @mkdir($d, 0750, true); return $d; }
function pf_key($email) { return substr(sha1('pf|' . strtolower(trim((string)$email))), 0, 24); }
function pf_path($key, $kind) { return pf_dir() . '/' . $key . '-' . $kind . ($kind === 'headshot' ? '.jpg' : '.png'); }
function pf_has($email, $kind) { return $email !== '' && is_file(pf_path(pf_key($email), $kind)); }
function pf_url($email, $kind) { $p = pf_path(pf_key($email), $kind); return is_file($p) ? 'pfimg.php?k=' . pf_key($email) . '&t=' . $kind . '&v=' . filemtime($p) : ''; }

// Save an uploaded image. Headshot: centered square, 600px JPEG. Logos: up to 1200px on the long side, PNG with
// transparency kept. Returns '' or an error message.
function pf_save_upload($email, $kind, array $up) {
    if (!isset(PF_KINDS[$kind])) return 'Unknown picture.';
    if (empty($up['tmp_name']) || !is_uploaded_file($up['tmp_name'])) return 'Choose a picture to upload.';
    if ((int)$up['size'] > PF_MAX_UPLOAD) return 'That picture is too large (15 MB max).';
    $raw = (string)file_get_contents($up['tmp_name']);
    $info = @getimagesizefromstring($raw);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return 'Please upload a JPG, PNG or WebP picture.';
    $im = @imagecreatefromstring($raw); if (!$im) return 'That picture could not be read. Please try another file.';
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) { // phone photos: turn upright
        $o = (int)(@exif_read_data($up['tmp_name'])['Orientation'] ?? 1);
        if ($o === 3) $im = imagerotate($im, 180, 0); elseif ($o === 6) $im = imagerotate($im, -90, 0); elseif ($o === 8) $im = imagerotate($im, 90, 0);
    }
    $w = imagesx($im); $h = imagesy($im); $path = pf_path(pf_key($email), $kind); $tmp = $path . '.tmp';
    if ($kind === 'headshot') {
        $s = min($w, $h); $x = (int)(($w - $s) / 2); $y = (int)max(0, ($h - $s) / 2 - $s * 0.08); // a little above center keeps the face in frame
        $y = min($y, $h - $s); $out = imagecreatetruecolor(600, 600);
        imagecopyresampled($out, $im, 0, 0, $x, $y, 600, 600, $s, $s);
        $ok = imagejpeg($out, $tmp, 86);
    } else {
        $sc = min(1, 1200 / max($w, $h)); $nw = max(1, (int)round($w * $sc)); $nh = max(1, (int)round($h * $sc));
        $out = imagecreatetruecolor($nw, $nh); imagealphablending($out, false); imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = imagepng($out, $tmp, 6);
    }
    if (!$ok || !@rename($tmp, $path)) { @unlink($tmp); return 'The picture could not be saved. Please try again.'; }
    flr_audit(flr_akey($email), 'profile_' . $kind, '', 'Uploaded a new ' . strtolower(PF_KINDS[$kind]));
    return '';
}
function pf_remove($email, $kind) {
    if (!isset(PF_KINDS[$kind])) return;
    @unlink(pf_path(pf_key($email), $kind));
    flr_audit(flr_akey($email), 'profile_' . $kind . '_remove', '', 'Removed ' . strtolower(PF_KINDS[$kind]));
}

// The person's Agent Records row, matched by sign-in email (or the record's Google Account), or null.
function pf_agent_record($email) {
    require_once __DIR__ . '/arlib.php';
    $email = strtolower(trim((string)$email)); if ($email === '') return null;
    $q = ar_db()->prepare('SELECT agent_id FROM agent_records WHERE email = ?'); $q->execute([$email]); $id = $q->fetchColumn();
    if ($id) return ar_get($id);
    foreach (ar_all() as $r) if (strtolower(trim((string)($r['fields']['google_account'] ?? ''))) === $email) return $r;
    return null;
}
