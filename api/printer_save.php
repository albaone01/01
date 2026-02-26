<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once '../inc/config.php'; require_once '../inc/db.php';
header('Content-Type: application/json');

$device = $_SESSION['device_id'] ?? ($_POST['device_id'] ?? '');
if(!$device) exit(json_encode(['ok'=>false,'msg'=>'device_id kosong']));

$id   = (int)($_POST['id'] ?? 0);
$nama = trim($_POST['nama'] ?? '');
$jenis= $_POST['jenis'] ?? 'network';
$alamat= trim($_POST['alamat'] ?? '');
$lebar = $_POST['lebar'] ?? '80';
$driver= $_POST['driver'] ?? 'escpos';
$isDef = isset($_POST['is_default']) ? 1 : 0;
if(!$nama || !$alamat) exit(json_encode(['ok'=>false,'msg'=>'Nama & alamat wajib']));

$pos_db->begin_transaction();
try{
    if($isDef){
        $stmt=$pos_db->prepare("UPDATE printer_setting SET is_default=0 WHERE device_id=?");
        $stmt->bind_param("s",$device); $stmt->execute(); $stmt->close();
    }
    if($id){
        $stmt=$pos_db->prepare("UPDATE printer_setting SET nama=?, jenis=?, alamat=?, lebar=?, driver=?, is_default=? WHERE id=? AND device_id=?");
        $stmt->bind_param("ssssiiis",$nama,$jenis,$alamat,$lebar,$driver,$isDef,$id,$device);
    } else {
        $stmt=$pos_db->prepare("INSERT INTO printer_setting (device_id,nama,jenis,alamat,lebar,driver,is_default) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssssi",$device,$nama,$jenis,$alamat,$lebar,$driver,$isDef);
    }
    $stmt->execute(); $stmt->close();
    $pos_db->commit();
    echo json_encode(['ok'=>true,'msg'=>'Tersimpan']);
} catch(Exception $e){
    $pos_db->rollback();
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
