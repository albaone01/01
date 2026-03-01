<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/inventory.php';
header('Content-Type: application/json');

requireLogin();
requireDevice();

function parse_ppn_rate(string $jenisPpn): float {
    $jp = strtolower(trim($jenisPpn));
    if (strpos($jp, '1.1') !== false) return 0.011;
    if (strpos($jp, '11') !== false) return 0.11;
    return 0.0;
}

function ensure_pembelian_schema(Database $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS pembelian (
        pembelian_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        supplier_id BIGINT NOT NULL,
        toko_id BIGINT NOT NULL,
        gudang_id BIGINT NOT NULL DEFAULT 1,
        nomor_faktur VARCHAR(80) NOT NULL,
        po_id BIGINT DEFAULT NULL,
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
        tempo_hari INT DEFAULT NULL,
        jenis_ppn VARCHAR(20) DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'draft',
        dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $need = [
        'po_id' => "ALTER TABLE pembelian ADD COLUMN po_id BIGINT DEFAULT NULL AFTER nomor_faktur",
        'tempo_hari' => "ALTER TABLE pembelian ADD COLUMN tempo_hari INT DEFAULT NULL AFTER salesman",
        'jenis_ppn' => "ALTER TABLE pembelian ADD COLUMN jenis_ppn VARCHAR(20) DEFAULT NULL AFTER tempo_hari",
    ];
    foreach ($need as $col => $ddl) {
        try {
            $c = $db->query("SHOW COLUMNS FROM pembelian LIKE '$col'");
            if (!$c || $c->num_rows === 0) $db->query($ddl);
        } catch (Exception $e) {}
    }
}

function ensure_pembelian_detail_schema(Database $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS pembelian_detail (
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
    $need = [
        'diskon_persen' => "ALTER TABLE pembelian_detail ADD COLUMN diskon_persen DECIMAL(7,2) NOT NULL DEFAULT 0 AFTER harga_beli",
        'profit_persen' => "ALTER TABLE pembelian_detail ADD COLUMN profit_persen DECIMAL(7,2) NOT NULL DEFAULT 0 AFTER diskon_persen",
        'harga_jual_target' => "ALTER TABLE pembelian_detail ADD COLUMN harga_jual_target DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER profit_persen",
        'satuan' => "ALTER TABLE pembelian_detail ADD COLUMN satuan VARCHAR(50) DEFAULT NULL AFTER subtotal",
    ];
    foreach ($need as $col => $ddl) {
        try {
            $c = $db->query("SHOW COLUMNS FROM pembelian_detail LIKE '$col'");
            if (!$c || $c->num_rows === 0) $db->query($ddl);
        } catch (Exception $e) {}
    }
}

function ensure_produk_harga_beli_tracking(Database $db): void {
    $need = [
        'harga_beli_sebelum' => "ALTER TABLE produk ADD COLUMN harga_beli_sebelum DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER harga_modal",
        'harga_beli_akhir' => "ALTER TABLE produk ADD COLUMN harga_beli_akhir DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER harga_beli_sebelum",
        'harga_beli_terakhir_at' => "ALTER TABLE produk ADD COLUMN harga_beli_terakhir_at DATETIME NULL AFTER harga_beli_akhir",
    ];
    foreach ($need as $col => $ddl) {
        try {
            $c = $db->query("SHOW COLUMNS FROM produk LIKE '$col'");
            if (!$c || $c->num_rows === 0) $db->query($ddl);
        } catch (Exception $e) {}
    }
}

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$gudangId = (int)($_POST['gudang_id'] ?? ($_SESSION['gudang_id'] ?? 1));
$poId = (int)($_POST['po_id'] ?? 0);
$supplierId = (int)($_POST['supplier_id'] ?? 0);
$nomorFaktur = trim((string)($_POST['nomor_faktur'] ?? ''));
$tanggal = $_POST['tanggal_terima'] ?? date('Y-m-d');
$tipeFaktur = ($_POST['tipe_faktur'] ?? 'cash') === 'tempo' ? 'tempo' : 'cash';
$tempoHari = max(0, (int)($_POST['tempo_hari'] ?? 0));
$dueDate = trim((string)($_POST['jatuh_tempo'] ?? ''));
$jenisPpn = trim((string)($_POST['jenis_ppn'] ?? 'PPN 11%'));
$catatan = trim((string)($_POST['catatan'] ?? ''));
$headerDiscInput = max(0, (float)($_POST['diskon_header'] ?? 0));

$barang = $_POST['barang'] ?? [];
$qtys = $_POST['qty'] ?? [];
$harga = $_POST['harga'] ?? [];
$prodIds = $_POST['produk_id'] ?? [];
$discItem = $_POST['item_diskon_persen'] ?? [];
$profitItem = $_POST['profit_persen'] ?? [];
$satuanItem = $_POST['item_satuan'] ?? [];

if (!$tokoId) exit(json_encode(['ok' => false, 'msg' => 'Sesi toko tidak valid']));
if (!$supplierId) exit(json_encode(['ok' => false, 'msg' => 'Supplier wajib dipilih']));
if ($nomorFaktur === '') exit(json_encode(['ok' => false, 'msg' => 'Nomor faktur wajib diisi']));
if (!is_array($barang) || !$barang) exit(json_encode(['ok' => false, 'msg' => 'Detail barang kosong']));

if ($tipeFaktur === 'tempo' && $dueDate === '' && $tempoHari > 0) {
    $dueDate = date('Y-m-d', strtotime($tanggal . " +{$tempoHari} days"));
}
if ($tipeFaktur === 'cash') {
    $tempoHari = 0;
    $dueDate = null;
}

ensure_pembelian_schema($pos_db);
ensure_pembelian_detail_schema($pos_db);
ensure_inventory_snapshot_columns($pos_db);
ensure_produk_harga_beli_tracking($pos_db);

// nomor faktur harus unik per toko
$dup = $pos_db->prepare("SELECT pembelian_id FROM pembelian WHERE toko_id=? AND nomor_faktur=? LIMIT 1");
$dup->bind_param("is", $tokoId, $nomorFaktur);
$dup->execute();
$dupRow = $dup->get_result()->fetch_assoc();
$dup->close();
if ($dupRow) exit(json_encode(['ok' => false, 'msg' => 'Nomor faktur sudah digunakan di toko ini']));

// cek supplier milik toko
$supQ = $pos_db->prepare("SELECT nama_supplier FROM supplier WHERE supplier_id=? AND toko_id=? LIMIT 1");
$supQ->bind_param("ii", $supplierId, $tokoId);
$supQ->execute();
$supRow = $supQ->get_result()->fetch_assoc();
$supQ->close();
if (!$supRow) exit(json_encode(['ok' => false, 'msg' => 'Supplier tidak valid untuk toko ini']));
$supplierNama = (string)$supRow['nama_supplier'];

$poInfo = null;
$poItemMap = [];
if ($poId > 0) {
    $poStmt = $pos_db->prepare("SELECT nomor, supplier_id, tipe_faktur, jatuh_tempo, tempo_hari, jenis_ppn
                                FROM purchase_order
                                WHERE po_id=? AND toko_id=? AND status='approved' LIMIT 1");
    $poStmt->bind_param("ii", $poId, $tokoId);
    $poStmt->execute();
    $poInfo = $poStmt->get_result()->fetch_assoc();
    $poStmt->close();
    if (!$poInfo) exit(json_encode(['ok' => false, 'msg' => 'PO tidak ditemukan atau belum approved']));
    if ((int)$poInfo['supplier_id'] !== $supplierId) exit(json_encode(['ok' => false, 'msg' => 'Supplier tidak sesuai dengan PO']));

    // cegah terima ganda untuk PO yang sama
    $chkPb = $pos_db->prepare("SELECT pembelian_id FROM pembelian WHERE toko_id=? AND po_id=? LIMIT 1");
    $chkPb->bind_param("ii", $tokoId, $poId);
    $chkPb->execute();
    $pbRow = $chkPb->get_result()->fetch_assoc();
    $chkPb->close();
    if ($pbRow) exit(json_encode(['ok' => false, 'msg' => 'PO ini sudah pernah diposting penerimaan']));

    if ($tipeFaktur !== 'tempo' && ($poInfo['tipe_faktur'] ?? '') === 'tempo') {
        $tipeFaktur = 'tempo';
        $tempoHari = (int)($poInfo['tempo_hari'] ?? $tempoHari);
        if (!$dueDate) $dueDate = $poInfo['jatuh_tempo'] ?? null;
    }
    if ($jenisPpn === '') $jenisPpn = (string)($poInfo['jenis_ppn'] ?? 'PPN 11%');

    $poDet = $pos_db->prepare("SELECT produk_id, nama_barang, qty FROM purchase_order_detail WHERE po_id=? AND qty>0");
    $poDet->bind_param("i", $poId);
    $poDet->execute();
    $poRes = $poDet->get_result();
    while ($r = $poRes->fetch_assoc()) {
        $pid = (int)($r['produk_id'] ?? 0);
        $nmKey = strtolower(trim((string)($r['nama_barang'] ?? '')));
        if ($pid > 0) $poItemMap['p:' . $pid] = (float)$r['qty'];
        elseif ($nmKey !== '') $poItemMap['n:' . $nmKey] = (float)$r['qty'];
    }
    $poDet->close();
}

$lineRows = [];
$rawSubtotal = 0.0;
$itemDiscountTotal = 0.0;
$poAccumulator = [];
for ($i = 0; $i < count($barang); $i++) {
    $nm = trim((string)($barang[$i] ?? ''));
    if ($nm === '') continue;
    $q = (float)($qtys[$i] ?? 0);
    if ($q <= 0) continue;
    $qInt = (int)round($q);
    if (abs($q - $qInt) > 0.0001) {
        exit(json_encode(['ok' => false, 'msg' => 'Qty penerimaan harus bilangan bulat']));
    }
    $h = max(0, (float)($harga[$i] ?? 0));
    $pid = (int)($prodIds[$i] ?? 0);
    $discPct = max(0, min(100, (float)($discItem[$i] ?? 0)));
    $profitPct = max(0, (float)($profitItem[$i] ?? 0));
    $sat = trim((string)($satuanItem[$i] ?? ''));
    if ($sat === '') $sat = 'PCS';

    if ($poId > 0) {
        $nmKey = strtolower($nm);
        $mapKey = $pid > 0 ? ('p:' . $pid) : ('n:' . $nmKey);
        if (!isset($poItemMap[$mapKey])) {
            exit(json_encode(['ok' => false, 'msg' => "Barang {$nm} tidak ada di PO"]));
        }
        $poAccumulator[$mapKey] = (float)($poAccumulator[$mapKey] ?? 0) + $q;
        if ($poAccumulator[$mapKey] - (float)$poItemMap[$mapKey] > 0.0001) {
            exit(json_encode(['ok' => false, 'msg' => "Qty terima {$nm} melebihi qty PO"]));
        }
    }

    $gross = $q * $h;
    $discAmt = $gross * ($discPct / 100);
    $net = $gross - $discAmt;
    $baseUnit = $q > 0 ? ($net / $q) : 0;
    $targetSell = $baseUnit * (1 + ($profitPct / 100));

    $lineRows[] = [
        'produk_id' => $pid,
        'nama_barang' => $nm,
        'qty' => $q,
        'qty_int' => $qInt,
        'harga_beli' => $h,
        'diskon_persen' => $discPct,
        'profit_persen' => $profitPct,
        'harga_jual_target' => $targetSell,
        'subtotal' => $net,
        'satuan' => $sat,
    ];
    $rawSubtotal += $gross;
    $itemDiscountTotal += $discAmt;
}
if (!$lineRows) exit(json_encode(['ok' => false, 'msg' => 'Tidak ada qty terima yang valid']));

$afterItem = max(0.0, $rawSubtotal - $itemDiscountTotal);
$headerDiscount = min($headerDiscInput, $afterItem);
$dpp = max(0.0, $afterItem - $headerDiscount);
$rate = parse_ppn_rate($jenisPpn);
$pajak = $dpp * $rate;
$total = $dpp + $pajak;
$totalDiskon = $itemDiscountTotal + $headerDiscount;

$statusVal = 'posted';
$pos_db->begin_transaction();
try {
    $ins = $pos_db->prepare("INSERT INTO pembelian
        (supplier_id,toko_id,gudang_id,nomor_faktur,po_id,tanggal,jatuh_tempo,subtotal,pajak,diskon,ongkir,total,catatan,tipe_faktur,salesman,tempo_hari,jenis_ppn,status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ongkir = 0.0;
    $salesman = '';
    $ins->bind_param(
        "iiisissdddddsssiss",
        $supplierId,
        $tokoId,
        $gudangId,
        $nomorFaktur,
        $poId,
        $tanggal,
        $dueDate,
        $rawSubtotal,
        $pajak,
        $totalDiskon,
        $ongkir,
        $total,
        $catatan,
        $tipeFaktur,
        $salesman,
        $tempoHari,
        $jenisPpn,
        $statusVal
    );
    $ins->execute();
    $pembelianId = (int)$pos_db->insertId();
    $ins->close();

    $insD = $pos_db->prepare("INSERT INTO pembelian_detail
        (pembelian_id,produk_id,nama_barang,qty,harga_beli,diskon_persen,profit_persen,harga_jual_target,subtotal,satuan)
        VALUES (?,?,?,?,?,?,?,?,?,?)");
    foreach ($lineRows as $rw) {
        $insD->bind_param(
            "iisdddddds",
            $pembelianId,
            $rw['produk_id'],
            $rw['nama_barang'],
            $rw['qty'],
            $rw['harga_beli'],
            $rw['diskon_persen'],
            $rw['profit_persen'],
            $rw['harga_jual_target'],
            $rw['subtotal'],
            $rw['satuan']
        );
        $insD->execute();

        $pid = (int)$rw['produk_id'];
        $qInt = (int)$rw['qty_int'];
        if ($pid > 0 && $qInt > 0) {
            $ref = $nomorFaktur;
            $st = apply_stock_mutation($pos_db, $tokoId, $gudangId, $pid, $qInt, 'masuk', $ref);
            $hppUnit = $rw['qty'] > 0 ? ((float)$rw['subtotal'] / (float)$rw['qty']) : (float)$rw['harga_beli'];
            update_produk_hpp_on_masuk($pos_db, $pid, (int)$st['stok_sebelum'], $qInt, $hppUnit);

            $hargaBeliBaru = (float)$rw['harga_beli'];
            $stPb = $pos_db->prepare("SELECT COALESCE(harga_beli_akhir,0) AS harga_beli_akhir FROM produk WHERE produk_id=? AND toko_id=? LIMIT 1");
            $stPb->bind_param("ii", $pid, $tokoId);
            $stPb->execute();
            $oldPbRow = $stPb->get_result()->fetch_assoc();
            $stPb->close();
            $hargaBeliAkhirLama = (float)($oldPbRow['harga_beli_akhir'] ?? 0);

            if ($hargaBeliAkhirLama <= 0) {
                $upPb = $pos_db->prepare("UPDATE produk SET harga_beli_akhir=?, harga_beli_terakhir_at=NOW() WHERE produk_id=? AND toko_id=?");
                $upPb->bind_param("dii", $hargaBeliBaru, $pid, $tokoId);
                $upPb->execute();
                $upPb->close();
            } elseif (abs($hargaBeliBaru - $hargaBeliAkhirLama) > 0.0001) {
                $upPb = $pos_db->prepare("UPDATE produk SET harga_beli_sebelum=?, harga_beli_akhir=?, harga_beli_terakhir_at=NOW() WHERE produk_id=? AND toko_id=?");
                $upPb->bind_param("ddii", $hargaBeliAkhirLama, $hargaBeliBaru, $pid, $tokoId);
                $upPb->execute();
                $upPb->close();
            } else {
                $upPb = $pos_db->prepare("UPDATE produk SET harga_beli_terakhir_at=NOW() WHERE produk_id=? AND toko_id=?");
                $upPb->bind_param("ii", $pid, $tokoId);
                $upPb->execute();
                $upPb->close();
            }
        }
    }
    $insD->close();

    if ($tipeFaktur === 'tempo') {
        $due = $dueDate ?: $tanggal;
        $statusH = (strtotime($due) < strtotime(date('Y-m-d'))) ? 'jatuh tempo' : 'tercatat';
        $hs = $pos_db->prepare("INSERT INTO hutang_supplier (toko_id, supplier_id, supplier, invoice, sisa, due_date, status) VALUES (?,?,?,?,?,?,?)");
        $hs->bind_param("iissdss", $tokoId, $supplierId, $supplierNama, $nomorFaktur, $total, $due, $statusH);
        $hs->execute();
        $hs->close();
    }

    if ($poId > 0) {
        $upd = $pos_db->prepare("UPDATE purchase_order SET status='received' WHERE po_id=? AND toko_id=?");
        $upd->bind_param("ii", $poId, $tokoId);
        $upd->execute();
        $upd->close();
    }

    $pos_db->commit();
    $redirect = $poId > 0
        ? "/public/admin/purchase_order/print.php?id=$poId"
        : "/public/admin/pembelian/index.php";
    echo json_encode(['ok' => true, 'msg' => 'Penerimaan tersimpan', 'redirect' => $redirect, 'pembelian_id' => $pembelianId]);
} catch (Exception $e) {
    $pos_db->rollback();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
