<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');

$poId = (int)($_GET['po_id'] ?? 0);
$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if(!$poId || !$tokoId) exit(json_encode(['ok'=>false,'msg'=>'Parameter tidak valid']));

$stmt = $pos_db->prepare("SELECT po.supplier_id, po.nomor, po.tanggal, po.status, po.jenis_ppn, po.pajak, po.subtotal as po_subtotal, s.nama_supplier
                          FROM purchase_order po
                          LEFT JOIN supplier s ON s.supplier_id = po.supplier_id
                          WHERE po.po_id=? AND po.toko_id=? AND po.status='approved' LIMIT 1");
$stmt->bind_param("ii", $poId, $tokoId);
$stmt->execute();
$headerRes = $stmt->get_result();
$header = $headerRes ? $headerRes->fetch_assoc() : null;
$stmt->close();
if(!$header) exit(json_encode(['ok'=>false,'msg'=>'PO tidak ditemukan atau belum approved']));

$items = [];
// Pastikan kolom satuan ada (untuk tabel lama)
try{
    $c = $pos_db->query("SHOW COLUMNS FROM purchase_order_detail LIKE 'satuan'");
    if(!$c || $c->num_rows==0){
        $pos_db->query("ALTER TABLE purchase_order_detail ADD COLUMN satuan VARCHAR(50) DEFAULT NULL AFTER subtotal");
    }
}catch(Exception $e){}

$det = $pos_db->prepare("SELECT pod.produk_id, pod.nama_barang, pod.qty, pod.harga, pod.subtotal,
                                 COALESCE(pod.satuan, p.satuan) AS satuan
                          FROM purchase_order_detail pod
                          LEFT JOIN produk p ON p.produk_id = pod.produk_id
                          WHERE pod.po_id=? AND pod.qty > 0");
$det->bind_param("i", $poId);
$det->execute();
$res = $det->get_result();
if($res) $items = $res->fetch_all(MYSQLI_ASSOC);
$det->close();

echo json_encode([
    'ok' => true,
    'supplier_id' => $header['supplier_id'] ?? null,
    'nama_supplier' => $header['nama_supplier'] ?? null,
    'jenis_ppn' => $header['jenis_ppn'] ?? '',
    'pajak' => $header['pajak'] ?? 0,
    'subtotal' => $header['po_subtotal'] ?? 0,
    'items' => $items
]);
