<?php require __DIR__ . "/guard.php"; require_once __DIR__ . "/dlib.php"; require_once __DIR__ . "/formslib.php";
// Forms (agents: Active Business > Forms; staff: Settings > Forms). Built from the Forms library on the server (formslib.php).
// Staff see the page exactly as agents do, plus "Edit forms" to add, edit, move or delete forms and sections.
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$isStaff = flr_is_broker(); $by = strtolower($_SESSION['email'] ?? '');
$editing = $isStaff && !empty($_GET['edit']);
$err = '';
if ($isStaff && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'forms.php?edit=1' . (preg_match('/^[a-z]+$/', (string)($_POST['cat'] ?? '')) ? '#' . $_POST['cat'] : '');
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { $err = 'That PDF is too large to upload. Please choose a smaller one.'; }
    elseif (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $a = (string)($_POST['a'] ?? '');
        if ($a === 'item') $err = fm_save_item((int)($_POST['id'] ?? 0), (int)($_POST['section'] ?? 0), $_POST['name'] ?? '', $_POST['link'] ?? '', $_FILES['pdf'] ?? [], $by);
        elseif ($a === 'delitem') $err = fm_delete_item((int)($_POST['id'] ?? 0), $by);
        elseif ($a === 'section') $err = fm_save_section((int)($_POST['id'] ?? 0), (string)($_POST['cat'] ?? ''), $_POST['name'] ?? '', $by);
        elseif ($a === 'delsection') $err = fm_delete_section((int)($_POST['id'] ?? 0), $by);
        elseif ($a === 'up' || $a === 'down') fm_move_section((int)($_POST['id'] ?? 0), $a === 'up' ? -1 : 1);
        if ($err === '') { header('Location: ' . $back . (strpos($back, '#') === false ? '' : '') . ''); $_SESSION['fm_ok'] = $a; exit; }
    }
    $editing = true;
}
$ok = $_SESSION['fm_ok'] ?? ''; unset($_SESSION['fm_ok']);
$perm = $_SESSION['perm'] ?? [];
$all = fm_all();
// Which category tabs show: Buyer, Seller, Agent for everyone; Commercial / Leasing for agents with that permission (staff see all).
$cats = []; foreach (FM_CATS as $k => $lbl) { if (in_array($k, ['commercial', 'leasing'], true) && !$isStaff && empty($perm[$k])) continue; $cats[$k] = $lbl; }
$h = function ($x) { return htmlspecialchars((string)$x); };
$csrfIn = '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf']) . '">';
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
<title>Forms - FLR Agent Back Office</title>
<link rel="stylesheet" href="style.css?v=11">
</head>
<body>
<header class="top"><div class="in">
  <a class="brand" href="home.php"><img src="logo-white.png" alt="Florida Luxury Realty"><span>Agent Back Office</span></a>
  <nav>
    <a href="home.php">Home</a>
    <?php if (!flr_is_broker()): ?>
    <a href="prospecting.php">Prospecting</a>
    <a href="leads.php">Leads &amp; Contacts</a>
    <a href="my-transactions.php" aria-current="page">Active Business</a>
    <?php endif; ?>
    <a href="directory.php">Directory</a>
    <?php if (flr_is_broker()): ?><a href="recruiting.php">Recruiting</a> <a href="mls_data.php">MLS Data</a><?php endif; ?>
    <a href="logout.php">Sign out</a>
  </nav>
</div></header>

<section class="hero">
  <p style="margin:0 0 4px"><?= $isStaff ? '<a href="settings.php">&larr; Settings</a>' : '<a href="my-transactions.php">&larr; Active Business</a>' ?></p>
  <h1>Forms</h1>
  <p><?= $editing ? 'Editing the forms agents see in Active Business &rsaquo; Forms. Changes show for every agent right away.' : 'Choose a category, find your form, and download it. Then open the PDF to fill it in.' ?></p>
</section>
<style>
.fmbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 14px}.fmbar .sp{flex:1}
.fmbtn{font:inherit;font-size:.82rem;font-weight:600;padding:6px 11px;border-radius:9px;border:1px solid var(--line);background:#fff;color:var(--teal);cursor:pointer;text-decoration:none;white-space:nowrap}
.fmbtn:hover{border-color:var(--teal)}.fmbtn.del{color:#c0392b}.fmbtn.del:hover{border-color:#c0392b}.fmbtn.pri{background:var(--teal);color:#fff;border-color:var(--teal)}
.fsec h2 .fmacts{margin-left:auto;display:flex;gap:6px;flex-wrap:wrap}.fsec h2 .fmacts form{display:inline}
.frow .fmacts{display:flex;gap:6px}.frow .fmacts form{display:inline}
.fmsrc{font-size:.72rem;font-weight:600;color:#5a6a6c;background:#eef3f3;border-radius:99px;padding:2px 8px;white-space:nowrap}
.fmedit .frow{border-style:dashed}
dialog.fmdlg{border:0;border-radius:16px;padding:0;width:min(520px,calc(100vw - 32px));box-shadow:0 20px 60px rgba(0,0,0,.25)}
dialog.fmdlg::backdrop{background:rgba(15,30,32,.45)}
.fmdlg form{padding:20px 22px}.fmdlg h2{margin:0 0 14px;font-size:1.15rem}
.fmdlg label{display:block;font-size:.88rem;color:#455355;margin:0 0 12px}.fmdlg input[type=text],.fmdlg select{display:block;width:100%;box-sizing:border-box;margin-top:4px;padding:9px 11px;border:1px solid var(--line);border-radius:9px;font:inherit;background:#fff}
.fmdlg input[type=file]{display:block;margin-top:6px;font:inherit;font-size:.88rem}
.fmor{display:flex;align-items:center;gap:10px;color:#5a6a6c;font-size:.82rem;margin:-2px 0 12px}.fmor:before,.fmor:after{content:"";flex:1;border-top:1px solid var(--line)}
.fmhint{font-size:.8rem;color:#5a6a6c;margin:4px 0 0}
.fmdlg .acts{display:flex;justify-content:flex-end;gap:8px;margin-top:6px}
</style>
<main class="wrap<?= $editing ? ' fmedit' : '' ?>">
<?php if ($err): ?>  <p class="sigerr"><?= $h($err) ?></p>
<?php elseif ($ok): ?>  <p class="note"><?= $h(['item' => 'Form saved.', 'delitem' => 'Form deleted.', 'section' => 'Section saved.', 'delsection' => 'Section deleted.', 'up' => 'Section moved.', 'down' => 'Section moved.'][$ok] ?? 'Saved.') ?></p>
<?php endif; ?>
<?php if ($isStaff): ?>
  <div class="fmbar">
<?php if ($editing): ?>
    <strong style="color:#8a5a0e">&#9998; Editing forms</strong><span class="sp"></span>
    <a class="fmbtn" href="forms.php" onclick="this.href='forms.php'+location.hash">Done editing</a>
<?php else: ?>
    <span style="color:#5a6a6c;font-size:.9rem">This is the Forms page agents see.</span><span class="sp"></span>
    <a class="fmbtn pri" href="forms.php?edit=1" onclick="this.href='forms.php?edit=1'+location.hash">&#9998; Edit forms</a>
<?php endif; ?>
  </div>
<?php endif; ?>
<?php if (!$editing): ?>
  <div class="tips">
    <div><b>1</b><span>Pick a category: <?= $h(implode(', ', $cats)) ?>.</span></div>
    <div><b>2</b><span>Click Download to save the form.</span></div>
    <div><b>3</b><span>Open the downloaded PDF to fill in the fields.</span></div>
  </div>
<?php endif; ?>
  <div class="ftabs" role="tablist">
<?php foreach ($cats as $k => $lbl): $n = 0; foreach ($all[$k] as $sec) $n += count($sec['forms']); ?>
    <button type="button" class="ftab" data-k="<?= $k ?>"><?= $h($lbl) ?><?= $n ? ' <span>' . $n . '</span>' : '' ?></button>
<?php endforeach; ?>
  </div>
  <div class="fsearch"><input id="fq" type="search" placeholder="Search this category" autocomplete="off"></div>
<?php foreach ($cats as $k => $lbl): $secs = $all[$k]; ?>
  <div class="fpanel" id="p-<?= $k ?>" hidden>
<?php if ($k === 'buyer'): ?>    <p class="fwiz"><a class="btn" href="wizard.php">Contract Forms Wizard: build a buyer contract packet</a></p>
<?php endif; ?>
<?php if (!$secs || (!$editing && !array_sum(array_map(function ($s) { return count($s['forms']); }, $secs)))): ?>
<?php if (!$editing): ?>    <p class="fsoon"><?= $h($lbl) ?> forms are coming soon. Need one now? Contact the office.</p>
<?php else: ?>    <p class="fsoon">No sections in <?= $h($lbl) ?> yet. Add one below.</p>
<?php endif; endif; ?>
<?php foreach ($secs as $si => $sec): if (!$editing && !$sec['forms']) continue; ?>
    <section class="fsec"><h2><?= $h($sec['name']) ?> <span><?= count($sec['forms']) ?></span>
<?php if ($editing): ?>
      <span class="fmacts">
        <button type="button" class="fmbtn" data-add="<?= $sec['id'] ?>">+ Add form</button>
        <button type="button" class="fmbtn" data-ren="<?= $sec['id'] ?>" data-name="<?= $h($sec['name']) ?>">Rename</button>
        <?php if ($si > 0): ?><form method="post"><?= $csrfIn ?><input type="hidden" name="a" value="up"><input type="hidden" name="cat" value="<?= $k ?>"><input type="hidden" name="id" value="<?= $sec['id'] ?>"><button class="fmbtn" title="Move up">&uarr;</button></form><?php endif; ?>
        <?php if ($si < count($secs) - 1): ?><form method="post"><?= $csrfIn ?><input type="hidden" name="a" value="down"><input type="hidden" name="cat" value="<?= $k ?>"><input type="hidden" name="id" value="<?= $sec['id'] ?>"><button class="fmbtn" title="Move down">&darr;</button></form><?php endif; ?>
        <form method="post" onsubmit="return confirm('Delete the section &quot;<?= $h(addslashes($sec['name'])) ?>&quot;?')"><?= $csrfIn ?><input type="hidden" name="a" value="delsection"><input type="hidden" name="cat" value="<?= $k ?>"><input type="hidden" name="id" value="<?= $sec['id'] ?>"><button class="fmbtn del">Delete section</button></form>
      </span>
<?php endif; ?>
    </h2><div class="flist">
<?php if (!$sec['forms']): ?>      <p class="fmhint" style="margin:0 0 4px">No forms in this section yet.</p>
<?php endif; ?>
<?php foreach ($sec['forms'] as $f): ?>
      <div class="frow" data-t="<?= $h(strtolower($f['name'])) ?>"><span class="fic"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 7V3.5L18.5 9H13z"/></svg></span><span class="fnm"><?= $h($f['name']) ?></span>
<?php if ($editing): ?>        <span class="fmsrc"><?= $f['file'] !== '' ? 'Uploaded PDF' : 'Google Drive' ?></span>
<?php endif; ?>
        <a class="fv" target="_blank" rel="noopener" href="<?= $h(fm_view_url($f)) ?>">View</a><a class="fd" href="<?= $h(fm_dl_url($f)) ?>">Download</a>
<?php if ($editing): ?>        <span class="fmacts"><button type="button" class="fmbtn" data-edit="<?= (int)$f['id'] ?>" data-sec="<?= $sec['id'] ?>" data-name="<?= $h($f['name']) ?>" data-link="<?= $f['drive_id'] !== '' ? $h('https://drive.google.com/file/d/' . $f['drive_id'] . '/view') : '' ?>" data-up="<?= $f['file'] !== '' ? '1' : '' ?>">Edit</button>
          <form method="post" onsubmit="return confirm('Delete &quot;<?= $h(addslashes($f['name'])) ?>&quot; from the Forms page?')"><?= $csrfIn ?><input type="hidden" name="a" value="delitem"><input type="hidden" name="cat" value="<?= $k ?>"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="fmbtn del">Delete</button></form></span>
<?php endif; ?>
      </div>
<?php endforeach; ?>
    </div></section>
<?php endforeach; ?>
<?php if ($editing): ?>
    <p style="margin:22px 0 0"><button type="button" class="fmbtn" data-newsec="<?= $k ?>">+ Add a section to <?= $h($lbl) ?></button></p>
<?php endif; ?>
  </div>
<?php endforeach; ?>
  <p class="note" id="fnone" hidden>No forms match your search.</p>
<?php if ($editing): ?>
  <dialog class="fmdlg" id="dlgItem"><form method="post" enctype="multipart/form-data"><?= $csrfIn ?><input type="hidden" name="a" value="item"><input type="hidden" name="id" value="0"><input type="hidden" name="cat" value="">
    <h2>Add a form</h2>
    <label>Form name <input type="text" name="name" maxlength="140" required placeholder="e.g. Property Repairs Addendum"></label>
    <label>Section <select name="section" required>
<?php foreach (FM_CATS as $k => $lbl): if (!$all[$k]) continue; ?>
      <optgroup label="<?= $h($lbl) ?>"><?php foreach ($all[$k] as $sec): ?><option value="<?= $sec['id'] ?>" data-cat="<?= $k ?>"><?= $h($sec['name']) ?></option><?php endforeach; ?></optgroup>
<?php endforeach; ?>
    </select></label>
    <label>Google Drive link to the PDF <input type="text" name="link" placeholder="https://drive.google.com/file/d/..."><span class="fmhint">In Drive: right-click the PDF &rsaquo; Share &rsaquo; Copy link. Anyone at FLR must be able to open it.</span></label>
    <div class="fmor">or upload the PDF</div>
    <label>PDF file <input type="file" name="pdf" accept="application/pdf,.pdf"><span class="fmhint" data-keep hidden>Leave both empty to keep the current file.</span></label>
    <div class="acts"><button type="button" class="fmbtn" data-close>Cancel</button><button class="fmbtn pri">Save form</button></div>
  </form></dialog>
  <dialog class="fmdlg" id="dlgSec"><form method="post"><?= $csrfIn ?><input type="hidden" name="a" value="section"><input type="hidden" name="id" value="0"><input type="hidden" name="cat" value="">
    <h2>Add a section</h2>
    <label>Section name <input type="text" name="name" maxlength="80" required placeholder="e.g. Buyer Disclosures"></label>
    <div class="acts"><button type="button" class="fmbtn" data-close>Cancel</button><button class="fmbtn pri">Save section</button></div>
  </form></dialog>
  <script>
  (function(){var di=document.getElementById("dlgItem"),ds=document.getElementById("dlgSec"),fi=di.querySelector("form"),fs=ds.querySelector("form");
  function cur(){return (location.hash||"#buyer").slice(1);}
  document.addEventListener("click",function(e){var b=e.target.closest("[data-add],[data-edit],[data-ren],[data-newsec],[data-close]");if(!b)return;
    if(b.hasAttribute("data-close")){b.closest("dialog").close();return;}
    if(b.dataset.add||b.dataset.edit){fi.reset();var ed=!!b.dataset.edit;fi.id.value=ed?b.dataset.edit:0;fi.cat.value=cur();fi.section.value=b.dataset.add||b.dataset.sec;
      fi.querySelector("h2").textContent=ed?"Edit form":"Add a form";if(ed){fi.name.value=b.dataset.name;fi.link.value=b.dataset.link||"";}
      fi.querySelector("[data-keep]").hidden=!ed;fi.link.required=false;di.showModal();fi.name.focus();return;}
    fs.reset();fs.cat.value=b.dataset.newsec||cur();fs.id.value=b.dataset.ren||0;fs.querySelector("h2").textContent=b.dataset.ren?"Rename section":"Add a section";if(b.dataset.ren)fs.name.value=b.dataset.name;ds.showModal();fs.name.focus();});
  fi.addEventListener("submit",function(e){var up=fi.pdf.files[0];if(up&&up.size>25000000){e.preventDefault();alert("That PDF is too large (25 MB max).");return;}
    if(!+fi.id.value&&!up&&!fi.link.value.trim()){e.preventDefault();alert("Paste the form's Google Drive link or choose a PDF to upload.");return;}
    if(up&&fi.link.value.trim()&&!confirm("You gave both a Drive link and a PDF. The uploaded PDF will be used. Continue?"))e.preventDefault();});
  })();
  </script>
<?php endif; ?>
</main>

<footer>Florida Luxury Realty, Inc. &middot; Agent access only.</footer>
<script>
(function(){
var tabs=[].slice.call(document.querySelectorAll(".ftab")),q=document.getElementById("fq"),none=document.getElementById("fnone"),cur="";
function panel(){return document.getElementById("p-"+cur);}
function filter(){var p=panel();if(!p)return;var v=q.value.trim().toLowerCase(),t=0,rows=[].slice.call(p.querySelectorAll(".frow"));rows.forEach(function(r){var m=!v||r.getAttribute("data-t").indexOf(v)>-1;r.style.display=m?"":"none";if(m)t++;});[].slice.call(p.querySelectorAll(".fsec")).forEach(function(s){s.style.display=(!v||s.querySelector('.frow:not([style*="none"])'))?"":"none";});none.hidden=!(v&&rows.length&&t===0);}
function show(k){if(!document.getElementById("p-"+k))k="buyer";cur=k;tabs.forEach(function(b){b.classList.toggle("on",b.getAttribute("data-k")===k);});[].slice.call(document.querySelectorAll(".fpanel")).forEach(function(p){p.hidden=p.id!=="p-"+k;});q.value="";filter();try{history.replaceState(null,"","#"+k);}catch(e){}}
tabs.forEach(function(b){b.addEventListener("click",function(){show(b.getAttribute("data-k"));});});
q.addEventListener("input",filter);
show((location.hash||"").slice(1)||"buyer");
})();
</script>
</body>
</html>
