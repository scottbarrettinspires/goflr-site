<?php
// Reached from the "Closing Prep" step: confirm the closing date and time, mark the file clear to close, and upload the
// signed Buyer Preclosing Walk Through Form once the buyer has signed it.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php";
require __DIR__ . "/sigtools.php";
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$err = ''; $msg = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'savedates') {
    dl_handle_save($me, 'buyer', $tx, 'closing_prep.php?tx=' . urlencode($tx), ['closing']);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'closingmeta') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else { dl_set_closing_meta($me, 'buyer', $tx, (string)($_POST['closing_time'] ?? ''), !empty($_POST['clear'])); header('Location: closing_prep.php?tx=' . urlencode($tx) . '&saved=1'); exit; }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['op'] ?? '') === 'uploadwalkthrough') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    elseif (empty($_FILES['walkfile']['tmp_name']) || !is_uploaded_file($_FILES['walkfile']['tmp_name'])) { $err = 'Please choose the signed Walk Through form to upload.'; }
    elseif ($_FILES['walkfile']['size'] > 20 * 1024 * 1024) { $err = 'That file is larger than 20 MB — please upload a smaller copy.'; }
    else {
        $b64 = base64_encode((string)file_get_contents($_FILES['walkfile']['tmp_name']));
        $ext = strtolower(pathinfo($_FILES['walkfile']['name'], PATHINFO_EXTENSION)) ?: 'pdf';
        $r = sg_script_post(['op' => 'save', 'email' => $me, 'folderId' => $tx, 'name' => 'Buyer Preclosing Walk Through - ' . gmdate('Y-m-d') . '.' . $ext, 'b64' => $b64]);
        if (is_array($r) && !empty($r['ok'])) { $msg = 'Uploaded ' . $r['name'] . '.'; } else { $err = 'Could not upload' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.'; }
    }
}
$meta = dl_closing_meta($tx);
$rec = dl_get($tx); $closingItem = (array)($rec['data']['items']['closing'] ?? []); $closingDate = (string)($closingItem['date'] ?? '');
require_once __DIR__ . "/subpage.php";
sp_head('Closing Prep', $tx);
?>
<div class="panel sigbox">
<?php if (!empty($_GET['saved'])): ?><p class="note">Saved.</p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
<?php if ($msg): ?><p class="note"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
  <h2 style="margin-top:0">Closing date, time and clear-to-close</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>"><input type="hidden" name="op" value="savedates">
    <p><label>Closing date<input type="date" name="dl_closing" value="<?= htmlspecialchars($closingDate) ?>"></label></p>
    <p><button type="submit" class="btn">Save closing date</button></p>
  </form>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>"><input type="hidden" name="op" value="closingmeta">
    <p><label>Closing time<input type="text" name="closing_time" value="<?= htmlspecialchars($meta['time']) ?>" placeholder="e.g. 1:00 PM" style="max-width:160px"></label></p>
    <p><label class="wr"><input type="checkbox" name="clear" value="1"<?= $meta['clear'] ? ' checked' : '' ?>> Clear to close</label></p>
    <p><button type="submit" class="btn">Save</button></p>
  </form>
</div>
<div class="panel sigbox">
  <h2 style="margin-top:0">Buyer Pre-Closing Final Inspection (Walk Through)</h2>
  <p class="note">Send the form to the buyer for e-signature, or, if it was already signed outside this system, upload the signed copy below. Either way it's saved straight into this transaction's folder.</p>
  <p><a class="btn" href="sign.php?f=138uML5GUJgxwVQ_Wu3XWTt9NbYwkqsJ6&s=b&tx=<?= urlencode($tx) ?>">Send the Walk Through form for signature</a></p>
  <form method="post" enctype="multipart/form-data" style="margin-top:14px">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>"><input type="hidden" name="op" value="uploadwalkthrough">
    <p><label>Or upload an already-signed copy<input type="file" name="walkfile" accept=".pdf,.jpg,.jpeg,.png"></label></p>
    <p><button type="submit" class="btn ghost">Upload signed Walk Through form</button></p>
  </form>
  <p><a class="btn ghost" href="https://drive.google.com/drive/folders/<?= urlencode($tx) ?>" target="_blank" rel="noopener">Open the transaction folder</a></p>
</div>
<?php sp_foot(); ?>
