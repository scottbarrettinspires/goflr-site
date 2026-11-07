<?php
// "Upcoming Open Houses" card on Home (under the celebrations card): the next few open houses, two across, with a
// small "+ Post" button in the corner. Full list and the post form live on openhouses.php.
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'oh_panel.php') { http_response_code(404); exit; } // include only
require_once __DIR__ . '/ohlib.php';
$ohAll = []; try { $ohAll = oh_upcoming(); } catch (Throwable $e) {}
$ohShow = array_slice($ohAll, 0, 4);
echo oh_css();
?>
  <div class="panel sigbox ohcard" id="ohcard">
    <div class="ohhead"><h2>&#127969; Upcoming Open Houses &#127880;</h2><a class="ohpost" href="openhouses.php?new=1&amp;back=home" title="Post your open house">+ Post</a></div>
<?php if ($ohShow): ?>
    <div class="ohgrid"><?php foreach ($ohShow as $o) echo oh_tile($o); ?></div>
<?php if (count($ohAll) > count($ohShow)): ?>    <p style="margin:10px 0 0;text-align:right"><a href="openhouses.php" style="font-weight:600">See all <?= count($ohAll) ?> &rarr;</a></p>
<?php endif; else: ?>
    <p style="color:#5a6a6c;margin:2px 0 0">No open houses posted yet. Have one coming up? Tap <strong>+ Post</strong> to share it with everyone.</p>
<?php endif; ?>
  </div>
<style>.ohcard{border-left:5px solid #e07b39;margin-bottom:16px;padding:16px 20px}
.ohhead{display:flex;align-items:center;gap:10px;margin:0 0 10px}.ohhead h2{margin:0;font-size:1.12rem;color:#a4501b;flex:1}
.ohpost{flex:0 0 auto;background:#e07b39;color:#fff;font-weight:700;font-size:.8rem;padding:5px 12px;border-radius:99px;text-decoration:none}.ohpost:hover{background:#c96a2c}</style>
