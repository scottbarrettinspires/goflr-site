<?php
// Reached from the "Close Transaction" step: upload the final signed closing statement and the signed Walk Through form
// (usually signed in person at the final walkthrough), then mark the record's pipeline stage Close Transaction.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/pstage.php";
require_once __DIR__ . "/sigtools.php";
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$err = ''; $msg = '';
function ct_upload($me, $tx, $field, $label) {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) return [false, ''];
    if ($_FILES[$field]['size'] > 20 * 1024 * 1024) return [false, 'That file is larger than 20 MB — please upload a smaller copy.'];
    $b64 = base64_encode((string)file_get_contents($_FILES[$field]['tmp_name']));
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION)) ?: 'pdf';
    $r = sg_script_post(['op' => 'save', 'email' => $me, 'folderId' => $tx, 'name' => $label . ' - ' . gmdate('Y-m-d') . '.' . $ext, 'b64' => $b64]);
    return (is_array($r) && !empty($r['ok'])) ? [true, 'Uploaded ' . $r['name'] . '.'] : [false, 'Could not upload ' . $label . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.'];
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $any = false; $notes = [];
        list($ok1, $n1) = ct_upload($me, $tx, 'closingstmt', 'Final Closing Statement'); if ($n1 !== '') { $notes[] = $n1; } if ($ok1) $any = true;
        list($ok2, $n2) = ct_upload($me, $tx, 'walkfile', 'Buyer Preclosing Walk Through - Signed'); if ($n2 !== '') { $notes[] = $n2; } if ($ok2) $any = true;
        if (!$any && !$notes) { $err = 'Please choose at least one file to upload.'; }
        else {
            $msg = implode(' ', $notes);
            if ($any) { ps_set($me, $tx, 'buyer', 'closed'); $msg .= ' Pipeline Stage set to Close Transaction.'; }
        }
    }
}
require_once __DIR__ . "/subpage.php";
sp_head('Close Transaction', $tx);
?>
<div class="panel sigbox">
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
<?php if ($msg): ?><p class="note"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
  <h2 style="margin-top:0">Upload the final signed documents</h2>
  <p class="note">Upload the final signed closing (settlement) statement, and the signed Walk Through form (whether it was signed in person at the final walkthrough or sent out for e-signature earlier). Uploading saves straight into this transaction's folder and marks this record's pipeline stage Close Transaction.</p>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>">
    <p><label>Final signed closing statement<input type="file" name="closingstmt" accept=".pdf,.jpg,.jpeg,.png"></label></p>
    <p><label>Signed Buyer Preclosing Walk Through form<input type="file" name="walkfile" accept=".pdf,.jpg,.jpeg,.png"></label></p>
    <p><button type="submit" class="btn">Upload</button></p>
  </form>
  <p><a class="btn ghost" href="https://drive.google.com/drive/folders/<?= urlencode($tx) ?>" target="_blank" rel="noopener">Open the transaction folder</a></p>
</div>
<?php sp_foot(); ?>
