<?php
require_once '../inc/config.php'; // mysqli $conn

header('Content-Type: application/json');

$license = $_POST['license_key'] ?? '';

if (!$license) {
    echo json_encode(['success'=>false,'message'=>'License kosong']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM master_license WHERE license_key=? LIMIT 1");
$stmt->bind_param('s',$license);
$stmt->execute();
$lic = $stmt->get_result()->fetch_assoc();

if (!$lic) {
    echo json_encode(['success'=>false,'message'=>'License tidak ditemukan']);
    exit;
}

// Ambil data toko
$stmt = $conn->prepare("SELECT * FROM clients WHERE client_id=?");
$stmt->bind_param('i', $lic['toko_id']);
$stmt->execute();
$toko = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'toko_id' => $toko['client_id'],
    'nama_toko' => $toko['nama_toko'],
    'status' => $lic['status'], // aktif / expired / blokir
    'expired_at' => $lic['expired_at']
]);
