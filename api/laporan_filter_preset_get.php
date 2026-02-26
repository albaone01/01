<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';
header('Content-Type: application/json');

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$userId = (int)($_SESSION['pengguna_id'] ?? $_SESSION['penguna_id'] ?? 0);
$kunci = trim((string)($_GET['kunci'] ?? 'laporan_stok_periode'));
if (!$tokoId || !$userId) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Sesi tidak lengkap']));
}

$pos_db->query("CREATE TABLE IF NOT EXISTS user_filter_preset (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    toko_id BIGINT NOT NULL,
    pengguna_id BIGINT NOT NULL,
    kunci VARCHAR(100) NOT NULL,
    nilai TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_filter_user (toko_id,pengguna_id,kunci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $pos_db->prepare("SELECT nilai FROM user_filter_preset WHERE toko_id=? AND pengguna_id=? AND kunci=? LIMIT 1");
$stmt->bind_param("iis", $tokoId, $userId, $kunci);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$nilai = [];
if ($row && isset($row['nilai']) && $row['nilai'] !== null && $row['nilai'] !== '') {
    $json = json_decode((string)$row['nilai'], true);
    if (is_array($json)) $nilai = $json;
}

echo json_encode(['ok' => true, 'kunci' => $kunci, 'nilai' => $nilai]);
