<?php
// Sample data for building and reviewing the back office before launch (Settings > Sample data, staff only).
// Loads a realistic set of agent data into the signed-in staff person's OWN account, so they can open Agent View and
// see what agents will see: buyer and seller records in every pipeline stage, contract deadlines of every kind
// (overdue, today, this week, on a weekend or holiday, later), client names and addresses, leads from every
// prospecting source, prospecting activity and goals, and signed documents. Everything is marked as sample:
//   transaction folders and signature requests start with "SAMPLE-", leads with "smp", prospecting rows are created = 'SAMPLE'.
// sd_clear() removes every sample item for everyone, in one step, before the official launch.
require_once __DIR__ . '/flrdb.php';
require_once __DIR__ . '/dlib.php';
require_once __DIR__ . '/pstage.php';
require_once __DIR__ . '/leadlib.php';
require_once __DIR__ . '/sigtools.php';
date_default_timezone_set('America/New_York');

function sd_db() { $pdo = flr_db(); $pdo->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT)'); return $pdo; }
function sd_emails() { $q = sd_db()->query("SELECT v FROM kv WHERE k = 'sample_emails'"); $a = json_decode((string)$q->fetchColumn(), true); return is_array($a) ? $a : []; }
function sd_loaded($email) { return in_array(strtolower($email), sd_emails(), true); }
// Sample client details for a SAMPLE- folder (used where real records read their details from Drive).
function sd_info($folder) {
    if (strpos((string)$folder, 'SAMPLE-') !== 0) return null;
    $q = flr_db()->prepare('SELECT v FROM kv WHERE k = ?'); $q->execute(['sample_info_' . $folder]); $j = json_decode((string)$q->fetchColumn(), true);
    return is_array($j) ? $j : [];
}
function sd_counts() {
    $pdo = sd_db(); $c = [];
    $c['records'] = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE folder LIKE 'SAMPLE-%'")->fetchColumn();
    $c['deadline sets'] = (int)$pdo->query("SELECT COUNT(*) FROM contracts WHERE folder LIKE 'SAMPLE-%'")->fetchColumn();
    $c['leads'] = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE id LIKE 'smp%'")->fetchColumn();
    try { $c['prospecting entries'] = (int)$pdo->query("SELECT COUNT(*) FROM pr_log WHERE created = 'SAMPLE'")->fetchColumn(); } catch (Throwable $e) { $c['prospecting entries'] = 0; }
    $c['signed documents'] = count(glob(sg_dir() . '/SAMPLE-*.json') ?: []);
    return $c;
}

function sd_load($email) {
    $email = strtolower(trim($email)); $ak = flr_akey($email); $pdo = sd_db();
    sd_clear_for($email); // start fresh for this person
    $D = function ($n) { return date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days')); };
    $now = date('c');
    // [folder, side, drive stage, name, pipeline stage, client info, deadline data or null]
    $recs = [
        ['SAMPLE-B1', 'buyer', 1, 'Bill & Sue Smith', 'agreement', ['b1_name' => 'Bill Smith', 'b2_name' => 'Sue Smith', 'b1_phone' => '727-555-0101', 'b1_email' => 'bill.smith@example.com', 'mailing' => '12 Oak Ct, Trinity FL 34655', 'payment' => 'conventional', 'lender' => 'Gulf Coast Lending', 'preapproval' => '450,000'], null],
        ['SAMPLE-B2', 'buyer', 1, 'Jamie Milano', 'shopping', ['b1_name' => 'Jamie Milano', 'b1_phone' => '727-555-0102', 'b1_email' => 'jamie.m@example.com', 'mailing' => '880 Bay Dr, New Port Richey FL 34652', 'payment' => 'cash'], null],
        ['SAMPLE-B3', 'buyer', 1, 'Jeremy Bergman', 'offer', ['b1_name' => 'Jeremy Bergman', 'b1_phone' => '727-555-0103', 'b1_email' => 'jbergman@example.com', 'payment' => 'fhava'], null],
        ['SAMPLE-B4', 'buyer', 3, '1150 Round Street', 'contract', ['b1_name' => 'Maria Lopez', 'b1_phone' => '727-555-0104', 'b1_email' => 'maria.lopez@example.com', 'property' => '1150 Round Street, Trinity FL 34655', 'payment' => 'conventional', 'lender' => 'Suncoast Mortgage', 'preapproval' => '380,000'],
            ['effective' => $D(-2), 'items' => ['deposit1' => ['days' => '3'], 'loanapp' => ['days' => '5'], 'inspect' => ['days' => '10'], 'appraisal' => ['days' => '21'], 'loan' => ['days' => '30'], 'closing' => ['date' => $D(35)]]]],
        ['SAMPLE-B5', 'buyer', 3, '6045 5th Avenue N', 'contingencies', ['b1_name' => 'Chris & Dana Patel', 'b1_phone' => '727-555-0105', 'b1_email' => 'patel.family@example.com', 'property' => '6045 5th Avenue N, St Petersburg FL 33710', 'payment' => 'conventional', 'lender' => 'First Bay Bank', 'preapproval' => '520,000'],
            ['effective' => $D(-12), 'items' => ['deposit1' => ['days' => '3', 'done' => true], 'deposit2' => ['days' => '15'], 'inspect' => ['days' => '15'], 'appraisal' => ['days' => '20'], 'loan' => ['days' => '30'], 'title' => ['days' => '17'], 'closing' => ['date' => $D(28)]]]],
        ['SAMPLE-B6', 'buyer', 3, '2210 Gulf Blvd #402', 'contingencies', ['b1_name' => 'Robert Kim', 'b1_phone' => '727-555-0106', 'b1_email' => 'rkim@example.com', 'property' => '2210 Gulf Blvd #402, Indian Rocks Beach FL 33785', 'payment' => 'cash'],
            ['effective' => $D(-9), 'items' => ['deposit1' => ['days' => '3', 'done' => true], 'inspect' => ['days' => '7'], 'hoa' => ['days' => '13'], 'title' => ['days' => '15'], 'closing' => ['date' => $D(21)]]]],
        ['SAMPLE-S1', 'seller', 1, '9 Oak Lane', 'prelisting', ['s1_name' => 'Linda Hart', 's1_phone' => '727-555-0201', 's1_email' => 'lhart@example.com', 'property' => '9 Oak Lane, Trinity FL 34655'], null],
        ['SAMPLE-S2', 'seller', 2, '55 Palm Avenue', 'active', ['s1_name' => 'Riley Turner', 's1_phone' => '727-555-0202', 's1_email' => 'rturner@example.com', 'property' => '55 Palm Avenue, Trinity FL 34655'], null],
        ['SAMPLE-S3', 'seller', 2, '314 Heron Way', 'offerrecv', ['s1_name' => 'Gary & Pat Olson', 's1_phone' => '727-555-0203', 's1_email' => 'olsons@example.com', 'property' => '314 Heron Way, Odessa FL 33556'], null],
        ['SAMPLE-S4', 'seller', 3, '78 Cypress Point Dr', 'contingencies', ['s1_name' => 'Nancy Ruiz', 's1_phone' => '727-555-0204', 's1_email' => 'nruiz@example.com', 'property' => '78 Cypress Point Dr, Palm Harbor FL 34683'],
            ['effective' => $D(-20), 'items' => ['deposit1' => ['days' => '3', 'done' => true], 'inspect' => ['days' => '15'], 'appraisal' => ['days' => '21'], 'loan' => ['days' => '30'], 'hoa' => ['days' => '22'], 'closing' => ['date' => $D(30)]]]],
        ['SAMPLE-S5', 'seller', 3, '1402 Bayshore Ct', 'closingready', ['s1_name' => 'Tom Bradley', 's1_phone' => '727-555-0205', 's1_email' => 'tbradley@example.com', 'property' => '1402 Bayshore Ct, Tarpon Springs FL 34689'],
            ['effective' => $D(-40), 'items' => ['deposit1' => ['days' => '3', 'done' => true], 'inspect' => ['days' => '15', 'done' => true], 'loan' => ['days' => '35', 'done' => true], 'title' => ['days' => '30', 'done' => true], 'closing' => ['date' => $D(4)]]]],
        ['SAMPLE-S6', 'seller', 3, '603 Lakeview Rd', 'closed', ['s1_name' => 'Ann Foster', 's1_phone' => '727-555-0206', 'property' => '603 Lakeview Rd, Land O Lakes FL 34639'], null],
    ];
    ps_ensure_column();
    $ins = $pdo->prepare('INSERT OR REPLACE INTO transactions (folder, akey, email, side, stage, stage_name, grp, name, lead_id, listed, first_seen, seen, updated) VALUES (?,?,?,?,?,?,?,?,?,1,?,?,?)');
    foreach ($recs as $r) {
        $ins->execute([$r[0], $ak, $email, $r[1], $r[2], ld_stage_name($r[1], $r[2]), ($r[1] === 'buyer' ? '1 BUYERS' : '2 SELLERS') . ' / sample', $r[3], '', $now, $now, $now]);
        $pdo->prepare('UPDATE transactions SET pstage = ? WHERE folder = ?')->execute([$r[4], $r[0]]);
        $pdo->prepare('INSERT OR REPLACE INTO kv (k, v) VALUES (?, ?)')->execute(['sample_info_' . $r[0], json_encode($r[5])]);
        if ($r[6]) dl_save($email, $r[0], $r[1], $r[6]);
    }
    // Leads from every prospecting source, in every category; some are the clients above.
    $L = function ($id, $f, $l, $ph, $src, $tags, $roles, $added, $notes = '') use ($email) {
        return ['id' => $id, 'first' => $f, 'last' => $l, 'phone' => $ph, 'email' => strtolower($f) . '.' . strtolower($l) . '@example.com', 'source' => $src, 'tags' => $tags, 'added' => $added, 'created' => $added . 'T09:00:00-05:00', 'notes' => $notes, 'roles' => $roles, 'log' => []];
    };
    $R = function ($sub, $since, $extra = []) { return ['sub' => $sub, 'since' => $since, 'touches' => 0, 'last' => ''] + $extra; };
    $leads = [
        $L('smp01', 'Taylor', 'Reed', '727-555-0301', 'Facebook', ['Facebook', 'Social media'], ['new' => ['since' => $D(0), 'touches' => 0]], $D(0), 'Asked about homes near Starkey Ranch.'),
        $L('smp02', 'Morgan', 'Blake', '727-555-0302', 'Zillow', ['Zillow'], ['new' => ['since' => $D(-1), 'touches' => 0]], $D(-1)),
        $L('smp03', 'Jordan', 'Price', '727-555-0303', 'Landing page', ['Landing page'], ['buyer' => $R('unid', $D(-12))], $D(-12)),
        $L('smp04', 'Casey', 'Nguyen', '727-555-0304', 'Open house', ['Open house'], ['buyer' => $R('ready', $D(-6))], $D(-6), 'Met at 55 Palm Ave open house.'),
        $L('smp05', 'Avery', 'Collins', '727-555-0305', 'Networking', ['Networking'], ['seller' => $R('soon', $D(-20))], $D(-20), 'Chamber mixer. Thinking of selling in spring.'),
        $L('smp06', 'Drew', 'Martin', '727-555-0306', 'Sphere', ['Sphere / friend or family'], ['sphere' => $R('a', $D(-45))], $D(-45)),
        $L('smp07', 'Sam', 'Ortiz', '727-555-0307', 'Past client', ['Past client'], ['sphere' => $R('b', $D(-90))], $D(-90)),
        $L('smp08', 'Pat', 'Hughes', '727-555-0308', 'Direct mail', ['Direct mail'], ['seller' => $R('later', $D(-30))], $D(-30), 'Farm postcard response.'),
        $L('smp09', 'Lee', 'Warren', '727-555-0309', 'Referral', ['Referral'], ['buyer' => $R('soon', $D(-15))], $D(-15), 'Referred by Drew Martin.'),
        $L('smp10', 'Kelly', 'Shaw', '727-555-0310', 'Expired', ['Expired'], ['seller' => $R('unid', $D(-3))], $D(-3)),
        $L('smp11', 'Bill', 'Smith', '727-555-0101', 'Instagram', ['Instagram', 'Social media'], ['buyer' => $R('client', $D(-25), ['folder' => 'SAMPLE-B1', 'stage' => 1])], $D(-25)),
        $L('smp12', 'Linda', 'Hart', '727-555-0201', 'Sphere', ['Sphere / friend or family'], ['seller' => $R('client', $D(-18), ['folder' => 'SAMPLE-S1', 'stage' => 1]), 'sphere' => $R('a', $D(-18))], $D(-60)),
        $L('smp13', 'Riley', 'Turner', '727-555-0202', 'Sign call', ['Sign call'], ['seller' => $R('client', $D(-40), ['folder' => 'SAMPLE-S2', 'stage' => 2])], $D(-50)),
        $L('smp14', 'Quinn', 'Foster', '727-555-0311', 'Website', ['Website'], ['buyer' => $R('later', $D(-8))], $D(-8)),
        $L('smp15', 'Jesse', 'Park', '727-555-0312', 'Agent referral', ['Agent referral'], ['buyer' => $R('ready', $D(-2))], $D(-2), 'Relocating from Ohio, referred by an agent there.'),
    ];
    flr_leads_update($ak, function (&$db) use ($leads) { $db['leads'] = array_merge($leads, array_values(array_filter((array)$db['leads'], function ($x) { return strpos((string)($x['id'] ?? ''), 'smp') !== 0; }))); return null; }, $email);
    foreach (['SAMPLE-B1' => 'smp11', 'SAMPLE-S1' => 'smp12', 'SAMPLE-S2' => 'smp13'] as $f => $lid) flr_tx_set($f, ['lead_id' => $lid]);
    // Prospecting activity and weekly goals
    $pdo->exec('CREATE TABLE IF NOT EXISTS pr_log (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, method TEXT NOT NULL, d TEXT NOT NULL, what TEXT, qty INTEGER DEFAULT 1, notes TEXT, created TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS pr_goals (email TEXT NOT NULL, method TEXT NOT NULL, per_week INTEGER, PRIMARY KEY (email, method))');
    $pl = $pdo->prepare("INSERT INTO pr_log (email, method, d, what, qty, notes, created) VALUES (?,?,?,?,?,?,'SAMPLE')");
    foreach ([['social', 0, 'Posted', 2, 'Just listed 55 Palm Ave'], ['social', -1, 'Went live', 1, 'Live tour of 55 Palm Ave'], ['social', -3, 'Replied to comments/DMs', 6, ''],
              ['openhouse', -1, 'Held an open house', 1, '55 Palm Ave, 14 visitors'], ['openhouse', -2, 'Invited neighbors', 20, 'Door hangers on Palm Ave'],
              ['network', -4, 'Attended an event', 1, 'Chamber mixer'], ['network', -2, 'Coffee or lunch meeting', 1, 'Lunch with a mortgage lender'],
              ['sphere', 0, 'Called', 5, 'A list check-ins'], ['sphere', -5, 'Pop-by', 3, 'Fall pumpkins'], ['farming', -6, 'Mailed postcards', 350, 'Trinity farm: just listed'],
              ['referral', -3, 'Asked for a referral', 2, ''], ['online', -1, 'Responded to a lead', 2, 'Zillow and landing page leads'], ['calls', 0, 'Calls made', 25, 'Expireds and FSBOs'], ['calls', 0, 'Appointments set', 1, '']] as $a)
        $pl->execute([$email, $a[0], $D($a[1]), $a[2], $a[3], $a[4]]);
    $pg = $pdo->prepare('INSERT INTO pr_goals (email, method, per_week) VALUES (?,?,?) ON CONFLICT(email, method) DO UPDATE SET per_week = excluded.per_week');
    foreach (['social' => 10, 'openhouse' => 1, 'sphere' => 15, 'calls' => 100, 'network' => 2] as $k => $g) $pg->execute([$email, $k, $g]);
    // Signed documents: one newly signed and filed, one waiting for signatures
    sg_save('SAMPLE-SIG1', ['id' => 'SAMPLE-SIG1', 'agent' => $email, 'form' => 'FLR - Exclusive Buyer Brokerage Agreement', 'form_id' => '', 'doc' => '', 'created' => gmdate('c', strtotime('-1 day')), 'completed_at' => gmdate('c', strtotime('-2 hours')),
        'signers' => [['email' => 'bill.smith@example.com'], ['email' => 'sue.smith@example.com']], 'signers_status' => ['bill.smith@example.com' => true, 'sue.smith@example.com' => true],
        'folder' => ['id' => 'SAMPLE-B1', 'label' => '1 BUYERS / Bill & Sue Smith'], 'completed' => true, 'filed' => ['name' => 'Signed - Exclusive Buyer Brokerage Agreement.pdf', 'url' => '#', 'folder' => '1 BUYERS / Bill & Sue Smith', 'at' => gmdate('c')]]);
    sg_save('SAMPLE-SIG2', ['id' => 'SAMPLE-SIG2', 'agent' => $email, 'form' => 'FLR - Listing Agreement', 'form_id' => '', 'doc' => '', 'created' => gmdate('c', strtotime('-3 hours')),
        'signers' => [['email' => 'lhart@example.com']], 'signers_status' => ['lhart@example.com' => false], 'folder' => ['id' => 'SAMPLE-S1', 'label' => '2 SELLERS / 9 Oak Lane'], 'completed' => false, 'filed' => null]);
    $em = sd_emails(); if (!in_array($email, $em, true)) $em[] = $email;
    $pdo->prepare("INSERT OR REPLACE INTO kv (k, v) VALUES ('sample_emails', ?)")->execute([json_encode($em)]);
    flr_audit($ak, 'sample_load', '', 'Sample data loaded');
}

// Remove one person's sample data (used before reloading).
function sd_clear_for($email) {
    $email = strtolower(trim($email)); $ak = flr_akey($email); $pdo = sd_db();
    $q = $pdo->prepare("SELECT folder FROM transactions WHERE folder LIKE 'SAMPLE-%' AND akey = ?"); $q->execute([$ak]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $f) { $pdo->prepare('DELETE FROM kv WHERE k IN (?, ?)')->execute(['sample_info_' . $f, 'nt_info_' . $f]); $pdo->prepare('DELETE FROM contracts WHERE folder = ?')->execute([$f]); }
    $pdo->prepare("DELETE FROM transactions WHERE folder LIKE 'SAMPLE-%' AND akey = ?")->execute([$ak]);
    try { flr_leads_update($ak, function (&$db) { $db['leads'] = array_values(array_filter((array)$db['leads'], function ($x) { return strpos((string)($x['id'] ?? ''), 'smp') !== 0; })); return null; }, $email); } catch (Throwable $e) {}
    try { $pdo->prepare("DELETE FROM pr_log WHERE created = 'SAMPLE' AND email = ?")->execute([$email]); $pdo->prepare('DELETE FROM pr_goals WHERE email = ?')->execute([$email]); } catch (Throwable $e) {}
    foreach (glob(sg_dir() . '/SAMPLE-*.json') ?: [] as $f) { $j = json_decode((string)file_get_contents($f), true); if (strtolower((string)($j['agent'] ?? '')) === $email) @unlink($f); }
}
// Remove ALL sample data, for everyone (before launch).
function sd_clear() {
    foreach (sd_emails() as $e) sd_clear_for($e);
    $pdo = sd_db();
    $pdo->exec("DELETE FROM contracts WHERE folder LIKE 'SAMPLE-%'"); $pdo->exec("DELETE FROM transactions WHERE folder LIKE 'SAMPLE-%'");
    $pdo->exec("DELETE FROM kv WHERE k LIKE 'sample_info_SAMPLE-%' OR k LIKE 'nt_info_SAMPLE-%'"); $pdo->exec("DELETE FROM leads WHERE id LIKE 'smp%'");
    try { $pdo->exec("DELETE FROM pr_log WHERE created = 'SAMPLE'"); } catch (Throwable $e) {}
    foreach (glob(sg_dir() . '/SAMPLE-*.json') ?: [] as $f) @unlink($f);
    $pdo->exec("DELETE FROM kv WHERE k = 'sample_emails'");
    flr_audit('', 'sample_clear', '', 'All sample data cleared');
}
