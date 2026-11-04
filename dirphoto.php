<?php
// Serves one directory headshot to a signed-in agent. Fetched from the office script once, then kept for a week.
require __DIR__ . "/guard.php";
$k = (string)($_GET['k'] ?? '');
if (!preg_match('/^[a-f0-9]{20}$/', $k)) { http_response_code(404); exit; }
$path = flr_dir_dir() . '/d-' . $k . '.jpg';
if (!is_file($path) || time() - filemtime($path) > 7 * 86400) {
    $d = flr_directory(); $fid = '';
    if ($d) { foreach (array_merge((array)($d['staff'] ?? []), (array)($d['agents'] ?? [])) as $p) { if (!empty($p['photo']) && flr_dir_photo_key($p['photo']) === $k) { $fid = $p['photo']; break; } } }
    $ok = false;
    if ($fid !== '') {
        $j = flr_get_json(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=dirphoto&id=' . urlencode($fid));
        if (is_array($j) && !empty($j['ok']) && !empty($j['photo'])) {
            $tmp = '__dir_' . $k; // reuse the shrink/crop routine
            $ok = flr_store_photo($tmp, $j['photo']);
            if ($ok) { @rename(flr_photo_path($tmp), $path); }
        }
    }
    if (!$ok && !is_file($path)) { http_response_code(404); exit; }
}
header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=86400');
readfile($path);
