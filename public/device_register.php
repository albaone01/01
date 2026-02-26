<?php
session_start();
require_once '../inc/config.php';   // Master DB config
require_once '../inc/db.php';       // Master DB connection
require_once '../inc/functions.php'; 

$error = '';
$success = '';

function debug($msg) {
    // Aktifkan hanya jika ingin debug
    // echo '<pre>'; print_r($msg); echo '</pre>';
}

/* =========================
   STEP 1 : SETUP TOKO
========================= */
if (isset($_POST['setup_toko'])) {

    $nama_toko   = trim($_POST['nama_toko']);
    $license     = trim($_POST['license_key']);

    $db_host = trim($_POST['db_host']);
    $db_port = !empty($_POST['db_port']) ? (int)$_POST['db_port'] : 3307;
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    $db_name = trim($_POST['db_name']);

    if (empty($nama_toko) || empty($license) || empty($db_host) || empty($db_user) || empty($db_name)) {
        $error = 'Semua data wajib diisi';
    } else {

        // TEST KONEKSI DATABASE TOKO
        $toko_db = @new mysqli($db_host,$db_user, $db_pass, $db_name, $db_port);
        if ($toko_db->connect_errno) {
            $error = 'Gagal koneksi database toko: ' . $toko_db->connect_error;
        }

        // CEK LICENSE DI MASTER SYSTEM
        if (!$error) {
            $stmt = $db->prepare("SELECT id, status, expired_at FROM master_license WHERE license_key = ? AND status = 'aktif'");
            $stmt->bind_param('s', $license);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 0) {
                $error = 'License tidak valid atau sudah nonaktif';
            } else {
                $license_info = $res->fetch_assoc();

                // SIMPAN TOKO DI MASTER SYSTEM
                $stmt = $db->prepare("
                    INSERT INTO toko 
                    (nama_toko, license_key, db_host, db_name, db_user, db_pass, aktif)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");

                $db_pass_enc = password_hash($db_pass, PASSWORD_DEFAULT);
                $stmt->bind_param(
                    'ssssss',
                    $nama_toko,
                    $license,
                    $db_host,
                    $db_name,
                    $db_user,
                    $db_pass_enc
                );

                if ($stmt->execute()) {
                    $_SESSION['toko_id'] = $stmt->insert_id;
                    $_SESSION['toko_db'] = [
                        'db_host' => $db_host,
                        'db_port' => $db_port,
                        'db_user' => $db_user,
                        'db_pass' => $db_pass,
                        'db_name' => $db_name
                    ];
                    $success = 'Setup toko berhasil, lanjut registrasi device';
                } else {
                    $error = 'Gagal menyimpan toko master: ' . $stmt->error;
                }
            }
        }
    }
}

/* =========================
   STEP 2 : REGISTRASI DEVICE
========================= */
if (isset($_POST['register_device']) && isset($_SESSION['toko_id'])) {

    $toko_id = $_SESSION['toko_id'];
    $nama_device = trim($_POST['nama_device']);
    $tipe = $_POST['tipe'];
    $ip = $_SERVER['REMOTE_ADDR'];

    if (empty($nama_device) || !in_array($tipe, ['kasir','admin','gudang'])) {
        $error = 'Data device tidak valid';
    } else {

        // CEK SESSION TOKO_DB ADA ATAU TIDAK
        if (!isset($_SESSION['toko_db'])) {
            $error = 'Data toko belum ada. Silakan setup toko dulu.';
        } else {
            $cfg = $_SESSION['toko_db'];
            $toko_db = @new mysqli(
                $cfg['db_host'],
                $cfg['db_user'],
                $cfg['db_pass'],
                $cfg['db_name'],
                $cfg['db_port'] ?? 3307
            );

            if ($toko_db->connect_errno) {
                $error = 'Gagal koneksi database toko lokal: ' . $toko_db->connect_error;
            } else {
                // CEK IP GANDA
                $stmt = $toko_db->prepare("SELECT device_id FROM device WHERE ip_address = ?");
                $stmt->bind_param('s', $ip);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res->num_rows > 0) {
                    $error = 'IP sudah terdaftar';
                } else {
                    // SIMPAN DEVICE DI DATABASE POS LOKAL
                    $stmt = $toko_db->prepare("
                        INSERT INTO device (toko_id, nama_device, ip_address, tipe, aktif, dibuat_pada)
                        VALUES (?, ?, ?, ?, 1, NOW())
                    ");
                    $stmt->bind_param('isss', $toko_id, $nama_device, $ip, $tipe);
                    $stmt->execute();
                    
                    // SIMPAN DEVICE JUGA KE MASTER DB
                    $stmt_master = $db->prepare("
                        INSERT INTO master_device (toko_id, device_name, ip_address, tipe, last_seen)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt_master->bind_param('isss', $toko_id, $nama_device, $ip, $tipe);
                    $stmt_master->execute();

                    // Hapus session toko, redirect ke login/dashboard
                    unset($_SESSION['toko_id'], $_SESSION['toko_db']);
                    header("Location: login.php");
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup Toko & Registrasi Device</title>
    <style>
        body { font-family: Arial; background:#f4f6f8 }
        .box { background:#fff; padding:20px; max-width:500px; margin:40px auto; border-radius:8px }
        input, select, button { width:100%; padding:10px; margin-top:8px }
        h2 { margin-top:0 }
        .error { color:#b00020 }
        .success { color:#0a7c2f }
        .login-btn { display:inline-block;padding:10px 20px;background:#0a7c2f;color:#fff;border-radius:5px;text-decoration:none;margin-top:10px; }
    </style>
</head>
<body>

<div class="box">
    <h2>Setup Toko</h2>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success && !isset($_SESSION['toko_id'])): ?>
        <p class="success"><?= htmlspecialchars($success) ?></p>
        <a href="login.php" class="login-btn">Login Sekarang</a>
    <?php endif; ?>

    <?php if (!isset($_SESSION['toko_id'])): ?>
    <form method="post">
        <input name="nama_toko" placeholder="Nama Toko" required>
        <input name="license_key" placeholder="License Key" required>

        <hr>

        <input name="db_host" placeholder="DB Host / IP" required>
        <input name="db_user" placeholder="DB Username" required>
        <input name="db_pass" placeholder="DB Password">
        <input name="db_name" placeholder="DB Name" required>
        <input name="db_port" placeholder="DB Port (optional)">

        <button name="setup_toko">Simpan & Lanjutkan</button>
    </form>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['toko_id'])): ?>
<div class="box">
    <h2>Registrasi Device</h2>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post">
        <input name="nama_device" placeholder="Nama Device" required>
        <select name="tipe">
            <option value="kasir">Kasir</option>
            <option value="admin">Admin</option>
            <option value="gudang">Gudang</option>
        </select>
        <p>IP Anda: <?= $_SERVER['REMOTE_ADDR'] ?></p>
        <button name="register_device">Daftarkan Device</button>
    </form>
</div>
<?php endif; ?>

</body>
</html>
