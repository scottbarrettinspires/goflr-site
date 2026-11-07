<?php
// Staff-only Agent Records dashboard -- a server-side view and editor for the Agent Records data (arlib.php),
// mirrored in from the Google Sheet via tools/import_agent_records.php. This is a PARALLEL system: the Sheet,
// the Apps Script, and everything Lindsay uses today (sign-in, directory, announcements, birthdays/anniversaries)
// are untouched and keep working exactly as they do now. Near launch, Scott will ask for a sheet-vs-server
// compare before this becomes the thing anyone relies on day to day.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/arlib.php";
if (!flr_is_broker()) { http_response_code(403); exit('Staff only.'); }
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

$YESNO = ['birthday_post_ok', 'anniversary_post_ok', 'text_recognition_ok', 'email_recognition_ok', 'private_group_post_ok', 'public_social_post_ok',
    'commercial_approved', 'property_management_approved', 'agent_directory_ok', 'w_9_on_file', "driver_s_license_copy_on_file", 'auto_insurance_card_on_file'];

function ar_next_id() {
    $max = 0;
    foreach (ar_all() as $r) { if (preg_match('/^A-(\d+)$/', $r['agent_id'], $m)) { $max = max($max, (int)$m[1]); } }
    return 'A-' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
}

$id = trim((string)($_GET['id'] ?? ''));
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    elseif (isset($_POST['ar_import'])) {
        $up = $_FILES['sheetfile'] ?? null;
        if (!$up || ($up['error'] ?? 1) !== UPLOAD_ERR_OK || ($up['size'] ?? 0) > 5000000) { $err = 'Choose the sheet data file (.json) to import.'; }
        else {
            $res = ar_import(json_decode((string)file_get_contents($up['tmp_name']), true), $_SESSION['email']);
            if (isset($res['error'])) { $err = $res['error']; } else { $_SESSION['ar_import_result'] = $res; header('Location: agentrecords.php?imported=1'); exit; }
        }
    }
    elseif (isset($_POST['ar_delete'])) {
        ar_delete((string)$_POST['ar_delete']);
        header('Location: agentrecords.php?deleted=1'); exit;
    } else {
        $agentId = trim((string)($_POST['agent_id'] ?? ''));
        if ($agentId === '') $agentId = ar_next_id();
        $fields = [];
        foreach (AR_FIELDS as $k => $label) { $fields[$k] = trim((string)($_POST['f_' . $k] ?? '')); }
        $fields['agent_id'] = $agentId;
        if ($fields['full_name'] === '' && $fields['first_name'] === '' && $fields['last_name'] === '') { $err = 'Enter at least a name.'; }
        else {
            if ($fields['full_name'] === '') $fields['full_name'] = trim($fields['first_name'] . ' ' . $fields['last_name']);
            ar_save($agentId, $fields, $_SESSION['email']);
            header('Location: agentrecords.php?id=' . urlencode($agentId) . '&saved=1'); exit;
        }
        $id = $agentId; // fall through to re-render the form with the error and entered values
        $editing = $fields;
    }
}

sp_head('Agent Records', '');
?>
<style>.arlist{width:100%;border-collapse:collapse;font-size:.92rem}.arlist th{text-align:left;border-bottom:2px solid var(--line);padding:6px 10px 6px 0}.arlist td{padding:7px 10px 7px 0;border-bottom:1px solid var(--line)}
.arlist tr.inactive{opacity:.6}.arform label{display:block;margin:0 0 10px;font-size:.88rem;color:#455355}.arform input[type=text],.arform select,.arform textarea{width:100%;margin-top:3px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;font:inherit}
.arform .grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:4px 18px}.archip{display:inline-block;font-size:.74rem;padding:2px 8px;border-radius:99px;background:var(--teal-soft);color:var(--teal)}</style>

<?php if (!empty($_GET['saved'])): ?><p class="note">Saved.</p><?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?><p class="note">Record deleted.</p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>

<?php if (!empty($_GET['imported']) && !empty($_SESSION['ar_import_result'])): $s = $_SESSION['ar_import_result']; unset($_SESSION['ar_import_result']); ?>
  <div class="note"><strong>Import complete.</strong> <?= count($s['added']) ?> added, <?= count($s['changed']) ?> changed, <?= (int)$s['unchanged'] ?> unchanged<?= $s['skipped'] ? ', ' . (int)$s['skipped'] . ' skipped (no Agent ID)' : '' ?>.
  <?php if ($s['changed']): ?><br>Changed: <?= htmlspecialchars(implode('; ', $s['changed'])) ?><?php endif; ?>
  <?php if ($s['not_in_pull']): ?><br>On the server but not in this file (kept): <?= htmlspecialchars(implode(', ', $s['not_in_pull'])) ?><?php endif; ?>
  <?php if ($s['unmatched_columns']): ?><br>Sheet columns ignored: <?= htmlspecialchars(implode(', ', $s['unmatched_columns'])) ?><?php endif; ?>
  <?php if ($s['missing_columns']): ?><br>Columns missing from the file: <?= htmlspecialchars(implode(', ', $s['missing_columns'])) ?><?php endif; ?></div>
<?php endif; ?>
<?php if ($id === ''): ?>
  <details class="panel sigbox" style="margin-bottom:14px"><summary style="cursor:pointer;font-weight:600">Import sheet data</summary>
    <p style="font-size:.9rem;color:#455355">Upload the Agent Records data file Claude prepares from the Google Sheet. Each agent is matched by Agent ID and updated; nothing here is ever deleted, and the Google Sheet itself is not changed.</p>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
      <input type="hidden" name="ar_import" value="1">
      <input type="file" name="sheetfile" accept=".json,application/json" required>
      <button class="btn" type="submit">Import</button>
    </form>
  </details>
  <p><a class="btn" href="agentrecords.php?id=new">+ Add agent</a> <input id="q" type="text" placeholder="Search name, email, office..." style="padding:8px 10px;border:1px solid var(--line);border-radius:8px;min-width:260px"></p>
  <div class="panel sigbox">
  <table class="arlist" id="artbl">
  <tr><th>Name</th><th>Status</th><th>Office</th><th>Email</th><th>Last updated</th><th></th></tr>
  <?php foreach (ar_all() as $r): $f = $r['fields']; $display = $r['preferred_name'] ?: $r['full_name'];
      $active = strtolower($r['status']) === 'active'; ?>
  <tr class="<?= $active ? '' : 'inactive' ?>" data-s="<?= htmlspecialchars(strtolower($display . ' ' . $r['email'] . ' ' . $f['office'])) ?>">
    <td><?= htmlspecialchars($display) ?></td>
    <td><?= htmlspecialchars($r['status']) ?></td>
    <td><?= htmlspecialchars($f['office'] ?? '') ?></td>
    <td><?= htmlspecialchars($r['email']) ?></td>
    <td><?= htmlspecialchars($f['last_updated'] ?? '') ?></td>
    <td><a href="agentrecords.php?id=<?= urlencode($r['agent_id']) ?>">Edit</a></td>
  </tr>
  <?php endforeach; ?>
  </table>
  </div>
  <script>
  (function(){var q=document.getElementById("q");if(!q)return;q.addEventListener("input",function(){var v=q.value.trim().toLowerCase();
    document.querySelectorAll("#artbl tr[data-s]").forEach(function(tr){tr.style.display=(!v||tr.dataset.s.indexOf(v)>-1)?"":"none";});});})();
  </script>

<?php else:
  $isNew = ($id === 'new');
  $rec = $isNew ? null : ar_get($id);
  if (!$isNew && !$rec) { echo '<p class="sigerr">No agent record with that ID.</p>'; sp_foot(); exit; }
  $f = $editing ?? ($rec['fields'] ?? []);
  $v = function ($k) use ($f) { return htmlspecialchars((string)($f[$k] ?? '')); };
  ?>
  <p><a href="agentrecords.php">&larr; All agents</a></p>
  <form method="post" class="arform">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <input type="hidden" name="agent_id" value="<?= $isNew ? '' : htmlspecialchars($id) ?>">
    <?php foreach (AR_GROUPS as $group => $keys): ?>
    <fieldset class="wq"><legend><?= htmlspecialchars($group) ?></legend>
      <div class="grid2">
      <?php foreach ($keys as $k):
          $label = AR_FIELDS[$k]; ?>
        <label><?= htmlspecialchars($label) ?>
        <?php if ($k === 'notes'): ?>
          <textarea name="f_<?= $k ?>" rows="3"><?= $v($k) ?></textarea>
        <?php elseif (in_array($k, $YESNO, true)): ?>
          <select name="f_<?= $k ?>"><option value="">&mdash;</option><option value="Yes" <?= $v($k) === 'Yes' ? 'selected' : '' ?>>Yes</option><option value="No" <?= $v($k) === 'No' ? 'selected' : '' ?>>No</option></select>
        <?php elseif ($k === 'status'): ?>
          <select name="f_status">
          <?php foreach (['Active', 'Inactive', 'Terminated'] as $opt): ?><option value="<?= $opt ?>" <?= $v('status') === $opt ? 'selected' : '' ?>><?= $opt ?></option><?php endforeach; ?>
          <?php if ($v('status') !== '' && !in_array($v('status'), ['Active', 'Inactive', 'Terminated'], true)): ?><option value="<?= $v('status') ?>" selected><?= $v('status') ?> (as imported)</option><?php endif; ?>
          </select>
        <?php else: ?>
          <input type="text" name="f_<?= $k ?>" value="<?= $v($k) ?>" autocomplete="off" data-lpignore="true" data-1p-ignore>
        <?php endif; ?>
        </label>
      <?php endforeach; ?>
      </div>
    </fieldset>
    <?php endforeach; ?>
    <p><button class="btn" type="submit">Save</button></p>
  </form>
  <?php if (!$isNew): ?>
  <form method="post" onsubmit="return confirm('Delete this server-side record? This does not touch the Google Sheet.');">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <input type="hidden" name="ar_delete" value="<?= htmlspecialchars($id) ?>">
    <button class="btn ghost" type="submit">Delete this record</button>
  </form>
  <?php endif; ?>
<?php endif;
sp_foot();
