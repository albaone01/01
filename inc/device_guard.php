<?php
require_once __DIR__ . '/db.php'; // pastikan db.php di-include dulu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function device_ip_candidates(string $ip): array {
    $ip = trim($ip);
    if ($ip === '') return [];
    $candidates = [$ip];

    // Samakan loopback IPv4/IPv6 agar Electron (127.0.0.1) dan browser (::1)
    // di mesin yang sama tetap dianggap device lokal yang sama.
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === '0:0:0:0:0:0:0:1') {
        $candidates[] = '127.0.0.1';
        $candidates[] = '::1';
        $candidates[] = '0:0:0:0:0:0:0:1';
    }

    return array_values(array_unique($candidates));
}

function checkDevice() {
    global $pos_db; // gunakan pos_db
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $candidates = device_ip_candidates($ip);
    if (!$candidates) return false;

    if (count($candidates) === 1) {
        $stmt = $pos_db->prepare("
            SELECT device_id, toko_id, nama_device, tipe
            FROM device
            WHERE ip_address = ?
              AND aktif = 1
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('s', $candidates[0]);
    } else {
        $stmt = $pos_db->prepare("
            SELECT device_id, toko_id, nama_device, tipe
            FROM device
            WHERE ip_address IN (?,?,?)
              AND aktif = 1
              AND deleted_at IS NULL
            ORDER BY CASE
                WHEN ip_address = ? THEN 0
                WHEN ip_address = ? THEN 1
                ELSE 2
            END
            LIMIT 1
        ");
        $first = $candidates[0];
        $second = $candidates[1] ?? $first;
        $third = $candidates[2] ?? $first;
        $stmt->bind_param('sssss', $first, $second, $third, $first, $second);
    }

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
