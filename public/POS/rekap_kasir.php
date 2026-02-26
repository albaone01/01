<?php
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

function rupiah(float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function valid_date(string $v): bool {
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}

$today = date('Y-m-d');
$from = $_GET['from'] ?? $today;
$to = $_GET['to'] ?? $today;
$kasirId = (int)($_GET['kasir_id'] ?? 0);

if (!valid_date($from)) $from = $today;
if (!valid_date($to)) $to = $today;
if ($from > $to) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}

$kasirList = [];
$stKasir = $pos_db->prepare("SELECT pengguna_id, nama FROM pengguna WHERE toko_id = ? AND peran = 'kasir' AND aktif = 1 AND deleted_at IS NULL ORDER BY nama");
$stKasir->bind_param('i', $tokoId);
$stKasir->execute();
$kasirList = $stKasir->get_result()->fetch_all(MYSQLI_ASSOC);
$stKasir->close();

$summary = [
    'trx' => 0,
    'omzet' => 0.0,
    'cash' => 0.0,
    'non_tunai' => 0.0,
    'piutang' => 0.0,
];

$sqlSummary = "
    SELECT
        COUNT(x.penjualan_id) AS trx,
        COALESCE(SUM(x.total_akhir), 0) AS omzet,
        COALESCE(SUM(x.bayar_cash), 0) AS cash,
        COALESCE(SUM(x.bayar_non_tunai), 0) AS non_tunai,
        COALESCE(SUM(GREATEST(x.total_akhir - x.total_bayar, 0)), 0) AS piutang
    FROM (
        SELECT
            p.penjualan_id,
            p.total_akhir,
            COALESCE(SUM(CASE WHEN b.metode = 'cash' THEN b.jumlah ELSE 0 END), 0) AS bayar_cash,
            COALESCE(SUM(CASE WHEN b.metode IN ('transfer','qris') THEN b.jumlah ELSE 0 END), 0) AS bayar_non_tunai,
            COALESCE(SUM(b.jumlah), 0) AS total_bayar
        FROM penjualan p
        LEFT JOIN pembayaran b ON b.penjualan_id = p.penjualan_id
        WHERE p.toko_id = ?
          AND DATE(p.dibuat_pada) BETWEEN ? AND ?
          AND (? = 0 OR p.kasir_id = ?)
        GROUP BY p.penjualan_id, p.total_akhir
    ) x
";
$stSum = $pos_db->prepare($sqlSummary);
$stSum->bind_param('issii', $tokoId, $from, $to, $kasirId, $kasirId);
$stSum->execute();
$sumRow = $stSum->get_result()->fetch_assoc();
$stSum->close();
if ($sumRow) {
    $summary['trx'] = (int)$sumRow['trx'];
    $summary['omzet'] = (float)$sumRow['omzet'];
    $summary['cash'] = (float)$sumRow['cash'];
    $summary['non_tunai'] = (float)$sumRow['non_tunai'];
    $summary['piutang'] = (float)$sumRow['piutang'];
}

$rowsKasir = [];
$sqlKasir = "
    SELECT
        u.pengguna_id,
        u.nama AS nama_kasir,
        COUNT(x.penjualan_id) AS trx,
        COALESCE(SUM(x.total_akhir), 0) AS omzet,
        COALESCE(SUM(x.bayar_cash), 0) AS cash,
        COALESCE(SUM(x.bayar_non_tunai), 0) AS non_tunai,
        COALESCE(SUM(GREATEST(x.total_akhir - x.total_bayar, 0)), 0) AS piutang
    FROM (
        SELECT
            p.penjualan_id,
            p.kasir_id,
            p.total_akhir,
            COALESCE(SUM(CASE WHEN b.metode = 'cash' THEN b.jumlah ELSE 0 END), 0) AS bayar_cash,
            COALESCE(SUM(CASE WHEN b.metode IN ('transfer','qris') THEN b.jumlah ELSE 0 END), 0) AS bayar_non_tunai,
            COALESCE(SUM(b.jumlah), 0) AS total_bayar
        FROM penjualan p
        LEFT JOIN pembayaran b ON b.penjualan_id = p.penjualan_id
        WHERE p.toko_id = ?
          AND DATE(p.dibuat_pada) BETWEEN ? AND ?
          AND (? = 0 OR p.kasir_id = ?)
        GROUP BY p.penjualan_id, p.kasir_id, p.total_akhir
    ) x
    INNER JOIN pengguna u ON u.pengguna_id = x.kasir_id
    GROUP BY u.pengguna_id, u.nama
    ORDER BY omzet DESC, trx DESC, u.nama
";
$stGroup = $pos_db->prepare($sqlKasir);
$stGroup->bind_param('issii', $tokoId, $from, $to, $kasirId, $kasirId);
$stGroup->execute();
$rowsKasir = $stGroup->get_result()->fetch_all(MYSQLI_ASSOC);
$stGroup->close();

$rowsInvoice = [];
$sqlInv = "
    SELECT
        p.penjualan_id,
        p.nomor_invoice,
        p.dibuat_pada,
        p.total_akhir,
        u.nama AS nama_kasir,
        COALESCE(pl.nama_pelanggan, 'Walk-in') AS nama_pelanggan,
        COALESCE(SUM(b.jumlah), 0) AS total_bayar
    FROM penjualan p
    INNER JOIN pengguna u ON u.pengguna_id = p.kasir_id
    LEFT JOIN pelanggan pl ON pl.pelanggan_id = p.pelanggan_id
    LEFT JOIN pembayaran b ON b.penjualan_id = p.penjualan_id
    WHERE p.toko_id = ?
      AND DATE(p.dibuat_pada) BETWEEN ? AND ?
      AND (? = 0 OR p.kasir_id = ?)
    GROUP BY p.penjualan_id, p.nomor_invoice, p.dibuat_pada, p.total_akhir, u.nama, pl.nama_pelanggan
    ORDER BY p.penjualan_id DESC
    LIMIT 200
";
$stInv = $pos_db->prepare($sqlInv);
$stInv->bind_param('issii', $tokoId, $from, $to, $kasirId, $kasirId);
$stInv->execute();
$rowsInvoice = $stInv->get_result()->fetch_all(MYSQLI_ASSOC);
$stInv->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Rekap Kasir</title>
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
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }
        .stat {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
        }
        .stat small { color: var(--muted); display: block; font-size: 12px; margin-bottom: 6px; }
        .stat strong { font-size: 20px; }
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
        .filter {
            margin-bottom: 12px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 8px;
            align-items: end;
        }
        label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; font-weight: 700; }
        input, select {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px;
            font-size: 13px;
            background: #fff;
        }
        button {
            border: 1px solid #0f172a;
            border-radius: 8px;
            padding: 10px 14px;
            color: #fff;
            background: #0f172a;
            font-weight: 600;
            cursor: pointer;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            border-bottom: 1px solid var(--border);
            padding: 9px 8px;
            text-align: left;
        }
        th { font-size: 11px; text-transform: uppercase; color: var(--muted); }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { color: var(--muted); font-size: 14px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <h1>Rekap Kasir</h1>
            <a href="index.php" class="btn">Kembali ke Menu POS</a>
        </div>

        <div class="card">
            <p class="muted">Halo, <?= htmlspecialchars($userNama) ?>. Rekap transaksi kasir berdasarkan data riil dari tabel `penjualan` dan `pembayaran`.</p>
            <form method="get" class="filter">
                <div>
                    <label>Dari Tanggal</label>
                    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
                </div>
                <div>
                    <label>Sampai Tanggal</label>
                    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
                </div>
                <div>
                    <label>Kasir</label>
                    <select name="kasir_id">
                        <option value="0">Semua Kasir</option>
                        <?php foreach ($kasirList as $k): ?>
                            <option value="<?= (int)$k['pengguna_id'] ?>" <?= $kasirId === (int)$k['pengguna_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit">Terapkan Filter</button>
                </div>
            </form>
        </div>

        <div class="grid">
            <div class="stat"><small>Total Transaksi</small><strong><?= number_format($summary['trx']) ?></strong></div>
            <div class="stat"><small>Omzet</small><strong><?= rupiah($summary['omzet']) ?></strong></div>
            <div class="stat"><small>Pembayaran Tunai</small><strong><?= rupiah($summary['cash']) ?></strong></div>
            <div class="stat"><small>Pembayaran Non Tunai</small><strong><?= rupiah($summary['non_tunai']) ?></strong></div>
            <div class="stat"><small>Sisa Piutang</small><strong><?= rupiah($summary['piutang']) ?></strong></div>
        </div>

        <div class="card" style="margin-top: 12px;">
            <h3 style="margin-top:0;">Ringkasan Per Kasir</h3>
            <table>
                <thead>
                    <tr>
                        <th>Kasir</th>
                        <th class="num">Transaksi</th>
                        <th class="num">Omzet</th>
                        <th class="num">Tunai</th>
                        <th class="num">Non Tunai</th>
                        <th class="num">Piutang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rowsKasir): ?>
                        <tr><td colspan="6" class="muted">Tidak ada transaksi di periode ini.</td></tr>
                    <?php else: foreach ($rowsKasir as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['nama_kasir']) ?></td>
                            <td class="num"><?= number_format((int)$r['trx']) ?></td>
                            <td class="num"><?= rupiah((float)$r['omzet']) ?></td>
                            <td class="num"><?= rupiah((float)$r['cash']) ?></td>
                            <td class="num"><?= rupiah((float)$r['non_tunai']) ?></td>
                            <td class="num"><?= rupiah((float)$r['piutang']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="margin-top: 12px;">
            <h3 style="margin-top:0;">Detail Invoice</h3>
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Invoice</th>
                        <th>Kasir</th>
                        <th>Pelanggan</th>
                        <th class="num">Total</th>
                        <th class="num">Dibayar</th>
                        <th class="num">Sisa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rowsInvoice): ?>
                        <tr><td colspan="7" class="muted">Tidak ada invoice pada filter ini.</td></tr>
                    <?php else: foreach ($rowsInvoice as $r):
                        $total = (float)$r['total_akhir'];
                        $bayar = (float)$r['total_bayar'];
                        $sisa = max(0, $total - $bayar);
                    ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($r['dibuat_pada']))) ?></td>
                            <td><?= htmlspecialchars($r['nomor_invoice']) ?></td>
                            <td><?= htmlspecialchars($r['nama_kasir']) ?></td>
                            <td><?= htmlspecialchars($r['nama_pelanggan']) ?></td>
                            <td class="num"><?= rupiah($total) ?></td>
                            <td class="num"><?= rupiah($bayar) ?></td>
                            <td class="num"><?= rupiah($sisa) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
