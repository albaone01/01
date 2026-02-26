<?php
session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/functions.php';

requireLogin();
requireDevice();

$piutangs = [
    ['customer' => 'Budi Setiawan', 'nomor' => 'INV-2402-015', 'jatuh_tempo' => '2026-02-25', 'saldo' => 420000, 'status' => 'Due Soon'],
    ['customer' => 'Andi Wijaya', 'nomor' => 'INV-2402-010', 'jatuh_tempo' => '2026-02-28', 'saldo' => 1150000, 'status' => 'Open'],
    ['customer' => 'Walk-in', 'nomor' => 'INV-2402-006', 'jatuh_tempo' => '2026-02-18', 'saldo' => 180000, 'status' => 'Overdue'],
];

function rupiah($v){ return 'Rp ' . number_format((float)$v,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Piutang Customer</title>
    <link rel="stylesheet" href='/assets/css/style.css'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        :root{
            --primary:#6366f1; --bg:#f5f7fb; --card:#fff; --border:#e2e8f0; --muted:#64748b; --text:#0f172a;
            --green:#10b981; --amber:#f59e0b; --red:#ef4444;
        }
        *{box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans','Inter',system-ui; margin:0; background:var(--bg); color:var(--text);} 
        .page{max-width:1100px; margin:28px auto; padding:0 16px 48px;}
        .hero{display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:18px;}
        .breadcrumbs{font-size:13px; color:var(--muted);} 
        .title{margin:4px 0 0; font-size:24px; font-weight:700;}
        .actions{display:flex; gap:10px; flex-wrap:wrap;}
        .btn{padding:10px 14px; border:none; border-radius:10px; font-weight:600; cursor:pointer; font-size:14px;}
        .btn-primary{background:var(--primary); color:#fff;}
        .btn-outline{background:#fff; color:var(--text); border:1px solid var(--border);} 
        .btn-ghost{background:#eef2f7; color:var(--text);} 
        .card{background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow:0 14px 36px rgba(15,23,42,0.08); padding:18px;}
        .panel{display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:12px; margin-top:12px;}
        .muted{color:var(--muted); font-size:13px;}
        table{width:100%; border-collapse:collapse; margin-top:10px;}
        th, td{padding:12px 10px; border-bottom:1px solid var(--border); text-align:left; font-size:13px;}
        th{background:#f8fafc; color:var(--muted); font-weight:700;}
        tr:hover td{background:#f9fafb;}
        .pill{padding:6px 10px; border-radius:999px; font-weight:600; font-size:12px; display:inline-flex; align-items:center; gap:6px;}
        .pill.green{background:#ecfdf3; color:#16a34a;}
        .pill.amber{background:#fef3c7; color:#d97706;}
        .pill.red{background:#fee2e2; color:#b91c1c;}
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="page">
    <div class="hero">
        <div>
            <div class="breadcrumbs">Penjualan / Piutang Customer</div>
            <div class="title">Piutang Customer</div>
            <div class="muted">Pantau saldo piutang, jatuh tempo, dan prioritas penagihan.</div>
        </div>
        <div class="actions">
            <button class="btn btn-outline" onclick="window.location.href='../penjualan/index.php'">Daftar Penjualan</button>
            <button class="btn btn-primary" onclick="window.location.href='../piutang_pembayaran/index.php'">Catat Pembayaran</button>
        </div>
    </div>

    <div class="panel">
        <div class="card">
            <div style="font-weight:700;">Tagihan Jatuh Tempo</div>
            <p class="muted" style="margin-top:6px;">Follow up invoice mendekati jatuh tempo agar cashflow terjaga.</p>
            <ul class="muted" style="margin:8px 0 0 18px;">
                <li>Reminder WA/Email otomatis</li>
                <li>Riwayat kontak penagihan</li>
                <li>Prioritas berdasar nominal & umur</li>
            </ul>
        </div>
        <div class="card">
            <div style="font-weight:700;">Kebijakan Kredit</div>
            <p class="muted" style="margin-top:6px;">Batas kredit per customer, grace period, dan denda keterlambatan.</p>
            <ul class="muted" style="margin:8px 0 0 18px;">
                <li>Credit limit & blocking otomatis</li>
                <li>Approval manager jika overlimit</li>
                <li>Simulasi denda harian</li>
            </ul>
        </div>
    </div>

    <div class="card" style="margin-top:14px;">
        <div style="font-weight:700; margin-bottom:6px;">Daftar Piutang</div>
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Invoice</th>
                    <th>Jatuh Tempo</th>
                    <th>Saldo</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($piutangs)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--muted); padding:18px;">Tidak ada piutang.</td></tr>
                <?php else: foreach($piutangs as $p): ?>
                    <tr>
                        <td><?=htmlspecialchars($p['customer']);?></td>
                        <td><?=htmlspecialchars($p['nomor']);?></td>
                        <td><?=htmlspecialchars($p['jatuh_tempo']);?></td>
                        <td><?=rupiah($p['saldo']);?></td>
                        <td>
                            <?php if($p['status']==='Overdue'): ?>
                                <span class="pill red"><i class="fa-solid fa-circle"></i> Overdue</span>
                            <?php elseif($p['status']==='Due Soon'): ?>
                                <span class="pill amber"><i class="fa-solid fa-circle"></i> Due Soon</span>
                            <?php else: ?>
                                <span class="pill green"><i class="fa-solid fa-circle"></i> Open</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <button class="btn btn-outline" onclick="window.location.href='../piutang_pembayaran/index.php?invoice=<?=urlencode($p['nomor']);?>'">Tagih</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>