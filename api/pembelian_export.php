<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="pembelian.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['ID','Supplier ID','Toko ID','Gudang ID','Nomor Faktur','Tanggal','Jatuh Tempo','Subtotal','Pajak','Diskon','Ongkir','Total','Catatan','Tipe','Salesman','Status','Dibuat']);
$res = $pos_db->query("SELECT pembelian_id,supplier_id,toko_id,gudang_id,nomor_faktur,tanggal,jatuh_tempo,subtotal,pajak,diskon,ongkir,total,catatan,tipe_faktur,salesman,status,dibuat_pada FROM pembelian ORDER BY dibuat_pada DESC");
if($res) while($r=$res->fetch_assoc()){
    fputcsv($out, [$r['pembelian_id'],$r['supplier_id'],$r['toko_id'],$r['gudang_id'],$r['nomor_faktur'],$r['tanggal'],$r['jatuh_tempo'],$r['subtotal'],$r['pajak'],$r['diskon'],$r['ongkir'],$r['total'],$r['catatan'],$r['tipe_faktur'],$r['salesman'],$r['status'],$r['dibuat_pada']]);
}
fclose($out);
exit;
