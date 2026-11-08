<?php
// Settings (gear icon on Home): put FLR Office on your phone and turn its alerts on or off for this device (one card).
// Moved here from the Home page. Alerts are for agents (every new lead) and staff (new recruiting contacts).
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php"; // flr_is_broker()
require_once __DIR__ . "/push_keys.php";
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$isStaff = flr_is_broker();
$alertWhat = $isStaff ? 'new recruiting contacts' : 'every new lead';
sp_head('Settings');
?>
<style>
.setw{max-width:720px}
.setc{display:flex;gap:14px;align-items:flex-start;background:var(--white);border:1px solid var(--line);border-left:5px solid var(--teal);border-radius:14px;padding:16px 18px;margin:0 0 16px}
.setc .ic{flex:0 0 40px;height:40px;border-radius:10px;background:var(--teal-soft);color:var(--teal);display:flex;align-items:center;justify-content:center}
.setc .tx{flex:1;min-width:0}.setc h2{font-size:1.05rem;margin:0 0 4px}.setc p{margin:0 0 10px;color:#5a6a6c;font-size:.92rem}
.setc .acts{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.setc .btn{border:0;cursor:pointer;font:inherit;font-weight:600;padding:8px 16px}
.seton{display:inline-flex;align-items:center;gap:6px;color:#2e7d32;font-weight:600;font-size:.92rem}
.setc a.lnk{font-size:.9rem;color:var(--teal)}
.arow{display:flex;flex-wrap:wrap;gap:10px;align-items:center}.arow>span[hidden]{display:none}.subp{margin:8px 0 0;font-size:.85rem;color:#5a6a6c}
</style>
<div class="setw">
  <div class="setc">
    <span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span>
    <div class="tx">
      <h2>Profile</h2>
      <p><?= $isStaff ? 'Your headshot and details.' : 'Your headshot, your logo in color and white, and your details from Agent Records.' ?></p>
      <div class="acts"><a class="btn" href="profile.php">Open Profile</a></div>
    </div>
  </div>
  <div class="setc">
    <span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 7H7a5 5 0 0 0 0 10h2M15 7h2a5 5 0 0 1 0 10h-2M8 12h8"/></svg></span>
    <div class="tx">
      <h2>Connectors</h2>
      <p>Connect the back office to Gmail, Google Calendar, Google Drive, Google Business Profile and your social media accounts.</p>
      <div class="acts"><a class="btn" href="connectors.php">Open Connectors</a></div>
    </div>
  </div>
<?php if ($isStaff): ?>
  <div class="setc">
    <span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg></span>
    <div class="tx">
      <h2>Forms</h2>
      <p>The forms agents download in Active Business &rsaquo; Forms. See the page as agents do, or add, edit and delete forms and sections.</p>
      <div class="acts"><a class="btn" href="forms.php">Open Forms</a> <a class="lnk" href="forms.php?edit=1">Edit forms</a></div>
    </div>
  </div>
  <div class="setc">
    <span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></svg></span>
    <div class="tx">
      <h2>Agent Records</h2>
      <p>Every agent&#39;s record on the server: contact details, license, dates, compliance files and marketing consents.</p>
      <div class="acts"><a class="btn" href="agentrecords.php">Open Agent Records</a></div>
    </div>
  </div>
  <div class="setc">
    <span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6"/></svg></span>
    <div class="tx">
      <h2>MLS Data</h2>
      <p>The MLS Grid (Stellar MLS) connection settings and the sample-data test of the pipeline.</p>
      <div class="acts"><a class="btn" href="mls_data.php">Open MLS Data</a></div>
    </div>
  </div>
  <div class="setc">
    <span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></span>
    <div class="tx">
      <h2>Sample data</h2>
      <p>Load realistic agent data into your account to preview screens as they're built, and clear it all before launch.</p>
      <div class="acts"><a class="btn" href="sample_data.php">Open Sample data</a></div>
    </div>
  </div>
<?php endif; ?>
  <div class="setc">
    <span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/></svg></span>
    <div class="tx">
      <h2>FLR Office on your phone</h2>
      <p>Install it from your browser, no app store needed, then turn on alerts to get one for <?= $alertWhat ?>.</p>
      <div class="acts arow">
        <span id="appnot"><a class="btn" href="install.php">Install the app</a></span><span id="appyes" hidden><span class="seton">&#10003; You're using the FLR Office app</span></span>
        <span id="pushcard" hidden><button class="btn" id="pushon" type="button">Turn on alerts</button></span>
        <span id="pushok" hidden><span class="seton">&#128276; Alerts are on for this phone</span> <a href="#" class="lnk" id="pushtest">Send a test</a> &middot; <a href="#" class="lnk" id="pushoff">Turn off</a></span>
      </div>
      <p id="pushtext" class="subp">Alerts work best in the FLR Office app on your home screen.</p>
      <div class="acts" id="pushno" hidden><span style="font-size:.9rem;color:#5a6a6c">This browser can't receive alerts. On a phone, install the app first, then open it from your home screen and come back here.</span></div>
      <div class="acts" id="pushblocked" hidden><span style="font-size:.9rem;color:#8a5a0e">Alerts are blocked for this app. Allow notifications for FLR Office in your phone's settings, then reload this page.</span></div>
    </div>
  </div>
</div>
<script>
(function(){
// Installed? (opened from the home screen)
var st=window.matchMedia("(display-mode: standalone)").matches||navigator.standalone===true;
document.getElementById("appnot").hidden=st;document.getElementById("appyes").hidden=!st;
// Alerts
var KEY=<?= json_encode(PUSH_PUBLIC) ?>,CSRF=<?= json_encode($_SESSION['csrf']) ?>,card=document.getElementById("pushcard"),ok=document.getElementById("pushok");
if(!("serviceWorker" in navigator)||!("PushManager" in window)||!("Notification" in window)){document.getElementById("pushno").hidden=false;return;}
if(Notification.permission==="denied"){document.getElementById("pushblocked").hidden=false;return;}
function k(s){var p="=".repeat((4-s.length%4)%4),b=atob((s+p).replace(/-/g,"+").replace(/_/g,"/")),a=new Uint8Array(b.length);for(var i=0;i<b.length;i++)a[i]=b.charCodeAt(i);return a;}
function post(o){o.csrf=CSRF;return fetch("push_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify(o)}).then(function(r){return r.json();});}
navigator.serviceWorker.ready.then(function(reg){
  reg.pushManager.getSubscription().then(function(sub){
    if(sub&&Notification.permission==="granted"){ok.hidden=false;post({endpoint:sub.endpoint});}else{card.hidden=false;}
  });
  document.getElementById("pushon").onclick=function(){var b=this;b.disabled=true;b.textContent="Turning on...";
    Notification.requestPermission().then(function(p){if(p!=="granted")throw new Error("Notifications were not allowed. You can allow them in your phone's settings for this app.");
      return reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:k(KEY)});})
    .then(function(sub){return post({endpoint:sub.endpoint});}).then(function(j){if(!j.ok)throw new Error(j.msg||"Could not turn on alerts.");card.hidden=true;ok.hidden=false;return post({action:"test"});})
    .catch(function(e){document.getElementById("pushtext").textContent=String(e.message);b.disabled=false;b.textContent="Try again";});};
  document.getElementById("pushtest").onclick=function(e){e.preventDefault();post({action:"test"});this.textContent="Sent";};
  document.getElementById("pushoff").onclick=function(e){e.preventDefault();reg.pushManager.getSubscription().then(function(sub){if(!sub)return;return post({action:"off",endpoint:sub.endpoint}).then(function(){return sub.unsubscribe();});}).then(function(){ok.hidden=true;card.hidden=false;});};
});
})();
</script>
<?php sp_foot();
