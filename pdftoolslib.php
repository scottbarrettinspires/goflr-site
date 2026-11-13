<?php
// PDF Tools: file a finished PDF into one of the signed-in agent's Active Business records (the Drive folder behind a buyer or seller record).
// Same folder rules as every other filing action: only the agent's own My Transactions folders, never overwrites (a number is added to a repeated name).
require_once __DIR__ . '/flrdb.php';
require_once __DIR__ . '/sigtools.php';
const PTL_MAX_PDF = 20 * 1024 * 1024;

// The agent's Active Business records: [id, section, name, group]. Returns [records|null, note]. Cached in the session for 10 minutes.
function ptl_records($me, $fresh = false) {
    if (!$fresh && !empty($_SESSION['ptl_rec']) && ($_SESSION['ptl_rec_t'] ?? 0) > time() - 600 && ($_SESSION['ptl_rec_u'] ?? '') === strtolower($me)) return [$_SESSION['ptl_rec'], ''];
    $all = sg_folders($me);
    if (!is_array($all)) return [null, 'Could not load your Active Business records. ' . ($GLOBALS['sg_folders_note'] ?? 'Please try again in a moment.')];
    $out = [];
    foreach ($all as $f) {
        $g = (string)($f['group'] ?? ''); $parts = explode(' / ', $g, 2);
        $side = ucwords(strtolower(trim(preg_replace('/^\d+\s*/', '', $parts[0] ?? ''))));
        $stage = trim(preg_replace('/^\d+\s*/', '', $parts[1] ?? ''));
        $out[] = ['id' => (string)$f['id'], 'section' => trim($side . ($stage !== '' && strcasecmp($stage, $side) !== 0 ? ' - ' . $stage : '')), 'name' => (string)$f['name'], 'group' => $g];
    }
    $_SESSION['ptl_rec'] = $out; $_SESSION['ptl_rec_t'] = time(); $_SESSION['ptl_rec_u'] = strtolower($me);
    return [$out, ''];
}
// Saves PDF bytes into the record's folder. Returns [true, [name, url, folder, folder_url]] or [false, message].
function ptl_save($me, $folderId, $name, $pdf) {
    $folderId = (string)$folderId;
    if (!preg_match('/^[A-Za-z0-9_-]{10,80}$/', $folderId)) return [false, 'Choose an Active Business record to save to.'];
    if (strlen($pdf) < 100 || strncmp($pdf, '%PDF-', 5) !== 0) return [false, 'That file is not a readable PDF.'];
    if (strlen($pdf) > PTL_MAX_PDF) return [false, 'That PDF is ' . round(strlen($pdf) / 1048576) . ' MB, too large to save here (20 MB max). Download it and add it to the record by hand, or compress it first.'];
    list($recs, $note) = ptl_records($me);
    if ($recs === null) return [false, $note];
    $rec = null; foreach ($recs as $r) if ($r['id'] === $folderId) { $rec = $r; break; }
    if (!$rec) return [false, 'That record was not found in your Active Business. Reload the page and pick it again.'];
    $name = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', '-', (string)$name)); if ($name === '') $name = 'PDF Tools document';
    if (!preg_match('/\.pdf$/i', $name)) $name .= '.pdf';
    $name = mb_substr($name, 0, 120);
    $r = sg_script_post(['op' => 'save', 'email' => $me, 'folderId' => $folderId, 'name' => $name, 'b64' => base64_encode($pdf)]);
    if (!is_array($r) || empty($r['ok'])) return [false, 'Could not save to the record' . (is_array($r) && !empty($r['note']) ? ' (' . $r['note'] . ')' : ' (no answer from the office script)') . '.'];
    flr_audit(flr_akey($me), 'pdftools_saved', $folderId, ($r['name'] ?? $name) . ' -> ' . $rec['name']);
    return [true, ['name' => (string)($r['name'] ?? $name), 'url' => (string)($r['url'] ?? ''), 'folder' => $rec['name'], 'folder_url' => (string)($r['folderUrl'] ?? '')]];
}
