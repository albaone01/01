<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/stok_report.php';

header('Content-Type: application/json');

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if (!$tokoId) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Sesi toko tidak valid']));
}

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$gudangId = (int)($_GET['gudang_id'] ?? 0);
$produkId = (int)($_GET['produk_id'] ?? 0);
if ($produkId <= 0) {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'msg' => 'produk_id wajib']));
}

try {
    $base = get_stok_period_report($pos_db, $tokoId, $from, $to, $gudangId, $produkId);
} catch (Exception $e) {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'msg' => $e->getMessage()]));
}

$opening = 0;
$namaProduk = '';
if (!empty($base['data'][0])) {
    $opening = (int)$base['data'][0]['stok_awal'];
    $namaProduk = (string)$base['data'][0]['nama_produk'];
}

$whereGudang = $gudangId > 0 ? " AND m.gudang_id=?" : "";
$sql = "SELECT m.mutasi_id,m.gudang_id,g.nama_gudang,m.produk_id,m.qty,m.stok_sebelum,m.stok_sesudah,m.tipe,m.referensi,m.dibuat_pada
        FROM stok_mutasi m
        LEFT JOIN gudang g ON g.gudang_id=m.gudang_id
        WHERE m.toko_id=? AND m.produk_id=? AND DATE(m.dibuat_pada) BETWEEN ? AND ? $whereGudang
        ORDER BY m.dibuat_pada ASC, m.mutasi_id ASC";
$stmt = $pos_db->prepare($sql);
if ($gudangId > 0) {
    $stmt->bind_param("iissi", $tokoId, $produkId, $from, $to, $gudangId);
} else {
    $stmt->bind_param("iiss", $tokoId, $produkId, $from, $to);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

$dailySql = "SELECT DATE(m.dibuat_pada) AS tanggal,
                    SUM(CASE WHEN m.tipe='masuk' THEN m.qty ELSE 0 END) AS masuk,
                    SUM(CASE WHEN m.tipe='keluar' THEN m.qty ELSE 0 END) AS keluar
             FROM stok_mutasi m
             WHERE m.toko_id=? AND m.produk_id=? AND DATE(m.dibuat_pada) BETWEEN ? AND ?"
             . ($gudangId > 0 ? " AND m.gudang_id=?" : "")
             . " GROUP BY DATE(m.dibuat_pada) ORDER BY tanggal";
$stmt = $pos_db->prepare($dailySql);
if ($gudangId > 0) {
    $stmt->bind_param("iissi", $tokoId, $produkId, $from, $to, $gudangId);
} else {
    $stmt->bind_param("iiss", $tokoId, $produkId, $from, $to);
}
$stmt->execute();
$daily = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'ok' => true,
    'produk_id' => $produkId,
    'nama_produk' => $namaProduk,
    'stok_awal' => $opening,
    'daily' => $daily,
    'mutasi' => $rows
]);
