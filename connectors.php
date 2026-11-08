<?php
// Connectors (Settings > Connectors): connect the back office to your own Google and social media accounts.
// Each connection needs the brokerage's app approved by that service first (Google, Meta, LinkedIn, X, TikTok).
// Until a service is switched live in CN_LIVE, turning it on saves your choice and shows "waiting for setup", so
// the office knows who wants what and everyone is connected the moment it goes live. Nothing is faked as connected.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');

// key => [name, what it's used for, group]
const CN_LIST = [
    'gmail'    => ['Gmail', 'Send and track client emails from the back office.', 'Google'],
    'gcal'     => ['Google Calendar', 'Put showings, deadlines and closings on your calendar.', 'Google'],
    'gdrive'   => ['Google Drive', 'Save forms, signed documents and marketing to your Drive.', 'Google'],
    'gbp'      => ['Google Business Profile', 'Post listings and updates to your Google Business Profile.', 'Google'],
    'facebook' => ['Facebook Business Page', 'Share listings, open houses and closings to your Page.', 'Social media'],
    'instagram'=> ['Instagram', 'Post listing photos and reels to your professional account.', 'Social media'],
    'linkedin' => ['LinkedIn', 'Share wins and market updates with your network.', 'Social media'],
    'x'        => ['X (Twitter)', 'Post listings and quick market updates.', 'Social media'],
    'tiktok'   => ['TikTok', 'Share listing videos and tours.', 'Social media'],
];
// Services whose connection is set up and working. Empty until the office finishes each one.
const CN_LIVE = [];

$pdo = flr_db();
$pdo->exec('CREATE TABLE IF NOT EXISTS connectors (email TEXT NOT NULL, provider TEXT NOT NULL, on_flag INTEGER DEFAULT 0, updated TEXT, PRIMARY KEY (email, provider))');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $j = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
    $ok = hash_equals($_SESSION['csrf'], (string)($j['csrf'] ?? '')) && isset(CN_LIST[$j['p'] ?? '']);
    if ($ok) {
        $on = !empty($j['on']) ? 1 : 0;
        $pdo->prepare('INSERT INTO connectors (email, provider, on_flag, updated) VALUES (?,?,?,?) ON CONFLICT(email, provider) DO UPDATE SET on_flag = excluded.on_flag, updated = excluded.updated')->execute([$me, $j['p'], $on, date('c')]);
        flr_audit(flr_akey($me), 'connector', $j['p'], $on ? 'Turned on' : 'Turned off');
    }
    header('Content-Type: application/json'); echo json_encode(['ok' => $ok]); exit;
}
$state = []; $q = $pdo->prepare('SELECT provider, on_flag FROM connectors WHERE email = ?'); $q->execute([$me]); foreach ($q->fetchAll() as $r) $state[$r['provider']] = (int)$r['on_flag'];
$icon = function ($k) {
    $p = ['gmail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
          'gcal' => '<rect x="3" y="4.5" width="18" height="17" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
          'gdrive' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
          'gbp' => '<path d="M4 10h16l-1.5-5h-13zM5 10v10h14V10M9 20v-5h6v5"/>'][$k] ?? '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>';
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
};
sp_head('Connectors');
?>
<style>
.cnw{max-width:760px}
.cnintro{color:#455355;margin:0 0 16px}
.cng{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:6px 18px;margin:0 0 16px}
.cng h2{font-size:.8rem;letter-spacing:.06em;text-transform:uppercase;color:#5a6a6c;margin:12px 0 4px}
.cnr{display:flex;gap:14px;align-items:center;padding:14px 0;border-top:1px solid var(--line)}.cng h2+.cnr{border-top:0}
.cnr .ic{flex:0 0 42px;height:42px;border-radius:11px;background:var(--teal-soft);color:var(--teal);display:flex;align-items:center;justify-content:center}
.cnr .tx{flex:1;min-width:0}.cnr .nm{font-weight:700}.cnr .ds{font-size:.88rem;color:#5a6a6c}
.cnst{font-size:.8rem;font-weight:600;margin-top:3px}.cnst.wait{color:#8a5a0e}.cnst.live{color:#2e7d32}.cnst.off{color:#8a9a9c}
.sw{position:relative;flex:0 0 52px;width:52px;height:30px;border-radius:99px;border:0;background:#cfd8dc;cursor:pointer;transition:background .15s;padding:0}
.sw::after{content:"";position:absolute;top:3px;left:3px;width:24px;height:24px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:transform .15s}
.sw[aria-checked="true"]{background:var(--teal)}.sw[aria-checked="true"]::after{transform:translateX(22px)}
.sw:focus-visible{outline:3px solid rgba(3,111,114,.35);outline-offset:2px}.sw:disabled{opacity:.6;cursor:default}
</style>
<div class="cnw">
  <p class="cnintro">Connect the back office to your own accounts. Turn on the ones you use. As each connection goes live, you'll sign in to that service once and approve access. You can turn any of them off at any time.</p>
<?php $g = ''; foreach (CN_LIST as $k => list($nm, $ds, $grp)):
    if ($grp !== $g): if ($g !== '') echo "  </div>\n"; $g = $grp; ?>
  <div class="cng"><h2><?= htmlspecialchars($grp) ?></h2>
<?php endif; $on = !empty($state[$k]); $live = in_array($k, CN_LIVE, true); ?>
    <div class="cnr">
      <span class="ic"><?= $icon($k) ?></span>
      <div class="tx"><div class="nm"><?= htmlspecialchars($nm) ?></div><div class="ds"><?= htmlspecialchars($ds) ?></div>
        <div class="cnst <?= $on ? ($live ? 'live' : 'wait') : 'off' ?>" data-st><?= $on ? ($live ? 'Connected' : 'On &middot; waiting for the office to finish setup') : 'Off' ?></div></div>
      <button type="button" class="sw" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="<?= htmlspecialchars($nm) ?>" data-p="<?= $k ?>" data-live="<?= $live ? 1 : 0 ?>"></button>
    </div>
<?php endforeach; ?>
  </div>
  <p style="font-size:.85rem;color:#5a6a6c">The back office only uses what you connect for the features described above, and never posts or sends anything without you choosing to.</p>
</div>
<script>
(function(){var CSRF=<?= json_encode($_SESSION['csrf']) ?>;
document.querySelectorAll(".sw").forEach(function(b){b.addEventListener("click",function(){var on=b.getAttribute("aria-checked")!=="true",st=b.closest(".cnr").querySelector("[data-st]");
  b.disabled=true;b.setAttribute("aria-checked",on?"true":"false");
  fetch("connectors.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:CSRF,p:b.dataset.p,on:on})})
  .then(function(r){return r.json();}).then(function(j){if(!j.ok)throw 0;st.className="cnst "+(on?(b.dataset.live==="1"?"live":"wait"):"off");st.innerHTML=on?(b.dataset.live==="1"?"Connected":"On &middot; waiting for the office to finish setup"):"Off";})
  .catch(function(){b.setAttribute("aria-checked",on?"false":"true");alert("Couldn't save that. Please try again.");}).then(function(){b.disabled=false;});});});
})();
</script>
<?php sp_foot();
