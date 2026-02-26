<?php
session_start();
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';
require_once '../../inc/functions.php';

requireLogin();
requireDevice();

// Dummy data showcase (replace with real query later)
$orders = [
    ['nomor' => 'POS-2402-0012', 'customer' => 'Budi Setiawan', 'total' => 1850000, 'status' => 'Paid', 'tanggal' => '2026-02-20', 'kasir' => 'Rina'],
    ['nomor' => 'POS-2402-0011', 'customer' => 'Walk-in', 'total' => 320000, 'status' => 'Unpaid', 'tanggal' => '2026-02-20', 'kasir' => 'Dewi'],
    ['nomor' => 'POS-2402-0008', 'customer' => 'Andi Wijaya', 'total' => 740000, 'status' => 'Paid', 'tanggal' => '2026-02-19', 'kasir' => 'Rina'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Penjualan</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloud/libs/font-awesomeflare.com/ajax/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        :root{
            --primary:#6366f1;
            --primary-strong:#4f46e5;
            --bg:#f5f7fb;
            --card:#ffffff;
            --border:#e2e8f0;
            --muted:#64748b;
            --text:#0f172a;
            --success:#10b981;
            --warning:#f59e0b;
        }
        *{box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans','Inter',system-ui; margin:0; background:var(--bg); color:var(--text);}        
        .page{max-width:1200px; margin:28px auto; padding:0 16px 48px;}
        .hero{display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px; flex-wrap:wrap;}
        .breadcrumbs{font-size:13px; color:var(--muted);} 
        .title{margin:4px 0 0; font-size:24px; font-weight:700;}
        .actions{display:flex; gap:10px; flex-wrap:wrap;}
        .card{background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow:0 14px 36px rgba(15,23,42,0.08); padding:18px;}
        .stat-grid{display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-bottom:14px;}
        .stat{display:flex; gap:12px; align-items:center;}
        .stat .icon{width:42px; height:42px; border-radius:12px; display:grid; place-items:center; color:#fff;}
        .stat h4{margin:0; font-size:15px; color:var(--muted);} 
        .stat .value{margin-top:2px; font-size:20px; font-weight:700;}
        .filter-bar{display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;}
        .filter-bar input, .filter-bar select{padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:#f8fafc; font-size:14px; min-width:140px;}
        .btn{padding:10px 14px; border:none; border-radius:10px; font-weight:600; cursor:pointer; font-size:14px;}
        .btn-primary{background:var(--primary); color:#fff;}
        .btn-ghost{background:#eef2f7; color:var(--text);} 
        .btn-outline{background:#fff; color:var(--text); border:1px solid var(--border);} 
        table{width:100%; border-collapse:collapse;}
        th, td{padding:12px 10px; border-bottom:1px solid var(--border); text-align:left; font-size:13px;}
        th{background:#f8fafc; color:var(--muted); font-weight:700;}
        tr:hover td{background:#f9fafb;}
        .pill{padding:6px 10px; border-radius:999px; font-weight:600; font-size:12px; display:inline-flex; align-items:center; gap:6px;}
        .pill.green{background:#ecfdf3; color:#16a34a;}
        .pill.amber{background:#fef3c7; color:#d97706;}
        .table-card{margin-top:14px;}
        .flex-between{display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;}
        .empty{padding:24px; text-align:center; color:var(--muted);} 
        .quick-actions{display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; margin-top:12px;}
        .qa-card{border:1px dashed var(--border); padding:14px; border-radius:12px; background:#f8fafc;}
        .qa-card h4{margin:0 0 6px;} 
        .qa-card p{margin:0; color:var(--muted); font-size:13px;}
        .qa-card button{margin-top:10px;}
        @media(max-width:640px){
            .stat-grid{grid-template-columns:repeat(auto-fit,minmax(160px,1fr));}
            .title{font-size:20px;}
        }
    </style>
</head>
<body>
<?php include '../../inc/header.php'; ?>
<div class="page">
    <div class="hero">
        <div>
            <div class="breadcrumbs">Penjualan / Transaksi Penjualan</div>
            <div class="title">Transaksi Penjualan</div>
            <div style="color:var(--muted); margin-top:4px;">Monitor transaksi kasir, status pembayaran, dan ringkasan performa harian.</div>
        </div>
        <div class="actions">
            <button class="btn btn-ghost" onclick="window.location.href='../purchase_order/index.php'">Lihat PO</button>
            <button class="btn btn-primary" onclick="window.location.href='kasir.php'">+ Transaksi Baru</button>
        </div>
    </div>

    <div class="stat-grid">
        <div class="card stat">
            <div class="icon" style="background:#6366f1;"><i class="fa-solid fa-bolt"></i></div>
            <div>
                <h4>Penjualan Hari Ini</h4>
                <div class="value">Rp 3.280.000</div>
            </div>
        </div>
        <div class="card stat">
            <div class="icon" style="background:#0ea5e9;"><i class="fa-solid fa-cash-register"></i></div>
            <div>
                <h4>Transaksi</h4>
                <div class="value">18 transaksi</div>
            </div>
        </div>
        <div class="card stat">
            <div class="icon" style="background:#10b981;"><i class="fa-solid fa-circle-check"></i></div>
            <div>
                <h4>Paid Ratio</h4>
                <div class="value">92% lunas</div>
            </div>
        </div>
        <div class="card stat">
            <div class="icon" style="background:#f59e0b;"><i class="fa-solid fa-hourglass-half"></i></div>
            <div>
                <h4>Belum Lunas</h4>
                <div class="value">Rp 420.000</div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="flex-between" style="margin-bottom:12px;">
            <div>
                <div style="font-weight:700;">Daftar Transaksi</div>
                <div style="font-size:13px; color:var(--muted);">Tampilkan status pembayaran dan kasir.</div>
            </div>
            <div class="filter-bar">
                <input type="date" value="<?=date('Y-m-d');?>">
                <select>
                    <option>Status: Semua</option>
                    <option>Lunas</option>
                    <option>Belum Lunas</option>
                </select>
                <input type="text" placeholder="Cari nomor / customer">
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th>Kasir</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($orders)): ?>
                    <tr><td colspan="7" class="empty">Belum ada transaksi.</td></tr>
                <?php else: foreach($orders as $o): ?>
                    <tr>
                        <td><?=htmlspecialchars($o['nomor']);?></td>
                        <td><?=htmlspecialchars($o['customer']);?></td>
                        <td><?=formatRupiah($o['total']);?></td>
                        <td>
                            <?php if($o['status']==='Paid'): ?>
                                <span class="pill green"><i class="fa-solid fa-circle"></i> Lunas</span>
                            <?php else: ?>
                                <span class="pill amber"><i class="fa-solid fa-circle"></i> Belum Lunas</span>
                            <?php endif; ?>
                        </td>
                        <td><?=htmlspecialchars($o['tanggal']);?></td>
                        <td><?=htmlspecialchars($o['kasir']);?></td>
                        <td style="text-align:right;">
                            <button class="btn btn-outline" onclick="window.location.href='struk.php?no=<?=urlencode($o['nomor']);?>'">Lihat</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="quick-actions">
        <div class="qa-card">
            <h4>Shortcut Kasir</h4>
            <p>Buka mode kasir dengan layout cepat untuk transaksi frontliner.</p>
            <button class="btn btn-primary" onclick="window.location.href='kasir.php'">Buka Kasir</button>
        </div>
        <div class="qa-card">
            <h4>Receipt / Struk</h4>
            <p>Unduh atau kirim struk elektronik ke pelanggan.</p>
            <button class="btn btn-outline" onclick="window.location.href='struk.php'">Kelola Struk</button>
        </div>
        <div class="qa-card">
            <h4>Settlement Harian</h4>
            <p>Rekonsiliasi pembayaran tunai, QRIS, kartu, dan dompet digital.</p>
            <button class="btn btn-ghost" onclick="alert('Fitur coming soon')">Lihat Ringkasan</button>
        </div>
    </div>
</div>
</body>
</html>
