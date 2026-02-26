<?php
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = $_SESSION['toko_id'];
$error = '';
$success = '';

$id = (int)($_GET['id'] ?? 0);

// Ambil data kategori induk
$stmt = $db->prepare("SELECT * FROM kategori_produk WHERE kategori_id=? AND toko_id=? AND deleted_at IS NULL");
$stmt->bind_param("ii", $id, $toko_id);
$stmt->execute();
$res = $stmt->get_result();
$kategori = $res->fetch_assoc();

if(!$kategori){
    die("Kategori induk tidak ditemukan.");
}

if(isset($_POST['update'])){
    $nama = trim($_POST['nama_kategori']);
    if(empty($nama)){
        $error = "Nama kategori wajib diisi";
    } else {
        $stmt = $db->prepare("UPDATE kategori_produk SET nama_kategori=? WHERE kategori_id=? AND toko_id=?");
        $stmt->bind_param("sii", $nama, $id, $toko_id);
        if($stmt->execute()){
            $success = "Kategori induk berhasil diupdate";
            $kategori['nama_kategori'] = $nama;
        } else {
            $error = "Terjadi kesalahan saat update";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Kategori Induk</title>
    <link rel="stylesheet" href='/assets/css/style.css">
</head>
<body>
    <?php include '../../inc/header.php'; ?>
    <div class="container">
        <h1>Edit Kategori Induk</h1>

        <?php if($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="nama_kategori" value="<?= htmlspecialchars($kategori['nama_kategori']) ?>" required>
            <button type="submit" name="update">Update</button>
        </form>

        <p><a href="index_induk.php">Kembali ke Daftar Kategori Induk</a></p>
    </div>
</body>
</html>
