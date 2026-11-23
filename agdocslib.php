<?php
// Agent documents (brokerage IC agreement and other agreements with the brokerage), staff only. The files live in the shared
// drive: Active / "A-0078 Alexandra Fitchett" / Agreements. The site reaches them through the office Google Apps Script
// (ROSTER_URL) -- ops agentdocs (list) and agentdocupload (add a file) -- see tools/office_script_agentdocs.gs.
// Both functions take plain arguments and return an array: ['ok' => true, ...] or ['ok' => false, 'msg' => readable error].
require_once __DIR__ . '/flrdb.php';
require_once __DIR__ . '/arlib.php';
if (!defined('ROSTER_URL')) require_once __DIR__ . '/config.php';

const AD_MAX_BYTES = 8000000; // the office script takes the file inside a JSON post; keep uploads modest
const AD_EXT = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'xls', 'xlsx', 'txt'];

function ad_script($payload, $post) {
    if (strpos((string)ROSTER_URL, 'PASTE_') === 0) return ['ok' => false, 'msg' => 'The office script address is not set on this site.'];
    $ch = curl_init();
    if ($post) {
        $payload['key'] = ROSTER_KEY;
        curl_setopt_array($ch, [CURLOPT_URL => ROSTER_URL, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => ['Content-Type: text/plain']]);
    } else curl_setopt($ch, CURLOPT_URL, ROSTER_URL . '?' . http_build_query(['key' => ROSTER_KEY] + $payload));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 90, CURLOPT_SSL_VERIFYPEER => true]);
    $b = curl_exec($ch); curl_close($ch);
    $j = $b === false ? null : json_decode((string)$b, true);
    if (!is_array($j)) return ['ok' => false, 'msg' => 'The office script did not answer. Try again in a moment.'];
    if (empty($j['ok'])) {
        $m = (string)($j['error'] ?? $j['msg'] ?? '');
        if ($m === '' || stripos($m, 'unknown') !== false || stripos($m, 'bad op') !== false) $m = 'The office script has not been updated for agent documents yet (see tools/office_script_agentdocs.gs).';
        return ['ok' => false, 'msg' => $m];
    }
    return $j;
}
// Documents in one agent's Agreements folder: {ok, folder_url, agent_folder_url, files:[{id, name, url, modified, size}]}.
function ad_list($agentId) {
    $r = ar_get($agentId); if (!$r) return ['ok' => false, 'msg' => 'No agent record with that Agent ID.'];
    $j = ad_script(['op' => 'agentdocs', 'agent_id' => $agentId], false);
    if (!$j['ok']) return $j;
    $files = []; foreach ((array)($j['files'] ?? []) as $f) { if (empty($f['name']) || empty($f['url'])) continue;
        $files[] = ['id' => (string)($f['id'] ?? ''), 'name' => (string)$f['name'], 'url' => (string)$f['url'], 'modified' => substr((string)($f['modified'] ?? ''), 0, 10), 'size' => (int)($f['size'] ?? 0)]; }
    usort($files, function ($a, $b) { return strcmp($a['name'], $b['name']); });
    return ['ok' => true, 'agent_id' => $agentId, 'folder_url' => (string)($j['folder_url'] ?? ''), 'agent_folder_url' => (string)($j['agent_folder_url'] ?? ''), 'files' => $files];
}
// Add a file to the agent's Agreements folder. $file is a PHP $_FILES entry; $name is an optional display name (the file keeps its
// extension). Returns {ok, name, url} or {ok:false, msg}. Records who uploaded it in the audit log.
function ad_upload($agentId, array $file, $name = '', $by = '') {
    $r = ar_get($agentId); if (!$r) return ['ok' => false, 'msg' => 'No agent record with that Agent ID.'];
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) return ['ok' => false, 'msg' => 'Choose a file to upload.'];
    if (($file['size'] ?? 0) > AD_MAX_BYTES) return ['ok' => false, 'msg' => 'That file is larger than ' . (AD_MAX_BYTES / 1000000) . ' MB. Please upload a smaller copy.'];
    $orig = (string)($file['name'] ?? 'document'); $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, AD_EXT, true)) return ['ok' => false, 'msg' => 'Upload a PDF, Word, Excel, image or text file.'];
    $title = trim((string)$name) !== '' ? trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', ' ', (string)$name)) : pathinfo($orig, PATHINFO_FILENAME);
    $title = mb_substr(trim($title), 0, 150); if ($title === '') $title = 'Document';
    if (strtolower(pathinfo($title, PATHINFO_EXTENSION)) !== $ext) $title .= '.' . $ext;
    $mime = (string)(function_exists('mime_content_type') ? @mime_content_type($file['tmp_name']) : '') ?: 'application/octet-stream';
    $j = ad_script(['op' => 'agentdocupload', 'agent_id' => $agentId, 'name' => $title, 'mime' => $mime, 'data' => base64_encode((string)file_get_contents($file['tmp_name']))], true);
    if (!$j['ok']) return $j;
    flr_audit(flr_akey($r['email']), 'agent_doc_upload', $agentId, 'Uploaded to Agreements folder: ' . $title);
    return ['ok' => true, 'name' => $title, 'url' => (string)($j['url'] ?? '')];
}
