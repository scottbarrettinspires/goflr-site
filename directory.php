<?php
require __DIR__ . "/guard.php";
$d = flr_directory();
function dir_phone_digits($p) { $x = preg_replace('/[^0-9+]/', '', (string)$p); return $x; }
function dir_phone_fmt($p) { $x = preg_replace('/\D/', '', (string)$p); if (strlen($x) === 11 && $x[0] === '1') $x = substr($x, 1); return strlen($x) === 10 ? substr($x, 0, 3) . '-' . substr($x, 3, 3) . '-' . substr($x, 6) : trim((string)$p); }
function dir_card($p, $sub) {
    $name = htmlspecialchars($p['name']); $ph = trim((string)($p['phone'] ?? '')); $em = trim((string)($p['email'] ?? ''));
    $ini = ''; foreach (preg_split('/\s+/', trim($p['name'])) as $w) { if ($w !== '' && strlen($ini) < 2) $ini .= mb_strtoupper(mb_substr($w, 0, 1)); }
    $img = !empty($p['photo']) ? '<img src="dirphoto.php?k=' . flr_dir_photo_key($p['photo']) . '" alt="" loading="lazy" onerror="this.remove()">' : '';
    $h = '<div class="dcard" data-s="' . htmlspecialchars(strtolower($p['name'] . ' ' . $sub . ' ' . $em . ' ' . $ph)) . '"><div class="dav"><span>' . htmlspecialchars($ini) . '</span>' . $img . '</div><div class="dtx"><div class="dnm">' . $name . '</div>';
    if ($sub !== '') $h .= '<div class="dsub">' . htmlspecialchars($sub) . '</div>';
    if ($ph !== '') $h .= '<div><a href="tel:' . htmlspecialchars(dir_phone_digits($ph)) . '">' . htmlspecialchars(dir_phone_fmt($ph)) . '</a></div>';
    if ($em !== '') $h .= '<div><a href="mailto:' . htmlspecialchars($em) . '">' . htmlspecialchars($em) . '</a></div>';
    return $h . '</div></div>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Directory - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=16">
<style>
.dtools{margin:0 0 18px}.dtools input{width:100%;max-width:420px;padding:11px 14px;border:1px solid var(--line);border-radius:10px;font:inherit}
.dgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin:0 0 32px}
.dcard{display:flex;gap:14px;align-items:center;background:var(--white);border:1px solid var(--line);border-radius:14px;padding:14px}
.dav{position:relative;flex:0 0 76px;width:76px;height:76px;border-radius:50%;overflow:hidden;background:var(--teal-soft);display:flex;align-items:center;justify-content:center;color:var(--teal);font-weight:700;font-size:1.4rem}
.dav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.dtx{min-width:0;font-size:.95rem;line-height:1.45}.dtx a{color:var(--teal);text-decoration:none;overflow-wrap:anywhere}.dtx a:hover{text-decoration:underline}
.dnm{font-weight:700;font-size:1.05rem}.dsub{color:#5a6a6c;font-size:.85rem}
.dsec h2{margin:8px 0 12px}.dsec h2 span{font-size:.8rem;color:#5a6a6c;font-weight:500;margin-left:6px}
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="my-transactions.php">My Transactions</a>
    <a href="forms.php">Forms</a>
    <a href="signed.php">Signed</a>
    <a href="training.php">Training</a>
    <a href="directory.php" aria-current="page">Directory</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1>Directory</h1><p>Our staff and active agents. Tap a phone number to call or an email address to write.</p></section>
<main class="wrap">
<?php if (!$d): ?>
  <p class="note">The directory could not be loaded right now. Please try again in a minute.</p>
<?php else: ?>
  <div class="dtools"><input type="search" id="q" placeholder="Search by name, phone or email" autocomplete="off"></div>
  <section class="dsec"><h2>Staff <span><?= count($d['staff'] ?? []) ?></span></h2><div class="dgrid">
<?php foreach ((array)($d['staff'] ?? []) as $p) echo dir_card($p, (string)($p['position'] ?? '')), "\n"; ?>
  </div></section>
  <section class="dsec"><h2>Active agents <span><?= count($d['agents'] ?? []) ?></span></h2><div class="dgrid">
<?php foreach ((array)($d['agents'] ?? []) as $p) echo dir_card($p, ''), "\n"; ?>
  </div></section>
  <p class="note" id="none" hidden>No one matches that search.</p>
<?php if (!empty($d['note'])): ?><p class="note"><?= htmlspecialchars($d['note']) ?></p><?php endif; ?>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
<script>
(function(){var q=document.getElementById("q");if(!q)return;q.addEventListener("input",function(){var v=q.value.trim().toLowerCase(),n=0;
[].forEach.call(document.querySelectorAll(".dcard"),function(c){var m=!v||c.dataset.s.indexOf(v)>-1;c.style.display=m?"":"none";if(m)n++;});
[].forEach.call(document.querySelectorAll(".dsec"),function(s){s.style.display=s.querySelector(".dcard:not([style*='none'])")?"":"none";});
document.getElementById("none").hidden=n>0;});})();
</script>
</body>
</html>
