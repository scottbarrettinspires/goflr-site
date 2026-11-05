<?php
// Called by the app when a push arrives: returns the newest alert for the phone's agent. The phone's push address is the key.
require __DIR__ . "/pushlib.php";
header('Content-Type: application/json'); header('Cache-Control: no-store');
$h = (string)($_GET['h'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $h)) { echo json_encode(['ok' => false]); exit; }
$ix = json_decode((string)@file_get_contents(ld_dir() . '/pushidx/' . $h . '.json'), true);
if (!is_array($ix) || !preg_match('/^[a-f0-9]{40}$/', (string)($ix['agent'] ?? ''))) { echo json_encode(['ok' => false]); exit; }
$al = (array)json_decode((string)@file_get_contents(ld_dir() . '/alerts-' . $ix['agent'] . '.json'), true);
$al = array_values(array_filter($al, function ($a) { return ($a['t'] ?? 0) > time() - 86400; }));
echo json_encode(['ok' => true, 'alert' => $al ? end($al) : null]);
