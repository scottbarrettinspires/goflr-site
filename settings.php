<?php
// Settings (gear icon on Home): put FLR Office on your phone, and turn phone alerts on or off for this device.
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
</style>
<div class="setw">
  <div class="setc">
    <span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/></svg></span>
    <div class="tx">
      <h2>FLR Office on your phone</h2>
      <p>Install it from the browser, no app store, and get an alert for <?= $alertWhat ?>.</p>
      <div class="acts" id="appnot"><a class="btn" href="install.php">Install the app</a></div>
      <div class="acts" id="appyes" hidden><span class="seton">&#10003; You're using the FLR Office app</span></div>
    </div>
  </div>

  <div class="setc">
    <span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg></span>
    <div class="tx">
      <h2>Phone alerts</h2>
      <p id="pushtext">Get an alert on this phone for <?= $alertWhat ?>. Works best in the FLR Office app on your home screen.</p>
      <div class="acts" id="pushcard" hidden><button class="btn" id="pushon" type="button">Turn on alerts</button></div>
      <div class="acts" id="pushok" hidden><span class="seton">&#128276; Alerts are on for this phone</span> <a href="#" class="lnk" id="pushtest">Send a test</a> &middot; <a href="#" class="lnk" id="pushoff">Turn off</a></div>
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
