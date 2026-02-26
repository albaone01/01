<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
$toko_id = (int)($_SESSION['toko_id'] ?? 0);

if (!$id || !$toko_id) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
    exit;
}

$stmt = $pos_db->prepare("
    SELECT p.pelanggan_id, p.kode_pelanggan, p.nama_pelanggan, p.telepon, p.alamat, 
           p.jenis_customer, p.flat_diskon, p.dibuat_pada,
           pt.level_id, pt.poin, pt.limit_kredit,
           pt.tanggal_daftar, pt.masa_berlaku, pt.exp, pt.masa_tenggang, 
           pt.exp_poin, pt.poin_awal, pt.poin_akhir
    FROM pelanggan p
    LEFT JOIN pelanggan_toko pt ON pt.pelanggan_id = p.pelanggan_id AND pt.toko_id = p.toko_id AND pt.deleted_at IS NULL
    WHERE p.pelanggan_id = ? AND p.toko_id = ? AND p.deleted_at IS NULL
    LIMIT 1
");
$stmt->bind_param("ii", $id, $toko_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    echo json_encode(['ok' => false, 'msg' => 'Pelanggan tidak ditemukan']);
    exit;
}

echo json_encode(['ok' => true, 'data' => $data]);
