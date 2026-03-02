<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';

header('Content-Type: application/json');

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$promoId = (int)($_POST['id'] ?? 0);
if ($tokoId <= 0 || $promoId <= 0) {
    exit(json_encode(['ok' => false, 'msg' => 'Parameter tidak valid']));
}

$stmt = $pos_db->prepare("UPDATE promo SET deleted_at=NOW(), aktif=0 WHERE promo_id=? AND toko_id=?");
$stmt->bind_param('ii', $promoId, $tokoId);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true, 'msg' => 'Promo dihapus']);
