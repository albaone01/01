<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/inventory.php';
header('Content-Type: application/json');

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$gudangId = (int)($_POST['gudang_id'] ?? ($_SESSION['gudang_id'] ?? 1));
$pb = (int)($_POST['pembelian_id'] ?? 0);
$produkId = (int)($_POST['produk_id'] ?? 0);
$nama = trim($_POST['nama_barang'] ?? '');
$qty = (float)($_POST['qty'] ?? 0);
$harga = (float)($_POST['harga_beli'] ?? 0);
$sub = $qty * $harga;

if (!$tokoId || !$pb || $nama === '' || $qty <= 0 || $harga < 0) {
    exit(json_encode(['ok' => false, 'msg' => 'Data detail pembelian tidak lengkap']));
}

$qtyInt = (int)round($qty);
if ($qtyInt <= 0) {
    exit(json_encode(['ok' => false, 'msg' => 'Qty harus lebih dari 0']));
}

$pos_db->begin_transaction();
try {
    ensure_inventory_snapshot_columns($pos_db);

    $stmt = $pos_db->prepare("INSERT INTO pembelian_detail (pembelian_id,produk_id,nama_barang,qty,harga_beli,subtotal) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("iisddd", $pb, $produkId, $nama, $qty, $harga, $sub);
    $stmt->execute();
    $stmt->close();

    if ($produkId > 0) {
        $ref = 'PB-' . $pb;
        $st = apply_stock_mutation($pos_db, $tokoId, $gudangId, $produkId, $qtyInt, 'masuk', $ref);
        update_produk_hpp_on_masuk($pos_db, $produkId, (int)$st['stok_sebelum'], $qtyInt, $harga);
    }

    $pos_db->commit();
    echo json_encode(['ok' => true, 'msg' => 'Detail tersimpan']);
} catch (Exception $e) {
    $pos_db->rollback();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
