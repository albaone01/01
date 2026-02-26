<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');

$tokoId = $_SESSION['toko_id'] ?? 0;

$pos_db->query("CREATE TABLE IF NOT EXISTS hutang_supplier (
    hutang_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    toko_id BIGINT NOT NULL DEFAULT 0,
    supplier_id BIGINT NOT NULL DEFAULT 0,
    supplier VARCHAR(150) NOT NULL,
    invoice VARCHAR(80) NOT NULL,
    sisa DECIMAL(15,2) NOT NULL DEFAULT 0,
    due_date DATE DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'tercatat',
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_toko (toko_id),
    KEY idx_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Safeguard jika tabel lama belum punya toko_id
try{
    $c = $pos_db->query("SHOW COLUMNS FROM hutang_supplier LIKE 'toko_id'");
    if(!$c || $c->num_rows==0){
        $pos_db->query("ALTER TABLE hutang_supplier ADD COLUMN toko_id BIGINT NOT NULL DEFAULT 0 AFTER hutang_id, ADD KEY idx_toko (toko_id)");
    }
}catch(Exception $e){}
// Safeguard supplier_id
try{
    $c = $pos_db->query("SHOW COLUMNS FROM hutang_supplier LIKE 'supplier_id'");
    if(!$c || $c->num_rows==0){
        $pos_db->query("ALTER TABLE hutang_supplier ADD COLUMN supplier_id BIGINT NOT NULL DEFAULT 0 AFTER toko_id, ADD KEY idx_supplier (supplier_id)");
    }
}catch(Exception $e){}

$supplierId = (int)($_POST['supplier_id'] ?? 0);
$supplier = trim($_POST['supplier'] ?? '');
$invoice  = trim($_POST['invoice'] ?? '');
$sisa     = (float)($_POST['sisa'] ?? 0);
$due      = $_POST['due_date'] ?? null;
$status   = trim($_POST['status'] ?? 'tercatat');

if($supplierId<=0 || $supplier=='' || $invoice=='') exit(json_encode(['ok'=>false,'msg'=>'Supplier, invoice wajib diisi']));

$stmt = $pos_db->prepare("INSERT INTO hutang_supplier (toko_id, supplier_id, supplier, invoice, sisa, due_date, status) VALUES (?,?,?,?,?,?,?)");
$stmt->bind_param("iissdds", $tokoId, $supplierId, $supplier, $invoice, $sisa, $due, $status);
$stmt->execute();
$stmt->close();

echo json_encode(['ok'=>true,'msg'=>'Hutang tersimpan']);
