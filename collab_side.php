<?php
// The Collaboration side lists -- "Recent posts" (3 newest) and "Most active" (see co_active) -- with thumbnails that
// open the post on the Collaboration page. Shared by collab.php (left column) and the agent Home page (right column).
// Optional before including: $sideCurrent (post id to highlight). Never breaks the page it's on: any error = no lists.
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'collab_side.php') { http_response_code(404); exit; } // include only, never a page
if (!function_exists('co_db')) require_once __DIR__ . '/collablib.php';
try { $sideRecent = co_recent(3); $sideActive = co_active(3, array_column($sideRecent, 'id')); }
catch (Throwable $e) { $sideRecent = []; $sideActive = []; }
$sideCurrent = (int)($sideCurrent ?? 0);
$sideSeeAll = basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'collab.php'; // on Home: a "See All" link beside the Recent posts title
?>
<style>
.cav{position:relative;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;overflow:hidden;background:var(--teal-soft);color:var(--teal);font-weight:700;flex:0 0 auto}
.cav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.coside{position:sticky;top:14px;max-height:calc(100vh - 28px);overflow:auto}
.coside h3{font-size:.8rem;letter-spacing:.06em;text-transform:uppercase;color:#5a6a6c;margin:0 0 8px;display:flex;align-items:baseline;gap:8px}
.coside h3 .seeall{margin-left:auto;text-transform:none;letter-spacing:0;font-size:.82rem;font-weight:600;color:var(--teal);text-decoration:none;white-space:nowrap}.coside h3 .seeall:hover{text-decoration:underline}
.cotitle{font-size:1.15rem;margin:0 0 10px}.cotitle a{color:#1f2a2c;text-decoration:none}.cotitle a:hover{color:var(--teal)}
.coside .grp{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:12px;margin:0 0 14px}
.coside .all{display:block;text-align:center;font-size:.85rem;font-weight:600;color:var(--teal);text-decoration:none;padding:6px 0 0}
.coitem{display:flex;gap:10px;align-items:center;padding:8px;border-radius:10px;text-decoration:none;color:inherit}
.coitem:hover{background:#f1f4f4}.coitem.on{background:var(--teal-soft)}
.coth{flex:0 0 52px;width:52px;height:52px;border-radius:10px;overflow:hidden;background:#f1f4f4}.coth img{width:100%;height:100%;object-fit:cover;display:block}
.cotx{min-width:0;display:flex;flex-direction:column;line-height:1.3}.cotx .nm{font-weight:700;font-size:.85rem}
.cotx .pv{font-size:.85rem;color:#2a3638;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.cotx .mt{font-size:.75rem;color:#5a6a6c;margin-top:2px}
@media(max-width:900px){
  .coside{position:static;max-height:none;overflow:visible;margin:0 -20px}
  .coside .grp{border:0;background:none;padding:0 20px;margin:0 0 12px}
  .coside .cotitle{padding:0 20px}
  .coside .list{display:flex;gap:10px;overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:4px}
  .coside .coitem{flex:0 0 76%;max-width:300px;background:var(--white);border:1px solid var(--line);scroll-snap-align:start}
}
</style>
<aside class="coside" aria-label="Collaboration: recent and most active posts">
<?php if ($sideSeeAll): ?>  <h2 class="cotitle"><a href="collab.php">Collaboration Lounge</a></h2>
<?php endif; ?><?php if ($sideRecent): ?>
  <div class="grp"><h3>Recent posts<?php if ($sideSeeAll): ?><a class="seeall" href="collab.php">See All &rarr;</a><?php endif; ?></h3><div class="list"><?php foreach ($sideRecent as $r) echo co_side_item($r, $sideCurrent === (int)$r['id']); ?></div></div>
<?php endif; if ($sideActive): ?>
  <div class="grp"><h3>Most active</h3><div class="list"><?php foreach ($sideActive as $r) echo co_side_item($r, $sideCurrent === (int)$r['id']); ?></div></div>
<?php endif; if (!$sideRecent): ?>
  <div class="grp"><h3>Recent posts</h3><p style="font-size:.85rem;color:#5a6a6c;margin:0 0 4px">New posts from the team will show here.</p><a class="all" href="collab.php">Share the first post &rarr;</a></div>
<?php endif; ?>
</aside>
