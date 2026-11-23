<?php
// Staff-only Agent Records dashboard -- a server-side view and editor for the Agent Records data (arlib.php),
// mirrored in from the Google Sheet via tools/import_agent_records.php. This is a PARALLEL system: the Sheet,
// the Apps Script, and everything Lindsay uses today (sign-in, directory, announcements, birthdays/anniversaries)
// are untouched and keep working exactly as they do now. Near launch, Scott will ask for a sheet-vs-server
// compare before this becomes the thing anyone relies on day to day.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/dlib.php"; // flr_is_broker()
require_once __DIR__ . "/arlib.php";
require_once __DIR__ . "/complib.php";
require_once __DIR__ . "/comp_panel.php";
if (!flr_is_broker()) { http_response_code(403); exit('Staff only.'); }
require __DIR__ . "/subpage.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

$YESNO = ['birthday_post_ok', 'anniversary_post_ok', 'text_recognition_ok', 'email_recognition_ok', 'private_group_post_ok', 'public_social_post_ok',
    'commercial_approved', 'property_management_approved', 'agent_directory_ok', 'w_9_on_file', "driver_s_license_copy_on_file", 'auto_insurance_card_on_file'];

// Groups left off the Agent Record form (their stored values are kept on save and in imports).
$HIDDEN_GROUPS = ['Compliance & files'];
function ar_next_id() {
    $max = 0;
    foreach (ar_all() as $r) { if (preg_match('/^A-(\d+)$/', $r['agent_id'], $m)) { $max = max($max, (int)$m[1]); } }
    return 'A-' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
}

$id = trim((string)($_GET['id'] ?? ''));
if ($id === '' && ($em0 = strtolower(trim((string)($_GET['email'] ?? '')))) !== '') { // from the Directory: open this agent's record by email
    $q0 = ar_db()->prepare('SELECT agent_id FROM agent_records WHERE email = ?'); $q0->execute([$em0]); $f0 = (string)$q0->fetchColumn();
    if ($f0 !== '') { header('Location: agentrecords.php?id=' . urlencode($f0) . '#comp'); exit; }
    $err = 'No Agent Record on the server has the email ' . $em0 . '. Import Agent Records first.';
}
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
        $hiddenKeys = []; foreach ($HIDDEN_GROUPS as $hg) foreach ((AR_GROUPS[$hg] ?? []) as $hk) $hiddenKeys[$hk] = true; // not on the form: keep what is stored
        $existing = ar_get($agentId)['fields'] ?? [];
        foreach (AR_FIELDS as $k => $label) { $fields[$k] = isset($hiddenKeys[$k]) ? (string)($existing[$k] ?? '') : trim((string)($_POST['f_' . $k] ?? '')); }
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

sp_head($id !== '' ? 'Agent Internal Record' : 'Agent Records', '');
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
  <?php if (!$isNew):
      $cpPeriod = (string)($_GET['period'] ?? 'ytd'); if (!in_array($cpPeriod, ['month', 'quarter', 'ytd', 'all'], true) && !preg_match('/^\d{4}$/', $cpPeriod)) $cpPeriod = 'ytd';
      $cpAgent = null; $cpOv = cp_overview($cpPeriod, ''); foreach ($cpOv['agents'] as $x) { if ($x['agent_id'] === $id) { $cpAgent = $x; break; } } ?>
  <?= cp_panel_css() ?>
  <?php endif; ?>
  <form method="post" class="arform">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <input type="hidden" name="agent_id" value="<?= $isNew ? '' : htmlspecialchars($id) ?>">
    <?php if (!$isNew):
        $cn = trim((string)($f['preferred_name'] ?? '')) ?: trim((string)($f['full_name'] ?? '')) ?: trim(($f['first_name'] ?? '') . ' ' . ($f['last_name'] ?? ''));
        $cini = ''; foreach (preg_split('/\s+/', $cn) as $w) { if ($w !== '' && strlen($cini) < 2) $cini .= mb_strtoupper(mb_substr($w, 0, 1)); }
        $chs = flr_headshot_url((string)($f['email'] ?? ''), ''); $caddr = trim(trim((string)($f['street_address'] ?? '')) . ', ' . trim((string)($f['city'] ?? '')) . ' ' . trim((string)($f['state'] ?? '')) . ' ' . trim((string)($f['zip'] ?? '')), ' ,'); ?>
    <style>.arcard{display:flex;gap:18px;align-items:center;flex-wrap:wrap;background:linear-gradient(135deg,var(--teal-dark),var(--teal));color:#fff;border-radius:16px;padding:18px 20px;margin:0 0 16px;box-shadow:0 6px 18px rgba(2,84,87,.28)}
    .arcard .av{width:92px;height:92px;border-radius:50%;background:#fff;color:var(--teal);display:flex;align-items:center;justify-content:center;font-size:1.9rem;font-weight:700;overflow:hidden;flex:none;border:3px solid rgba(255,255,255,.85);position:relative}.arcard .av img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
    .arcard .tx{flex:1;min-width:230px}.arcard h2{margin:0 0 2px;font-size:1.45rem;color:#fff}.arcard .sub{opacity:.9;font-size:.92rem;margin:0 0 6px}.arcard .ln{font-size:.92rem;margin:2px 0}.arcard a{color:#fff}
    .arcard .st{display:inline-block;font-size:.75rem;padding:2px 10px;border-radius:99px;background:rgba(255,255,255,.2);margin-left:8px;vertical-align:middle}.arcard .ed{background:#fff;color:var(--teal-dark);border:0;border-radius:10px;padding:9px 18px;font:inherit;font-weight:600;cursor:pointer}</style>
    <div class="arcard" id="arcard"><div class="av"><span><?= htmlspecialchars($cini) ?></span><?php if ($chs !== ''): ?><img src="<?= htmlspecialchars($chs) ?>" alt="" onerror="this.remove()"><?php endif; ?></div>
      <div class="tx"><h2><?= htmlspecialchars($cn) ?><span class="st"><?= htmlspecialchars((string)($f['status'] ?? '')) ?></span></h2>
        <p class="sub"><?= htmlspecialchars((string)($f['agent_id'] ?? $id)) ?><?= !empty($f['office']) ? ' &middot; ' . htmlspecialchars($f['office']) : '' ?><?= !empty($f['team_and_team_leader']) ? ' &middot; ' . htmlspecialchars($f['team_and_team_leader']) : '' ?></p>
        <?php if (!empty($f['phone'])): ?><div class="ln"><a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $f['phone'])) ?>"><?= htmlspecialchars($f['phone']) ?></a></div><?php endif; ?>
        <?php if (!empty($f['email'])): ?><div class="ln"><a href="mailto:<?= htmlspecialchars($f['email']) ?>"><?= htmlspecialchars($f['email']) ?></a></div><?php endif; ?>
        <?php if ($caddr !== ''): ?><div class="ln"><?= htmlspecialchars($caddr) ?></div><?php endif; ?>
        <?php if (!empty($f['pay_commission_to']) || !empty($f['payee_type']) || !empty($f['dbpr_legal_name'])): ?><div class="ln" style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.3)">
          <?php if (!empty($f['pay_commission_to'])): ?><div><span style="opacity:.8">Pay commission to:</span> <?= htmlspecialchars($f['pay_commission_to']) ?><?= !empty($f['payee_type']) ? ' <span style="opacity:.8">(' . htmlspecialchars($f['payee_type']) . ')</span>' : '' ?></div><?php elseif (!empty($f['payee_type'])): ?><div><span style="opacity:.8">Payee type:</span> <?= htmlspecialchars($f['payee_type']) ?></div><?php endif; ?>
          <?php if (!empty($f['dbpr_legal_name'])): ?><div><span style="opacity:.8">DBPR legal name:</span> <?= htmlspecialchars($f['dbpr_legal_name']) ?></div><?php endif; ?>
        </div><?php endif; ?>
        <div class="ln"><span style="opacity:.8">Commercial approved:</span> <?= htmlspecialchars(($f['commercial_approved'] ?? '') !== '' ? $f['commercial_approved'] : 'Not set') ?> &nbsp;&middot;&nbsp; <span style="opacity:.8">Property management approved:</span> <?= htmlspecialchars(($f['property_management_approved'] ?? '') !== '' ? $f['property_management_approved'] : 'Not set') ?> &nbsp;&middot;&nbsp; <span style="opacity:.8">Revenue share option in agreement:</span> <?= htmlspecialchars(($f['revenue_share_option_in_agreement'] ?? '') !== '' ? $f['revenue_share_option_in_agreement'] : 'Not set') ?></div>
      </div>
      <button type="button" class="ed" onclick="var g=document.getElementById('idfs');g.hidden=!g.hidden;this.textContent=g.hidden?'Edit':'Close editor';if(!g.hidden)g.scrollIntoView({behavior:'smooth',block:'nearest'});">Edit</button></div>
    <?php endif; ?>
    <?php $groupsList = AR_GROUPS; if (!$isNew) { $cmp = ['Compensation' => $groupsList['Compensation']]; unset($groupsList['Compensation']); $groupsList = array_slice($groupsList, 0, 1, true) + $cmp + array_slice($groupsList, 1, null, true); } // Compensation fields sit with the plan and income, right after Identity
    foreach ($groupsList as $group => $keys): if (in_array($group, $HIDDEN_GROUPS, true)) continue; ?>
    <?php $isId = (!$isNew && $group === 'Identity'); $dd = (!$isNew && !$isId); ?>
    <?php if ($dd && $group === 'Compensation'): ?><details class="siag" id="income"<?= (isset($_GET['period'])) ? ' open' : '' ?>>
      <summary><b>Income &amp; closed sales</b>
      <?php if ($cpAgent): ?><span><?= (int)$cpAgent['buyer_sides'] + (int)$cpAgent['seller_sides'] ?> transactions (<?= (int)$cpAgent['buyer_sides'] ?> buyer / <?= (int)$cpAgent['seller_sides'] ?> seller)</span><span>Brokerage <?= $cpAgent['company_income'] === null ? '<span class="sinot">not set</span>' : '$' . number_format($cpAgent['company_income'], 2) ?></span><?php else: ?><span class="sinot">Not on the Agent Compensation sheet import yet</span><?php endif; ?></summary><div class="in">
      <div style="margin:8px 0"><label style="font-size:.88rem">Period <select onchange="location.href='agentrecords.php?id=<?= urlencode($id) ?>&amp;period='+encodeURIComponent(this.value)+'#income'"><?php foreach (['month' => 'This month', 'quarter' => 'This quarter', 'ytd' => date('Y') . ' year to date', (string)(date('Y') - 1) => (string)(date('Y') - 1), 'all' => 'All time'] as $pk => $pl): ?><option value="<?= htmlspecialchars($pk) ?>"<?= (string)$pk === $cpPeriod ? ' selected' : '' ?>><?= htmlspecialchars($pl) ?></option><?php endforeach; ?></select></label></div>
      <?= $cpAgent ? cp_panel_body($cpAgent, false, 'income') : '<p class="sinot">This agent is not in the imported Agent Compensation sheet. Import the finance data on the Agent Income page (Settings).</p>' ?>
    </div></details>
    <details class="siag" id="comp"<?= $err ? ' open' : '' ?>>
      <summary><b>Compensation &amp; plan</b>
      <?php if ($cpAgent): $cc = $cpAgent['plan']; ?><span><?= htmlspecialchars($cc['plan'] !== '' ? $cc['plan'] : 'No plan on the sheet') ?></span><?php else: ?><span class="sinot">Not on the Agent Compensation sheet import yet</span><?php endif; ?></summary><div class="in">
      <?= $cpAgent ? cp_panel_body($cpAgent, false, 'plan') : '<p class="sinot">This agent is not in the imported Agent Compensation sheet. Import the finance data on the Agent Income page (Settings).</p>' ?>
      <h3 style="margin:16px 0 4px;font-size:1rem">Payee and split settings <span style="font-weight:400;font-size:.82rem;color:#455355">(from Agent Records; saved with the Save button)</span></h3>
    <?php elseif ($dd): ?><details class="siag"<?= $err ? ' open' : '' ?>><summary><b><?= htmlspecialchars($group) ?></b></summary><div class="in">
    <?php else: ?><fieldset class="wq"<?= $isId ? ($err ? ' id="idfs"' : ' id="idfs" hidden') : '' ?>><legend><?= htmlspecialchars($group) ?></legend><?php endif; ?>
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
    <?php if ($dd): ?></div></details><?php else: ?></fieldset><?php endif; ?>
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
