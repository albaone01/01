<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/inventory.php';
header('Content-Type: application/json');

$tokoId = $_SESSION['toko_id'] ?? 0;
$poId   = (int)($_POST['po_id'] ?? 0);
$supplierId = (int)($_POST['supplier_id'] ?? 0);
$tanggal = $_POST['tanggal_terima'] ?? date('Y-m-d');
$subtotal = (float)($_POST['subtotal'] ?? 0);
$pajak    = (float)($_POST['pajak'] ?? 0);
$total    = (float)($_POST['total'] ?? 0);
$catatan  = trim($_POST['catatan'] ?? '');
$gudangId = (int)($_POST['gudang_id'] ?? ($_SESSION['gudang_id'] ?? 1));

if(!$poId || !$supplierId) exit(json_encode(['ok'=>false,'msg'=>'PO dan supplier wajib diisi']));

// pastikan tabel pembelian & detail ada
$pos_db->query("CREATE TABLE IF NOT EXISTS pembelian (
    pembelian_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT NOT NULL,
    toko_id BIGINT NOT NULL,
    gudang_id BIGINT NOT NULL DEFAULT 1,
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

$pos_db->query("CREATE TABLE IF NOT EXISTS pembelian_detail (
    detail_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    pembelian_id BIGINT NOT NULL,
    produk_id BIGINT NOT NULL,
    nama_barang VARCHAR(200) NOT NULL,
    qty DECIMAL(15,2) NOT NULL DEFAULT 0,
    harga_beli DECIMAL(15,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pembelian (pembelian_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$barang = $_POST['barang'] ?? [];
$qtys   = $_POST['qty'] ?? [];
$harga  = $_POST['harga'] ?? [];
$prodIds = $_POST['produk_id'] ?? [];

if(!$barang || !is_array($barang)) exit(json_encode(['ok'=>false,'msg'=>'Detail barang kosong']));

$nomorFaktur = 'RC-' . date('Ymd') . '-' . substr(str_shuffle('1234567890'),0,4);
$tipeFaktur = 'cash'; // penerimaan default cash; akan diisi dari PO jika ada
$dueDate = null;
$tempoHari = 0;
$supplierNama = null;
// ambil nama supplier
$sName = $pos_db->prepare("SELECT nama_supplier FROM supplier WHERE supplier_id=? LIMIT 1");
$sName->bind_param("i", $supplierId);
$sName->execute();
$sRes = $sName->get_result();
if($sRes && $sRes->num_rows) $supplierNama = $sRes->fetch_assoc()['nama_supplier'];
$sName->close();

$pos_db->begin_transaction();
try{
    ensure_inventory_snapshot_columns($pos_db);
    // Ambil info PO tempo jika ada
    if($poId){
        $poStmt = $pos_db->prepare("SELECT nomor, tipe_faktur, jatuh_tempo, tempo_hari FROM purchase_order WHERE po_id=? LIMIT 1");
        $poStmt->bind_param("i", $poId);
        $poStmt->execute();
        $poInfo = $poStmt->get_result()->fetch_assoc();
        $poStmt->close();
        if($poInfo){
            $nomorFaktur = $poInfo['nomor'] ?? $nomorFaktur;
            $tipeFaktur = $poInfo['tipe_faktur'] ?? 'cash';
            $dueDate = $poInfo['jatuh_tempo'] ?? null;
            $tempoHari = (int)($poInfo['tempo_hari'] ?? 0);
        }
    }

    $stmt = $pos_db->prepare("INSERT INTO pembelian (supplier_id,toko_id,gudang_id,nomor_faktur,tanggal,jatuh_tempo,tempo_hari,subtotal,pajak,diskon,ongkir,total,catatan,tipe_faktur,status,po_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $zeroD=0.0; $statusVal='posted';
    $types = "iiisssidddddsssi";
    $stmt->bind_param($types, $supplierId,$tokoId,$gudangId,$nomorFaktur,$tanggal,$dueDate,$tempoHari,$subtotal,$pajak,$zeroD,$zeroD,$total,$catatan,$tipeFaktur,$statusVal,$poId);
    $stmt->execute();
    $pembelianId = $pos_db->insertId();
    $stmt->close();

$stmtD = $pos_db->prepare("INSERT INTO pembelian_detail (pembelian_id,produk_id,nama_barang,qty,harga_beli,subtotal) VALUES (?,?,?,?,?,?)");
foreach($barang as $i=>$nm){
    $nm = trim($nm);
    if($nm==='') continue;
    $q = (float)($qtys[$i] ?? 0);
    $qInt = (int)round($q);
    if ($q > 0 && abs($q - $qInt) > 0.0001) {
        throw new Exception('Qty penerimaan harus bilangan bulat');
    }
    $h = (float)($harga[$i] ?? 0);
    $sub = $q*$h;
    $prodId = (int)($prodIds[$i] ?? 0);
    $stmtD->bind_param("iisddd", $pembelianId, $prodId, $nm, $q, $h, $sub);
    $stmtD->execute();

    if ($prodId > 0 && $qInt > 0) {
        $ref = $nomorFaktur;
        $stock = apply_stock_mutation($pos_db, $tokoId, $gudangId, $prodId, $qInt, 'masuk', $ref);
        update_produk_hpp_on_masuk($pos_db, $prodId, (int)$stock['stok_sebelum'], $qInt, $h);
    }
}
    $stmtD->close();

    // jika tempo, buat entri hutang supplier
    if($tipeFaktur === 'tempo'){
        $due = $dueDate ?: $tanggal;
        $statusH = (strtotime($due) < strtotime(date('Y-m-d'))) ? 'jatuh tempo' : 'tercatat';
        $hs = $pos_db->prepare("INSERT INTO hutang_supplier (toko_id, supplier_id, supplier, invoice, sisa, due_date, status) VALUES (?,?,?,?,?,?,?)");
        $hs->bind_param("iissdss", $tokoId, $supplierId, $supplierNama, $nomorFaktur, $total, $due, $statusH);
        $hs->execute();
        $hs->close();
    }

    // tandai PO received
    $upd = $pos_db->prepare("UPDATE purchase_order SET status='received' WHERE po_id=?");
    $upd->bind_param("i", $poId);
    $upd->execute();
    $upd->close();

    $pos_db->commit();
    echo json_encode(['ok'=>true,'msg'=>'Penerimaan tersimpan','redirect'=>"/public/purchase_order/print.php?id=$poId"]);
}catch(Exception $e){
    $pos_db->rollback();
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
