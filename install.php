<?php // Install the FLR Office app (home-screen app, no app store). Works on iPhone, Android and computers. ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install FLR Office</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#036f72">
<link rel="icon" href="/icon-192.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="FLR Office">
<script>if("serviceWorker" in navigator){navigator.serviceWorker.register("/sw.js").catch(function(){});}</script>
<style>
:root{--teal:#036f72}*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#f4f8f8;color:#1f2a2c;line-height:1.5}
.top{background:var(--teal);color:#fff;text-align:center;padding:26px 16px 40px}.top img{width:84px;height:84px;border-radius:20px;box-shadow:0 6px 18px rgba(0,0,0,.25)}
.top h1{margin:12px 0 4px;font-size:1.5rem}.top p{margin:0;opacity:.9}
.box{max-width:520px;margin:-24px auto 30px;background:#fff;border-radius:16px;padding:20px;box-shadow:0 8px 30px rgba(0,0,0,.08)}
.step{display:flex;gap:12px;align-items:flex-start;padding:12px 0;border-top:1px solid #e3eaeb}.step:first-of-type{border-top:0}
.n{flex:0 0 30px;height:30px;border-radius:50%;background:var(--teal);color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center}
.ic{display:inline-flex;vertical-align:-6px;background:#eef3f3;border-radius:8px;padding:3px 6px}
.btn{display:block;width:100%;text-align:center;background:var(--teal);color:#fff;border:0;border-radius:12px;padding:14px;font:inherit;font-weight:700;font-size:1.05rem;text-decoration:none;cursor:pointer;margin-top:10px}
.note{background:#e7f2f2;border-left:4px solid var(--teal);padding:10px 12px;border-radius:8px;font-size:.9rem;margin-top:14px}
.warn{background:#fff4e0;border-left-color:#c27c0e}.sec{display:none}.small{font-size:.85rem;color:#5a6a6c}
</style>
</head>
<body>
<div class="top"><img src="/icon-192.png" alt=""><h1>FLR Office</h1><p>Florida Luxury Realty agent back office on your phone. No app store needed.</p></div>
<div class="box">
  <div class="sec" id="done"><h2>You're all set</h2><p>The FLR Office app is installed and open. Tap the gear (<strong>Settings</strong>) on the Home page, then <strong>Turn on alerts</strong> to get a notification for every new lead.</p><a class="btn" href="/home.php">Go to Home</a></div>

  <div class="sec" id="ios-safari">
    <h2>Install on iPhone</h2>
    <div class="step"><span class="n">1</span><div>Tap the <strong>Share</strong> button <span class="ic"><svg width="18" height="20" viewBox="0 0 18 20" fill="none" stroke="#036f72" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 13V2M5 6l4-4 4 4"/><path d="M5 9H3v9h12V9h-2"/></svg></span> at the bottom of Safari (on iPad, at the top).</div></div>
    <div class="step"><span class="n">2</span><div>Scroll down and tap <strong>Add to Home Screen</strong> <span class="ic"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="#036f72" stroke-width="1.8"><rect x="2" y="2" width="14" height="14" rx="3"/><path d="M9 6v6M6 9h6" stroke-linecap="round"/></svg></span>. If you don't see it, tap <strong>Edit Actions</strong> at the bottom of the list to add it.</div></div>
    <div class="step"><span class="n">3</span><div>Make sure <strong>Open as Web App</strong> is on (newer iPhones), then tap <strong>Add</strong>.</div></div>
    <div class="step"><span class="n">4</span><div>Open <strong>FLR Office</strong> from your home screen, sign in, and tap the gear (<strong>Settings</strong>) on the Home page, then <strong>Turn on alerts</strong>.</div></div>
    <div class="note">New-lead alerts on iPhone need iOS 16.4 or later and only work from the home-screen app, not from Safari.</div>
  </div>

  <div class="sec" id="ios-other">
    <h2>Open this page in Safari</h2>
    <p>On iPhone, apps like this install from <strong>Safari</strong>.</p>
    <div class="step"><span class="n">1</span><div>Tap <strong>Copy link</strong> below.</div></div>
    <div class="step"><span class="n">2</span><div>Open <strong>Safari</strong>, paste the link in the address bar and go.</div></div>
    <div class="step"><span class="n">3</span><div>Follow the iPhone steps that appear.</div></div>
    <button class="btn" id="copy" type="button">Copy link</button>
    <p class="small">Newer iPhones can also install from Chrome: tap the Share button in Chrome's address bar, then <strong>Add to Home Screen</strong>.</p>
  </div>

  <div class="sec" id="android">
    <h2>Install on Android</h2>
    <button class="btn" id="installbtn" type="button" style="display:none">Install FLR Office</button>
    <div id="android-steps">
      <div class="step"><span class="n">1</span><div>In <strong>Chrome</strong>, tap the <strong>&#8942;</strong> menu at the top right.</div></div>
      <div class="step"><span class="n">2</span><div>Tap <strong>Install app</strong> (or <strong>Add to Home screen</strong>), then <strong>Install</strong>.</div></div>
      <div class="step"><span class="n">3</span><div>Open <strong>FLR Office</strong> from your home screen and tap the gear (<strong>Settings</strong>) on the Home page, then <strong>Turn on alerts</strong>.</div></div>
      <p class="small">Samsung Internet: tap <strong>&#8801;</strong> at the bottom right, then <strong>Add page to</strong> &rsaquo; <strong>Home screen</strong>.</p>
    </div>
  </div>

  <div class="sec" id="desktop">
    <h2>Install on your phone</h2>
    <p>Open <strong>goflr.com/install.php</strong> on your iPhone or Android phone and follow the steps there.</p>
    <p class="small">On a computer you can also install it: in Chrome or Edge, click the install icon at the right end of the address bar.</p>
    <button class="btn" id="installbtn2" type="button" style="display:none">Install on this computer</button>
  </div>
</div>
<script>
(function(){
var ua=navigator.userAgent,ios=/iPhone|iPad|iPod/.test(ua)||(navigator.platform==="MacIntel"&&navigator.maxTouchPoints>1),android=/Android/.test(ua);
var standalone=window.matchMedia("(display-mode: standalone)").matches||navigator.standalone===true;
var safari=ios&&/Safari/.test(ua)&&!/CriOS|FxiOS|EdgiOS|GSA|FBAN|FBAV|Instagram|Line/.test(ua);
function show(id){document.getElementById(id).style.display="block";}
if(standalone)show("done");else if(ios)show(safari?"ios-safari":"ios-other");else if(android)show("android");else show("desktop");
var cp=document.getElementById("copy");if(cp)cp.onclick=function(){var u="https://goflr.com/install.php";(navigator.clipboard?navigator.clipboard.writeText(u):Promise.reject()).then(function(){cp.textContent="Link copied";},function(){prompt("Copy this link:",u);});};
var deferred=null;window.addEventListener("beforeinstallprompt",function(e){e.preventDefault();deferred=e;["installbtn","installbtn2"].forEach(function(id){var b=document.getElementById(id);if(b)b.style.display="block";});});
["installbtn","installbtn2"].forEach(function(id){var b=document.getElementById(id);if(b)b.onclick=function(){if(!deferred)return;deferred.prompt();deferred.userChoice.then(function(){deferred=null;b.style.display="none";});};});
window.addEventListener("appinstalled",function(){document.querySelectorAll(".sec").forEach(function(s){s.style.display="none";});show("done");});
})();
</script>
</body>
</html>
