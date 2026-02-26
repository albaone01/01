<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/csrf.php';

header('Content-Type: application/json');

// Guard
requireLogin();
requireDevice();

$tokoId = $_SESSION['toko_id'] ?? 0;
if (!$tokoId) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Toko tidak ditemukan di sesi']));
}

$q = trim($_GET['q'] ?? '');
$limit = 20;

// Jika kosong, kembalikan sedikit pelanggan
if ($q === '') {
    $stmt = $pos_db->prepare("SELECT p.pelanggan_id, p.nama_pelanggan, p.telepon,
                                     COALESCE(pt.poin,0) AS saldo_poin,
                                     COALESCE(pb.total_belanja_bulan,0) AS total_belanja_bulan,
                                     COALESCE(p.flat_diskon,0) AS flat_diskon,
                                     COALESCE(ml.diskon_persen,0) AS level_diskon_persen,
                                     COALESCE(ml.nama_level,'') AS level_nama
                              FROM pelanggan p
                              LEFT JOIN pelanggan_toko pt ON pt.pelanggan_id = p.pelanggan_id AND pt.toko_id = p.toko_id AND pt.deleted_at IS NULL
                              LEFT JOIN member_level ml ON ml.level_id = pt.level_id AND ml.toko_id = p.toko_id AND ml.deleted_at IS NULL
                              LEFT JOIN (
                                  SELECT pelanggan_id, toko_id, SUM(total_akhir) AS total_belanja_bulan
                                  FROM penjualan
                                  WHERE pelanggan_id IS NOT NULL
                                    AND DATE_FORMAT(dibuat_pada, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')
                                  GROUP BY pelanggan_id, toko_id
                              ) pb ON pb.pelanggan_id = p.pelanggan_id AND pb.toko_id = p.toko_id
                              WHERE p.toko_id = ? AND p.deleted_at IS NULL
                              ORDER BY p.nama_pelanggan
                              LIMIT ?");
    $stmt->bind_param('ii', $tokoId, $limit);
} else {
    $like = "%{$q}%";
    $stmt = $pos_db->prepare("SELECT p.pelanggan_id, p.nama_pelanggan, p.telepon,
                                     COALESCE(pt.poin,0) AS saldo_poin,
                                     COALESCE(pb.total_belanja_bulan,0) AS total_belanja_bulan,
                                     COALESCE(p.flat_diskon,0) AS flat_diskon,
                                     COALESCE(ml.diskon_persen,0) AS level_diskon_persen,
                                     COALESCE(ml.nama_level,'') AS level_nama
                              FROM pelanggan p
                              LEFT JOIN pelanggan_toko pt ON pt.pelanggan_id = p.pelanggan_id AND pt.toko_id = p.toko_id AND pt.deleted_at IS NULL
                              LEFT JOIN member_level ml ON ml.level_id = pt.level_id AND ml.toko_id = p.toko_id AND ml.deleted_at IS NULL
                              LEFT JOIN (
                                  SELECT pelanggan_id, toko_id, SUM(total_akhir) AS total_belanja_bulan
                                  FROM penjualan
                                  WHERE pelanggan_id IS NOT NULL
                                    AND DATE_FORMAT(dibuat_pada, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')
                                  GROUP BY pelanggan_id, toko_id
                              ) pb ON pb.pelanggan_id = p.pelanggan_id AND pb.toko_id = p.toko_id
                              WHERE p.toko_id = ? AND p.deleted_at IS NULL
                                AND (
                                    p.nama_pelanggan LIKE ? OR p.telepon LIKE ?
                                )
                              ORDER BY p.nama_pelanggan
                              LIMIT ?");
    $stmt->bind_param('issi', $tokoId, $like, $like, $limit);
}

$stmt->execute();
$res = $stmt->get_result();
$data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

echo json_encode(['ok' => true, 'data' => $data]);
