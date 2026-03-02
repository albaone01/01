<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/lokasi_rak.php';
header('Content-Type: application/json');

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
if ($tokoId <= 0) exit(json_encode(['ok' => false, 'msg' => 'Sesi toko tidak valid']));
if ($id <= 0) exit(json_encode(['ok' => false, 'msg' => 'ID kosong']));

try {
    lokasi_rak_ensure_schema($pos_db);
    $stmt = $pos_db->prepare("
        SELECT lokasi_id, gudang_id, kode_lokasi, nama_lokasi, zona, lorong, level_rak, bin,
               kapasitas, aktif, catatan
        FROM lokasi_rak
        WHERE lokasi_id=? AND toko_id=? AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bind_param("ii", $id, $tokoId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) exit(json_encode(['ok' => false, 'msg' => 'Lokasi tidak ditemukan']));
    echo json_encode(['ok' => true, 'data' => $row]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
