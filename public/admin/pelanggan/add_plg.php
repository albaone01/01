<?php
require_once __DIR__ . '/../../../inc/config.php';
require_once __DIR__ . '/../../../inc/db.php';
require_once __DIR__ . '/../../../inc/auth.php';
require_once __DIR__ . '/../../../inc/functions.php';

requireLogin();
requireDevice();

$toko_id = (int)($_SESSION['toko_id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_pelanggan = trim($_POST['kode_pelanggan'] ?? '');
    $nama = trim($_POST['nama_pelanggan'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $jenis_customer = trim($_POST['jenis_customer'] ?? '');
    $flat_diskon = (float)($_POST['flat_diskon'] ?? 0);
    $masa_berlaku_tahun = (int)($_POST['masa_berlaku'] ?? 1);
    
    if ($masa_berlaku_tahun < 1) $masa_berlaku_tahun = 1;

    if ($nama === '') {
        $error = 'Nama wajib diisi.';
    } else {
        $stmt = $pos_db->prepare("INSERT INTO pelanggan (toko_id, kode_pelanggan, nama_pelanggan, telepon, alamat, jenis_customer, flat_diskon) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssd", $toko_id, $kode_pelanggan, $nama, $telepon, $alamat, $jenis_customer, $flat_diskon);
        if ($stmt->execute()) {
            $pelanggan_id = $stmt->insert_id;
            
            // Auto-create pelanggan_toko entry dengan exp otomatis
            $tanggal_daftar = date('Y-m-d');
            $exp_date = date('Y-m-d', strtotime("+{$masa_berlaku_tahun} years"));
            
            $stmt2 = $pos_db->prepare("INSERT INTO pelanggan_toko (pelanggan_id, toko_id, tanggal_daftar, masa_berlaku, exp, exp_poin) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("iisiss", $pelanggan_id, $toko_id, $tanggal_daftar, $masa_berlaku_tahun, $exp_date, $exp_date);
            $stmt2->execute();
            $stmt2->close();
            
            header("Location: index.php");
            exit;
        }
        $error = 'Gagal menyimpan: ' . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pelanggan</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        form div { margin-bottom: 12px; }
        label { display: inline-block; width: 150px; font-weight: bold; }
        input, select, textarea { padding: 6px; width: 200px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../../inc/header.php'; ?>
<div class="container">
    <h1>Tambah Pelanggan</h1>
    <?php if ($error !== ''): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
        <div>
            <label>Kode Pelanggan</label>
            <input type="text" name="kode_pelanggan" placeholder="Kode Pelanggan">
        </div>
        <div>
            <label>Nama Pelanggan</label>
            <input type="text" name="nama_pelanggan" placeholder="Nama Pelanggan" required>
        </div>
        <div>
            <label>Telepon</label>
            <input type="text" name="telepon" placeholder="Telepon">
        </div>
        <div>
            <label>Alamat</label>
            <textarea name="alamat" placeholder="Alamat" rows="2"></textarea>
        </div>
        <div>
            <label>Jenis Customer</label>
            <select name="jenis_customer">
                <option value="">- Pilih Jenis -</option>
                <option value="Retail">Retail</option>
                <option value="Grosir">Grosir</option>
                <option value="Reseller">Reseller</option>
                <option value="Member">Member</option>
            </select>
        </div>
        <div>
            <label>Flat Diskon (%)</label>
            <input type="number" name="flat_diskon" placeholder="0" step="0.01" min="0" max="100" value="0">
        </div>
        <div>
            <label>Masa Berlaku (Tahun)</label>
            <input type="number" name="masa_berlaku" value="1" min="1" max="10" step="1">
        </div>
        <button type="submit">Simpan</button>
        <a href="index.php" class="btn">Kembali</a>
    </form>
</div>
</body>
</html>
