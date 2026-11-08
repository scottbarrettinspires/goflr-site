<?php
// Active Business client rows (shared by Active Business and Performance): one row per buyer/seller record, styled like
// the Leads & Contacts list. ab_rows() returns plain data; ab_row() renders one row.
require_once __DIR__ . '/dlib.php';
require_once __DIR__ . '/pstage.php';
// Client rows for one side (used by the Buyers/Sellers tabs and the Overview's Active Clients card).
function ab_rows($me, $side, $list, $pk) {
    $out = [];
    require_once __DIR__ . '/notiflib.php'; require_once __DIR__ . '/leadlib.php';
    $infos = []; try { $infos = nt_infos($me, $list, true); } catch (Throwable $e) {} // client contact details from the record (cached 10 min)
    $leads = []; try { foreach (ld_load($me)['leads'] as $L) foreach (['buyer', 'seller'] as $r) if (!empty($L['roles'][$r]['folder'])) $leads[$L['roles'][$r]['folder']] = $L; } catch (Throwable $e) {}
    $keys = ps_keys($side); $p = $side === 'buyer' ? 'b' : 's';
    foreach ($list as $t) {
        $k = $pk($t); $in = $infos[$t['folder']] ?? []; $L = $leads[$t['folder']] ?? null;
        $nm = trim((string)($in[$p . '1_name'] ?? '')) ?: ($L ? trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? '')) : '') ?: (string)$t['name'];
        if (trim((string)($in[$p . '2_name'] ?? '')) !== '') $nm .= ' & ' . trim($in[$p . '2_name']);
        $ph = trim((string)($in[$p . '1_phone'] ?? '')) ?: (string)($L['phone'] ?? '');
        $em = trim((string)($in[$p . '1_email'] ?? '')) ?: (string)($L['email'] ?? '');
        $next = null; $c = dl_get($t['folder']); if ($c) foreach (dl_compute(is_array($c["data"]) ? $c["data"] : (json_decode((string)$c["data"], true) ?: [])) as $d) { if (!$d['done']) { $next = $d; break; } }
        $out[] = ['t' => $t, 'k' => $k, 'i' => array_search($k, $keys, true), 'nm' => $nm, 'ph' => $ph, 'em' => $em, 'src' => (string)($L['source'] ?? ''), 'next' => $next,
            'addr' => trim((string)($in['property'] ?? '')) ?: ($nm !== $t['name'] ? (string)$t['name'] : '')];
    }
    usort($out, function ($a, $b) { return [$a['i'], $a['nm']] <=> [$b['i'], $b['nm']]; });
    return $out;
}
function ab_row($r, $side) {
    $h = function ($x) { return htmlspecialchars((string)$x); };
    $url = ($side === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . urlencode($r['t']['folder']);
    $meta = [PS_STAGES[$side][$r['k']] ?? '']; if ($r['addr'] !== '') $meta[] = $r['addr']; if ($r['src'] !== '') $meta[] = $r['src']; if ($r['ph'] !== '') $meta[] = $r['ph'];
    if ($r['k'] === 'closed') $tag = '<span class="due" style="background:#e3f4e8;color:#2e7d32">Closed</span>';
    elseif ($r['next']) { $d = $r['next']; $cl = $d['left'] < 0 ? 'over' : ($d['left'] <= 3 ? 'today' : 'later'); $tag = '<span class="due ' . $cl . '">' . $h($d['label'] . ' · ' . dl_when($d['left'])) . '</span>'; }
    else $tag = '<span class="due later">No deadlines entered</span>';
    $dig = preg_replace('/[^0-9+]/', '', $r['ph']);
    $qa = '<span class="qa">' . ($dig !== '' ? '<a href="tel:' . $h($dig) . '" title="Call">&#9742;</a><a href="sms:' . $h($dig) . '" title="Text"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg></a>' : '')
        . ($r['em'] !== '' ? '<a href="mailto:' . $h($r['em']) . '" title="Email">&#9993;</a>' : '') . '</span>';
    return '<div class="lrow crow" onclick="if(!event.target.closest(\'.qa\'))location.href=\'' . $h($url) . '\'"><span class="dot" style="background:' . $h(ps_color($side, $r['k'])) . '"></span><div class="who"><a class="nm" href="' . $h($url) . '">' . $h($r['nm']) . '</a><div class="meta">' . $h(implode(' · ', $meta)) . '</div></div>' . $tag . $qa . '</div>';
}
// Stage key for a record (the pipeline stage saved on the site, else the Drive folder's stage).
function ab_pk($t) { return ps_get($t['folder'], $t['side'], (int)$t['stage']); }
// Closed records for one agent, both sides: ['buyer' => rows, 'seller' => rows], newest first by name order of stage.
function ab_closed($me) {
    $all = flr_tx_list($me); $out = [];
    foreach (['buyer', 'seller'] as $side) {
        $list = array_values(array_filter($all, function ($t) use ($side) { return $t['side'] === $side && ab_pk($t) === 'closed'; }));
        $out[$side] = ab_rows($me, $side, $list, 'ab_pk');
    }
    return $out;
}
