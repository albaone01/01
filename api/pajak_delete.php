<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');

// pastikan tabel ada
$pos_db->query("CREATE TABLE IF NOT EXISTS pajak (
    pajak_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL UNIQUE,
    persen DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    deskripsi VARCHAR(255) NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diupdate_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$id = (int)($_POST['pajak_id'] ?? 0);
if(!$id) exit(json_encode(['ok'=>false,'msg'=>'ID kosong']));

$stmt = $pos_db->prepare("DELETE FROM pajak WHERE pajak_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

echo json_encode(['ok'=>true,'msg'=>'Dihapus']);
