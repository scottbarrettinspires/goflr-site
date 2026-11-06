<?php
// Contract deadlines: the agent enters the Effective Date and each period from the executed contract; the server does the date math.
// Days are counted as calendar days after the Effective Date. A date that lands on a weekend or national legal holiday is flagged with the
// next business day; the agent confirms against the contract's own time provision (nothing here is legal advice).
require_once __DIR__ . '/flrdb.php';

// Each item: key => [label, kind]. kind 'days' = N days after the Effective Date; 'date' = a calendar date from the contract.
const DL_ITEMS = [
    'deposit1'  => ['Initial escrow deposit due', 'days'],
    'deposit2'  => ['Additional escrow deposit due', 'days'],
    'loanapp'   => ['Loan application due', 'days'],
    'inspect'   => ['Inspection period ends', 'days'],
    'appraisal' => ['Appraisal contingency ends', 'days'],
    'loan'      => ['Loan approval period ends', 'days'],
    'title'     => ['Title evidence due', 'days'],
    'hoa'       => ['HOA / condo documents review ends', 'days'],
    'closing'   => ['Closing date', 'date'],
];

function dl_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS contracts (folder TEXT PRIMARY KEY, akey TEXT NOT NULL, email TEXT, side TEXT, data TEXT NOT NULL, updated TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS contracts_akey ON contracts(akey)');
    return $pdo;
}
// US national legal holidays for a year: [Y-m-d => name], including the weekday a weekend holiday is observed on.
function dl_holidays($y) {
    static $c = []; if (isset($c[$y])) return $c[$y];
    $nth = function ($n, $dow, $m) use ($y) { $d = new DateTime("$y-$m-01"); $k = 0; while (true) { if ((int)$d->format('N') === $dow && ++$k === $n) return $d->format('Y-m-d'); $d->modify('+1 day'); } };
    $last = function ($dow, $m) use ($y) { $d = new DateTime("$y-$m-01"); $d->modify('last day of this month'); while ((int)$d->format('N') !== $dow) $d->modify('-1 day'); return $d->format('Y-m-d'); };
    $h = ["$y-01-01" => "New Year's Day", $nth(3, 1, 1) => 'Martin Luther King Jr. Day', $nth(3, 1, 2) => "Washington's Birthday", $last(1, 5) => 'Memorial Day',
        "$y-06-19" => 'Juneteenth', "$y-07-04" => 'Independence Day', $nth(1, 1, 9) => 'Labor Day', $nth(2, 1, 10) => 'Columbus Day',
        "$y-11-11" => 'Veterans Day', $nth(4, 4, 11) => 'Thanksgiving Day', "$y-12-25" => 'Christmas Day'];
    foreach ($h as $d => $n) { $w = (int)date('N', strtotime($d)); if ($w === 6) $h[date('Y-m-d', strtotime($d . ' -1 day'))] = $n . ' (observed)'; if ($w === 7) $h[date('Y-m-d', strtotime($d . ' +1 day'))] = $n . ' (observed)'; }
    return $c[$y] = $h;
}
// Why a date is not a business day ('' when it is one).
function dl_offday($d) {
    $w = (int)date('N', strtotime($d)); if ($w === 6) return 'Saturday'; if ($w === 7) return 'Sunday';
    $h = dl_holidays((int)substr($d, 0, 4)); return $h[$d] ?? '';
}
function dl_next_business($d) { $x = $d; for ($i = 0; $i < 10 && dl_offday($x) !== ''; $i++) $x = date('Y-m-d', strtotime($x . ' +1 day')); return $x; }
function dl_valid_date($d) { return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4)); }

// The computed list for one contract: [key, label, date, math, offday, nextbiz, done, left] sorted by date.
function dl_compute(array $c, $today = null) {
    $today = $today ?: (new DateTime('now', new DateTimeZone('America/New_York')))->format('Y-m-d');
    $eff = dl_valid_date($c['effective'] ?? '') ? $c['effective'] : ''; $out = [];
    foreach (DL_ITEMS as $k => $def) {
        $it = (array)($c['items'][$k] ?? []); $date = ''; $math = '';
        if ($def[1] === 'days') { $n = (string)($it['days'] ?? ''); if ($eff === '' || $n === '' || !ctype_digit($n)) continue; $date = date('Y-m-d', strtotime($eff . ' +' . (int)$n . ' days')); $math = 'Effective ' . date('M j, Y', strtotime($eff)) . ' + ' . (int)$n . ' days'; }
        else { $date = (string)($it['date'] ?? ''); if (!dl_valid_date($date)) continue; $math = 'Date in the contract'; }
        $out[] = dl_row($k, $def[0], $date, $math, !empty($it['done']), $today);
    }
    foreach ((array)($c['custom'] ?? []) as $i => $x) { if (!dl_valid_date($x['date'] ?? '') || trim((string)($x['label'] ?? '')) === '') continue; $out[] = dl_row('custom' . $i, (string)$x['label'], $x['date'], 'Date you entered', !empty($x['done']), $today); }
    usort($out, function ($a, $b) { return strcmp($a['date'], $b['date']); });
    return $out;
}
function dl_row($k, $label, $date, $math, $done, $today) {
    $off = dl_offday($date); $nb = $off !== '' ? dl_next_business($date) : '';
    $eff = $nb ?: $date; $left = (int)round((strtotime($eff . ' 12:00') - strtotime($today . ' 12:00')) / 86400);
    return ['key' => $k, 'label' => $label, 'date' => $date, 'math' => $math, 'offday' => $off, 'nextbiz' => $nb, 'done' => $done, 'left' => $left];
}
function dl_get($folder) { $q = dl_db()->prepare('SELECT * FROM contracts WHERE folder = ?'); $q->execute([$folder]); $r = $q->fetch(); if (!$r) return null; $r['data'] = json_decode($r['data'], true) ?: []; return $r; }
function dl_save($email, $folder, $side, array $data) {
    dl_db()->prepare('INSERT INTO contracts (folder, akey, email, side, data, updated) VALUES (?,?,?,?,?,?) ON CONFLICT(folder) DO UPDATE SET data=excluded.data, side=excluded.side, updated=excluded.updated')
        ->execute([$folder, flr_akey($email), strtolower($email), $side, json_encode($data), date('c')]);
    flr_audit(flr_akey($email), 'contract_dates', $folder, 'Contract dates saved');
}
// Upcoming (not done) deadlines for one agent, or for everyone when $email is '' (broker view). Each row gets folder, name, email.
function dl_upcoming($email, $days = 14, $includeOverdue = true) {
    $sql = 'SELECT c.folder, c.email, c.side, c.data, t.name, t.listed, t.stage_name FROM contracts c LEFT JOIN transactions t ON t.folder = c.folder' . ($email !== '' ? ' WHERE c.akey = ?' : '');
    $q = dl_db()->prepare($sql); $q->execute($email !== '' ? [flr_akey($email)] : []); $rows = [];
    foreach ($q->fetchAll() as $r) {
        if ($r['listed'] !== null && !(int)$r['listed']) continue; // record archived or gone
        foreach (dl_compute(json_decode($r['data'], true) ?: []) as $d) {
            if ($d['done'] || $d['left'] > $days || (!$includeOverdue && $d['left'] < 0)) continue;
            $rows[] = $d + ['folder' => $r['folder'], 'name' => (string)($r['name'] ?? ''), 'email' => $r['email'], 'side' => $r['side'], 'stage' => (string)($r['stage_name'] ?? '')];
        }
    }
    usort($rows, function ($a, $b) { return $a['left'] - $b['left']; });
    return $rows;
}
function dl_when($left) { return $left < 0 ? 'Overdue by ' . -$left . ' day' . ($left === -1 ? '' : 's') : ($left === 0 ? 'Due today' : ($left === 1 ? 'Due tomorrow' : 'In ' . $left . ' days')); }
// Who may see every agent's data: office staff, or a broker account listed here.
const FLR_BROKER_EMAILS = ['owner@myflroffice.com', 'broker@myflroffice.com', 'admin@myflroffice.com', 'operations@myflroffice.com'];
function flr_is_broker() { return !empty($_SESSION['staff']) || in_array(strtolower($_SESSION['email'] ?? ''), FLR_BROKER_EMAILS, true); }

// POST op=savedates on a buyer/seller record page: saves the effective date, each period's day-count or date, and done
// checkboxes. $only restricts the save to those item keys (used by a slim sub-page like contingencies.php) and leaves
// every other item, the effective date, and the custom dates exactly as they were — never a destructive full rebuild.
function dl_handle_save($me, $side, $tx, $self, $only = null) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['op'] ?? '') !== 'savedates') return;
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { header('Location: ' . $self); exit; }
    $old = dl_get($tx); $data = $old ? $old['data'] : ['effective' => '', 'items' => [], 'custom' => []];
    $data['items'] = (array)($data['items'] ?? []); $data['custom'] = (array)($data['custom'] ?? []);
    if ($only === null) { $eff = (string)($_POST['dl_effective'] ?? ''); $data['effective'] = dl_valid_date($eff) ? $eff : ''; }
    foreach (DL_ITEMS as $k => $def) {
        if ($only !== null && !in_array($k, $only, true)) continue; // not part of this form: leave untouched
        if (!isset($data['items'][$k])) $data['items'][$k] = [];
        if ($def[1] === 'days') { $n = trim((string)($_POST['dl_' . $k] ?? '')); if ($n !== '' && ctype_digit($n)) { $data['items'][$k]['days'] = $n; } else { unset($data['items'][$k]['days']); } }
        else { $d = (string)($_POST['dl_' . $k] ?? ''); if (dl_valid_date($d)) { $data['items'][$k]['date'] = $d; } else { unset($data['items'][$k]['date']); } }
        $data['items'][$k]['done'] = !empty($_POST['dlx_' . $k]);
    }
    if ($only === null) {
        $data['custom'] = [];
        for ($i = 0; $i < 6; $i++) {
            $lbl = trim((string)($_POST['dlc_label' . $i] ?? '')); $d = (string)($_POST['dlc_date' . $i] ?? '');
            if ($lbl === '' || !dl_valid_date($d)) continue;
            $data['custom'][] = ['label' => mb_substr($lbl, 0, 80), 'date' => $d, 'done' => !empty($_POST['dlcx' . $i])];
        }
    }
    dl_save($me, $tx, $side, $data);
    header('Location: ' . $self . (strpos($self, '?') === false ? '?' : '&') . 'datessaved=1'); exit;
}

// The "Contract Deadlines" panel for a buyer or seller record: editable Effective Date + periods, with the computed dates
// shown live. Pass $only (an array of DL_ITEMS keys) to render just those items with no Effective Date field and no
// custom-dates section — used by contingencies.php for a focused, merge-safe view of only the contingency deadlines.
function dl_box($me, $side, $tx, $self, $only = null, $title = null) {
    $h = function ($s) { return htmlspecialchars((string)$s); };
    $rec = dl_get($tx); $c = $rec ? $rec['data'] : ['effective' => '', 'items' => [], 'custom' => []];
    $rows = array_values(array_filter(dl_compute($c), function ($r) use ($only) { return $only === null || in_array($r['key'], $only, true); }));
    $title = $title ?? ($only === null ? 'Contract Deadlines' : 'Contingencies');
    $o = '<div class="panel sigbox" style="margin-bottom:14px">';
    $o .= '<h2 style="margin:0 0 6px;font-size:1.1rem">' . $h($title) . '</h2>';
    if (!empty($_GET['datessaved'])) $o .= '<p class="note">Saved.</p>';
    if ($only === null) $o .= '<p style="margin:0 0 10px;color:#4b5b5d;font-size:.92rem">Enter the Effective Date and the number of days for each period straight from the executed contract (or a fixed date for closing). The site does the math — check it against the contract\'s own time provision. This is not legal advice.</p>';
    if ($rows) {
        $o .= '<table style="width:100%;border-collapse:collapse;margin-bottom:12px;font-size:.92rem">';
        foreach ($rows as $r) {
            $color = $r['done'] ? '#6a777a' : ($r['left'] < 0 ? '#c0392b' : ($r['left'] <= 3 ? '#b7791f' : '#1b4b4d'));
            $flag = $r['offday'] !== '' ? ' <span style="color:#b7791f">(' . $h($r['offday']) . ' — next business day ' . date('M j', strtotime($r['nextbiz'])) . ')</span>' : '';
            $o .= '<tr' . ($r['done'] ? ' style="opacity:.55"' : '') . '><td style="padding:3px 8px 3px 0;white-space:nowrap">' . date('M j, Y', strtotime($r['date'])) . $flag . '</td><td style="padding:3px 8px">' . $h($r['label']) . '</td><td style="padding:3px 0;color:' . $color . ';font-weight:600;white-space:nowrap">' . $h(dl_when($r['left'])) . '</td></tr>';
        }
        $o .= '</table>';
    } elseif ($only !== null) { $o .= '<p class="note">No contingency periods entered yet — add them below.</p>'; }
    $o .= '<form method="post"><input type="hidden" name="csrf" value="' . $h($_SESSION['csrf']) . '"><input type="hidden" name="tx" value="' . $h($tx) . '"><input type="hidden" name="op" value="savedates">';
    if ($only === null) $o .= '<p><label>Effective Date<input type="date" name="dl_effective" value="' . $h($c['effective'] ?? '') . '"></label></p>';
    $o .= '<div class="wq">';
    foreach (DL_ITEMS as $k => $def) {
        if ($only !== null && !in_array($k, $only, true)) continue;
        $it = (array)($c['items'][$k] ?? []); $done = !empty($it['done']);
        if ($def[1] === 'days') $o .= '<label>' . $h($def[0]) . '<input type="number" min="0" max="365" name="dl_' . $k . '" value="' . $h($it['days'] ?? '') . '" placeholder="days" style="width:5em;display:inline-block;margin-right:6px"> days after Effective Date</label>';
        else $o .= '<label>' . $h($def[0]) . '<input type="date" name="dl_' . $k . '" value="' . $h($it['date'] ?? '') . '"></label>';
        $o .= '<label class="wr" style="margin-left:0 0 8px"><input type="checkbox" name="dlx_' . $k . '"' . ($done ? ' checked' : '') . '> Done / waived</label>';
    }
    $o .= '</div>';
    if ($only === null) {
        $custom = (array)($c['custom'] ?? []);
        $o .= '<p style="margin:10px 0 4px;font-weight:600">Other dates from this contract (optional)</p>';
        for ($i = 0; $i < 6; $i++) {
            $x = $custom[$i] ?? ['label' => '', 'date' => '', 'done' => false];
            if ($i > 0 && $x['label'] === '' && !isset($custom[$i])) { if ($i > count($custom)) break; }
            $o .= '<p style="margin:0 0 6px"><input type="text" name="dlc_label' . $i . '" value="' . $h($x['label']) . '" placeholder="e.g. Repair request due" style="width:46%;margin-right:6px"><input type="date" name="dlc_date' . $i . '" value="' . $h($x['date']) . '" style="margin-right:6px"><label class="wr"><input type="checkbox" name="dlcx' . $i . '"' . (!empty($x['done']) ? ' checked' : '') . '> Done</label></p>';
        }
    }
    $o .= '<p><button type="submit" class="btn">Save ' . ($only === null ? 'contract dates' : 'contingencies') . '</button></p></form></div>';
    return $o;
}

// Which DL_ITEMS keys count as contract contingencies (as opposed to deposits, loan app, or the closing date itself).
// Used only to decide when the ladder's "Contingencies Satisfied" label kicks in -- the Contingencies screen itself now
// shows and saves every deadline (see contingencies.php), not just these.
const DL_CONTINGENCY_KEYS = ['inspect', 'appraisal', 'loan', 'title', 'hoa'];
// True once every contingency the agent has actually entered a period for is marked Done/waived. False (and the default
// label "Contingencies") if none are configured yet, or any configured one is still open.
function dl_contingencies_satisfied($tx) {
    $rec = dl_get($tx); if (!$rec) return false;
    $items = (array)($rec['data']['items'] ?? []); $any = false;
    foreach (DL_CONTINGENCY_KEYS as $k) {
        $it = (array)($items[$k] ?? []);
        $configured = ($it['days'] ?? '') !== '' || ($it['date'] ?? '') !== '';
        if (!$configured) continue;
        $any = true;
        if (empty($it['done'])) return false;
    }
    return $any;
}

// Closing-day logistics, kept alongside the deadline data for the same transaction: a time (the date itself is the
// 'closing' DL_ITEMS date) and whether the file is clear to close. Not a legal record — just what Closing Prep tracks.
function dl_closing_meta($tx) {
    $rec = dl_get($tx); $m = (array)($rec['data']['closing_meta'] ?? []);
    return ['time' => (string)($m['time'] ?? ''), 'clear' => !empty($m['clear'])];
}
function dl_set_closing_meta($me, $side, $tx, $time, $clear) {
    $old = dl_get($tx); $data = $old ? $old['data'] : ['effective' => '', 'items' => [], 'custom' => []];
    $data['closing_meta'] = ['time' => mb_substr(trim((string)$time), 0, 20), 'clear' => (bool)$clear];
    dl_save($me, $tx, $side, $data);
}
