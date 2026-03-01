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

$rows = [];
$sql = "
    SELECT
        kp.kategori_id,
        kp.nama_kategori,
        COUNT(DISTINCT p.penjualan_id) AS transaksi,
        COALESCE(SUM(CASE WHEN p.penjualan_id IS NOT NULL THEN pd.qty ELSE 0 END),0) AS qty,
        COALESCE(SUM(CASE WHEN p.penjualan_id IS NOT NULL THEN pd.diskon ELSE 0 END),0) AS diskon,
        COALESCE(SUM(CASE WHEN p.penjualan_id IS NOT NULL THEN pd.subtotal ELSE 0 END),0) AS omzet
    FROM kategori_produk kp
    LEFT JOIN produk pr ON pr.kategori_id = kp.kategori_id AND pr.deleted_at IS NULL
    LEFT JOIN penjualan_detail pd ON pd.produk_id = pr.produk_id
    LEFT JOIN penjualan p ON p.penjualan_id = pd.penjualan_id
        AND p.toko_id = kp.toko_id
        AND DATE(p.dibuat_pada) BETWEEN ? AND ?
    WHERE kp.toko_id = ?
      AND kp.deleted_at IS NULL
    GROUP BY kp.kategori_id, kp.nama_kategori
    ORDER BY omzet DESC, qty DESC
";
$st = $pos_db->prepare($sql);
$st->bind_param('ssi', $dateFrom, $dateTo, $tokoId);
$st->execute();
$res = $st->get_result();
while ($r = $res->fetch_assoc()) $rows[] = $r;
$st->close();

$totalOmzet = 0.0;
foreach ($rows as $r) $totalOmzet += (float)$r['omzet'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Performa Kategori</title>
    <style>
        :root { --bg:#f6f8fc; --card:#fff; --line:#e2e8f0; --text:#0f172a; --muted:#64748b; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; }
        .wrap { max-width:1100px; margin:16px auto; padding:0 12px 20px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; box-shadow:0 10px 24px rgba(15,23,42,.05); }
        .head { padding:12px 14px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
        .title { margin:0; font-size:20px; }
        .muted { color:var(--muted); font-size:13px; margin:4px 0 0; }
        .btn { border:1px solid var(--line); background:#fff; color:var(--text); border-radius:8px; padding:8px 10px; cursor:pointer; font-weight:700; }
        .toolbar { padding:12px 14px; display:flex; gap:8px; align-items:end; flex-wrap:wrap; border-bottom:1px solid var(--line); }
        label { display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; }
        input[type="date"] { border:1px solid var(--line); border-radius:8px; padding:8px 9px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:8px 10px; border-bottom:1px solid var(--line); text-align:left; }
        th { background:#f8fafc; color:#475569; }
        td.num { text-align:right; font-variant-numeric:tabular-nums; }
        .section { padding:10px; }
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div>
                <h1 class="title">Performa Kategori</h1>
                <p class="muted">Kontribusi penjualan per kategori produk.</p>
            </div>
            <button class="btn" type="button" onclick="window.location.replace('../dashboard.php')">Kembali</button>
        </div>
        <form class="toolbar" method="get">
            <div><label>Dari</label><input type="date" name="from" value="<?=h($dateFrom)?>"></div>
            <div><label>Sampai</label><input type="date" name="to" value="<?=h($dateTo)?>"></div>
            <div><button class="btn" type="submit">Terapkan</button></div>
        </form>
        <div class="section" style="padding:0;">
            <table>
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th class="num">Transaksi</th>
                        <th class="num">Qty</th>
                        <th class="num">Diskon</th>
                        <th class="num">Omzet</th>
                        <th class="num">Kontribusi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="muted">Belum ada data kategori pada periode ini.</td></tr>
                <?php else: foreach ($rows as $r):
                    $omzet = (float)$r['omzet'];
                    $share = $totalOmzet > 0 ? ($omzet / $totalOmzet * 100) : 0;
                ?>
                    <tr>
                        <td><?=h((string)$r['nama_kategori'])?></td>
                        <td class="num"><?=number_format((int)$r['transaksi'])?></td>
                        <td class="num"><?=number_format((float)$r['qty'])?></td>
                        <td class="num"><?=rupiah((float)$r['diskon'])?></td>
                        <td class="num"><?=rupiah($omzet)?></td>
                        <td class="num"><?=number_format($share, 2, ',', '.')?>%</td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
