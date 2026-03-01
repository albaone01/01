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
$limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = date('Y-m-d');
if ($dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function rupiah(float $v): string { return 'Rp ' . number_format($v, 0, ',', '.'); }

$rows = [];
$sql = "
    SELECT
        pr.produk_id,
        pr.nama_produk,
        COALESCE(SUM(pd.qty),0) AS qty_terjual,
        COALESCE(SUM(pd.subtotal),0) AS omzet,
        COALESCE(SUM(pd.harga_modal_snapshot * pd.qty),0) AS modal,
        COALESCE(SUM(pd.subtotal) - SUM(pd.harga_modal_snapshot * pd.qty),0) AS laba_kotor,
        MAX(p.dibuat_pada) AS terakhir_terjual
    FROM penjualan_detail pd
    INNER JOIN penjualan p ON p.penjualan_id = pd.penjualan_id
    INNER JOIN produk pr ON pr.produk_id = pd.produk_id
    WHERE p.toko_id = ?
      AND DATE(p.dibuat_pada) BETWEEN ? AND ?
    GROUP BY pr.produk_id, pr.nama_produk
    ORDER BY omzet DESC
    LIMIT ?
";
$st = $pos_db->prepare($sql);
$st->bind_param('issi', $tokoId, $dateFrom, $dateTo, $limit);
$st->execute();
$res = $st->get_result();
while ($r = $res->fetch_assoc()) $rows[] = $r;
$st->close();

$slowRows = [];
$sql = "
    SELECT
        pr.produk_id,
        pr.nama_produk,
        MAX(p.dibuat_pada) AS terakhir_terjual,
        COALESCE(SUM(pd.qty),0) AS qty_total
    FROM produk pr
    LEFT JOIN penjualan_detail pd ON pd.produk_id = pr.produk_id
    LEFT JOIN penjualan p ON p.penjualan_id = pd.penjualan_id AND p.toko_id = pr.toko_id
    WHERE pr.toko_id = ?
      AND pr.deleted_at IS NULL
      AND pr.aktif = 1
    GROUP BY pr.produk_id, pr.nama_produk
    ORDER BY MAX(p.dibuat_pada) IS NULL DESC, MAX(p.dibuat_pada) ASC
    LIMIT 10
";
$st = $pos_db->prepare($sql);
$st->bind_param('i', $tokoId);
$st->execute();
$res = $st->get_result();
while ($r = $res->fetch_assoc()) $slowRows[] = $r;
$st->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Performa Produk</title>
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
        input[type="date"], input[type="number"] { border:1px solid var(--line); border-radius:8px; padding:8px 9px; }
        .grid { margin-top:10px; display:grid; grid-template-columns:1.4fr .6fr; gap:10px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:8px 10px; border-bottom:1px solid var(--line); text-align:left; }
        th { background:#f8fafc; color:#475569; }
        td.num { text-align:right; font-variant-numeric:tabular-nums; }
        .section { padding:10px; }
        @media (max-width:960px){ .grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div>
                <h1 class="title">Performa Produk</h1>
                <p class="muted">Top produk berdasarkan omzet pada periode terpilih.</p>
            </div>
            <button class="btn" type="button" onclick="window.location.replace('../dashboard.php')">Kembali</button>
        </div>
        <form class="toolbar" method="get">
            <div><label>Dari</label><input type="date" name="from" value="<?=h($dateFrom)?>"></div>
            <div><label>Sampai</label><input type="date" name="to" value="<?=h($dateTo)?>"></div>
            <div><label>Limit</label><input type="number" name="limit" min="5" max="100" value="<?=h((string)$limit)?>"></div>
            <div><button class="btn" type="submit">Terapkan</button></div>
        </form>
        <div class="section">
            <div class="grid">
                <div class="card">
                    <div class="head"><strong>Top Produk</strong></div>
                    <div class="section" style="padding:0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Produk</th><th class="num">Qty</th><th class="num">Omzet</th><th class="num">Modal</th><th class="num">Laba Kotor</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="5" class="muted">Belum ada data produk terjual pada periode ini.</td></tr>
                            <?php else: foreach ($rows as $r): ?>
                                <tr>
                                    <td><?=h((string)$r['nama_produk'])?></td>
                                    <td class="num"><?=number_format((float)$r['qty_terjual'])?></td>
                                    <td class="num"><?=rupiah((float)$r['omzet'])?></td>
                                    <td class="num"><?=rupiah((float)$r['modal'])?></td>
                                    <td class="num"><?=rupiah((float)$r['laba_kotor'])?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card">
                    <div class="head"><strong>Produk Lambat</strong></div>
                    <div class="section" style="padding:0;">
                        <table>
                            <thead><tr><th>Produk</th><th>Terakhir Jual</th></tr></thead>
                            <tbody>
                            <?php if (!$slowRows): ?>
                                <tr><td colspan="2" class="muted">Tidak ada data produk.</td></tr>
                            <?php else: foreach ($slowRows as $r): ?>
                                <tr>
                                    <td><?=h((string)$r['nama_produk'])?></td>
                                    <td><?=h($r['terakhir_terjual'] ? (string)$r['terakhir_terjual'] : 'Belum pernah terjual')?></td>
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
