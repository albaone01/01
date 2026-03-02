<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/lokasi_rak.php';
header('Content-Type: application/json');

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$id = (int)($_POST['id'] ?? 0);
if ($tokoId <= 0) exit(json_encode(['ok' => false, 'msg' => 'Sesi toko tidak valid']));
if ($id <= 0) exit(json_encode(['ok' => false, 'msg' => 'ID kosong']));

try {
    lokasi_rak_ensure_schema($pos_db);
    $stmt = $pos_db->prepare("UPDATE produk_lokasi_rak SET deleted_at=NOW() WHERE id=? AND toko_id=? AND deleted_at IS NULL");
    $stmt->bind_param("ii", $id, $tokoId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true, 'msg' => 'Penempatan produk dihapus']);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
