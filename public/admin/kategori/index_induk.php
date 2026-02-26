<?php
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = $_SESSION['toko_id'];

// Ambil kategori induk
$query = "SELECT * FROM kategori_produk 
          WHERE toko_id = $toko_id AND induk_id IS NULL AND deleted_at IS NULL
          ORDER BY nama_kategori";
$result = $pos_db->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Daftar Kategori Induk</title>
    <link rel="stylesheet" href='/assets/css/style.css">
</head>
<body>
    <?php include '../../inc/header.php'; ?>
    <div class="container">
        <h1>Daftar Kategori Induk</h1>
        <a href="tambah_induk.php" class="btn">Tambah Kategori Induk</a>
        <table border="1" cellpadding="5">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Kategori Induk</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['kategori_id'] ?></td>
                    <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                    <td>
                        <a href="edit_induk.php?id=<?= $row['kategori_id'] ?>">Edit</a>
                        <a href="hapus_induk.php?id=<?= $row['kategori_id'] ?>" onclick="return confirm('Hapus kategori induk?')">Hapus</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
