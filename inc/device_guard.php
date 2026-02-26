<?php
require_once __DIR__ . '/db.php'; // pastikan db.php di-include dulu
session_start();

function checkDevice() {
    global $pos_db; // gunakan pos_db
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $pos_db->prepare("SELECT device_id, toko_id, nama_device, tipe FROM device WHERE ip_address = ? AND aktif = 1 AND deleted_at IS NULL");
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $device = $result->fetch_assoc();
        $_SESSION['device_id'] = $device['device_id'];
        $_SESSION['toko_id'] = $device['toko_id'];
        $_SESSION['device_tipe'] = $device['tipe'];
        return true;
    }
    return false;
}
