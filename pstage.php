<?php
// Pipeline sub-stage for buyer/seller records: a finer, agent-set stage shown as colored pills (same pattern as the
// Leads sub-category buttons), stored on the transaction record itself and independent of which Drive folder (Buyers /
// Pending / Archived, etc.) the record's files happen to sit in right now.
require_once __DIR__ . '/flrdb.php';
require_once __DIR__ . '/dlib.php';

const PS_STAGES = [
    'buyer'  => ['agreement' => 'Buyer Agreement', 'shopping' => 'Shop Homes', 'offer' => 'Make An Offer', 'contract' => 'Executed Contract', 'contingencies' => 'Contingencies', 'closingready' => 'Closing Prep', 'closed' => 'Close Transaction'],
    'seller' => ['prelisting' => 'Prelisting Prep', 'active' => 'Active Listing', 'managing' => 'Managing', 'offerrecv' => 'Offer Received', 'contract' => 'Executed Contract', 'contingencies' => 'Contingencies', 'closingready' => 'Closing Ready', 'closed' => 'Closing Completed'],
];
// Light to dark, ending in green on the final (Closing Completed) stage of either side.
const PS_PALETTE = ['#cfd8dc', '#b3d4e0', '#8ec9d9', '#6cb8c9', '#4aa6b8', '#2b8e94', '#036f72', '#1b5e20'];
// The "Contingencies" stage's label is dynamic: "Contingencies" while any entered contingency is still open, and
// "Contingencies Satisfied" once every contingency the agent has entered a period for is marked Done/waived.
function ps_label($side, $key, $tx) {
    if ($key === 'contingencies' && dl_contingencies_satisfied($tx)) return 'Contingencies Satisfied';
    return PS_STAGES[$side][$key] ?? $key;
}
// Where each buyer pipeline stage's label takes the agent, beyond just marking it as the current stage. Seller stages
// have no destination yet and stay a plain stage-select button.
function ps_dest($side, $key, $tx) {
    if ($side !== 'buyer') return null;
    $q = '?tx=' . urlencode($tx);
    $map = ['agreement' => 'buyer_agreement.php', 'shopping' => 'shop_notes.php', 'offer' => 'wizard.php', 'contract' => 'signed_filed.php' . $q . '&kind=contract', 'contingencies' => 'contingencies.php', 'closingready' => 'closing_prep.php', 'closed' => 'close_transaction.php'];
    $dest = $map[$key] ?? null;
    return $dest === null ? null : (strpos($dest, '?') !== false ? $dest : $dest . $q);
}
// Every buyer stage's screen shows in the panel beside the ladder except Make An Offer, which opens the full Buyer Form
// Wizard as its own page (it's too large and too important a tool to shrink into a side panel).
function ps_embeddable($key) { return $key !== 'offer'; }

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
// The buyer/seller's name and phone/email, read straight off the saved info fields (b1_/b2_ or s1_/s2_).
function ps_contacts($side, array $info) {
    $p1 = $side === 'seller' ? 's1' : 'b1'; $p2 = $side === 'seller' ? 's2' : 'b2';
    $out = [];
    foreach ([$p1 => '', $p2 => '2'] as $p => $suf) {
        $name = trim((string)($info[$p . '_name'] ?? '')); if ($name === '') continue;
        $out[] = ['prefix' => $p, 'label' => ucfirst($side === 'seller' ? 'seller' : 'buyer') . ($suf ?: ''), 'name' => $name, 'phone' => trim((string)($info[$p . '_phone'] ?? '')), 'email' => trim((string)($info[$p . '_email'] ?? ''))];
    }
    return $out;
}
// Validates a pencil-edit submission from ps_box()'s contact card: picks which contact (b1/b2 or s1/s2) was edited and
// returns its three fields, ready to merge into the record's saved info. Returns [$prefix, $fields, $error].
function ps_contact_edit_fields(array $allowedPrefixes) {
    $p = (string)($_POST['p'] ?? '');
    if (!in_array($p, $allowedPrefixes, true)) return [null, null, 'Please choose which contact to edit.'];
    $name = mb_substr(trim((string)($_POST['c_name'] ?? '')), 0, 200);
    $phone = mb_substr(trim((string)($_POST['c_phone'] ?? '')), 0, 200);
    $email = mb_substr(trim((string)($_POST['c_email'] ?? '')), 0, 200);
    if ($name === '') return [$p, null, 'Please enter a name.'];
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return [$p, null, 'Please check the email address: ' . $email];
    return [$p, [$p . '_name' => $name, $p . '_phone' => $phone, $p . '_email' => $email], ''];
}
// The buyer/seller's name and contact info, as a compact bar with a pencil to fix a name, phone or email in place
// (posts to the same page as op=editcontact, so it works whether or not the record's info fields are otherwise locked).
function ps_contact_bar($side, $tx, array $info) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $contacts = ps_contacts($side, $info);
    $o = '<div style="margin-bottom:16px">';
    if (!$contacts) { return $o . '<p class="note" style="margin:0">No contact info saved yet.</p></div>'; }
    foreach ($contacts as $c) {
        $o .= '<details style="margin-bottom:6px"><summary style="cursor:pointer;display:flex;flex-wrap:wrap;gap:14px;align-items:baseline;list-style:revert">';
        $o .= '<strong>' . $h($c['name']) . '</strong>';
        if ($c['phone'] !== '') $o .= '<a href="tel:' . $h(preg_replace('/[^0-9+]/', '', $c['phone'])) . '" onclick="event.stopPropagation()">' . $h($c['phone']) . '</a>';
        if ($c['email'] !== '') $o .= '<a href="mailto:' . $h($c['email']) . '" onclick="event.stopPropagation()">' . $h($c['email']) . '</a>';
        if ($c['phone'] === '' && $c['email'] === '') $o .= '<span style="color:#6a777a">No phone or email saved.</span>';
        $o .= '<span style="color:#036f72;font-weight:700;margin-left:auto;white-space:nowrap">&#9998; Edit</span></summary>';
        $o .= '<form method="post" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin:10px 0 4px">';
        $o .= '<input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tx" value="' . $h($tx) . '"><input type="hidden" name="op" value="editcontact"><input type="hidden" name="p" value="' . $h($c['prefix']) . '">';
        $o .= '<label>Name<br><input type="text" name="c_name" value="' . $h($c['name']) . '" required></label>';
        $o .= '<label>Phone<br><input type="text" name="c_phone" value="' . $h($c['phone']) . '" inputmode="tel"></label>';
        $o .= '<label>Email<br><input type="email" name="c_email" value="' . $h($c['email']) . '"></label>';
        $o .= '<button type="submit" class="btn">Save</button></form></details>';
    }
    return $o . '</div>';
}
// The pill-button ladder for one record (click a step to move it to that pipeline stage — climbs bottom-to-top, the
// first stage at the bottom and Closing Completed at the top), the buyer/seller's name and contact info above it with
// an edit pencil, and — for buyer records — the selected stage's screen in a panel beside the ladder, so clicking a
// stage shows its content right there instead of leaving the page or opening a new window.
function ps_box($side, $tx, $driveStage = 0, $recName = '', array $info = []) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $cur = ps_get($tx, $side, $driveStage);
    $o = '<div class="panel sigbox" style="margin-bottom:14px">';
    $o .= ps_contact_bar($side, $tx, $info);
    $o .= '<div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-start">';
    // Left: the stage ladder, acting as a menu.
    $o .= '<div><form method="post"><input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tx" value="' . $h($tx) . '"><input type="hidden" name="op" value="setpstage"><div style="display:flex;flex-direction:column-reverse;gap:6px;width:260px">';
    $i = 0;
    foreach (PS_STAGES[$side] as $k => $stageLabel) {
        $i++; $on = $k === $cur; $bg = ps_color($side, $k); $fg = ps_fg($side, $k); $label = ps_label($side, $k, $tx); $dest = ps_dest($side, $k, $tx);
        $pillStyle = 'display:flex;align-items:center;gap:8px;background:' . $bg . ';color:' . $fg . ';font-weight:700;font-size:.85rem;border-radius:10px;border:1.5px solid rgba(0,0,0,.1);text-align:left' . ($on ? ';outline:3px solid #1f2a2c;outline-offset:1px' : ';opacity:.72');
        $circle = '<span style="background:rgba(255,255,255,.35);color:inherit;border-radius:99px;min-width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;flex:none">' . $i . '</span>';
        if ($dest !== null) {
            // Split control: the numbered circle sets this as the current stage; the label opens that stage's screen in
            // the panel to the right (except Make An Offer, which still opens the full Wizard page).
            $embeddable = ps_embeddable($k);
            $href = $embeddable ? ($dest . '&embed=1') : $dest;
            $target = $embeddable ? ' target="pscontent"' : '';
            $o .= '<div style="' . $pillStyle . ';padding:2px">';
            $o .= '<button type="submit" name="pstage" value="' . $h($k) . '" title="Set as current stage" style="background:transparent;border:none;color:inherit;cursor:pointer;padding:7px 0 7px 10px">' . $circle . '</button>';
            $o .= '<a href="' . $h($href) . '"' . $target . ' style="flex:1;color:inherit;text-decoration:none;padding:7px 10px 7px 6px">' . $h($label) . '</a>';
            $o .= '</div>';
        } else {
            $o .= '<button type="submit" name="pstage" value="' . $h($k) . '" style="' . $pillStyle . ';padding:9px 14px;cursor:pointer">' . $circle . $h($label) . '</button>';
        }
    }
    $o .= '</div></form></div>';
    // Right: the content panel for buyer records (nothing to show yet for sellers, whose stages have no screens).
    if ($side === 'buyer') {
        $defDest = ps_dest($side, $cur, $tx);
        $src = ($defDest !== null && ps_embeddable($cur)) ? ($defDest . '&embed=1') : '';
        $o .= '<iframe name="pscontent" id="pscontent"' . ($src !== '' ? ' src="' . $h($src) . '"' : '') . ' style="flex:1;min-width:280px;min-height:560px;border:1px solid #d7e2e2;border-radius:12px;background:#fff"';
        if ($src === '') { $o .= ' srcdoc="<body style=\'font-family:sans-serif;color:#6a777a;padding:20px;margin:0\'>Click a stage on the left to view it here.</body>"'; }
        $o .= '></iframe>';
    }
    $o .= '</div></div>';
    return $o;
}
// A colored-pill summary (counts per stage) plus the records grouped under each stage, for the "choose a record" list view.
function ps_group_box($side, array $records, callable $urlFn) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $byStage = []; foreach (ps_keys($side) as $k) $byStage[$k] = [];
    foreach ($records as $r) { $k = ps_get($r['folder'], $side, (int)($r['stage'] ?? 0)); $byStage[$k][] = $r; }
    $o = '<div style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 16px">';
    foreach (PS_STAGES[$side] as $k => $stageLabel) {
        $bg = ps_color($side, $k); $fg = ps_fg($side, $k); $n = count($byStage[$k]);
        $o .= '<span style="background:' . $bg . ';color:' . $fg . ';font-weight:700;font-size:.82rem;padding:5px 10px;border-radius:99px">' . $h($stageLabel) . ' <span style="opacity:.85">' . $n . '</span></span>';
    }
    $o .= '</div>';
    foreach (PS_STAGES[$side] as $k => $stageLabel) {
        if (!$byStage[$k]) continue;
        $o .= '<h3 style="margin:14px 0 6px">' . $h($stageLabel) . '</h3>';
        foreach ($byStage[$k] as $r) { $o .= '<p style="margin:0 0 6px"><a href="' . $h($urlFn($r)) . '">' . $h($r['name']) . '</a>' . (!empty($r['lead_id']) ? ' <span style="color:#6a777a;font-size:.85rem">&middot; linked to a lead</span>' : '') . '</p>'; }
    }
    if (!$records) $o .= '<p class="note">No records found yet.</p>';
    return $o;
}
