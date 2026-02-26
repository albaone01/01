<?php
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = $_SESSION['toko_id'];
$error = '';
$success = '';

if(isset($_POST['tambah'])){
    $nama = trim($_POST['nama_kategori']);
    if(empty($nama)){
        $error = "Nama kategori induk wajib diisi";
    } else {
        $stmt = $pos_db->prepare("INSERT INTO kategori_produk (nama_kategori, toko_id) VALUES (?, ?)");
        $stmt->bind_param("si", $nama, $toko_id);
        if($stmt->execute()){
            $success = "Kategori induk berhasil ditambahkan";
        } else {
            $error = "Terjadi kesalahan saat menyimpan";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tambah Kategori Induk</title>
    <link rel="stylesheet" href='/assets/css/style.css">
</head>
<body>
    <?php include '../../inc/header.php'; ?>
    <div class="container">
        <h1>Tambah Kategori Induk</h1>

        <?php if($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="nama_kategori" placeholder="Nama Kategori Induk" required>
            <button type="submit" name="tambah">Simpan</button>
        </form>

        <p><a href="index_induk.php">Kembali ke Daftar Kategori Induk</a></p>
    </div>
</body>
</html>
