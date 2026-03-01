<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');

function ensure_po_schema($db){
    $db->query("CREATE TABLE IF NOT EXISTS purchase_order (
        po_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        toko_id BIGINT NOT NULL DEFAULT 0,
        supplier_id BIGINT NULL,
        nomor VARCHAR(60) NOT NULL,
        tanggal DATE NULL,
        jatuh_tempo DATE NULL,
        subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
        pajak DECIMAL(15,2) NOT NULL DEFAULT 0,
        diskon DECIMAL(15,2) NOT NULL DEFAULT 0,
        ongkir DECIMAL(15,2) NOT NULL DEFAULT 0,
        total DECIMAL(15,2) NOT NULL DEFAULT 0,
        catatan VARCHAR(255) DEFAULT NULL,
        tipe_faktur ENUM('cash','tempo') NOT NULL DEFAULT 'cash',
        salesman VARCHAR(100) DEFAULT NULL,
        tempo_hari INT DEFAULT NULL,
        jenis_ppn VARCHAR(20) DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'draft',
        UNIQUE KEY uq_po_toko_nomor (toko_id, nomor),
        dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $need = [
        'toko_id' => "ALTER TABLE purchase_order ADD COLUMN toko_id BIGINT NOT NULL DEFAULT 0 AFTER po_id",
        'supplier_id' => "ALTER TABLE purchase_order ADD COLUMN supplier_id BIGINT NULL",
        'tanggal' => "ALTER TABLE purchase_order ADD COLUMN tanggal DATE NULL",
        'jatuh_tempo' => "ALTER TABLE purchase_order ADD COLUMN jatuh_tempo DATE NULL",
        'subtotal' => "ALTER TABLE purchase_order ADD COLUMN subtotal DECIMAL(15,2) NOT NULL DEFAULT 0",
        'pajak' => "ALTER TABLE purchase_order ADD COLUMN pajak DECIMAL(15,2) NOT NULL DEFAULT 0",
        'diskon' => "ALTER TABLE purchase_order ADD COLUMN diskon DECIMAL(15,2) NOT NULL DEFAULT 0",
        'ongkir' => "ALTER TABLE purchase_order ADD COLUMN ongkir DECIMAL(15,2) NOT NULL DEFAULT 0",
        'catatan' => "ALTER TABLE purchase_order ADD COLUMN catatan VARCHAR(255) DEFAULT NULL",
        'tipe_faktur' => "ALTER TABLE purchase_order ADD COLUMN tipe_faktur ENUM('cash','tempo') NOT NULL DEFAULT 'cash'",
        'salesman' => "ALTER TABLE purchase_order ADD COLUMN salesman VARCHAR(100) DEFAULT NULL",
        'tempo_hari' => "ALTER TABLE purchase_order ADD COLUMN tempo_hari INT DEFAULT NULL",
        'jenis_ppn' => "ALTER TABLE purchase_order ADD COLUMN jenis_ppn VARCHAR(20) DEFAULT NULL",
    ];
    foreach($need as $col=>$ddl){
        try{
            $cRes = $db->query("SHOW COLUMNS FROM purchase_order LIKE '$col'");
            if(!$cRes || $cRes->num_rows==0){
                $db->query($ddl);
            }
        }catch(Exception $e){}
    }

    // Migrasi unique lama: nomor global -> unik per toko
    try{
        $idxRes = $db->query("SHOW INDEX FROM purchase_order");
        $idxMap = [];
        if($idxRes){
            while($ix = $idxRes->fetch_assoc()){
                $k = (string)$ix['Key_name'];
                if(!isset($idxMap[$k])){
                    $idxMap[$k] = [
                        'non_unique' => (int)$ix['Non_unique'],
                        'cols' => []
                    ];
                }
                $idxMap[$k]['cols'][(int)$ix['Seq_in_index']] = (string)$ix['Column_name'];
            }
        }

        $hasComposite = false;
        foreach($idxMap as $k => $meta){
            ksort($meta['cols']);
            $cols = array_values($meta['cols']);
            if($meta['non_unique'] === 0 && count($cols) === 2 && $cols[0] === 'toko_id' && $cols[1] === 'nomor'){
                $hasComposite = true;
            }
        }

        foreach($idxMap as $k => $meta){
            if($k === 'PRIMARY') continue;
            if($meta['non_unique'] !== 0) continue;
            ksort($meta['cols']);
            $cols = array_values($meta['cols']);
            if(count($cols) === 1 && $cols[0] === 'nomor'){
                $db->query("ALTER TABLE purchase_order DROP INDEX `{$k}`");
            }
        }

        if(!$hasComposite){
            $db->query("ALTER TABLE purchase_order ADD UNIQUE KEY uq_po_toko_nomor (toko_id, nomor)");
        }
    }catch(Exception $e){}
}
ensure_po_schema($pos_db);

// ensure detail table
$pos_db->query("CREATE TABLE IF NOT EXISTS purchase_order_detail (
    detail_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    po_id BIGINT NOT NULL,
    produk_id BIGINT NULL,
    nama_barang VARCHAR(200) NOT NULL,
    qty DECIMAL(15,2) NOT NULL DEFAULT 0,
    harga DECIMAL(15,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    satuan VARCHAR(50) DEFAULT NULL,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_po (po_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// tambahkan kolom produk_id jika tabel lama belum punya
try{
    $c = $pos_db->query("SHOW COLUMNS FROM purchase_order_detail LIKE 'produk_id'");
    if(!$c || $c->num_rows==0){
        $pos_db->query("ALTER TABLE purchase_order_detail ADD COLUMN produk_id BIGINT NULL AFTER po_id");
    }
}catch(Exception $e){}

// tambahkan kolom satuan jika tabel lama belum punya
try{
    $c = $pos_db->query("SHOW COLUMNS FROM purchase_order_detail LIKE 'satuan'");
    if(!$c || $c->num_rows==0){
        $pos_db->query("ALTER TABLE purchase_order_detail ADD COLUMN satuan VARCHAR(50) DEFAULT NULL AFTER subtotal");
    }
}catch(Exception $e){}

$tokoId   = (int)($_SESSION['toko_id'] ?? 0);
$supplierId = (int)($_POST['supplier_id'] ?? 0);
$nomor    = trim($_POST['nomor'] ?? '');
$tanggal  = $_POST['tanggal'] ?? date('Y-m-d');
$jt       = $_POST['jatuh_tempo'] ?? null;
$subtotal = (float)($_POST['subtotal'] ?? 0);
$pajak    = (float)($_POST['pajak'] ?? 0);
$diskon   = (float)($_POST['diskon'] ?? 0);
$ongkir   = (float)($_POST['ongkir'] ?? 0);
$total    = (float)($_POST['total'] ?? 0);
$catatan  = trim($_POST['catatan'] ?? '');
$tempoHari = (int)($_POST['tempo_hari'] ?? 0);
$jenisPpn  = trim($_POST['jenis_ppn'] ?? '');
$tipe     = $_POST['tipe_faktur'] === 'tempo' ? 'tempo' : 'cash';
$sales    = trim($_POST['salesman'] ?? '');
$status   = trim($_POST['status'] ?? 'draft');
if (!in_array($status, ['draft', 'approved'], true)) $status = 'draft';

if(!$tokoId) exit(json_encode(['ok'=>false,'msg'=>'Sesi toko tidak valid']));
if(!$supplierId || $nomor=='') exit(json_encode(['ok'=>false,'msg'=>'Supplier dan nomor PO wajib diisi']));

$supCheck = $pos_db->prepare("SELECT supplier_id FROM supplier WHERE supplier_id=? AND toko_id=? LIMIT 1");
$supCheck->bind_param("ii", $supplierId, $tokoId);
$supCheck->execute();
$supRow = $supCheck->get_result()->fetch_assoc();
$supCheck->close();
if (!$supRow) exit(json_encode(['ok'=>false,'msg'=>'Supplier tidak valid untuk toko ini']));

$dupCheck = $pos_db->prepare("SELECT po_id FROM purchase_order WHERE toko_id=? AND nomor=? LIMIT 1");
$dupCheck->bind_param("is", $tokoId, $nomor);
$dupCheck->execute();
$dupPo = $dupCheck->get_result()->fetch_assoc();
$dupCheck->close();
if($dupPo) exit(json_encode(['ok'=>false,'msg'=>'Nomor PO sudah dipakai di toko ini']));

if($tipe === 'tempo' && !$jt && $tempoHari>0){
    $jt = date('Y-m-d', strtotime($tanggal . " +{$tempoHari} days"));
}
$pos_db->begin_transaction();
try{
    $stmt = $pos_db->prepare("INSERT INTO purchase_order (toko_id, supplier_id, nomor, tanggal, jatuh_tempo, tempo_hari, jenis_ppn, subtotal, pajak, diskon, ongkir, total, catatan, tipe_faktur, salesman, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $types = "iisssisdddddssss";
    $stmt->bind_param($types, $tokoId, $supplierId, $nomor, $tanggal, $jt, $tempoHari, $jenisPpn, $subtotal, $pajak, $diskon, $ongkir, $total, $catatan, $tipe, $sales, $status);
    $stmt->execute();
    $poId = $pos_db->insertId();
    $stmt->close();

    // simpan detail jika ada
    $names = $_POST['item_nama'] ?? [];
    $qtys  = $_POST['item_qty'] ?? [];
    $harga = $_POST['item_harga'] ?? [];
    $prodIds = $_POST['item_product_id'] ?? [];
    $satuans = $_POST['item_satuan'] ?? [];
    if(is_array($names)){
        $stmtD = $pos_db->prepare("INSERT INTO purchase_order_detail (po_id, produk_id, nama_barang, qty, harga, subtotal, satuan) VALUES (?,?,?,?,?,?,?)");
        foreach($names as $i=>$nm){
            $nm = trim($nm);
            $q  = (float)($qtys[$i] ?? 0);
            $h  = (float)($harga[$i] ?? 0);
            if($nm === '' || $q <= 0) continue; // hindari baris kosong/duplikat nol
            $sub = $q * $h;
            $pid = (int)($prodIds[$i] ?? 0) ?: null;
            $sat = $satuans[$i] ?? '';
            $stmtD->bind_param("iisddds", $poId, $pid, $nm, $q, $h, $sub, $sat);
            $stmtD->execute();
        }
        $stmtD->close();
    }

    $pos_db->commit();
    echo json_encode(['ok'=>true,'msg'=>'PO tersimpan','id'=>$poId]);
}catch(Exception $e){
    $pos_db->rollback();
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
