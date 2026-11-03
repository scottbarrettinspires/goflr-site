/* Original illustrated Florida scenes for the sign-in page. To use real photos instead, add file names to PHOTOS. */
var PHOTOS = [];
(function () {
  var seed = 7;
  function rnd() { seed = (seed * 9301 + 49297) % 233280; return seed / 233280; }
  function palm(x, y, h, lean, c, s) {
    s = s || 1;
    var tx = x + lean, ty = y - h, o = '<path d="M' + x + ' ' + y + ' Q' + (x + lean * 0.2) + ' ' + (y - h * 0.5) + ' ' + tx + ' ' + ty + '" stroke="' + c + '" stroke-width="' + (9 * s) + '" fill="none" stroke-linecap="round"/>';
    [[-150, 10], [-120, -40], [-80, -70], [-30, -85], [30, -85], [80, -70], [120, -40], [150, 10]].forEach(function (a) {
      var ex = tx + a[0] * s, ey = ty + a[1] * s + 30 * s, cx = tx + a[0] * 0.5 * s, cy = ty + (a[1] - 30) * s;
      o += '<path d="M' + tx + ' ' + ty + ' Q' + cx + ' ' + cy + ' ' + ex + ' ' + ey + ' Q' + cx + ' ' + (cy + 22 * s) + ' ' + tx + ' ' + ty + 'Z" fill="' + c + '"/>';
    });
    return o;
  }
  function skyline(y, c, n, minH, maxH, x0, x1) {
    var o = '', x = x0;
    while (x < x1) {
      var w = 40 + rnd() * 70, h = minH + rnd() * (maxH - minH);
      o += '<rect x="' + x + '" y="' + (y - h) + '" width="' + w + '" height="' + h + '" fill="' + c + '"/>';
      if (rnd() > 0.7) o += '<rect x="' + (x + w / 2 - 2) + '" y="' + (y - h - 30) + '" width="4" height="30" fill="' + c + '"/>';
      x += w + rnd() * 8;
    }
    return o;
  }
  var cnt = 0;
  function wrap(defs, body) { cnt++; var u = function (t) { return t.replace(/id="(g|w)"/g, 'id="$1' + cnt + '"').replace(/url\(#(g|w)\)/g, 'url(#$1' + cnt + ')'); }; defs = u(defs); body = u(body); return '<svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg"><defs>' + defs + '</defs>' + body + '</svg>'; }
  function grad(id, stops) { return '<linearGradient id="' + id + '" x1="0" y1="0" x2="0" y2="1">' + stops.map(function (s, i) { return '<stop offset="' + s[0] + '" stop-color="' + s[1] + '"/>'; }).join('') + '</linearGradient>'; }

  var scenes = [];

  /* Tampa Bay skyline at sunset */
  scenes.push({ name: 'Tampa Bay', svg: (function () {
    seed = 11;
    var b = '<rect width="1600" height="900" fill="url(#g)"/><circle cx="1150" cy="520" r="110" fill="#ffe2b0" opacity=".9"/><circle cx="1150" cy="520" r="190" fill="#ffd39a" opacity=".25"/>';
    b += skyline(640, '#5b3a78', 0, 80, 260, 380, 1500);
    b += skyline(650, '#2c1f4a', 0, 50, 180, 300, 1600);
    // University-of-Tampa style minarets, generic Moorish domes
    [520, 600, 680].forEach(function (x) { b += '<rect x="' + (x - 6) + '" y="520" width="12" height="130" fill="#241a3d"/><path d="M' + (x - 14) + ' 524 Q' + x + ' 470 ' + (x + 14) + ' 524Z" fill="#241a3d"/>'; });
    b += '<rect y="650" width="1600" height="250" fill="url(#w)"/>';
    for (var i = 0; i < 10; i++) b += '<rect x="' + (1050 + i * 8) + '" y="' + (660 + i * 20) + '" width="' + (200 - i * 16) + '" height="6" rx="3" fill="#ffd9a0" opacity="' + (0.55 - i * 0.04) + '"/>';
    b += palm(180, 900, 520, 60, '#140f26', 1.5) + palm(1480, 900, 440, -50, '#140f26', 1.3) + palm(330, 900, 300, 30, '#140f26', 0.9);
    return wrap(grad('g', [[0, '#1b2a5a'], [0.5, '#e0527a'], [1, '#ffb36b']]) + grad('w', [[0, '#e2845f'], [1, '#2a2457']]), b);
  })() });

  /* Gulf Coast beach */
  scenes.push({ name: 'Florida Gulf Coast', svg: (function () {
    seed = 5;
    var b = '<rect width="1600" height="900" fill="url(#g)"/><circle cx="420" cy="300" r="80" fill="#fff6d6"/><circle cx="420" cy="300" r="150" fill="#fff6d6" opacity=".25"/>';
    b += '<ellipse cx="1100" cy="190" rx="170" ry="30" fill="#fff" opacity=".7"/><ellipse cx="1250" cy="215" rx="110" ry="22" fill="#fff" opacity=".6"/><ellipse cx="700" cy="140" rx="120" ry="20" fill="#fff" opacity=".55"/>';
    b += '<rect y="470" width="1600" height="430" fill="url(#w)"/>';
    for (var i = 0; i < 9; i++) b += '<path d="M0 ' + (520 + i * 36) + ' Q200 ' + (500 + i * 36) + ' 400 ' + (520 + i * 36) + ' T800 ' + (520 + i * 36) + ' T1200 ' + (520 + i * 36) + ' T1600 ' + (520 + i * 36) + '" stroke="#fff" stroke-opacity="' + (0.15 + i * 0.03) + '" stroke-width="3" fill="none"/>';
    b += '<path d="M1000 560 L1100 560 L1060 470 Z" fill="#fff"/><path d="M1050 560 L1050 440 L1020 560Z" fill="#f6f6f6"/><path d="M990 565 L1110 565 L1090 585 L1010 585Z" fill="#274b63"/>';
    b += '<path d="M0 760 Q400 690 900 740 T1600 700 L1600 900 L0 900Z" fill="#f3e2b8"/><path d="M0 800 Q500 760 1000 800 T1600 780 L1600 900 L0 900Z" fill="#e9d29c"/>';
    b += palm(1330, 800, 430, -90, '#1d4a3a', 1.5) + palm(1480, 820, 330, -40, '#245c47', 1.1) + palm(160, 840, 380, 70, '#1d4a3a', 1.3);
    return wrap(grad('g', [[0, '#4fb8e6'], [0.6, '#bfeaf5'], [1, '#fff0c8']]) + grad('w', [[0, '#7fd6d9'], [1, '#1d8fa3']]), b);
  })() });

  /* Miami art deco at dusk */
  scenes.push({ name: 'Miami Beach', svg: (function () {
    var b = '<rect width="1600" height="900" fill="url(#g)"/><circle cx="800" cy="520" r="130" fill="#ffd0a0" opacity=".8"/>';
    b += '<rect y="620" width="1600" height="280" fill="url(#w)"/>';
    var cols = ['#ffd7e6', '#c9f0ec', '#fff3c4', '#e0d4ff', '#ffe0cc'];
    for (var i = 0; i < 7; i++) {
      var x = 120 + i * 210, h = 230 + (i % 3) * 70, c = cols[i % 5];
      b += '<rect x="' + x + '" y="' + (700 - h) + '" width="170" height="' + h + '" rx="10" fill="' + c + '"/>';
      b += '<rect x="' + (x + 20) + '" y="' + (700 - h + 20) + '" width="130" height="10" fill="#ff5fa2" opacity=".75"/><rect x="' + (x + 20) + '" y="' + (700 - h + 44) + '" width="130" height="6" fill="#2ec4b6" opacity=".75"/>';
      for (var r = 0; r < 4; r++) for (var q = 0; q < 3; q++) b += '<rect x="' + (x + 24 + q * 42) + '" y="' + (700 - h + 80 + r * 50) + '" width="24" height="30" rx="3" fill="#3b2a5c" opacity=".55"/>';
      b += '<rect x="' + (x + 70) + '" y="' + (700 - h - 40) + '" width="30" height="40" fill="' + c + '"/>';
    }
    b += '<rect y="700" width="1600" height="40" fill="#2a2457"/><rect y="700" width="1600" height="5" fill="#ff5fa2"/>';
    b += palm(80, 900, 560, 40, '#1c1238', 1.6) + palm(1530, 900, 500, -60, '#1c1238', 1.5) + palm(1250, 900, 340, 30, '#1c1238', 1);
    return wrap(grad('g', [[0, '#2b1055'], [0.55, '#ff5fa2'], [1, '#ffc371']]) + grad('w', [[0, '#ff9a7a'], [1, '#2b1a55']]), b);
  })() });

  /* Mediterranean-style Florida estate */
  scenes.push({ name: 'Florida Luxury Homes', svg: (function () {
    var b = '<rect width="1600" height="900" fill="url(#g)"/><ellipse cx="300" cy="150" rx="160" ry="28" fill="#fff" opacity=".8"/><ellipse cx="1300" cy="200" rx="200" ry="32" fill="#fff" opacity=".7"/>';
    b += '<rect y="640" width="1600" height="260" fill="#6fae5c"/><rect y="760" width="1600" height="140" fill="#5d9a4e"/>';
    b += '<rect x="330" y="400" width="640" height="260" fill="#f2e3c8"/><rect x="900" y="340" width="360" height="320" fill="#efdcbc"/>';
    b += '<path d="M300 400 L450 300 L850 300 L1000 400Z" fill="#c8643c"/><path d="M870 340 L960 250 L1200 250 L1290 340Z" fill="#b95a35"/>';
    for (var i = 0; i < 14; i++) b += '<line x1="' + (350 + i * 46) + '" y1="320" x2="' + (330 + i * 48) + '" y2="400" stroke="#a84e2c" stroke-width="3" opacity=".6"/>';
    [380, 480, 640, 740].forEach(function (x) { b += '<path d="M' + x + ' 560 V470 Q' + (x + 30) + ' 430 ' + (x + 60) + ' 470 V560Z" fill="#3b5a6b"/><path d="M' + x + ' 560 V470 Q' + (x + 30) + ' 430 ' + (x + 60) + ' 470" stroke="#fff" stroke-width="5" fill="none"/>'; });
    b += '<path d="M540 660 V500 Q580 440 620 500 V660Z" fill="#7a4b2a"/><path d="M1000 600 V440 Q1050 390 1100 440 V600Z" fill="#3b5a6b"/><path d="M1130 600 V440 Q1180 390 1230 440 V600Z" fill="#3b5a6b"/>';
    b += '<ellipse cx="760" cy="800" rx="360" ry="60" fill="#46b6e0"/><ellipse cx="760" cy="796" rx="340" ry="48" fill="#8fdcf2" opacity=".7"/>';
    b += palm(170, 700, 450, 40, '#1f5a3e', 1.4) + palm(1420, 710, 480, -40, '#1f5a3e', 1.5) + palm(1300, 720, 300, 30, '#2d7050', 1);
    return wrap(grad('g', [[0, '#6cc3ee'], [1, '#e8f6ff']]), b);
  })() });

  /* Florida Keys bridge at sunrise */
  scenes.push({ name: 'Florida Keys', svg: (function () {
    seed = 3;
    var b = '<rect width="1600" height="900" fill="url(#g)"/><circle cx="1000" cy="500" r="100" fill="#fff0b8"/><circle cx="1000" cy="500" r="210" fill="#ffe08a" opacity=".25"/>';
    b += '<rect y="520" width="1600" height="380" fill="url(#w)"/>';
    for (var i = 0; i < 8; i++) b += '<rect x="' + (880 + i * 10) + '" y="' + (540 + i * 26) + '" width="' + (240 - i * 22) + '" height="6" rx="3" fill="#fff0b8" opacity="' + (0.6 - i * 0.06) + '"/>';
    b += '<path d="M0 560 Q400 500 800 540 T1600 520 L1600 580 L0 580Z" fill="#1f5560"/>';
    for (var p = 0; p < 22; p++) b += '<rect x="' + (p * 75) + '" y="' + (535 + Math.sin(p) * 4) + '" width="10" height="70" fill="#17434d"/>';
    b += '<path d="M0 540 L1600 520 L1600 536 L0 556Z" fill="#17434d"/>';
    for (var k = 0; k < 6; k++) b += '<path d="M' + (1100 + k * 70) + ' 300 q10 -14 22 0 q12 -14 22 0" stroke="#3a3a4a" stroke-width="3" fill="none" opacity=".7"/>';
    b += '<path d="M0 900 L0 760 Q60 700 120 760 Q170 690 230 770 Q300 700 360 780 L420 900Z" fill="#12362f"/><path d="M1600 900 L1600 740 Q1540 690 1480 750 Q1420 690 1360 760 Q1300 700 1250 790 L1200 900Z" fill="#12362f"/>';
    b += palm(1450, 800, 420, -70, '#0d2a25', 1.4);
    return wrap(grad('g', [[0, '#ff9a5a'], [0.45, '#ffd27a'], [1, '#aee3e4']]) + grad('w', [[0, '#6fd3d6'], [1, '#14707e']]), b);
  })() });

  /* Waterfront modern home */
  scenes.push({ name: 'Waterfront Living', svg: (function () {
    var b = '<rect width="1600" height="900" fill="url(#g)"/><circle cx="1300" cy="180" r="70" fill="#fff8dc"/>';
    b += '<rect y="600" width="1600" height="300" fill="url(#w)"/>';
    b += '<rect x="260" y="330" width="720" height="130" fill="#f6f6f4"/><rect x="180" y="440" width="860" height="170" fill="#ffffff"/><rect x="170" y="430" width="880" height="14" fill="#2f3b45"/><rect x="250" y="320" width="740" height="14" fill="#2f3b45"/>';
    for (var i = 0; i < 6; i++) b += '<rect x="' + (210 + i * 135) + '" y="465" width="115" height="130" fill="#6fa7c6" opacity=".75"/>';
    for (var j = 0; j < 4; j++) b += '<rect x="' + (300 + j * 170) + '" y="345" width="140" height="95" fill="#6fa7c6" opacity=".7"/>';
    b += '<rect x="200" y="610" width="800" height="20" fill="#cdbba0"/><rect x="1000" y="620" width="520" height="10" fill="#8a6b4a"/>';
    for (var d = 0; d < 6; d++) b += '<rect x="' + (1040 + d * 90) + '" y="620" width="8" height="50" fill="#6a4f35"/>';
    b += '<path d="M1180 600 L1420 600 L1380 650 L1220 650Z" fill="#f8f8f8"/><rect x="1298" y="500" width="5" height="100" fill="#555"/><path d="M1303 505 L1380 590 L1303 590Z" fill="#fff"/>';
    for (var r = 0; r < 6; r++) b += '<rect x="' + (240 + r * 40) + '" y="' + (690 + r * 22) + '" width="' + (500 - r * 60) + '" height="5" rx="2" fill="#fff" opacity=".35"/>';
    b += palm(90, 700, 420, 50, '#1f5a3e', 1.4) + palm(1530, 700, 380, -40, '#245c47', 1.2);
    return wrap(grad('g', [[0, '#58b5ea'], [1, '#d9f1fb']]) + grad('w', [[0, '#5ec4cf'], [1, '#1d7f98']]), b);
  })() });

  window.FLR_SCENES = scenes;
})();
