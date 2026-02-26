<?php
session_start();
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';

requireLogin();
requireDevice();

$userNama = (string)($_SESSION['pengguna_nama'] ?? 'User');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Proses Data</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 24px 16px;
        }
        .head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        h1 { margin: 0; font-size: 24px; }
        .btn {
            text-decoration: none;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 600;
        }
        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
        }
        .muted { color: var(--muted); font-size: 14px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <h1>Proses Data</h1>
            <a href="index.php" class="btn">Kembali ke Menu POS</a>
        </div>
        <div class="card">
            <p class="muted">Halo, <?= htmlspecialchars($userNama) ?>.</p>
            <p>Halaman proses data sudah tersedia untuk kebutuhan sinkronisasi, validasi, atau proses batch data POS.</p>
        </div>
    </div>
</body>
</html>
