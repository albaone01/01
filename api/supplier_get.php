<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if(!$id) exit(json_encode(['ok'=>false,'msg'=>'ID kosong']));

$stmt = $pos_db->prepare("SELECT supplier_id, nama_supplier, telepon, alamat, dibuat_pada
                          FROM supplier
                          WHERE supplier_id=? AND deleted_at IS NULL
                          LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if(!$row) exit(json_encode(['ok'=>false,'msg'=>'Supplier tidak ditemukan']));

echo json_encode(['ok'=>true,'data'=>$row]);
