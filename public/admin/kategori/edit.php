<?php
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = $_SESSION['toko_id'];
$error = '';
$success = '';

$kategori_id = (int)($_GET['id'] ?? 0);

if(!$kategori_id){
    header("Location: index.php");
    exit;
}

// Ambil data kategori
$stmt = $pos_db->prepare("SELECT * FROM kategori_produk WHERE kategori_id=? AND toko_id=? AND deleted_at IS NULL");
$stmt->bind_param("ii", $kategori_id, $toko_id);
$stmt->execute();
$result = $stmt->get_result();
$kategori = $result->fetch_assoc();

if(!$kategori){
    header("Location: index.php");
    exit;
}

// Ambil daftar kategori untuk dropdown induk (kecuali diri sendiri)
$stmt2 = $pos_db->prepare("SELECT kategori_id, nama_kategori FROM kategori_produk WHERE toko_id=? AND kategori_id!=? AND deleted_at IS NULL ORDER BY nama_kategori");
$stmt2->bind_param("ii", $toko_id, $kategori_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
$kategori_list = [];
while($row = $res2->fetch_assoc()){
    $kategori_list[] = $row;
}

// Proses form submit
if(isset($_POST['update'])){
    $nama_kategori = trim($_POST['nama_kategori']);
    $induk_id = !empty($_POST['induk_id']) ? (int)$_POST['induk_id'] : null;

    if(empty($nama_kategori)){
        $error = "Nama kategori wajib diisi";
    } else {
        $stmt = $pos_db->prepare("UPDATE kategori_produk SET nama_kategori=?, induk_id=? WHERE kategori_id=? AND toko_id=?");
        $stmt->bind_param("siii", $nama_kategori, $induk_id, $kategori_id, $toko_id);
        if($stmt->execute()){
            header("Location: index.php");
            exit;
        } else {
            $error = "Gagal update: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Kategori</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="container">
    <h1>Edit Kategori</h1>

    <?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
    <?php if($success): ?><div class="success"><?= $success ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Nama Kategori</label>
            <input type="text" name="nama_kategori" value="<?= htmlspecialchars($kategori['nama_kategori']) ?>" required>
        </div>

        <div class="form-group">
            <label>Induk Kategori (opsional)</label>
            <select name="induk_id">
                <option value="">-- Pilih Induk --</option>
                <?php foreach($kategori_list as $k): ?>
                    <option value="<?= $k['kategori_id'] ?>" <?= $kategori['induk_id']==$k['kategori_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['nama_kategori']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" name="update">Update</button>
        <a href="index.php" class="btn">Kembali</a>
    </form>
</div>
</body>
</html>
