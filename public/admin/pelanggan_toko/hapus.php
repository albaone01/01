<?php
require_once __DIR__ . '/../../../inc/config.php';
require_once __DIR__ . '/../../../inc/db.php';
require_once __DIR__ . '/../../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = (int)($_SESSION['toko_id'] ?? 0);
$pelanggan_id = (int)($_GET['pelanggan_id'] ?? 0);

if ($toko_id > 0 && $pelanggan_id > 0) {
    $stmt = $pos_db->prepare("UPDATE pelanggan_toko SET deleted_at = NOW() WHERE pelanggan_id = ? AND toko_id = ?");
    $stmt->bind_param("ii", $pelanggan_id, $toko_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: index.php");
exit;
