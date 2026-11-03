<?php
require __DIR__ . "/guard.php";
require __DIR__ . "/events.php";
$full = trim($_SESSION['name'] ?? '');
$first = $full !== '' ? preg_split('/\s+/', $full)[0] : '';
$pic = $_SESSION['pic'] ?? '';
$ini = '';
foreach (array_slice(preg_split('/\s+/', $full), 0, 2) as $w) { if ($w !== '') $ini .= mb_strtoupper(mb_substr($w, 0, 1)); }
if ($ini === '') $ini = 'FLR';
// Next upcoming event (Eastern time). Today's event stays until its end time has passed.
$tz = new DateTimeZone('America/New_York');
$now = new DateTime('now', $tz);
$next = null;
foreach ($FLR_EVENTS as $ev) {
    $end = DateTime::createFromFormat('Y-m-d g:i A', $ev['date'] . ' ' . ($ev['end'] ?: '11:59 PM'), $tz);
    if ($end && $end >= $now) { $next = $ev; $dt = new DateTime($ev['date'], $tz); break; }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Home - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=9">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php" aria-current="page">Home</a>
    <a href="my-transactions.php">My Transactions</a>
    <a href="forms.php">Forms</a>
    <a href="training.php">Training</a>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>

<section class="hero welcome">
  <div class="who">
    <?php if ($pic): ?><img class="av" src="<?= htmlspecialchars($pic === 'photo.php' ? 'photo.php?v=' . (int)@filemtime(flr_photo_path($_SESSION['email'] ?? '')) : $pic) ?>" alt="" referrerpolicy="no-referrer" onerror="this.outerHTML='<span class=&quot;av&quot;><?= htmlspecialchars($ini) ?></span>'"><?php else: ?><span class="av"><?= htmlspecialchars($ini) ?></span><?php endif; ?>
    <div>
      <h1>Welcome<?= $first !== '' ? ', ' . htmlspecialchars($first) : '' ?></h1>
      <p>Your FLR back office. Everything you need to run your business, in one place.</p>
    </div>
  </div>
<?php if ($next): ?>
  <div class="nextup">
    <div class="tile"><b><?= strtoupper($dt->format('M')) ?></b><span><?= $dt->format('j') ?></span></div>
    <div class="nb">
      <span class="tag">Next up &middot; <?= htmlspecialchars($next['type']) ?></span>
      <h2><?= htmlspecialchars($next['title']) ?></h2>
      <div class="meta"><?= htmlspecialchars($dt->format('l')) ?><?= $next['start'] ? ' &middot; ' . htmlspecialchars($next['start'] . ' – ' . $next['end']) : '' ?><?= $next['where'] ? ' &middot; ' . htmlspecialchars($next['where']) : '' ?></div>
      <div class="row">
        <?php if ($next['zoom']): ?><a class="btn" target="_blank" rel="noopener" href="<?= htmlspecialchars($next['zoom']) ?>">Join on Zoom</a><?php endif; ?>
        <?php if ($next['rsvp']): ?><a class="btn" target="_blank" rel="noopener" href="<?= htmlspecialchars($next['rsvp']) ?>">RSVP</a><?php endif; ?>
        <a class="btn ghost" href="training.php">See all training</a>
      </div>
    </div>
  </div>
<?php endif; ?>
</section>

<main class="wrap">
  <div class="grid">
    <a class="card" href="my-transactions.php">
      <h2>My Transactions</h2>
      <p>Your active files and documents, organized by Buyers, Sellers and status.</p>
    </a>
    <a class="card" href="forms.php">
      <h2>Forms</h2>
      <p>Current Buyer, Seller, Agent and Referral contract forms. Download, then fill in.</p>
    </a>
    <a class="card" href="training.php">
      <h2>Training</h2>
      <p>Round Table schedule, Zoom links and special events.</p>
    </a>
    <div class="card soon">
      <h2>Contacts and Directory</h2>
      <p>Office staff and key contacts.</p>
      <span class="tag">Coming soon</span>
    </div>
    <div class="card soon">
      <h2>Support</h2>
      <p>How to reach the office.</p>
      <span class="tag">Coming soon</span>
    </div>
  </div>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only. Having trouble signing in? Contact the office.</footer>
</body>
</html>
