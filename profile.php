<?php
// Profile (Settings > Profile): your headshot, your logos (agents: color and white), and your details from Agent Records.
// Pictures can be uploaded, replaced or removed here; the details are kept by the office in Agent Records.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php"; // flr_is_broker()
require_once __DIR__ . "/profilelib.php";
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$me = strtolower($_SESSION['email'] ?? '');
$isStaff = flr_is_broker();
$kinds = $isStaff ? ['headshot'] : ['headshot', 'logo', 'logow'];
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $k = (string)($_POST['kind'] ?? '');
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { $err = 'That picture is too large to upload. Please choose a smaller one.'; }
    elseif (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    elseif (!in_array($k, $kinds, true)) { $err = 'Unknown picture.'; }
    elseif (($_POST['a'] ?? '') === 'remove') { pf_remove($me, $k); header('Location: profile.php?ok=removed#' . $k); exit; }
    else { $err = pf_save_upload($me, $k, $_FILES['pic'] ?? []); if ($err === '') { header('Location: profile.php?ok=saved#' . $k); exit; } }
}
$rec = pf_agent_record($me); $F = $rec['fields'] ?? [];
$name = trim((string)($F['full_name'] ?? '')) ?: trim((string)($_SESSION['name'] ?? '')) ?: $me;
$ini = ''; foreach (preg_split('/\s+/', $name) as $w) { if ($w !== '' && mb_strlen($ini) < 2) $ini .= mb_strtoupper(mb_substr($w, 0, 1)); }
// Current headshot: the one uploaded here, else the roster photo from sign-in.
$sessPic = (string)($_SESSION['pic'] ?? ''); if ($sessPic === 'photo.php') $sessPic = 'photo.php?v=' . (int)@filemtime(flr_photo_path($me));
$head = flr_headshot_url($me, $sessPic);
$h = function ($x) { return htmlspecialchars((string)$x); };
$csrfIn = '<input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '">';
// Details shown (read-only). Private items -- pay, splits, W-9, ID and insurance copies, notes -- are left off.
$md = function ($v) { $v = trim((string)$v); if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $v, $m)) return date('F j', mktime(0, 0, 0, (int)$m[1], (int)$m[2], 2000)); return $v; };
$dt = function ($v) { $v = trim((string)$v); $t = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? strtotime($v) : false; return $t ? date('M j, Y', $t) : $v; };
$addr = trim(implode(', ', array_filter([trim((string)($F['street_address'] ?? '')), trim((string)($F['city'] ?? '')), trim(trim((string)($F['state'] ?? '')) . ' ' . trim((string)($F['zip'] ?? '')))])));
$rows = $isStaff && !$rec ? [
    'Name' => $name, 'Email' => $me, 'Position' => (string)($_SESSION['staff'] ?? 'Staff'),
] : [
    'Full name' => $F['full_name'] ?? $name, 'Preferred name' => $F['preferred_name'] ?? '', 'License number' => $F['license_number'] ?? '',
    'License expiration' => $dt($F['license_expiration'] ?? ''), 'NRDS number' => $F['nrds_number'] ?? '', 'Email' => $F['email'] ?? $me,
    'Phone' => $F['phone'] ?? '', 'Mailing address' => $addr, 'Office' => $F['office'] ?? '', 'Team' => $F['team_and_team_leader'] ?? '',
    'Realtor board' => $F['realtor_board_name'] ?? ($F['realtor_board'] ?? ''), 'MLS' => $F['mls_name'] ?? '', 'MLS office ID' => $F['mls_office_id'] ?? '',
    'Date hired' => $dt($F['date_hired'] ?? ''), 'Birthday' => $md($F['birthdate'] ?? ''), 'Social media' => $F['social_media_handles'] ?? '',
];
sp_head('Profile');
?>
<style>
.pfw{max-width:820px}
.pfc{background:var(--white);border:1px solid var(--line);border-radius:14px;padding:18px 20px;margin:0 0 16px}
.pfc h2{font-size:1.05rem;margin:0 0 4px}.pfc .sub{color:#5a6a6c;font-size:.9rem;margin:0 0 14px}
.pftop{display:flex;gap:20px;align-items:center;flex-wrap:wrap}
.pfav{position:relative;width:132px;height:132px;border-radius:50%;overflow:hidden;background:var(--teal-soft);color:var(--teal);display:flex;align-items:center;justify-content:center;font:700 2.6rem/1 system-ui,sans-serif;flex:0 0 auto;box-shadow:0 4px 16px rgba(3,111,114,.15)}
.pfav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.pfname{font-size:1.4rem;font-weight:800;margin:0}.pfrole{color:#5a6a6c;margin:2px 0 12px}
.pfacts{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.pfacts form{display:inline}
.pfbtn{font:inherit;font-size:.86rem;font-weight:600;padding:7px 13px;border-radius:9px;border:1px solid var(--line);background:#fff;color:var(--teal);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.pfbtn:hover{border-color:var(--teal)}.pfbtn.pri{background:var(--teal);color:#fff;border-color:var(--teal)}.pfbtn.del{color:#c0392b}.pfbtn input{display:none}
.pflogos{display:grid;grid-template-columns:1fr 1fr;gap:14px}@media(max-width:640px){.pflogos{grid-template-columns:1fr}}
.pflogo{border:1px solid var(--line);border-radius:12px;overflow:hidden;display:flex;flex-direction:column}
.pfbox{height:150px;display:flex;align-items:center;justify-content:center;padding:14px;box-sizing:border-box}
.pfbox img{max-width:100%;max-height:100%;object-fit:contain}
.pfbox.light{background:#fff;background-image:linear-gradient(45deg,#f3f6f6 25%,transparent 25%,transparent 75%,#f3f6f6 75%),linear-gradient(45deg,#f3f6f6 25%,transparent 25%,transparent 75%,#f3f6f6 75%);background-size:20px 20px;background-position:0 0,10px 10px}
.pfbox.dark{background:#036f72}.pfbox .none{color:#8a9a9c;font-size:.9rem}.pfbox.dark .none{color:#cfe6e6}
.pflogo .bar{padding:10px 12px;border-top:1px solid var(--line);display:flex;flex-wrap:wrap;gap:8px;align-items:center}.pflogo .bar b{flex:1 1 100%;font-size:.92rem}
.pfdl{display:grid;grid-template-columns:200px 1fr;gap:0;margin:0}.pfdl dt,.pfdl dd{padding:9px 0;border-top:1px solid var(--line);margin:0}.pfdl dt{color:#5a6a6c;font-size:.9rem}.pfdl dd{font-weight:600;overflow-wrap:anywhere}
.pfdl dd.empty{color:#9aa7a9;font-weight:400}
@media(max-width:560px){.pfdl{grid-template-columns:1fr}.pfdl dd{border-top:0;padding-top:0}}
</style>
<div class="pfw">
<?php if ($err): ?>  <p class="sigerr"><?= $h($err) ?></p>
<?php elseif (!empty($_GET['ok'])): ?>  <p class="note"><?= $_GET['ok'] === 'removed' ? 'Picture removed.' : 'Saved. Your new picture shows across the back office now.' ?></p>
<?php endif; ?>
  <div class="pfc" id="headshot">
    <div class="pftop">
      <span class="pfav"><span><?= $h($ini) ?></span><?php if ($head !== ''): ?><img src="<?= $h($head) ?>" alt="Your headshot" onerror="this.remove()"><?php endif; ?></span>
      <div style="flex:1;min-width:220px">
        <p class="pfname"><?= $h($name) ?></p>
        <p class="pfrole"><?= $h($isStaff ? (string)($_SESSION['staff'] ?? 'Staff') : 'Agent') ?> &middot; Florida Luxury Realty</p>
        <div class="pfacts">
          <form method="post" enctype="multipart/form-data"><?= $csrfIn ?><input type="hidden" name="kind" value="headshot"><label class="pfbtn pri"><?= $head !== '' ? 'Replace headshot' : 'Upload headshot' ?><input type="file" name="pic" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()"></label></form>
<?php if (pf_has($me, 'headshot')): ?>
          <form method="post" onsubmit="return confirm('Remove the headshot you uploaded? Your office photo will show instead, if there is one.')"><?= $csrfIn ?><input type="hidden" name="kind" value="headshot"><input type="hidden" name="a" value="remove"><button class="pfbtn del">Remove</button></form>
<?php endif; ?>
        </div>
        <p style="font-size:.8rem;color:#5a6a6c;margin:10px 0 0">A clear, front-facing photo works best. It's cropped to a square and shows on Home, the Directory, Messages and the Collaboration Lounge.</p>
      </div>
    </div>
  </div>

<?php if (!$isStaff): ?>
  <div class="pfc" id="logos">
    <h2>Your logo</h2>
    <p class="sub">Your personal logo for marketing: one in color for light backgrounds and one in white for dark backgrounds. PNG with a transparent background works best.</p>
    <div class="pflogos">
<?php foreach (['logo' => ['Color logo', 'light'], 'logow' => ['White logo', 'dark']] as $k => list($lbl, $bg)): $u = pf_url($me, $k); ?>
      <div class="pflogo" id="<?= $k ?>">
        <div class="pfbox <?= $bg ?>"><?= $u !== '' ? '<img src="' . $h($u) . '" alt="' . $h($lbl) . '">' : '<span class="none">No ' . strtolower($lbl) . ' yet</span>' ?></div>
        <div class="bar"><b><?= $h($lbl) ?></b>
          <form method="post" enctype="multipart/form-data"><?= $csrfIn ?><input type="hidden" name="kind" value="<?= $k ?>"><label class="pfbtn<?= $u === '' ? ' pri' : '' ?>"><?= $u !== '' ? 'Replace' : 'Upload' ?><input type="file" name="pic" accept="image/png,image/jpeg,image/webp" onchange="this.form.submit()"></label></form>
<?php if ($u !== ''): ?>
          <a class="pfbtn" href="<?= $h($u) ?>&amp;dl=1">Download</a>
          <form method="post" onsubmit="return confirm('Remove your <?= strtolower($lbl) ?>?')"><?= $csrfIn ?><input type="hidden" name="kind" value="<?= $k ?>"><input type="hidden" name="a" value="remove"><button class="pfbtn del">Remove</button></form>
<?php endif; ?>
        </div>
      </div>
<?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

  <div class="pfc" id="details">
    <h2>Your details</h2>
    <p class="sub"><?= $isStaff && !$rec ? 'From your staff sign-in.' : 'From your Agent Records file with the office. Something wrong or out of date? <a href="messages.php">Message the office</a> and they\'ll update it.' ?></p>
<?php if (!$rec && !$isStaff): ?>
    <p class="note">We couldn't find your Agent Records file yet. Please <a href="messages.php">message the office</a>.</p>
<?php else: ?>
    <dl class="pfdl">
<?php foreach ($rows as $lbl => $v): $v = trim((string)$v); ?>
      <dt><?= $h($lbl) ?></dt><dd<?= $v === '' ? ' class="empty"' : '' ?>><?= $v === '' ? 'Not on file' : nl2br($h($v)) ?></dd>
<?php endforeach; ?>
    </dl>
<?php endif; ?>
  </div>
</div>
<?php sp_foot();
