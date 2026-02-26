<?php
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = $_SESSION['toko_id'];
$error = '';
$success = '';

// Ambil daftar kategori untuk dropdown induk
$query = "SELECT kategori_id, nama_kategori FROM kategori_produk 
          WHERE toko_id = ? AND deleted_at IS NULL ORDER BY nama_kategori";
$stmt = $pos_db->prepare($query);
$stmt->bind_param("i", $toko_id);
$stmt->execute();
$result = $stmt->get_result();
$kategori_list = [];
while($row = $result->fetch_assoc()){
    $kategori_list[] = $row;
}

// Proses form submit
if(isset($_POST['simpan'])){

    $nama_kategori = trim($_POST['nama_kategori']);
    $induk_id = !empty($_POST['induk_id']) ? (int)$_POST['induk_id'] : null;

    // Jika user membuat kategori induk baru
    if(!empty($_POST['induk_baru'])){
        $induk_baru = trim($_POST['induk_baru']);
        if($induk_baru){
            $stmtInduk = $pos_db->prepare("INSERT INTO kategori_produk (nama_kategori, toko_id) VALUES (?, ?)");
            $stmtInduk->bind_param("si", $induk_baru, $toko_id);
            if($stmtInduk->execute()){
                $induk_id = $pos_db->insert_id; // gunakan ID induk baru
            } else {
                $error = "Gagal menambah kategori induk: " . $stmtInduk->error;
            }
        }
    }

    if(empty($nama_kategori)){
        $error = "Nama kategori wajib diisi";
    }

    if(!$error){
        $stmt = $pos_db->prepare("INSERT INTO kategori_produk (nama_kategori, induk_id, toko_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $nama_kategori, $induk_id, $toko_id);
        if($stmt->execute()){
            $success = "Kategori berhasil ditambahkan";
            header("Location: index.php");
            exit;
        } else {
            $error = "Gagal menyimpan kategori: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tambah Kategori</title>
    <link rel="stylesheet" href="../..//assets/css/style.css">
    <style>
        .form-group { margin-bottom: 15px; }
        label { display:block; margin-bottom:5px; font-weight:bold; }
        input, select, button { width:100%; padding:8px; font-size:14px; margin-top:5px; }
        button { background:#2563eb; color:#fff; border:none; border-radius:5px; cursor:pointer; }
        button:hover { background:#1d4ed8; }
        .btn { padding:10px 15px; background:#6b7280; color:#fff; border:none; border-radius:5px; text-decoration:none; display:inline-block; margin-top:10px; }
        .btn:hover { background:#4b5563; }
        .error { color:#b00020; margin-bottom:10px; background:#ffe5e5; padding:8px; border-radius:5px; }
        .success { color:#0a7c2f; margin-bottom:10px; background:#e6ffed; padding:8px; border-radius:5px; }
    </style>
</head>
<body>
    <?php include '../../../inc/header.php'; ?>

    <div class="container">
        <h1>Tambah Kategori</h1>

        <?php if($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nama Kategori</label>
                <input type="text" name="nama_kategori" required placeholder="Nama kategori baru">
            </div>

            <div class="form-group">
                <label>Induk Kategori (opsional)</label>
                <select name="induk_id">
                    <option value="">-- Pilih Induk --</option>
                    <?php foreach($kategori_list as $k): ?>
                        <option value="<?= $k['kategori_id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Atau buat kategori induk baru</label>
                <input type="text" name="induk_baru" placeholder="Nama kategori induk baru">
            </div>

            <button type="submit" name="simpan">Simpan</button>
            <a href="index.php" class="btn">Kembali</a>
        </form>
    </div>
</body>
</html>
