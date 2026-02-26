<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/stok_report.php';
require_once '../inc/xlsx_helper.php';

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if (!$tokoId) {
    http_response_code(400);
    exit('Sesi toko tidak valid');
}

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$gudangId = (int)($_GET['gudang_id'] ?? 0);
$produkId = (int)($_GET['produk_id'] ?? 0);

try {
    $report = get_stok_period_report($pos_db, $tokoId, $from, $to, $gudangId, $produkId);
    $rows = [];
    foreach ($report['data'] as $r) {
        $rows[] = [
            $r['nama_produk'],
            (int)$r['stok_awal'],
            (int)$r['masuk'],
            (int)$r['keluar'],
            (int)$r['stok_akhir'],
        ];
    }
    $rows[] = ['TOTAL', (int)$report['summary']['stok_awal'], (int)$report['summary']['masuk'], (int)$report['summary']['keluar'], (int)$report['summary']['stok_akhir']];
    $name = 'laporan_stok_periode_' . $from . '_' . $to . '.xlsx';
    xlsx_output(['Produk', 'Stok Awal', 'Masuk', 'Keluar', 'Stok Akhir'], $rows, $name, 'Laporan Stok');
} catch (Exception $e) {
    http_response_code(422);
    echo $e->getMessage();
}
