<?php
// Connectors (Settings > Connectors): connect the back office to your own Google and social media accounts.
// Turning one on shows the steps, then sends you to that service to sign in and approve; you come back connected,
// with the account shown. Turning it off disconnects it. Services the office hasn't set up yet save your request and
// say so (nothing is ever shown as connected unless it really is). Staff set up each service under "Office setup".
// Sign-in and token handling: oauthlib.php, connect.php, oauth_cb.php.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/oauthlib.php";
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
$isStaff = flr_is_broker();
$msg = ''; $err = (string)($_GET['err'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $j = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
    if (!hash_equals($_SESSION['csrf'], (string)($j['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    elseif (($j['a'] ?? '') === 'appsave' && $isStaff && isset(OA_APPS[$j['app'] ?? ''])) { // Office setup: save one app's keys
        $cfg = oa_cfg(); $app = $j['app']; $cur = $cfg[$app] ?? [];
        $id = trim((string)($j['id'] ?? '')); $sec = trim((string)($j['secret'] ?? ''));
        if (!empty($j['clear'])) { unset($cfg[$app]); }
        else { $cur['id'] = $id; if ($sec !== '') $cur['secret'] = $sec; if ($app === 'meta') $cur['version'] = preg_match('/^v\d+\.\d+$/', (string)($j['version'] ?? '')) ? $j['version'] : ($cur['version'] ?? 'v21.0'); $cur['by'] = $me; $cur['at'] = date('c'); $cfg[$app] = $cur; }
        oa_cfg_save($cfg); flr_audit('', 'oauth_app', $app, (!empty($j['clear']) ? 'Removed' : 'Saved') . ' by ' . $me);
        header('Location: connectors.php?saved=' . urlencode($app) . '#setup'); exit;
    } elseif (isset(OA_CONNECTORS[$j['p'] ?? ''])) { // agent toggles: off = disconnect; on (service not live yet) = save the request
        $p = $j['p'];
        if (empty($j['on'])) oa_disconnect($me, $p); else oa_set($me, $p, ['on_flag' => 1]);
        header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit;
    }
    if (!empty($j['p'])) { header('Content-Type: application/json'); echo json_encode(['ok' => false]); exit; }
}
$rows = oa_rows($me); $cfg = oa_cfg();
if (!empty($_GET['connected']) && isset(OA_CONNECTORS[$_GET['connected']])) $msg = OA_CONNECTORS[$_GET['connected']][0] . ' is connected.';
$h = function ($x) { return htmlspecialchars((string)$x); };
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
.sw.wait[aria-checked="true"]{background:#d49a00}
.sw:focus-visible{outline:3px solid rgba(3,111,114,.35);outline-offset:2px}.sw:disabled{opacity:.6;cursor:default}
dialog.cnd{border:0;border-radius:16px;padding:0;width:min(480px,calc(100vw - 32px));box-shadow:0 20px 60px rgba(0,0,0,.25)}dialog.cnd::backdrop{background:rgba(15,30,32,.45)}
.cnd .in{padding:22px}.cnd h2{margin:0 0 6px;font-size:1.2rem}.cnd ol{margin:12px 0;padding-left:20px;line-height:1.55}.cnd ol li{margin:0 0 6px}
.cnd .need{background:#fdf3dc;color:#7a520b;border-radius:10px;padding:9px 12px;font-size:.88rem;margin:10px 0}
.cnd .acts{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}.cnd .acts .btn{border:0;cursor:pointer;font:inherit;font-weight:600}
.cnd .ghost{background:none;border:1px solid var(--line)!important;color:#3c4646}
.setup{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:16px 18px;margin:24px 0 16px}
.setup>h2{margin:0 0 4px;font-size:1.1rem}.setup>p{color:#5a6a6c;margin:0 0 12px;font-size:.9rem}
.app{border-top:1px solid var(--line);padding:12px 0}.app summary{cursor:pointer;display:flex;align-items:center;gap:10px;font-weight:700;list-style:none}.app summary::-webkit-details-marker{display:none}
.app .st{margin-left:auto;font-size:.78rem;font-weight:700;padding:2px 9px;border-radius:99px}.app .st.on{background:#e3f4e8;color:#2e7d32}.app .st.no{background:#eef3f3;color:#5a6a6c}
.app ol{font-size:.88rem;color:#2a3638;line-height:1.5;padding-left:18px}.app label{display:block;font-size:.85rem;color:#455355;margin:0 0 8px}.app input{display:block;width:100%;box-sizing:border-box;margin-top:3px;padding:8px 10px;border:1px solid var(--line);border-radius:9px;font:inherit}
.redir{display:flex;gap:8px;align-items:center;background:#f3f7f7;border-radius:9px;padding:8px 10px;font-family:ui-monospace,monospace;font-size:.85rem;margin:6px 0 12px;overflow-wrap:anywhere}
</style>
<div class="cnw">
<?php if ($err !== ''): ?>  <p class="sigerr"><?= $h($err) ?></p><?php endif; ?>
<?php if ($msg !== ''): ?>  <p class="note">&#10003; <?= $h($msg) ?></p><?php endif; ?>
<?php if (!empty($_GET['saved'])): ?>  <p class="note">Office setup saved for <?= $h(OA_APPS[$_GET['saved']][0] ?? '') ?>.</p><?php endif; ?>
  <p class="cnintro">Connect the back office to your own accounts. Turn one on and you'll see what to expect, then sign in to that service and approve access. You can turn any of them off at any time, and that disconnects it.</p>
<?php $g = ''; foreach (OA_CONNECTORS as $k => $c):
    if ($c[2] !== $g): if ($g !== '') echo "  </div>\n"; $g = $c[2]; ?>
  <div class="cng"><h2><?= $h($g) ?></h2>
<?php endif; $r = $rows[$k] ?? []; $connected = !empty($r['tokens']); $live = oa_ready($k); $wants = !$connected && !empty($r['on_flag']) && !$live; $ready = !$connected && !empty($r['on_flag']) && $live; ?>
    <div class="cnr">
      <span class="ic"><?= $icon($k) ?></span>
      <div class="tx"><div class="nm"><?= $h($c[0]) ?></div><div class="ds"><?= $h($c[1]) ?></div>
        <div class="cnst <?= $connected ? 'live' : ($wants || $ready ? 'wait' : 'off') ?>" data-st><?= $connected ? '&#10003; Connected' . ($r['account'] ? ' as ' . $h($r['account']) : '') : ($wants ? 'Requested &middot; waiting for the office to finish setting up ' . $h($c[0]) : ($ready ? 'Ready! Turn it on to finish connecting.' : ($live ? 'Off' : 'Off &middot; not available yet'))) ?></div></div>
      <button type="button" class="sw<?= $wants ? ' wait' : '' ?>" role="switch" aria-checked="<?= $connected || $wants ? 'true' : 'false' ?>" aria-label="<?= $h($c[0]) ?>" data-p="<?= $k ?>" data-live="<?= $live ? 1 : 0 ?>" data-conn="<?= $connected ? 1 : 0 ?>"></button>
    </div>
<?php endforeach; ?>
  </div>
  <p style="font-size:.85rem;color:#5a6a6c">FLR Office only uses what you connect for the features described above, and never posts or sends anything without you choosing to.</p>

<?php if ($isStaff): $want = []; foreach (oa_db()->query("SELECT provider, COUNT(*) n FROM connectors WHERE on_flag = 1 AND (tokens IS NULL OR tokens = '') GROUP BY provider") as $x) $want[$x['provider']] = (int)$x['n']; ?>
  <div class="setup" id="setup">
    <h2>Office setup (staff only)</h2>
    <p>Each company needs one FLR app. Create it, add the redirect address, and paste its keys here. The moment keys are saved, agents can connect that service. Keys are stored on the server, never shown in full again.</p>
<?php foreach (OA_APPS as $app => $a): $ready = oa_app_ready($app); $c = $cfg[$app] ?? []; $uses = array_keys(array_filter(OA_CONNECTORS, function ($x) use ($app) { return $x[3] === $app; }));
      $waiting = array_sum(array_map(function ($u) use ($want) { return $want[$u] ?? 0; }, $uses)); ?>
    <details class="app"<?= !empty($_GET['saved']) && $_GET['saved'] === $app ? ' open' : '' ?>><summary><?= $h($a[0]) ?> <span style="font-weight:400;color:#5a6a6c;font-size:.85rem">&middot; <?= $h(implode(', ', array_map(function ($u) { return OA_CONNECTORS[$u][0]; }, $uses))) ?><?= $waiting ? ' &middot; ' . $waiting . ' agent request' . ($waiting === 1 ? '' : 's') . ' waiting' : '' ?></span><span class="st <?= $ready ? 'on' : 'no' ?>"><?= $ready ? 'Live' : 'Not set up' ?></span></summary>
      <ol><?php foreach ($a[2] as $s): ?><li><?= $h($s) ?></li><?php endforeach; ?></ol>
      <p style="margin:0;font-size:.85rem"><a href="<?= $h($a[1]) ?>" target="_blank" rel="noopener">Open the <?= $h(explode(' ', $a[0])[0]) ?> developer console &#8599;</a></p>
      <div class="redir">Redirect address: <?= $h(OA_REDIRECT) ?></div>
      <form method="post"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="a" value="appsave"><input type="hidden" name="app" value="<?= $app ?>">
        <label><?= $app === 'meta' ? 'App ID' : ($app === 'tiktok' ? 'Client key' : 'Client ID') ?><input type="text" name="id" value="<?= $h($c['id'] ?? '') ?>" autocomplete="off" required></label>
        <label><?= $app === 'meta' ? 'App secret' : 'Client secret' ?><input type="password" name="secret" autocomplete="new-password" placeholder="<?= !empty($c['secret']) ? 'Saved (leave empty to keep)' : '' ?>"<?= empty($c['secret']) ? ' required' : '' ?>></label>
<?php if ($app === 'meta'): ?>        <label>Graph API version<input type="text" name="version" value="<?= $h($c['version'] ?? 'v21.0') ?>" placeholder="v21.0"></label><?php endif; ?>
        <p style="margin:6px 0 0;display:flex;gap:10px;align-items:center"><button class="btn" type="submit" style="border:0;cursor:pointer;font:inherit;font-weight:600">Save</button>
<?php if ($ready): ?>          <button class="btn" name="clear" value="1" type="submit" style="background:#fff;color:#c0392b;border:1px solid var(--line);cursor:pointer;font:inherit;font-weight:600" onclick="return confirm('Remove these keys? Agents won\'t be able to connect <?= $h($a[0]) ?> until new keys are saved.')">Remove keys</button><?php endif; ?>
<?php if (!empty($c['at'])): ?>          <span style="font-size:.8rem;color:#5a6a6c">Saved <?= $h(date('M j, Y', strtotime($c['at']))) ?> by <?= $h($c['by'] ?? '') ?></span><?php endif; ?></p>
      </form>
    </details>
<?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<dialog class="cnd" id="cnd"><div class="in">
  <h2 id="cndt"></h2>
  <p id="cndlead" style="margin:0;color:#455355"></p>
  <ol id="cndsteps"></ol>
  <p class="need" id="cndneed" hidden></p>
  <div class="acts"><button type="button" class="btn ghost" data-x>Cancel</button><a class="btn" id="cndgo" href="#">Continue</a></div>
</div></dialog>
<script>
(function(){var CSRF=<?= json_encode($_SESSION['csrf']) ?>,C=<?= json_encode(array_map(function ($c) { return ['n' => $c[0], 'how' => $c[5], 'need' => $c[6], 'svc' => ['google' => 'Google', 'meta' => 'Facebook', 'linkedin' => 'LinkedIn', 'x' => 'X', 'tiktok' => 'TikTok'][$c[3]]]; }, OA_CONNECTORS)) ?>;
var d=document.getElementById("cnd");
function post(o){o.csrf=CSRF;return fetch("connectors.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(o)}).then(function(r){return r.json();});}
function open(p,live){var c=C[p];document.getElementById("cndt").textContent="Connect "+c.n;
  var s=document.getElementById("cndsteps"),n=document.getElementById("cndneed"),go=document.getElementById("cndgo"),lead=document.getElementById("cndlead");
  if(live){lead.textContent="Here's what happens next:";s.innerHTML="<li>You'll go to "+c.svc+" to sign in.</li><li>"+c.how+"</li><li>You'll come right back here, connected.</li>";go.textContent="Continue to "+c.svc;go.href="connect.php?p="+p+"&t="+CSRF;}
  else{lead.textContent=c.n+" isn't switched on by the office yet. Turn it on now and you'll be first in line; once it's ready you'll come back here and:";s.innerHTML="<li>Sign in to "+c.svc+".</li><li>"+c.how+"</li>";go.textContent="Request it";go.href="#request";go.dataset.p=p;}
  n.hidden=!c.need;n.textContent=c.need?"You'll need: "+c.need:"";d.showModal();}
d.addEventListener("click",function(e){if(e.target.closest("[data-x]")||e.target===d)d.close();
  var g=e.target.closest("#cndgo");if(g&&g.getAttribute("href")==="#request"){e.preventDefault();post({p:g.dataset.p,on:1}).then(function(){location.reload();});}});
document.querySelectorAll(".sw").forEach(function(b){b.addEventListener("click",function(){var p=b.dataset.p,on=b.getAttribute("aria-checked")==="true";
  if(!on){open(p,b.dataset.live==="1");return;}
  if(b.dataset.conn==="1"&&!confirm("Disconnect "+C[p].n+"? FLR Office will stop using it right away."))return;
  b.disabled=true;post({p:p,on:0}).then(function(){location.reload();}).catch(function(){b.disabled=false;alert("Couldn't save that. Please try again.");});});});
})();
</script>
<?php sp_foot();
