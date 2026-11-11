<?php
// Card pictures for the Learn page (desktop). GET ?id=N returns the saved first-page picture of a presentation or playbook (404 if none yet).
// POST id, csrf, img (JPEG data URL) saves one, made in the signed-in person's browser from the PDF's first page. Saved by PDF file name,
// so a replaced PDF gets a fresh picture. Videos use YouTube's own picture and do not come here.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/learnlib.php";
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
function ln_thumb_path(array $it) { return $it['file'] === '' ? '' : ln_dir() . '/thumbs/' . preg_replace('/\.pdf$/', '', basename($it['file'])) . '.jpg'; }
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$it = ln_item((int)($post ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0)));
if (!$it || $it['kind'] === 'video') { http_response_code(404); exit; }
if ($post) {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'This page expired. Please reload and try again.']); exit; }
    $path = ln_thumb_path($it); if ($path === '') { echo json_encode(['ok' => false, 'msg' => 'The PDF is not on the server yet.']); exit; }
    $d = (string)($_POST['img'] ?? ''); if (!preg_match('~^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$~', $d, $m)) { echo json_encode(['ok' => false, 'msg' => 'Not a JPEG picture.']); exit; }
    $bin = base64_decode($m[1], true); if ($bin === false || strncmp($bin, "\xFF\xD8\xFF", 3) !== 0 || strlen($bin) > 400000) { echo json_encode(['ok' => false, 'msg' => 'That picture is not valid or is too large.']); exit; }
    @mkdir(dirname($path), 0750, true); echo json_encode(file_put_contents($path, $bin) === false ? ['ok' => false, 'msg' => 'Could not save the picture.'] : ['ok' => true]); exit;
}
$path = ln_thumb_path($it);
if ($path === '' || !is_file($path)) { http_response_code(404); exit; }
while (ob_get_level()) @ob_end_clean();
header('Content-Type: image/jpeg'); header('Content-Length: ' . filesize($path)); header('Cache-Control: private, max-age=86400');
readfile($path);
