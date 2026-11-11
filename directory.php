<?php
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require_once __DIR__ . "/msglib.php";
require_once __DIR__ . "/favlib.php";
$myEmail = strtolower(trim($_SESSION['email'] ?? ''));
$myFavs = fav_list($myEmail);
$omMine = om_my_keys($_SESSION['email'] ?? '', flr_is_broker());
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$canLoginAs = flr_is_broker();
$d = flr_directory();
function dir_phone_digits($p) { $x = preg_replace('/[^0-9+]/', '', (string)$p); return $x; }
function dir_phone_fmt($p) { $x = preg_replace('/\D/', '', (string)$p); if (strlen($x) === 11 && $x[0] === '1') $x = substr($x, 1); return strlen($x) === 10 ? substr($x, 0, 3) . '-' . substr($x, 3, 3) . '-' . substr($x, 6) : trim((string)$p); }
// $msgKey: who a Message button addresses ('' = no button, e.g. your own card or someone not in Agent Records).
// $fav: null = no favorite star (staff cards, your own card); true/false = starred or not.
function dir_card($p, $sub, $loginAsCsrf = null, $msgKey = '', $fav = null, $i = 0) {
    $name = htmlspecialchars($p['name']); $ph = trim((string)($p['phone'] ?? '')); $em = trim((string)($p['email'] ?? ''));
    $ini = ''; foreach (preg_split('/\s+/', trim($p['name'])) as $w) { if ($w !== '' && strlen($ini) < 2) $ini .= mb_strtoupper(mb_substr($w, 0, 1)); }
    $pu = flr_headshot_url($em, !empty($p['photo']) ? 'dirphoto.php?k=' . flr_dir_photo_key($p['photo']) : ''); $img = $pu !== '' ? '<img src="' . htmlspecialchars($pu) . '" alt="" loading="lazy" onerror="this.remove()">' : '';
    $star = $fav === null ? '' : '<button type="button" class="dstar' . ($fav ? ' on' : '') . '" aria-pressed="' . ($fav ? 'true' : 'false') . '" title="' . ($fav ? 'Remove from favorites' : 'Add to favorites') . '" aria-label="Favorite ' . $name . '">'
        . '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 2.8l2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2l-5.7 3.1 1.2-6.4-4.7-4.4 6.4-.8z"/></svg></button>';
    $h = '<div class="dcard" data-s="' . htmlspecialchars(strtolower($p['name'] . ' ' . $sub . ' ' . $em . ' ' . $ph)) . '" data-email="' . htmlspecialchars(strtolower($em)) . '" data-n="' . htmlspecialchars(strtolower($p['name'])) . '" data-i="' . (int)$i . '"><div class="davw"><div class="dav"><span>' . htmlspecialchars($ini) . '</span>' . $img . '</div>' . $star . '</div><div class="dtx"><div class="dnm">' . $name . '</div>';
    if ($sub !== '') $h .= '<div class="dsub">' . htmlspecialchars($sub) . '</div>';
    if ($ph !== '') $h .= '<div><a href="tel:' . htmlspecialchars(dir_phone_digits($ph)) . '">' . htmlspecialchars(dir_phone_fmt($ph)) . '</a></div>';
    if ($em !== '') $h .= '<div><a href="mailto:' . htmlspecialchars($em) . '">' . htmlspecialchars($em) . '</a></div>';
    if ($msgKey !== '') $h .= '<div style="margin:4px 0 2px"><a class="dmsg" href="messages.php?to=' . urlencode($msgKey) . '#compose">&#9993; Message</a></div>';
    if ($loginAsCsrf !== null && $em !== '') {
        $h .= '<form method="post" action="login_as.php" style="margin-top:2px"><input type="hidden" name="csrf" value="' . htmlspecialchars($loginAsCsrf) . '"><input type="hidden" name="email" value="' . htmlspecialchars($em) . '">'
            . '<button type="submit" style="background:none;border:none;padding:0;font:inherit;font-size:.82rem;color:var(--teal);text-decoration:underline;cursor:pointer" onclick="return confirm(\'View the back office as \' + ' . json_encode($p['name']) . ' + \'? Use Return to my account to come back.\');">Login As</button></form>';
    }
    return $h . '</div></div>';
}
?>
<!doctype html>
<html lang="en">
<head>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#036f72">
<link rel="icon" href="/icon-192.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="FLR Office">
<script>if("serviceWorker" in navigator){navigator.serviceWorker.register("/sw.js").catch(function(){});}</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Directory - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=17">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
.dtools{margin:0 0 18px}.dtools input{width:100%;max-width:420px;padding:11px 14px;border:1px solid var(--line);border-radius:10px;font:inherit}
.dgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin:0 0 32px}
.dcard{display:flex;gap:14px;align-items:center;background:var(--white);border:1px solid var(--line);border-radius:14px;padding:14px}
.dav{position:relative;flex:0 0 76px;width:76px;height:76px;border-radius:50%;overflow:hidden;background:var(--teal-soft);display:flex;align-items:center;justify-content:center;color:var(--teal);font-weight:700;font-size:1.4rem}
.dav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.dtx{min-width:0;font-size:.95rem;line-height:1.45}.dtx a{color:var(--teal);text-decoration:none;overflow-wrap:anywhere}.dtx a:hover{text-decoration:underline}
.dnm{font-weight:700;font-size:1.05rem}.dsub{color:#5a6a6c;font-size:.85rem}
.davw{position:relative;flex:0 0 76px}
.dstar{position:absolute;top:-6px;right:-8px;width:32px;height:32px;padding:0;border:0;background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;filter:drop-shadow(0 1px 2px rgba(0,0,0,.45))}
.dstar svg path{fill:#fff;stroke:#c9cfd0;stroke-width:1;stroke-linejoin:round;transition:fill .15s}
.dstar:hover svg path{fill:#fff6cc}.dstar.on svg path{fill:#f5c518;stroke:#d4a200}.dstar:focus-visible{outline:2px solid var(--teal);border-radius:50%}
.dtx a.dmsg{display:inline-block;background:var(--teal);color:#fff;border-radius:99px;padding:3px 12px;font-size:.82rem;font-weight:600}.dtx a.dmsg:hover{text-decoration:none;background:var(--teal-dark)}
.dbar{display:flex;gap:12px;align-items:center;justify-content:space-between;margin:0 0 18px}.dbar .dtools{margin:0;flex:1}
#dmap{display:none;height:calc(100vh - 260px);min-height:480px;border:1px solid var(--line);border-radius:14px}
.pin{width:46px;height:46px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.45);background:var(--teal-soft) center/cover no-repeat;color:var(--teal);font:700 15px/40px Arial,sans-serif;text-align:center;overflow:hidden}
.mpop{display:flex;gap:12px;align-items:center;min-width:230px}.mpop .dav{width:64px;height:64px;flex-basis:64px;font-size:1.2rem}
.leaflet-popup-content{margin:12px 14px}.leaflet-popup-content a{color:var(--teal)}
@media(max-width:900px){#bymap{display:none}}
.dsec h2{margin:8px 0 12px}.dsec h2 span{font-size:.8rem;color:#5a6a6c;font-weight:500;margin-left:6px}
</style>
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Virtual Broker Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (flr_agent_menu()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php">Active Business</a>
    <a href="performance.php">Performance</a>
    <a href="learn.php">Learn</a>
    <?php endif; ?>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a><?php endif; ?>
    <a href="logout.php">Sign Out</a>
  </nav>
</div></header>
<section class="hero"><h1>Directory</h1><p>Our staff and active agents. Tap a phone number to call or an email address to write.</p></section>
<main class="wrap">
<?php $laErr = (string)($_GET['e'] ?? ''); if ($laErr === 'already'): ?>
  <p class="note">Return to your own account first before logging in as someone else.</p>
<?php elseif ($laErr === 'notfound'): ?>
  <p class="note">That agent could not be found.</p>
<?php elseif ($laErr === 'expired'): ?>
  <p class="note">This page expired. Please try again.</p>
<?php endif; ?>
<?php if (!$d): ?>
  <p class="note">The directory could not be loaded right now. Please try again in a minute.</p>
<?php else: ?>
  <div class="dbar"><div class="dtools"><input type="search" id="q" placeholder="Search by name, phone or email" autocomplete="off"></div><button type="button" class="btn" id="bymap">Show By Location</button></div>
  <div id="dmap"></div>
  <div id="dlist"><section class="dsec"><h2>Staff<?php if ($canLoginAs): ?> <span><?= count($d['staff'] ?? []) ?></span><?php endif; ?></h2><div class="dgrid">
<?php foreach ((array)($d['staff'] ?? []) as $p) { $k = om_key_for($p['email'] ?? '', true); echo dir_card($p, (string)($p['position'] ?? ''), null, in_array($k, $omMine, true) ? '' : $k), "\n"; } ?>
  </div></section>
<?php $agentsAll = array_values((array)($d['agents'] ?? [])); $favCards = ''; $restCards = ''; $nFav = 0; $nRest = 0;
foreach ($agentsAll as $i => $p) {
    $em = strtolower(trim((string)($p['email'] ?? ''))); $k = om_key_for($p['email'] ?? '', false);
    $isMe = ($em !== '' && $em === $myEmail) || ($k !== '' && in_array($k, $omMine, true)); // you sign in with a different Google address
    $isFav = !$isMe && isset($myFavs[$em]);
    $card = dir_card($p, '', $canLoginAs ? $_SESSION['csrf'] : null, in_array($k, $omMine, true) ? '' : $k, ($isMe || $em === '') ? null : $isFav, $i) . "\n";
    if ($isFav) { $favCards .= $card; $nFav++; } else { $restCards .= $card; $nRest++; }
} ?>
  <section class="dsec" id="favsec"<?= $nFav ? '' : ' style="display:none"' ?>><h2>&#9733; Favorites <span id="favn"><?= $nFav ?></span></h2><div class="dgrid" id="favgrid">
<?= $favCards ?>  </div></section>
  <section class="dsec"><h2>Active agents <span id="restn"<?= $canLoginAs ? '' : ' hidden' ?>><?= $nRest ?></span></h2><div class="dgrid" id="restgrid">
<?= $restCards ?>  </div></section>
  </div>
  <p class="note" id="none" hidden>No one matches that search.</p>
<?php if (!empty($d['note']) && $canLoginAs): // the office script's technical note: staff only ?><p class="note"><?= htmlspecialchars($d['note']) ?></p><?php endif; ?>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
<script>
(function(){var q=document.getElementById("q");if(!q)return;q.addEventListener("input",function(){var v=q.value.trim().toLowerCase(),n=0;
[].forEach.call(document.querySelectorAll(".dcard"),function(c){var m=!v||c.dataset.s.indexOf(v)>-1;c.style.display=m?"":"none";if(m)n++;});
[].forEach.call(document.querySelectorAll(".dsec"),function(s){s.style.display=s.querySelector(".dcard:not([style*='none'])")?"":"none";});
document.getElementById("none").hidden=n>0;});})();
// Favorites: star an agent to move them into Favorites at the top (saved for you only).
(function(){var fg=document.getElementById("favgrid"),rg=document.getElementById("restgrid"),fs=document.getElementById("favsec");if(!fg||!rg)return;
var CSRF=<?= json_encode($_SESSION['csrf']) ?>;
function place(card,grid){var kids=[].slice.call(grid.querySelectorAll(".dcard")),at=null; // keep the directory's own order
  for(var j=0;j<kids.length;j++){if(+kids[j].dataset.i>+card.dataset.i){at=kids[j];break;}}
  grid.insertBefore(card,at);}
function counts(){var f=fg.querySelectorAll(".dcard").length;document.getElementById("favn").textContent=f;document.getElementById("restn").textContent=rg.querySelectorAll(".dcard").length;fs.style.display=f?"":"none";}
function set(b,on){b.classList.toggle("on",on);b.setAttribute("aria-pressed",on?"true":"false");b.title=on?"Remove from favorites":"Add to favorites";}
document.addEventListener("click",function(e){var b=e.target.closest(".dstar");if(!b)return;e.preventDefault();
  var card=b.closest(".dcard"),on=!b.classList.contains("on");set(b,on);place(card,on?fg:rg);counts();
  fetch("fav_api.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({csrf:CSRF,email:card.dataset.email,on:on})})
   .then(function(r){return r.json();}).then(function(j){if(!j.ok)throw 0;})
   .catch(function(){set(b,!on);place(card,on?rg:fg);counts();alert("Couldn't save that favorite. Please try again.");});});
})();
</script>
<?php
$pins = [];
if ($d) { foreach ((array)($d['agents'] ?? []) as $p) { if (isset($p['lat']) && is_numeric($p['lat']) && is_numeric($p['lng'])) { $pins[] = ['n' => $p['name'], 'e' => (string)($p['email'] ?? ''), 'p' => (string)($p['phone'] ?? ''), 'pf' => dir_phone_fmt($p['phone'] ?? ''), 'pd' => dir_phone_digits($p['phone'] ?? ''), 'k' => flr_headshot_url((string)($p['email'] ?? ''), !empty($p['photo']) ? 'dirphoto.php?k=' . flr_dir_photo_key($p['photo']) : ''), 'lat' => (float)$p['lat'], 'lng' => (float)$p['lng']]; } } }
$noloc = $d ? max(0, count((array)($d['agents'] ?? [])) - count($pins)) : 0;
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function(){
var PINS=<?= json_encode($pins) ?>,NOLOC=<?= (int)$noloc ?>,map=null,shown=false;
var btn=document.getElementById("bymap"),mapEl=document.getElementById("dmap"),list=document.getElementById("dlist"),qb=document.querySelector(".dtools");
if(!btn)return;
function esc(t){return String(t).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];});}
function ini(n){return n.split(/\s+/).filter(Boolean).slice(0,2).map(function(w){return w[0].toUpperCase();}).join("");}
function build(){
  map=L.map(mapEl,{scrollWheelZoom:true}).setView([28.2,-82.6],9);
  L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:18,attribution:"&copy; OpenStreetMap contributors"}).addTo(map);
  var seen={},pts=[];
  PINS.forEach(function(p){
    var key=p.lat+","+p.lng,n=seen[key]||0;seen[key]=n+1;
    var a=n*2.4,r=n?0.004*Math.sqrt(n):0,lat=p.lat+r*Math.sin(a),lng=p.lng+r*Math.cos(a)*1.1;
    var bg=p.k?"background-image:url("+p.k+")":"";
    var ic=L.divIcon({className:"",html:'<div class="pin" style="'+bg+'">'+(p.k?"":esc(ini(p.n)))+'</div>',iconSize:[46,46],iconAnchor:[23,23],popupAnchor:[0,-24]});
    var h='<div class="mpop"><div class="dav"><span>'+esc(ini(p.n))+'</span>'+(p.k?'<img src="'+esc(p.k)+'" alt="">':"")+'</div><div class="dtx"><div class="dnm">'+esc(p.n)+'</div>'
      +(p.pd?'<div><a href="tel:'+esc(p.pd)+'">'+esc(p.pf)+'</a></div>':"")+(p.e?'<div><a href="mailto:'+esc(p.e)+'">'+esc(p.e)+'</a></div>':"")+'</div></div>';
    L.marker([lat,lng],{icon:ic,title:p.n}).addTo(map).bindPopup(h);pts.push([lat,lng]);
  });
  if(pts.length>1)map.fitBounds(pts,{padding:[50,50],maxZoom:12});else if(pts.length===1)map.setView(pts[0],11);
  if(NOLOC){var n=document.createElement("p");n.className="note";n.style.marginTop="12px";n.textContent=NOLOC+" agent"+(NOLOC===1?" has":"s have")+" no location on file yet, so they appear in the list only.";mapEl.parentNode.insertBefore(n,mapEl.nextSibling);}
}
btn.addEventListener("click",function(){
  shown=!shown;mapEl.style.display=shown?"block":"none";list.style.display=shown?"none":"";qb.style.visibility=shown?"hidden":"";
  btn.textContent=shown?"Show List":"Show By Location";
  if(shown){if(!map)build();setTimeout(function(){map.invalidateSize();},50);}
});
})();
</script>
</body>
</html>
