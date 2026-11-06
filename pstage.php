<?php
// Pipeline sub-stage for buyer/seller records: a finer, agent-set stage shown as colored pills (same pattern as the
// Leads sub-category buttons), stored on the transaction record itself and independent of which Drive folder (Buyers /
// Pending / Archived, etc.) the record's files happen to sit in right now.
require_once __DIR__ . '/flrdb.php';

const PS_STAGES = [
    'buyer'  => ['agreement' => 'Agreement Executed', 'shopping' => 'Shopping Homes', 'offer' => 'Offer Submitted', 'contract' => 'Contract Executed', 'contingencies' => 'Contingencies Satisfied', 'closingready' => 'Closing Ready', 'closed' => 'Closing Completed'],
    'seller' => ['prelisting' => 'Prelisting Prep', 'active' => 'Active Listing', 'managing' => 'Managing', 'offerrecv' => 'Offer Received', 'contract' => 'Executed Contract', 'contingencies' => 'Contingencies Satisfied', 'closingready' => 'Closing Ready', 'closed' => 'Closing Completed'],
];
// Light to dark, ending in green on the final (Closing Completed) stage of either side.
const PS_PALETTE = ['#cfd8dc', '#b3d4e0', '#8ec9d9', '#6cb8c9', '#4aa6b8', '#2b8e94', '#036f72', '#1b5e20'];

function ps_ensure_column() {
    static $done = false; if ($done) return; $done = true;
    $pdo = flr_db();
    foreach ($pdo->query('PRAGMA table_info(transactions)')->fetchAll() as $c) { if ($c['name'] === 'pstage') return; }
    $pdo->exec('ALTER TABLE transactions ADD COLUMN pstage TEXT');
}
function ps_keys($side) { return array_keys(PS_STAGES[$side] ?? []); }
// A record's pipeline stage, defaulting from its current Drive-folder stage number until the agent sets one explicitly.
function ps_default($side, $driveStage) {
    $n = (int)$driveStage;
    if ($side === 'buyer') return $n >= 4 ? 'closed' : ($n === 3 ? 'contract' : 'agreement');
    if ($side === 'seller') return $n >= 4 ? 'closed' : ($n === 3 ? 'contract' : ($n === 2 ? 'active' : 'prelisting'));
    return ps_keys($side)[0] ?? '';
}
function ps_get($folder, $side, $driveStage = 0) {
    ps_ensure_column();
    $q = flr_db()->prepare('SELECT pstage FROM transactions WHERE folder = ?'); $q->execute([$folder]); $v = $q->fetchColumn();
    $keys = ps_keys($side);
    if ($v && in_array($v, $keys, true)) return $v;
    $d = ps_default($side, $driveStage);
    return in_array($d, $keys, true) ? $d : ($keys[0] ?? '');
}
function ps_set($me, $folder, $side, $key) {
    ps_ensure_column();
    if (!in_array($key, ps_keys($side), true)) return false;
    flr_db()->prepare('UPDATE transactions SET pstage = ? WHERE folder = ?')->execute([$key, $folder]);
    flr_audit(flr_akey($me), 'pstage', $folder, 'Pipeline stage set to ' . (PS_STAGES[$side][$key] ?? $key));
    return true;
}
function ps_color($side, $key) {
    $keys = ps_keys($side); $i = array_search($key, $keys, true); if ($i === false) $i = 0;
    return $i === count($keys) - 1 ? PS_PALETTE[7] : PS_PALETTE[min($i, 6)];
}
function ps_fg($side, $key) { $keys = ps_keys($side); $i = array_search($key, $keys, true); return ($i !== false && $i < 3) ? '#1b2a2c' : '#fff'; }

// POST op=setpstage on a buyer/seller record page.
function ps_handle_save($me, $side, $tx, $self) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['op'] ?? '') !== 'setpstage') return;
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { header('Location: ' . $self); exit; }
    ps_set($me, $tx, $side, (string)($_POST['pstage'] ?? ''));
    header('Location: ' . $self); exit;
}
// The pill-button row for one record: click a pill to move the record to that pipeline stage. Same visual idea as the
// Leads sub-category buttons — colored, current one outlined — but a plain form post rather than an AJAX call.
function ps_box($side, $tx, $driveStage = 0) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $cur = ps_get($tx, $side, $driveStage);
    $o = '<div class="panel sigbox" style="margin-bottom:14px">';
    $o .= '<h2 style="margin:0 0 10px;font-size:1.1rem">Pipeline Stage</h2>';
    $o .= '<form method="post"><input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tx" value="' . $h($tx) . '"><input type="hidden" name="op" value="setpstage"><div style="display:flex;flex-wrap:wrap;gap:6px">';
    foreach (PS_STAGES[$side] as $k => $label) {
        $on = $k === $cur; $bg = ps_color($side, $k); $fg = ps_fg($side, $k);
        $o .= '<button type="submit" name="pstage" value="' . $h($k) . '" style="background:' . $bg . ';color:' . $fg . ';font-weight:700;font-size:.85rem;padding:7px 12px;border-radius:10px;border:1.5px solid rgba(0,0,0,.1);cursor:pointer' . ($on ? ';outline:3px solid #1f2a2c;outline-offset:1px' : ';opacity:.72') . '">' . $h($label) . '</button>';
    }
    $o .= '</div></form></div>';
    return $o;
}
// A colored-pill summary (counts per stage) plus the records grouped under each stage, for the "choose a record" list view.
function ps_group_box($side, array $records, callable $urlFn) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $byStage = []; foreach (ps_keys($side) as $k) $byStage[$k] = [];
    foreach ($records as $r) { $k = ps_get($r['folder'], $side, (int)($r['stage'] ?? 0)); $byStage[$k][] = $r; }
    $o = '<div style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 16px">';
    foreach (PS_STAGES[$side] as $k => $label) {
        $bg = ps_color($side, $k); $fg = ps_fg($side, $k); $n = count($byStage[$k]);
        $o .= '<span style="background:' . $bg . ';color:' . $fg . ';font-weight:700;font-size:.82rem;padding:5px 10px;border-radius:99px">' . $h($label) . ' <span style="opacity:.85">' . $n . '</span></span>';
    }
    $o .= '</div>';
    foreach (PS_STAGES[$side] as $k => $label) {
        if (!$byStage[$k]) continue;
        $o .= '<h3 style="margin:14px 0 6px">' . $h($label) . '</h3>';
        foreach ($byStage[$k] as $r) { $o .= '<p style="margin:0 0 6px"><a href="' . $h($urlFn($r)) . '">' . $h($r['name']) . '</a>' . (!empty($r['lead_id']) ? ' <span style="color:#6a777a;font-size:.85rem">&middot; linked to a lead</span>' : '') . '</p>'; }
    }
    if (!$records) $o .= '<p class="note">No records found yet.</p>';
    return $o;
}
