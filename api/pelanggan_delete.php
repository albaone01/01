<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

header('Content-Type: application/json');

$toko_id = (int)($_SESSION['toko_id'] ?? 0);
$id = (int)($_POST['id'] ?? 0);

if (!$id || !$toko_id) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
    exit;
}

// Soft delete pelanggan
$stmt = $pos_db->prepare("UPDATE pelanggan SET deleted_at = NOW() WHERE pelanggan_id = ? AND toko_id = ?");
$stmt->bind_param("ii", $id, $toko_id);
$stmt->execute();
$stmt->close();

// Also soft delete from pelanggan_toko
$stmt2 = $pos_db->prepare("UPDATE pelanggan_toko SET deleted_at = NOW() WHERE pelanggan_id = ? AND toko_id = ?");
$stmt2->bind_param("ii", $id, $toko_id);
$stmt2->execute();
$stmt2->close();

echo json_encode(['ok' => true]);
