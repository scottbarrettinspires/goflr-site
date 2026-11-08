<?php
// Messages: laid out like a web mail inbox (Gmail style). Left: Compose and the folders (Inbox, Sent, Done). Right:
// the message list (unread in bold) or one conversation with Reply. Compose opens as a window in the corner.
// Reached from the envelope icon and from each Directory card's Message button (?to=<key>). Data: msglib.php.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php"; // flr_is_broker()
require_once __DIR__ . "/msglib.php";
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
$isStaff = flr_is_broker();
$myKeys = om_my_keys($me, $isStaff);
$msgErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $msgErr = 'This page expired. Please reload and try again.'; }
    elseif (isset($_POST['msgundone'])) { om_undone((int)$_POST['msgundone'], (string)($_POST['rkey'] ?? ''), $myKeys); header('Location: messages.php?id=' . (int)$_POST['msgundone'] . '&moved=1'); exit; }
    else { $_POST['back'] = 'messages.php'; $msgErr = om_handle_post($me, trim($_SESSION['name'] ?? ''), $isStaff); }
}
// Old links: ?reply=<id> opens that message; om_handle_post sends replies back with ?replied=<id>.
if (!empty($_GET['reply'])) { header('Location: messages.php?id=' . (int)$_GET['reply'] . '#reply'); exit; }
if (!empty($_GET['replied'])) { header('Location: messages.php?id=' . (int)$_GET['replied'] . '&sent=1'); exit; }

$folder = in_array($_GET['f'] ?? '', ['sent', 'done'], true) ? $_GET['f'] : 'inbox';
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
$id = (int)($_GET['id'] ?? 0);
$agentsAll = om_active_agents(); $staffPeople = om_staff_people();
$h = function ($x) { return htmlspecialchars((string)$x); };
$csrfIn = '<input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="back" value="messages.php">';
$when = function ($iso, $long = false) {
    $t = strtotime((string)$iso); if (!$t) return '';
    if ($long) return date('D, M j, Y, g:i A', $t);
    return date('Y-m-d', $t) === date('Y-m-d') ? date('g:i A', $t) : (date('Y', $t) === date('Y') ? date('M j', $t) : date('n/j/y', $t));
};
$av = function ($p, $px) use ($h) { return '<span class="gav" style="width:' . $px . 'px;height:' . $px . 'px;flex-basis:' . $px . 'px;font-size:' . round($px * .38) . 'px"><span>' . $h($p['ini']) . '</span>' . ($p['photo'] ? '<img src="' . $h($p['photo']) . '" alt="" loading="lazy" onerror="this.remove()">' : '') . '</span>'; };

// Opened message / conversation
$view = null; $thread = []; $sentView = null; $recips = [];
if ($id && $folder !== 'sent') { $view = om_open($id, $myKeys); if ($view) $thread = om_thread($me, $myKeys, $view['from_key']); }
if ($id && !$view) { $s = om_get($id); if ($s && $s['from_email'] === $me) { $sentView = $s; $recips = om_recipients($id); $folder = 'sent'; } }
if ($view) $folder = $view['done_at'] ? 'done' : 'inbox';
$list = (!$view && !$sentView) ? ($folder === 'sent' ? om_sent_box($me, $q) : om_box($myKeys, $folder, $q)) : [];
$unread = om_unread_count($myKeys);
// Compose: pre-addressed from a Directory Message button (?to=<key>).
$composeTo = '';
if (!empty($_GET['to'])) { $t = (string)$_GET['to'];
    if ((strpos($t, 'agent:') === 0 && isset($agentsAll[substr($t, 6)]) && !in_array($t, $myKeys, true)) || (strpos($t, 'staff:') === 0 && $t !== 'staff:' . $me)) $composeTo = $t; }
if ($composeTo !== '' && strpos($composeTo, 'staff:') === 0 && $composeTo !== 'staff:*' && !isset($staffPeople[substr($composeTo, 6)])) $staffPeople[substr($composeTo, 6)] = om_key_label($composeTo);
$openCompose = $composeTo !== '' || !empty($_GET['compose']) || ($msgErr !== '' && isset($_POST['msgbody']) && !isset($_POST['replyto']));
$fname = function ($m) { return (string)($m['from_name'] ?: $m['from_email']); };
sp_head('Messages');
?>
<style>
.hashi{padding-bottom:6px}
.gm{display:grid;grid-template-columns:220px minmax(0,1fr);gap:18px;align-items:start}
.gmside{position:sticky;top:12px}
.gcomp{display:inline-flex;align-items:center;gap:12px;background:#c2e7e8;color:#04393b;border:0;border-radius:16px;padding:16px 24px 16px 18px;font:inherit;font-weight:700;font-size:.98rem;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,.12);margin:0 0 14px;transition:box-shadow .15s}
.gcomp:hover{box-shadow:0 2px 8px rgba(0,0,0,.2)}
.gfold{display:flex;flex-direction:column;gap:2px}
.gfold a{display:flex;align-items:center;gap:14px;padding:8px 14px 8px 16px;border-radius:0 99px 99px 0;margin-left:-16px;padding-left:28px;color:#1f2a2c;text-decoration:none;font-size:.93rem}
.gfold a:hover{background:#eaf1f1}.gfold a.on{background:#d3e9ea;font-weight:700;color:#04393b}.gfold a .c{margin-left:auto;font-size:.8rem;font-weight:700}
.gmain{background:var(--white);border-radius:16px;border:1px solid var(--line);min-height:420px;overflow:hidden}
.gbar{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--line)}
.gsearch{flex:1;display:flex;align-items:center;gap:10px;background:#eef3f3;border-radius:99px;padding:0 16px;max-width:560px}
.gsearch input{flex:1;border:0;background:none;font:inherit;padding:11px 0;outline:none}.gsearch:focus-within{background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18)}
.gbar .ct{margin-left:auto;font-size:.82rem;color:#5a6a6c;white-space:nowrap}
.grow{display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid #edf1f1;text-decoration:none;color:#3c4646;background:#f7f9f9;font-size:.92rem}
.grow:hover{box-shadow:inset 1px 0 0 #dadce0,inset -1px 0 0 #dadce0,0 1px 2px rgba(60,64,67,.25);position:relative;z-index:1}
.grow.un{background:#fff;color:#111}.grow.un .gf,.grow.un .gt,.grow.un .gsn b{font-weight:800}
.grow .gf{flex:0 0 190px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.grow .gsn{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#5f6b6d}.grow .gsn b{color:#1f2a2c;font-weight:600}
.grow .gt{flex:0 0 auto;font-size:.8rem;color:#5f6b6d}
.gtag{display:inline-block;font-size:.7rem;font-weight:700;padding:1px 7px;border-radius:5px;background:#e3eded;color:#3d5557;margin-right:6px;vertical-align:1px}
.gav{position:relative;border-radius:50%;overflow:hidden;background:var(--teal-soft);color:var(--teal);font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto}
.gav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.gempty{padding:60px 20px;text-align:center;color:#5f6b6d}
.gvbar{display:flex;align-items:center;gap:6px;padding:8px 10px;border-bottom:1px solid var(--line)}
.gib{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-width:38px;height:38px;padding:0 10px;border-radius:99px;border:0;background:none;color:#3c4646;font:inherit;font-size:.86rem;font-weight:600;cursor:pointer;text-decoration:none}
.gib:hover{background:#eaf1f1}.gvbar form{display:inline}
.gsubj{font-size:1.35rem;font-weight:500;margin:18px 22px 6px;color:#1f2a2c}
.gmsg{display:flex;gap:14px;padding:16px 22px;border-bottom:1px solid #edf1f1}.gmsg.me{background:#fbfdfd}
.gmsg .hd{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}.gmsg .hd b{font-size:.95rem}.gmsg .hd .to{font-size:.8rem;color:#5f6b6d}.gmsg .hd .tm{margin-left:auto;font-size:.8rem;color:#5f6b6d}
.gmsg .bd{white-space:pre-line;margin:8px 0 0;line-height:1.55;color:#222;overflow-wrap:anywhere}.gmsg.cur{box-shadow:inset 3px 0 0 var(--teal)}
.grep{padding:16px 22px 22px}
.grbtn{display:inline-flex;align-items:center;gap:8px;border:1px solid #c4cfd0;background:#fff;border-radius:99px;padding:9px 22px;font:inherit;font-weight:600;color:#3c4646;cursor:pointer}.grbtn:hover{background:#f3f7f7}
.grbox{border:1px solid #c4cfd0;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.08);padding:12px 14px}
.grbox .to{font-size:.85rem;color:#5f6b6d;margin:0 0 6px}.grbox textarea{width:100%;box-sizing:border-box;border:0;outline:none;font:inherit;min-height:110px;resize:vertical}
.gsend{background:var(--teal);color:#fff;border:0;border-radius:99px;padding:9px 24px;font:inherit;font-weight:700;cursor:pointer}.gsend:hover{background:#025c5e}
.gnote{margin:12px 22px 0;padding:10px 14px;border-radius:10px;background:#e3f4e8;color:#1e5e2f;font-size:.9rem;font-weight:600}
.gerr{background:#fde2e1;color:#8a1c14}
/* Compose window */
.gcw{position:fixed;right:24px;bottom:0;width:520px;max-width:calc(100vw - 16px);background:#fff;border-radius:12px 12px 0 0;box-shadow:0 8px 30px rgba(0,0,0,.28);z-index:10000;display:flex;flex-direction:column;max-height:85vh}
.gcw[hidden]{display:none}.gcw .hd{display:flex;align-items:center;background:#eaf1f1;border-radius:12px 12px 0 0;padding:10px 12px 10px 16px;font-weight:700;font-size:.92rem;color:#1f2a2c}
.gcw .hd button{margin-left:auto;background:none;border:0;font-size:1.3rem;line-height:1;cursor:pointer;color:#3c4646;padding:2px 6px;border-radius:6px}.gcw .hd button:hover{background:#d3e3e3}
.gcw form{display:flex;flex-direction:column;overflow:auto}
.gcw .fl{display:flex;align-items:center;gap:8px;border-bottom:1px solid #edf1f1;padding:6px 16px;font-size:.9rem;color:#5f6b6d}
.gcw .fl select,.gcw .fl input[type=text]{flex:1;border:0;font:inherit;padding:6px 0;outline:none;background:none;color:#1f2a2c}
.gcw .modes{display:flex;flex-wrap:wrap;gap:6px;padding:8px 16px;border-bottom:1px solid #edf1f1}
.gcw .modes label{font-size:.82rem;font-weight:600;padding:5px 11px;border-radius:99px;border:1px solid var(--line);cursor:pointer}.gcw .modes input{display:none}.gcw .modes label:has(input:checked){background:var(--teal);color:#fff;border-color:var(--teal)}
.gcw textarea{border:0;outline:none;font:inherit;padding:12px 16px;min-height:200px;resize:vertical}
.gcw .ft{display:flex;align-items:center;gap:10px;padding:10px 16px 14px}
#grplist{max-height:180px;overflow:auto;padding:4px 16px;border-bottom:1px solid #edf1f1}#grplist label{display:block;padding:3px 0;font-size:.9rem}
@media(max-width:820px){.gm{grid-template-columns:1fr}.gmside{position:static;display:flex;flex-wrap:wrap;align-items:center;gap:8px}.gcomp{margin:0}.gfold{flex-direction:row;flex-wrap:wrap}.gfold a{margin:0;padding:8px 14px;border-radius:99px}
  .gbar .ct{display:none}.grow .gf{flex-basis:120px}.grow{flex-wrap:wrap}.grow .gsn{flex-basis:100%;order:3;padding-left:48px}.gcw{right:0;left:0;width:auto;max-width:none}}
</style>
<div class="gm">
  <aside class="gmside">
    <button type="button" class="gcomp" data-compose><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>Compose</button>
    <nav class="gfold" aria-label="Folders">
      <a href="messages.php" class="<?= $folder === 'inbox' ? 'on' : '' ?>"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13l3.5 7v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z"/></svg>Inbox<?php if ($unread): ?><span class="c"><?= $unread ?></span><?php endif; ?></a>
      <a href="messages.php?f=sent" class="<?= $folder === 'sent' ? 'on' : '' ?>"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>Sent</a>
      <a href="messages.php?f=done" class="<?= $folder === 'done' ? 'on' : '' ?>"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="5" rx="1"/><path d="M4 9v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9M10 13h4"/></svg>Done</a>
    </nav>
  </aside>

  <section class="gmain">
<?php if ($view): $other = om_person($view['from_key'], $fname($view)); $canReply = $view['from_key'] !== '' && !in_array($view['from_key'], $myKeys, true); $office = $view['rkey'] === 'staff:*'; ?>
    <div class="gvbar">
      <a class="gib" href="messages.php<?= $view['done_at'] ? '?f=done' : '' ?>" title="Back" aria-label="Back"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></a>
<?php if (!$view['done_at']): ?>
      <form method="post"><?= $csrfIn ?><input type="hidden" name="msgdismiss" value="<?= (int)$view['id'] ?>"><input type="hidden" name="rkey" value="<?= $h($view['rkey']) ?>"><button class="gib" title="<?= $office ? 'Mark handled' : 'Done' ?>"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><?= $office ? 'Mark handled' : 'Done' ?></button></form>
<?php else: ?>
      <form method="post"><?= $csrfIn ?><input type="hidden" name="msgundone" value="<?= (int)$view['id'] ?>"><input type="hidden" name="rkey" value="<?= $h($view['rkey']) ?>"><button class="gib"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13l3.5 7v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z"/></svg>Move to Inbox</button></form>
<?php endif; ?>
<?php if ($canReply): ?>      <a class="gib" href="#reply" data-reply><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 17 4 12l5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>Reply</a><?php endif; ?>
    </div>
<?php if (!empty($_GET['sent'])): ?>    <p class="gnote">&#10003; Reply sent to <?= $h($fname($view)) ?></p><?php endif; ?>
<?php if (!empty($_GET['moved'])): ?>    <p class="gnote">Moved back to your Inbox.</p><?php endif; ?>
<?php if ($msgErr): ?>    <p class="gnote gerr"><?= $h($msgErr) ?></p><?php endif; ?>
    <h2 class="gsubj">Conversation with <?= $h($other['name'] ?: $fname($view)) ?><?= $office ? ' <span class="gtag">Office inbox</span>' : '' ?></h2>
<?php foreach ($thread ?: [$view] as $m): $mine = strtolower((string)$m['from_email']) === $me; $p = $mine ? om_person($isStaff ? 'staff:' . $me : ($myKeys[0] ?? ''), trim($_SESSION['name'] ?? '') ?: 'You') : om_person($m['from_key'], $fname($m)); ?>
    <div class="gmsg<?= $mine ? ' me' : '' ?><?= (int)$m['id'] === (int)$view['id'] ? ' cur' : '' ?>" id="om<?= (int)$m['id'] ?>">
      <?= $av($p, 40) ?>
      <div style="flex:1;min-width:0"><div class="hd"><b><?= $mine ? 'You' : $h($fname($m)) ?></b><span class="to"><?= $mine ? 'to ' . $h($m['to_label'] ?: om_key_label($view['from_key'])) : ($m['from_staff'] ? 'staff' : 'agent') . ' &middot; to ' . ($office ? 'the office' : 'me') ?></span><span class="tm"><?= $h($when($m['created'], true)) ?></span></div>
        <div class="bd"><?= $h($m['body']) ?></div></div>
    </div>
<?php endforeach; ?>
    <div class="grep" id="reply">
<?php if ($canReply): ?>
      <button type="button" class="grbtn" data-reply><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 17 4 12l5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>Reply</button>
      <form method="post" class="grbox" id="grform" hidden><?= $csrfIn ?><input type="hidden" name="replyto" value="<?= (int)$view['id'] ?>"><input type="hidden" name="rkey" value="<?= $h($view['rkey']) ?>">
        <p class="to">To: <?= $h($fname($view)) ?></p>
        <textarea name="msgbody" required placeholder="Write your reply..."></textarea>
        <p style="margin:6px 0 0;display:flex;gap:10px;align-items:center"><button class="gsend">Send</button><button type="button" class="gib" data-cancel>Discard</button></p>
      </form>
<?php else: ?>      <p style="color:#5f6b6d;font-size:.9rem;margin:0">This message can't be replied to here.</p><?php endif; ?>
    </div>

<?php elseif ($sentView): $nSeen = count(array_filter($recips, function ($r) { return $r['seen_at']; })); ?>
    <div class="gvbar"><a class="gib" href="messages.php?f=sent" title="Back" aria-label="Back"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></a></div>
    <h2 class="gsubj">To <?= $h($sentView['to_label']) ?></h2>
    <div class="gmsg me"><?= $av(om_person($isStaff ? 'staff:' . $me : ($myKeys[0] ?? ''), trim($_SESSION['name'] ?? '') ?: 'You'), 40) ?>
      <div style="flex:1;min-width:0"><div class="hd"><b>You</b><span class="to">to <?= $h($sentView['to_label']) ?></span><span class="tm"><?= $h($when($sentView['created'], true)) ?></span></div><div class="bd"><?= $h($sentView['body']) ?></div></div></div>
    <p style="padding:12px 22px;margin:0;color:#5f6b6d;font-size:.88rem">Read by <?= $nSeen ?> of <?= count($recips) ?></p>

<?php else: ?>
    <div class="gbar">
      <form class="gsearch" method="get" role="search"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5f6b6d" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <?php if ($folder !== 'inbox'): ?><input type="hidden" name="f" value="<?= $folder ?>"><?php endif; ?><input type="search" name="q" value="<?= $h($q) ?>" placeholder="Search messages"></form>
      <span class="ct"><?= count($list) ? '1–' . count($list) : '0' ?></span>
    </div>
<?php if ($msgErr): ?>    <p class="gnote gerr"><?= $h($msgErr) ?></p><?php endif; ?>
<?php if (!empty($_GET['msgsent'])): ?>    <p class="gnote">&#10003; Message sent.</p><?php endif; ?>
<?php if (!$list): ?>
    <div class="gempty"><?= $q !== '' ? 'No messages match "' . $h($q) . '".' : ['inbox' => 'Your inbox is empty. Nice and clean!', 'sent' => 'Nothing sent yet.', 'done' => 'Messages you mark Done show up here.'][$folder] ?></div>
<?php else: foreach ($list as $m):
    if ($folder === 'sent') { $p = om_person($isStaff ? 'staff:' . $me : ($myKeys[0] ?? ''), 'You'); $un = false; $who = 'To: ' . $m['to_label']; $tag = ''; }
    else { $p = om_person($m['from_key'], $fname($m)); $un = !$m['seen_at']; $who = $fname($m); $tag = $m['rkey'] === 'staff:*' ? '<span class="gtag">Office</span>' : ((string)$m['to_label'] !== '' && strpos((string)$m['to_label'], 'All agents') === 0 ? '<span class="gtag">All agents</span>' : ''); }
    $snip = trim(preg_replace('/\s+/', ' ', (string)$m['body'])); $first = mb_strimwidth($snip, 0, 60, '…'); $rest = mb_strlen($snip) > 60 ? ' - ' . mb_strimwidth(mb_substr($snip, 60), 0, 120, '…') : ''; ?>
    <a class="grow<?= $un ? ' un' : '' ?>" href="messages.php?<?= $folder === 'sent' ? 'f=sent&amp;' : '' ?>id=<?= (int)$m['id'] ?>"><?= $av($p, 32) ?><span class="gf"><?= $h($who) ?></span><span class="gsn"><?= $tag ?><b><?= $h($first) ?></b><?= $h($rest) ?></span><span class="gt"><?= $folder === 'sent' ? '<span style="margin-right:8px">' . (int)$m['seen'] . '/' . (int)$m['n'] . ' read</span>' : '' ?><?= $h($when($m['created'])) ?></span></a>
<?php endforeach; endif; ?>
<?php endif; ?>
  </section>
</div>

<div class="gcw" id="gcw"<?= $openCompose ? '' : ' hidden' ?> role="dialog" aria-label="New message">
  <div class="hd">New Message<button type="button" data-close aria-label="Close">&times;</button></div>
  <form method="post" id="compose"><?= $csrfIn ?>
<?php if ($msgErr && $openCompose): ?>    <p class="gnote gerr" style="margin:10px 16px 0"><?= $h($msgErr) ?></p><?php endif; ?>
<?php if ($isStaff): $mode = $composeTo ? 'one' : (string)($_POST['mode'] ?? 'one'); ?>
    <div class="modes"><label><input type="radio" name="mode" value="one" <?= $mode === 'one' ? 'checked' : '' ?>>One person</label><label><input type="radio" name="mode" value="group" <?= $mode === 'group' ? 'checked' : '' ?>>A group of agents</label><label><input type="radio" name="mode" value="all" <?= $mode === 'all' ? 'checked' : '' ?>>All agents (<?= count($agentsAll) ?>)</label></div>
    <div class="fl" data-mode="one">To <select name="to"><option value="">Choose who it's for&hellip;</option>
      <optgroup label="Agents"><?php foreach ($agentsAll as $aid => $a): ?><option value="agent:<?= $h($aid) ?>" <?= $composeTo === 'agent:' . $aid ? 'selected' : '' ?>><?= $h($a['name']) ?></option><?php endforeach; ?></optgroup>
      <optgroup label="Staff"><option value="staff:*" <?= $composeTo === 'staff:*' ? 'selected' : '' ?>>All staff (office inbox)</option><?php foreach ($staffPeople as $e => $n): if ($e === $me) continue; ?><option value="staff:<?= $h($e) ?>" <?= $composeTo === 'staff:' . $e ? 'selected' : '' ?>><?= $h($n) ?></option><?php endforeach; ?></optgroup></select></div>
    <div data-mode="group" hidden>
      <div class="fl">To <input type="text" id="grpq" placeholder="Search agents..."> <a href="#" id="grpall" style="font-size:.8rem">All shown</a> <a href="#" id="grpnone" style="font-size:.8rem">Clear</a> <span id="grpn" style="font-size:.8rem">0</span></div>
      <div id="grplist"><?php foreach ($agentsAll as $aid => $a): ?><label data-s="<?= $h(strtolower($a['name'] . ' ' . $a['office'])) ?>"><input type="checkbox" name="group[]" value="<?= $h($aid) ?>"> <?= $h($a['name']) ?><?= $a['office'] !== '' ? ' <span style="color:#5a6a6c;font-size:.8rem">&middot; ' . $h($a['office']) . '</span>' : '' ?></label><?php endforeach; ?></div>
    </div>
    <div class="fl" data-mode="all" hidden>To every Active agent (<?= count($agentsAll) ?>)</div>
<?php else: ?>
    <div class="fl">To <select name="to">
      <optgroup label="Office"><option value="staff:*">Anyone in the office</option><?php foreach ($staffPeople as $e => $n): ?><option value="staff:<?= $h($e) ?>" <?= $composeTo === 'staff:' . $e ? 'selected' : '' ?>><?= $h($n) ?></option><?php endforeach; ?></optgroup>
      <optgroup label="Agents"><?php foreach ($agentsAll as $aid => $a): if (in_array('agent:' . $aid, $myKeys, true)) continue; ?><option value="agent:<?= $h($aid) ?>" <?= $composeTo === 'agent:' . $aid ? 'selected' : '' ?>><?= $h($a['name']) ?></option><?php endforeach; ?></optgroup></select></div>
<?php endif; ?>
    <textarea name="msgbody" required placeholder="Write your message..."><?= $openCompose && $msgErr ? $h((string)($_POST['msgbody'] ?? '')) : '' ?></textarea>
    <div class="ft"><button class="gsend">Send</button><span style="font-size:.8rem;color:#5f6b6d">They'll see it in their Messages and on the envelope icon.</span></div>
  </form>
</div>
<script>
(function(){
var w=document.getElementById("gcw"),f=document.getElementById("compose");
document.addEventListener("click",function(e){
  if(e.target.closest("[data-compose]")){w.hidden=false;f.querySelector("textarea").focus();return;}
  if(e.target.closest("#gcw [data-close]")){w.hidden=true;return;}
  var r=e.target.closest("[data-reply]");if(r){e.preventDefault();var g=document.getElementById("grform");if(!g)return;g.hidden=false;var b=document.querySelector(".grbtn");if(b)b.hidden=true;g.querySelector("textarea").focus();g.scrollIntoView({block:"center",behavior:"smooth"});return;}
  if(e.target.closest("[data-cancel]")){var g2=document.getElementById("grform");g2.hidden=true;g2.querySelector("textarea").value="";var b2=document.querySelector(".grbtn");if(b2)b2.hidden=false;}
});
if(location.hash==="#reply"){var rb=document.querySelector("[data-reply]");if(rb)rb.click();}
<?php if ($isStaff): ?>
function show(){var m=(f.querySelector("input[name=mode]:checked")||{}).value||"one";f.querySelectorAll("[data-mode]").forEach(function(el){el.hidden=el.getAttribute("data-mode")!==m;});}
f.querySelectorAll("input[name=mode]").forEach(function(r){r.addEventListener("change",show);});show();
var q=document.getElementById("grpq"),list=document.getElementById("grplist"),n=document.getElementById("grpn");
function count(){n.textContent=list.querySelectorAll("input:checked").length+" selected";}count();
list.addEventListener("change",count);
q.addEventListener("input",function(){var v=q.value.trim().toLowerCase();list.querySelectorAll("label").forEach(function(l){l.style.display=(!v||l.dataset.s.indexOf(v)>-1)?"block":"none";});});
document.getElementById("grpall").addEventListener("click",function(e){e.preventDefault();list.querySelectorAll("label").forEach(function(l){if(l.style.display!=="none")l.querySelector("input").checked=true;});count();});
document.getElementById("grpnone").addEventListener("click",function(e){e.preventDefault();list.querySelectorAll("input").forEach(function(i){i.checked=false;});count();});
f.addEventListener("submit",function(e){var m=(f.querySelector("input[name=mode]:checked")||{}).value;
  if(m==="all"&&!confirm("Send this to all <?= count($agentsAll) ?> agents?")){e.preventDefault();}
  if(m==="one"&&!f.querySelector("select[name=to]").value){e.preventDefault();alert("Choose who the message is for.");}
  if(m==="group"&&!list.querySelectorAll("input:checked").length){e.preventDefault();alert("Pick at least one agent.");}});
<?php endif; ?>
})();
</script>
<?php sp_foot();
