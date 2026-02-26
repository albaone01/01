<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/csrf.php';
header('Content-Type: application/json');

requireLogin();
requireDevice();
csrf_protect_json();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$userId = (int)($_SESSION['pengguna_id'] ?? $_SESSION['penguna_id'] ?? 0);
if (!$tokoId || !$userId) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Sesi tidak lengkap']));
}

$kunci = trim((string)($_POST['kunci'] ?? 'laporan_stok_periode'));
$nilaiRaw = (string)($_POST['nilai'] ?? '{}');
if ($kunci === '') {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'msg' => 'Kunci preset wajib']));
}
if (strlen($nilaiRaw) > 10000) {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'msg' => 'Nilai preset terlalu besar']));
}
$decoded = json_decode($nilaiRaw, true);
if (!is_array($decoded)) {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'msg' => 'Nilai preset harus JSON object']));
}
$nilai = json_encode($decoded, JSON_UNESCAPED_UNICODE);

$pos_db->query("CREATE TABLE IF NOT EXISTS user_filter_preset (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    toko_id BIGINT NOT NULL,
    pengguna_id BIGINT NOT NULL,
    kunci VARCHAR(100) NOT NULL,
    nilai TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_filter_user (toko_id,pengguna_id,kunci)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $pos_db->prepare("INSERT INTO user_filter_preset (toko_id,pengguna_id,kunci,nilai) VALUES (?,?,?,?)
                          ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)");
$stmt->bind_param("iiss", $tokoId, $userId, $kunci, $nilai);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true]);
