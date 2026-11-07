<?php
// Agent Records: a server-side mirror of the "Agent Records" Google Sheet (the Agents tab), so this data can be
// viewed and managed from a staff-only dashboard here instead of only in the Sheet.
//
// Parallel system, not a cutover: Lindsay keeps using the Sheet + the existing Apps Script exactly as she does
// today (sign-in, directory, announcements, agentdates, etc. all still read the Sheet; nothing here changes
// that). This table is populated and refreshed only by an explicit import (tools/import_agent_records.php),
// fed by a fresh pull of the Sheet that only Claude can do (no Sheets API access from this server). Near launch,
// Scott will ask for a sheet-vs-server compare and reconciliation before anything here becomes load-bearing.
require_once __DIR__ . '/flrdb.php';

// Every Agent Records column, in sheet order, as [snake_case field key => sheet header]. Keeping the exact
// header text lets the dashboard show real labels and lets a future re-import match columns even if the sheet's
// column order changes (matched by header text, not position).
const AR_FIELDS = [
    'agent_id' => 'Agent ID',
    'first_name' => 'First name',
    'last_name' => 'Last name',
    'full_name' => 'Full name',
    'preferred_name' => 'Preferred name',
    'status' => 'Status',
    'email' => 'Email',
    'phone' => 'Phone',
    'street_address' => 'Street address',
    'city' => 'City',
    'state' => 'State',
    'zip' => 'ZIP',
    'team_and_team_leader' => 'Team and team leader',
    'date_hired' => 'Date hired',
    'date_terminated' => 'Date terminated',
    'license_number' => 'License number',
    'license_expiration' => 'License expiration',
    'nrds_number' => 'NRDS number',
    'birthdate' => 'Birthdate',
    'agreement_signed' => 'Agreement signed',
    'birthday_post_ok' => 'Birthday post OK',
    'anniversary_post_ok' => 'Anniversary post OK',
    'business_card_ordered' => 'Business card ordered',
    'name_badge_ordered' => 'Name badge ordered',
    'headshot' => 'Headshot',
    'w_9_on_file' => 'W-9 on file',
    'w_9_date' => 'W-9 date',
    'where_the_w_9_is_kept' => 'Where the W-9 is kept',
    'last_updated' => 'Last updated',
    'notes' => 'Notes',
    'text_recognition_ok' => 'Text recognition OK',
    'email_recognition_ok' => 'Email recognition OK',
    'private_group_post_ok' => 'Private group post OK',
    'public_social_post_ok' => 'Public social post OK',
    'social_media_handles' => 'Social media handles',
    'office' => 'Office',
    'mls_office_id' => 'MLS office ID',
    'realtor_board' => 'Realtor board',
    'realtor_board_name' => 'Realtor board name',
    'mls_name' => 'MLS name',
    'home_office_from_mls_office_id' => 'Home office (from MLS office ID)',
    'dbpr_legal_name' => 'DBPR legal name',
    'pay_commission_to' => 'Pay commission to',
    'payee_type' => 'Payee type',
    'secondary_realtor_board' => 'Secondary Realtor board',
    'records_folder_link' => 'Records folder link',
    'records_beta_start' => 'Records beta start',
    'google_account' => 'Google Account',
    'referral_fee_split_to_agent' => 'Referral fee split to agent',
    'commercial_split_to_agent' => 'Commercial split to agent',
    'commercial_approved' => 'Commercial approved',
    'property_management_approved' => 'Property management approved',
    'property_management_split_to_agent' => 'Property management split to agent',
    'revenue_share_option_in_agreement' => 'Revenue share option in agreement',
    'driver_s_license_copy_on_file' => "Driver's license copy on file",
    'driver_s_license_copy_date' => "Driver's license copy date",
    'auto_insurance_card_on_file' => 'Auto insurance card on file',
    'auto_insurance_expiration' => 'Auto insurance expiration',
    'where_the_id_and_insurance_copies_are_kept' => 'Where the ID and insurance copies are kept',
    'branded_logo' => 'Branded logo',
    'business_card_qr_code' => 'Business card QR code',
    'license_last_renewed' => 'License last renewed',
    'realtor_board_dues_paid' => 'Realtor board dues paid',
    'mls_dues_paid' => 'MLS dues paid',
    'agent_directory_ok' => 'Agent directory OK',
];

// Fields grouped for the dashboard form; every AR_FIELDS key appears in exactly one group.
const AR_GROUPS = [
    'Identity' => ['agent_id', 'first_name', 'last_name', 'full_name', 'preferred_name', 'status', 'email', 'phone', 'street_address', 'city', 'state', 'zip', 'google_account', 'team_and_team_leader', 'office'],
    'Dates' => ['date_hired', 'date_terminated', 'birthdate', 'agreement_signed', 'records_beta_start', 'last_updated'],
    'License & boards' => ['license_number', 'license_expiration', 'license_last_renewed', 'nrds_number', 'mls_office_id', 'realtor_board', 'realtor_board_name', 'secondary_realtor_board', 'mls_name', 'home_office_from_mls_office_id', 'realtor_board_dues_paid', 'mls_dues_paid'],
    'Compliance & files' => ['w_9_on_file', 'w_9_date', 'where_the_w_9_is_kept', "driver_s_license_copy_on_file", "driver_s_license_copy_date", 'auto_insurance_card_on_file', 'auto_insurance_expiration', 'where_the_id_and_insurance_copies_are_kept', 'records_folder_link'],
    'Compensation' => ['pay_commission_to', 'payee_type', 'dbpr_legal_name', 'referral_fee_split_to_agent', 'commercial_split_to_agent', 'commercial_approved', 'property_management_approved', 'property_management_split_to_agent', 'revenue_share_option_in_agreement'],
    'Marketing & consent' => ['headshot', 'social_media_handles', 'branded_logo', 'business_card_qr_code', 'business_card_ordered', 'name_badge_ordered', 'birthday_post_ok', 'anniversary_post_ok', 'text_recognition_ok', 'email_recognition_ok', 'private_group_post_ok', 'public_social_post_ok', 'agent_directory_ok'],
    'Notes' => ['notes'],
];

function ar_db() {
    $pdo = flr_db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS agent_records (agent_id TEXT PRIMARY KEY, email TEXT, status TEXT, full_name TEXT, preferred_name TEXT, fields TEXT NOT NULL DEFAULT "{}", updated TEXT, updated_by TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS agent_records_email ON agent_records(email)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS agent_records_status ON agent_records(status)');
    return $pdo;
}

function ar_yes($v) { return strtolower(trim((string)$v)) === 'yes'; }

// All agents, newest-first by full name. Each row: the indexed columns plus every AR_FIELDS key under 'fields'.
function ar_all() {
    $rows = ar_db()->query('SELECT * FROM agent_records ORDER BY (status = "Active") DESC, full_name')->fetchAll();
    foreach ($rows as &$r) { $f = json_decode($r['fields'], true); $r['fields'] = is_array($f) ? $f : []; }
    return $rows;
}
function ar_get($agentId) {
    $q = ar_db()->prepare('SELECT * FROM agent_records WHERE agent_id = ?'); $q->execute([$agentId]); $r = $q->fetch();
    if (!$r) return null;
    $f = json_decode($r['fields'], true); $r['fields'] = is_array($f) ? $f : [];
    return $r;
}
// Upsert one agent's full field set (an assoc array keyed by AR_FIELDS keys -- unknown keys are dropped, missing
// keys default to ''). $by is the staff email making the change, for the updated_by column.
function ar_save($agentId, array $fields, $by = '') {
    $clean = [];
    foreach (AR_FIELDS as $k => $label) { $clean[$k] = trim((string)($fields[$k] ?? '')); }
    $clean['agent_id'] = $agentId;
    ar_db()->prepare('INSERT INTO agent_records (agent_id, email, status, full_name, preferred_name, fields, updated, updated_by) VALUES (?,?,?,?,?,?,?,?)
        ON CONFLICT(agent_id) DO UPDATE SET email=excluded.email, status=excluded.status, full_name=excluded.full_name, preferred_name=excluded.preferred_name, fields=excluded.fields, updated=excluded.updated, updated_by=excluded.updated_by')
        ->execute([$agentId, strtolower($clean['email']), $clean['status'], $clean['full_name'], $clean['preferred_name'], json_encode($clean), date('c'), strtolower(trim((string)$by))]);
}
function ar_delete($agentId) { ar_db()->prepare('DELETE FROM agent_records WHERE agent_id = ?')->execute([$agentId]); }

// Upcoming birthdays and work anniversaries for Active agents, within $days (today counts as 0), Eastern time.
// Birthdate is MM-DD (no year, so age is never known or shown); anniversaries come from Date hired (YYYY-MM-DD) and
// only count once the agent has completed at least one year. Feb 29 falls on Feb 28 in non-leap years.
// Item shape (used by home.php): type, name, email, monthDay, daysAway, postOk, years (anniversary only).
function ar_upcoming_dates($days = 14, $today = null) {
    $tz = new DateTimeZone('America/New_York');
    $t = $today ? new DateTime($today, $tz) : new DateTime('today', $tz); $t->setTime(0, 0);
    $next = function ($m, $d) use ($t, $tz) {
        foreach ([(int)$t->format('Y'), (int)$t->format('Y') + 1] as $y) {
            $dd = ($m === 2 && $d === 29 && !checkdate(2, 29, $y)) ? 28 : $d;
            if (!checkdate($m, $dd, $y)) return null;
            $c = new DateTime(sprintf('%04d-%02d-%02d', $y, $m, $dd), $tz);
            if ($c >= $t) return $c;
        }
        return null;
    };
    $out = [];
    foreach (ar_all() as $r) {
        if (strtolower($r['status']) !== 'active') continue;
        $f = $r['fields']; $name = $r['preferred_name'] !== '' ? $r['preferred_name'] : $r['full_name']; if ($name === '') continue;
        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $f['birthdate'] ?? '', $m) && ($c = $next((int)$m[1], (int)$m[2]))) {
            $da = (int)$t->diff($c)->days;
            if ($da <= $days) $out[] = ['type' => 'birthday', 'name' => $name, 'email' => $r['email'], 'agent_id' => $r['agent_id'], 'date' => $c->format('Y-m-d'), 'monthDay' => $c->format('m-d'), 'daysAway' => $da, 'postOk' => ar_yes($f['birthday_post_ok'] ?? '')];
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f['date_hired'] ?? '', $m) && ($c = $next((int)$m[2], (int)$m[3]))) {
            $da = (int)$t->diff($c)->days; $yrs = (int)$c->format('Y') - (int)$m[1];
            if ($da <= $days && $yrs >= 1) $out[] = ['type' => 'anniversary', 'name' => $name, 'email' => $r['email'], 'agent_id' => $r['agent_id'], 'date' => $c->format('Y-m-d'), 'monthDay' => $c->format('m-d'), 'daysAway' => $da, 'years' => $yrs, 'postOk' => ar_yes($f['anniversary_post_ok'] ?? '')];
        }
    }
    usort($out, function ($a, $b) { return [$a['daysAway'], $a['name']] <=> [$b['daysAway'], $b['name']]; });
    return $out;
}

// Import a sheet pull shaped {header:[...], keys:[...], records:[{key:value}]}. Columns are matched by header TEXT
// (not position), every row is an upsert by Agent ID, and nothing is ever deleted. Returns a summary array, or
// ['error' => '...'] without changing anything if the data isn't the expected shape.
function ar_import($data, $by = '') {
    if (!is_array($data) || empty($data['header']) || empty($data['keys']) || !isset($data['records']) || !is_array($data['records'])) return ['error' => 'Not the expected {header, keys, records} shape.'];
    $header = $data['header']; $jsonKeys = $data['keys'];
    if (count($header) !== count($jsonKeys)) return ['error' => 'header/keys length mismatch.'];
    $headerToArKey = array_flip(AR_FIELDS); $posToArKey = []; $unmatched = [];
    foreach ($header as $i => $h) { $h = trim((string)$h); if (isset($headerToArKey[$h])) $posToArKey[$i] = $headerToArKey[$h]; else $unmatched[] = $h; }
    $missing = array_values(array_diff(array_keys(AR_FIELDS), array_values($posToArKey)));
    $before = []; foreach (ar_all() as $r) $before[$r['agent_id']] = $r['fields'];
    $s = ['added' => [], 'changed' => [], 'unchanged' => 0, 'skipped' => 0, 'unmatched_columns' => $unmatched, 'missing_columns' => $missing, 'not_in_pull' => []];
    $seen = []; $pdo = ar_db(); $pdo->exec('BEGIN IMMEDIATE');
    try {
        foreach ($data['records'] as $rec) {
            $fields = []; foreach ($posToArKey as $i => $k) $fields[$k] = trim((string)(is_array($rec) ? ($rec[$jsonKeys[$i]] ?? '') : ''));
            $id = $fields['agent_id'] ?? ''; if ($id === '') { $s['skipped']++; continue; }
            $seen[$id] = true;
            $name = ($fields['full_name'] ?? '') !== '' ? $fields['full_name'] : $id;
            if (!isset($before[$id])) $s['added'][] = $name;
            else {
                $cmp = $before[$id]; foreach (AR_FIELDS as $k => $l) { $cmp[$k] = $cmp[$k] ?? ''; $fields[$k] = $fields[$k] ?? ''; }
                $diff = []; foreach (AR_FIELDS as $k => $l) { if ((string)$cmp[$k] !== (string)$fields[$k]) $diff[] = $l; }
                if ($diff) $s['changed'][] = $name . ' (' . implode(', ', $diff) . ')'; else { $s['unchanged']++; continue; }
            }
            ar_save($id, $fields, $by);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); return ['error' => 'Import failed, nothing changed: ' . $e->getMessage()]; }
    foreach ($before as $id => $f) if (!isset($seen[$id])) $s['not_in_pull'][] = ($f['full_name'] ?? '') !== '' ? $f['full_name'] : $id;
    flr_audit('', 'agent_records_import', '', count($s['added']) . ' added, ' . count($s['changed']) . ' changed, ' . $s['unchanged'] . ' unchanged');
    return $s;
}
