<?php
require_once __DIR__ . '/lib.php';
flr_session();
header('Cache-Control: no-store');
if (!empty($_SESSION['email'])) { header('Location: home.php'); exit; }
$removed = isset($_GET['e']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Sign in - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
<style>
:root{--teal:#036F72;--teal-dark:#025457;--teal-soft:#e6f2f2;--muted:#5b6669;--white:#fff;--line:#dfe6e7}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;line-height:1.5}
a{color:var(--teal)}
.btn{display:inline-block;background:var(--teal);color:#fff;text-decoration:none;padding:10px 16px;border-radius:10px;font-weight:600}
.note{background:var(--teal-soft);border-left:4px solid var(--teal);padding:12px 16px;border-radius:8px;font-size:.92rem;margin:0 0 16px}
body.login{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0d2a2c;padding:20px;position:relative;overflow:hidden}
.login .box{position:relative;z-index:3;background:rgba(255,255,255,.88);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);color:#23292b;border:1px solid rgba(255,255,255,.5);border-radius:20px;padding:38px 30px;max-width:440px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.35)}
.login .logo{display:flex;flex-direction:column;line-height:1.1;margin-bottom:22px;color:var(--teal)}
.login .logo b{font-size:1.15rem;letter-spacing:.14em;text-transform:uppercase}
.login .logo span{font-size:.72rem;letter-spacing:.3em;text-transform:uppercase;opacity:.8}
.login h1{margin:0 0 6px;font-size:1.6rem}
.login p{color:var(--muted);margin:0 0 20px}
.btn.big{padding:14px 28px;font-size:1.05rem}
.login .note{text-align:left;margin:22px 0 14px}
.login .help{font-size:.85rem;margin:0}
.bgs{position:fixed;inset:0;z-index:0}
.bg{position:absolute;inset:0;opacity:0;transition:opacity 1.6s ease;background-size:cover;background-position:center}
.bg.on{opacity:1}
.bg svg{width:100%;height:100%;display:block}
.shade{position:fixed;inset:0;z-index:1;background:radial-gradient(ellipse at center,rgba(0,0,0,.05) 0,rgba(0,0,0,.35) 100%)}
.cap{position:fixed;left:24px;bottom:18px;z-index:3;color:#fff;font-size:.85rem;letter-spacing:.25em;text-transform:uppercase;text-shadow:0 1px 6px rgba(0,0,0,.6)}
.login .box p{color:#4a5558}
.login .logo{color:var(--teal)}

html,body{height:100%}
.login .logo img{width:100%;max-width:300px;height:auto;margin:0 auto 6px;display:block}
.err{color:#b3261e;font-weight:600;font-size:.92rem;margin:14px 0 0}
.login .box .g_id_signin{display:flex;justify-content:center}
</style>
</head>
<body class="login">
<div class="bgs" id="bgs" aria-hidden="true"></div>
<div class="shade"></div>
<div class="cap" id="cap"></div>
<main class="box">
  <div class="logo"><img src="logo.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></div>
  <h1>Welcome back</h1>
  <p>Your transactions, forms and training in one place.</p>
  <div id="g_id_onload" data-client_id="635313816169-b0ibgp7e6056ohnali4oq6kq42cv9kd4.apps.googleusercontent.com" data-callback="onCred" data-auto_prompt="false" data-use_fedcm_for_prompt="true"></div>
  <div class="g_id_signin" data-type="standard" data-size="large" data-theme="filled_blue" data-text="signin_with" data-shape="pill" data-logo_alignment="left" data-width="300"></div>
  <p class="err" id="err" role="alert"<?php if (!$removed) echo ' hidden'; ?>><?php if ($removed) echo 'Your access has ended or changed. Please contact the office.'; ?></p>
  <p class="note">Sign in with the Google (Gmail) account the office has on file for you. Your transactions and files are shown only from folders shared with that account.</p>
  <p class="help">Can't get in? Contact the office.</p>
</main>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script src="scenes.js?v=4"></script>
<script>
function onCred(r) {
  var e = document.getElementById("err");
  e.hidden = true;
  fetch("auth.php", { method: "POST", headers: { "Content-Type": "application/json" }, credentials: "same-origin", body: JSON.stringify({ credential: r.credential }) })
    .then(function (x) { return x.json(); })
    .then(function (d) {
      if (d.ok) { location.href = "home.php"; return; }
      e.textContent = d.msg === "notlisted" ? "That Google account is not on the agent list. Please contact the office." : "Sign-in did not work. Please try again.";
      e.hidden = false;
    })
    .catch(function () { e.textContent = "Sign-in did not work. Please try again."; e.hidden = false; });
}
(function () {
  var holder = document.getElementById("bgs"), cap = document.getElementById("cap");
  var items = (window.PHOTOS && PHOTOS.length) ? PHOTOS.map(function (f) { return { name: "Florida", img: f }; }) : FLR_SCENES;
  var it = items[Math.floor(Math.random() * items.length)];
  var d = document.createElement("div"); d.className = "bg on";
  if (it.img) d.style.backgroundImage = "url(" + it.img + ")"; else d.innerHTML = it.svg;
  holder.appendChild(d);
  cap.textContent = it.name;
})();
</script>
</body>
</html>
