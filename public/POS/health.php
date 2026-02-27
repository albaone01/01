<?php
// Lightweight health endpoint for LAN monitoring / kiosk pre-check.
require_once '../../inc/config.php';
require_once '../../inc/db.php';

header('Content-Type: application/json; charset=utf-8');

$status = [
    'ok' => true,
    'app' => 'ritel4-pos',
    'time' => date('c'),
    'db' => 'ok',
];

try {
    $st = $pos_db->prepare("SELECT 1 AS v");
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ((int)($row['v'] ?? 0) !== 1) {
        $status['ok'] = false;
        $status['db'] = 'unexpected';
    }
} catch (Throwable $e) {
    http_response_code(500);
    $status['ok'] = false;
    $status['db'] = 'error';
    $status['error'] = $e->getMessage();
}

echo json_encode($status, JSON_UNESCAPED_SLASHES);

