<?php
// Executed documents for one of the signed-in agent's client records, with their Google Drive references. JSON.
// GET docs_api.php?tx=<transaction folder id> -> {ok, tx, name, count, waiting, folder_url, groups:[{key,label,items:[{id,title,signed_on,source,drive_url,folder,download_url,note}]}]}
require __DIR__ . "/guard.php";
require_once __DIR__ . "/doclib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
echo json_encode(dc_list($_SESSION['email'] ?? '', (string)($_GET['tx'] ?? '')));
