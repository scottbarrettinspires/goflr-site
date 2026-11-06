<?php
// Reached from the "Shop Homes" step: a running log of notes while the buyer is shopping, including listings scheduled
// or shown. Stored per transaction folder, newest first.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/pstage.php"; // pulls in flrdb.php
require_once __DIR__ . "/subpage.php";
$me = $_SESSION['email'];
$tx = (string)($_GET['tx'] ?? ($_POST['tx'] ?? ''));
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

function shop_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS shop_notes (id INTEGER PRIMARY KEY AUTOINCREMENT, folder TEXT NOT NULL, akey TEXT NOT NULL, kind TEXT, address TEXT, note TEXT, created TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS shop_notes_folder ON shop_notes(folder)');
    return $pdo;
}
$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $err = 'This page expired. Please reload and try again.'; }
    else {
        $kind = in_array($_POST['kind'] ?? '', ['scheduled', 'shown', 'note'], true) ? $_POST['kind'] : 'note';
        $addr = mb_substr(trim((string)($_POST['address'] ?? '')), 0, 200);
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 2000);
        if ($kind !== 'note' && $addr === '') { $err = 'Please enter the listing address.'; }
        elseif ($kind === 'note' && $note === '') { $err = 'Please enter a note.'; }
        else {
            shop_db()->prepare('INSERT INTO shop_notes (folder, akey, kind, address, note, created) VALUES (?,?,?,?,?,?)')
                ->execute([$tx, flr_akey($me), $kind, $addr, $note, date('c')]);
            header('Location: shop_notes.php?tx=' . urlencode($tx) . '&saved=1'); exit;
        }
    }
}
$q = shop_db()->prepare('SELECT * FROM shop_notes WHERE folder = ? ORDER BY id DESC'); $q->execute([$tx]); $rows = $q->fetchAll();
$kindLabel = ['scheduled' => 'Showing scheduled', 'shown' => 'Shown', 'note' => 'Note'];
sp_head('Shop Homes', $tx);
?>
<div class="panel sigbox">
<?php if (!empty($_GET['saved'])): ?><p class="note">Saved.</p><?php endif; ?>
<?php if ($err): ?><p class="sigerr"><?= htmlspecialchars($err) ?></p><?php endif; ?>
  <h2 style="margin-top:0">Log a showing or a note</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="tx" value="<?= htmlspecialchars($tx) ?>">
    <div class="wq">
      <label>Listing address (for a scheduled or shown entry)<input type="text" name="address" placeholder="123 Main St"></label>
      <label>Note<textarea name="note" rows="3" placeholder="What the buyer thought, next steps, etc."></textarea></label>
    </div>
    <p>
      <button type="submit" class="btn" name="kind" value="scheduled">Log a scheduled showing</button>
      <button type="submit" class="btn" name="kind" value="shown">Log a home shown</button>
      <button type="submit" class="btn ghost" name="kind" value="note">Add a note only</button>
    </p>
  </form>
</div>
<div class="panel sigbox">
  <h2 style="margin-top:0">History</h2>
<?php if (!$rows): ?><p class="note">Nothing logged yet.</p>
<?php else: foreach ($rows as $r): ?>
  <p style="margin:0 0 10px"><strong><?= htmlspecialchars($kindLabel[$r['kind']] ?? 'Note') ?></strong><?= $r['address'] !== '' ? ' &middot; ' . htmlspecialchars($r['address']) : '' ?><br>
  <span style="color:#6a777a;font-size:.85rem"><?= htmlspecialchars(date('M j, Y g:ia', strtotime($r['created']))) ?></span>
<?php if ($r['note'] !== ''): ?><br><?= nl2br(htmlspecialchars($r['note'])) ?><?php endif; ?>
  </p>
<?php endforeach; endif; ?>
</div>
<?php sp_foot(); ?>
