<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once '../inc/config.php'; require_once '../inc/db.php';
header('Content-Type: application/json');

$device = $_SESSION['device_id'] ?? ($_GET['device_id'] ?? '');
if(!$device) exit(json_encode(['ok'=>false,'msg'=>'device_id kosong']));
$userId = $_SESSION['user_id'] ?? null;

$stmt = $pos_db->prepare("SELECT * FROM printer_setting WHERE device_id=? ORDER BY is_default DESC, nama");
$stmt->bind_param("s", $device);
$stmt->execute();
$res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo json_encode(['ok'=>true,'data'=>$res]);
