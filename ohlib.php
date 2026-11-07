<?php
// Upcoming Open Houses: agents post their open houses (picked from their own listings or entered by hand) and every
// agent sees them on Home. One photo each (resized and stored like Collaboration photos, served by collab_img.php to
// signed-in users only). Authors edit/remove their own; staff can remove any. Past open houses drop off by themselves.
require_once __DIR__ . '/collablib.php'; // co_save_image / co_img_dir, om_person (headshots), co_me()
date_default_timezone_set('America/New_York');

function oh_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS oh_events (id INTEGER PRIMARY KEY AUTOINCREMENT, agent_email TEXT, agent_key TEXT, agent_name TEXT, tx_folder TEXT,
        address TEXT, mls TEXT, beds TEXT, baths TEXT, garage TEXT, price INTEGER, date TEXT, start TEXT, end TEXT, photo TEXT, deleted INTEGER DEFAULT 0, created TEXT, updated TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS oh_events_date ON oh_events(deleted, date)');
    return $pdo;
}
// Upcoming (today's stay until they end), soonest first.
function oh_upcoming($limit = 0) {
    $rows = oh_db()->prepare('SELECT * FROM oh_events WHERE deleted = 0 AND date >= ? ORDER BY date, start, id'); $rows->execute([date('Y-m-d')]);
    $now = time(); $out = [];
    foreach ($rows->fetchAll() as $r) { $end = strtotime($r['date'] . ' ' . ($r['end'] ?: '23:59')); if ($end !== false && $end < $now) continue; $out[] = $r; if ($limit && count($out) >= $limit) break; }
    return $out;
}
function oh_get($id) { $q = oh_db()->prepare('SELECT * FROM oh_events WHERE id = ? AND deleted = 0'); $q->execute([(int)$id]); return $q->fetch() ?: null; }

// The signed-in agent's active listings (seller records not yet closed): [folder => address-ish name].
function oh_my_listings($email) {
    $out = [];
    try {
        require_once __DIR__ . '/pstage.php';
        $sellers = array_values(array_filter(flr_tx_list($email), function ($t) { return $t['side'] === 'seller'; }));
        $open = []; foreach ($sellers as $t) { if (ps_get($t['folder'], 'seller', (int)$t['stage']) !== 'closed') $open[] = $t; }
        $infos = [];
        if ($open) { require_once __DIR__ . '/notiflib.php'; $infos = nt_infos($email, $open, true); } // saved property address, when there is one
        foreach ($open as $t) { $a = trim((string)($infos[$t['folder']]['property'] ?? '')); $out[$t['folder']] = $a !== '' ? $a : (string)$t['name']; }
    } catch (Throwable $e) {}
    return $out;
}

// Validate a posted form into a clean row, or return an error string.
function oh_clean(array $in) {
    $t = function ($k, $max = 120) use ($in) { return trim(mb_substr((string)($in[$k] ?? ''), 0, $max)); };
    $r = ['tx_folder' => $t('tx_folder', 100), 'address' => $t('address', 160), 'mls' => strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $t('mls', 20))),
          'beds' => $t('beds', 6), 'baths' => $t('baths', 6), 'garage' => $t('garage', 20), 'date' => $t('date', 10), 'start' => $t('start', 5), 'end' => $t('end', 5)];
    $price = preg_replace('/[^0-9]/', '', $t('price', 20)); $r['price'] = $price === '' ? null : (int)$price;
    if ($r['address'] === '') return 'Enter the property address (or choose one of your listings).';
    $d = DateTime::createFromFormat('Y-m-d', $r['date']);
    if (!$d || $d->format('Y-m-d') !== $r['date']) return 'Choose the open house date.';
    if ($r['date'] < date('Y-m-d')) return 'That date has already passed.';
    if ($r['date'] > date('Y-m-d', strtotime('+120 days'))) return 'Open houses can be posted up to 4 months ahead.';
    foreach (['start', 'end'] as $k) if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $r[$k])) return 'Enter the start and end times.';
    if ($r['end'] <= $r['start']) return 'The end time needs to be after the start time.';
    foreach (['beds', 'baths'] as $k) if ($r[$k] !== '' && !preg_match('/^\d{1,2}(\.\d)?$/', $r[$k])) return 'Beds and baths should be numbers (baths can be like 2.5).';
    return $r;
}
function oh_save($id, array $r, $photo) {
    $me = co_me(); $pdo = oh_db(); $now = date('c');
    if ($id) {
        $old = oh_get($id); if (!$old || $old['agent_email'] !== $me['email']) return 'You can only edit your own open houses.';
        if ($photo !== '' && $old['photo'] !== '') @unlink(co_img_dir() . '/' . basename($old['photo']));
        $pdo->prepare('UPDATE oh_events SET tx_folder=?, address=?, mls=?, beds=?, baths=?, garage=?, price=?, date=?, start=?, end=?, photo=?, updated=? WHERE id=?')
            ->execute([$r['tx_folder'], $r['address'], $r['mls'], $r['beds'], $r['baths'], $r['garage'], $r['price'], $r['date'], $r['start'], $r['end'], $photo !== '' ? $photo : $old['photo'], $now, (int)$id]);
        return (int)$id;
    }
    $pdo->prepare('INSERT INTO oh_events (agent_email, agent_key, agent_name, tx_folder, address, mls, beds, baths, garage, price, date, start, end, photo, created, updated) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$me['email'], $me['key'], $me['name'], $r['tx_folder'], $r['address'], $r['mls'], $r['beds'], $r['baths'], $r['garage'], $r['price'], $r['date'], $r['start'], $r['end'], $photo, $now, $now]);
    return (int)$pdo->lastInsertId();
}
function oh_delete($id) {
    $me = co_me(); $o = oh_get($id); if (!$o || ($o['agent_email'] !== $me['email'] && !$me['staff'])) return 'Not allowed.';
    oh_db()->prepare('UPDATE oh_events SET deleted = 1, updated = ? WHERE id = ?')->execute([date('c'), (int)$id]);
    if ($o['photo'] !== '') @unlink(co_img_dir() . '/' . basename($o['photo']));
    if ($o['agent_email'] !== $me['email']) flr_audit('', 'openhouse_remove', (string)$id, 'Removed by staff: ' . $me['email']);
    return '';
}

// ---- Display ----
function oh_time($hm) { return $hm === '' ? '' : date('g:i A', strtotime('2000-01-01 ' . $hm)); }
function oh_when($o) {
    $d = strtotime($o['date']); $today = date('Y-m-d');
    $day = $o['date'] === $today ? 'Today' : ($o['date'] === date('Y-m-d', strtotime('+1 day')) ? 'Tomorrow' : date('D, M j', $d));
    return $day . ' · ' . str_replace(':00 ', ' ', oh_time($o['start'])) . '–' . str_replace(':00 ', ' ', oh_time($o['end']));
}
function oh_facts($o) {
    $f = [];
    if ($o['beds'] !== '') $f[] = $o['beds'] . ' bd'; if ($o['baths'] !== '') $f[] = $o['baths'] . ' ba'; if ($o['garage'] !== '') $f[] = (ctype_digit($o['garage']) ? $o['garage'] . '-car garage' : $o['garage']);
    return implode(' · ', $f);
}
// One open house as a card tile (Home card and the full list page).
function oh_tile($o, $manage = false) {
    $au = om_person($o['agent_key'], $o['agent_name']); $id = (int)$o['id'];
    $img = $o['photo'] !== '' ? '<img src="collab_img.php?f=' . rawurlencode($o['photo']) . '" alt="Photo of ' . htmlspecialchars($o['address']) . '" loading="lazy">' : '<span class="ohnoimg" aria-hidden="true">🏡</span>';
    $h = '<div class="ohtile" id="oh' . $id . '"><a class="ohph" href="collab_img.php?f=' . rawurlencode($o['photo']) . '"' . ($o['photo'] === '' ? ' onclick="return false" style="cursor:default"' : ' target="_blank" rel="noopener"') . '>' . $img . '</a><div class="ohtx">'
       . '<div class="ohwhen">📅 ' . htmlspecialchars(oh_when($o)) . '</div>'
       . ($o['price'] !== null && $o['price'] !== '' ? '<div class="ohprice">$' . number_format((int)$o['price']) . '</div>' : '')
       . '<div class="ohaddr">' . htmlspecialchars($o['address']) . '</div>'
       . (oh_facts($o) !== '' ? '<div class="ohfacts">' . htmlspecialchars(oh_facts($o)) . '</div>' : '')
       . ($o['mls'] !== '' ? '<div class="ohmls">MLS# ' . htmlspecialchars($o['mls']) . '</div>' : '')
       . '<div class="ohagent"><span class="ohav"><span>' . htmlspecialchars($au['ini']) . '</span>' . ($au['photo'] ? '<img src="' . htmlspecialchars($au['photo']) . '" alt="" loading="lazy" onerror="this.remove()">' : '') . '</span>' . htmlspecialchars($o['agent_name']) . '</div>';
    if ($manage) {
        $me = co_me(); $mine = $o['agent_email'] === $me['email'];
        if ($mine || $me['staff']) {
            $h .= '<div class="ohman">' . ($mine ? '<a href="openhouses.php?edit=' . $id . '">Edit</a> · ' : '')
                . '<form method="post" action="openhouses.php" style="display:inline" onsubmit="return confirm(\'Remove this open house?\')"><input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf'] ?? '') . '"><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="' . $id . '"><button type="submit">' . ($mine ? 'Remove' : 'Remove (staff)') . '</button></form></div>';
        }
    }
    return $h . '</div></div>';
}
function oh_css() {
    return '<style>
.ohgrid{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:600px){.ohgrid{grid-template-columns:1fr}}
.ohtile{display:flex;gap:12px;border:1px solid var(--line);border-radius:12px;padding:10px;background:var(--white);min-width:0}
.ohph{flex:0 0 112px;height:96px;border-radius:9px;overflow:hidden;background:#eef4f4;display:flex;align-items:center;justify-content:center;text-decoration:none}
.ohph img{width:100%;height:100%;object-fit:cover;display:block}.ohnoimg{font-size:2.6rem}
.ohtx{min-width:0;flex:1;line-height:1.35;font-size:.9rem}
.ohwhen{font-weight:700;color:#036f72;font-size:.85rem}.ohprice{font-weight:800;font-size:1.05rem;color:#1f2a2c}
.ohaddr{font-weight:600;overflow-wrap:anywhere}.ohfacts,.ohmls{color:#5a6a6c;font-size:.84rem}
.ohagent{display:flex;align-items:center;gap:6px;margin-top:4px;font-size:.82rem;color:#455355}
.ohav{position:relative;width:22px;height:22px;border-radius:50%;overflow:hidden;background:var(--teal-soft);color:var(--teal);font:700 .55rem/22px system-ui,sans-serif;text-align:center;flex:0 0 auto}
.ohav img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.ohman{margin-top:4px;font-size:.82rem}.ohman a{color:var(--teal)}.ohman button{background:none;border:0;padding:0;font:inherit;color:#c0392b;text-decoration:underline;cursor:pointer}
</style>';
}
