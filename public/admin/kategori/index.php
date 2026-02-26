<?php
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = $_SESSION['toko_id'];

// Ambil kategori
$query = "SELECT k.*, i.nama_kategori as induk_nama 
          FROM kategori_produk k 
          LEFT JOIN kategori_produk i ON k.induk_id = i.kategori_id
          WHERE k.toko_id = $toko_id AND k.deleted_at IS NULL 
          ORDER BY k.nama_kategori";
$result = $pos_db->query($query);
?>
<!DOCTYPE html>
<html>
<head></head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Kategori</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../../inc/header.php'; ?>
    <div class="container">
        <h1>Daftar Kategori</h1>
        <a href="tambah.php" class="btn">Tambah Kategori</a>
        <table border="1" cellpadding="5">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Kategori</th>
                    <th>Induk</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['kategori_id']; ?></td>
                    <td><?php echo htmlspecialchars($row['nama_kategori']); ?></td>
                    <td><?php echo htmlspecialchars($row['induk_nama']); ?></td>
                    <td>
                        <a href="edit.php?id=<?php echo $row['kategori_id']; ?>">Edit</a>
                        <a href="hapus.php?id=<?php echo $row['kategori_id']; ?>" onclick="return confirm('Hapus?')">Hapus</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>