<?php
// Notes on an Active Business record, and completing a contract task with a note.
// A record linked to a lead keeps its notes in that lead's Notes (same list the lead record shows); a record with no lead
// keeps them here (kv recnotes_<folder>). Plain functions returning '' or a readable error so pages and assistants share them.
require_once __DIR__ . '/dlib.php';
require_once __DIR__ . '/leadlib.php';
require_once __DIR__ . '/flrdb.php';

// What the agent must record to complete each contract task (kept on the record as well as in the note). type: money|date|text|choice.
const RN_TASK_FIELDS = [
    'deposit1'  => [['amount', 'Amount deposited ($)', 'money', 1], ['on', 'Date deposited', 'date', 1], ['heldby', 'Held by (escrow / title company)', 'text', 0]],
    'deposit2'  => [['amount', 'Amount deposited ($)', 'money', 1], ['on', 'Date deposited', 'date', 1], ['heldby', 'Held by (escrow / title company)', 'text', 0]],
    'loanapp'   => [['lender', 'Lender', 'text', 1], ['on', 'Date application submitted', 'date', 1]],
    'inspect'   => [['result', 'Inspection result', 'choice:Accepted as is|Repair request sent|Contract terminated', 1], ['inspector', 'Inspector', 'text', 0]],
    'appraisal' => [['value', 'Appraised value ($)', 'money', 1], ['result', 'Result', 'choice:At or above the contract price|Below the contract price', 1]],
    'loan'      => [['status', 'Loan approval status', 'choice:Clear to close|Approved with conditions|Denied', 1], ['lender', 'Lender', 'text', 0]],
    'title'     => [['company', 'Title company', 'text', 1], ['on', 'Date received', 'date', 1]],
    'hoa'       => [['result', 'HOA / condo documents', 'choice:Accepted|Objection sent|Contract terminated', 1]],
    'closing'   => [['on', 'Date closed', 'date', 1]],
];
function rn_own($me, $tx) { foreach (flr_tx_list($me, false) as $t) { if ($t['folder'] === $tx && in_array($t['side'], ['buyer', 'seller'], true)) return $t; } return null; }
function rn_kv_get($k) { $q = flr_db()->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute([$k]); $v = $q->fetchColumn(); return $v === false ? null : (string)$v; }
function rn_list($me, $tx) { $j = json_decode((string)rn_kv_get('recnotes_' . $tx), true); return is_array($j) ? $j : []; }
// Add a dated note to the record. '' or an error message.
function rn_add($me, $tx, $text) {
    if (!rn_own($me, $tx)) return 'That record was not found in your business.';
    $text = trim(preg_replace('/\s+/', ' ', (string)$text)); if ($text === '') return 'Write the note first.';
    if (mb_strlen($text) > 600) return 'That note is too long (600 characters). Please shorten it.';
    $line = date('M j, Y') . ': ' . $text; $lead = null; try { $lead = ld_find_by_folder($me, $tx); } catch (Throwable $e) {}
    if ($lead) {
        $lid = (string)$lead['id'];
        ld_update($me, function (&$db) use ($lid, $line, $text) { foreach ($db['leads'] as &$L) { if (($L['id'] ?? '') !== $lid) continue;
            $L['notes'] = trim((string)($L['notes'] ?? '')) === '' ? $line : rtrim((string)$L['notes']) . "\n" . $line;
            $L['log'][] = ['d' => ld_today(), 'role' => 'client', 'mid' => '', 'note' => 'Note added from the record: ' . mb_substr($text, 0, 80)]; } });
    } else {
        $pdo = flr_db(); $pdo->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT)');
        $n = rn_list($me, $tx); $n[] = ['d' => date('Y-m-d'), 'text' => $text];
        $pdo->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)')->execute(['recnotes_' . $tx, json_encode($n)]);
    }
    flr_audit(flr_akey($me), 'record_note', $tx, mb_substr($text, 0, 120));
    return '';
}
// Mark one contract task done, keep what the task requires on the record, and record the note. $fields = [name => value] from
// RN_TASK_FIELDS. '' or an error message. Nothing is saved unless everything required is there.
function rn_complete_task($me, $tx, $key, $note, array $fields = []) {
    $own = rn_own($me, $tx); if (!$own) return 'That record was not found in your business.';
    $c = dl_get($tx); if (!$c) return 'No contract dates are entered on that record.';
    $data = (array)$c['data']; $label = '';
    foreach (dl_compute($data) as $r) { if ($r['key'] === $key) $label = $r['label']; }
    if ($label === '') return 'That task was not found on the record.';
    $kept = []; $parts = [];
    foreach (RN_TASK_FIELDS[$key] ?? [] as $f) {
        list($n, $lb, $ty, $req) = $f; $v = trim((string)($fields[$n] ?? ''));
        if ($v === '') { if ($req) return 'Please enter: ' . preg_replace('/ \(.*\)$/', '', $lb) . '.'; continue; }
        if ($ty === 'money') { $v = str_replace(['$', ','], '', $v); if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $v)) return 'Enter ' . preg_replace('/ \(.*\)$/', '', $lb) . ' as a dollar amount, for example 5000.'; }
        elseif ($ty === 'date') { if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) || !strtotime($v)) return 'Enter ' . $lb . ' as a real date.'; }
        elseif (strpos($ty, 'choice:') === 0) { if (!in_array($v, explode('|', substr($ty, 7)), true)) return 'Choose one of the listed answers for ' . $lb . '.'; }
        else $v = mb_substr($v, 0, 120);
        $kept[$n] = $v; $parts[] = preg_replace('/ \(.*\)$/', '', $lb) . ': ' . ($ty === 'money' ? '$' . number_format((float)$v, strpos($v, '.') !== false ? 2 : 0) : ($ty === 'date' ? date('M j, Y', strtotime($v)) : $v));
    }
    $note = trim((string)$note); $text = 'Completed: ' . $label . ($parts ? '. ' . implode('; ', $parts) . '.' : '.') . ($note !== '' ? ' ' . $note : '');
    $e = rn_add($me, $tx, $text); if ($e !== '') return $e;
    if (strpos($key, 'custom') === 0) { $i = (int)substr($key, 6); if (!isset($data['custom'][$i])) return 'That task was not found on the record.'; $data['custom'][$i]['done'] = true; }
    else { $data['items'][$key]['done'] = true; }
    dl_save($me, $tx, (string)$c['side'], $data);
    if ($kept) { $pdo = flr_db(); $ti = json_decode((string)rn_kv_get('taskinfo_' . $tx), true); $ti = is_array($ti) ? $ti : []; $ti[$key] = $kept + ['completed' => date('Y-m-d')];
        $pdo->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)')->execute(['taskinfo_' . $tx, json_encode($ti)]); }
    flr_audit(flr_akey($me), 'task_done', $tx, $label);
    return '';
}
