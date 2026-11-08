<?php
// "Let's Celebrate With Our Agent Family!" card on Home (staff and agents): upcoming birthdays and work anniversaries
// (next 14 days, Active agents, from the server Agent Records table), two across, with headshots. Expects $agentDates.
if (!isset($agentDates)) { http_response_code(404); exit; }
require_once __DIR__ . '/celeblib.php'; try { cb_run(); } catch (Throwable $e) {} // today's celebration posts go up on the first visit of the day
?>
<?php if ($agentDates): ?>
  <div class="panel sigbox celeb">
    <h2>&#127881; Let's Celebrate With Our Agent Family! &#127881;</h2>
    <div class="celgrid">
<?php foreach ($agentDates as $D):
        $yrs = (int)($D['years'] ?? 0);
        $what = $D['type'] === 'anniversary' ? '&#127882; ' . $yrs . ' year' . ($yrs === 1 ? '' : 's') . ' with FLR' : '&#127874; Birthday';
        $who = om_person('agent:' . ($D['agent_id'] ?? ''), (string)($D['name'] ?? ''));
        $day = !empty($D['date']) ? date('l, F j', strtotime($D['date'])) : ''; ?>
    <div class="celrow">
      <span class="celav"><span><?= htmlspecialchars($who['ini']) ?></span><?php if ($who['photo']): ?><img src="<?= htmlspecialchars($who['photo']) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></span>
      <div class="celtx"><strong><?= htmlspecialchars($D['name'] ?? '') ?></strong><span class="celwhat"><?= $what ?></span><span class="celday"><?= htmlspecialchars($day) ?></span></div>
<?php $cpid = cb_post_id($D); if ($cpid): ?>      <a class="celpost" href="collab.php?post=<?= $cpid ?>" title="See the post in the Collaboration Lounge">&#127881; See post</a>
<?php endif; ?>
    </div>
<?php endforeach; ?>
    </div>
  </div>
<style>.celeb{border-left:5px solid #7a4fc9;margin-bottom:16px;padding:16px 20px}.celeb h2{margin:0 0 6px;font-size:1.12rem;color:#5b3aa0}
.celgrid{display:grid;grid-template-columns:1fr 1fr;column-gap:20px}
.celrow{display:flex;gap:12px;align-items:center;padding:9px 0;border-top:1px solid var(--line)}
@media(max-width:600px){.celgrid{grid-template-columns:1fr}}
.celav{position:relative;flex:0 0 48px;width:48px;height:48px;border-radius:50%;overflow:hidden;background:#ece3fb;color:#5b3aa0;font-weight:700;display:flex;align-items:center;justify-content:center}
.celav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.celtx{display:flex;flex-direction:column;line-height:1.35;flex:1;min-width:0}
.celpost{flex:0 0 auto;font-size:.78rem;font-weight:700;color:#5b3aa0;background:#ece3fb;border-radius:99px;padding:5px 10px;text-decoration:none;white-space:nowrap}.celpost:hover{background:#dfd0f8}.celwhat{font-size:.92rem}.celday{font-size:.85rem;color:#5a6a6c}</style>
<?php endif; ?>
