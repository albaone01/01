<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id     = (int)($data['id'] ?? 0);
$status = (int)($data['status'] ?? 0);
if(!$id) exit(json_encode(['ok'=>false,'msg'=>'ID kosong']));

$stmt = $pos_db->prepare("UPDATE produk SET aktif=? WHERE produk_id=?");
$stmt->bind_param("ii", $status, $id);
$stmt->execute();
$stmt->close();

echo json_encode(['ok'=>true,'msg'=>'Status diperbarui']);
