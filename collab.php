<?php
// Collaboration: FLR's private group feed (see collablib.php). Posts with an optional photo, reactions, comments.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php"; // flr_is_broker()
require_once __DIR__ . "/collablib.php";
require_once __DIR__ . "/celeblib.php"; try { cb_run(); } catch (Throwable $e) {} // birthday / workiversary posts for today
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
        $single = (int)($_POST['single'] ?? 0);
        if ($single && $a !== 'delete' && strpos($go, '#') === 0) $go = '?post=' . $single . $go;
        if ($err === '') { header('Location: collab.php' . $go); exit; }
    }
}
// An upload bigger than the server's limit arrives with no form fields at all.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) $err = 'That photo is too large. Please choose a smaller one.';

$before = (int)($_GET['before'] ?? 0);
$single = (int)($_GET['post'] ?? 0); // one post on its own page, opened from the side lists
if ($single) { $sp = co_single($single); $posts = $sp ? [$sp] : []; $more = false; }
else { list($posts, $more) = co_feed(20, $before); }
co_mark_seen($me['email']);
$editId = (int)($_GET['edit'] ?? 0);
$mePerson = om_person($me['key'], $me['name']);
$csrfIn = '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf']) . '">' . ($single ? '<input type="hidden" name="single" value="' . $single . '">' : '');

// Reaction summary under a post: top 3 reaction icons + "You and 4 others"; tap to see who reacted with what.
// collab.php's JavaScript redraws the same markup after a reaction (keep the two in step).
function co_react_summary($p, $myEmail) {
    $counts = []; $people = [];
    foreach ($p['likes'] as $l) { $k = co_kind($l['kind']); $counts[$k] = ($counts[$k] ?? 0) + 1; $people[] = [$l['email'] === $myEmail ? 'You' : $l['name'], $k]; }
    $n = count($people); if (!$n) return '';
    arsort($counts); $ems = ''; foreach (array_slice(array_keys($counts), 0, 3) as $k) $ems .= CO_REACTIONS[$k][0];
    $first = $p['mine'] !== '' ? 'You' : $people[0][0];
    $txt = $n === 1 ? $first : $first . ' and ' . ($n - 1) . ' other' . ($n - 1 === 1 ? '' : 's');
    $who = ''; foreach ($people as $w) $who .= '<li><span>' . CO_REACTIONS[$w[1]][0] . '</span>' . htmlspecialchars($w[0]) . '</li>';
    return '<button type="button" class="cosum" aria-expanded="false"><span class="ems">' . $ems . '</span> ' . htmlspecialchars($txt) . '</button><ul class="cowho" hidden>' . $who . '</ul>';
}
// The reaction button (shows your reaction if you have one) and its pop-up picker.
function co_react_button($p) {
    $m = $p['mine']; $r = $m !== '' ? CO_REACTIONS[$m] : null;
    $face = $r ? '<span class="em">' . $r[0] . '</span>' . $r[1]
        : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v11H3V10zM7 10l4-8a3 3 0 0 1 3 3v4h6a2 2 0 0 1 2 2.3l-1.4 8A2 2 0 0 1 18.6 21H7"/></svg>Like';
    $pick = ''; foreach (CO_REACTIONS as $k => $x) $pick .= '<button type="button" data-k="' . $k . '" title="' . $x[1] . '" aria-label="' . $x[1] . '"><span>' . $x[0] . '</span><em>' . $x[1] . '</em></button>';
    return '<div class="colikew"><button type="button" class="colike' . ($r ? ' on' : '') . '" data-id="' . (int)$p['id'] . '" data-mine="' . $m . '"' . ($r ? ' style="color:' . $r[2] . '"' : '')
        . ' aria-haspopup="true" title="Tap to like. Hold (or hover) for more reactions.">' . $face . '</button><div class="copick" role="group" aria-label="Reactions" hidden>' . $pick . '</div></div>';
}

sp_head('Collaboration Lounge');
?>
<style>
.colayout{display:grid;grid-template-columns:270px minmax(0,680px);gap:22px;justify-content:center;align-items:start}
.cofeed{min-width:0}
@media(max-width:900px){.colayout{grid-template-columns:minmax(0,1fr)}}
.cocard{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:14px 16px;margin:0 0 14px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.cohead{display:flex;gap:10px;align-items:center}.cohead .who{flex:1;min-width:0;line-height:1.3}.cohead .nm{font-weight:700}.cohead .meta{font-size:.8rem;color:#5a6a6c}
.cobadge{display:inline-block;font-size:.68rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;background:var(--teal-soft);color:var(--teal);border-radius:99px;padding:1px 7px;margin-left:6px;vertical-align:middle}
.copin{font-size:.78rem;font-weight:600;color:#b7791f;margin:0 0 8px}
.cobody{margin:10px 0 0;white-space:pre-line;overflow-wrap:anywhere;line-height:1.5}.cobody a{color:var(--teal)}
.coimg{display:block;margin:10px -16px 0;background:#f1f4f4}.coimg img{display:block;width:100%;max-height:640px;object-fit:contain}
.cocount{display:flex;justify-content:space-between;font-size:.85rem;color:#5a6a6c;margin:10px 0 0;min-height:1.2em}
.coacts{display:flex;border-top:1px solid var(--line);border-bottom:1px solid var(--line);margin:8px 0 0}
.coacts>button,.colikew{flex:1}.coacts button.colike,.coacts>button{width:100%;background:none;border:0;padding:8px 0;font:inherit;font-weight:600;color:#5a6a6c;cursor:pointer;border-radius:8px;display:flex;align-items:center;justify-content:center;gap:6px}
.coacts button.colike:hover,.coacts>button:hover{background:#f1f4f4}
.colike,.copick button{-webkit-user-select:none;user-select:none;-webkit-touch-callout:none;touch-action:manipulation;-webkit-tap-highlight-color:transparent}.colike .em{font-size:1.15rem;line-height:1}
.colikew{position:relative}
.copick{position:absolute;bottom:calc(100% + 6px);left:0;z-index:6;display:flex;gap:2px;background:#fff;border-radius:99px;padding:5px 6px;box-shadow:0 6px 24px rgba(0,0,0,.18);animation:copop .14s ease-out}
.copick[hidden]{display:none}
@keyframes copop{from{opacity:0;transform:translateY(6px) scale(.9)}to{opacity:1;transform:none}}
.copick button{position:relative;background:none;border:0;padding:2px;cursor:pointer;border-radius:50%;transition:transform .12s}
.copick button span{display:block;font-size:1.9rem;line-height:1.15}
.copick button:hover,.copick button:focus-visible{transform:translateY(-6px) scale(1.25);outline:none}
.copick button em{position:absolute;bottom:calc(100% + 4px);left:50%;transform:translateX(-50%);background:rgba(0,0,0,.75);color:#fff;font:600 .7rem/1 system-ui,sans-serif;font-style:normal;padding:4px 7px;border-radius:99px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .1s}
.copick button:hover em,.copick button:focus-visible em{opacity:1}
.cosum{background:none;border:0;padding:0;font:inherit;color:inherit;cursor:pointer;-webkit-tap-highlight-color:transparent;-webkit-user-select:none;user-select:none}.cosum:hover{text-decoration:underline}.cosum .ems{letter-spacing:-.15em;margin-right:.2em}
.cowho{position:absolute;z-index:6;list-style:none;margin:4px 0 0;padding:8px 12px;background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.12);max-height:240px;overflow:auto;min-width:180px}
.cowho li{display:flex;gap:8px;align-items:center;padding:3px 0;font-size:.88rem;color:#2a3638}.cowho li span{width:1.4em;text-align:center}
.cocount .likers{position:relative}
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
<div class="colayout">
<?php $sideCurrent = $single; require __DIR__ . "/collab_side.php"; ?>
<div class="cofeed">
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>

<?php if ($single): ?>
  <p style="margin:0 0 12px"><a href="collab.php">&larr; Back to all posts</a></p>
<?php elseif (!$before): ?>
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
  <div class="cocard" style="text-align:center;color:#5a6a6c"><?= $single ? 'That post is no longer available.' : ($before ? 'No older posts.' : 'No posts yet. Be the first to share a win, a question, or a listing.') ?></div>
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
        <?php if ($mine): ?><a href="collab.php?<?= $single ? 'post=' . $single . '&amp;' : '' ?>edit=<?= (int)$p['id'] ?>#p<?= (int)$p['id'] ?>">Edit post</a><?php endif; ?>
        <?php if ($me['staff']): ?><form method="post"><?= $csrfIn ?><input type="hidden" name="a" value="<?= $p['pinned'] ? 'unpin' : 'pin' ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button type="submit"><?= $p['pinned'] ? 'Unpin' : 'Pin to top' ?></button></form><?php endif; ?>
        <?php if ($canDel): ?><form method="post" onsubmit="return confirm('Delete this post<?= $mine ? '' : ' for everyone' ?>?');"><?= $csrfIn ?><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button type="submit" style="color:#c0392b"><?= $mine ? 'Delete post' : 'Remove post' ?></button></form><?php endif; ?>
      </div></details>
<?php endif; ?>
    </div>
<?php if ($editId === (int)$p['id'] && $mine): ?>
    <form method="post" style="margin-top:10px"><?= $csrfIn ?><input type="hidden" name="a" value="edit"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <textarea name="body" rows="4" maxlength="<?= CO_MAX_BODY ?>" style="width:100%;box-sizing:border-box;border:1px solid var(--line);border-radius:10px;padding:10px;font:inherit"><?= htmlspecialchars($p['body']) ?></textarea>
      <p style="margin:8px 0 0"><button class="btn sm" type="submit" style="border:0;cursor:pointer;font:inherit;font-weight:600;padding:6px 14px">Save</button> <a href="collab.php<?= $single ? '?post=' . $single : '' ?>#p<?= (int)$p['id'] ?>" style="margin-left:8px;font-size:.9rem">Cancel</a></p>
    </form>
<?php elseif ($p['body'] !== ''): ?>
    <div class="cobody"><?= co_text($p['body']) ?></div>
<?php endif; ?>
<?php if ($p['image'] !== ''): $src = 'collab_img.php?f=' . rawurlencode($p['image']); ?>
    <a class="coimg" href="<?= htmlspecialchars($src) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars($src) ?>" alt="Photo shared by <?= htmlspecialchars($p['author_name']) ?>" loading="lazy"></a>
<?php endif; ?>
    <div class="cocount"><span class="likers"><?= co_react_summary($p, $me['email']) ?></span><span><?= $nc ? $nc . ' comment' . ($nc === 1 ? '' : 's') : '' ?></span></div>
    <div class="coacts">
      <?= co_react_button($p) ?>
      <button type="button" class="cocmt" data-id="<?= (int)$p['id'] ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12z"/></svg>Comment</button>
    </div>
    <div class="cocoms">
<?php $hide = $single ? 0 : max(0, $nc - 3); if ($hide): ?>      <button type="button" class="comore" data-n="<?= $hide ?>">View <?= $hide ?> more comment<?= $hide === 1 ? '' : 's' ?></button>
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
</div>
<script>
(function(){
var CSRF=<?= json_encode($_SESSION['csrf']) ?>,ME=<?= json_encode($me['email']) ?>;
// Reactions. Quick tap on the button: Like (or remove your reaction). Hover (mouse) or press-and-hold (touch):
// the picker opens; choosing a reaction sets it, choosing the one you have removes it. Saved without a reload.
var R=<?= json_encode(array_map(function ($x) { return ['e' => $x[0], 'l' => $x[1], 'c' => $x[2]]; }, CO_REACTIONS), JSON_UNESCAPED_UNICODE) ?>;
var LIKE_SVG='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M7 10v11H3V10zM7 10l4-8a3 3 0 0 1 3 3v4h6a2 2 0 0 1 2 2.3l-1.4 8A2 2 0 0 1 18.6 21H7"/></svg>';
function esc(t){return String(t).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}
function draw(card,st){var b=card.querySelector(".colike"),m=st.mine;
  b.dataset.mine=m;b.classList.toggle("on",!!m);b.style.color=m?R[m].c:"";b.innerHTML=m?'<span class="em">'+R[m].e+'</span>'+R[m].l:LIKE_SVG+"Like";
  var s=card.querySelector(".likers"),n=st.total;if(!n){s.innerHTML="";return;}
  var ems=Object.keys(st.counts).slice(0,3).map(function(k){return R[k].e;}).join(""),first=m?"You":st.people[0].name;
  var who=st.people.map(function(p){return "<li><span>"+R[p.kind].e+"</span>"+esc(p.name)+"</li>";}).join("");
  s.innerHTML='<button type="button" class="cosum" aria-expanded="false"><span class="ems">'+ems+'</span> '+esc(n===1?first:first+" and "+(n-1)+" other"+(n-1===1?"":"s"))+'</button><ul class="cowho" hidden>'+who+'</ul>';}
function send(b,kind){var card=b.closest(".cocard");b.disabled=true;
  fetch("collab_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:CSRF,like:+b.dataset.id,kind:kind})})
  .then(function(r){return r.json();}).then(function(j){if(!j.ok)throw 0;draw(card,j);})
  .catch(function(){alert("Couldn't save that reaction. Please try again.");}).then(function(){b.disabled=false;});}
var openPick=null,hoverT=null,holdT=null,held=false;
function show(w){if(openPick&&openPick!==w)hide(openPick);w.querySelector(".copick").hidden=false;openPick=w;}
function hide(w){if(!w)return;w.querySelector(".copick").hidden=true;if(openPick===w)openPick=null;}
document.querySelectorAll(".colikew").forEach(function(w){var b=w.querySelector(".colike");
  w.addEventListener("pointerenter",function(e){if(e.pointerType!=="mouse")return;clearTimeout(hoverT);hoverT=setTimeout(function(){show(w);},450);});
  w.addEventListener("pointerleave",function(e){if(e.pointerType!=="mouse")return;clearTimeout(hoverT);hoverT=setTimeout(function(){hide(w);},350);});
  b.addEventListener("pointerdown",function(e){if(e.pointerType==="mouse")return;held=false;clearTimeout(holdT);holdT=setTimeout(function(){held=true;show(w);if(navigator.vibrate)navigator.vibrate(10);},420);});
  ["pointerup","pointercancel","pointerleave"].forEach(function(ev){b.addEventListener(ev,function(){clearTimeout(holdT);});});
  b.addEventListener("contextmenu",function(e){e.preventDefault();});
  b.addEventListener("click",function(){if(held){held=false;return;}clearTimeout(hoverT);hide(w);send(b,b.dataset.mine||"like");});
  b.addEventListener("keydown",function(e){if(e.key==="ArrowUp"){e.preventDefault();show(w);w.querySelector(".copick button").focus();}});
  w.querySelector(".copick").addEventListener("click",function(e){var k=e.target.closest("button[data-k]");if(!k)return;hide(w);send(b,k.dataset.k);});
  w.querySelector(".copick").addEventListener("keydown",function(e){if(e.key==="Escape"){hide(w);b.focus();}});});
document.addEventListener("pointerdown",function(e){if(openPick&&!openPick.contains(e.target))hide(openPick);});
// Who reacted: tap the summary.
document.addEventListener("click",function(e){var s=e.target.closest(".cosum");document.querySelectorAll(".cowho:not([hidden])").forEach(function(u){if(!s||u.previousElementSibling!==s){u.hidden=true;u.previousElementSibling.setAttribute("aria-expanded","false");}});
  if(s){var u=s.nextElementSibling;u.hidden=!u.hidden;s.setAttribute("aria-expanded",u.hidden?"false":"true");}});
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
