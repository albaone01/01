<?php
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = $_SESSION['toko_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $gudang_id = (int)$_POST['gudang_id'];
    $stmt = $db->prepare("SELECT gudang_id FROM gudang WHERE toko_id = ? AND gudang_id = ? AND aktif = 1 AND deleted_at IS NULL");
    $stmt->bind_param('ii', $toko_id, $gudang_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['gudang_id'] = $gudang_id;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Gudang tidak valid';
    }
}

$gudang_list = $db->prepare("SELECT gudang_id, nama_gudang FROM gudang WHERE toko_id = ? AND aktif = 1 AND deleted_at IS NULL");
$gudang_list->bind_param('i', $toko_id);
$gudang_list->execute();
$result = $gudang_list->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pilih Gudang</title>
    <link rel="stylesheet" href='/assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Pilih Gudang</h1>
        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post">
            <div>
                <label>Gudang:</label>
                <select name="gudang_id" required>
                    <option value="">Pilih Gudang</option>
                    <?php while($gudang = $result->fetch_assoc()): ?>
                        <option value="<?php echo $gudang['gudang_id']; ?>"><?php echo htmlspecialchars($gudang['nama_gudang']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit">Pilih</button>
        </form>
    </div>
</body>
</html>