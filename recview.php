<?php
// The client header card at the top of a buyer or seller record (buyer.php / seller.php), styled like Leads & Contacts:
// stage dot, client name(s), stage · address · source, next deadline, Call / Text / Email, and what the linked lead knows.
// Contact details come from the record first, then the linked lead, so the card is never blank when we know who it is.
require_once __DIR__ . '/leadbox.php';
require_once __DIR__ . '/pstage.php';
require_once __DIR__ . '/dlib.php';

// Plain data for the header (also handy for a future JSON endpoint): names, contacts, stage, address, lead, next deadline.
function rv_data($me, $side, $tx, array $rec, array $info) {
    $p = $side === 'seller' ? 's' : 'b';
    $lead = ld_find_by_folder($me, $tx);
    $people = [];
    foreach ([1, 2] as $n) {
        $nm = trim((string)($info[$p . $n . '_name'] ?? '')); $ph = trim((string)($info[$p . $n . '_phone'] ?? '')); $em = trim((string)($info[$p . $n . '_email'] ?? ''));
        if ($n === 1 && $lead) { // fill gaps from the lead
            if ($nm === '') $nm = trim(($lead['first'] ?? '') . ' ' . ($lead['last'] ?? ''));
            if ($ph === '') $ph = trim((string)($lead['phone'] ?? ''));
            if ($em === '') $em = trim((string)($lead['email'] ?? ''));
        }
        if ($nm !== '' || ($n === 1 && ($ph !== '' || $em !== ''))) $people[] = ['name' => $nm, 'phone' => $ph, 'email' => $em];
    }
    $k = ps_get($tx, $side, (int)($rec['stage'] ?? 0));
    $addr = trim((string)($info['property'] ?? ''));
    $recName = (string)($rec['name'] ?? '');
    if ($addr === '' && $side === 'seller') $addr = $recName; // listing folders are named by address
    $next = null; $c = dl_get($tx);
    if ($c) foreach (dl_compute(is_array($c['data']) ? $c['data'] : (json_decode((string)$c['data'], true) ?: [])) as $d) { if (!$d['done']) { $next = $d; break; } }
    $title = $people ? implode(' & ', array_filter(array_column($people, 'name'))) : '';
    if ($title === '') $title = $recName ?: ucfirst($side);
    if ($addr === $title) $addr = '';
    return ['side' => $side, 'tx' => $tx, 'stage' => $k, 'stage_label' => PS_STAGES[$side][$k] ?? '', 'title' => $title, 'people' => $people,
        'address' => $addr, 'source' => (string)($lead['source'] ?? ''), 'lead' => $lead, 'next' => $next];
}

function rv_header($me, $side, $tx, array $rec, array $info) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $d = rv_data($me, $side, $tx, $rec, $info); $w = $side === 'seller' ? 'seller' : 'buyer';
    $meta = array_filter([$d['stage_label'], $d['address'], $d['source']]);
    if ($d['stage'] === 'closed') $tag = '<span class="due" style="background:#e3f4e8;color:#2e7d32">Closed</span>';
    elseif ($d['next']) { $n = $d['next']; $cl = $n['left'] < 0 ? 'over' : ($n['left'] <= 3 ? 'today' : 'later'); $tag = '<span class="due ' . $cl . '">' . $h($n['label'] . ' · ' . dl_when($n['left'])) . '</span>'; }
    else $tag = '<span class="due later">No deadlines entered</span>';
    $o = '<div class="rvh"><div class="rvtop"><span class="dot" style="background:' . $h(ps_color($side, $d['stage'])) . '"></span>'
        . '<div class="rvwho"><div class="rvnm">' . $h($d['title']) . '</div><div class="rvmeta">' . $h(implode(' · ', $meta)) . '</div></div>' . $tag . '</div>';
    // One line per person, each with its own Call / Text / Email.
    $o .= '<div class="rvppl">';
    if (!$d['people']) $o .= '<div class="rvp"><span class="rvnone">No phone or email saved yet.</span></div>';
    foreach ($d['people'] as $pp) {
        $dig = preg_replace('/[^0-9+]/', '', $pp['phone']);
        $o .= '<div class="rvp"><div class="rvpi"><b>' . $h($pp['name'] ?: ucfirst($w)) . '</b>'
            . ($pp['phone'] !== '' ? '<span>' . $h($pp['phone']) . '</span>' : '') . ($pp['email'] !== '' ? '<span>' . $h($pp['email']) . '</span>' : '')
            . ($pp['phone'] === '' && $pp['email'] === '' ? '<span class="rvnone">No phone or email saved.</span>' : '') . '</div><span class="qa">'
            . ($dig !== '' ? '<a href="tel:' . $h($dig) . '" title="Call">&#9742;</a><a href="sms:' . $h($dig) . '" title="Text"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg></a>' : '')
            . ($pp['email'] !== '' ? '<a href="mailto:' . $h($pp['email']) . '" title="Email">&#9993;</a>' : '') . '</span></div>';
    }
    $o .= '</div>';
    // Actions row.
    $o .= '<div class="rvact"><a href="contact_edit.php?tx=' . urlencode($tx) . '&amp;side=' . urlencode($side) . '&amp;embed=1" target="pscontent" onclick="var f=document.getElementById(\'pscontent\');if(f)setTimeout(function(){f.scrollIntoView({behavior:\'smooth\',block:\'start\'})},50)">&#9998; Edit contact info</a>';
    if ($d['lead']) $o .= '<a href="leads.php?open=' . urlencode($d['lead']['id']) . '">Open in Leads &amp; Contacts</a>';
    $o .= '<a href="https://drive.google.com/drive/folders/' . urlencode($tx) . '" target="_blank" rel="noopener">Open the ' . ($side === 'seller' ? 'listing' : 'buyer') . ' folder</a></div>';
    $o .= lb_strip($me, $side, $tx, $info, $d['lead']);
    return $o . '</div>';
}

// What the lead knows (linked), or a one-line prompt to link / add the client (not linked).
function lb_strip($me, $side, $tx, array $info, $link) {
    $h = function ($s) { return htmlspecialchars((string)$s); }; $w = lb_word($side); $p = lb_prefix($side);
    $form = function ($lid, $label, $cls) use ($h, $tx) {
        return '<form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tx" value="' . $h($tx) . '"><input type="hidden" name="op" value="linklead"><input type="hidden" name="lid" value="' . $h($lid) . '"><button type="submit" class="rvbtn' . $cls . '">' . $h($label) . '</button></form>';
    };
    if ($link) {
        $pk = ld_pack_for_record($link, ld_load($me)['leads']);
        $nl = array_slice(array_values(array_filter(preg_split('/\r?\n/', (string)($link['notes'] ?? '')), 'strlen')), -3);
        $bits = array_filter([($pk['lead_ref'] ?? '') ? 'Referred by ' . $pk['lead_ref'] : '', ($pk['lead_added'] ?? '') ? 'Lead since ' . $pk['lead_added'] : '']);
        $t = $side === 'seller' ? ld_home_text($link['home'] ?? []) : ld_wants_text($link['wants'] ?? []);
        $o = '<div class="rvlead">';
        if (!empty($_GET['linked'])) $o .= '<p class="rvok">Linked. This ' . $w . ' now shows in Leads &amp; Contacts.</p>';
        if ($bits) $o .= '<p>' . $h(implode(' · ', $bits)) . '</p>';
        $o .= '<p><strong>' . ($side === 'seller' ? 'The home:' : 'Looking for:') . '</strong> ' . ($t !== '' ? $h($t) : '<span class="rvnone">Nothing recorded yet.</span>') . '</p>';
        if ($nl) $o .= '<p><strong>Latest notes:</strong> ' . implode(' · ', array_map($h, $nl)) . '</p>';
        return $o . '</div>';
    }
    $m = ld_find_match($me, $side, $info[$p . '_email'] ?? '', $info[$p . '_phone'] ?? '');
    if ($m) return '<div class="rvlink"><span>Same contact as your lead <b>' . $h(trim(($m['first'] ?? '') . ' ' . ($m['last'] ?? ''))) . '</b>. Link them?</span>' . $form($m['id'], 'Yes, link them', '') . $form('new', 'Add as new contact', ' ghost') . '</div>';
    return '<div class="rvlink"><span>Not in Leads &amp; Contacts yet.</span>' . $form('new', 'Add to Leads & Contacts', '') . '</div>';
}

function rv_css() {
    return '<style>
.rvback{margin:0 0 14px}
.rvh{background:var(--white,#fff);border:1px solid var(--line,#dfe7e7);border-radius:14px;padding:16px;margin:0 0 14px}
.rvtop{display:flex;gap:12px;align-items:center}.rvh .dot{width:14px;height:14px;border-radius:50%;flex:0 0 14px;border:1px solid rgba(0,0,0,.15)}
.rvwho{flex:1;min-width:0}.rvnm{font-weight:800;font-size:1.35rem;line-height:1.2}.rvmeta{font-size:.88rem;color:#5a6a6c;margin-top:2px}
.rvh .due{font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:99px;white-space:nowrap}
.due.over{background:#fde2e1;color:#b71c1c}.due.today{background:#fff1d6;color:#8a5a00}.due.later{background:#eef3f3;color:#4b5b5d}
.rvppl{margin:12px 0 0;display:flex;flex-direction:column;gap:8px}
.rvp{display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--line,#dfe7e7);border-radius:12px}
.rvpi{flex:1;min-width:0;display:flex;flex-wrap:wrap;gap:4px 16px;align-items:baseline}.rvpi span{color:#4b5b5d;font-size:.9rem}
.rvh .qa{display:flex;flex:0 0 auto}.rvh .qa a{display:inline-flex;width:32px;height:32px;border-radius:9px;align-items:center;justify-content:center;background:var(--teal-soft,#e1f3f2);color:var(--teal,#036f72);text-decoration:none;font-size:.9rem;margin-left:4px}
.rvnone{color:#8a9a9c}
.rvact{display:flex;flex-wrap:wrap;gap:6px 18px;margin:12px 0 0;font-size:.9rem}.rvact a{color:var(--teal,#036f72);font-weight:700;text-decoration:none}.rvact a:hover{text-decoration:underline}
.rvlead{margin:12px 0 0;padding:10px 12px;background:#f4f8f8;border-radius:10px;font-size:.9rem;color:#2a3638}.rvlead p{margin:0 0 4px}.rvlead p:last-child{margin:0}.rvok{color:#2e7d32;font-weight:600}
.rvlink{margin:12px 0 0;padding:10px 12px;background:#fff8ec;border:1px solid #f3dfb8;border-radius:10px;font-size:.9rem;display:flex;flex-wrap:wrap;gap:8px;align-items:center}.rvlink span{flex:1;min-width:200px}
.rvbtn{font:inherit;font-size:.82rem;font-weight:700;padding:5px 12px;border-radius:8px;border:1px solid var(--teal,#036f72);background:var(--teal,#036f72);color:#fff;cursor:pointer}.rvbtn.ghost{background:#fff;color:var(--teal,#036f72)}
.rvh~.panel.sigbox,.rvh~details.panel{max-width:none;border-radius:14px}
.rvsec{background:var(--white,#fff);border:1px solid var(--line,#dfe7e7);border-radius:14px;padding:16px;margin:0 0 14px}.rvsec>h2{margin:0 0 12px;font-size:1.15rem}
@media(max-width:640px){.rvtop{flex-wrap:wrap}.rvwho{flex:1 1 calc(100% - 30px)}.rvh .due{margin-left:26px;white-space:normal}.rvp{align-items:flex-start}}
</style>';
}
