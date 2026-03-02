<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';

header('Content-Type: application/json');

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$promoId = (int)($_GET['id'] ?? 0);
if ($tokoId <= 0 || $promoId <= 0) {
    exit(json_encode(['ok' => false, 'msg' => 'Parameter tidak valid']));
}

$stmt = $pos_db->prepare("
    SELECT promo_id, nama_promo, tipe, nilai, minimal_belanja, berlaku_dari, berlaku_sampai, aktif
    FROM promo
    WHERE promo_id=? AND toko_id=? AND deleted_at IS NULL
    LIMIT 1
");
$stmt->bind_param('ii', $promoId, $tokoId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    exit(json_encode(['ok' => false, 'msg' => 'Promo tidak ditemukan']));
}

echo json_encode(['ok' => true, 'data' => $row]);
