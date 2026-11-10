<?php
// Pipeline sub-stage for buyer/seller records: a finer, agent-set stage shown as colored pills (same pattern as the
// Leads sub-category buttons), stored on the transaction record itself and independent of which Drive folder (Buyers /
// Pending / Archived, etc.) the record's files happen to sit in right now.
require_once __DIR__ . '/flrdb.php';
require_once __DIR__ . '/dlib.php';
require_once __DIR__ . '/sigtools.php';

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
// Whether an executed contract/offer/addendum has been signed and filed to this transaction (same match used by
// signed_filed.php's "Executed Contract" screen). Used to merge the "Make An Offer" and "Executed Contract" rungs: one
// rung shows "Make An Offer" until a contract is filed, then switches to "Executed Contract" on its own.
function ps_contract_filed($tx) {
    $me = strtolower((string)($_SESSION['email'] ?? ''));
    if ($me === '') return false;
    foreach (sg_for_agent($me) as $r) {
        if ((string)($r['folder']['id'] ?? '') !== $tx || empty($r['completed'])) continue;
        $t = strtolower((string)($r['form'] ?? ''));
        if (strpos($t, 'contract') !== false || strpos($t, 'offer') !== false || strpos($t, 'addendum') !== false) return true;
    }
    return false;
}
// The merged "Make An Offer" / "Executed Contract" rung's current label, destination, whether it opens in the panel,
// and which underlying pstage key it represents.
function ps_offer_contract_state($tx) {
    if (ps_contract_filed($tx)) return ['label' => 'Executed Contract', 'dest' => 'signed_filed.php?tx=' . urlencode($tx) . '&kind=contract', 'embeddable' => true, 'key' => 'contract'];
    return ['label' => 'Make An Offer', 'dest' => 'wizard.php?tx=' . urlencode($tx), 'embeddable' => false, 'key' => 'offer'];
}
// The rungs shown in the buyer ladder: "offer" stands in for both "offer" and "contract" (see ps_offer_contract_state).
function ps_buyer_rungs() { return ['agreement', 'shopping', 'offer', 'contingencies', 'closingready', 'closed']; }
// Which buyer-agreement type (if any) is filed to this transaction: 'exclusive' (Exclusive Buyer Brokerage Agreement --
// covers every home shown from here on, no further agreement needed) or 'showing' (Showing Agreement -- covers only the
// home(s) named on that signed form; a new one, or moving up to Exclusive, is needed for anything else). If more than
// one is on file, the most recently signed one wins.
const BAG_EXCLUSIVE_FORM_ID = '1IoJl7SSl2grnKbpLMqH_L1gq15XDJi7U';
const BAG_SHOWING_FORM_ID = '17tIOcmfAHRbhshBrsADOlJxeFlgAEsi8';
function bag_agreement_type($tx) {
    $me = strtolower((string)($_SESSION['email'] ?? ''));
    if ($me === '') return null;
    foreach (sg_for_agent($me) as $r) {
        if ((string)($r['folder']['id'] ?? '') !== $tx || empty($r['completed'])) continue;
        $t = strtolower((string)($r['form'] ?? ''));
        if (strpos($t, 'exclusive buyer brokerage') !== false) return 'exclusive';
        if (strpos($t, 'showing agreement') !== false && strpos($t, 'amendment') === false) return 'showing';
    }
    return null;
}
// The two agreement-choice cards (send for signature / download), shared by buyer_consult.php (before the buyer record
// exists, $tx is '') and buyer_agreement.php (once it does, so the signature request is tied to the transaction).
function bag_choice_cards($tx) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $q = $tx !== '' ? '&tx=' . urlencode($tx) : '';
    $target = $tx !== '' ? ' target="_top"' : '';
    $o = '<div style="display:flex;flex-wrap:wrap;gap:16px">';
    $o .= '<div class="panel sigbox" style="flex:1;min-width:260px"><h3 style="margin-top:0">Exclusive Buyer Brokerage Agreement</h3>';
    $o .= '<p class="note">Covers every home shown to this buyer from here on &mdash; no further agreement needed.</p>';
    $o .= '<p><a class="btn" href="sign.php?f=' . BAG_EXCLUSIVE_FORM_ID . '&s=b' . $h($q) . '"' . $target . '>Send for signature</a> <a class="btn ghost" href="https://drive.google.com/uc?export=download&amp;id=' . BAG_EXCLUSIVE_FORM_ID . '" target="_blank" rel="noopener">Download</a></p></div>';
    $o .= '<div class="panel sigbox" style="flex:1;min-width:260px"><h3 style="margin-top:0">Showing Agreement</h3>';
    $o .= '<p class="note">Covers only the home(s) named on this signed form. A home it doesn\'t name needs a new Showing Agreement, or an upgrade to Exclusive.</p>';
    $o .= '<p><a class="btn" href="sign.php?f=' . BAG_SHOWING_FORM_ID . '&s=b' . $h($q) . '"' . $target . '>Send for signature</a> <a class="btn ghost" href="https://drive.google.com/uc?export=download&amp;id=' . BAG_SHOWING_FORM_ID . '" target="_blank" rel="noopener">Download</a></p></div>';
    $o .= '</div>';
    return $o;
}

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
// The buyer/seller's name and contact info, as a compact bar with a single pencil that opens one edit screen (both
// contacts at once) in the panel to the right, the same way a pipeline stage does.
function ps_contact_bar($side, $tx, array $info) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $contacts = ps_contacts($side, $info);
    $o = '<div style="margin-bottom:16px">';
    if ($contacts) {
        foreach ($contacts as $c) {
            $o .= '<p style="margin:0 0 6px;display:flex;flex-wrap:wrap;gap:14px;align-items:baseline">';
            $o .= '<strong>' . $h($c['name']) . '</strong>';
            if ($c['phone'] !== '') $o .= '<a href="tel:' . $h(preg_replace('/[^0-9+]/', '', $c['phone'])) . '">' . $h($c['phone']) . '</a>';
            if ($c['email'] !== '') $o .= '<a href="mailto:' . $h($c['email']) . '">' . $h($c['email']) . '</a>';
            if ($c['phone'] === '' && $c['email'] === '') $o .= '<span style="color:#6a777a">No phone or email saved.</span>';
            $o .= '</p>';
        }
    } else {
        $o .= '<p class="note" style="margin:0 0 6px">No contact info saved yet.</p>';
    }
    $o .= '<p style="margin:0"><a href="contact_edit.php?tx=' . urlencode($tx) . '&side=' . urlencode($side) . '&embed=1" target="pscontent" style="color:#036f72;font-weight:700;text-decoration:none">&#9998; Edit contact info</a></p>';
    return $o . '</div>';
}
// The pill-button ladder for one record (click a step to move it to that pipeline stage — runs left to right, the
// first stage on the left and Closing Completed on the right), the buyer/seller's name and contact info above it with
// an edit pencil, and — for buyer records — the selected stage's screen in a panel below the stages, so clicking a
// stage shows its content right there instead of leaving the page or opening a new window.
function ps_box($side, $tx, $driveStage = 0, $recName = '', array $info = [], $contact = true) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $cur = ps_get($tx, $side, $driveStage);
    $o = '<div class="panel sigbox" style="margin-bottom:14px">';
    $o .= $contact ? ps_contact_bar($side, $tx, $info) : '<h2 style="margin:0 0 12px;font-size:1.15rem">Progress To A Closed Sale</h2>';
    $o .= '<style>.psrow{display:flex;gap:6px;overflow-x:auto;padding:4px 2px 8px;scroll-snap-type:x proximity}.psrow>*{flex:1 1 0;min-width:min-content;scroll-snap-align:start}.psrow>*{font-size:.8rem!important;gap:4px!important}.psrow a,.psrow button{line-height:1.25}.psrow a{padding:3px 6px 3px 4px!important}.psrow>div>button{padding:3px 0 3px 7px!important}.psrow>button{padding:5px 10px!important}.psrow span[title]{min-width:18px!important;height:18px!important}@media(max-width:700px){.psrow>*{flex:0 0 150px}}</style>';
    $o .= '<div style="display:flex;flex-direction:column;gap:14px">';
    // Top: the stages left to right (first stage on the left), acting as a menu.
    $o .= '<div><form method="post"><input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tx" value="' . $h($tx) . '"><input type="hidden" name="op" value="setpstage"><div class="psrow">';
    // Buyer records show 6 rungs: "offer" stands in for both Make An Offer and Executed Contract (ps_offer_contract_state
    // switches its label/destination on its own once a contract is filed). Sellers are unchanged.
    $rungKeys = $side === 'buyer' ? ps_buyer_rungs() : array_keys(PS_STAGES[$side]);
    $curDisp = ($side === 'buyer' && $cur === 'contract') ? 'offer' : $cur;
    $curPos = array_search($curDisp, $rungKeys, true); $curPos = $curPos === false ? 1 : $curPos + 1;
    $i = 0;
    foreach ($rungKeys as $k) {
        $i++;
        $merged = ($side === 'buyer' && $k === 'offer') ? ps_offer_contract_state($tx) : null;
        $storeKey = $merged ? $merged['key'] : $k;
        $on = $i === $curPos; $done = $i < $curPos;
        $bg = ps_color($side, $k); $fg = ps_fg($side, $k);
        $label = $merged ? $merged['label'] : ps_label($side, $k, $tx);
        $dest = $merged ? $merged['dest'] : ps_dest($side, $k, $tx);
        $pillStyle = 'display:flex;align-items:center;gap:8px;background:' . $bg . ';color:' . $fg . ';font-weight:700;font-size:.85rem;border-radius:10px;border:1.5px solid rgba(0,0,0,.1);text-align:left' . ($on ? ';outline:3px solid #1f2a2c;outline-offset:1px' : ($done ? '' : ';opacity:.6'));
        $circle = '<span style="background:rgba(255,255,255,.35);color:inherit;border-radius:99px;min-width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;flex:none" title="' . ($done ? 'Completed' : 'Set as current stage') . '">' . ($done ? '&#10003;' : $i) . '</span>';
        if ($dest !== null) {
            // Split control: the numbered circle sets this as the current stage; the label opens that stage's screen in
            // the panel below (except Make An Offer, which still opens the full Wizard page).
            $embeddable = $merged ? $merged['embeddable'] : ps_embeddable($k);
            $href = $embeddable ? ($dest . '&embed=1') : $dest;
            $target = $embeddable ? ' target="pscontent"' : '';
            $o .= '<div' . ($on ? ' data-cur="1"' : '') . ' style="' . $pillStyle . ';padding:2px">';
            $o .= '<button type="submit" name="pstage" value="' . $h($storeKey) . '" title="Set as current stage" style="background:transparent;border:none;color:inherit;cursor:pointer;padding:7px 0 7px 10px">' . $circle . '</button>';
            $o .= '<a href="' . $h($href) . '"' . $target . ' style="flex:1;color:inherit;text-decoration:none;padding:7px 10px 7px 6px">' . $h($label) . '</a>';
            $o .= '</div>';
        } else {
            $o .= '<button type="submit" name="pstage" value="' . $h($storeKey) . '"' . ($on ? ' data-cur="1"' : '') . ' style="' . $pillStyle . ';padding:9px 10px;cursor:pointer">' . $circle . $h($label) . '</button>';
        }
    }
    $o .= '</div></form></div>';
    // On a narrow screen the row scrolls sideways: start it at the current stage.
    $o .= '<script>(function(){var r=document.querySelectorAll(".psrow"),i;for(i=0;i<r.length;i++){var c=r[i].querySelector("[data-cur]");if(c&&r[i].scrollWidth>r[i].clientWidth)r[i].scrollLeft=Math.max(0,c.offsetLeft-r[i].offsetLeft-24);}})();</script>';
    // Below: the content panel. Every buyer stage (but Make An Offer) opens here; sellers' stages have no screens yet,
    // but the panel still exists so the "Edit contact info" pencil above has somewhere to open into.
    $src = '';
    if ($side === 'buyer') {
        $curMerged = $curDisp === 'offer' ? ps_offer_contract_state($tx) : null;
        $defDest = $curMerged ? $curMerged['dest'] : ps_dest($side, $cur, $tx);
        $defEmbeddable = $curMerged ? $curMerged['embeddable'] : ps_embeddable($cur);
        $src = ($defDest !== null && $defEmbeddable) ? ($defDest . '&embed=1') : '';
    }
    $o .= '<iframe name="pscontent" id="pscontent"' . ($src !== '' ? ' src="' . $h($src) . '"' : '') . ' style="width:100%;min-height:560px;border:1px solid #d7e2e2;border-radius:12px;background:#fff"';
    if ($src === '') { $o .= ' srcdoc="<body style=\'font-family:sans-serif;color:#6a777a;padding:20px;margin:0\'>Click a stage, or Edit contact info, to view it here.</body>"'; }
    $o .= ' scrolling="no"></iframe>';
    // Grow the panel to fit its screen, so the page scrolls instead of the panel (only a document viewer inside keeps its own scroll).
    $o .= '<script>(function(){var f=document.getElementById("pscontent");if(!f)return;var ro=null;
function fit(){try{var d=f.contentDocument;if(!d||!d.body)return;var h=Math.max(d.documentElement.scrollHeight,d.body.scrollHeight);f.style.height=Math.max(560,h+2)+"px";}catch(e){f.removeAttribute("scrolling");}}
f.addEventListener("load",function(){fit();try{if(ro)ro.disconnect();ro=new ResizeObserver(fit);ro.observe(f.contentDocument.body);}catch(e){}setTimeout(fit,300);setTimeout(fit,1500);});})();</script>';
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
