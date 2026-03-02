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
$deviceId = (string)($_SESSION['device_id'] ?? '');
$penjualanId = (int)($_POST['penjualan_id'] ?? 0);
$autoPrint = (int)($_POST['auto_print'] ?? 1) === 1;
$openDrawer = (int)($_POST['open_drawer'] ?? 0) === 1;

if ($tokoId <= 0 || $penjualanId <= 0) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Parameter tidak valid']));
}
if (!$deviceId) {
    exit(json_encode(['ok' => false, 'msg' => 'device_id tidak ada di sesi']));
}

$stPrinter = $pos_db->prepare("SELECT nama, alamat, lebar, driver FROM printer_setting WHERE device_id=? ORDER BY is_default DESC, id ASC LIMIT 1");
$stPrinter->bind_param('s', $deviceId);
$stPrinter->execute();
$printer = $stPrinter->get_result()->fetch_assoc();
$stPrinter->close();
if (!$printer) {
    exit(json_encode(['ok' => false, 'msg' => 'Printer default belum diatur untuk device ini']));
}

$stHead = $pos_db->prepare("
    SELECT p.penjualan_id, p.nomor_invoice, p.dibuat_pada, p.subtotal, p.diskon, p.total_akhir,
           COALESCE(u.nama,'Kasir') AS kasir
    FROM penjualan p
    LEFT JOIN pengguna u ON u.pengguna_id = p.kasir_id
    WHERE p.penjualan_id=? AND p.toko_id=?
    LIMIT 1
");
$stHead->bind_param('ii', $penjualanId, $tokoId);
$stHead->execute();
$head = $stHead->get_result()->fetch_assoc();
$stHead->close();
if (!$head) {
    exit(json_encode(['ok' => false, 'msg' => 'Transaksi tidak ditemukan']));
}

$stStore = $pos_db->prepare("SELECT nama_toko, alamat, telepon FROM toko WHERE toko_id=? LIMIT 1");
$stStore->bind_param('i', $tokoId);
$stStore->execute();
$store = $stStore->get_result()->fetch_assoc();
$stStore->close();

$stDetail = $pos_db->prepare("
    SELECT d.qty, d.harga_jual, d.subtotal, COALESCE(pr.nama_produk,'Produk') AS nama_produk
    FROM penjualan_detail d
    LEFT JOIN produk pr ON pr.produk_id = d.produk_id
    WHERE d.penjualan_id=?
    ORDER BY d.detail_id ASC
");
$stDetail->bind_param('i', $penjualanId);
$stDetail->execute();
$details = $stDetail->get_result()->fetch_all(MYSQLI_ASSOC);
$stDetail->close();

$stPay = $pos_db->prepare("
    SELECT metode, jumlah, COALESCE(uang_diterima, jumlah) AS uang_diterima, COALESCE(kembalian,0) AS kembalian
    FROM pembayaran
    WHERE penjualan_id=?
    ORDER BY pembayaran_id DESC
    LIMIT 1
");
$stPay->bind_param('i', $penjualanId);
$stPay->execute();
$pay = $stPay->get_result()->fetch_assoc();
$stPay->close();
$metode = strtolower((string)($pay['metode'] ?? 'cash'));

if (!$autoPrint && !$openDrawer) {
    echo json_encode(['ok' => true, 'msg' => 'Tidak ada aksi print/drawer']);
    exit;
}

function rp(float $n): string {
    return number_format($n, 0, ',', '.');
}

$lines = [];
$storeName = trim((string)($store['nama_toko'] ?? 'TOKO'));
$storeAddr = trim((string)($store['alamat'] ?? ''));
$storeTel = trim((string)($store['telepon'] ?? ''));
$lines[] = $storeName;
if ($storeAddr !== '') $lines[] = $storeAddr;
if ($storeTel !== '') $lines[] = 'Telp: ' . $storeTel;
$lines[] = str_repeat('-', 32);
$lines[] = 'No: ' . (string)$head['nomor_invoice'];
$lines[] = 'Kasir: ' . (string)$head['kasir'];
$lines[] = 'Waktu: ' . date('d/m/Y H:i', strtotime((string)$head['dibuat_pada']));
$lines[] = str_repeat('-', 32);
foreach ($details as $d) {
    $nama = mb_substr((string)$d['nama_produk'], 0, 28);
    $qty = (int)$d['qty'];
    $harga = (float)$d['harga_jual'];
    $sub = (float)$d['subtotal'];
    $lines[] = $nama;
    $lines[] = $qty . ' x ' . rp($harga) . ' = ' . rp($sub);
}
$lines[] = str_repeat('-', 32);
$lines[] = 'Subtotal : Rp ' . rp((float)$head['subtotal']);
$lines[] = 'Diskon   : Rp ' . rp((float)$head['diskon']);
$lines[] = 'Total    : Rp ' . rp((float)$head['total_akhir']);
if ($pay) {
    $lines[] = 'Metode   : ' . strtoupper($metode);
    $lines[] = 'Bayar    : Rp ' . rp((float)$pay['uang_diterima']);
    $lines[] = 'Kembali  : Rp ' . rp((float)$pay['kembalian']);
}
$lines[] = str_repeat('-', 32);
$lines[] = 'Terima kasih';
$lines[] = '';
$lines[] = '';
$text = implode("\n", $lines);

$needDrawerKick = $openDrawer && $metode === 'cash';
$payload = [
    'cmd' => 'test_print',
    'text' => $text,
    'printer' => (string)$printer['alamat'],
    'driver' => (string)$printer['driver'],
    'width' => (int)$printer['lebar'],
    'cut' => true,
    'open_drawer' => $needDrawerKick,
];

$ch = curl_init("http://localhost:19100/print");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 8,
]);
$resp = curl_exec($ch);
$err = curl_error($ch);
$http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($err || $http >= 400) {
    http_response_code(502);
    exit(json_encode(['ok' => false, 'msg' => 'Gagal kirim ke agent print: ' . ($err ?: $resp)]));
}

$agent = json_decode((string)$resp, true);
echo json_encode([
    'ok' => true,
    'msg' => 'Print/drawer diproses',
    'drawer_kicked' => $needDrawerKick,
    'agent' => is_array($agent) ? $agent : $resp,
]);
