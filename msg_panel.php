<?php
// "Messages" panel for staff Home and the Messages page (see msglib.php). Expects $me, $isStaff, $msgErr; optional
// $omPage ('home.php' or 'messages.php', where replies/sends return to).
if (!isset($isStaff, $me)) { http_response_code(404); exit; }
$omPage = (isset($omPage) && $omPage === 'messages.php') ? 'messages.php' : 'home.php';
$myKeys = om_my_keys($me, $isStaff);
$inbox = om_inbox($myKeys, 50);
$agentsAll = om_active_agents();
// Pre-addressed: Reply (?reply=<message id>) or a Directory "Message" button (?to=<key>).
$replyTo = ''; $replyName = ''; $isReply = false;
if (!empty($_GET['reply']) && ($rm = om_get((int)$_GET['reply'])) && $rm['from_key'] !== '' && !in_array($rm['from_key'], $myKeys, true)) { $replyTo = $rm['from_key']; $replyName = $rm['from_name'] ?: $rm['from_email']; $isReply = true; }
elseif (!empty($_GET['to'])) {
    $t = (string)$_GET['to'];
    if ((strpos($t, 'agent:') === 0 && isset($agentsAll[substr($t, 6)]) && !in_array($t, $myKeys, true)) || (strpos($t, 'staff:') === 0 && $t !== 'staff:' . strtolower($me))) { $replyTo = $t; $replyName = om_key_label($t); }
}
$staffPeople = om_staff_people();
$csrfIn = '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf']) . '"><input type="hidden" name="back" value="' . $omPage . '">';
// A staff person reached from the Directory who isn't in the staff list (rare) still needs to be selectable.
if ($replyTo !== '' && strpos($replyTo, 'staff:') === 0 && $replyTo !== 'staff:*' && !isset($staffPeople[substr($replyTo, 6)])) $staffPeople[substr($replyTo, 6)] = $replyName;
$fld = 'width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid var(--line);border-radius:10px;font:inherit';
?>
<style>.omcard .btn.sm{padding:6px 14px;font-size:.85rem;font-family:inherit;line-height:1.4;cursor:pointer;vertical-align:middle}.omcard button.btn.sm:not(.ghost){border:0}
.omcard .om{display:flex;gap:12px;padding:12px 0;border-top:1px solid var(--line)}.omcard .om.new{background:linear-gradient(90deg,rgba(3,111,114,.06),transparent)}
.omcard .omav{position:relative;flex:0 0 48px;width:48px;height:48px;border-radius:50%;overflow:hidden;background:var(--teal-soft);color:var(--teal);font-weight:700;display:flex;align-items:center;justify-content:center}
.omcard .omav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.omcard .ombody{flex:1;min-width:0}
.omcard .omnew{display:inline-block;font-size:.7rem;font-weight:700;letter-spacing:.04em;background:#c0392b;color:#fff;border-radius:99px;padding:1px 7px;margin-left:6px;vertical-align:middle}</style>
  <div class="panel sigbox omcard" id="messages" style="border-left:5px solid #036f72;margin:0 0 16px">
    <h2 style="margin-top:0"><?= $omPage === 'messages.php' ? 'Inbox' : 'Messages' ?><?php if ($inbox): ?> <span style="font-size:.85rem;color:#5a6a6c;font-weight:400">(<?= count($inbox) ?>)</span><?php endif; ?></h2>
<?php if (!$inbox): ?>
    <p style="color:#5a6a6c;margin:0 0 6px">No new messages.</p>
<?php else: foreach ($inbox as $m):
        $office = $m['rkey'] === 'staff:*';
        $canReply = $m['from_key'] !== '' && !in_array($m['from_key'], $myKeys, true);
        $who = om_person($m['from_key'], (string)($m['from_name'] ?: $m['from_email'])); ?>
    <div class="om<?= $m['seen_at'] ? '' : ' new' ?>">
      <div class="omav"><span><?= htmlspecialchars($who['ini']) ?></span><?php if ($who['photo']): ?><img src="<?= htmlspecialchars($who['photo']) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></div>
      <div class="ombody">
      <p style="margin:0 0 4px"><strong><?= htmlspecialchars($m['from_name'] ?: $m['from_email']) ?></strong><?= $m['seen_at'] ? '' : '<span class="omnew">NEW</span>' ?>
        <span style="color:#5a6a6c;font-size:.85rem">&middot; <?= $m['from_staff'] ? 'staff' : 'agent' ?><?= $isStaff ? ' &middot; to ' . htmlspecialchars($office ? 'the office' : 'you') : ($m['to_label'] && strpos($m['to_label'], 'All agents') === 0 ? ' &middot; to all agents' : '') ?> &middot; <?= htmlspecialchars(date('M j, g:ia', strtotime($m['created']))) ?></span></p>
      <p style="white-space:pre-line;margin:0 0 8px"><?= htmlspecialchars($m['body']) ?></p>
      <form method="post" style="display:inline"><?= $csrfIn ?><input type="hidden" name="msgdismiss" value="<?= (int)$m['id'] ?>"><input type="hidden" name="rkey" value="<?= htmlspecialchars($m['rkey']) ?>"><button class="btn sm ghost" type="submit"><?= $office ? 'Mark handled' : 'Got it' ?></button></form>
      <?php if ($canReply): ?> <a class="btn sm" href="<?= $omPage ?>?reply=<?= (int)$m['id'] ?>#compose">Reply</a><?php endif; ?>
      </div>
    </div>
<?php endforeach; endif; ?>

  </div>

  <div class="panel sigbox omcard" id="composecard" style="border-left:5px solid #e07b39;margin:0 0 16px">
    <form method="post" id="compose">
      <?php if (!empty($_GET['msgsent'])): ?><p class="note">Sent. They'll see it the next time they open their Home page.</p><?php endif; ?>
      <?php if (!empty($msgErr)): ?><p class="sigerr"><?= htmlspecialchars($msgErr) ?></p><?php endif; ?>
      <?= $csrfIn ?>
      <h2 style="margin:0 0 10px"><?= $replyTo ? ($isReply ? 'Reply to ' : 'Message ') . htmlspecialchars($replyName) : 'New message' ?></h2>
<?php if ($isStaff): $mode = $replyTo ? 'one' : (string)($_POST['mode'] ?? 'one'); ?>
      <p style="margin:0 0 8px;display:flex;flex-wrap:wrap;gap:14px">
        <label><input type="radio" name="mode" value="one" <?= $mode === 'one' ? 'checked' : '' ?>> One person</label>
        <label><input type="radio" name="mode" value="group" <?= $mode === 'group' ? 'checked' : '' ?>> A group of agents</label>
        <label><input type="radio" name="mode" value="all" <?= $mode === 'all' ? 'checked' : '' ?>> All agents (<?= count($agentsAll) ?>)</label>
      </p>
      <div data-mode="one">
        <select name="to" style="<?= $fld ?>;margin-bottom:8px">
          <option value="">Choose who it's for&hellip;</option>
          <optgroup label="Agents"><?php foreach ($agentsAll as $id => $a): ?><option value="agent:<?= htmlspecialchars($id) ?>" <?= $replyTo === 'agent:' . $id ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?></option><?php endforeach; ?></optgroup>
          <optgroup label="Staff"><option value="staff:*" <?= $replyTo === 'staff:*' ? 'selected' : '' ?>>All staff (office inbox)</option><?php foreach ($staffPeople as $e => $n): if ($e === strtolower($me)) continue; ?><option value="staff:<?= htmlspecialchars($e) ?>" <?= $replyTo === 'staff:' . $e ? 'selected' : '' ?>><?= htmlspecialchars($n) ?></option><?php endforeach; ?></optgroup>
        </select>
      </div>
      <div data-mode="group" hidden>
        <input type="text" id="grpq" placeholder="Search agents or office..." style="<?= $fld ?>;margin-bottom:6px">
        <p style="margin:0 0 6px;font-size:.85rem"><a href="#" id="grpall">Select all shown</a> &middot; <a href="#" id="grpnone">Clear</a> &middot; <span id="grpn">0</span> selected</p>
        <div id="grplist" style="max-height:220px;overflow:auto;border:1px solid var(--line);border-radius:10px;padding:6px 10px;margin-bottom:8px">
<?php foreach ($agentsAll as $id => $a): ?>
          <label style="display:block;padding:3px 0" data-s="<?= htmlspecialchars(strtolower($a['name'] . ' ' . $a['office'])) ?>"><input type="checkbox" name="group[]" value="<?= htmlspecialchars($id) ?>"> <?= htmlspecialchars($a['name']) ?><?= $a['office'] !== '' ? ' <span style="color:#5a6a6c;font-size:.82rem">&middot; ' . htmlspecialchars($a['office']) . '</span>' : '' ?></label>
<?php endforeach; ?>
        </div>
      </div>
      <p data-mode="all" hidden class="note" style="margin:0 0 8px">Goes to every Active agent (<?= count($agentsAll) ?>) on their Home page.</p>
<?php else: ?>
      <label style="display:block;margin-bottom:8px;font-size:.9rem">To
        <select name="to" style="<?= $fld ?>;margin-top:4px">
          <optgroup label="Office"><option value="staff:*">Anyone in the office</option>
<?php foreach ($staffPeople as $e => $n): ?>          <option value="staff:<?= htmlspecialchars($e) ?>" <?= $replyTo === 'staff:' . $e ? 'selected' : '' ?>><?= htmlspecialchars($n) ?></option>
<?php endforeach; ?></optgroup>
          <optgroup label="Agents"><?php foreach ($agentsAll as $id => $a): if (in_array('agent:' . $id, $myKeys, true)) continue; ?><option value="agent:<?= htmlspecialchars($id) ?>" <?= $replyTo === 'agent:' . $id ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?></option><?php endforeach; ?></optgroup>
        </select></label>
<?php endif; ?>
      <textarea name="msgbody" rows="3" placeholder="Type your message..." style="<?= $fld ?>" required></textarea>
      <p style="margin:8px 0 0"><button class="btn sm" type="submit">Send</button><?php if ($replyTo): ?> <a href="home.php#compose" style="font-size:.85rem;margin-left:8px">Cancel reply</a><?php endif; ?></p>
    </form>

<?php if ($isStaff && ($sent = om_sent($me, 8))): ?>
    <details style="margin-top:14px"><summary style="cursor:pointer;font-weight:600">Sent by you</summary>
<?php foreach ($sent as $s): ?>
      <div style="padding:8px 0;border-top:1px solid var(--line);font-size:.9rem">
        <strong>To <?= htmlspecialchars($s['to_label']) ?></strong> <span style="color:#5a6a6c">&middot; <?= htmlspecialchars(date('M j, g:ia', strtotime($s['created']))) ?> &middot; seen by <?= (int)$s['seen'] ?> of <?= (int)$s['n'] ?><?= (int)$s['done'] ? ', ' . (int)$s['done'] . ' cleared' : '' ?></span>
        <div style="white-space:pre-line;color:#455355"><?= htmlspecialchars(mb_strimwidth($s['body'], 0, 220, '…')) ?></div>
      </div>
<?php endforeach; ?>
    </details>
<?php endif; ?>
  </div>
<?php if ($isStaff): ?>
<script>
(function(){
  var f=document.getElementById("compose"); if(!f) return;
  function show(){var m=(f.querySelector("input[name=mode]:checked")||{}).value||"one";f.querySelectorAll("[data-mode]").forEach(function(el){el.hidden=el.getAttribute("data-mode")!==m;});}
  f.querySelectorAll("input[name=mode]").forEach(function(r){r.addEventListener("change",show);}); show();
  var q=document.getElementById("grpq"),list=document.getElementById("grplist"),n=document.getElementById("grpn");
  function count(){n.textContent=list.querySelectorAll("input:checked").length;}
  list.addEventListener("change",count);
  q.addEventListener("input",function(){var v=q.value.trim().toLowerCase();list.querySelectorAll("label").forEach(function(l){l.style.display=(!v||l.dataset.s.indexOf(v)>-1)?"block":"none";});});
  document.getElementById("grpall").addEventListener("click",function(e){e.preventDefault();list.querySelectorAll("label").forEach(function(l){if(l.style.display!=="none")l.querySelector("input").checked=true;});count();});
  document.getElementById("grpnone").addEventListener("click",function(e){e.preventDefault();list.querySelectorAll("input").forEach(function(i){i.checked=false;});count();});
  f.addEventListener("submit",function(e){var m=(f.querySelector("input[name=mode]:checked")||{}).value;
    if(m==="all"&&!confirm("Send this to all <?= count($agentsAll) ?> agents?")){e.preventDefault();}
    if(m==="one"&&!f.querySelector("select[name=to]").value){e.preventDefault();alert("Choose who the message is for.");}});
})();
</script>
<?php endif; ?>
