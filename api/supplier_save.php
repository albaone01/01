<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

// pastikan tabel ada
$pos_db->query("CREATE TABLE IF NOT EXISTS supplier (
    supplier_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nama_supplier VARCHAR(150) NOT NULL,
    telepon VARCHAR(50) DEFAULT NULL,
    alamat TEXT DEFAULT NULL,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$id    = (int)($_POST['supplier_id'] ?? 0);
$nama  = trim($_POST['nama_supplier'] ?? '');
$telp  = trim($_POST['telepon'] ?? '');
$alamat= trim($_POST['alamat'] ?? '');

if($nama === '') exit(json_encode(['ok'=>false,'msg'=>'Nama supplier wajib diisi']));

try{
    if($id){
        $stmt = $pos_db->prepare("UPDATE supplier SET nama_supplier=?, telepon=?, alamat=? WHERE supplier_id=?");
        $stmt->bind_param("sssi", $nama, $telp, $alamat, $id);
    } else {
        $stmt = $pos_db->prepare("INSERT INTO supplier (nama_supplier, telepon, alamat) VALUES (?,?,?)");
        $stmt->bind_param("sss", $nama, $telp, $alamat);
    }
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok'=>true,'msg'=>'Tersimpan']);
} catch(Exception $e){
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
