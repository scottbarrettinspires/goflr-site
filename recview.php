<?php
// The client header card at the top of a buyer or seller record (buyer.php / seller.php), styled like Leads & Contacts:
// stage dot, client name(s), stage · address · source, next deadline, Call / Text / Email, and what the linked lead knows.
// Contact details come from the record first, then the linked lead, so the card is never blank when we know who it is.
require_once __DIR__ . '/leadbox.php';
require_once __DIR__ . '/pstage.php';
require_once __DIR__ . '/dlib.php';
require_once __DIR__ . '/ablib.php';

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
        if ($nm !== '' || ($n === 1 && ($ph !== '' || $em !== ''))) $people[] = ['n' => $n, 'name' => $nm, 'phone' => $ph, 'email' => $em];
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
    flr_outcome_columns(); $oc = (string)($rec['outcome'] ?? ''); $ocOn = (string)($rec['outcome_on'] ?? '');
    if ($oc === '') { $q = flr_db()->prepare('SELECT outcome, outcome_on FROM transactions WHERE folder = ?'); $q->execute([$tx]); $x = $q->fetch() ?: []; $oc = (string)($x['outcome'] ?? ''); $ocOn = (string)($x['outcome_on'] ?? ''); }
    if ($oc !== '') $tag = '<span class="due over">' . $h((AB_OUTCOMES[$oc] ?? 'Cancelled') . ($ocOn ? ' · ' . date('M j, Y', strtotime($ocOn)) : '')) . '</span>';
    $o = (!empty($GLOBALS['rvErr']) ? '<p class="sigerr">' . $h($GLOBALS['rvErr']) . '</p>' : '') . '<div class="rvh"><div class="rvtop"><span class="dot" style="background:' . $h(ps_color($side, $d['stage'])) . '"></span>'
        . '<div class="rvwho"><div class="rvnm">' . $h($d['title']) . '</div><div class="rvmeta">' . $h(implode(' · ', $meta)) . '</div></div>' . $tag . '</div>';
    $epjs = '<script>(function(){var eps=document.querySelectorAll(".rvep"),btns=document.querySelectorAll(".rvedit");if(!eps.length)return;'
        . 'btns.forEach(function(b){b.onclick=function(){var box=document.querySelector(".rvep[data-n=\'"+b.dataset.n+"\']"),open=box.hidden;'
        . 'eps.forEach(function(x){x.hidden=true;});btns.forEach(function(x){x.setAttribute("aria-expanded","false");x.classList.remove("on");});'
        . 'if(open){box.hidden=false;b.classList.add("on");b.setAttribute("aria-expanded","true");var fr=box.querySelector("iframe");if(!fr.src)fr.src=fr.dataset.src;box.scrollIntoView({behavior:"smooth",block:"nearest"});}};});'
        . 'window.addEventListener("message",function(e){if(e.origin!==location.origin||!e.data||!e.data.flrcontact)return;eps.forEach(function(x){var fr=x.querySelector("iframe");if(fr.contentWindow===e.source){if(e.data.h)fr.style.height=(e.data.h+8)+"px";}});if(e.data.saved)setTimeout(function(){location.reload();},900);});})();</script>';
    // One line per person, each with its own Call / Text / Email.
    $o .= '<div class="rvppl">';
    if (!$d['people']) $o .= '<div class="rvp"><span class="rvnone">No phone or email saved yet.</span></div>';
    foreach ($d['people'] as $pp) {
        $dig = preg_replace('/[^0-9+]/', '', $pp['phone']);
        $o .= '<div class="rvp"><div class="rvpi"><b>' . $h($pp['name'] ?: ucfirst($w)) . '</b>'
            . ($pp['phone'] !== '' ? '<span>' . $h($pp['phone']) . '</span>' : '') . ($pp['email'] !== '' ? '<span>' . $h($pp['email']) . '</span>' : '') . '<button type="button" class="rvedit" data-n="' . (int)$pp['n'] . '" aria-expanded="false" title="Edit contact info"><span class="rvpen">&#9998;</span> Edit</button>'
            . ($pp['phone'] === '' && $pp['email'] === '' ? '<span class="rvnone">No phone or email saved.</span>' : '') . '</div><span class="qa">'
            . ($dig !== '' ? '<a href="tel:' . $h($dig) . '" title="Call">&#9742;</a><a href="sms:' . $h($dig) . '" title="Text"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg></a>' : '')
            . ($pp['email'] !== '' ? '<a href="mailto:' . $h($pp['email']) . '" title="Email">&#9993;</a>' : '') . '</span>'
            . '<div class="rvep" hidden data-n="' . (int)$pp['n'] . '"><iframe title="Edit contact info" data-src="contact_edit.php?tx=' . urlencode($tx) . '&amp;side=' . urlencode($side) . '&amp;n=' . (int)$pp['n'] . '&amp;embed=1" style="width:100%;border:0;min-height:120px"></iframe></div></div>';
    }
    $o .= '</div>' . $epjs;
    // Actions row.

    // Actions row.
    $o .= '<div class="rvact">';
    $dd = '';
    if ($d['lead']) { // the lead's own Property Details, Notes and History, opened right here (same panes as the lead record)
        $L = $d['lead']; $lid = (string)$L['id'];
        $nn = trim((string)($L['notes'] ?? '')) === '' ? 0 : count(preg_split('/\r?\n/', trim((string)$L['notes']))); $hn = count((array)($L['log'] ?? []));
        $pane = $side === 'seller' ? 'home' : 'wants';
        foreach ([['client', 'Client Details', null], [$pane, 'Property Details', null], ['notes', 'Notes', $nn], ['hist', 'History', $hn]] as $t)
            $o .= '<button type="button" class="rvtab" data-pane="' . $t[0] . '" aria-expanded="false">' . $h($t[1]) . ($t[2] !== null ? ' <span class="rvn">' . (int)$t[2] . '</span>' : '') . ' <span class="rvcar">&#9662;</span></button>';
        $dd = '<div class="rvpanes" data-lead="' . $h($lid) . '" data-tx="' . $h($tx) . '" data-side="' . $h($side) . '" hidden><iframe class="rvframe" title="Lead details" style="width:100%;border:0;min-height:120px"></iframe></div>'
            . '<script>(function(){var box=document.querySelector(".rvpanes");if(!box)return;var fr=box.querySelector("iframe"),cur="";'
            . 'document.querySelectorAll(".rvtab").forEach(function(b){b.onclick=function(){var p=b.dataset.pane;if(cur===p){cur="";box.hidden=true;b.setAttribute("aria-expanded","false");b.classList.remove("on");return;}'
            . 'document.querySelectorAll(".rvtab").forEach(function(x){x.classList.remove("on");x.setAttribute("aria-expanded","false");});cur=p;b.classList.add("on");b.setAttribute("aria-expanded","true");box.hidden=false;'
            . 'fr.src="leads.php?embed=1&open="+encodeURIComponent(box.dataset.lead)+"&pane="+encodeURIComponent(p)+"&tx="+encodeURIComponent(box.dataset.tx)+"&side="+encodeURIComponent(box.dataset.side);};});'
            . 'window.addEventListener("message",function(e){if(e.origin!==location.origin||!e.data||!e.data.flrpane)return;if(e.data.pane===cur&&e.data.h)fr.style.height=(e.data.h+8)+"px";});})();</script>';
    }
    $o .= rv_outcome_ui($side, $tx, $oc) . '</div>' . $dd . rv_price_ui($d['stage'], $tx, $info);
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
        $o = '';
        if (!empty($_GET['linked'])) $o .= '<div class="rvlead"><p class="rvok">Linked. This ' . $w . ' now shows in Leads &amp; Contacts.</p></div>';
        return $o;
    }
    $m = ld_find_match($me, $side, $info[$p . '_email'] ?? '', $info[$p . '_phone'] ?? '');
    if ($m) return '<div class="rvlink"><span>Same contact as your lead <b>' . $h(trim(($m['first'] ?? '') . ' ' . ($m['last'] ?? ''))) . '</b>. Link them?</span>' . $form($m['id'], 'Yes, link them', '') . $form('new', 'Add as new contact', ' ghost') . '</div>';
    return '<div class="rvlink"><span>Not in Leads &amp; Contacts yet.</span>' . $form('new', 'Add to Leads & Contacts', '') . '</div>';
}

function rv_css() {
    return '<style>
.rvback{margin:0 0 14px}
.rvedit{font:inherit;font-weight:700;font-size:.9rem;color:var(--teal,#036f72);background:none;border:0;padding:0;cursor:pointer}.rvedit.on{text-decoration:underline}.rvpen{display:inline-block;transform:scaleX(-1)}
.rvep{flex:0 0 100%;margin:4px 0 0;padding:10px 0 0;border-top:1px solid var(--line,#dfe7e7)}
.rvtab{font:inherit;font-weight:700;font-size:.92rem;color:var(--teal,#036f72);background:none;border:0;padding:0;cursor:pointer}.rvtab.on{text-decoration:underline}.rvtab .rvn{font-size:.72rem;background:#eef3f3;border-radius:99px;padding:1px 7px}.rvtab .rvcar{font-size:.75rem}
.rvpanes{margin:10px 0 0;padding-top:10px;border-top:1px solid var(--line,#dfe7e7)}
.rvh{background:var(--white,#fff);border:1px solid var(--line,#dfe7e7);border-radius:14px;padding:16px;margin:0 0 14px}
.rvtop{display:flex;gap:12px;align-items:center}.rvh .dot{width:14px;height:14px;border-radius:50%;flex:0 0 14px;border:1px solid rgba(0,0,0,.15)}
.rvwho{flex:1;min-width:0}.rvnm{font-weight:800;font-size:1.35rem;line-height:1.2}.rvmeta{font-size:.88rem;color:#5a6a6c;margin-top:2px}
.rvh .due{font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:99px;white-space:nowrap}
.due.over{background:#fde2e1;color:#b71c1c}.due.today{background:#fff1d6;color:#8a5a00}.due.later{background:#eef3f3;color:#4b5b5d}
.rvppl{margin:12px 0 0;display:flex;flex-direction:column;gap:8px}
.rvp{display:flex;flex-wrap:wrap;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--line,#dfe7e7);border-radius:12px}
.rvpi{flex:1;min-width:0;display:flex;flex-wrap:wrap;gap:4px 16px;align-items:baseline}.rvpi span{color:#4b5b5d;font-size:.9rem}
.rvh .qa{display:flex;flex:0 0 auto}.rvh .qa a{display:inline-flex;width:32px;height:32px;border-radius:9px;align-items:center;justify-content:center;background:var(--teal-soft,#e1f3f2);color:var(--teal,#036f72);text-decoration:none;font-size:.9rem;margin-left:4px}
.rvnone{color:#8a9a9c}
.rvact{display:flex;flex-wrap:wrap;gap:6px 18px;margin:12px 0 0;font-size:.9rem}.rvact a{color:var(--teal,#036f72);font-weight:700;text-decoration:none}.rvact a:hover{text-decoration:underline}
.rvlead{margin:12px 0 0;padding:10px 12px;background:#f4f8f8;border-radius:10px;font-size:.9rem;color:#2a3638}.rvlead p{margin:0 0 4px}.rvlead p:last-child{margin:0}.rvok{color:#2e7d32;font-weight:600}
.rvlink{margin:12px 0 0;padding:10px 12px;background:#fff8ec;border:1px solid #f3dfb8;border-radius:10px;font-size:.9rem;display:flex;flex-wrap:wrap;gap:8px;align-items:center}.rvlink span{flex:1;min-width:200px}
.rvbtn{font:inherit;font-size:.82rem;font-weight:700;padding:5px 12px;border-radius:8px;border:1px solid var(--teal,#036f72);background:var(--teal,#036f72);color:#fff;cursor:pointer}.rvbtn.ghost{background:#fff;color:var(--teal,#036f72)}
.rvh~.panel.sigbox,.rvh~details.panel{max-width:none;border-radius:14px}
.rvoc{display:inline}.rvoc summary{cursor:pointer;color:#b71c1c;font-weight:700;list-style:none}.rvoc summary::-webkit-details-marker{display:none}
.rvoc[open]{display:block;flex-basis:100%}.rvoc form{display:flex;flex-wrap:wrap;gap:8px 12px;align-items:flex-end;margin:8px 0 0;padding:10px 12px;border:1px solid #f3c7c4;background:#fff6f5;border-radius:10px}
.rvoc label{display:flex;flex-direction:column;font-size:.75rem;font-weight:700;color:#4b5b5d;gap:2px}.rvoc select,.rvoc input{font:inherit;font-size:.88rem;padding:6px 8px;border:1px solid var(--line,#dfe7e7);border-radius:8px;background:#fff}
.rvoc form p{flex-basis:100%;margin:0;font-size:.8rem;color:#5a6a6c}.rvlnk{font:inherit;font-weight:700;color:var(--teal,#036f72);background:none;border:0;padding:0;cursor:pointer}
.rvpr{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;margin:12px 0 0;padding:10px 12px;border:1px solid var(--line,#dfe7e7);border-radius:10px}.rvprl{font-size:.85rem;font-weight:700;color:#4b5b5d}.rvpr .prx{margin:0}
.rvsec{background:var(--white,#fff);border:1px solid var(--line,#dfe7e7);border-radius:14px;padding:16px;margin:0 0 14px}.rvsec>h2{margin:0 0 12px;font-size:1.15rem}
@media(max-width:640px){.rvtop{flex-wrap:wrap}.rvwho{flex:1 1 calc(100% - 30px)}.rvh .due{margin-left:26px;white-space:normal}.rvp{align-items:flex-start}}
</style>';
}

// Cancelled / Expired control on the record header: a small form to mark the file, or to make it active again.
function rv_outcome_ui($side, $tx, $oc) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $hid = '<input type="hidden" name="csrf" value="' . $h($_SESSION['csrf'] ?? '') . '"><input type="hidden" name="tx" value="' . $h($tx) . '"><input type="hidden" name="op" value="setoutcome">';
    if ($oc !== '') return '<form method="post" class="rvoc">' . $hid . '<input type="hidden" name="outcome" value=""><button type="submit" class="rvlnk">Make active again</button></form>';
    $opts = ''; foreach (ab_outcomes_for($side) as $k => $l) $opts .= '<option value="' . $h($k) . '">' . $h($l) . '</option>';
    return '<details class="rvoc"><summary>Mark cancelled or expired</summary><form method="post">' . $hid
        . '<label>What happened<select name="outcome">' . $opts . '</select></label><label>Date<input type="date" name="on" value="' . date('Y-m-d') . '" max="' . date('Y-m-d') . '"></label>'
        . '<button type="submit" class="rvbtn">Save</button><p>The file moves to Performance &rsaquo; Cancelled &amp; Expired and leaves Active Business. You can make it active again.</p></form></details>';
}
// POST op=setoutcome on a record page. Redirects on success; returns an error message otherwise.
function rv_handle_outcome($me, $tx, $self) {
    if (($_POST['op'] ?? '') === 'setprice') { require_once __DIR__ . '/perfcards.php'; return pf_handle_post($me, $self); } // price / commission from the header
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['op'] ?? '') !== 'setoutcome') return '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) return 'This page expired. Please reload and try again.';
    $e = ab_set_outcome($me, $tx, (string)($_POST['outcome'] ?? ''), (string)($_POST['on'] ?? ''));
    if ($e === '') { header('Location: ' . $self); exit; }
    return $e;
}

// Sold / contract price and gross commission on the header, for files under contract or closed (same editor as Performance).
function rv_price_ui($stage, $tx, array $info) {
    if ($stage !== 'closed' && !in_array($stage, AB_PENDING, true)) return '';
    require_once __DIR__ . '/perfcards.php';
    $r = ['t' => ['folder' => $tx], 'price' => ab_price($tx, $info), 'comm' => ab_comm($tx)];
    $closed = $stage === 'closed';
    return '<div class="rvpr"><span class="rvprl">' . ($closed ? 'Sold price &amp; commission' : 'Contract price &amp; commission') . '</span>'
        . pf_price_ui($r, $closed ? '+ Sold price' : '+ Contract price', $closed ? 'sold price' : 'contract price') . '</div>' . pf_css() . pf_js();
}
