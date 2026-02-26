<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="hutang_supplier.csv"');

$pos_db->query("CREATE TABLE IF NOT EXISTS hutang_supplier (
    hutang_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    supplier VARCHAR(150) NOT NULL,
    invoice VARCHAR(80) NOT NULL,
    sisa DECIMAL(15,2) NOT NULL DEFAULT 0,
    due_date DATE DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'tercatat',
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$out = fopen('php://output', 'w');
fputcsv($out, ['ID','Supplier','Invoice','Sisa','Jatuh Tempo','Status','Dibuat']);
$res = $pos_db->query("SELECT hutang_id,supplier,invoice,sisa,due_date,status,dibuat_pada FROM hutang_supplier ORDER BY dibuat_pada DESC");
if($res) while($r=$res->fetch_assoc()){
    fputcsv($out, [$r['hutang_id'],$r['supplier'],$r['invoice'],$r['sisa'],$r['due_date'],$r['status'],$r['dibuat_pada']]);
}
fclose($out);
exit;
