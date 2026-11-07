<?php
// The Home page bell's count, including the slower checks of transaction-record details in Drive. GET -> {ok, count}.
require __DIR__ . "/guard.php";
require_once __DIR__ . "/notiflib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$n = 0; try { $n = nt_count(nt_sections(strtolower($_SESSION['email'] ?? ''), flr_is_broker(), true)); } catch (Throwable $e) {}
echo json_encode(['ok' => true, 'count' => $n]);
