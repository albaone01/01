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

$customerRows = [];
$st = $pos_db->prepare("
    SELECT
        pl.pelanggan_id,
        pl.nama_pelanggan,
        COUNT(p.penjualan_id) AS frekuensi,
        COALESCE(SUM(p.total_akhir),0) AS total_belanja,
        COALESCE(AVG(p.total_akhir),0) AS rata_transaksi,
        MAX(p.dibuat_pada) AS transaksi_terakhir,
        COALESCE(pt.poin,0) AS saldo_poin
    FROM pelanggan pl
    LEFT JOIN penjualan p
      ON p.pelanggan_id = pl.pelanggan_id
     AND p.toko_id = pl.toko_id
     AND DATE(p.dibuat_pada) BETWEEN ? AND ?
    LEFT JOIN pelanggan_toko pt
      ON pt.pelanggan_id = pl.pelanggan_id
     AND pt.toko_id = pl.toko_id
     AND pt.deleted_at IS NULL
    WHERE pl.toko_id = ?
      AND pl.deleted_at IS NULL
    GROUP BY pl.pelanggan_id, pl.nama_pelanggan, pt.poin
    ORDER BY total_belanja DESC, frekuensi DESC
    LIMIT 50
");
$st->bind_param('ssi', $dateFrom, $dateTo, $tokoId);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) $customerRows[] = $row;
$st->close();

$stats = ['new_customer' => 0, 'returning_customer' => 0, 'active_customer' => 0];
$st = $pos_db->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN x.first_tx BETWEEN ? AND ? THEN 1 ELSE 0 END),0) AS new_customer,
        COALESCE(SUM(CASE WHEN x.first_tx < ? AND x.tx_in_range > 0 THEN 1 ELSE 0 END),0) AS returning_customer,
        COALESCE(SUM(CASE WHEN x.tx_in_range > 0 THEN 1 ELSE 0 END),0) AS active_customer
    FROM (
        SELECT
            p.pelanggan_id,
            MIN(DATE(p.dibuat_pada)) AS first_tx,
            SUM(CASE WHEN DATE(p.dibuat_pada) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS tx_in_range
        FROM penjualan p
        WHERE p.toko_id = ?
          AND p.pelanggan_id IS NOT NULL
        GROUP BY p.pelanggan_id
    ) x
");
$st->bind_param('sssssi', $dateFrom, $dateTo, $dateFrom, $dateFrom, $dateTo, $tokoId);
$st->execute();
$stats = $st->get_result()->fetch_assoc() ?: $stats;
$st->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Behavior</title>
    <style>
        :root { --bg:#f6f8fc; --card:#fff; --line:#e2e8f0; --text:#0f172a; --muted:#64748b; }
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
        .kpis { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; padding:10px; }
        .kpi { background:#f8fafc; border:1px solid var(--line); border-radius:10px; padding:10px; }
        .kpi small { color:#475569; font-weight:700; }
        .kpi strong { display:block; font-size:20px; margin-top:4px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:8px 10px; border-bottom:1px solid var(--line); text-align:left; }
        th { background:#f8fafc; color:#475569; }
        td.num { text-align:right; font-variant-numeric:tabular-nums; }
        @media (max-width:780px){ .kpis{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div>
                <h1 class="title">Customer Behavior</h1>
                <p class="muted">Analisis customer baru, ulang, dan belanja per pelanggan.</p>
            </div>
            <button class="btn" type="button" onclick="window.location.replace('../dashboard.php')">Kembali</button>
        </div>
        <form class="toolbar" method="get">
            <div><label>Dari</label><input type="date" name="from" value="<?=h($dateFrom)?>"></div>
            <div><label>Sampai</label><input type="date" name="to" value="<?=h($dateTo)?>"></div>
            <div><button class="btn" type="submit">Terapkan</button></div>
        </form>
        <div class="kpis">
            <div class="kpi"><small>Customer Aktif</small><strong><?=number_format((int)$stats['active_customer'])?></strong></div>
            <div class="kpi"><small>Customer Baru</small><strong><?=number_format((int)$stats['new_customer'])?></strong></div>
            <div class="kpi"><small>Customer Ulang</small><strong><?=number_format((int)$stats['returning_customer'])?></strong></div>
        </div>
        <div style="padding:0 10px 10px;">
            <table>
                <thead>
                    <tr>
                        <th>Pelanggan</th>
                        <th class="num">Frekuensi</th>
                        <th class="num">Total Belanja</th>
                        <th class="num">Rata-rata</th>
                        <th class="num">Saldo Poin</th>
                        <th>Transaksi Terakhir</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$customerRows): ?>
                    <tr><td colspan="6" class="muted">Belum ada data pelanggan pada periode ini.</td></tr>
                <?php else: foreach ($customerRows as $r): ?>
                    <tr>
                        <td><?=h((string)$r['nama_pelanggan'])?></td>
                        <td class="num"><?=number_format((int)$r['frekuensi'])?></td>
                        <td class="num"><?=rupiah((float)$r['total_belanja'])?></td>
                        <td class="num"><?=rupiah((float)$r['rata_transaksi'])?></td>
                        <td class="num"><?=number_format((int)$r['saldo_poin'])?></td>
                        <td><?=h($r['transaksi_terakhir'] ? (string)$r['transaksi_terakhir'] : '-')?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>

