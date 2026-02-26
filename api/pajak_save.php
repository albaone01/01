<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

// Ensure table exists
$pos_db->query("CREATE TABLE IF NOT EXISTS pajak (
    pajak_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL UNIQUE,
    persen DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    deskripsi VARCHAR(255) NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diupdate_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$id   = (int)($_POST['pajak_id'] ?? 0);
$nama = trim($_POST['nama'] ?? '');
$persen = (float)($_POST['persen'] ?? 0);
$desk = trim($_POST['deskripsi'] ?? '');
$aktif = isset($_POST['aktif']) && $_POST['aktif']==1 ? 1 : 0;

if ($nama === '' || $persen < 0 || $persen > 100) {
    exit(json_encode(['ok'=>false,'msg'=>'Nama wajib, persentase 0-100']));
}

try{
    if ($id) {
        $stmt = $pos_db->prepare("UPDATE pajak SET nama=?, persen=?, deskripsi=?, aktif=? WHERE pajak_id=?");
        $stmt->bind_param("sdsii", $nama, $persen, $desk, $aktif, $id);
    } else {
        $stmt = $pos_db->prepare("INSERT INTO pajak (nama,persen,deskripsi,aktif) VALUES (?,?,?,?)");
        $stmt->bind_param("sdsi", $nama, $persen, $desk, $aktif);
    }
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok'=>true,'msg'=>'Tersimpan']);
} catch(Exception $e){
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
