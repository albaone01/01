<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');

$tokoId = $_SESSION['toko_id'] ?? 0;

$pos_db->query("CREATE TABLE IF NOT EXISTS pembayaran_hutang (
    bayar_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    toko_id BIGINT NOT NULL DEFAULT 0,
    supplier_id BIGINT NOT NULL DEFAULT 0,
    hutang_id BIGINT NULL,
    supplier VARCHAR(150) NOT NULL,
    referensi VARCHAR(120) DEFAULT NULL,
    jumlah DECIMAL(15,2) NOT NULL,
    kelebihan DECIMAL(15,2) NOT NULL DEFAULT 0,
    catatan VARCHAR(255) DEFAULT NULL,
    dibayar_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_toko (toko_id),
    KEY idx_supplier (supplier_id),
    KEY idx_hutang (hutang_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try{
    $c = $pos_db->query("SHOW COLUMNS FROM pembayaran_hutang LIKE 'toko_id'");
    if(!$c || $c->num_rows==0){
        $pos_db->query("ALTER TABLE pembayaran_hutang ADD COLUMN toko_id BIGINT NOT NULL DEFAULT 0 AFTER bayar_id, ADD KEY idx_toko (toko_id)");
    }
}catch(Exception $e){}
try{
    $c = $pos_db->query("SHOW COLUMNS FROM pembayaran_hutang LIKE 'supplier_id'");
    if(!$c || $c->num_rows==0){
        $pos_db->query("ALTER TABLE pembayaran_hutang ADD COLUMN supplier_id BIGINT NOT NULL DEFAULT 0 AFTER toko_id, ADD KEY idx_supplier (supplier_id)");
    }
}catch(Exception $e){}
try{
    $c = $pos_db->query("SHOW COLUMNS FROM pembayaran_hutang LIKE 'hutang_id'");
    if(!$c || $c->num_rows==0){
        $pos_db->query("ALTER TABLE pembayaran_hutang ADD COLUMN hutang_id BIGINT NULL AFTER supplier_id, ADD KEY idx_hutang (hutang_id)");
    }
}catch(Exception $e){}
try{
    $c = $pos_db->query("SHOW COLUMNS FROM pembayaran_hutang LIKE 'kelebihan'");
    if(!$c || $c->num_rows==0){
        $pos_db->query("ALTER TABLE pembayaran_hutang ADD COLUMN kelebihan DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER jumlah");
    }
}catch(Exception $e){}

$supplierId = (int)($_POST['supplier_id'] ?? 0);
$supplier = trim($_POST['supplier'] ?? '');
$ref      = trim($_POST['referensi'] ?? '');
$jumlah   = (float)($_POST['jumlah'] ?? 0);
$catatan  = trim($_POST['catatan'] ?? '');
$hutangId = (int)($_POST['hutang_id'] ?? 0);

if($supplierId<=0 || $supplier=='' || $jumlah<=0) exit(json_encode(['ok'=>false,'msg'=>'Supplier dan jumlah wajib diisi']));

$kelebihan = 0.0;
if($hutangId > 0){
    $hStmt = $pos_db->prepare("SELECT sisa, invoice FROM hutang_supplier WHERE hutang_id=? AND toko_id=? LIMIT 1");
    $hStmt->bind_param("ii", $hutangId, $tokoId);
    $hStmt->execute();
    $hRes = $hStmt->get_result()->fetch_assoc();
    $hStmt->close();
    if($hRes){
        if(empty($ref)) $ref = $hRes['invoice'];
        $sisa = (float)$hRes['sisa'];
        if($jumlah > $sisa){
            $kelebihan = $jumlah - $sisa;
            $bayarKeHutang = $sisa;
            $newSisa = 0;
            $newStatus = 'lunas';
        } else {
            $bayarKeHutang = $jumlah;
            $newSisa = $sisa - $jumlah;
            $newStatus = $newSisa <= 0 ? 'lunas' : 'tercatat';
        }
        $upd = $pos_db->prepare("UPDATE hutang_supplier SET sisa=?, status=? WHERE hutang_id=? AND toko_id=?");
        $upd->bind_param("dsii", $newSisa, $newStatus, $hutangId, $tokoId);
        $upd->execute();
        $upd->close();
    }
}

$stmt = $pos_db->prepare("INSERT INTO pembayaran_hutang (toko_id, supplier_id, hutang_id, supplier, referensi, jumlah, kelebihan, catatan) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param("iiissdds", $tokoId, $supplierId, $hutangId, $supplier, $ref, $jumlah, $kelebihan, $catatan);
$stmt->execute();
$stmt->close();

echo json_encode(['ok'=>true,'msg'=>'Pembayaran tercatat']);
