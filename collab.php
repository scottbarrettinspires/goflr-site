<?php
// Collaboration: FLR's private group feed (see collablib.php). Posts with an optional photo, likes, comments.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php"; // flr_is_broker()
require_once __DIR__ . "/collablib.php";
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = co_me();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $a = (string)($_POST['a'] ?? ''); $id = (int)($_POST['id'] ?? 0); $go = '';
        if ($a === 'post') {
            list($img, $e) = co_save_image($_FILES['photo'] ?? []);
            if ($e) $err = $e;
            else { $r = co_add_post($_POST['body'] ?? '', $img); if (is_int($r)) $go = '#p' . $r; else { $err = $r; if ($img) @unlink(co_img_dir() . '/' . $img); } }
        }
        elseif ($a === 'edit') { $err = co_edit_post($id, $_POST['body'] ?? ''); $go = '#p' . $id; }
        elseif ($a === 'delete') { $err = co_delete_post($id); }
        elseif ($a === 'pin' || $a === 'unpin') { $err = co_pin_post($id, $a === 'pin'); $go = '#p' . $id; }
        elseif ($a === 'comment') { $err = co_add_comment($id, $_POST['body'] ?? ''); $go = '#p' . $id; }
        elseif ($a === 'delcomment') { $c = co_comment($id); $err = co_delete_comment($id); $go = $c ? '#p' . $c['post_id'] : ''; }
        if ($err === '') { header('Location: collab.php' . $go); exit; }
    }
}
// An upload bigger than the server's limit arrives with no form fields at all.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) $err = 'That photo is too large. Please choose a smaller one.';

$before = (int)($_GET['before'] ?? 0);
list($posts, $more) = co_feed(20, $before);
co_mark_seen($me['email']);
$editId = (int)($_GET['edit'] ?? 0);
$mePerson = om_person($me['key'], $me['name']);
$csrfIn = '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf']) . '">';

function co_av($p, $px) {
    return '<span class="cav" style="width:' . $px . 'px;height:' . $px . 'px;flex-basis:' . $px . 'px;font-size:' . round($px * .38) . 'px"><span>' . htmlspecialchars($p['ini']) . '</span>'
        . ($p['photo'] ? '<img src="' . htmlspecialchars($p['photo']) . '" alt="" loading="lazy" onerror="this.remove()">' : '') . '</span>';
}
function co_likers($p, $myEmail) {
    $n = count($p['likes']); if (!$n) return '';
    $names = array_map(function ($l) use ($myEmail) { return $l['email'] === $myEmail ? 'You' : $l['name']; }, $p['likes']);
    $first = $p['liked'] ? 'You' : $names[0];
    return $n === 1 ? $first : $first . ' and ' . ($n - 1) . ' other' . ($n - 1 === 1 ? '' : 's');
}

sp_head('Collaboration');
?>
<style>
.cofeed{max-width:680px;margin:0 auto}
.cocard{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:14px 16px;margin:0 0 14px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.cav{position:relative;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;overflow:hidden;background:var(--teal-soft);color:var(--teal);font-weight:700;flex:0 0 auto}
.cav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.cohead{display:flex;gap:10px;align-items:center}.cohead .who{flex:1;min-width:0;line-height:1.3}.cohead .nm{font-weight:700}.cohead .meta{font-size:.8rem;color:#5a6a6c}
.cobadge{display:inline-block;font-size:.68rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;background:var(--teal-soft);color:var(--teal);border-radius:99px;padding:1px 7px;margin-left:6px;vertical-align:middle}
.copin{font-size:.78rem;font-weight:600;color:#b7791f;margin:0 0 8px}
.cobody{margin:10px 0 0;white-space:pre-line;overflow-wrap:anywhere;line-height:1.5}.cobody a{color:var(--teal)}
.coimg{display:block;margin:10px -16px 0;background:#f1f4f4}.coimg img{display:block;width:100%;max-height:640px;object-fit:contain}
.cocount{display:flex;justify-content:space-between;font-size:.85rem;color:#5a6a6c;margin:10px 0 0;min-height:1.2em}
.coacts{display:flex;border-top:1px solid var(--line);border-bottom:1px solid var(--line);margin:8px 0 0}
.coacts button{flex:1;background:none;border:0;padding:8px 0;font:inherit;font-weight:600;color:#5a6a6c;cursor:pointer;border-radius:8px;display:flex;align-items:center;justify-content:center;gap:6px}
.coacts button:hover{background:#f1f4f4}.coacts button.on{color:var(--teal)}.coacts button.on svg{fill:var(--teal)}
.cocoms{margin:10px 0 0}.cocom{display:flex;gap:8px;margin:0 0 8px}.cobub{background:#f1f4f4;border-radius:14px;padding:7px 12px;max-width:100%;overflow-wrap:anywhere}
.cobub .nm{font-weight:700;font-size:.85rem}.cobub div{white-space:pre-line;font-size:.92rem}.cocm{font-size:.75rem;color:#5a6a6c;margin:2px 0 0 12px}
.cocm button{background:none;border:0;padding:0;font:inherit;color:#5a6a6c;cursor:pointer;text-decoration:underline}
.coform{display:flex;gap:8px;align-items:flex-end}.coform textarea{flex:1;resize:none;border:1px solid var(--line);border-radius:18px;padding:8px 14px;font:inherit;min-height:38px;box-sizing:border-box;background:#f7f9f9}
.cosend{background:var(--teal);color:#fff;border:0;border-radius:99px;padding:8px 14px;font:inherit;font-weight:600;cursor:pointer}
.comenu{position:relative}.comenu summary{list-style:none;cursor:pointer;padding:2px 8px;border-radius:8px;color:#5a6a6c;font-size:1.3rem;line-height:1}.comenu summary::-webkit-details-marker{display:none}
.comenu summary:hover{background:#f1f4f4}.comenu .dd{position:absolute;right:0;top:100%;z-index:5;background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.12);min-width:150px;padding:4px}
.comenu .dd a,.comenu .dd button{display:block;width:100%;text-align:left;background:none;border:0;padding:8px 12px;font:inherit;font-size:.9rem;color:inherit;text-decoration:none;cursor:pointer;border-radius:6px}.comenu .dd a:hover,.comenu .dd button:hover{background:#f1f4f4}
.comp textarea{width:100%;box-sizing:border-box;border:0;resize:vertical;font:inherit;font-size:1.02rem;min-height:70px;padding:8px 0;outline:none}
.comp .bar{display:flex;align-items:center;gap:10px;border-top:1px solid var(--line);padding-top:10px;flex-wrap:wrap}
.cophoto{display:inline-flex;align-items:center;gap:6px;cursor:pointer;color:#2e8b57;font-weight:600;padding:6px 10px;border-radius:8px}.cophoto:hover{background:#f1f4f4}.cophoto input{display:none}
#cprev{display:none;position:relative;margin:8px 0}#cprev img{max-width:100%;max-height:260px;border-radius:10px;display:block}
#cprev button{position:absolute;top:6px;right:6px;background:rgba(0,0,0,.6);color:#fff;border:0;border-radius:50%;width:28px;height:28px;cursor:pointer}
.conote{font-size:.78rem;color:#5a6a6c;margin:8px 0 0}
.comore{display:block;font-size:.85rem;color:#5a6a6c;background:none;border:0;padding:0 0 8px;cursor:pointer;font-weight:600}
</style>
<div class="cofeed">
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>

<?php if (!$before): ?>
  <form class="cocard comp" method="post" enctype="multipart/form-data">
    <?= $csrfIn ?><input type="hidden" name="a" value="post">
    <div class="cohead"><?= co_av($mePerson, 42) ?><div class="who"><div class="nm"><?= htmlspecialchars($me['name']) ?></div></div></div>
    <textarea name="body" id="cbody" placeholder="Share something with the team, <?= htmlspecialchars(explode(' ', $me['name'])[0]) ?>..." maxlength="<?= CO_MAX_BODY ?>"></textarea>
    <div id="cprev"><img alt=""><button type="button" aria-label="Remove photo">&times;</button></div>
    <div class="bar">
      <label class="cophoto"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>Photo<input type="file" name="photo" id="cfile" accept="image/*"></label>
      <span style="flex:1"></span>
      <button class="btn" type="submit" style="border:0;cursor:pointer;font:inherit;font-weight:600">Post</button>
    </div>
    <p class="conote">Seen by every FLR agent and staff member. Please don't post clients' personal information.</p>
  </form>
<?php else: ?>
  <p><a href="collab.php">&larr; Back to the newest posts</a></p>
<?php endif; ?>

<?php if (!$posts): ?>
  <div class="cocard" style="text-align:center;color:#5a6a6c"><?= $before ? 'No older posts.' : 'No posts yet. Be the first to share a win, a question, or a listing.' ?></div>
<?php endif; ?>

<?php foreach ($posts as $p):
    $au = om_person($p['author_key'], $p['author_name']); $mine = $p['author_email'] === $me['email'];
    $canDel = $mine || $me['staff']; $nc = count($p['comments']); ?>
  <article class="cocard" id="p<?= (int)$p['id'] ?>">
    <?php if ($p['pinned']): ?><p class="copin">&#128204; Pinned by the office</p><?php endif; ?>
    <div class="cohead">
      <?= co_av($au, 42) ?>
      <div class="who"><div class="nm"><?= htmlspecialchars($p['author_name'] ?: $p['author_email']) ?><?= $p['author_staff'] ? '<span class="cobadge">Staff</span>' : '' ?></div>
        <div class="meta" title="<?= htmlspecialchars(date('l, M j, Y g:ia', strtotime($p['created']))) ?>"><?= htmlspecialchars(co_ago($p['created'])) ?><?= $p['edited'] ? ' &middot; edited' : '' ?></div></div>
<?php if ($mine || $me['staff']): ?>
      <details class="comenu"><summary aria-label="Post options">&hellip;</summary><div class="dd">
        <?php if ($mine): ?><a href="collab.php?edit=<?= (int)$p['id'] ?>#p<?= (int)$p['id'] ?>">Edit post</a><?php endif; ?>
        <?php if ($me['staff']): ?><form method="post"><?= $csrfIn ?><input type="hidden" name="a" value="<?= $p['pinned'] ? 'unpin' : 'pin' ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button type="submit"><?= $p['pinned'] ? 'Unpin' : 'Pin to top' ?></button></form><?php endif; ?>
        <?php if ($canDel): ?><form method="post" onsubmit="return confirm('Delete this post<?= $mine ? '' : ' for everyone' ?>?');"><?= $csrfIn ?><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button type="submit" style="color:#c0392b"><?= $mine ? 'Delete post' : 'Remove post' ?></button></form><?php endif; ?>
      </div></details>
<?php endif; ?>
    </div>
<?php if ($editId === (int)$p['id'] && $mine): ?>
    <form method="post" style="margin-top:10px"><?= $csrfIn ?><input type="hidden" name="a" value="edit"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <textarea name="body" rows="4" maxlength="<?= CO_MAX_BODY ?>" style="width:100%;box-sizing:border-box;border:1px solid var(--line);border-radius:10px;padding:10px;font:inherit"><?= htmlspecialchars($p['body']) ?></textarea>
      <p style="margin:8px 0 0"><button class="btn sm" type="submit" style="border:0;cursor:pointer;font:inherit;font-weight:600;padding:6px 14px">Save</button> <a href="collab.php#p<?= (int)$p['id'] ?>" style="margin-left:8px;font-size:.9rem">Cancel</a></p>
    </form>
<?php elseif ($p['body'] !== ''): ?>
    <div class="cobody"><?= co_text($p['body']) ?></div>
<?php endif; ?>
<?php if ($p['image'] !== ''): $src = 'collab_img.php?f=' . rawurlencode($p['image']); ?>
    <a class="coimg" href="<?= htmlspecialchars($src) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars($src) ?>" alt="Photo shared by <?= htmlspecialchars($p['author_name']) ?>" loading="lazy"></a>
<?php endif; ?>
    <div class="cocount"><span class="likers" title="<?= htmlspecialchars(implode(', ', array_column($p['likes'], 'name'))) ?>"><?= $p['likes'] ? '&#128077; ' . htmlspecialchars(co_likers($p, $me['email'])) : '' ?></span><span><?= $nc ? $nc . ' comment' . ($nc === 1 ? '' : 's') : '' ?></span></div>
    <div class="coacts">
      <button type="button" class="colike<?= $p['liked'] ? ' on' : '' ?>" data-id="<?= (int)$p['id'] ?>" aria-pressed="<?= $p['liked'] ? 'true' : 'false' ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v11H3V10zM7 10l4-8a3 3 0 0 1 3 3v4h6a2 2 0 0 1 2 2.3l-1.4 8A2 2 0 0 1 18.6 21H7"/></svg>Like</button>
      <button type="button" class="cocmt" data-id="<?= (int)$p['id'] ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12z"/></svg>Comment</button>
    </div>
    <div class="cocoms">
<?php $hide = max(0, $nc - 3); if ($hide): ?>      <button type="button" class="comore" data-n="<?= $hide ?>">View <?= $hide ?> more comment<?= $hide === 1 ? '' : 's' ?></button>
<?php endif; foreach ($p['comments'] as $i => $c): $ca = om_person($c['author_key'], $c['author_name']); ?>
      <div class="cocom"<?= $i < $hide ? ' hidden' : '' ?>><?= co_av($ca, 32) ?><div><div class="cobub"><span class="nm"><?= htmlspecialchars($c['author_name'] ?: $c['author_email']) ?></span><?= $c['author_staff'] ? '<span class="cobadge">Staff</span>' : '' ?><div><?= co_text($c['body']) ?></div></div>
        <div class="cocm"><?= htmlspecialchars(co_ago($c['created'])) ?><?php if ($c['author_email'] === $me['email'] || $me['staff']): ?> &middot; <form method="post" style="display:inline" onsubmit="return confirm('Delete this comment?');"><?= $csrfIn ?><input type="hidden" name="a" value="delcomment"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button type="submit">Delete</button></form><?php endif; ?></div></div></div>
<?php endforeach; ?>
      <form class="coform" method="post"><?= $csrfIn ?><input type="hidden" name="a" value="comment"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <?= co_av($mePerson, 32) ?><textarea name="body" rows="1" placeholder="Write a comment..." maxlength="<?= CO_MAX_COMMENT ?>" required></textarea><button class="cosend" type="submit">Send</button></form>
    </div>
  </article>
<?php endforeach; ?>
<?php if ($more && $posts): ?><p style="text-align:center"><a class="btn ghost" href="collab.php?before=<?= (int)end($posts)['id'] ?>">Older posts</a></p><?php endif; ?>
</div>
<script>
(function(){
var CSRF=<?= json_encode($_SESSION['csrf']) ?>,ME=<?= json_encode($me['email']) ?>;
// Like without reloading.
document.addEventListener("click",function(e){var b=e.target.closest(".colike");if(!b)return;b.disabled=true;
  fetch("collab_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:CSRF,like:+b.dataset.id})})
  .then(function(r){return r.json();}).then(function(j){if(!j.ok)throw 0;b.classList.toggle("on",j.liked);b.setAttribute("aria-pressed",j.liked?"true":"false");
    var s=b.closest(".cocard").querySelector(".likers"),n=j.count;
    s.textContent=n?"👍 "+(j.liked?(n===1?"You":"You and "+(n-1)+" other"+(n-1===1?"":"s")):(n+(n===1?" like":" likes"))):"";})
  .catch(function(){alert("Couldn't save that like. Please try again.");}).then(function(){b.disabled=false;});});
// Comment button jumps to the comment box; "View more" shows earlier comments.
document.addEventListener("click",function(e){var b=e.target.closest(".cocmt");if(b){var t=b.closest(".cocard").querySelector(".coform textarea");t.focus();t.scrollIntoView({block:"center",behavior:"smooth"});}
  var m=e.target.closest(".comore");if(m){m.parentNode.querySelectorAll(".cocom[hidden]").forEach(function(c){c.hidden=false;});m.remove();}});
// Comment boxes grow as you type; Enter sends, Shift+Enter makes a new line.
document.querySelectorAll(".coform textarea").forEach(function(t){t.addEventListener("input",function(){t.style.height="auto";t.style.height=Math.min(t.scrollHeight,160)+"px";});
  t.addEventListener("keydown",function(e){if(e.key==="Enter"&&!e.shiftKey&&!e.isComposing){e.preventDefault();if(t.value.trim())t.form.submit();}});});
// Photo preview on the new-post form.
var f=document.getElementById("cfile"),pv=document.getElementById("cprev");
if(f){f.addEventListener("change",function(){var x=f.files[0];if(!x){pv.style.display="none";return;}
  if(x.size>15000000){alert("That photo is too large (15 MB max).");f.value="";return;}
  pv.querySelector("img").src=URL.createObjectURL(x);pv.style.display="block";});
  pv.querySelector("button").addEventListener("click",function(){f.value="";pv.style.display="none";});}
// Close an open post menu when tapping elsewhere.
document.addEventListener("click",function(e){document.querySelectorAll(".comenu[open]").forEach(function(d){if(!d.contains(e.target))d.removeAttribute("open");});});
})();
</script>
<?php sp_foot();
