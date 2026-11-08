<?php
// Prospecting (agents): the ways an agent generates leads -- Social Media, Open Houses, Networking, Sphere, Farming,
// Referrals, Online Leads, Calls & Lists. Same look as Leads & Contacts: tabs across the top, stat cards, then lists.
// Each method shows the leads it produced (from each lead's source tags in Leads & Contacts), lets the agent log
// prospecting activity and set a weekly goal, and keeps a short list of ideas. Activity is stored per agent on the server.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/leadlib.php";
date_default_timezone_set('America/New_York');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
$isStaff = flr_is_broker();

// key => [label, color, lead source tags that count for it, quick activities, ideas]
const PR_METHODS = [
    'social'   => ['Social Media', '#7a4fc9', ['Social media', 'Facebook', 'Instagram'], ['Posted', 'Went live', 'Posted a reel', 'Ran an ad', 'Replied to comments/DMs'],
                   ['Post a listing, a sold, or a local tip at least 3 times a week.', 'Answer every comment and DM the same day; that is where leads start.', 'Go live from a listing or a local business once a week.']],
    'openhouse'=> ['Open Houses', '#e07b39', ['Open house'], ['Held an open house', 'Invited neighbors', 'Followed up with visitors'],
                   ['Knock on 20 neighbor doors before the open house and invite them.', 'Sign every visitor in, then follow up within 24 hours.', 'Post it on Home with + Post so other agents can send buyers.']],
    'network'  => ['Networking', '#1f7a4d', ['Networking', 'Community event', 'Hosting seminar'], ['Attended an event', 'Coffee or lunch meeting', 'Hosted a seminar', 'Joined a group'],
                   ['Join one group you enjoy (chamber, BNI, club) and show up every time.', 'Book one coffee a week with a local business owner.', 'Host a first-time buyer or seller seminar each quarter.']],
    'sphere'   => ['Sphere', '#036f72', ['Sphere / friend or family', 'Past client'], ['Called', 'Texted', 'Pop-by', 'Handwritten note', 'Client event'],
                   ['Touch your A list every 30 days and B list every 60 days.', 'Do a pop-by with a small gift around a holiday.', 'Ask past clients: "Who do you know thinking of moving this year?"']],
    'farming'  => ['Farming', '#b7791f', ['Door knocking', 'Direct mail'], ['Mailed postcards', 'Door knocked', 'Dropped flyers', 'Neighborhood update'],
                   ['Pick one neighborhood of 300-500 homes and stay with it 12 months.', 'Mail the same farm every month: just sold, just listed, market update.', 'Door knock after every listing or sale in the farm.']],
    'referral' => ['Referrals', '#c0392b', ['Referral', 'Agent referral'], ['Asked for a referral', 'Sent a thank-you', 'Referred out', 'Agent-to-agent call'],
                   ['Ask every happy client for an introduction at closing.', 'Thank every referral the same day, win or lose.', 'Build agent-to-agent referral partners in other markets.']],
    'online'   => ['Online Leads', '#1f5fa8', ['Website', 'Landing page', 'Agent App', 'Zillow', 'Realtor.com', 'Email campaign'], ['Responded to a lead', 'Updated my landing page', 'Sent an email campaign', 'Shared my lead link'],
                   ['Call every online lead within 5 minutes.', 'Put your lead link (Leads & Contacts) in your bio and email signature.', 'Send a monthly email to your database.']],
    'calls'    => ['Calls & Lists', '#455a64', ['FSBO', 'Expired', 'Sign call', 'Cold calling', 'Pre-foreclosure', 'Probate'], ['Calls made', 'Conversations', 'Appointments set'],
                   ['Block one hour a day for calls before anything else.', 'Call new expireds and FSBOs the day they show up.', 'Track dials, conversations and appointments.']],
];

$pdo = flr_db();
$pdo->exec('CREATE TABLE IF NOT EXISTS pr_log (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, method TEXT NOT NULL, d TEXT NOT NULL, what TEXT, qty INTEGER DEFAULT 1, notes TEXT, created TEXT)');
$pdo->exec('CREATE INDEX IF NOT EXISTS pr_log_email ON pr_log(email, d)');
$pdo->exec('CREATE TABLE IF NOT EXISTS pr_goals (email TEXT NOT NULL, method TEXT NOT NULL, per_week INTEGER, PRIMARY KEY (email, method))');

$m = isset(PR_METHODS[$_GET['m'] ?? '']) ? $_GET['m'] : 'overview';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pm = (string)($_POST['m'] ?? '');
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? '')) || !isset(PR_METHODS[$pm])) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $a = (string)($_POST['a'] ?? '');
        if ($a === 'log') {
            $d = (string)($_POST['d'] ?? date('Y-m-d')); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d > date('Y-m-d')) $d = date('Y-m-d');
            $what = trim(mb_substr((string)($_POST['what'] ?? ''), 0, 80)); $qty = max(1, min(999, (int)($_POST['qty'] ?? 1))); $notes = trim(mb_substr((string)($_POST['notes'] ?? ''), 0, 300));
            if ($what === '') $err = 'Choose or type what you did.';
            else $pdo->prepare('INSERT INTO pr_log (email, method, d, what, qty, notes, created) VALUES (?,?,?,?,?,?,?)')->execute([$me, $pm, $d, $what, $qty, $notes, date('c')]);
        } elseif ($a === 'del') {
            $pdo->prepare('DELETE FROM pr_log WHERE id = ? AND email = ?')->execute([(int)($_POST['id'] ?? 0), $me]);
        } elseif ($a === 'goal') {
            $g = max(0, min(999, (int)($_POST['goal'] ?? 0)));
            $pdo->prepare('INSERT INTO pr_goals (email, method, per_week) VALUES (?,?,?) ON CONFLICT(email, method) DO UPDATE SET per_week = excluded.per_week')->execute([$me, $pm, $g]);
        }
        if ($err === '') { header('Location: prospecting.php?m=' . urlencode($pm) . '&ok=' . urlencode($a)); exit; }
        $m = $pm;
    }
}

// Leads by method (a lead counts for every method one of its source tags belongs to).
$today = date('Y-m-d'); $d30 = date('Y-m-d', strtotime('-30 days')); $wk = date('Y-m-d', strtotime('monday this week'));
$leads = []; try { $leads = (array)(ld_load($me)['leads'] ?? []); } catch (Throwable $e) {}
$tagMethod = []; foreach (PR_METHODS as $k => $x) foreach ($x[2] as $t) $tagMethod[strtolower($t)] = $k;
$byM = array_fill_keys(array_keys(PR_METHODS), []); $untagged = 0;
foreach ($leads as $L) {
    $tags = ld_src_names(isset($L['tags']) ? (array)$L['tags'] : ld_auto_tags($L['source'] ?? ''));
    $hit = []; foreach ($tags as $t) { $k = $tagMethod[strtolower($t)] ?? ''; if ($k !== '') $hit[$k] = true; }
    if (!$hit) $untagged++;
    foreach (array_keys($hit) as $k) $byM[$k][] = $L;
}
foreach ($byM as &$list) usort($list, function ($a, $b) { return strcmp((string)($b['added'] ?? ''), (string)($a['added'] ?? '')); }); unset($list);
$new30 = function ($list) use ($d30) { return count(array_filter($list, function ($L) use ($d30) { return (string)($L['added'] ?? '') >= $d30; })); };
// Activity
$q = $pdo->prepare('SELECT method, SUM(qty) FROM pr_log WHERE email = ? AND d >= ? GROUP BY method'); $q->execute([$me, $wk]); $actWk = $q->fetchAll(PDO::FETCH_KEY_PAIR);
$q = $pdo->prepare('SELECT method, SUM(qty) FROM pr_log WHERE email = ? AND d >= ? GROUP BY method'); $q->execute([$me, $d30]); $act30 = $q->fetchAll(PDO::FETCH_KEY_PAIR);
$q = $pdo->prepare('SELECT method, per_week FROM pr_goals WHERE email = ?'); $q->execute([$me]); $goals = $q->fetchAll(PDO::FETCH_KEY_PAIR);
$q = $pdo->prepare('SELECT * FROM pr_log WHERE email = ?' . ($m !== 'overview' ? ' AND method = ?' : '') . ' ORDER BY d DESC, id DESC LIMIT 25'); $q->execute($m !== 'overview' ? [$me, $m] : [$me]); $recent = $q->fetchAll();
$totWk = array_sum(array_map('intval', $actWk)); $totLeads30 = count(array_filter($leads, function ($L) use ($d30) { return (string)($L['added'] ?? '') >= $d30; }));
$h = function ($x) { return htmlspecialchars((string)$x); };
$csrfIn = '<input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '">';
$nm = function ($L) { return trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? '')) ?: (($L['phone'] ?? '') ?: (($L['email'] ?? '') ?: 'Lead')); };
$when = function ($d) use ($today) { if ($d === $today) return 'Today'; if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday'; $t = strtotime($d); return $t ? date('M j', $t) : $d; };
?>
<!doctype html>
<html lang="en">
<head>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#036f72">
<link rel="icon" href="/icon-192.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="FLR Office">
<script>if("serviceWorker" in navigator){navigator.serviceWorker.register("/sw.js").catch(function(){});}</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Prospecting - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=16">
<style>
.ltabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px}
.ltabs a{font-weight:700;padding:8px 13px;font-size:.88rem;border-radius:10px;border:1px solid var(--line);background:var(--white);color:var(--teal);text-decoration:none;display:flex;gap:8px;align-items:center}
.ltabs a.on{background:var(--teal);color:#fff;border-color:var(--teal)}.ltabs a .n{font-size:.72rem;font-weight:700;background:rgba(0,0,0,.08);border-radius:99px;padding:1px 7px}.ltabs a.on .n{background:rgba(255,255,255,.25)}
.ltabs a .sw{width:9px;height:9px;border-radius:50%}.ltabs a.ov{color:#5a6a6c;font-weight:600}.ltabs a.ov.on{color:#fff}
.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin:0 0 18px}
.stat{display:block;background:var(--white);border:1px solid var(--line);border-radius:12px;padding:12px 14px;color:inherit;text-decoration:none}.stat b{display:block;font-size:1.6rem;line-height:1.1}.stat span{font-size:.82rem;color:#5a6a6c}
a.stat:hover{border-color:var(--teal)}.stat.big{background:var(--teal);border-color:var(--teal);color:#fff}.stat.big span{color:#fff;font-weight:600}.stat.big b{font-size:2rem}
.stat .bar{height:6px;border-radius:99px;background:#eef3f3;margin-top:8px;overflow:hidden}.stat .bar i{display:block;height:100%;border-radius:99px}
.lpanel{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:16px;margin:0 0 14px}
.lpanel h2{margin:0 0 12px;font-size:1.15rem;display:flex;align-items:center;gap:8px}
.lrow{display:flex;gap:12px;align-items:center;padding:11px 12px;border:1px solid var(--line);border-radius:12px;margin:0 0 8px;background:var(--white);color:inherit;text-decoration:none}
a.lrow:hover{border-color:var(--teal)}.lrow .who{flex:1;min-width:0}.lrow .nm{display:block;font-weight:700}.lrow .meta{display:block;font-size:.82rem;color:#5a6a6c;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dot{width:12px;height:12px;border-radius:50%;flex:0 0 12px}
.due{font-size:.74rem;font-weight:700;padding:3px 9px;border-radius:99px;white-space:nowrap;background:#eef3f3;color:#4b5b5d}
.empty{color:#6a777a;padding:14px;text-align:center;margin:0}
.pgrid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:14px;align-items:start}@media(max-width:900px){.pgrid{grid-template-columns:1fr}}
.qk{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 10px}.qk button{font:inherit;font-size:.82rem;font-weight:600;padding:6px 11px;border-radius:99px;border:1px solid var(--line);background:#fff;color:var(--teal);cursor:pointer}.qk button.on{background:var(--teal);color:#fff;border-color:var(--teal)}
.lgf{display:grid;grid-template-columns:1fr 90px 150px;gap:8px}.lgf input,.lgf textarea{font:inherit;padding:8px 10px;border:1px solid var(--line);border-radius:9px;box-sizing:border-box;width:100%}
.lgf textarea{grid-column:1/-1;resize:vertical;min-height:40px}@media(max-width:560px){.lgf{grid-template-columns:1fr 80px}.lgf .dt{grid-column:1/-1}}
.lgf label{font-size:.78rem;color:#5a6a6c;display:block}
.btn.sm{padding:7px 14px;font-size:.88rem;border:0;cursor:pointer;font-weight:600}
.ideas{margin:0;padding-left:18px;color:#2a3638;line-height:1.55;font-size:.92rem}.ideas li{margin:0 0 6px}
.srcbar{display:flex;align-items:center;gap:10px;margin:0 0 9px;font-size:.9rem}.srcbar .l{flex:0 0 120px;font-weight:600}.srcbar .t{flex:1;height:10px;background:#eef3f3;border-radius:99px;overflow:hidden}.srcbar .t i{display:block;height:100%}.srcbar .v{flex:0 0 40px;text-align:right;color:#5a6a6c}
.lgdel{background:none;border:0;color:#9aa7a9;cursor:pointer;font-size:1.1rem;padding:0 4px}.lgdel:hover{color:#c0392b}
.goalf{display:flex;gap:8px;align-items:center;font-size:.88rem;color:#5a6a6c;flex-wrap:wrap}.goalf input{width:70px;font:inherit;padding:6px 8px;border:1px solid var(--line);border-radius:8px}
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Virtual Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php" aria-current="page">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php">Active Business</a>
    <a href="performance.php">Performance</a>
    <?php endif; ?>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a><?php endif; ?>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>

<section class="hero">
  <h1>Prospecting</h1>
  <p>It's time to capture some new business opportunities to serve.</p>
</section>

<main class="wrap">
<?php if ($err): ?>  <p class="sigerr"><?= $h($err) ?></p>
<?php elseif (!empty($_GET['ok'])): ?>  <p class="note"><?= $h(['log' => 'Activity logged. Nice work!', 'del' => 'Activity removed.', 'goal' => 'Weekly goal saved.'][$_GET['ok']] ?? 'Saved.') ?></p>
<?php endif; ?>
  <div class="ltabs">
    <a href="prospecting.php" class="ov<?= $m === 'overview' ? ' on' : '' ?>">Overview</a>
<?php foreach (PR_METHODS as $k => $x): ?>
    <a href="prospecting.php?m=<?= $k ?>" class="<?= $m === $k ? 'on' : '' ?>"><span class="sw" style="background:<?= $x[1] ?>"></span><?= $h($x[0]) ?> <span class="n"><?= count($byM[$k]) ?></span></a>
<?php endforeach; ?>
  </div>

<?php if ($m === 'overview'):
    $maxL = max(1, max(array_map('count', $byM))); ?>
  <div class="stats">
    <div class="stat big"><b><?= $totWk ?></b><span>Prospecting activities this week</span></div>
    <div class="stat big" style="background:#1f7a4d;border-color:#1f7a4d"><b><?= $totLeads30 ?></b><span>New leads, last 30 days</span></div>
<?php foreach (PR_METHODS as $k => $x): $g = (int)($goals[$k] ?? 0); $a = (int)($actWk[$k] ?? 0); ?>
    <a class="stat" href="prospecting.php?m=<?= $k ?>" style="border-left:5px solid <?= $x[1] ?>"><b><?= $new30($byM[$k]) ?></b><span><?= $h($x[0]) ?> leads, 30 days &middot; <?= $a ?><?= $g ? '/' . $g : '' ?> activities this week</span><?php if ($g): ?><div class="bar"><i style="width:<?= min(100, round($a / $g * 100)) ?>%;background:<?= $x[1] ?>"></i></div><?php endif; ?></a>
<?php endforeach; ?>
  </div>
  <div class="pgrid">
    <div>
      <div class="lpanel"><h2>Where your leads come from</h2>
<?php if (!$leads): ?>        <p class="empty">No leads yet. Add them in <a href="leads.php">Leads &amp; Contacts</a> and tag where they came from.</p>
<?php else: foreach (PR_METHODS as $k => $x): $n = count($byM[$k]); ?>
        <div class="srcbar"><span class="l"><?= $h($x[0]) ?></span><span class="t"><i style="width:<?= round($n / $maxL * 100) ?>%;background:<?= $x[1] ?>"></i></span><span class="v"><?= $n ?></span></div>
<?php endforeach; if ($untagged): ?>        <p style="font-size:.85rem;color:#5a6a6c;margin:10px 0 0"><?= $untagged ?> lead<?= $untagged === 1 ? ' has' : 's have' ?> no source yet. Tag them in <a href="leads.php">Leads &amp; Contacts</a> so they count here.</p>
<?php endif; endif; ?>
      </div>
    </div>
    <div class="lpanel"><h2>Recent activity</h2>
<?php if (!$recent): ?>      <p class="empty">Nothing logged yet. Open a method above and log what you did.</p>
<?php else: foreach (array_slice($recent, 0, 10) as $r): $x = PR_METHODS[$r['method']] ?? ['', '#999']; ?>
      <div class="lrow"><span class="dot" style="background:<?= $x[1] ?>"></span><span class="who"><span class="nm"><?= $h($r['what']) ?><?= (int)$r['qty'] > 1 ? ' &times; ' . (int)$r['qty'] : '' ?></span><span class="meta"><?= $h($x[0] . ' · ' . $when($r['d'])) ?></span></span></div>
<?php endforeach; endif; ?>
    </div>
  </div>

<?php else: $x = PR_METHODS[$m]; $list = $byM[$m]; $g = (int)($goals[$m] ?? 0); $a = (int)($actWk[$m] ?? 0); ?>
  <div class="stats">
    <div class="stat" style="border-left:5px solid <?= $x[1] ?>"><b><?= count($list) ?></b><span><?= $h($x[0]) ?> leads, all time</span></div>
    <div class="stat"><b><?= $new30($list) ?></b><span>New in the last 30 days</span></div>
    <div class="stat"><b><?= $a ?><?= $g ? '<small style="font-size:1rem;color:#5a6a6c"> / ' . $g . '</small>' : '' ?></b><span>Activities this week<?= $g ? ' (goal ' . $g . ')' : '' ?></span><?php if ($g): ?><div class="bar"><i style="width:<?= min(100, round($a / $g * 100)) ?>%;background:<?= $x[1] ?>"></i></div><?php endif; ?></div>
    <div class="stat"><b><?= (int)($act30[$m] ?? 0) ?></b><span>Activities, last 30 days</span></div>
  </div>
  <div class="pgrid">
    <div>
      <div class="lpanel"><h2><span class="dot" style="background:<?= $x[1] ?>"></span>Log <?= $h(strtolower($x[0])) ?> activity</h2>
        <form method="post"><?= $csrfIn ?><input type="hidden" name="a" value="log"><input type="hidden" name="m" value="<?= $m ?>">
          <div class="qk"><?php foreach ($x[3] as $w): ?><button type="button" data-w="<?= $h($w) ?>"><?= $h($w) ?></button><?php endforeach; ?></div>
          <div class="lgf">
            <div><label>What you did</label><input type="text" name="what" id="lgwhat" maxlength="80" required placeholder="Pick one above or type it"></div>
            <div><label>How many</label><input type="number" name="qty" min="1" max="999" value="1"></div>
            <div class="dt"><label>Date</label><input type="date" name="d" value="<?= $today ?>" max="<?= $today ?>"></div>
            <textarea name="notes" maxlength="300" placeholder="Notes (optional): who, where, what came of it"></textarea>
          </div>
          <p style="margin:10px 0 0"><button class="btn sm" type="submit">Log it</button></p>
        </form>
      </div>
      <div class="lpanel"><h2>Leads from <?= $h(strtolower($x[0])) ?></h2>
<?php if (!$list): ?>        <p class="empty">No leads tagged <?= $h(implode(', ', $x[2])) ?> yet. When a lead comes from <?= $h(strtolower($x[0])) ?>, tag it in <a href="leads.php">Leads &amp; Contacts</a>.</p>
<?php else: foreach (array_slice($list, 0, 50) as $L): $ts = array_values(array_intersect(ld_src_names(isset($L['tags']) ? (array)$L['tags'] : ld_auto_tags($L['source'] ?? '')), $x[2])); ?>
        <a class="lrow" href="leads.php?open=<?= urlencode((string)($L['id'] ?? '')) ?>"><span class="dot" style="background:<?= $x[1] ?>"></span><span class="who"><span class="nm"><?= $h($nm($L)) ?></span><span class="meta"><?= $h(implode(' · ', array_filter([implode(', ', $ts), (string)($L['phone'] ?? '')]))) ?></span></span><span class="due"><?= $h('Added ' . $when((string)($L['added'] ?? ''))) ?></span></a>
<?php endforeach; if (count($list) > 50): ?>        <p style="text-align:center;font-size:.88rem;color:#5a6a6c">Showing the newest 50 of <?= count($list) ?>.</p>
<?php endif; endif; ?>
      </div>
<?php if ($m === 'openhouse'): require_once __DIR__ . '/ohlib.php'; $mine = array_values(array_filter(oh_upcoming(), function ($o) use ($me) { return strtolower($o['agent_email']) === $me; })); ?>
      <div class="lpanel"><h2>Your upcoming open houses <a class="btn sm" href="openhouses.php?new=1" style="margin-left:auto;background:#e07b39">+ Post</a></h2>
<?php if (!$mine): ?>        <p class="empty">None posted. Post your next one so every agent sees it on Home.</p>
<?php else: foreach ($mine as $o): ?>
        <a class="lrow" href="openhouses.php#oh<?= (int)$o['id'] ?>"><span class="dot" style="background:#e07b39"></span><span class="who"><span class="nm"><?= $h($o['address']) ?></span><span class="meta"><?= $h(oh_when($o)) ?></span></span></a>
<?php endforeach; endif; ?>
      </div>
<?php elseif ($m === 'sphere'): $sph = count(array_filter($leads, function ($L) { return !empty($L['roles']['sphere']); })); ?>
      <div class="lpanel"><h2>Your Sphere list</h2>
        <p style="margin:0 0 10px"><b><?= $sph ?></b> <?= $sph === 1 ? 'person is' : 'people are' ?> in your Sphere (A, B and C lists) in Leads &amp; Contacts.</p>
        <a class="btn sm" href="leads.php">Open Leads &amp; Contacts</a>
      </div>
<?php endif; ?>
    </div>
    <div>
      <div class="lpanel"><h2>Weekly goal</h2>
        <form method="post" class="goalf"><?= $csrfIn ?><input type="hidden" name="a" value="goal"><input type="hidden" name="m" value="<?= $m ?>">
          <input type="number" name="goal" min="0" max="999" value="<?= $g ?: '' ?>" placeholder="0"> activities a week <button class="btn sm" type="submit">Save</button></form>
      </div>
      <div class="lpanel"><h2>Ideas that work</h2><ul class="ideas"><?php foreach ($x[4] as $t): ?><li><?= $h($t) ?></li><?php endforeach; ?></ul></div>
      <div class="lpanel"><h2>Your <?= $h(strtolower($x[0])) ?> log</h2>
<?php if (!$recent): ?>        <p class="empty">Nothing logged yet.</p>
<?php else: foreach ($recent as $r): ?>
        <div class="lrow"><span class="who"><span class="nm"><?= $h($r['what']) ?><?= (int)$r['qty'] > 1 ? ' &times; ' . (int)$r['qty'] : '' ?></span><span class="meta"><?= $h($when($r['d']) . ($r['notes'] !== '' ? ' · ' . $r['notes'] : '')) ?></span></span>
          <form method="post" onsubmit="return confirm('Remove this activity?')"><?= $csrfIn ?><input type="hidden" name="a" value="del"><input type="hidden" name="m" value="<?= $m ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="lgdel" title="Remove" aria-label="Remove">&times;</button></form></div>
<?php endforeach; endif; ?>
      </div>
    </div>
  </div>
<script>(function(){var w=document.getElementById("lgwhat");document.querySelectorAll(".qk button").forEach(function(b){b.addEventListener("click",function(){document.querySelectorAll(".qk button").forEach(function(x){x.classList.toggle("on",x===b);});w.value=b.dataset.w;});});
w.addEventListener("input",function(){document.querySelectorAll(".qk button").forEach(function(x){x.classList.toggle("on",x.dataset.w===w.value);});});})();</script>
<?php endif; ?>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
</body>
</html>
