<?php
// Celebration posts: on an agent's birthday or work anniversary, the office automatically posts a graphic in FLR's
// style (Happy Birthday / Happy Workiversary, the agent's headshot, name, years, FLR logo) to the Collaboration
// Lounge, once. The Home "Let's Celebrate" card then links to that post. Only for Active agents whose Agent Records
// say the post is OK (Birthday post OK / Anniversary post OK = Yes). Graphics are drawn here with GD at 2x and scaled
// down for smooth edges; fonts are in /fonts (all SIL Open Font License).
require_once __DIR__ . '/collablib.php';
require_once __DIR__ . '/arlib.php';
date_default_timezone_set('America/New_York');

function cb_db() {
    $pdo = co_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS celeb_posts (k TEXT PRIMARY KEY, post_id INTEGER, created TEXT)');
    return $pdo;
}
function cb_key($D) { return $D['type'] . '|' . $D['agent_id'] . '|' . $D['date']; }
// The Collaboration Lounge post for one celebration ($D from ar_upcoming_dates), or 0.
function cb_post_id($D) {
    static $map = null;
    if ($map === null) { $map = []; foreach (cb_db()->query('SELECT k, post_id FROM celeb_posts WHERE post_id > 0') as $r) $map[$r['k']] = (int)$r['post_id']; }
    $id = $map[cb_key($D)] ?? 0;
    return $id && co_post($id) ? $id : 0;
}

// Post today's celebrations that haven't been posted yet. Cheap to call on every page view (checks at most every
// 10 minutes); each celebration is claimed in the table first so two visitors can't post it twice.
function cb_run($force = false) {
    $pdo = cb_db(); $flag = 'cb_check';
    if (!$force) {
        $q = $pdo->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute([$flag]);
        if (time() - (int)$q->fetchColumn() < 60) return 0;
        $pdo->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)')->execute([$flag, (string)time()]);
    }
    $n = cb_examples();
    foreach (ar_upcoming_dates(0) as $D) {
        if ((int)$D['daysAway'] !== 0 || empty($D['postOk'])) continue;
        if (cb_post_one($D)) $n++;
    }
    return $n;
}
// Post one celebration ($D from ar_upcoming_dates) if it hasn't been posted. Returns the post id, or 0.
function cb_post_one($D) {
    $pdo = cb_db();
    $claim = $pdo->prepare('INSERT OR IGNORE INTO celeb_posts (k, post_id, created) VALUES (?, 0, ?)'); $claim->execute([cb_key($D), date('c')]);
    if (!$claim->rowCount()) return 0; // already posted (or being posted)
    try {
        $rec = ar_get($D['agent_id']); $full = trim((string)($rec['full_name'] ?? '')) ?: $D['name'];
        $img = cb_render($D['type'], $full, (int)($D['years'] ?? 0), cb_headshot($D['email']));
        $yrs = (int)($D['years'] ?? 0);
        $body = $D['type'] === 'birthday' // Scott's wording
            ? "Happy Birthday, {$full}!\nHere's to another great year, new goals and plenty of reasons to celebrate.\nWe're so glad you're part of the FLR family. Wishing you a day full of joy!"
            : "Happy Workiversary!\n{$full} is celebrating {$yrs} year" . ($yrs === 1 ? '' : 's') . " with Florida Luxury Realty!\nThank you for your hard work, your clients, and everything you bring to our team. Here's to many more!";
        $pdo->prepare('INSERT INTO co_posts (author_email, author_key, author_name, author_staff, body, image, created) VALUES (?,?,?,?,?,?,?)')
            ->execute(['', 'staff:*', 'Florida Luxury Realty', 1, $body, $img, date('c')]);
        $pid = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE celeb_posts SET post_id = ? WHERE k = ?')->execute([$pid, cb_key($D)]);
        flr_audit('', 'celebration_post', (string)$pid, $D['type'] . ' for ' . $full);
        return $pid;
    } catch (Throwable $e) {
        $pdo->prepare('DELETE FROM celeb_posts WHERE k = ? AND post_id = 0')->execute([cb_key($D)]); // try again on a later visit
        cb_log(cb_key($D) . ': ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        return 0;
    }
}
// One-time examples Scott asked for (Nov 8, 2026): Bonnie Bowersox's workiversary and Tiffany Prete's birthday,
// posted ahead of their days. They're recorded under their real dates, so they aren't posted again on the day.
function cb_log($msg) { @file_put_contents(__DIR__ . '/logs/celebrate.log', date('c') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX); }
function cb_examples() {
    $pdo = cb_db();
    $q = $pdo->prepare("SELECT v FROM kv WHERE k = 'cb_examples_v2'"); $q->execute(); if ($q->fetchColumn()) return 0; // done
    $n = 0; $found = 0;
    foreach (ar_upcoming_dates(30) as $D) {
        $nm = strtolower($D['name'] . ' ' . (ar_get($D['agent_id'])['full_name'] ?? ''));
        if (($D['type'] === 'anniversary' && strpos($nm, 'bowersox') !== false) || ($D['type'] === 'birthday' && strpos($nm, 'prete') !== false)) { $found++; if (cb_post_one($D) || cb_post_id($D)) $n++; }
    }
    cb_log('examples: found ' . $found . ', posted ' . $n . ' (GD freetype: ' . (function_exists('imagettftext') ? 'yes' : 'no') . ')');
    if ($found && $n >= $found) $pdo->prepare("INSERT OR REPLACE INTO kv (k, v) VALUES ('cb_examples_v2', ?)")->execute([date('c')]); // otherwise try again next visit
    return $n;
}

// A local file with the agent's headshot: their Profile upload, else the directory photo (fetched once and cached).
function cb_headshot($email) {
    $email = strtolower(trim((string)$email));
    if ($email !== '') { require_once __DIR__ . '/profilelib.php'; $p = pf_path(pf_key($email), 'headshot'); if (is_file($p)) return $p; }
    $d = flr_directory();
    foreach ((array)($d['agents'] ?? []) as $x) {
        if (strtolower(trim((string)($x['email'] ?? ''))) !== $email || empty($x['photo'])) continue;
        $k = flr_dir_photo_key($x['photo']); $path = flr_dir_dir() . '/d-' . $k . '.jpg';
        if (!is_file($path)) {
            $j = flr_get_json(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=dirphoto&id=' . urlencode($x['photo']));
            if (is_array($j) && !empty($j['ok']) && !empty($j['photo']) && flr_store_photo('__dir_' . $k, $j['photo'])) @rename(flr_photo_path('__dir_' . $k), $path);
        }
        return is_file($path) ? $path : '';
    }
    return '';
}

// ---- Drawing ----
function cb_font($n) { return __DIR__ . '/fonts/' . $n; }
function cb_rgb($im, $hex, $a = 0) { $hex = ltrim($hex, '#'); return imagecolorallocatealpha($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $a); }
function cb_text($im, $size, $x, $y, $hex, $font, $txt, $align = 'left') {
    $b = imagettfbbox($size, 0, cb_font($font), $txt); $w = $b[2] - $b[0];
    if ($align === 'center') $x -= $w / 2; elseif ($align === 'right') $x -= $w;
    imagettftext($im, $size, 0, (int)$x, (int)$y, cb_rgb($im, $hex), cb_font($font), $txt);
    return $w;
}
function cb_fit($size, $font, $txt, $maxW) { while ($size > 10) { $b = imagettfbbox($size, 0, cb_font($font), $txt); if ($b[2] - $b[0] <= $maxW) break; $size -= 2; } return $size; }
// A torn-paper band: the area between a jagged line from ($x1,$y1) to ($x2,$y2) and the far side ($side: 'tl' corner or 'bottom').
function cb_torn($im, $pts, $hex) { imagefilledpolygon($im, $pts, cb_rgb($im, $hex)); }
function cb_jag($x1, $y1, $x2, $y2, $amp, $seed) {
    mt_srand($seed); $pts = [];
    $dx = $x2 - $x1; $dy = $y2 - $y1; $len = sqrt($dx * $dx + $dy * $dy); $nx = -$dy / $len; $ny = $dx / $len;
    $t = 0; $o = 0;
    while ($t < 1) { // uneven steps and depths, like torn paper
        $o = max(-$amp, min($amp, $o * 0.35 + (mt_rand(-100, 100) / 100) * $amp));
        $pts[] = $x1 + $dx * $t + $nx * $o; $pts[] = $y1 + $dy * $t + $ny * $o;
        $t += (mt_rand(35, 140) / 100) * (14 / max(1, $len / 2));
    }
    $pts[] = $x2; $pts[] = $y2;
    return $pts;
}
function cb_balloon($im, $cx, $cy, $r, $hex, $light) {
    $h = $r * 1.18;
    imageline($im, (int)$cx, (int)($cy + $h), (int)($cx + $r * 0.15), (int)($cy + $h + $r * 1.9), cb_rgb($im, '#b9c0c3'));
    imagesetthickness($im, 1);
    imagefilledpolygon($im, [$cx - $r * .12, $cy + $h + $r * .16, $cx + $r * .12, $cy + $h + $r * .16, $cx, $cy + $h - $r * .05], cb_rgb($im, $hex));
    imagefilledellipse($im, (int)$cx, (int)$cy, (int)($r * 2), (int)($h * 2), cb_rgb($im, $hex));
    imagefilledellipse($im, (int)($cx + $r * .12), (int)($cy + $h * .12), (int)($r * 1.7), (int)($h * 1.7), cb_rgb($im, $light, 70));
    imagefilledellipse($im, (int)($cx - $r * .38), (int)($cy - $h * .42), (int)($r * .5), (int)($h * .62), cb_rgb($im, '#ffffff', 45));
}
function cb_ribbon($im, $pts, $hex, $w) {
    imagesetthickness($im, $w); $c = cb_rgb($im, $hex);
    for ($i = 0; $i + 3 < count($pts); $i += 2) imageline($im, (int)$pts[$i], (int)$pts[$i + 1], (int)$pts[$i + 2], (int)$pts[$i + 3], $c);
    for ($i = 0; $i + 1 < count($pts); $i += 2) imagefilledellipse($im, (int)$pts[$i], (int)$pts[$i + 1], $w, $w, $c);
    imagesetthickness($im, 1);
}
function cb_curve($x0, $y0, $pts, $steps = 40) { // smooth path through control points (Catmull-Rom)
    $P = array_merge([[$x0, $y0]], $pts); $out = [];
    for ($i = 0; $i < count($P) - 1; $i++) {
        $p0 = $P[max(0, $i - 1)]; $p1 = $P[$i]; $p2 = $P[$i + 1]; $p3 = $P[min(count($P) - 1, $i + 2)];
        for ($s = 0; $s < $steps; $s++) { $t = $s / $steps; $t2 = $t * $t; $t3 = $t2 * $t;
            foreach ([0, 1] as $k) $out[] = 0.5 * ((2 * $p1[$k]) + (-$p0[$k] + $p2[$k]) * $t + (2 * $p0[$k] - 5 * $p1[$k] + 4 * $p2[$k] - $p3[$k]) * $t2 + (-$p0[$k] + 3 * $p1[$k] - 3 * $p2[$k] + $p3[$k]) * $t3); }
    }
    $last = end($P); $out[] = $last[0]; $out[] = $last[1];
    return $out;
}
function cb_burst($im, $cx, $cy, $r, $hexA, $hexB, $w) {
    imagesetthickness($im, $w);
    for ($i = 0; $i < 16; $i++) { $a = $i * M_PI / 8; $c = cb_rgb($im, $i % 2 ? $hexA : $hexB); $r1 = $r * .32; $r2 = $r * ($i % 2 ? .78 : .95);
        imageline($im, (int)($cx + cos($a) * $r1), (int)($cy + sin($a) * $r1), (int)($cx + cos($a) * $r2), (int)($cy + sin($a) * $r2), $c);
        imagefilledellipse($im, (int)($cx + cos($a) * ($r2 + $w * 1.6)), (int)($cy + sin($a) * ($r2 + $w * 1.6)), (int)($w * 1.7), (int)($w * 1.7), $c); }
    imagesetthickness($im, 1);
}
function cb_sparkle($im, $cx, $cy, $r, $hex) { $c = cb_rgb($im, $hex); imagefilledpolygon($im, [$cx, $cy - $r, $cx + $r * .22, $cy - $r * .22, $cx + $r, $cy, $cx + $r * .22, $cy + $r * .22, $cx, $cy + $r, $cx - $r * .22, $cy + $r * .22, $cx - $r, $cy, $cx - $r * .22, $cy - $r * .22], $c); }
// The headshot as a circle with a soft paper-colored ring.
function cb_photo($im, $cx, $cy, $r, $path, $initials) {
    imagefilledellipse($im, (int)$cx + 6, (int)$cy + 10, (int)($r * 2 + 64), (int)($r * 2 + 64), cb_rgb($im, '#000000', 118));
    imagefilledellipse($im, (int)$cx, (int)$cy, (int)($r * 2 + 60), (int)($r * 2 + 60), cb_rgb($im, '#ecebe7'));
    $d = (int)($r * 2); $src = $path !== '' ? @imagecreatefromstring((string)@file_get_contents($path)) : null;
    if (!$src) { imagefilledellipse($im, (int)$cx, (int)$cy, $d, $d, cb_rgb($im, '#036f72')); cb_text($im, $r * .55, $cx, $cy + $r * .2, '#ffffff', 'YoungSerif-Regular.ttf', $initials, 'center'); return; }
    $sw = imagesx($src); $sh = imagesy($src); $s = min($sw, $sh);
    $sq = imagecreatetruecolor($d, $d); imagecopyresampled($sq, $src, 0, 0, (int)(($sw - $s) / 2), (int)max(0, ($sh - $s) / 2 - $s * .05), $d, $d, $s, $s);
    $r2 = ($d / 2) * ($d / 2); $x0 = (int)($cx - $d / 2); $y0 = (int)($cy - $d / 2);
    for ($y = 0; $y < $d; $y++) { $dy = $y - $d / 2 + .5; $span = $r2 - $dy * $dy; if ($span <= 0) continue; $hw = (int)sqrt($span);
        imagecopy($im, $sq, $x0 + (int)($d / 2) - $hw, $y0 + $y, (int)($d / 2) - $hw, $y, $hw * 2, 1); }
}
function cb_logo($im, $right, $bottom, $w) {
    $lg = @imagecreatefrompng(__DIR__ . '/logo.png'); if (!$lg) return;
    $h = (int)($w * imagesy($lg) / imagesx($lg)); imagealphablending($im, true);
    imagecopyresampled($im, $lg, (int)($right - $w), (int)($bottom - $h), 0, 0, (int)$w, $h, imagesx($lg), imagesy($lg));
}
// Render the graphic; returns the saved file name in the Collaboration image folder.
function cb_render($type, $fullName, $years, $headPath) {
    $S = 2; $W = 1080; $H = $type === 'birthday' ? 1080 : 1350; // drawn at 2x, then scaled down
    $im = imagecreatetruecolor($W * $S, $H * $S); imagealphablending($im, true); imagefill($im, 0, 0, cb_rgb($im, '#ffffff'));
    $X = function ($v) use ($S) { return $v * $S; };
    $ini = ''; foreach (preg_split('/\s+/', $fullName) as $w) if ($w !== '' && strlen($ini) < 2) $ini .= strtoupper($w[0]);
    // Torn paper: top-left corner (teal, then white, then light gray) and along the bottom.
    cb_torn($im, array_merge([0, 0], cb_jag(0, $X(330), $X(420), 0, $X(9), 7), [0, 0]), '#d9dcdd');
    cb_torn($im, array_merge([0, 0], cb_jag(0, $X(250), $X(345), 0, $X(9), 11), [0, 0]), '#f4f4f2');
    cb_torn($im, array_merge([0, 0], cb_jag(0, $X(205), $X(300), 0, $X(10), 3)), '#0d5c5e');
    $bt = array_merge([0, $X($H)], cb_jag(0, $X($H - 22), $X($W * .62), $X($H - 4), $X(7), 5), [$X($W * .62), $X($H)]); cb_torn($im, $bt, '#0d5c5e');
    $br = array_merge([$X($W * .5), $X($H)], cb_jag($X($W * .5), $X($H - 6), $X($W), $X($H - 90), $X(8), 9), [$X($W), $X($H)]); cb_torn($im, $br, '#dfe2e3');
    $br2 = array_merge([$X($W * .66), $X($H)], cb_jag($X($W * .66), $X($H - 2), $X($W), $X($H - 48), $X(7), 13), [$X($W), $X($H)]); cb_torn($im, $br2, '#f4f4f2');
    if ($type === 'birthday') {
        cb_ribbon($im, cb_curve($X(470), 0, [[$X(560), $X(60)], [$X(700), $X(150)], [$X(760), $X(240)], [$X(860), $X(250)], [$X(940), $X(170)]]), '#1c6f72', $X(13));
        cb_ribbon($im, cb_curve($X(770), 0, [[$X(860), $X(50)], [$X(990), $X(90)]]), '#e8b923', $X(10));
        foreach ([[840, 180, 62, '#5fa3a6', '#8cc3c5'], [985, 190, 92, '#a9b1b5', '#d3d8da'], [1030, 70, 50, '#efb92b', '#f7d669']] as $b) cb_balloon($im, $X($b[0]), $X($b[1]), $X($b[2]), $b[3], $b[4]);
        foreach ([[40, 900, 64, '#3f9195', '#7fc0c2'], [190, 860, 66, '#3f9195', '#7fc0c2'], [330, 885, 70, '#efb92b', '#f7d669'], [470, 870, 64, '#3f9195', '#7fc0c2'], [10, 1000, 60, '#efb92b', '#f7d669'], [150, 975, 84, '#a9b1b5', '#d3d8da'], [410, 1000, 78, '#4f9da0', '#8cc3c5'], [540, 945, 68, '#a9b1b5', '#d3d8da'], [590, 1030, 58, '#4f9da0', '#8cc3c5']] as $b) cb_balloon($im, $X($b[0]), $X($b[1]), $X($b[2]), $b[3], $b[4]);
        cb_text($im, $X(46), $X(96), $X(305), '#2f3436', 'Gloock-Regular.ttf', 'HAPPY');
        cb_text($im, cb_fit($X(112), 'NothingYouCouldDo-Regular.ttf', 'Birthday', $X(500)), $X(84), $X(420), '#0d5c5e', 'NothingYouCouldDo-Regular.ttf', 'Birthday');
        foreach (['Wishing you a year full of', 'happiness, health and', 'success.'] as $i => $l) cb_text($im, $X(23), $X(96), $X(500 + $i * 38), '#3b4244', 'LibreBaskerville-Regular.ttf', $l);
        $parts = preg_split('/\s+/', trim($fullName), 2);
        $ns = cb_fit($X(44), 'YoungSerif-Regular.ttf', strlen($parts[0]) >= strlen($parts[1] ?? '') ? $parts[0] : $parts[1], $X(400));
        cb_text($im, $ns, $X(142), $X(668), '#0d5c5e', 'YoungSerif-Regular.ttf', $parts[0]);
        if (!empty($parts[1])) cb_text($im, $ns, $X(142), $X(668) + $ns * 1.25, '#0d5c5e', 'YoungSerif-Regular.ttf', $parts[1]);
        cb_photo($im, $X(812), $X(575), $X(200), $headPath, $ini);
        cb_logo($im, $X(1050), $X(950), $X(410));
    } else {
        cb_ribbon($im, cb_curve($X(470), 0, [[$X(520), $X(60)], [$X(660), $X(80)], [$X(760), $X(180)], [$X(720), $X(270)], [$X(820), $X(240)]]), '#1c6f72', $X(11));
        cb_ribbon($im, cb_curve($X(790), 0, [[$X(900), $X(60)], [$X(1000), $X(150)], [$X(1080), $X(190)]]), '#e8b923', $X(9));
        cb_burst($im, $X(860), $X(190), $X(130), '#0d5c5e', '#e8b923', $X(6)); cb_burst($im, $X(985), $X(330), $X(90), '#e8b923', '#0d5c5e', $X(5));
        foreach ([[1045, 60, 16, '#0d5c5e'], [720, 120, 18, '#e8b923'], [1020, 430, 12, '#0d5c5e']] as $s) cb_sparkle($im, $X($s[0]), $X($s[1]), $X($s[2]), $s[3]);
        cb_text($im, $X(46), $X(96), $X(262), '#2f3436', 'Gloock-Regular.ttf', 'HAPPY');
        cb_text($im, cb_fit($X(104), 'NothingYouCouldDo-Regular.ttf', 'Workiversary', $X(700)), $X(90), $X(395), '#0d5c5e', 'NothingYouCouldDo-Regular.ttf', 'Workiversary');
        cb_photo($im, $X(540), $X(590), $X(135), $headPath, $ini);
        $ns = cb_fit($X(46), 'YoungSerif-Regular.ttf', $fullName, $X(900)); cb_text($im, $ns, $X(540), $X(815), '#0d5c5e', 'YoungSerif-Regular.ttf', $fullName, 'center');
        $yt = $years . ' YEAR' . ($years === 1 ? '' : 'S');
        cb_text($im, $X(92), $X(543), $X(938), '#c98f0e', 'BigShoulders-Bold.ttf', $yt, 'center'); cb_text($im, $X(92), $X(540), $X(934), '#f0b323', 'BigShoulders-Bold.ttf', $yt, 'center');
        // Champagne glasses
        foreach ([[-1, 205], [1, 245]] as list($dir, $x)) { $t = $dir * 0.24; $c = cos($t); $s = sin($t); $cx = $X($x); $cy = $X(1130);
            $P = function ($px, $py) use ($c, $s, $cx, $cy, $X) { return [$cx + $X($px) * $c - $X($py) * $s, $cy + $X($px) * $s + $X($py) * $c]; };
            $poly = array_merge($P(-40, -115), $P(40, -115), $P(26, 40), $P(0, 56), $P(-26, 40)); imagefilledpolygon($im, $poly, cb_rgb($im, '#9a9fa2'));
            $poly = array_merge($P(-33, -104), $P(33, -104), $P(20, 34), $P(0, 46), $P(-20, 34)); imagefilledpolygon($im, $poly, cb_rgb($im, '#f2c14e'));
            foreach ([[-10, -60], [8, -30], [-4, 0], [12, 20]] as $bb) { list($bx, $by) = $P($bb[0], $bb[1]); imagefilledellipse($im, (int)$bx, (int)$by, $X(9), $X(9), cb_rgb($im, '#fff3c4')); }
            imagesetthickness($im, $X(7)); list($ax, $ay) = $P(0, 54); list($bx, $by) = $P($dir * -46, 170); imageline($im, (int)$ax, (int)$ay, (int)$bx, (int)$by, cb_rgb($im, '#9a9fa2')); imagesetthickness($im, 1); }
        imagefilledellipse($im, $X(228), $X(1300), $X(90), $X(18), cb_rgb($im, '#7d8386'));
        foreach ([[115, 1020, 16, '#e8b923'], [235, 990, 20, '#e8b923'], [340, 1030, 12, '#8fb9bb']] as $s) cb_sparkle($im, $X($s[0]), $X($s[1]), $X($s[2]), $s[3]);
        foreach ([[645, 1015, 7, '#7d8386'], [890, 1035, 6, '#e8b923'], [950, 1015, 6, '#e8b923'], [800, 1060, 5, '#8fb9bb']] as $d) imagefilledellipse($im, $X($d[0]), $X($d[1]), $X($d[2] * 2), $X($d[2] * 2), cb_rgb($im, $d[3]));
        cb_logo($im, $X(1050), $X(1245), $X(600));
    }
    $out = imagecreatetruecolor($W, $H); imagecopyresampled($out, $im, 0, 0, 0, 0, $W, $H, $W * $S, $H * $S);
    $name = bin2hex(random_bytes(12)) . '.jpg'; // the same naming as other Lounge photos (collab_img.php)
    if (!imagejpeg($out, co_img_dir() . '/' . $name, 90)) throw new RuntimeException('Could not save the celebration graphic.');
    return $name;
}
