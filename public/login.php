<?php
session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';

/* =========================================================
   HELPER
========================================================= */

function generateFingerprint() {
    return hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
}

function getClientIP() {
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

/* =========================================================
   STATE
========================================================= */
$error = '';
$success = '';
$showLogin  = true;
$showSetup  = false;
$showDevice = false;
$dupDevice  = false;

// Jika diarahkan setelah setup sukses
if (isset($_GET['register_device']) && isset($_SESSION['setup_toko_id'])) {
    $showLogin  = false;
    $showSetup  = false;
    $showDevice = true;
    $success = "Setup toko berhasil. Silakan daftar device untuk aktivasi.";
}

/* =========================================================
   SETUP TOKO (VALIDASI LICENSE MASTER)
========================================================= */
if (isset($_POST['setup_toko'])) {

    $showLogin = false;
    $showSetup = true;

    $nama_toko = trim($_POST['nama_toko']);
    $pemilik   = trim($_POST['pemilik']);
    $alamat    = trim($_POST['alamat']);
    $license   = trim($_POST['license_key']);

    if (empty($nama_toko) || empty($pemilik) || empty($license)) {
        $error = "Semua field wajib diisi";
    } else {

        // 1️⃣ Validasi license
        $stmt = $master_db->prepare("
            SELECT * FROM master_license
            WHERE license_key=? AND status='active'
        ");
        $stmt->bind_param("s", $license);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows == 0) {
            $error = "License tidak valid atau tidak aktif.";
        } else {

            $licenseData = $res->fetch_assoc();
            $toko_id     = $licenseData['toko_id'];
            $expired_at  = $licenseData['expired_at'];
            $grace_days  = $licenseData['grace_days'];
            $max_device  = $licenseData['max_device'];

            // 2️⃣ Cek expired + grace
            $today = date('Y-m-d');
            $expired_plus_grace = date('Y-m-d', strtotime($expired_at . " +$grace_days days"));

            if ($today > $expired_plus_grace) {
                $error = "License sudah expired.";
            } else {

                // Jalankan transaksi di kedua DB agar konsisten
                $master_db->begin_transaction();
                $pos_db->begin_transaction();

                try {

                    // Insert client (master)
                    $stmtClient = $master_db->prepare("
                        INSERT INTO clients (nama_toko, pemilik, alamat, status)
                        VALUES (?, ?, ?, 'active')
                    ");
                    $stmtClient->bind_param("sss", $nama_toko, $pemilik, $alamat);
                    $stmtClient->execute();
                    $client_id = $master_db->insertId();

                    // Update master_toko (master)
                    $stmtUpdate = $master_db->prepare("
                        UPDATE master_toko
                        SET client_id=?, status='aktif'
                        WHERE id=?
                    ");
                    $stmtUpdate->bind_param("ii", $client_id, $toko_id);
                    $stmtUpdate->execute();

                    // Pastikan toko tercatat di DB POS lokal (hindari FK fail saat registrasi device)
                    $stmtPosToko = $pos_db->prepare("
                        INSERT INTO toko (toko_id, nama_toko, alamat, aktif, dibuat_pada)
                        VALUES (?, ?, ?, 1, NOW())
                        ON DUPLICATE KEY UPDATE
                            nama_toko = VALUES(nama_toko),
                            alamat    = VALUES(alamat),
                            deleted_at = NULL,
                            aktif = 1
                    ");
                    $stmtPosToko->bind_param("iss", $toko_id, $nama_toko, $alamat);
                    $stmtPosToko->execute();

                    // Buat user admin default jika belum ada user di toko ini
                    $stmtCekUser = $pos_db->prepare("
                        SELECT COUNT(*) AS total FROM pengguna WHERE toko_id=? AND deleted_at IS NULL
                    ");
                    $stmtCekUser->bind_param("i", $toko_id);
                    $stmtCekUser->execute();
                    $totalUser = $stmtCekUser->get_result()->fetch_assoc()['total'] ?? 0;

                    if ($totalUser == 0) {
                        $defaultEmail = "admin_" . $toko_id . "@local";
                        $defaultPassPlain = "admin123";
                        $hash = password_hash($defaultPassPlain, PASSWORD_DEFAULT);

                        $stmtCreateUser = $pos_db->prepare("
                            INSERT INTO pengguna (toko_id, nama, email, password, peran, aktif, dibuat_pada)
                            VALUES (?, ?, ?, ?, 'owner', 1, NOW())
                        ");
                        $stmtCreateUser->bind_param("isss", $toko_id, $pemilik, $defaultEmail, $hash);
                        $stmtCreateUser->execute();

                        // Simpan agar bisa ditampilkan setelah device terdaftar
                        $_SESSION['setup_default_user'] = [
                            'email' => $defaultEmail,
                            'password' => $defaultPassPlain
                        ];
                    }

                    // Commit keduanya
                    $master_db->commit();
                    $pos_db->commit();

                    // Simpan ke session
                    $_SESSION['setup_toko_id']  = $toko_id;
                    $_SESSION['setup_license']  = $license;
                    $_SESSION['setup_max_dev']  = $max_device;

                    header("Location: login.php?register_device=1");
                    exit;

                } catch (Exception $e) {
                    $master_db->rollback();
                    $pos_db->rollback();
                    $error = "Gagal setup toko.";
                }
            }
        }
    }
}

/* =========================================================
   REGISTER DEVICE
========================================================= */
if (isset($_POST['register_device']) && isset($_SESSION['setup_toko_id'])) {

    $showLogin  = false;
    $showSetup  = false;
    $showDevice = true;

    $toko_id   = $_SESSION['setup_toko_id'];
    $max_dev   = $_SESSION['setup_max_dev'];
    $tipe      = $_POST['tipe'];
    $device_name = trim($_POST['nama_device']);
    $nama_user   = trim($_POST['nama_pengguna'] ?? '');
    $email_user  = trim($_POST['email_pengguna'] ?? '');
    $pass_user   = $_POST['password_pengguna'] ?? '';
    $peran_user  = $_POST['peran_pengguna'] ?? '';

    if (
        empty($device_name) ||
        !in_array($tipe, ['kasir','admin','gudang']) ||
        empty($nama_user) ||
        empty($email_user) ||
        empty($pass_user) ||
        !in_array($peran_user, ['owner','manager','kasir','gudang'])
    ) {
        $error = "Data device tidak valid";
    } else {

        // Cek jumlah device
        $stmtCount = $master_db->prepare("
            SELECT COUNT(*) as total 
            FROM master_device 
            WHERE toko_id=? AND status='active'
        ");
        $stmtCount->bind_param("i", $toko_id);
        $stmtCount->execute();
        $countRes = $stmtCount->get_result()->fetch_assoc();

        if ($countRes['total'] >= $max_dev) {
            $error = "Jumlah device melebihi batas license.";
        } else {

            $fingerprint = generateFingerprint();
            $ip = getClientIP();

            // Cek duplikasi fingerprint atau IP di master
            $stmtDup = $master_db->prepare("
                SELECT id FROM master_device
                WHERE toko_id=? AND (device_fingerprint=? OR ip_address=?) AND status='active'
            ");
            $stmtDup->bind_param("iss", $toko_id, $fingerprint, $ip);
            $stmtDup->execute();
            $dupRes = $stmtDup->get_result();

            if ($dupRes->num_rows > 0) {
                $error = "Device sudah terdaftar untuk toko ini.";
                $dupDevice = true; // tawarkan opsi pakai license & toko lain
            } else {

                // Pastikan email user belum dipakai di toko ini
                $stmtEmail = $pos_db->prepare("
                    SELECT 1 FROM pengguna WHERE toko_id=? AND email=? AND deleted_at IS NULL
                ");
                $stmtEmail->bind_param("is", $toko_id, $email_user);
                $stmtEmail->execute();
                if ($stmtEmail->get_result()->num_rows > 0) {
                    $error = "Email sudah terpakai di toko ini.";
                } else {
                // Transaksi di kedua DB
                $master_db->begin_transaction();
                $pos_db->begin_transaction();
                try {
                    // Simpan ke master (pusat)
                    $stmtDevice = $master_db->prepare("
                        INSERT INTO master_device
                        (toko_id, device_fingerprint, ip_address, tipe, device_name, last_seen, status)
                        VALUES (?, ?, ?, ?, ?, NOW(), 'active')
                    ");
                    $stmtDevice->bind_param("issss",
                        $toko_id,
                        $fingerprint,
                        $ip,
                        $tipe,
                        $device_name
                    );
                    $stmtDevice->execute();

                    // Simpan ke database POS lokal (hyeepos)
                    $stmtPos = $pos_db->prepare("
                        INSERT INTO device (toko_id, nama_device, ip_address, tipe, aktif, dibuat_pada)
                        VALUES (?, ?, ?, ?, 1, NOW())
                    ");
                    $stmtPos->bind_param("isss", $toko_id, $device_name, $ip, $tipe);
                    $stmtPos->execute();
                    $newDeviceId = $pos_db->insertId();

                    // Buat user POS sesuai input
                    $hash = password_hash($pass_user, PASSWORD_DEFAULT);
                    $stmtUser = $pos_db->prepare("
                        INSERT INTO pengguna (toko_id, nama, email, password, peran, aktif, dibuat_pada)
                        VALUES (?, ?, ?, ?, ?, 1, NOW())
                    ");
                    $stmtUser->bind_param("issss", $toko_id, $nama_user, $email_user, $hash, $peran_user);
                    $stmtUser->execute();
                    $newUserId = $pos_db->insertId();

                    $master_db->commit();
                    $pos_db->commit();

                    // Hapus session setup
                    unset($_SESSION['setup_toko_id']);
                    unset($_SESSION['setup_license']);
                    unset($_SESSION['setup_max_dev']);

                    // Auto-login - redirect berdasarkan peran
                    $_SESSION['pengguna_id']   = $newUserId;
                    $_SESSION['pengguna_nama'] = $nama_user;
                    $_SESSION['toko_id']       = $toko_id;
                    $_SESSION['peran']         = $peran_user;
                    $_SESSION['device_id']     = $newDeviceId;

                    if ($peran_user === 'kasir') {
                        header("Location: POS/index.php");
                    } else {
                        header("Location: admin/dashboard.php");
                    }
                    exit;

                } catch (Exception $e) {
                    $master_db->rollback();
                    $pos_db->rollback();
                    $error = "Device gagal disimpan: " . $e->getMessage();
                }
                }
            }
        }
    }
}


/* =========================================================
   LOGIN MANUAL
========================================================= */
// Jika user sudah login, langsung redirect berdasarkan peran
if (isset($_SESSION['pengguna_id'])) {
    if ($_SESSION['peran'] === 'kasir') {
        header("Location: POS/index.php");
    } else {
        header("Location: admin/dashboard.php");
    }
    exit;
}

$error = '';

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pos_db->prepare("
        SELECT * FROM pengguna
        WHERE email=? AND aktif=1 AND deleted_at IS NULL
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($user = $res->fetch_assoc()) {

        if (password_verify($password, $user['password'])) {

            // Simpan session login
            $_SESSION['pengguna_id']   = $user['pengguna_id'];
            $_SESSION['pengguna_nama'] = $user['nama'];
            $_SESSION['toko_id']       = $user['toko_id'];
            $_SESSION['peran']         = $user['peran'];

            // Redirect berdasarkan peran
            if ($user['peran'] === 'kasir') {
                header("Location: POS/index.php");
            } else {
                header("Location: admin/dashboard.php");
            }
            exit;

        } else {
            $error = "Password salah";
        }

    } else {
        $error = "Email tidak ditemukan";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
    <head>
    <meta charset="UTF-8">
    <title>AlbaOne POS</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f4f6f8;
            }

            .container {
                max-width: 500px;
                margin: 40px auto;
            }

            .box {
                background: #fff;
                padding: 25px;
                border-radius: 10px;
                box-shadow: 0 3px 10px rgba(0,0,0,0.05);
                margin-bottom: 20px;
            }

            h2 {
                margin-top: 0;
            }

            input, select, button {
                width: 100%;
                padding: 10px;
                margin-top: 10px;
                border-radius: 6px;
                border: 1px solid #ddd;
                font-size: 14px;
            }

            button {
                background: #2563eb;
                color: #fff;
                border: none;
                cursor: pointer;
            }

            button:hover {
                background: #1d4ed8;
            }

            .link {
                text-align: center;
                margin-top: 15px;
            }

            .link a {
                color: #2563eb;
                text-decoration: none;
            }

            .error {
                background: #ffe5e5;
                color: #b00020;
                padding: 10px;
                border-radius: 6px;
                margin-bottom: 10px;
            }

            .success {
                background: #e6ffed;
                color: #0a7c2f;
                padding: 10px;
                border-radius: 6px;
                margin-bottom: 10px;
            }
        </style>
    </head>
<body>

<div class="container">

<!-- ================= LOGIN ================= -->
<div class="box" style="<?= $showLogin ? 'display:block;' : 'display:none;' ?>">
    <h2>Login POS</h2>

    <?php if ($error && $showLogin): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success && $showLogin): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>

    <div class="link">
        <a href="#" onclick="showSetup()">Belum punya toko? Setup Sekarang</a>
    </div>
</div>

<!-- ================= SETUP TOKO ================= -->
<div class="box" id="setupBox" style="<?= $showSetup ? 'display:block;' : 'display:none;' ?>">
    <h2>Setup Toko</h2>

    <?php if ($error && $showSetup): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success && $showSetup): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
        <input name="nama_toko" placeholder="Nama Toko" required>
        <input name="pemilik" placeholder="Nama Pemilik" required>
        <input name="alamat" placeholder="Alamat">
        <input name="license_key" placeholder="License Key" required>

        <button type="submit" name="setup_toko">
            Simpan & Lanjutkan
        </button>
    </form>

    <div class="link">
        <a href="#" onclick="showLogin()">Kembali ke Login</a>
    </div>
</div>


<!-- ================= REGISTRASI DEVICE ================= -->
<div class="box" style="<?= $showDevice ? 'display:block;' : 'display:none;' ?>">
    <h2>Registrasi Device</h2>

    <?php if ($error && $showDevice): ?>
        <div class="error"><?= $error ?></div>
        <?php if ($dupDevice): ?>
            <div class="link" style="margin-top:10px;">
                <a href="#" onclick="showSetup()">Pakai license &amp; nama toko lain</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($success && $showDevice): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
        <input name="nama_device" placeholder="Nama Device (contoh: Kasir 1)" required>

        <select name="tipe">
            <option value="kasir">Kasir</option>
            <option value="admin">Admin</option>
            <option value="gudang">Gudang</option>
        </select>

        <h3>Data Pengguna Pertama</h3>
        <input name="nama_pengguna" placeholder="Nama Lengkap" required>
        <input type="email" name="email_pengguna" placeholder="Email" required>
        <input type="password" name="password_pengguna" placeholder="Password" required>
        <select name="peran_pengguna" required>
            <option value="owner">Owner</option>
            <option value="manager">Manager</option>
            <option value="kasir">Kasir</option>
            <option value="gudang">Gudang</option>
        </select>

        <button type="submit" name="register_device">
            Daftarkan Device
        </button>
    </form>
</div>

</div>


<script>
function showSetup(){
    document.querySelectorAll('.box').forEach(e => e.style.display='none');
    document.getElementById('setupBox').style.display='block';
}

function showLogin(){
    document.querySelectorAll('.box').forEach(e => e.style.display='none');
    document.querySelector('.box').style.display='block';
}
</script>

</body>
</html>
