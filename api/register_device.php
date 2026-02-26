<?php
require_once '../inc/config.php';

header('Content-Type: application/json');

$license = $_POST['license_key'] ?? '';
$device_name = $_POST['device_name'] ?? '';
$tipe = $_POST['tipe'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'];

if (!$license || !$device_name || !in_array($tipe,['kasir','admin','gudang'])) {
    echo json_encode(['success'=>false,'message'=>'Data device tidak valid']);
    exit;
}

// Ambil license
$stmt = $conn->prepare("SELECT * FROM master_license WHERE license_key=? LIMIT 1");
$stmt->bind_param('s',$license);
$stmt->execute();
$lic = $stmt->get_result()->fetch_assoc();

if (!$lic || $lic['status'] != 'aktif') {
    echo json_encode(['success'=>false,'message'=>'License tidak valid atau nonaktif']);
    exit;
}

// CEK MAX DEVICE (opsional)
$stmt = $conn->prepare("SELECT COUNT(*) c FROM master_device WHERE license_key=?");
$stmt->bind_param('s',$license);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['c'] ?? 0;

// if ($count >= $lic['max_device']) ...

// Simpan device
$stmt = $conn->prepare("INSERT INTO master_device (toko_id, device_name, ip_address, tipe, last_seen) VALUES (?,?,?,?,NOW())");
$stmt->bind_param('isss',$lic['toko_id'],$device_name,$ip,$tipe);
$stmt->execute();

echo json_encode(['success'=>true,'message'=>'Device terdaftar']);
