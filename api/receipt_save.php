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
if(!$tokoId) exit(json_encode(['ok'=>false,'msg'=>'Sesi toko tidak valid']));

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
try{
    $cols = [
        'tempo_hari' => "ALTER TABLE pembelian ADD COLUMN tempo_hari INT DEFAULT NULL AFTER salesman",
        'po_id' => "ALTER TABLE pembelian ADD COLUMN po_id BIGINT DEFAULT NULL AFTER nomor_faktur",
    ];
    foreach($cols as $col=>$ddl){
        $c = $pos_db->query("SHOW COLUMNS FROM pembelian LIKE '$col'");
        if(!$c || $c->num_rows===0){
            $pos_db->query($ddl);
        }
    }
}catch(Exception $e){}

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
$diskonPo = 0.0;
$ongkirPo = 0.0;
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
        $poStmt = $pos_db->prepare("SELECT nomor, tipe_faktur, jatuh_tempo, tempo_hari, supplier_id, diskon, ongkir FROM purchase_order WHERE po_id=? AND toko_id=? AND status='approved' LIMIT 1");
        $poStmt->bind_param("ii", $poId, $tokoId);
        $poStmt->execute();
        $poInfo = $poStmt->get_result()->fetch_assoc();
        $poStmt->close();
        if(!$poInfo){
            throw new Exception('PO tidak ditemukan atau belum approved');
        }
        if((int)($poInfo['supplier_id'] ?? 0) !== $supplierId){
            throw new Exception('Supplier tidak sesuai dengan PO');
        }
        $nomorFaktur = $poInfo['nomor'] ?? $nomorFaktur;
        $tipeFaktur = $poInfo['tipe_faktur'] ?? 'cash';
        $dueDate = $poInfo['jatuh_tempo'] ?? null;
        $tempoHari = (int)($poInfo['tempo_hari'] ?? 0);
        $diskonPo = (float)($poInfo['diskon'] ?? 0);
        $ongkirPo = (float)($poInfo['ongkir'] ?? 0);
    }

    $stmt = $pos_db->prepare("INSERT INTO pembelian (supplier_id,toko_id,gudang_id,nomor_faktur,tanggal,jatuh_tempo,tempo_hari,subtotal,pajak,diskon,ongkir,total,catatan,tipe_faktur,status,po_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $statusVal='posted';
    $types = "iiisssidddddsssi";
    $stmt->bind_param($types, $supplierId,$tokoId,$gudangId,$nomorFaktur,$tanggal,$dueDate,$tempoHari,$subtotal,$pajak,$diskonPo,$ongkirPo,$total,$catatan,$tipeFaktur,$statusVal,$poId);
    $stmt->execute();
    $pembelianId = $pos_db->insertId();
    $stmt->close();

$poItemMap = [];
$poDet = $pos_db->prepare("SELECT produk_id, nama_barang, qty FROM purchase_order_detail WHERE po_id=? AND qty>0");
$poDet->bind_param("i", $poId);
$poDet->execute();
$poRes = $poDet->get_result();
while($r = $poRes->fetch_assoc()){
    $pid = (int)($r['produk_id'] ?? 0);
    $nmKey = strtolower(trim((string)($r['nama_barang'] ?? '')));
    if($pid > 0){
        $poItemMap['p:'.$pid] = (float)$r['qty'];
    } elseif($nmKey !== '') {
        $poItemMap['n:'.$nmKey] = (float)$r['qty'];
    }
}
$poDet->close();

$receivedAccumulator = [];
$validRows = 0;
$stmtD = $pos_db->prepare("INSERT INTO pembelian_detail (pembelian_id,produk_id,nama_barang,qty,harga_beli,subtotal) VALUES (?,?,?,?,?,?)");
foreach($barang as $i=>$nm){
    $nm = trim($nm);
    if($nm==='') continue;
    $q = (float)($qtys[$i] ?? 0);
    if($q <= 0) continue;
    $qInt = (int)round($q);
    if ($q > 0 && abs($q - $qInt) > 0.0001) {
        throw new Exception('Qty penerimaan harus bilangan bulat');
    }
    $h = (float)($harga[$i] ?? 0);
    $sub = $q*$h;
    $prodId = (int)($prodIds[$i] ?? 0);

    $nmKey = strtolower($nm);
    $mapKey = $prodId > 0 ? ('p:'.$prodId) : ('n:'.$nmKey);
    if(!isset($poItemMap[$mapKey])){
        throw new Exception("Barang {$nm} tidak ada di PO");
    }
    $receivedAccumulator[$mapKey] = (float)($receivedAccumulator[$mapKey] ?? 0) + $q;
    if($receivedAccumulator[$mapKey] - (float)$poItemMap[$mapKey] > 0.0001){
        throw new Exception("Qty terima {$nm} melebihi qty PO");
    }

    $stmtD->bind_param("iisddd", $pembelianId, $prodId, $nm, $q, $h, $sub);
    $stmtD->execute();
    $validRows++;

    if ($prodId > 0 && $qInt > 0) {
        $ref = $nomorFaktur;
        $stock = apply_stock_mutation($pos_db, $tokoId, $gudangId, $prodId, $qInt, 'masuk', $ref);
        update_produk_hpp_on_masuk($pos_db, $prodId, (int)$stock['stok_sebelum'], $qInt, $h);
    }
}
    $stmtD->close();
    if($validRows <= 0){
        throw new Exception('Tidak ada qty terima yang valid');
    }

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
    $upd = $pos_db->prepare("UPDATE purchase_order SET status='received' WHERE po_id=? AND toko_id=?");
    $upd->bind_param("ii", $poId, $tokoId);
    $upd->execute();
    $upd->close();

    $pos_db->commit();
    echo json_encode(['ok'=>true,'msg'=>'Penerimaan tersimpan','redirect'=>"/public/admin/purchase_order/print.php?id=$poId"]);
}catch(Exception $e){
    $pos_db->rollback();
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
