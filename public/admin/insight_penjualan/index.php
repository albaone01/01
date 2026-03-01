<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if ($tokoId <= 0) {
    header('Location: ../pilih_gudang.php');
    exit;
}

$dateFrom = trim((string)($_GET['from'] ?? date('Y-m-01')));
$dateTo = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = date('Y-m-d');
if ($dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function rupiah(float $v): string { return 'Rp ' . number_format($v, 0, ',', '.'); }

$summary = ['trx' => 0, 'omzet' => 0.0, 'diskon' => 0.0, 'avg_ticket' => 0.0];
$st = $pos_db->prepare("
    SELECT
        COUNT(*) AS trx,
        COALESCE(SUM(total_akhir),0) AS omzet,
        COALESCE(SUM(diskon),0) AS diskon,
        COALESCE(AVG(total_akhir),0) AS avg_ticket
    FROM penjualan
    WHERE toko_id = ?
      AND DATE(dibuat_pada) BETWEEN ? AND ?
");
$st->bind_param('iss', $tokoId, $dateFrom, $dateTo);
$st->execute();
$summary = $st->get_result()->fetch_assoc() ?: $summary;
$st->close();

$daily = [];
$st = $pos_db->prepare("
    SELECT
        DATE(dibuat_pada) AS tanggal,
        COUNT(*) AS trx,
        COALESCE(SUM(total_akhir),0) AS omzet
    FROM penjualan
    WHERE toko_id = ?
      AND DATE(dibuat_pada) BETWEEN ? AND ?
    GROUP BY DATE(dibuat_pada)
    ORDER BY tanggal ASC
");
$st->bind_param('iss', $tokoId, $dateFrom, $dateTo);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) $daily[] = $row;
$st->close();

$payments = [];
$st = $pos_db->prepare("
    SELECT
        b.metode,
        COALESCE(SUM(b.jumlah),0) AS total
    FROM pembayaran b
    INNER JOIN penjualan p ON p.penjualan_id = b.penjualan_id
    WHERE p.toko_id = ?
      AND DATE(p.dibuat_pada) BETWEEN ? AND ?
    GROUP BY b.metode
    ORDER BY total DESC
");
$st->bind_param('iss', $tokoId, $dateFrom, $dateTo);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) $payments[] = $row;
$st->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Insight Penjualan</title>
    <style>
        :root { --bg:#f6f8fc; --card:#fff; --line:#e2e8f0; --text:#0f172a; --muted:#64748b; --pri:#0ea5e9; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; }
        .wrap { max-width:1200px; margin:16px auto; padding:0 12px 20px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; box-shadow:0 10px 24px rgba(15,23,42,.05); }
        .head { padding:12px 14px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
        .title { margin:0; font-size:20px; }
        .muted { color:var(--muted); font-size:13px; margin:4px 0 0; }
        .btn { border:1px solid var(--line); background:#fff; color:var(--text); border-radius:8px; padding:8px 10px; cursor:pointer; font-weight:700; }
        .toolbar { padding:12px 14px; display:flex; gap:8px; align-items:end; flex-wrap:wrap; border-bottom:1px solid var(--line); }
        label { display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; }
        input[type="date"] { border:1px solid var(--line); border-radius:8px; padding:8px 9px; }
        .kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; margin-top:10px; }
        .kpi { background:#f8fbff; border:1px solid #dbeafe; border-radius:10px; padding:10px; }
        .kpi small { color:#0369a1; font-weight:700; }
        .kpi strong { display:block; font-size:18px; margin-top:5px; }
        .grid { margin-top:10px; display:grid; grid-template-columns:1.2fr .8fr; gap:10px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:8px 10px; border-bottom:1px solid var(--line); text-align:left; }
        th { background:#f8fafc; color:#475569; }
        td.num { text-align:right; font-variant-numeric:tabular-nums; }
        .section { padding:10px; }
        @media (max-width:960px){ .kpis{grid-template-columns:repeat(2,minmax(0,1fr));} .grid{grid-template-columns:1fr;} }
        @media (max-width:560px){ .kpis{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div>
                <h1 class="title">Insight Penjualan</h1>
                <p class="muted">Ringkasan performa penjualan berdasarkan data transaksi.</p>
            </div>
            <button class="btn" type="button" onclick="window.location.replace('../dashboard.php')">Kembali</button>
        </div>
        <form class="toolbar" method="get">
            <div>
                <label>Dari</label>
                <input type="date" name="from" value="<?=h($dateFrom)?>">
            </div>
            <div>
                <label>Sampai</label>
                <input type="date" name="to" value="<?=h($dateTo)?>">
            </div>
            <div><button class="btn" type="submit">Terapkan</button></div>
        </form>
        <div class="section">
            <div class="kpis">
                <div class="kpi"><small>Total Transaksi</small><strong><?=number_format((int)$summary['trx'])?></strong></div>
                <div class="kpi"><small>Omzet</small><strong><?=rupiah((float)$summary['omzet'])?></strong></div>
                <div class="kpi"><small>Total Diskon</small><strong><?=rupiah((float)$summary['diskon'])?></strong></div>
                <div class="kpi"><small>Rata-rata Ticket</small><strong><?=rupiah((float)$summary['avg_ticket'])?></strong></div>
            </div>
            <div class="grid">
                <div class="card">
                    <div class="head"><strong>Tren Harian</strong></div>
                    <div class="section" style="padding:0;">
                        <table>
                            <thead><tr><th>Tanggal</th><th class="num">Transaksi</th><th class="num">Omzet</th></tr></thead>
                            <tbody>
                            <?php if (!$daily): ?>
                                <tr><td colspan="3" class="muted">Belum ada data pada periode ini.</td></tr>
                            <?php else: foreach ($daily as $r): ?>
                                <tr>
                                    <td><?=h((string)$r['tanggal'])?></td>
                                    <td class="num"><?=number_format((int)$r['trx'])?></td>
                                    <td class="num"><?=rupiah((float)$r['omzet'])?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card">
                    <div class="head"><strong>Komposisi Pembayaran</strong></div>
                    <div class="section" style="padding:0;">
                        <table>
                            <thead><tr><th>Metode</th><th class="num">Total</th></tr></thead>
                            <tbody>
                            <?php if (!$payments): ?>
                                <tr><td colspan="2" class="muted">Belum ada pembayaran tercatat.</td></tr>
                            <?php else: foreach ($payments as $r): ?>
                                <tr>
                                    <td><?=h(strtoupper((string)$r['metode']))?></td>
                                    <td class="num"><?=rupiah((float)$r['total'])?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

