<?php
session_start();

// Ambil data dari form (atau set manual untuk test)
$db_host = '127.0.0.1'; // ganti localhost/127.0.0.1
$db_user = 'root';
$db_pass = '';           // isi password sesuai MySQL
$db_name = 'hyeepos';

// DEBUG: tampilkan semua info koneksi
echo "<pre>";
echo "Host: $db_host\n";
echo "User: $db_user\n";
echo "DB: $db_name\n";

$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);

// Cek koneksi
if ($mysqli->connect_errno) {
    echo "❌ Gagal koneksi!\n";
    echo "Kode Error: " . $mysqli->connect_errno . "\n";
    echo "Pesan Error: " . $mysqli->connect_error . "\n";

    // Cek port jika pakai default 3306
    $port = parse_url($db_host, PHP_URL_PORT) ?: 3306;
    echo "Mencoba port: $port\n";

    // Cek apakah host bisa di-ping
    echo "Coba ping host:\n";
    $ping = (strtolower(PHP_OS_FAMILY) == 'windows') ? `ping -n 1 127.0.0.1` : `ping -c 1 127.0.0.1`;
    echo $ping;
    exit;
}

echo "✅ Koneksi sukses!";
echo "</pre>";
?>
