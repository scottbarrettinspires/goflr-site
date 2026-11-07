<?php
// Notifications (bell on Home): what needs attention now, in sections. More sections can be added later -- each is
// a list of items {title, sub, url, level: 'red'|'amber'}.
//   Agents: new leads, hot leads due/overdue (incl. not reached yet), contract deadlines within 3 days (not marked done, overdue included), and
//           transaction records missing key information.
//   Staff:  recruiting touches due, and contract deadlines within 3 days across every agent.
// Transaction-record details (client name/phone/email etc.) live in Drive behind the office script, so those are
// fetched in parallel and cached for 10 minutes; saving a record clears its cache (see sigtools.php).
require_once __DIR__ . '/flrdb.php';
require_once __DIR__ . '/dlib.php';
date_default_timezone_set('America/New_York');

const NT_DEADLINE_DAYS = 3;
const NT_INFO_TTL = 600;
const NT_UNDER_CONTRACT = ['contract', 'contingencies', 'closingready'];

// ---- Transaction record details from Drive, cached ----
function nt_info_cached($folder) {
    $q = flr_db()->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute(['nt_info_' . $folder]); $j = json_decode((string)$q->fetchColumn(), true);
    return (is_array($j) && time() - (int)($j['t'] ?? 0) < NT_INFO_TTL) ? (array)($j['info'] ?? []) : null;
}
// [folder => info array] for buyer/seller records, fetching any not cached (in parallel). A record whose details
// can't be read right now is left out, so it's never flagged by mistake.
function nt_infos($email, array $txs, $remote = true) {
    $out = []; $need = [];
    foreach ($txs as $t) { $c = nt_info_cached($t['folder']); if ($c !== null) $out[$t['folder']] = $c; elseif ($remote) $need[] = $t; }
    if (!$need || !function_exists('curl_multi_init')) return $out;
    $mh = curl_multi_init(); $hs = [];
    foreach (array_slice($need, 0, 25) as $t) {
        $op = $t['side'] === 'seller' ? 'sellerinfo' : 'buyerinfo';
        $ch = curl_init(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=' . $op . '&email=' . urlencode(strtolower($email)) . '&folderId=' . urlencode($t['folder']));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8]);
        curl_multi_add_handle($mh, $ch); $hs[$t['folder']] = $ch;
    }
    do { $st = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1.0); } while ($run && $st === CURLM_OK);
    $put = flr_db()->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)');
    foreach ($hs as $folder => $ch) {
        $j = json_decode((string)curl_multi_getcontent($ch), true); curl_multi_remove_handle($mh, $ch); curl_close($ch);
        if (!is_array($j) || empty($j['ok'])) continue;
        $info = is_array($j['info'] ?? null) ? $j['info'] : [];
        $put->execute(['nt_info_' . $folder, json_encode(['t' => time(), 'info' => $info])]); $out[$folder] = $info;
    }
    curl_multi_close($mh);
    return $out;
}
function nt_info_forget($folder) { try { flr_db()->prepare('DELETE FROM kv WHERE k = ?')->execute(['nt_info_' . $folder]); } catch (Throwable $e) {} }

// What's missing on one record (labels), given its saved details.
function nt_missing($t, array $info, $underContract) {
    $m = []; $blank = function ($k) use ($info) { return trim((string)($info[$k] ?? '')) === ''; };
    if ($t['side'] === 'buyer') {
        if ($blank('b1_name')) $m[] = 'buyer name'; if ($blank('b1_phone')) $m[] = 'buyer phone'; if ($blank('b1_email')) $m[] = 'buyer email';
        if ($blank('mailing')) $m[] = 'mailing address'; if ($blank('payment')) $m[] = 'how they\'re paying';
        if (in_array($info['payment'] ?? '', ['conventional', 'fhava'], true)) { if ($blank('lender')) $m[] = 'lender'; if ($blank('preapproval')) $m[] = 'pre-approval'; }
    } else {
        if ($blank('s1_name')) $m[] = 'seller name'; if ($blank('s1_phone')) $m[] = 'seller phone'; if ($blank('s1_email')) $m[] = 'seller email';
        if ($blank('property')) $m[] = 'property address';
    }
    return $m;
}

// ---- Sections ----
// $remote false = only what's quick and local (plus cached record details): used for the Home badge's first draw.
function nt_sections($email, $isStaff, $remote = true) {
    $email = strtolower(trim((string)$email)); $S = [];
    if ($isStaff) {
        try {
            require_once __DIR__ . '/rcrlib.php'; list($ra, $rp) = rcr_me(); $items = [];
            foreach (rcr_view($ra, $rp) as $R) { if (!isset($R['over']) || $R['over'] < 0) continue;
                $items[] = ['title' => trim(($R['first'] ?? '') . ' ' . ($R['last'] ?? '')) ?: (($R['phone'] ?? '') ?: (($R['email'] ?? '') ?: 'Recruit')), 'sub' => $R['over'] > 0 ? $R['over'] . ' day' . ($R['over'] === 1 ? '' : 's') . ' overdue' : 'Due today', 'url' => 'recruiting.php?id=' . urlencode($R['id']), 'level' => $R['over'] > 0 ? 'red' : 'amber', 'sort' => -$R['over']]; }
            usort($items, function ($a, $b) { return $a['sort'] <=> $b['sort']; });
            $S[] = ['key' => 'recruit', 'title' => 'Recruiting touches due', 'empty' => 'No recruiting follow-ups due.', 'items' => $items];
        } catch (Throwable $e) {}
        $items = [];
        foreach (dl_upcoming('', NT_DEADLINE_DAYS, true) as $d) {
            $items[] = ['title' => $d['label'] . ' — ' . ($d['name'] ?: 'untitled record'), 'sub' => nt_due($d) . ' · ' . nt_agent_name($d['email']), 'url' => '', 'level' => $d['left'] < 0 ? 'red' : 'amber'];
        }
        $S[] = ['key' => 'deadlines', 'title' => 'Contract deadlines (all agents, next ' . NT_DEADLINE_DAYS . ' days)', 'empty' => 'No contract deadlines due in the next ' . NT_DEADLINE_DAYS . ' days.', 'items' => $items];
        return $S;
    }
    // New leads (not yet sorted into Buyers / Sellers / Sphere) -- call within 5 minutes; and hot leads: any other lead
    // with a follow-up due today or overdue (not do-not-contact), which includes leads not reached yet.
    $newItems = []; $items = [];
    try {
        require_once __DIR__ . '/leadlib.php';
        $today = ld_today();
        $acts = function ($L) { $tel = preg_replace('/[^0-9+]/', '', (string)($L['phone'] ?? '')); return ['tel' => $tel, 'email' => trim((string)($L['email'] ?? ''))]; };
        foreach (ld_view($email, '', '') as $L) {
            $nm = trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? '')) ?: (($L['phone'] ?? '') ?: ($L['email'] ?? 'Lead'));
            $isNew = true; foreach ($L['view'] as $v) { if ($v['role'] !== 'new') $isNew = false; }
            if ($isNew) {
                $d = $L['added'] ?? $today; $when = $d === $today ? 'Today' : ($d === ld_add($today, -1) ? 'Yesterday' : date('M j', strtotime($d)));
                $newItems[] = ['title' => $nm, 'sub' => $when . (!empty($L['source']) ? ' · ' . $L['source'] : ''), 'url' => 'leads.php?open=' . urlencode($L['id']), 'level' => 'red', 'acts' => $acts($L), 'sort' => (string)($L['created'] ?? $L['added'] ?? '')];
                continue;
            }
            foreach ($L['view'] as $v) {
                if (!isset($v['over']) || $v['over'] < 0 || $v['sub'] === 'dnc') continue;
                $items[] = ['title' => $nm, 'sub' => (['seller' => 'Seller', 'buyer' => 'Buyer', 'sphere' => 'Sphere'][$v['role']] ?? 'Lead') . ($v['sub'] === 'unid' ? ' · not reached yet' : '') . ' · attempt ' . $v['step'] . ' · ' . $v['ch'] . ' · ' . ($v['over'] > 0 ? $v['over'] . ' day' . ($v['over'] === 1 ? '' : 's') . ' overdue' : 'due today'),
                    'url' => 'leads.php?open=' . urlencode($L['id']), 'level' => $v['over'] > 0 ? 'red' : 'amber', 'acts' => $acts($L), 'sort' => -$v['over']];
                break;
            }
        }
    } catch (Throwable $e) {}
    usort($newItems, function ($a, $b) { return strcmp($b['sort'], $a['sort']); });
    usort($items, function ($a, $b) { return $a['sort'] <=> $b['sort']; });
    $S[] = ['key' => 'new', 'title' => 'New leads', 'intro' => $newItems ? 'Call within 5 minutes when you can, then sort each one into Buyers, Sellers or Sphere.' : '', 'empty' => 'No new leads waiting.', 'items' => $newItems];
    $S[] = ['key' => 'hot', 'title' => 'Hot leads that need attention', 'intro' => $items ? 'Follow-ups due today or overdue, including leads you haven\'t reached yet. Once you reach someone, move them to Ready, Soon or Later.' : '', 'empty' => 'No lead follow-ups due right now.', 'items' => $items];
    // Contract deadlines within 3 days (or overdue), not marked done.
    $items = [];
    foreach (dl_upcoming($email, NT_DEADLINE_DAYS, true) as $d) {
        $items[] = ['title' => $d['label'] . ' — ' . ($d['name'] ?: 'untitled record'), 'sub' => nt_due($d),
            'url' => ($d['side'] === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . urlencode($d['folder']), 'level' => $d['left'] < 0 ? 'red' : 'amber'];
    }
    $S[] = ['key' => 'deadlines', 'title' => 'Contract deadlines in the next ' . NT_DEADLINE_DAYS . ' days', 'empty' => 'Nothing due in the next ' . NT_DEADLINE_DAYS . ' days.', 'items' => $items, 'more' => ['deadlines.php', 'See all upcoming deadlines']];
    // Newly signed documents: fully signed requests the agent hasn't looked at yet. Opening the record clears them;
    // ones not filed in any record yet stay until they are (they link to signed.php to choose the record).
    $items = [];
    try {
        require_once __DIR__ . '/signed_panel.php'; require_once __DIR__ . '/pstage.php';
        $txAll = flr_tx_list($email);
        foreach (sg_for_agent($email) as $r) {
            if (empty($r['completed']) || !empty($r['seen'])) continue;
            $rec = sg_record_for($r, $txAll);
            $ct = strtotime($r['completed_at'] ?? '');
            $what = $r['offer_address'] ?? '' ?: preg_replace('/^[^-]*-\s*/', '', (string)($r['form'] ?? 'Document'));
            if ($rec) { $items[] = ['title' => 'Signed: ' . $what, 'sub' => ($ct ? date('M j', $ct) . ' · ' : '') . 'In ' . $rec['name'] . ' › Signed Documents', 'url' => $rec['url'], 'level' => 'amber']; }
            elseif (empty($r['filed']) && !sg_can_file($r)) { $items[] = ['title' => 'Signed: ' . $what, 'sub' => 'Fully signed — choose which record to file it in', 'url' => 'signed.php?assign=' . urlencode($r['id']), 'level' => 'red']; }
            else { $items[] = ['title' => 'Signed: ' . $what, 'sub' => ($ct ? date('M j', $ct) . ' · ' : '') . (!empty($r['filed']) ? 'Filed in ' . $r['filed']['folder'] : 'Being filed in your records'), 'url' => 'signed.php?seen=' . urlencode($r['id']), 'level' => 'amber']; }
        }
    } catch (Throwable $e) {}
    $S[] = ['key' => 'signed', 'title' => 'Newly signed documents', 'empty' => 'No newly signed documents.', 'items' => $items];
    // Transaction records missing information.
    $items = [];
    try {
        require_once __DIR__ . '/pstage.php';
        $txs = array_values(array_filter(flr_tx_list($email), function ($t) { return in_array($t['side'], ['buyer', 'seller'], true); }));
        $active = [];
        foreach ($txs as $t) { $t['pkey'] = ps_get($t['folder'], $t['side'], (int)$t['stage']); if ($t['pkey'] !== 'closed') $active[] = $t; }
        $infos = nt_infos($email, $active, $remote);
        foreach ($active as $t) {
            $under = in_array($t['pkey'], NT_UNDER_CONTRACT, true);
            $miss = isset($infos[$t['folder']]) ? nt_missing($t, $infos[$t['folder']], $under) : [];
            if ($under) { $c = dl_get($t['folder']); if (!$c || trim((string)($c['data']['effective'] ?? '')) === '') $miss[] = 'contract dates'; }
            if (!$miss) continue;
            $items[] = ['title' => ($t['name'] ?: 'Untitled record') . ' (' . $t['side'] . ')', 'sub' => 'Missing: ' . implode(', ', $miss),
                'url' => ($t['side'] === 'seller' ? 'seller.php' : 'buyer.php') . '?tx=' . urlencode($t['folder']), 'level' => in_array('contract dates', $miss, true) ? 'red' : 'amber'];
        }
    } catch (Throwable $e) {}
    $S[] = ['key' => 'missing', 'title' => 'Transaction records missing information', 'empty' => 'Every active record has its key details.', 'items' => $items];
    return $S;
}
// "Due tomorrow · Sat, Nov 14 (Saturday — next business day Mon, Nov 16)": same wording as the deadlines panel.
function nt_due($d) {
    return dl_when($d['left']) . ' · ' . date('D, M j', strtotime($d['date'])) . ($d['offday'] !== '' ? ' (' . $d['offday'] . ' — next business day ' . date('D, M j', strtotime($d['nextbiz'])) . ')' : '');
}
function nt_agent_name($email) {
    static $map = null; if ($map === null) { $map = []; try { require_once __DIR__ . '/arlib.php'; foreach (ar_all() as $r) $map[$r['email']] = $r['preferred_name'] ?: $r['full_name']; } catch (Throwable $e) {} }
    return $map[strtolower((string)$email)] ?? (string)$email;
}
function nt_count(array $sections) { $n = 0; foreach ($sections as $s) $n += count($s['items']); return $n; }
