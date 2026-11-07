<?php
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
$d = flr_directory();
function dir_phone_digits($p) { $x = preg_replace('/[^0-9+]/', '', (string)$p); return $x; }
function dir_phone_fmt($p) { $x = preg_replace('/\D/', '', (string)$p); if (strlen($x) === 11 && $x[0] === '1') $x = substr($x, 1); return strlen($x) === 10 ? substr($x, 0, 3) . '-' . substr($x, 3, 3) . '-' . substr($x, 6) : trim((string)$p); }
function dir_card($p, $sub) {
    $name = htmlspecialchars($p['name']); $ph = trim((string)($p['phone'] ?? '')); $em = trim((string)($p['email'] ?? ''));
    $ini = ''; foreach (preg_split('/\s+/', trim($p['name'])) as $w) { if ($w !== '' && strlen($ini) < 2) $ini .= mb_strtoupper(mb_substr($w, 0, 1)); }
    $img = !empty($p['photo']) ? '<img src="dirphoto.php?k=' . flr_dir_photo_key($p['photo']) . '" alt="" loading="lazy" onerror="this.remove()">' : '';
    $h = '<div class="dcard" data-s="' . htmlspecialchars(strtolower($p['name'] . ' ' . $sub . ' ' . $em . ' ' . $ph)) . '"><div class="dav"><span>' . htmlspecialchars($ini) . '</span>' . $img . '</div><div class="dtx"><div class="dnm">' . $name . '</div>';
    if ($sub !== '') $h .= '<div class="dsub">' . htmlspecialchars($sub) . '</div>';
    if ($ph !== '') $h .= '<div><a href="tel:' . htmlspecialchars(dir_phone_digits($ph)) . '">' . htmlspecialchars(dir_phone_fmt($ph)) . '</a></div>';
    if ($em !== '') $h .= '<div><a href="mailto:' . htmlspecialchars($em) . '">' . htmlspecialchars($em) . '</a></div>';
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
<link rel="stylesheet" href="style.css?v=16">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
.dtools{margin:0 0 18px}.dtools input{width:100%;max-width:420px;padding:11px 14px;border:1px solid var(--line);border-radius:10px;font:inherit}
.dgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin:0 0 32px}
.dcard{display:flex;gap:14px;align-items:center;background:var(--white);border:1px solid var(--line);border-radius:14px;padding:14px}
.dav{position:relative;flex:0 0 76px;width:76px;height:76px;border-radius:50%;overflow:hidden;background:var(--teal-soft);display:flex;align-items:center;justify-content:center;color:var(--teal);font-weight:700;font-size:1.4rem}
.dav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.dtx{min-width:0;font-size:.95rem;line-height:1.45}.dtx a{color:var(--teal);text-decoration:none;overflow-wrap:anywhere}.dtx a:hover{text-decoration:underline}
.dnm{font-weight:700;font-size:1.05rem}.dsub{color:#5a6a6c;font-size:.85rem}
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
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <a href="leads.php">Leads</a>
    <a href="my-transactions.php">Transactions</a>
    <a href="forms.php">Forms</a>
    <a href="training.php">Training</a>
    <a href="directory.php" aria-current="page">Directory</a>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a> <a href="mls_data.php">MLS Data</a><?php endif; ?>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>
<section class="hero"><h1>Directory</h1><p>Our staff and active agents. Tap a phone number to call or an email address to write.</p></section>
<main class="wrap">
<?php if (!$d): ?>
  <p class="note">The directory could not be loaded right now. Please try again in a minute.</p>
<?php else: ?>
  <div class="dbar"><div class="dtools"><input type="search" id="q" placeholder="Search by name, phone or email" autocomplete="off"></div><button type="button" class="btn" id="bymap">Show By Location</button></div>
  <div id="dmap"></div>
  <div id="dlist"><section class="dsec"><h2>Staff <span><?= count($d['staff'] ?? []) ?></span></h2><div class="dgrid">
<?php foreach ((array)($d['staff'] ?? []) as $p) echo dir_card($p, (string)($p['position'] ?? '')), "\n"; ?>
  </div></section>
  <section class="dsec"><h2>Active agents <span><?= count($d['agents'] ?? []) ?></span></h2><div class="dgrid">
<?php foreach ((array)($d['agents'] ?? []) as $p) echo dir_card($p, ''), "\n"; ?>
  </div></section>
  </div>
  <p class="note" id="none" hidden>No one matches that search.</p>
<?php if (!empty($d['note'])): ?><p class="note"><?= htmlspecialchars($d['note']) ?></p><?php endif; ?>
<?php endif; ?>
</main>
<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
<script>
(function(){var q=document.getElementById("q");if(!q)return;q.addEventListener("input",function(){var v=q.value.trim().toLowerCase(),n=0;
[].forEach.call(document.querySelectorAll(".dcard"),function(c){var m=!v||c.dataset.s.indexOf(v)>-1;c.style.display=m?"":"none";if(m)n++;});
[].forEach.call(document.querySelectorAll(".dsec"),function(s){s.style.display=s.querySelector(".dcard:not([style*='none'])")?"":"none";});
document.getElementById("none").hidden=n>0;});})();
</script>
<?php
$pins = [];
if ($d) { foreach ((array)($d['agents'] ?? []) as $p) { if (isset($p['lat']) && is_numeric($p['lat']) && is_numeric($p['lng'])) { $pins[] = ['n' => $p['name'], 'e' => (string)($p['email'] ?? ''), 'p' => (string)($p['phone'] ?? ''), 'pf' => dir_phone_fmt($p['phone'] ?? ''), 'pd' => dir_phone_digits($p['phone'] ?? ''), 'k' => !empty($p['photo']) ? flr_dir_photo_key($p['photo']) : '', 'lat' => (float)$p['lat'], 'lng' => (float)$p['lng']]; } } }
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
    var bg=p.k?"background-image:url(dirphoto.php?k="+p.k+")":"";
    var ic=L.divIcon({className:"",html:'<div class="pin" style="'+bg+'">'+(p.k?"":esc(ini(p.n)))+'</div>',iconSize:[46,46],iconAnchor:[23,23],popupAnchor:[0,-24]});
    var h='<div class="mpop"><div class="dav"><span>'+esc(ini(p.n))+'</span>'+(p.k?'<img src="dirphoto.php?k='+p.k+'" alt="">':"")+'</div><div class="dtx"><div class="dnm">'+esc(p.n)+'</div>'
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
