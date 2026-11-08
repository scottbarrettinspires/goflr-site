<?php
// FLR standard: the server holds the data, Google Drive holds the documents.
// One SQLite database outside the web root holds leads, agent settings, the transaction index and an audit trail.
// Each agent's old leads/<sha1>.json file is imported automatically the first time it is touched, and kept as a backup.

function flr_db_dir() {
    static $d = null; if ($d !== null) return $d;
    foreach ([dirname(__DIR__) . '/flrdata', __DIR__ . '/data'] as $c) { // outside public_html first
        if (!is_dir($c)) @mkdir($c, 0750, true);
        if (is_dir($c) && is_writable($c)) { $d = $c; break; }
    }
    if ($d === null) throw new RuntimeException('No writable data folder for the database.');
    if (strpos($d, __DIR__) === 0 && !is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "Require all denied\n");
    return $d;
}
function flr_db() {
    static $pdo = null; if ($pdo) return $pdo;
    $path = flr_db_dir() . '/flr.sqlite';
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=15000; PRAGMA synchronous=NORMAL;');
    $pdo->exec('CREATE TABLE IF NOT EXISTS agents (akey TEXT PRIMARY KEY, email TEXT, intake_key TEXT, meta TEXT NOT NULL DEFAULT "{}", updated TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS agents_intake ON agents(intake_key)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS agents_email ON agents(email)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS leads (id TEXT PRIMARY KEY, akey TEXT NOT NULL, pos INTEGER, first TEXT, last TEXT, phone TEXT, phone_digits TEXT, email TEXT, source TEXT, added TEXT, data TEXT NOT NULL, updated TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS leads_akey ON leads(akey, pos)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS leads_phone ON leads(akey, phone_digits)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS leads_email ON leads(akey, email)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS transactions (folder TEXT PRIMARY KEY, akey TEXT NOT NULL, email TEXT, side TEXT, stage INTEGER, stage_name TEXT, grp TEXT, name TEXT, lead_id TEXT, listed INTEGER DEFAULT 1, first_seen TEXT, seen TEXT, updated TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS tx_akey ON transactions(akey, side, stage)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS audit (id INTEGER PRIMARY KEY AUTOINCREMENT, at TEXT, akey TEXT, actor TEXT, action TEXT, ref TEXT, detail TEXT)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS audit_akey ON audit(akey, id)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT)');
    flr_db_backup($pdo, $path);
    return $pdo;
}
// Once a day: a consistent copy of the database, 14 days kept.
function flr_db_backup($pdo, $path) {
    $day = gmdate('Y-m-d'); $dir = flr_db_dir() . '/backups';
    try {
        $last = $pdo->query("SELECT v FROM kv WHERE k='backup_day'")->fetchColumn();
        if ($last === $day) return;
        $pdo->prepare("INSERT OR REPLACE INTO kv (k, v) VALUES ('backup_day', ?)")->execute([$day]);
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $to = $dir . '/flr-' . $day . '.sqlite'; if (is_file($to)) return;
        $pdo->exec('VACUUM INTO ' . $pdo->quote($to));
        $old = glob($dir . '/flr-*.sqlite') ?: []; sort($old);
        while (count($old) > 14) { @unlink(array_shift($old)); }
    } catch (Throwable $e) { /* a missed backup never blocks the agent */ }
}
function flr_akey($email) { return sha1(strtolower(trim((string)$email))); }
function flr_audit($akey, $action, $ref, $detail = '') {
    try { flr_db()->prepare('INSERT INTO audit (at, akey, actor, action, ref, detail) VALUES (?,?,?,?,?,?)')
        ->execute([date('c'), $akey, (string)($GLOBALS['FLR_ACTOR'] ?? ($_SESSION['email'] ?? 'system')), $action, (string)$ref, mb_substr((string)$detail, 0, 500)]); } catch (Throwable $e) {}
}

// ---- Lead store: the same shape the lead pages always used ({leads:[...], sources, interests, intake, ...}) ----
function flr_legacy_file($akey) { return __DIR__ . '/leads/' . $akey . '.json'; }
function flr_leads_read($pdo, $akey, $email = '') {
    $a = $pdo->prepare('SELECT meta, email FROM agents WHERE akey = ?'); $a->execute([$akey]); $row = $a->fetch();
    if (!$row) { // first touch: import the old JSON file, if any
        $old = json_decode((string)@file_get_contents(flr_legacy_file($akey)), true); if (!is_array($old)) $old = ['leads' => []];
        $leads = (array)($old['leads'] ?? []); unset($old['leads']);
        $pdo->prepare('INSERT OR IGNORE INTO agents (akey, email, intake_key, meta, updated) VALUES (?,?,?,?,?)')
            ->execute([$akey, strtolower((string)$email), (string)($old['intake']['key'] ?? ''), json_encode($old), date('c')]);
        foreach (array_values($leads) as $i => $L) { flr_lead_put($pdo, $akey, $i, $L); }
        if ($leads) flr_audit($akey, 'import', '', count($leads) . ' leads imported from the old lead file');
        $db = $old; $db['leads'] = array_values($leads); return $db;
    }
    if ($email !== '' && (string)$row['email'] === '') $pdo->prepare('UPDATE agents SET email = ? WHERE akey = ?')->execute([strtolower($email), $akey]);
    $db = json_decode((string)$row['meta'], true); if (!is_array($db)) $db = [];
    $q = $pdo->prepare('SELECT data FROM leads WHERE akey = ? ORDER BY pos, rowid'); $q->execute([$akey]);
    $db['leads'] = []; foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $d) { $L = json_decode($d, true); if (is_array($L)) $db['leads'][] = $L; }
    return $db;
}
function flr_lead_put($pdo, $akey, $pos, $L) {
    $d = preg_replace('/\D/', '', (string)($L['phone'] ?? '')); if (strlen($d) === 11 && $d[0] === '1') $d = substr($d, 1);
    $pdo->prepare('INSERT INTO leads (id, akey, pos, first, last, phone, phone_digits, email, source, added, data, updated) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ON CONFLICT(id) DO UPDATE SET akey=excluded.akey, pos=excluded.pos, first=excluded.first, last=excluded.last, phone=excluded.phone, phone_digits=excluded.phone_digits, email=excluded.email, source=excluded.source, added=excluded.added, data=excluded.data, updated=excluded.updated')
        ->execute([(string)$L['id'], $akey, (int)$pos, (string)($L['first'] ?? ''), (string)($L['last'] ?? ''), (string)($L['phone'] ?? ''), $d, strtolower(trim((string)($L['email'] ?? ''))), (string)($L['source'] ?? ''), (string)($L['added'] ?? ''), json_encode($L), date('c')]);
}
function flr_leads_load($akey, $email = '') {
    $pdo = flr_db(); if ($pdo->inTransaction()) return flr_leads_read($pdo, $akey, $email);
    $pdo->exec('BEGIN IMMEDIATE');
    try { $db = flr_leads_read($pdo, $akey, $email); $pdo->exec('COMMIT'); return $db; } catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
}
// Read-modify-write in one locked transaction; only leads that changed are written, and every change is audited.
function flr_leads_update($akey, callable $fn, $email = '') {
    $pdo = flr_db(); if ($pdo->inTransaction()) throw new LogicException('Nested lead update');
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $db = flr_leads_read($pdo, $akey, $email);
        $before = []; foreach ($db['leads'] as $i => $L) { if (!empty($L['id'])) $before[$L['id']] = [$i, json_encode($L)]; }
        $res = $fn($db);
        if (!isset($db['leads']) || !is_array($db['leads'])) $db['leads'] = [];
        $db['leads'] = array_values($db['leads']); $now = [];
        foreach ($db['leads'] as $i => $L) {
            if (empty($L['id'])) continue; $now[$L['id']] = true; $j = json_encode($L);
            if (!isset($before[$L['id']])) { flr_lead_put($pdo, $akey, $i, $L); flr_audit($akey, 'lead_add', $L['id'], trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? ''))); }
            elseif ($before[$L['id']][1] !== $j) { flr_lead_put($pdo, $akey, $i, $L); flr_audit($akey, 'lead_update', $L['id'], trim(($L['first'] ?? '') . ' ' . ($L['last'] ?? ''))); }
            elseif ($before[$L['id']][0] !== $i) { $pdo->prepare('UPDATE leads SET pos = ? WHERE id = ?')->execute([$i, $L['id']]); }
        }
        foreach ($before as $id => $x) { if (!isset($now[$id])) { $pdo->prepare('DELETE FROM leads WHERE id = ? AND akey = ?')->execute([$id, $akey]); flr_audit($akey, 'lead_delete', $id); } }
        $meta = $db; unset($meta['leads']);
        $pdo->prepare('UPDATE agents SET meta = ?, intake_key = ?, updated = ? WHERE akey = ?')->execute([json_encode($meta), (string)($meta['intake']['key'] ?? ''), date('c'), $akey]);
        $pdo->exec('COMMIT');
        return $res;
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
}
function flr_intake_lookup($key) {
    $q = flr_db()->prepare('SELECT akey, meta FROM agents WHERE intake_key = ?'); $q->execute([$key]); $r = $q->fetch();
    if ($r) { $m = json_decode($r['meta'], true); return ['akey' => $r['akey'], 'intake' => (array)($m['intake'] ?? [])]; }
    // Not migrated yet: look in the old files, then import that agent.
    foreach (glob(__DIR__ . '/leads/' . substr($key, 0, 12) . '*.json') ?: [] as $f) {
        $db = json_decode((string)@file_get_contents($f), true);
        if (is_array($db) && !empty($db['intake']['key']) && hash_equals($db['intake']['key'], $key)) { $ak = basename($f, '.json'); flr_leads_load($ak); return ['akey' => $ak, 'intake' => $db['intake']]; }
    }
    return null;
}

// ---- Transaction index: one row per Active Business record folder (documents stay in Drive) ----
function flr_tx_side($grp) { return preg_match('/^1\s/', $grp) ? 'buyer' : (preg_match('/^2\s/', $grp) ? 'seller' : 'other'); }
function flr_tx_stage($grp) { $p = explode(' / ', $grp, 2); return preg_match('/^(\d+)\s*(.*)$/', trim($p[1] ?? ''), $m) ? [(int)$m[1], trim($m[2])] : [0, '']; }
// Store what Drive shows now. Returns [rows changed, list of folder ids that changed stage].
function flr_tx_sync($email, array $folders) {
    $pdo = flr_db(); $ak = flr_akey($email); $now = date('c'); $moved = []; $n = 0; $seen = [];
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $cur = []; $q = $pdo->prepare('SELECT folder, stage, grp, name, listed FROM transactions WHERE akey = ?'); $q->execute([$ak]); foreach ($q->fetchAll() as $r) $cur[$r['folder']] = $r;
        foreach ($folders as $f) {
            $id = (string)($f['id'] ?? ''); if ($id === '') continue; $seen[$id] = true;
            $grp = (string)($f['group'] ?? ''); list($st, $stn) = flr_tx_stage($grp); $side = flr_tx_side($grp); $nm = (string)($f['name'] ?? '');
            if (!isset($cur[$id])) { $pdo->prepare('INSERT INTO transactions (folder, akey, email, side, stage, stage_name, grp, name, listed, first_seen, seen, updated) VALUES (?,?,?,?,?,?,?,?,1,?,?,?)')->execute([$id, $ak, strtolower($email), $side, $st, $stn, $grp, $nm, $now, $now, $now]); $n++; flr_audit($ak, 'tx_found', $id, $grp . ' / ' . $nm); continue; }
            $c = $cur[$id];
            if ($c['grp'] !== $grp || $c['name'] !== $nm || !(int)$c['listed']) {
                $pdo->prepare('UPDATE transactions SET side=?, stage=?, stage_name=?, grp=?, name=?, listed=1, seen=?, updated=? WHERE folder=?')->execute([$side, $st, $stn, $grp, $nm, $now, $now, $id]); $n++;
                if ((int)$c['stage'] !== $st) { $moved[] = $id; flr_audit($ak, 'tx_stage', $id, $c['grp'] . ' -> ' . $grp); }
            } else { $pdo->prepare('UPDATE transactions SET seen=? WHERE folder=?')->execute([$now, $id]); }
        }
        foreach ($cur as $id => $c) { if (!isset($seen[$id]) && (int)$c['listed'] && strpos((string)$id, 'SAMPLE-') !== 0) { $pdo->prepare('UPDATE transactions SET listed=0, updated=? WHERE folder=?')->execute([$now, $id]); $n++; flr_audit($ak, 'tx_unlisted', $id, 'No longer in the active folders (archived or moved)'); } }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
    return [$n, $moved];
}
function flr_tx_list($email, $listedOnly = true) {
    $q = flr_db()->prepare('SELECT * FROM transactions WHERE akey = ?' . ($listedOnly ? ' AND listed = 1' : '') . ' ORDER BY side, stage, name'); $q->execute([flr_akey($email)]); return $q->fetchAll();
}
function flr_tx_set($folder, array $set) {
    if (!$set) return; $cols = []; $vals = [];
    foreach ($set as $k => $v) { if (in_array($k, ['stage', 'stage_name', 'grp', 'lead_id', 'listed', 'name'], true)) { $cols[] = $k . ' = ?'; $vals[] = $v; } }
    if (!$cols) return; $vals[] = date('c'); $vals[] = $folder;
    flr_db()->prepare('UPDATE transactions SET ' . implode(', ', $cols) . ', updated = ? WHERE folder = ?')->execute($vals);
}
// A record was created or moved from the site: sync again on the next page load instead of waiting.
function flr_tx_dirty($email) { try { flr_db()->prepare('DELETE FROM kv WHERE k = ?')->execute(['txsync_' . flr_akey($email)]); } catch (Throwable $e) {} }
