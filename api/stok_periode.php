<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/stok_report.php';

header('Content-Type: application/json');

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if (!$tokoId) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Sesi toko tidak valid']));
}

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$gudangId = (int)($_GET['gudang_id'] ?? 0);
$produkId = (int)($_GET['produk_id'] ?? 0);

try {
    $result = get_stok_period_report($pos_db, $tokoId, $from, $to, $gudangId, $produkId);
    echo json_encode(['ok' => true] + $result);
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
