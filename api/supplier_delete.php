<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
if(!$id) exit(json_encode(['ok'=>false,'msg'=>'ID kosong']));

$stmt = $pos_db->prepare("UPDATE supplier SET deleted_at=NOW() WHERE supplier_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

echo json_encode(['ok'=>true,'msg'=>'Supplier dihapus']);
