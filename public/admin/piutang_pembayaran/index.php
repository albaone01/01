<?php
session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/functions.php';

requireLogin();
requireDevice();

$history = [
    ['nomor' => 'INV-2402-015', 'customer' => 'Budi Setiawan', 'tanggal' => '2026-02-20', 'metode' => 'Transfer', 'jumlah' => 250000],
    ['nomor' => 'INV-2402-006', 'customer' => 'Walk-in', 'tanggal' => '2026-02-19', 'metode' => 'Tunai', 'jumlah' => 180000],
];

function rp($v){ return 'Rp ' . number_format((float)$v,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Piutang</title>
    <link rel="stylesheet" href='/assets/css/style.css'>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        :root{ --primary:#6366f1; --bg:#f5f7fb; --card:#fff; --border:#e2e8f0; --muted:#64748b; --text:#0f172a; }
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
        .form-grid{display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px; margin-top:12px;}
        label{font-weight:600; font-size:13px;}
        input, select, textarea{width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:10px; font-size:14px; margin-top:6px; background:#f8fafc;}
        textarea{min-height:70px;}
        table{width:100%; border-collapse:collapse; margin-top:10px;}
        th, td{padding:12px 10px; border-bottom:1px solid var(--border); text-align:left; font-size:13px;}
        th{background:#f8fafc; color:var(--muted); font-weight:700;}
        tr:hover td{background:#f9fafb;}
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="page">
    <div class="hero">
        <div>
            <div class="breadcrumbs">Penjualan / Pembayaran Piutang</div>
            <div class="title">Pembayaran Piutang</div>
            <div style="color:var(--muted);">Catat pelunasan, unggah bukti, dan update saldo piutang.</div>
        </div>
        <div class="actions">
            <button class="btn btn-outline" onclick="window.location.href='../piutang/index.php'">Lihat Piutang</button>
        </div>
    </div>

    <div class="card">
        <div style="font-weight:700;">Form Pembayaran</div>
        <form>
            <div class="form-grid">
                <div>
                    <label>Invoice</label>
                    <input type="text" name="invoice" placeholder="Masukkan nomor invoice" value="<?=htmlspecialchars($_GET['invoice'] ?? '')?>">
                </div>
                <div>
                    <label>Customer</label>
                    <input type="text" name="customer" placeholder="Nama customer">
                </div>
                <div>
                    <label>Tanggal Bayar</label>
                    <input type="date" name="tanggal" value="<?=date('Y-m-d');?>">
                </div>
                <div>
                    <label>Metode</label>
                    <select name="metode">
                        <option>Tunai</option>
                        <option>Transfer</option>
                        <option>QRIS</option>
                        <option>Kartu</option>
                    </select>
                </div>
                <div>
                    <label>Jumlah</label>
                    <input type="number" name="jumlah" placeholder="0" step="0.01">
                </div>
                <div>
                    <label>Bukti (URL / referensi)</label>
                    <input type="text" name="bukti" placeholder="Link bukti transfer atau catatan">
                </div>
            </div>
            <div style="margin-top:10px;">
                <label>Catatan</label>
                <textarea name="catatan" placeholder="Opsional"></textarea>
            </div>
            <div style="margin-top:14px; display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;">
                <button type="button" class="btn btn-outline" onclick="alert('Draft disimpan')">Simpan Draft</button>
                <button type="button" class="btn btn-primary" onclick="alert('Pembayaran tercatat (mock)')">Simpan Pembayaran</button>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:14px;">
        <div style="font-weight:700; margin-bottom:6px;">Riwayat Pembayaran Terakhir</div>
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Tanggal</th>
                    <th>Metode</th>
                    <th>Jumlah</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($history)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--muted); padding:18px;">Belum ada pembayaran.</td></tr>
                <?php else: foreach($history as $h): ?>
                    <tr>
                        <td><?=htmlspecialchars($h['nomor']);?></td>
                        <td><?=htmlspecialchars($h['customer']);?></td>
                        <td><?=htmlspecialchars($h['tanggal']);?></td>
                        <td><?=htmlspecialchars($h['metode']);?></td>
                        <td><?=rp($h['jumlah']);?></td>
                        <td style="text-align:right;">
                            <button class="btn btn-outline" onclick="alert('Detail pembayaran coming soon');">Detail</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>