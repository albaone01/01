<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');

$tokoId = $_SESSION['toko_id'] ?? 3;

$supplier = (int)($_POST['supplier_id'] ?? 0);
$gudang   = (int)($_POST['gudang_id'] ?? 1);
$nomor    = trim($_POST['nomor_faktur'] ?? '');
$tanggal  = $_POST['tanggal'] ?? date('Y-m-d');
$jatuh    = $_POST['jatuh_tempo'] ?? null;
$subtotal = (float)($_POST['subtotal'] ?? 0);
$pajak    = (float)($_POST['pajak'] ?? 0);
$diskon   = (float)($_POST['diskon'] ?? 0);
$ongkir   = (float)($_POST['ongkir'] ?? 0);
$total    = (float)($_POST['total'] ?? 0);
$catatan  = trim($_POST['catatan'] ?? '');
$tipe     = $_POST['tipe_faktur'] === 'tempo' ? 'tempo' : 'cash';
$sales    = trim($_POST['salesman'] ?? '');
$status   = trim($_POST['status'] ?? 'draft');

if(!$supplier || $total <= 0 || $nomor==='') exit(json_encode(['ok'=>false,'msg'=>'Supplier, nomor faktur, dan total wajib']));

$pos_db->query("CREATE TABLE IF NOT EXISTS pembelian (
    pembelian_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT NOT NULL,
    toko_id BIGINT NOT NULL,
    gudang_id BIGINT NOT NULL,
    nomor_faktur VARCHAR(80) NOT NULL,
    tanggal DATE NOT NULL,
    jatuh_tempo DATE DEFAULT NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    pajak DECIMAL(15,2) NOT NULL DEFAULT 0,
    diskon DECIMAL(15,2) NOT NULL DEFAULT 0,
    ongkir DECIMAL(15,2) NOT NULL DEFAULT 0,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    catatan VARCHAR(255) DEFAULT NULL,
    tipe_faktur ENUM('cash','tempo') NOT NULL DEFAULT 'cash',
    salesman VARCHAR(100) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// alter safeguard
$cols = [
    "ADD COLUMN IF NOT EXISTS tipe_faktur ENUM('cash','tempo') NOT NULL DEFAULT 'cash'",
    "ADD COLUMN IF NOT EXISTS salesman VARCHAR(100) DEFAULT NULL"
];
foreach($cols as $c){ try{ $pos_db->query("ALTER TABLE pembelian $c"); }catch(Exception $e){} }

$stmt = $pos_db->prepare("INSERT INTO pembelian (supplier_id,toko_id,gudang_id,nomor_faktur,tanggal,jatuh_tempo,subtotal,pajak,diskon,ongkir,total,catatan,tipe_faktur,salesman,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$stmt->bind_param("iiisssddddddsss", $supplier, $tokoId, $gudang, $nomor, $tanggal, $jatuh, $subtotal, $pajak, $diskon, $ongkir, $total, $catatan, $tipe, $sales, $status);
$stmt->execute();
$stmt->close();

echo json_encode(['ok'=>true,'msg'=>'Pembelian ditambahkan']);
