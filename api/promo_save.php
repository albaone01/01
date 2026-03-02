<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';

header('Content-Type: application/json');

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if ($tokoId <= 0) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Sesi toko tidak valid']));
}

try {
    $pos_db->query("
        CREATE TABLE IF NOT EXISTS promo (
            promo_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            toko_id BIGINT NOT NULL,
            nama_promo VARCHAR(100) NOT NULL,
            tipe ENUM('persen','nominal','gratis') NOT NULL,
            nilai DECIMAL(15,2) NOT NULL DEFAULT 0,
            minimal_belanja DECIMAL(15,2) DEFAULT 0,
            berlaku_dari DATETIME NOT NULL,
            berlaku_sampai DATETIME NOT NULL,
            aktif TINYINT(1) DEFAULT 1,
            dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            KEY idx_promo_toko (toko_id),
            KEY idx_promo_active (toko_id, aktif, berlaku_dari, berlaku_sampai)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pos_db->query("
        CREATE TABLE IF NOT EXISTS promo_produk (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            promo_id BIGINT NOT NULL,
            produk_id BIGINT NOT NULL,
            UNIQUE KEY uq_promo_produk (promo_id, produk_id),
            KEY idx_pp_produk (produk_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {}

$promoId = (int)($_POST['promo_id'] ?? 0);
$nama = trim((string)($_POST['nama_promo'] ?? ''));
$tipe = strtolower(trim((string)($_POST['tipe'] ?? 'persen')));
$nilai = (float)($_POST['nilai'] ?? 0);
$minimal = max(0, (float)($_POST['minimal_belanja'] ?? 0));
$dari = trim((string)($_POST['berlaku_dari'] ?? ''));
$sampai = trim((string)($_POST['berlaku_sampai'] ?? ''));
$aktif = (int)($_POST['aktif'] ?? 1) === 1 ? 1 : 0;

if ($nama === '') {
    exit(json_encode(['ok' => false, 'msg' => 'Nama promo wajib diisi']));
}
if (!in_array($tipe, ['persen', 'nominal', 'gratis'], true)) {
    exit(json_encode(['ok' => false, 'msg' => 'Tipe promo tidak valid']));
}
if ($tipe !== 'gratis' && $nilai <= 0) {
    exit(json_encode(['ok' => false, 'msg' => 'Nilai promo wajib lebih dari 0']));
}

$dtDari = date_create($dari);
$dtSampai = date_create($sampai);
if (!$dtDari || !$dtSampai) {
    exit(json_encode(['ok' => false, 'msg' => 'Periode promo tidak valid']));
}
$dariDb = $dtDari->format('Y-m-d H:i:s');
$sampaiDb = $dtSampai->format('Y-m-d H:i:s');
if (strtotime($sampaiDb) < strtotime($dariDb)) {
    exit(json_encode(['ok' => false, 'msg' => 'Tanggal selesai harus >= tanggal mulai']));
}

try {
    if ($promoId > 0) {
        $stmt = $pos_db->prepare("
            UPDATE promo
            SET nama_promo=?, tipe=?, nilai=?, minimal_belanja=?, berlaku_dari=?, berlaku_sampai=?, aktif=?
            WHERE promo_id=? AND toko_id=? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ssddssiii', $nama, $tipe, $nilai, $minimal, $dariDb, $sampaiDb, $aktif, $promoId, $tokoId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $pos_db->prepare("
            INSERT INTO promo (toko_id, nama_promo, tipe, nilai, minimal_belanja, berlaku_dari, berlaku_sampai, aktif)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issddssi', $tokoId, $nama, $tipe, $nilai, $minimal, $dariDb, $sampaiDb, $aktif);
        $stmt->execute();
        $promoId = (int)$pos_db->insert_id;
        $stmt->close();
    }
    echo json_encode(['ok' => true, 'msg' => 'Promo tersimpan', 'promo_id' => $promoId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
