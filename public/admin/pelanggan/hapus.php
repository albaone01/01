<?php
require_once __DIR__ . '/../../../inc/config.php';
require_once __DIR__ . '/../../../inc/db.php';
require_once __DIR__ . '/../../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = (int)($_SESSION['toko_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);

if ($id > 0 && $toko_id > 0) {
    $stmt = $pos_db->prepare("UPDATE pelanggan SET deleted_at = NOW() WHERE pelanggan_id = ? AND toko_id = ? AND deleted_at IS NULL");
    $stmt->bind_param("ii", $id, $toko_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: index.php");
exit;
