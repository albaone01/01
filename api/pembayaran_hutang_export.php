<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="pembayaran_hutang.csv"');

$pos_db->query("CREATE TABLE IF NOT EXISTS pembayaran_hutang (
    bayar_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    supplier VARCHAR(150) NOT NULL,
    referensi VARCHAR(120) DEFAULT NULL,
    jumlah DECIMAL(15,2) NOT NULL,
    catatan VARCHAR(255) DEFAULT NULL,
    dibayar_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$out = fopen('php://output', 'w');
fputcsv($out, ['ID','Supplier','Referensi','Jumlah','Catatan','Dibayar Pada']);
$res = $pos_db->query("SELECT bayar_id,supplier,referensi,jumlah,catatan,dibayar_pada FROM pembayaran_hutang ORDER BY dibayar_pada DESC");
if($res) while($r=$res->fetch_assoc()){
    fputcsv($out, [$r['bayar_id'],$r['supplier'],$r['referensi'],$r['jumlah'],$r['catatan'],$r['dibayar_pada']]);
}
fclose($out);
exit;
