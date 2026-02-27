<?php
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';
require_once '../../inc/functions.php';
require_once '../../inc/url.php';

// Pastikan user login
requireLogin();

// Pastikan device sudah terdaftar
requireDevice();

// Update last_seen device di master
$fingerprint = hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);

$stmtUpdate = $master_db->prepare("
    UPDATE master_device 
    SET last_seen = NOW()
    WHERE device_fingerprint = ?
");
$stmtUpdate->bind_param("s", $fingerprint);
$stmtUpdate->execute();

// Ambil session toko
if (!isset($_SESSION['toko_id'])) {
    header('Location: ../../../pilih_gudang.php');
    exit;
}

$toko_id = $_SESSION['toko_id'];

// Ambil data user saat ini
$user = getCurrentUser();

// Jika user tidak ditemukan, redirect ke login
if (!$user) {
    header('Location: ' . app_url('/public/admin/login.php'));
    exit;
}

// ==============================
// Statistik
// ==============================

// Total produk
$stmt = $pos_db->prepare("SELECT COUNT(*) as total FROM produk WHERE toko_id = ? AND deleted_at IS NULL");
$stmt->bind_param('i', $toko_id);
$stmt->execute();
$result = $stmt->get_result();
$total_produk = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Total penjualan hari ini
$stmt = $pos_db->prepare("SELECT COUNT(*) as total FROM penjualan WHERE toko_id = ? AND DATE(dibuat_pada) = CURDATE()");
$stmt->bind_param('i', $toko_id);
$stmt->execute();
$result = $stmt->get_result();
$total_penjualan_hari_ini = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

// Total pelanggan
$stmt = $pos_db->prepare("SELECT COUNT(*) as total FROM pelanggan WHERE toko_id = ? AND deleted_at IS NULL");
$stmt->bind_param('i', $toko_id);
$stmt->execute();
$result = $stmt->get_result();
$total_pelanggan = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { font-family: Arial; background:#f4f6f8 }
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .stats { display:flex; gap:20px; margin-bottom:30px; }
        .stat { background:#fff; padding:20px; border-radius:8px; flex:1; text-align:center; box-shadow:0 2px 5px rgba(0,0,0,0.1); }
        .menu ul { list-style:none; padding:0; display:flex; gap:15px; flex-wrap:wrap; }
        .menu ul li { background:#fff; padding:15px 20px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1); }
        .menu ul li a { text-decoration:none; color:#fff; font-weight:bold; }
    </style>
</head>
<body>
    <?php include '../../inc/header.php'; ?>

    <div class="container">
        <h1>Dashboard</h1>
        <p>Selamat datang, <?php echo htmlspecialchars($user['nama'] ?? 'Guest'); ?></p>

        <div class="stats">
            <div class="stat">
                <h3>Total Produk</h3>
                <p><?php echo $total_produk; ?></p>
            </div>
            <div class="stat">
                <h3>Penjualan Hari Ini</h3>
                <p><?php echo $total_penjualan_hari_ini; ?></p>
            </div>
            <div class="stat">
                <h3>Total Pelanggan</h3>
                <p><?php echo $total_pelanggan; ?></p>
            </div>
        </div>
    </div>
</body>
</html>
