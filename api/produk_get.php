<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if(!$id) exit(json_encode(['ok'=>false,'msg'=>'ID kosong']));
if(!$tokoId) exit(json_encode(['ok'=>false,'msg'=>'Sesi toko tidak valid']));
try { $pos_db->query("ALTER TABLE produk ADD COLUMN max_stok INT NOT NULL DEFAULT 0 AFTER min_stok"); } catch(Exception $e) {}

// Ambil data produk
$stmt = $pos_db->prepare("SELECT p.produk_id, p.nama_produk, p.sku, p.barcode, p.merk, p.kategori_id, p.supplier_id, p.satuan,
                                 p.harga_modal, p.min_stok, COALESCE(p.max_stok,0) AS max_stok, p.pajak_persen, p.aktif,
                                 COALESCE(p.is_jasa,0) AS is_jasa, COALESCE(p.is_konsinyasi,0) AS is_konsinyasi,
                                 s.satuan_id
                          FROM produk p
                          LEFT JOIN satuan s ON s.nama = p.satuan
                          WHERE p.produk_id=? AND p.toko_id=? AND p.deleted_at IS NULL LIMIT 1");
$stmt->bind_param("ii", $id, $tokoId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if(!$row) exit(json_encode(['ok'=>false,'msg'=>'Tidak ditemukan']));

// Harga per tipe
$stmt = $pos_db->prepare("SELECT tipe, harga_jual FROM produk_harga WHERE produk_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while($h = $res->fetch_assoc()){
    $row["harga_{$h['tipe']}"] = $h['harga_jual'];
}
$stmt->close();

// Multi satuan
$row['multi_satuan'] = [];
$chk = $pos_db->query("SHOW TABLES LIKE 'produk_satuan'");
if ($chk && $chk->num_rows > 0) {
    $stmt = $pos_db->prepare("SELECT nama_satuan, qty_dasar, urutan FROM produk_satuan WHERE produk_id=? ORDER BY urutan ASC, id ASC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($s = $res->fetch_assoc()){
        $row['multi_satuan'][] = [
            'nama_satuan' => $s['nama_satuan'],
            'qty_dasar' => (float)$s['qty_dasar'],
            'urutan' => (int)$s['urutan'],
        ];
    }
    $stmt->close();
}

echo json_encode(['ok'=>true,'data'=>$row]);
