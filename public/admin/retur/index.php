<?php
session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/functions.php';

requireLogin();
requireDevice();

$returs = [
    ['nomor' => 'RT-2402-001', 'tanggal' => '2026-02-20', 'customer' => 'Budi Setiawan', 'kasir' => 'Rina', 'total' => 180000, 'status' => 'Approved'],
    ['nomor' => 'RT-2402-000', 'tanggal' => '2026-02-19', 'customer' => 'Walk-in', 'kasir' => 'Dewi', 'total' => 95000, 'status' => 'Pending'],
];

function rp($v){ return 'Rp ' . number_format((float)$v,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retur Penjualan</title>
    <link rel="stylesheet" href='/assets/css/style.css'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        :root{
            --primary:#6366f1; --bg:#f5f7fb; --card:#fff; --border:#e2e8f0; --muted:#64748b; --text:#0f172a;
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
        .card{background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow:0 14px 36px rgba(15,23,42,0.08); padding:18px;}
        table{width:100%; border-collapse:collapse; margin-top:10px;}
        th, td{padding:12px 10px; border-bottom:1px solid var(--border); text-align:left; font-size:13px;}
        th{background:#f8fafc; color:var(--muted); font-weight:700;}
        tr:hover td{background:#f9fafb;}
        .pill{padding:6px 10px; border-radius:999px; font-weight:600; font-size:12px; display:inline-flex; align-items:center; gap:6px;}
        .pill.green{background:#ecfdf3; color:#16a34a;}
        .pill.amber{background:#fef3c7; color:#d97706;}
        .panel{margin-top:12px; display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:12px;}
        .muted{color:var(--muted); font-size:13px;}
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="page">
    <div class="hero">
        <div>
            <div class="breadcrumbs">Penjualan / Retur Penjualan</div>
            <div class="title">Retur Penjualan</div>
            <div class="muted">Kelola pengembalian barang, validasi struk, dan proses pengembalian dana atau tukar barang.</div>
        </div>
        <div class="actions">
            <button class="btn btn-outline" onclick="window.location.href='../penjualan/index.php'">Daftar Penjualan</button>
            <button class="btn btn-primary" onclick="alert('Form retur akan dihubungkan ke data penjualan')">+ Buat Retur</button>
        </div>
    </div>

    <div class="panel">
        <div class="card">
            <div style="font-weight:700;">Alur Retur</div>
            <p class="muted" style="margin-top:6px;">Verifikasi struk ➜ pilih item retur ➜ hitung nilai ➜ atur refund/tukar.</p>
            <ul style="margin:8px 0 0 18px;" class="muted">
                <li>Scan QR / input nomor struk</li>
                <li>Otorisasi supervisor untuk kasus khusus</li>
                <li>Log sebab retur & foto bukti</li>
            </ul>
        </div>
        <div class="card">
            <div style="font-weight:700;">Kebijakan</div>
            <p class="muted" style="margin-top:6px;">Batas waktu 7 hari, kondisi barang utuh, sertakan struk.</p>
            <ul style="margin:8px 0 0 18px;" class="muted">
                <li>Refund tunai / transfer / voucher</li>
                <li>Restocking otomatis stok gudang</li>
                <li>Audit trail per kasir</li>
            </ul>
        </div>
    </div>

    <div class="card" style="margin-top:14px;">
        <div style="font-weight:700; margin-bottom:6px;">Riwayat Retur</div>
        <table>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Tanggal</th>
                    <th>Customer</th>
                    <th>Kasir</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($returs)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--muted); padding:18px;">Belum ada retur.</td></tr>
                <?php else: foreach($returs as $r): ?>
                    <tr>
                        <td><?=htmlspecialchars($r['nomor']);?></td>
                        <td><?=htmlspecialchars($r['tanggal']);?></td>
                        <td><?=htmlspecialchars($r['customer']);?></td>
                        <td><?=htmlspecialchars($r['kasir']);?></td>
                        <td><?=rp($r['total']);?></td>
                        <td>
                            <?php if($r['status']==='Approved'): ?>
                                <span class="pill green"><i class="fa-solid fa-circle"></i> Approved</span>
                            <?php else: ?>
                                <span class="pill amber"><i class="fa-solid fa-circle"></i> Pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <button class="btn btn-outline" onclick="alert('Detail retur coming soon');">Detail</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>