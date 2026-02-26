<?php
require_once __DIR__ . '/../../../inc/config.php';
require_once __DIR__ . '/../../../inc/db.php';
require_once __DIR__ . '/../../../inc/auth.php';
require_once __DIR__ . '/../../../inc/functions.php';

requireLogin();
requireDevice();

$toko_id = (int)($_SESSION['toko_id'] ?? 0);
$error = '';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pos_db->prepare("SELECT pelanggan_id, kode_pelanggan, nama_pelanggan, telepon, alamat, jenis_customer, flat_diskon FROM pelanggan WHERE pelanggan_id=? AND toko_id=? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param("ii", $id, $toko_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    die("Pelanggan tidak ditemukan");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_pelanggan = trim($_POST['kode_pelanggan'] ?? '');
    $nama = trim($_POST['nama_pelanggan'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $jenis_customer = trim($_POST['jenis_customer'] ?? '');
    $flat_diskon = (float)($_POST['flat_diskon'] ?? 0);

    if ($nama === '') {
        $error = "Nama wajib diisi.";
    } else {
        $stmt = $pos_db->prepare("UPDATE pelanggan SET kode_pelanggan=?, nama_pelanggan=?, telepon=?, alamat=?, jenis_customer=?, flat_diskon=? WHERE pelanggan_id=? AND toko_id=?");
        $stmt->bind_param("sssssdii", $kode_pelanggan, $nama, $telepon, $alamat, $jenis_customer, $flat_diskon, $id, $toko_id);
        if ($stmt->execute()) {
            header("Location: index.php");
            exit;
        }
        $error = "Gagal menyimpan: " . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pelanggan</title>
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
    <h1>Edit Pelanggan</h1>
    <?php if ($error !== ''): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
        <div>
            <label>Kode Pelanggan</label>
            <input type="text" name="kode_pelanggan" value="<?= htmlspecialchars((string)($data['kode_pelanggan'] ?? '')) ?>">
        </div>
        <div>
            <label>Nama Pelanggan</label>
            <input type="text" name="nama_pelanggan" value="<?= htmlspecialchars((string)$data['nama_pelanggan']) ?>" required>
        </div>
        <div>
            <label>Telepon</label>
            <input type="text" name="telepon" value="<?= htmlspecialchars((string)($data['telepon'] ?? '')) ?>">
        </div>
        <div>
            <label>Alamat</label>
            <textarea name="alamat" rows="2"><?= htmlspecialchars((string)($data['alamat'] ?? '')) ?></textarea>
        </div>
        <div>
            <label>Jenis Customer</label>
            <select name="jenis_customer">
                <option value="">- Pilih Jenis -</option>
                <option value="Retail" <?= (($data['jenis_customer'] ?? '') === 'Retail') ? 'selected' : '' ?>>Retail</option>
                <option value="Grosir" <?= (($data['jenis_customer'] ?? '') === 'Grosir') ? 'selected' : '' ?>>Grosir</option>
                <option value="Reseller" <?= (($data['jenis_customer'] ?? '') === 'Reseller') ? 'selected' : '' ?>>Reseller</option>
                <option value="Member" <?= (($data['jenis_customer'] ?? '') === 'Member') ? 'selected' : '' ?>>Member</option>
            </select>
        </div>
        <div>
            <label>Flat Diskon (%)</label>
            <input type="number" name="flat_diskon" value="<?= number_format((float)($data['flat_diskon'] ?? 0), 2, '.', '') ?>" step="0.01" min="0" max="100">
        </div>
        <button type="submit">Simpan</button>
        <a href="index.php" class="btn">Kembali</a>
    </form>
</div>
</body>
</html>
