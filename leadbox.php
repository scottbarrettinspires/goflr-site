<?php
// The "From Leads and Contacts" box on buyer and seller record pages, and the link / add actions behind it.
// $side is 'buyer' or 'seller'; the record's contact fields are b1_* (buyer) or s1_* (seller).
require_once __DIR__ . '/leadlib.php';

function lb_prefix($side) { return $side === 'seller' ? 's1' : 'b1'; }
function lb_word($side) { return $side === 'seller' ? 'seller' : 'buyer'; }

// POST op=linklead: link this record to an existing lead (lid) or add the person as a new contact (lid=new).
function lb_handle_link($me, $side, $tx, $recName, $stage, array $info, $self) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['op'] ?? '') !== 'linklead') return;
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? '')) || ld_find_by_folder($me, $tx)) { header('Location: ' . $self); exit; }
    $lid = (string)($_POST['lid'] ?? ''); $p = lb_prefix($side); $today = ld_today(); $w = lb_word($side);
    $stage = (int)$stage ?: 1;
    ld_update($me, function (&$db) use ($lid, $tx, $recName, $today, $info, $p, $side, $stage, $w) {
        $role = ['sub' => 'client', 'since' => $today, 'touches' => 0, 'last' => '', 'folder' => $tx, 'record' => $recName, 'stage' => $stage];
        if ($lid === 'new') {
            $nm = preg_split('/\s+/', trim((string)($info[$p . '_name'] ?? '')), 2);
            $L = ['id' => bin2hex(random_bytes(6)), 'first' => $nm[0] ?? '', 'last' => $nm[1] ?? '', 'phone' => (string)($info[$p . '_phone'] ?? ''), 'email' => (string)($info[$p . '_email'] ?? ''),
                'source' => '', 'notes' => '', 'added' => $today, 'created' => date('c'), 'roles' => [$side => $role], 'log' => [['d' => $today, 'role' => $side, 'mid' => '', 'note' => 'Added from the ' . $w . ' record in Active Business']]];
            if ($side === 'seller') $L['home'] = ['addr' => (string)($info['property'] ?? '') ?: $recName, 'updated' => $today]; // listing folders are named by address
            array_unshift($db['leads'], $L);
            if ($L['first'] === '' && $L['phone'] === '' && $L['email'] === '') { $db['leads'][0]['first'] = $recName; }
            return null;
        }
        foreach ($db['leads'] as &$L) { if (($L['id'] ?? '') === $lid) { unset($L['roles']['new']); $L['roles'][$side] = $role; $L['log'][] = ['d' => $today, 'role' => $side, 'mid' => '', 'note' => 'Linked to the ' . $w . ' record in Active Business']; } }
        return null;
    });
    $l = ld_find_by_folder($me, $tx); if ($l) flr_tx_set($tx, ['lead_id' => $l['id']]);
    header('Location: ' . $self . (strpos($self, '?') === false ? '?' : '&') . 'linked=1'); exit;
}

// After the record is saved: fill a missing phone or email on the linked lead (never overwrites).
function lb_fill_contact($me, $tx, $side, array $info) {
    $l = ld_find_by_folder($me, $tx); if (!$l) return; $p = lb_prefix($side); $lid = $l['id'];
    ld_update($me, function (&$db) use ($lid, $info, $p) { foreach ($db['leads'] as &$L) { if (($L['id'] ?? '') !== $lid) continue;
        if (trim((string)($L['phone'] ?? '')) === '' && ($info[$p . '_phone'] ?? '') !== '') $L['phone'] = $info[$p . '_phone'];
        if (trim((string)($L['email'] ?? '')) === '' && ($info[$p . '_email'] ?? '') !== '') $L['email'] = $info[$p . '_email']; } return null; });
}

function lb_box($me, $side, $tx, array $info) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $link = ld_find_by_folder($me, $tx); $w = lb_word($side); $p = lb_prefix($side);
    $form = function ($lid, $label, $cls) use ($h, $tx) {
        return '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tx" value="' . $h($tx) . '"><input type="hidden" name="op" value="linklead"><input type="hidden" name="lid" value="' . $h($lid) . '"><button class="btn sm' . $cls . '" type="submit">' . $h($label) . '</button></form> ';
    };
    $o = '<div class="panel" style="margin-bottom:14px;border-left:5px solid var(--teal,#036f72)">';
    if ($link) {
        $pk = ld_pack_for_record($link, ld_load($me)['leads']); $nh = count($link['log'] ?? []);
        $nl = array_slice(array_values(array_filter(preg_split('/\r?\n/', (string)($link['notes'] ?? '')), 'strlen')), -3);
        $o .= '<h2 style="margin:0 0 6px;font-size:1.1rem">From Leads and Contacts</h2>';
        if (!empty($_GET['linked'])) $o .= '<p class="note">Linked. This ' . $w . ' now shows in Leads and Contacts under ' . ($side === 'seller' ? 'Sellers' : 'Buyers') . ' &rsaquo; In Active Business.</p>';
        $o .= '<p style="margin:0 0 6px;color:#4b5b5d;font-size:.92rem">' . $h(implode(' · ', array_filter([($pk['lead_source'] ? 'Source: ' . $pk['lead_source'] : ''), ($pk['lead_ref'] ? 'Referred by ' . $pk['lead_ref'] : ''), ($pk['lead_added'] ? 'Lead since ' . date('M j, Y', strtotime($pk['lead_added'])) : ''), $nh . ' history ' . ($nh === 1 ? 'entry' : 'entries')]))) . '</p>';
        if ($side === 'seller') { $t = ld_home_text($link['home'] ?? []); $o .= '<p style="margin:0 0 6px"><strong>The home:</strong> ' . ($t !== '' ? $h($t) : '<span style="color:#6a777a">Nothing recorded yet. Add it on the lead\'s Property Details tab.</span>') . '</p>'; }
        else { $t = ld_wants_text($link['wants'] ?? []); $o .= '<p style="margin:0 0 6px"><strong>Looking for:</strong> ' . ($t !== '' ? $h($t) : '<span style="color:#6a777a">Nothing recorded yet. Add it on the lead\'s Property Details tab.</span>') . '</p>'; }
        if ($nl) $o .= '<p style="margin:0 0 6px;font-size:.9rem"><strong>Latest notes:</strong><br>' . implode('<br>', array_map($h, $nl)) . '</p>';
        $o .= '<p style="margin:8px 0 0"><a class="btn sm ghost" href="leads.php?open=' . urlencode($link['id']) . '">Open the lead: notes, history, property details</a></p>';
    } else {
        $m = ld_find_match($me, $side, $info[$p . '_email'] ?? '', $info[$p . '_phone'] ?? '');
        if ($m) {
            $by = ($info[$p . '_email'] ?? '') !== '' && strtolower(trim((string)($m['email'] ?? ''))) === strtolower(trim((string)$info[$p . '_email'])) ? 'email' : 'phone';
            $o .= '<h2 style="margin:0 0 6px;font-size:1.1rem">Is this your lead ' . $h(trim(($m['first'] ?? '') . ' ' . ($m['last'] ?? ''))) . '?</h2>'
                . '<p style="margin:0 0 8px;color:#4b5b5d;font-size:.92rem">A lead in Leads and Contacts has the same ' . $by . '. Link them so the lead\'s notes and history show here, follow-up pauses while you work the deal, and the ' . $w . ' moves to your Sphere after closing.</p>'
                . $form($m['id'], 'Yes, link them', '') . $form('new', 'No, add as a new contact', ' ghost');
        } else {
            $o .= '<h2 style="margin:0 0 6px;font-size:1.1rem">Not in Leads and Contacts yet</h2>'
                . '<p style="margin:0 0 8px;color:#4b5b5d;font-size:.92rem">This record was made outside the lead system. Add the ' . $w . ' as a contact so their history lives in one place and they move to your Sphere after closing.</p>'
                . $form('new', 'Add to Leads and Contacts', '');
        }
    }
    return $o . '</div>';
}
