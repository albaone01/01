<?php
/**
 * DEVICE REGISTER + INSTALLER
 * POS OFFLINE - PHP NATIVE
 */

/* =====================================================
 * 1. CEK APAKAH SUDAH TERINSTAL
 * ===================================================== */
$configFile = __DIR__ . '/../inc/config.php';
$lockFile   = __DIR__ . '/../inc/install.lock';

$isInstalled = false;

if (file_exists($configFile) && filesize($configFile) > 50) {
    require_once $configFile;

    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
        @$testConn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$testConn->connect_error) {
            $cek = $testConn->query("SHOW TABLES LIKE 'toko'");
            if ($cek && $cek->num_rows > 0) {
                $isInstalled = true;
            }
            $testConn->close();
        }
    }
}

/* =====================================================
 * 2. KUNCI INSTALLER BERDASARKAN IP
 * ===================================================== */
if (file_exists($lockFile)) {
    $allowedIp = trim(file_get_contents($lockFile));
    if ($_SERVER['REMOTE_ADDR'] !== $allowedIp) {
        die('Akses installer ditolak');
    }
}

/* =====================================================
 * 3. PROSES INSTALLER
 * ===================================================== */
$installError = '';
$installSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'install') {

    if ($isInstalled) {
        die('Installer sudah dikunci');
    }

    $db_host   = trim($_POST['db_host']);
    $db_user   = trim($_POST['db_user']);
    $db_pass   = $_POST['db_pass'];
    $db_name   = trim($_POST['db_name']);
    $nama_toko = trim($_POST['nama_toko']);
    $lisensi   = trim($_POST['kode_lisensi']);

    if (!$db_host || !$db_user || !$db_name || !$nama_toko) {
        $installError = 'Data instalasi belum lengkap';
    } else {
        $testConn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($testConn->connect_error) {
            $installError = 'Koneksi database gagal';
        } else {

            // TULIS CONFIG
            $configContent = "<?php\n";
            $configContent .= "define('DB_HOST', '".addslashes($db_host)."');\n";
            $configContent .= "define('DB_USER', '".addslashes($db_user)."');\n";
            $configContent .= "define('DB_PASS', '".addslashes($db_pass)."');\n";
            $configContent .= "define('DB_NAME', '".addslashes($db_name)."');\n";

            if (!file_put_contents($configFile, $configContent)) {
                $installError = 'Gagal menulis config.php';
            } else {

                require_once $configFile;
                require_once __DIR__ . '/../inc/db.php';

                // INSERT TOKO
                $stmt = $db->prepare(
                    "INSERT INTO toko (nama_toko, kode_lisensi, aktif) VALUES (?, ?, 1)"
                );
                $stmt->bind_param('ss', $nama_toko, $lisensi);

                if ($stmt->execute()) {
                    file_put_contents($lockFile, $_SERVER['REMOTE_ADDR']);
                    chmod($configFile, 0600);

                    $installSuccess = 'Instalasi berhasil. Silakan daftarkan device ADMIN.';
                    $isInstalled = true;
                } else {
                    unlink($configFile);
                    $installError = 'Gagal menyimpan data toko';
                }

                $stmt->close();
                $testConn->close();
            }
        }
    }
}

/* =====================================================
 * 4. MODE NORMAL - REGISTRASI DEVICE
 * ===================================================== */
$error = '';
$success = '';

if ($isInstalled) {
    require_once $configFile;
    require_once __DIR__ . '/../inc/db.php';
    require_once __DIR__ . '/../inc/functions.php';

    // CEK APAKAH DEVICE PERTAMA
    $totalDevice = $db->query(
        "SELECT COUNT(*) AS total FROM device WHERE deleted_at IS NULL"
    )->fetch_assoc()['total'];

    $isFirstDevice = ($totalDevice == 0);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['mode'])) {

        $toko_id = (int)$_POST['toko_id'];
        $nama_device = trim($_POST['nama_device']);
        $tipe = $isFirstDevice ? 'admin' : $_POST['tipe'];

        if (!$toko_id || !$nama_device) {
            $error = 'Data device tidak valid';
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];

            $cek = $db->prepare(
                "SELECT device_id FROM device WHERE ip_address = ? AND deleted_at IS NULL"
            );
            $cek->bind_param('s', $ip);
            $cek->execute();

            if ($cek->get_result()->num_rows > 0) {
                $error = 'IP sudah terdaftar';
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO device (toko_id, nama_device, ip_address, tipe)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param('isss', $toko_id, $nama_device, $ip, $tipe);

                if ($stmt->execute()) {
                    $success = 'Device berhasil didaftarkan';
                } else {
                    $error = 'Gagal mendaftarkan device';
                }
            }
        }
    }

    $toko_list = $db->query(
        "SELECT toko_id, nama_toko FROM toko WHERE aktif = 1"
    );
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>POS - Registrasi Device</title>
    <style>
        body { font-family: Arial; background:#f5f5f5 }
        .container { width:500px; margin:30px auto; background:#fff; padding:20px }
        input, select, button { width:100%; padding:8px; margin-bottom:10px }
        .alert { padding:10px; margin-bottom:10px }
        .error { background:#f8d7da }
        .success { background:#d4edda }
        .info { background:#d1ecf1 }
    </style>
</head>
<body>
<div class="container">
<h2>Registrasi Device POS</h2>

<?php if (!$isInstalled): ?>
    <div class="alert info">Sistem belum terinstal</div>

    <?php if ($installError): ?><div class="alert error"><?=htmlspecialchars($installError)?></div><?php endif; ?>
    <?php if ($installSuccess): ?><div class="alert success"><?=$installSuccess?></div><?php endif; ?>

    <form method="post">
        <input type="hidden" name="mode" value="install">

        <h3>Database</h3>
        <input name="db_host" placeholder="DB Host" value="localhost" required>
        <input name="db_user" placeholder="DB User" required>
        <input name="db_pass" placeholder="DB Password">
        <input name="db_name" placeholder="DB Name" required>

        <h3>Toko</h3>
        <input name="nama_toko" placeholder="Nama Toko" required>
        <input name="kode_lisensi" placeholder="Kode Lisensi (opsional)">

        <button type="submit">Install Sistem</button>
    </form>

<?php else: ?>

    <?php if ($error): ?><div class="alert error"><?=$error?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?=$success?></div><?php endif; ?>

    <form method="post">
        <select name="toko_id" required>
            <option value="">Pilih Toko</option>
            <?php while($t=$toko_list->fetch_assoc()): ?>
                <option value="<?=$t['toko_id']?>"><?=$t['nama_toko']?></option>
            <?php endwhile; ?>
        </select>

        <input name="nama_device" placeholder="Nama Device" required>

        <?php if ($isFirstDevice): ?>
            <input type="hidden" name="tipe" value="admin">
            <div class="alert info">Device pertama otomatis ADMIN</div>
        <?php else: ?>
            <select name="tipe" required>
                <option value="kasir">Kasir</option>
                <option value="admin">Admin</option>
                <option value="gudang">Gudang</option>
            </select>
        <?php endif; ?>

        <button type="submit">Daftarkan Device</button>
    </form>

<?php endif; ?>

</div>
</body>
</html>
