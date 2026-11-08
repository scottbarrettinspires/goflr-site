<?php
// Recruiting CRM: one shared brokerage-wide pipeline for recruiting agents to FLR. Staff/broker only.
// Near-duplicate of leads.php's look (tabs, sidebar, due badges, stats), server-rendered instead of the single-page
// JS app leads.php uses -- recruiting doesn't need the buyer/seller property-detail forms that drove that complexity.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/rcrlib.php";
if (!flr_is_broker()) { header('Location: home.php'); exit; }
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
list($agent, $phone) = rcr_me();
$msg = ''; $err = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $op = (string)($_POST['op'] ?? '');
        if ($op === 'save') {
            $e = rcr_save($_POST, (string)($_POST['id'] ?? ''));
            if ($e) { $err = $e; } else { header('Location: recruiting.php?tab=' . urlencode((string)($_POST['type'] ?? 'overview')) . '&flash=' . urlencode('Saved.')); exit; }
        } elseif ($op === 'touch') {
            $e = rcr_touch((string)($_POST['id'] ?? ''), (string)($_POST['note'] ?? ''));
            if ($e) { $err = $e; } else { header('Location: recruiting.php?id=' . urlencode((string)($_POST['id'] ?? '')) . '&flash=' . urlencode('Touch logged.')); exit; }
        } elseif ($op === 'delete') {
            rcr_delete((string)($_POST['id'] ?? ''));
            header('Location: recruiting.php?flash=' . urlencode('Removed.')); exit;
        } elseif ($op === 'intake_reset') {
            rcr_intake_reset();
            header('Location: recruiting.php?flash=' . urlencode('New link made. The old one stops working.')); exit;
        }
    }
}
if (($_GET['flash'] ?? '') !== '') $msg = (string)$_GET['flash'];

$recruits = rcr_view($agent, $phone);
$intake = rcr_intake_ensure();
$intakeUrl = 'https://goflr.com/recruit_in.php?k=' . ($intake['key'] ?? '');

$tab = (string)($_GET['tab'] ?? 'overview');
$sub = (string)($_GET['sub'] ?? '');
$editId = (string)($_GET['id'] ?? '');
$editRec = $editId !== '' ? rcr_find($editId) : null;
$adding = $tab !== 'overview' && $sub === 'add';

function rcr_count_type($recruits, $type) { return count(array_filter($recruits, function ($R) use ($type) { return ($R['type'] ?? '') === $type; })); }
function rcr_count_sub($recruits, $type, $status) { return count(array_filter($recruits, function ($R) use ($type, $status) { return ($R['type'] ?? '') === $type && ($R['status'] ?? '') === $status; })); }
function rcr_due_badge($R) {
    if (in_array($R['status'] ?? '', RCR_CLOSED, true)) { $cls = ($R['status'] === 'joined') ? 'background:#e1f3f2;color:#036f72' : 'background:#eee;color:#6a777a'; return '<span class="due" style="' . $cls . '">' . htmlspecialchars(RCR_STATUS[$R['status']]['label']) . '</span>'; }
    if (!empty($R['done'])) return '<span class="due later">Plan finished</span>';
    if (!isset($R['over'])) return '<span class="due off">No plan</span>';
    if ($R['over'] > 0) return '<span class="due over">Overdue ' . (int)$R['over'] . ' day' . ($R['over'] === 1 ? '' : 's') . ' &middot; ' . htmlspecialchars($R['ch']) . '</span>';
    if ($R['over'] === 0) return '<span class="due today">Due today &middot; ' . htmlspecialchars($R['ch']) . '</span>';
    return '<span class="due later">' . htmlspecialchars(date('M j', strtotime($R['due']))) . ' &middot; ' . htmlspecialchars($R['ch']) . '</span>';
}
function rcr_name($R) { return trim(($R['first'] ?? '') . ' ' . ($R['last'] ?? '')) ?: ($R['phone'] ?? ($R['email'] ?? '(no name)')); }
function rcr_row($R) {
    $meta = htmlspecialchars((RCR_TYPES[$R['type'] ?? ''] ?? '') . ' &middot; ' . (RCR_STATUS[$R['status'] ?? '']['label'] ?? '') . ($R['phone'] ? ' &middot; ' . $R['phone'] : ''));
    return '<a class="lrow" style="text-decoration:none;color:inherit;display:flex" href="recruiting.php?id=' . htmlspecialchars($R['id']) . '"><span class="dot h' . (($R['over'] ?? 0) > 0 ? '5' : '2') . '"></span><div class="who"><div class="nm">' . htmlspecialchars(rcr_name($R)) . '</div><div class="meta">' . $meta . '</div></div>' . rcr_due_badge($R) . '</a>';
}
$dueNow = array_values(array_filter($recruits, function ($R) { return isset($R['over']) && $R['over'] >= 0; }));
usort($dueNow, function ($a, $b) { return $b['over'] - $a['over']; });
$newCount = rcr_count_sub($recruits, 'other_brokerage', 'new') + rcr_count_sub($recruits, 'new_prelicense', 'new') + rcr_count_sub($recruits, 'new_not_active', 'new') + rcr_count_sub($recruits, 'new_joined_other', 'new') + rcr_count_sub($recruits, 'past_inactive', 'new') + rcr_count_sub($recruits, 'flr_retention', 'new');
$meetingCount = count(array_filter($recruits, function ($R) { return ($R['status'] ?? '') === 'meeting'; }));
$consideringCount = count(array_filter($recruits, function ($R) { return ($R['status'] ?? '') === 'considering'; }));
$joinedCount = count(array_filter($recruits, function ($R) { return ($R['status'] ?? '') === 'joined'; }));
?>
<!doctype html>
<html lang="en">
<head>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#036f72">
<link rel="icon" href="/icon-192.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Recruiting CRM - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=16">
<style>
.ltabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 16px}
.ltabs a{padding:8px 14px;border-radius:999px;border:1px solid var(--line);color:#1b4b4d;text-decoration:none;font-size:.9rem;font-weight:600}
.ltabs a.on{background:#036f72;color:#fff;border-color:#036f72}
.ltabs a .n{opacity:.75;font-weight:500}
.lwrap{display:grid;grid-template-columns:230px 1fr;gap:16px}
.lside{display:flex;flex-direction:column;gap:6px}
.sb{display:flex;justify-content:space-between;gap:8px;padding:9px 12px;border-radius:10px;border:1px solid var(--line);background:#fff;text-align:left;text-decoration:none;color:inherit;font-size:.88rem}
.sb.on{background:#eaf4f3;border-color:#036f72;font-weight:600}
.sb .n{color:#6a777a}
@media (max-width:700px){.lwrap{grid-template-columns:1fr}}
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Virtual Broker Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php">Active Business</a>
    <a href="performance.php">Performance</a>
    <?php endif; ?>
    <a href="recruiting.php" aria-current="page">Recruiting</a>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>
<section class="hero"><h1>Recruiting CRM</h1><p>Recruiting agents to Florida Luxury Realty: New Licensees, licensed agents at other brokerages, and FLR's own agents. One shared pipeline for the whole brokerage.</p></section>
<main class="wrap">
<?php if ($msg): ?><p class="note"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>

<?php if ($editId !== ''): ?>
  <?php if (!$editRec): ?><p class="note">That recruit was not found. <a href="recruiting.php">Back to Recruiting</a></p><?php else:
    $nx = rcr_next($editRec); ?>
  <div class="panel sigbox">
    <p><a href="recruiting.php?tab=<?= htmlspecialchars($editRec['type']) ?>">&larr; Back to <?= htmlspecialchars(RCR_TYPES[$editRec['type']] ?? '') ?></a></p>
    <h2 style="margin-top:0"><?= htmlspecialchars(rcr_name($editRec)) ?></h2>
    <p style="color:#5a6a6c;margin-top:-6px">Added <?= htmlspecialchars($editRec['added'] ?? '') ?><?= $editRec['source'] ? ' &middot; ' . htmlspecialchars($editRec['source']) : '' ?><?= $editRec['referred_by'] ? ' &middot; handed off by ' . htmlspecialchars($editRec['referred_by']) : '' ?></p>
    <form method="post" class="fgrid">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="save"><input type="hidden" name="id" value="<?= htmlspecialchars($editRec['id']) ?>">
      <label>First name<input name="first" value="<?= htmlspecialchars($editRec['first'] ?? '') ?>"></label>
      <label>Last name<input name="last" value="<?= htmlspecialchars($editRec['last'] ?? '') ?>"></label>
      <label>Phone<input name="phone" value="<?= htmlspecialchars($editRec['phone'] ?? '') ?>"></label>
      <label>Email<input name="email" value="<?= htmlspecialchars($editRec['email'] ?? '') ?>"></label>
      <label>Prospect Type<select name="type"><?php foreach (RCR_TYPES as $k => $lbl): ?><option value="<?= $k ?>" <?= $editRec['type'] === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option><?php endforeach; ?></select></label>
      <label>Status<select name="status"><?php foreach (RCR_STATUS as $k => $s): ?><option value="<?= $k ?>" <?= ($editRec['status'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($s['label']) ?></option><?php endforeach; ?></select></label>
      <label>When could they move<select name="when"><option value="">Unknown / not asked</option><?php foreach (RCR_WHEN as $k => $lbl): if ($k === 'unknown') continue; ?><option value="<?= $k ?>" <?= ($editRec['when'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option><?php endforeach; ?></select></label>
      <label>Current brokerage<input name="current_brokerage" value="<?= htmlspecialchars($editRec['current_brokerage'] ?? '') ?>"></label>
      <label>Trigger date <span style="color:#6a777a;font-weight:400">(contract renewal, anniversary, fee due)</span><input type="date" name="trigger_date" value="<?= htmlspecialchars($editRec['trigger_date'] ?? '') ?>"></label>
      <label>Trigger reason<input name="trigger_reason" value="<?= htmlspecialchars($editRec['trigger_reason'] ?? '') ?>" placeholder="e.g. Contract renewal"></label>
      <label>Source<input name="source" value="<?= htmlspecialchars($editRec['source'] ?? '') ?>"></label>
      <label>Owner <span style="color:#6a777a;font-weight:400">(who's working this one)</span><input name="owner" value="<?= htmlspecialchars($editRec['owner'] ?? '') ?>" placeholder="Scott or Lindsay"></label>
      <label class="full">Notes<textarea name="notes" rows="3"><?= htmlspecialchars($editRec['notes'] ?? '') ?></textarea></label>
      <p class="full"><button class="btn" type="submit">Save</button></p>
    </form>
    <?php if ($nx && empty($nx['done']) && !in_array($editRec['status'], RCR_CLOSED, true)): ?>
    <div class="panel" style="background:#f7fbfb;margin-top:10px">
      <p style="margin:0 0 4px"><strong>Next touch &middot; Plan <?= htmlspecialchars($nx['plan']) ?> (<?= htmlspecialchars(RCR_PLANS[$nx['plan']][0]) ?>), step <?= (int)$nx['step'] ?> of <?= (int)$nx['of'] ?>, due <?= htmlspecialchars($nx['due']) ?></strong></p>
      <p style="margin:4px 0;color:#5a6a6c"><?= $nx['msg']['ch'] ? htmlspecialchars($nx['msg']['ch']) . ($nx['msg']['subj'] ? ' &middot; ' . htmlspecialchars(rcr_fill($nx['msg']['subj'], $editRec, $agent, $phone)) : '') : '' ?></p>
      <p style="white-space:pre-wrap"><?= htmlspecialchars(rcr_fill($nx['msg']['text'], $editRec, $agent, $phone)) ?></p>
      <p style="color:#5a6a6c;font-size:.88rem"><?= htmlspecialchars($nx['msg']['goal']) ?></p>
      <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="touch"><input type="hidden" name="id" value="<?= htmlspecialchars($editRec['id']) ?>"><button class="btn sm" type="submit">Mark this touch done</button></form>
    </div>
    <?php elseif ($nx && !empty($nx['done'])): ?>
      <p class="note">Plan <?= htmlspecialchars($nx['plan']) ?> has run its course with no loop. Move the status or check in by hand.</p>
    <?php elseif (in_array($editRec['status'], RCR_CLOSED, true)): ?>
      <p class="note"><?= $editRec['status'] === 'joined' ? 'Joined -- no further recruiting touches. Run the onboarding checklist.' : 'No further touches are scheduled for this status.' ?></p>
    <?php endif; ?>
    <h3 style="margin:18px 0 8px">Log a touch</h3>
    <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="touch"><input type="hidden" name="id" value="<?= htmlspecialchars($editRec['id']) ?>">
      <input name="note" placeholder="What happened (optional)" style="width:70%"> <button class="btn sm ghost" type="submit">Log touch</button></form>
    <h3 style="margin:18px 0 8px">History</h3>
    <?php foreach (array_reverse($editRec['log'] ?? []) as $g): ?><p style="margin:2px 0;font-size:.9rem"><span style="color:#6a777a"><?= htmlspecialchars($g['d'] ?? '') ?></span> &middot; <?= htmlspecialchars($g['note'] ?? '') ?></p><?php endforeach; ?>
    <form method="post" style="margin-top:14px" onsubmit="return confirm('Remove this recruit? This cannot be undone.');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= htmlspecialchars($editRec['id']) ?>"><button class="btn sm ghost" type="submit">Remove</button></form>
  </div>
  <?php endif; ?>

<?php else: ?>
  <div class="ltabs">
    <a class="<?= $tab === 'overview' ? 'on' : '' ?>" href="recruiting.php">Overview</a>
    <?php foreach (RCR_TYPES as $k => $lbl): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="recruiting.php?tab=<?= $k ?>"><?= htmlspecialchars($lbl) ?> <span class="n">(<?= rcr_count_type($recruits, $k) ?>)</span></a><?php endforeach; ?>
  </div>

  <?php if ($tab === 'overview'): ?>
    <div class="stats">
      <div class="stat urgent"><b><?= count($dueNow) ?></b><span>Touches due now</span></div>
      <div class="stat urgent"><b><?= $newCount ?></b><span>New to contact</span></div>
      <div class="stat hot"><b><?= $meetingCount ?></b><span>Lunch/Meeting set</span></div>
      <div class="stat hot"><b><?= $consideringCount ?></b><span>Considering</span></div>
      <div class="stat" style="border-left:5px solid #036f72"><b><?= $joinedCount ?></b><span>Joined</span></div>
      <div class="stat"><b><?= count($recruits) ?></b><span>Total in the pipeline</span></div>
    </div>
    <div class="lpanel"><h2>Due now: work these first</h2><?= $dueNow ? implode('', array_map('rcr_row', array_slice($dueNow, 0, 25))) : '<p class="empty">Nothing due. Nice work.</p>' ?></div>
    <div class="lpanel" style="margin-top:14px">
      <h2>Your recruiting link</h2>
      <p style="color:#5a6a6c;margin:-4px 0 10px">Share this link for people to reach out about joining FLR, or hand it to an outside agent you meet during a transaction. Everyone who fills it in lands in the pipeline as New.</p>
      <div class="lrow" style="cursor:default"><div class="who"><div class="nm">Recruiting intake link</div><div class="meta"><?= htmlspecialchars($intakeUrl) ?></div></div><a class="btn sm ghost" href="<?= htmlspecialchars($intakeUrl) ?>" target="_blank" rel="noopener">Open</a></div>
      <form method="post" style="margin-top:8px"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="intake_reset"><button class="btn sm ghost" type="submit" onclick="return confirm('The old link stops working. Continue?');">Make a new link</button></form>
    </div>
    <p style="margin:14px 0"><a class="btn" href="recruiting.php?tab=other_brokerage&sub=add">+ Add a recruit by hand</a></p>
  <?php else: ?>
    <?php $statuses = RCR_STATUS; ?>
    <div class="lwrap">
      <div class="lside">
        <a class="sb <?= $sub === 'add' ? 'on' : '' ?>" href="recruiting.php?tab=<?= $tab ?>&sub=add" style="font-weight:700">+ Add new</a>
        <?php foreach ($statuses as $sk => $s): $n = rcr_count_sub($recruits, $tab, $sk); ?>
        <a class="sb <?= $sub === $sk ? 'on' : '' ?>" href="recruiting.php?tab=<?= $tab ?>&sub=<?= $sk ?>"><span><?= htmlspecialchars($s['label']) ?></span><span class="n"><?= $n ?></span></a>
        <?php endforeach; ?>
      </div>
      <div>
      <?php if ($adding): ?>
        <div class="lpanel">
          <h2>Add a recruit &middot; <?= htmlspecialchars(RCR_TYPES[$tab] ?? '') ?></h2>
          <form method="post" class="fgrid">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="op" value="save"><input type="hidden" name="type" value="<?= htmlspecialchars($tab) ?>">
            <label>First name<input name="first"></label>
            <label>Last name<input name="last"></label>
            <label>Phone<input name="phone"></label>
            <label>Email<input name="email"></label>
            <label>Current brokerage<input name="current_brokerage"></label>
            <label>Source<input name="source" placeholder="Call list, referral, DBPR list, Facebook..."></label>
            <label>Owner<input name="owner" placeholder="Scott or Lindsay"></label>
            <label class="full">Notes<textarea name="notes" rows="2"></textarea></label>
            <input type="hidden" name="type" value="<?= htmlspecialchars($tab) ?>">
            <p class="full"><button class="btn" type="submit">Add</button></p>
          </form>
        </div>
      <?php elseif ($sub !== ''): $list = array_values(array_filter($recruits, function ($R) use ($tab, $sub) { return ($R['type'] ?? '') === $tab && ($R['status'] ?? '') === $sub; })); ?>
        <div class="lpanel">
          <h2><?= htmlspecialchars(RCR_TYPES[$tab] ?? '') ?> &middot; <?= htmlspecialchars(RCR_STATUS[$sub]['label'] ?? '') ?></h2>
          <?= $list ? implode('', array_map('rcr_row', $list)) : '<p class="empty">No one here yet.</p>' ?>
        </div>
      <?php else: $list = array_values(array_filter($recruits, function ($R) use ($tab) { return ($R['type'] ?? '') === $tab; })); ?>
        <div class="lpanel">
          <h2><?= htmlspecialchars(RCR_TYPES[$tab] ?? '') ?></h2>
          <p style="color:#5a6a6c;margin:-6px 0 12px;font-size:.9rem">Pick a status on the left, or see everyone in this type below.</p>
          <?= $list ? implode('', array_map('rcr_row', $list)) : '<p class="empty">No one here yet.</p>' ?>
        </div>
      <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Broker / staff access only. Prospect information is confidential.</footer>
</body>
</html>
