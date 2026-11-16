<?php
// Buyer Consultation, done from inside the lead record (no page change). Returns the buyer's Active Business record
// (transaction folder id), creating it from the lead the first time -- the same record buyer.php?new=1&auto=1 makes.
// Data in, data out: cn_start($email, $leadId) => ['ok'=>true,'tx'=>id,'created'=>bool] or ['ok'=>false,'msg'=>readable error].
require_once __DIR__ . '/leadlib.php';
require_once __DIR__ . '/sigtools.php';
require_once __DIR__ . '/flrdb.php';

function cn_missing($n) {
    if (($n['b1_name'] ?? '') === '') return 'add the buyer\'s name';
    if (($n['b1_phone'] ?? '') === '') return 'add Buyer 1\'s phone number';
    if (($n['b1_email'] ?? '') === '') return 'add Buyer 1\'s email address';
    if (($n['b2_name'] ?? '') !== '' && ($n['b2_email'] ?? '') === '') return 'add the second buyer\'s email address';
    return '';
}
function cn_start($me, $leadId) {
    $me = strtolower(trim((string)$me));
    if (!preg_match('/^[a-z0-9]{2,20}$/', (string)$leadId)) return ['ok' => false, 'msg' => 'That lead could not be found.'];
    $all = ld_load($me)['leads']; $lead = null;
    foreach ($all as $x) { if (($x['id'] ?? '') === $leadId) { $lead = $x; break; } }
    if (!$lead) return ['ok' => false, 'msg' => 'That lead could not be found.'];
    $have = (string)($lead['roles']['buyer']['folder'] ?? '');
    if (preg_match('/^[A-Za-z0-9_-]{10,80}$/', $have)) return ['ok' => true, 'tx' => $have, 'created' => false];
    $cd = (array)($lead['client'] ?? []); $w = (array)($lead['wants'] ?? []);
    $info = ['b1_name' => trim(($lead['first'] ?? '') . ' ' . ($lead['last'] ?? '')), 'b1_phone' => (string)($lead['phone'] ?? ''), 'b1_email' => (string)($lead['email'] ?? '')];
    $fin = !empty($cd['fin']) ? (array)$cd['fin'] : (array)($w['fin'] ?? []);
    $lend = trim((string)($cd['lender'] ?? '')) !== '' ? $cd['lender'] : ($w['lender'] ?? '');
    $pre = trim((string)($cd['preamt'] ?? '')) !== '' ? $cd['preamt'] : ($w['preamt'] ?? '');
    if (!empty($lend)) $info['lender'] = (string)$lend;
    if (!empty($pre)) $info['preapproval'] = (string)$pre;
    if (!empty($cd['mailing'])) $info['mailing'] = (string)$cd['mailing'];
    if (!empty($cd['co_name'])) { $info['b2_name'] = (string)$cd['co_name']; $info['b2_phone'] = (string)($cd['co_phone'] ?? ''); $info['b2_email'] = (string)($cd['co_email'] ?? ''); }
    $pm = ['Cash' => 'cash', 'Conventional' => 'conventional', 'FHA' => 'fhava', 'VA' => 'fhava', 'FHA/VA' => 'fhava'];
    if (!empty($fin[0]) && isset($pm[$fin[0]])) $info['payment'] = $pm[$fin[0]];
    $miss = cn_missing($info);
    if ($miss !== '') return ['ok' => false, 'msg' => 'Before the agreement can be prepared, ' . $miss . ' (Client Details tab).'];
    if (strpos((string)$leadId, 'smp') === 0) { // a sample lead: make a local SAMPLE- record, never a real Drive folder
        $ak = flr_akey($me); $pdo = flr_db(); $now = date('c');
        $label = preg_replace('/\s+/', ' ', trim($info['b1_name'] . (($info['b2_name'] ?? '') !== '' ? ' & ' . $info['b2_name'] : '')));
        $fid = 'SAMPLE-C' . substr(sha1($leadId . microtime()), 0, 8);
        require_once __DIR__ . '/pstage.php';
        if (function_exists('ps_ensure_column')) ps_ensure_column();
        $pdo->prepare('INSERT OR REPLACE INTO transactions (folder, akey, email, side, stage, stage_name, grp, name, lead_id, listed, first_seen, seen, updated) VALUES (?,?,?,?,?,?,?,?,?,1,?,?,?)')
            ->execute([$fid, $ak, $me, 'buyer', 1, ld_stage_name('buyer', 1), '1 BUYERS / sample', $label, $leadId, $now, $now, $now]);
        $pdo->prepare('UPDATE transactions SET pstage = ? WHERE folder = ?')->execute(['agreement', $fid]);
        $pdo->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)')->execute(['sample_info_' . $fid, json_encode($info)]);
        ld_update($me, function (&$db) use ($leadId, $fid, $label) {
            foreach ($db['leads'] as &$L) { if (($L['id'] ?? '') !== $leadId) continue;
                $L['roles']['buyer'] = ['sub' => 'client', 'since' => ld_today(), 'touches' => 0, 'last' => '', 'folder' => $fid, 'record' => $label, 'stage' => 1];
                $L['log'][] = ['d' => ld_today(), 'role' => 'buyer', 'mid' => '', 'note' => 'Sample buyer record created in Active Business'];
            }
        });
        flr_audit($ak, 'consult_record_created', $fid, 'sample lead ' . $leadId);
        return ['ok' => true, 'tx' => $fid, 'created' => true];
    }
    $jr = flr_get_json(ROSTER_URL . '?key=' . urlencode(ROSTER_KEY) . '&op=mytxroot&email=' . urlencode($me));
    $hint = (is_array($jr) && !empty($jr['ok']) && !empty($jr['id'])) ? (string)$jr['id'] : '';
    if (!preg_match('/^[A-Za-z0-9_-]{10,80}$/', $hint)) return ['ok' => false, 'msg' => 'Could not find your Active Business folder. Please try again in a moment.'];
    $label = preg_replace('/\s+/', ' ', trim($info['b1_name'] . (($info['b2_name'] ?? '') !== '' ? ' & ' . $info['b2_name'] : '')));
    $info['updated'] = gmdate('c');
    $info += ld_pack_for_record($lead, $all);
    $r = sg_script_post(['op' => 'createbuyer', 'email' => $me, 'hint' => $hint, 'name' => $label, 'info' => $info]);
    if (!is_array($r) || empty($r['ok']) || empty($r['id'])) return ['ok' => false, 'msg' => 'Could not create the buyer record' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : '') . '.'];
    $fid = (string)$r['id'];
    ld_update($me, function (&$db) use ($leadId, $fid, $label) {
        foreach ($db['leads'] as &$L) { if (($L['id'] ?? '') !== $leadId) continue;
            $L['roles']['buyer'] = ['sub' => 'client', 'since' => ld_today(), 'touches' => 0, 'last' => '', 'folder' => $fid, 'record' => $label, 'stage' => 1];
            $L['log'][] = ['d' => ld_today(), 'role' => 'buyer', 'mid' => '', 'note' => 'Buyer record created in Active Business'];
        }
    });
    flr_tx_dirty($me);
    flr_audit(flr_akey($me), 'consult_record_created', $fid, 'lead ' . $leadId);
    return ['ok' => true, 'tx' => $fid, 'created' => true];
}
