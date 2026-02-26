<?php
// ... (Bagian PHP tetap sama seperti kode Anda) ...
session_start();
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$userNama = (string)($_SESSION['pengguna_nama'] ?? 'User');

if ($tokoId <= 0) {
    http_response_code(400);
    exit('Sesi toko tidak valid.');
}

function rupiah($n): string {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

// Data fetching logic (tetap seperti kode asli Anda)
$sum = ['trx_hari_ini' => 0, 'omzet_hari_ini' => 0.0, 'cash_hari_ini' => 0.0, 'non_tunai_hari_ini' => 0.0, 'piutang_hari_ini' => 0.0];
$sumStmt = $pos_db->prepare("SELECT COUNT(p.penjualan_id) AS trx_hari_ini, COALESCE(SUM(p.total_akhir), 0) AS omzet_hari_ini, COALESCE(SUM(CASE WHEN b.metode = 'cash' THEN b.jumlah ELSE 0 END), 0) AS cash_hari_ini, COALESCE(SUM(CASE WHEN b.metode IN ('qris','transfer') THEN b.jumlah ELSE 0 END), 0) AS non_tunai_hari_ini FROM penjualan p LEFT JOIN pembayaran b ON b.penjualan_id = p.penjualan_id WHERE p.toko_id = ? AND DATE(p.dibuat_pada) = CURRENT_DATE()");
$sumStmt->bind_param('i', $tokoId); $sumStmt->execute(); $sumRow = $sumStmt->get_result()->fetch_assoc(); $sumStmt->close();
if ($sumRow) { $sum['trx_hari_ini'] = (int)$sumRow['trx_hari_ini']; $sum['omzet_hari_ini'] = (float)$sumRow['omzet_hari_ini']; $sum['cash_hari_ini'] = (float)$sumRow['cash_hari_ini']; $sum['non_tunai_hari_ini'] = (float)$sumRow['non_tunai_hari_ini']; }
$piutangStmt = $pos_db->prepare("SELECT COALESCE(SUM(sisa),0) AS piutang_hari_ini FROM piutang WHERE DATE(dibuat_pada) = CURRENT_DATE()");
$piutangStmt->execute(); $piutangRow = $piutangStmt->get_result()->fetch_assoc(); $piutangStmt->close();
$sum['piutang_hari_ini'] = (float)($piutangRow['piutang_hari_ini'] ?? 0);
$listStmt = $pos_db->prepare("SELECT p.penjualan_id, p.nomor_invoice, p.total_akhir, p.dibuat_pada, COALESCE(pl.nama_pelanggan, 'Walk-in') AS nama_pelanggan, COALESCE(SUM(b.jumlah), 0) AS total_bayar FROM penjualan p LEFT JOIN pelanggan pl ON pl.pelanggan_id = p.pelanggan_id LEFT JOIN pembayaran b ON b.penjualan_id = p.penjualan_id WHERE p.toko_id = ? AND DATE(p.dibuat_pada) = CURRENT_DATE() GROUP BY p.penjualan_id, p.nomor_invoice, p.total_akhir, p.dibuat_pada, pl.nama_pelanggan ORDER BY p.penjualan_id DESC LIMIT 30");
$listStmt->bind_param('i', $tokoId); $listStmt->execute(); $rows = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC); $listStmt->close();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Kas Harian | POS System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --primary: #2563eb;
        --bg-body: #f1f5f9; /* Sedikit lebih gelap agar card lebih "pop" */
        --text-main: #0f172a;
        --text-muted: #64748b;
        --card-bg: #ffffff;
        --border: #e2e8f0;
        --radius: 12px;
    }

    * { box-sizing: border-box; }
    
    body { 
        font-family: 'Inter', system-ui, -apple-system, sans-serif; 
        background: var(--bg-body); 
        margin: 0; 
        color: var(--text-main);
        height: 100vh;
        overflow: hidden; /* Mencegah scroll pada body utama */
        display: flex;
        justify-content: center;
    }

    /* Layout Wrapper - Menangani masalah "mepet" */
    .app-container {
        display: flex;
        flex-direction: column;
        height: 100vh;
        width: 100%;
        max-width: 1280px; /* Ukuran standar dashboard profesional */
        padding: 24px 32px; /* Jarak aman dari pinggir layar (atas-bawah, kiri-kanan) */
        gap: 24px;
    }

    /* Header Section - Lebih lega dan elegan */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-shrink: 0;
        padding-bottom: 8px;
    }
    
    .header-info h2 { 
        margin: 0; 
        font-size: 24px; 
        font-weight: 800; 
        letter-spacing: -0.025em;
        color: #0f172a; 
    }
    
    .header-info p { 
        margin: 4px 0 0; 
        font-size: 14px; 
        color: var(--text-muted); 
    }

    .btn-group { display: flex; gap: 10px; }
    
    .btn {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 10px 18px;
        text-decoration: none;
        color: var(--text-main);
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    
    .btn:hover { 
        background: #f8fafc; 
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }
    
    .btn-primary { 
        background: var(--primary); 
        color: #fff; 
        border: none; 
    }
    
    .btn-primary:hover { background: #1d4ed8; }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 16px;
        flex-shrink: 0;
    }
    
    .stat-card {
        background: var(--card-bg);
        padding: 20px;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        transition: transform 0.2s;
    }
    
    .stat-label { 
        font-size: 11px; 
        text-transform: uppercase; 
        color: var(--text-muted); 
        font-weight: 700; 
        letter-spacing: 0.05em; 
    }
    
    .stat-value { 
        font-size: 20px; 
        font-weight: 800; 
        margin-top: 8px; 
        color: #0f172a; 
    }
    
    /* Table Section - Fokus Minim Scroll */
    .table-container {
        flex-grow: 1; 
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden; 
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
    }
    
    .table-scroll {
        overflow-y: auto;
        flex-grow: 1;
    }

    /* Custom Scrollbar biar cantik */
    .table-scroll::-webkit-scrollbar { width: 6px; }
    .table-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 14px; }
    
    thead th {
        position: sticky;
        top: 0;
        background: #f8fafc;
        z-index: 10;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        font-size: 11px;
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
    }

    tbody td { 
        padding: 14px 20px; 
        border-bottom: 1px solid #f1f5f9; 
        vertical-align: middle;
    }
    
    tr:hover td { background: #fbfcfe; }

    .text-right { text-align: right; }

    /* Status Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .badge-ok { background: #dcfce7; color: #166534; }
    .badge-warn { background: #fee2e2; color: #991b1b; }

    /* Responsivitas Layar Kecil */
    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(3, 1fr); }
        .app-container { padding: 20px; }
    }
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .header { flex-direction: column; align-items: flex-start; gap: 16px; }
        .hide-mobile { display: none; }
    }
</style>
</head>
<body>

<div class="app-container">
    <header class="header">
        <div class="header-info">
            <h2>Kas Harian</h2>
            <p>Halo, <strong><?=htmlspecialchars($userNama)?></strong>. Ringkasan hari ini.</p>
        </div>
        <div class="btn-group">
            <a class="btn" href="index.php">POS Utama</a>
            <a class="btn btn-primary" href="kasir.php">Buka Kasir</a>
        </div>
    </header>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Transaksi</div>
            <div class="stat-value"><?=number_format($sum['trx_hari_ini'])?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Omzet</div>
            <div class="stat-value"><?=rupiah($sum['omzet_hari_ini'])?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Tunai</div>
            <div class="stat-value" style="color: #059669;"><?=rupiah($sum['cash_hari_ini'])?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Non-Tunai</div>
            <div class="stat-value" style="color: #2563eb;"><?=rupiah($sum['non_tunai_hari_ini'])?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Piutang</div>
            <div class="stat-value" style="color: #dc2626;"><?=rupiah($sum['piutang_hari_ini'])?></div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Pelanggan</th>
                        <th class="hide-mobile">Waktu</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Dibayar</th>
                        <th class="text-right">Sisa</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" style="text-align:center;color:#6b7280;padding:40px">Belum ada transaksi terekam hari ini.</td></tr>
                    <?php else: foreach ($rows as $r):
                        $total = (float)$r['total_akhir'];
                        $bayar = (float)$r['total_bayar'];
                        $sisa = max(0, $total - $bayar);
                        $lunas = $sisa <= 0.0001;
                    ?>
                        <tr>
                            <td style="font-weight: 600; color: #2563eb;">#<?=htmlspecialchars($r['nomor_invoice'])?></td>
                            <td><?=htmlspecialchars($r['nama_pelanggan'])?></td>
                            <td class="hide-mobile"><?=date('H:i', strtotime($r['dibuat_pada']))?> <small style="color:#94a3b8"><?=date('d/m', strtotime($r['dibuat_pada']))?></small></td>
                            <td class="text-right" style="font-weight: 600;"><?=rupiah($total)?></td>
                            <td class="text-right" style="color: #059669;"><?=rupiah($bayar)?></td>
                            <td class="text-right" style="color: <?= $sisa > 0 ? '#dc2626' : 'inherit' ?>;"><?=rupiah($sisa)?></td>
                            <td><span class="badge <?=$lunas ? 'badge-ok' : 'badge-warn'?>"><?=$lunas ? 'LUNAS' : 'PIUTANG'?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>