<?php
session_start();
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/csrf.php';
require_once '../../inc/device_guard.php';
require_once '../../inc/url.php';

$error = '';
$needDevice = isset($_GET['need_device']) && $_GET['need_device'] === '1';
$csrf = csrf_token();
$clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '-');

function redirect_admin_home(): void {
    header('Location: ' . app_url('/public/admin/dashboard.php'));
    exit;
}

if (isset($_SESSION['pengguna_id'], $_SESSION['peran'])) {
    $role = (string)$_SESSION['peran'];
    if (in_array($role, ['owner', 'manager', 'gudang', 'admin'], true)) {
        redirect_admin_home();
    }
}

$deviceOk = checkDevice();
$deviceType = (string)($_SESSION['device_tipe'] ?? '');
$tokoIdFromDevice = (int)($_SESSION['toko_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect_redirect();

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!$deviceOk || $tokoIdFromDevice <= 0) {
        $error = 'Device belum terdaftar/aktif. Silakan registrasi device dulu.';
    } elseif ($deviceType === 'kasir') {
        $error = 'Device tipe kasir tidak diizinkan login ke halaman admin.';
    } elseif ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $st = $pos_db->prepare("
            SELECT pengguna_id, nama, email, password, peran, toko_id
            FROM pengguna
            WHERE email = ?
              AND toko_id = ?
              AND aktif = 1
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->bind_param('si', $email, $tokoIdFromDevice);
        $st->execute();
        $user = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$user || !password_verify($password, (string)$user['password'])) {
            $error = 'Email atau password tidak valid.';
        } else {
            $role = (string)$user['peran'];
            if (!in_array($role, ['owner', 'manager', 'gudang', 'admin'], true)) {
                $error = 'Akun ini tidak diizinkan untuk login admin.';
            } else {
                $_SESSION['pengguna_id'] = (int)$user['pengguna_id'];
                $_SESSION['pengguna_nama'] = (string)$user['nama'];
                $_SESSION['toko_id'] = (int)$user['toko_id'];
                $_SESSION['peran'] = $role;
                redirect_admin_home();
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login Admin</title>
    <style>
        :root { --bg:#f8fafc; --card:#fff; --border:#e2e8f0; --text:#0f172a; --muted:#64748b; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:linear-gradient(150deg,#f8fafc 0%,#dbeafe 70%,#e2e8f0 100%); font-family:Inter,sans-serif; color:var(--text); }
        .box { width:min(430px,92vw); background:var(--card); border:1px solid var(--border); border-radius:16px; padding:22px; box-shadow:0 18px 30px rgba(15,23,42,.08); }
        h1 { margin:0 0 6px; font-size:24px; }
        p { margin:0 0 14px; color:var(--muted); font-size:13px; }
        .alert { border:1px solid #fecaca; background:#fef2f2; color:#991b1b; border-radius:10px; padding:10px; font-size:13px; margin-bottom:10px; }
        .ok { border:1px solid #bbf7d0; background:#f0fdf4; color:#166534; border-radius:10px; padding:10px; font-size:13px; margin-bottom:10px; }
        label { display:block; font-size:12px; font-weight:700; color:#334155; margin:10px 0 5px; }
        input { width:100%; border:1px solid var(--border); border-radius:10px; padding:11px; font-size:14px; }
        button { width:100%; margin-top:12px; border:1px solid #0f172a; background:#0f172a; color:#fff; border-radius:10px; padding:11px; font-weight:700; cursor:pointer; }
        .links { margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; }
        .links a { font-size:12px; color:#1d4ed8; text-decoration:none; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Login Admin</h1>
        <p>Login khusus halaman admin/backoffice.</p>

        <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($needDevice): ?><div class="alert">Sesi device belum valid. Daftarkan device dulu sebelum login admin.</div><?php endif; ?>
        <?php if ($deviceOk && $tokoIdFromDevice > 0): ?>
            <div class="ok">Device terdeteksi. Toko ID: <?= (int)$tokoIdFromDevice ?><?= $deviceType !== '' ? ' | Tipe device: ' . htmlspecialchars($deviceType) : '' ?></div>
        <?php else: ?>
            <div class="alert">Device dari IP ini belum terdaftar aktif. IP terdeteksi: <strong><?= htmlspecialchars($clientIp) ?></strong></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <label>Email</label>
            <input type="email" name="email" autocomplete="username" required>
            <label>Password</label>
            <input type="password" name="password" autocomplete="current-password" required>
            <button type="submit">Masuk ke Admin</button>
        </form>

        <div class="links">
            <a href="<?= htmlspecialchars(app_url('/public/device_register.php')) ?>">Registrasi Device</a>
            <a href="<?= htmlspecialchars(app_url('/public/POS/login.php')) ?>">Login POS (Kasir)</a>
        </div>
    </div>
</body>
</html>
