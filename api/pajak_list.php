<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');

// Pastikan tabel ada
$pos_db->query("CREATE TABLE IF NOT EXISTS pajak (
    pajak_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL UNIQUE,
    persen DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    deskripsi VARCHAR(255) NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diupdate_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$res = $pos_db->query("SELECT pajak_id,nama,persen,deskripsi,aktif FROM pajak ORDER BY aktif DESC, nama");
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode(['ok'=>true,'data'=>$rows]);
