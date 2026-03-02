<?php
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';
require_once '../../inc/functions.php';
require_once '../../inc/url.php';

// Pastikan user login
requireLogin();

// Pastikan device sudah terdaftar
requireDevice();

// Update last_seen device di master
$fingerprint = hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);

$stmtUpdate = $master_db->prepare("
    UPDATE master_device 
    SET last_seen = NOW()
    WHERE device_fingerprint = ?
");
$stmtUpdate->bind_param("s", $fingerprint);
$stmtUpdate->execute();

// Ambil session toko
if (!isset($_SESSION['toko_id'])) {
    header('Location: ../../../pilih_gudang.php');
    exit;
}

$toko_id = $_SESSION['toko_id'];

// Ambil data user saat ini
$user = getCurrentUser();

// Jika user tidak ditemukan, redirect ke login
if (!$user) {
    header('Location: ' . app_url('/public/admin/login.php'));
    exit;
}

function fetch_one(Database $db, string $sql, string $types = '', array $params = []): array {
    $stmt = $db->prepare($sql);
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? ($res->fetch_assoc() ?: []) : [];
    $stmt->close();
    return $row;
}

function fetch_all(Database $db, string $sql, string $types = '', array $params = []): array {
    $stmt = $db->prepare($sql);
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function to_idr(float $value): string {
    return 'Rp ' . number_format($value, 0, ',', '.');
}

$todayStat = fetch_one(
    $pos_db,
    "SELECT COUNT(*) AS trx, COALESCE(SUM(total_akhir),0) AS omzet
     FROM penjualan
     WHERE toko_id=? AND DATE(dibuat_pada)=CURDATE()",
    'i',
    [(int)$toko_id]
);

$monthStat = fetch_one(
    $pos_db,
    "SELECT COUNT(*) AS trx, COALESCE(SUM(total_akhir),0) AS omzet
     FROM penjualan
     WHERE toko_id=? AND DATE_FORMAT(dibuat_pada,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')",
    'i',
    [(int)$toko_id]
);

$productStat = fetch_one(
    $pos_db,
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN aktif=1 THEN 1 ELSE 0 END) AS aktif
     FROM produk
     WHERE toko_id=? AND deleted_at IS NULL",
    'i',
    [(int)$toko_id]
);

$customerStat = fetch_one(
    $pos_db,
    "SELECT COUNT(*) AS total
     FROM pelanggan
     WHERE toko_id=? AND deleted_at IS NULL",
    'i',
    [(int)$toko_id]
);

$stockStat = fetch_one(
    $pos_db,
    "SELECT
        COALESCE(SUM(COALESCE(s.stok_total,0) * p.harga_modal),0) AS nilai_stok,
        SUM(CASE WHEN COALESCE(s.stok_total,0) <= COALESCE(p.min_stok,0) THEN 1 ELSE 0 END) AS low_stock
     FROM produk p
     LEFT JOIN (
        SELECT produk_id, toko_id, SUM(stok) AS stok_total
        FROM stok_gudang
        WHERE toko_id=?
        GROUP BY produk_id, toko_id
     ) s ON s.produk_id = p.produk_id AND s.toko_id = p.toko_id
     WHERE p.toko_id=? AND p.deleted_at IS NULL",
    'ii',
    [(int)$toko_id, (int)$toko_id]
);

$receivableStat = fetch_one(
    $pos_db,
    "SELECT COALESCE(SUM(pt.sisa),0) AS sisa
     FROM piutang pt
     INNER JOIN penjualan pj ON pj.penjualan_id = pt.penjualan_id
     WHERE pj.toko_id=? AND pt.status <> 'lunas'",
    'i',
    [(int)$toko_id]
);

$payableStat = fetch_one(
    $pos_db,
    "SELECT COALESCE(SUM(sisa),0) AS sisa
     FROM hutang_supplier
     WHERE toko_id=? AND sisa > 0",
    'i',
    [(int)$toko_id]
);

$purchaseToday = fetch_one(
    $pos_db,
    "SELECT COUNT(*) AS trx, COALESCE(SUM(total),0) AS total
     FROM pembelian
     WHERE toko_id=? AND DATE(dibuat_pada)=CURDATE()",
    'i',
    [(int)$toko_id]
);

$sales7Rows = fetch_all(
    $pos_db,
    "SELECT DATE(dibuat_pada) AS tgl, COUNT(*) AS trx, COALESCE(SUM(total_akhir),0) AS omzet
     FROM penjualan
     WHERE toko_id=? AND DATE(dibuat_pada) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
     GROUP BY DATE(dibuat_pada)
     ORDER BY DATE(dibuat_pada) ASC",
    'i',
    [(int)$toko_id]
);

$salesMap = [];
foreach ($sales7Rows as $r) {
    $salesMap[$r['tgl']] = [
        'trx' => (int)$r['trx'],
        'omzet' => (float)$r['omzet'],
    ];
}

$sales7 = [];
$maxOmzet7 = 0.0;
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} day"));
    $label = date('d M', strtotime($date));
    $data = $salesMap[$date] ?? ['trx' => 0, 'omzet' => 0.0];
    $sales7[] = ['date' => $date, 'label' => $label, 'trx' => $data['trx'], 'omzet' => $data['omzet']];
    if ($data['omzet'] > $maxOmzet7) $maxOmzet7 = $data['omzet'];
}

$topProducts = fetch_all(
    $pos_db,
    "SELECT
        COALESCE(p.nama_produk, CONCAT('Produk #', pd.produk_id)) AS nama_produk,
        SUM(pd.qty) AS qty,
        COALESCE(SUM(pd.subtotal),0) AS nilai
     FROM penjualan_detail pd
     INNER JOIN penjualan pj ON pj.penjualan_id = pd.penjualan_id
     LEFT JOIN produk p ON p.produk_id = pd.produk_id
     WHERE pj.toko_id=? AND DATE_FORMAT(pj.dibuat_pada,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')
     GROUP BY pd.produk_id, p.nama_produk
     ORDER BY qty DESC
     LIMIT 5",
    'i',
    [(int)$toko_id]
);

$recentSales = fetch_all(
    $pos_db,
    "SELECT
        pj.nomor_invoice,
        pj.total_akhir,
        pj.dibuat_pada,
        COALESCE(u.nama,'-') AS kasir
     FROM penjualan pj
     LEFT JOIN pengguna u ON u.pengguna_id = pj.kasir_id
     WHERE pj.toko_id=?
     ORDER BY pj.dibuat_pada DESC
     LIMIT 8",
    'i',
    [(int)$toko_id]
);

$paymentToday = fetch_all(
    $pos_db,
    "SELECT pb.metode, COALESCE(SUM(pb.jumlah),0) AS total
     FROM pembayaran pb
     INNER JOIN penjualan pj ON pj.penjualan_id = pb.penjualan_id
     WHERE pj.toko_id=? AND DATE(pb.dibayar_pada)=CURDATE()
     GROUP BY pb.metode
     ORDER BY total DESC",
    'i',
    [(int)$toko_id]
);

$storeInfo = fetch_one(
    $pos_db,
    "SELECT nama_toko, alamat FROM toko WHERE toko_id=? LIMIT 1",
    'i',
    [(int)$toko_id]
);

$trxToday = (int)($todayStat['trx'] ?? 0);
$omzetToday = (float)($todayStat['omzet'] ?? 0);
$avgToday = $trxToday > 0 ? ($omzetToday / $trxToday) : 0;
$omzetMonth = (float)($monthStat['omzet'] ?? 0);
$trxMonth = (int)($monthStat['trx'] ?? 0);
$totalProduk = (int)($productStat['total'] ?? 0);
$aktifProduk = (int)($productStat['aktif'] ?? 0);
$totalPelanggan = (int)($customerStat['total'] ?? 0);
$nilaiStok = (float)($stockStat['nilai_stok'] ?? 0);
$lowStock = (int)($stockStat['low_stock'] ?? 0);
$sisaPiutang = (float)($receivableStat['sisa'] ?? 0);
$sisaHutang = (float)($payableStat['sisa'] ?? 0);
$trxBeliToday = (int)($purchaseToday['trx'] ?? 0);
$totalBeliToday = (float)($purchaseToday['total'] ?? 0);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --bg: #eef4f7;
            --surface: #ffffff;
            --border: #d9e4ec;
            --ink: #0f172a;
            --muted: #5b6b7a;
            --brand: #0f766e;
            --brand-soft: #ccfbf1;
            --warn-soft: #fff7ed;
            --warn-ink: #9a3412;
            --danger-soft: #fef2f2;
            --danger-ink: #991b1b;
            --shadow: 0 10px 34px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 8% 12%, rgba(15,118,110,0.17), transparent 26%),
                radial-gradient(circle at 90% 0%, rgba(14,116,144,0.16), transparent 22%),
                var(--bg);
        }

        .dashboard-wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 18px 14px 28px;
        }

        .hero {
            background: linear-gradient(140deg, #0b3b39 0%, #0f766e 48%, #115e59 100%);
            color: #ecfeff;
            border-radius: 20px;
            padding: 18px;
            box-shadow: var(--shadow);
            display: grid;
            gap: 10px;
            grid-template-columns: 1.7fr 1fr;
            margin-bottom: 14px;
        }
        .hero h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: .01em;
        }
        .hero .meta {
            margin-top: 4px;
            font-size: 13px;
            opacity: .88;
        }
        .hero .welcome {
            margin-top: 12px;
            font-size: 14px;
            opacity: .96;
            max-width: 680px;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }
        .hero-btn {
            text-decoration: none;
            border-radius: 10px;
            padding: 10px 12px;
            border: 1px solid rgba(255,255,255,.35);
            color: #ecfeff;
            font-size: 13px;
            font-weight: 700;
            background: rgba(255,255,255,0.11);
        }
        .hero-side {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 14px;
            padding: 14px;
        }
        .hero-side .label {
            font-size: 12px;
            opacity: .9;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .hero-side .value {
            margin-top: 4px;
            font-size: 22px;
            font-weight: 800;
        }
        .hero-side .sub {
            margin-top: 5px;
            font-size: 12px;
            opacity: .88;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }
        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px;
            box-shadow: var(--shadow);
        }
        .kpi-title {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .kpi-value {
            margin-top: 7px;
            font-size: 22px;
            font-weight: 800;
            line-height: 1.15;
        }
        .kpi-sub {
            margin-top: 4px;
            font-size: 12px;
            color: var(--muted);
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 12px;
        }
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow);
            padding: 12px;
        }
        .panel h3 {
            margin: 0 0 10px;
            font-size: 16px;
        }
        .panel-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
        }
        .panel-note {
            font-size: 12px;
            color: var(--muted);
        }

        .chart {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            align-items: end;
            gap: 8px;
            min-height: 190px;
        }
        .bar-wrap {
            display: flex;
            flex-direction: column;
            gap: 5px;
            align-items: center;
        }
        .bar {
            width: 100%;
            max-width: 56px;
            border-radius: 10px 10px 6px 6px;
            background: linear-gradient(180deg, #0ea5e9 0%, #0369a1 100%);
            min-height: 8px;
        }
        .bar-value {
            font-size: 11px;
            color: var(--muted);
            text-align: center;
        }
        .bar-label {
            font-size: 11px;
            color: var(--ink);
            font-weight: 600;
        }

        .list {
            display: grid;
            gap: 8px;
        }
        .list-item {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .list-item .title {
            font-size: 13px;
            font-weight: 700;
        }
        .list-item .subtitle {
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
        }
        .list-item .amount {
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }
        .empty {
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .badge {
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid var(--border);
            background: #f8fbff;
        }
        a.badge {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: transform .15s ease, filter .15s ease;
        }
        a.badge:hover {
            transform: translateY(-1px);
            filter: brightness(0.98);
        }
        .badge.warn {
            background: var(--warn-soft);
            color: var(--warn-ink);
            border-color: #fed7aa;
        }
        .badge.danger {
            background: var(--danger-soft);
            color: var(--danger-ink);
            border-color: #fecaca;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        .quick-links a {
            text-decoration: none;
            color: var(--ink);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 9px 10px;
            font-size: 13px;
            font-weight: 700;
            background: #fff;
        }
        .quick-links a:hover {
            background: #f8fafc;
        }

        @media (max-width: 1080px) {
            .hero { grid-template-columns: 1fr; }
            .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .main-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 640px) {
            .kpi-grid { grid-template-columns: 1fr; }
            .quick-links { grid-template-columns: 1fr; }
            .hero h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <?php include '../../inc/header.php'; ?>
    <main class="dashboard-wrap">
        <section class="hero">
            <div>
                <h1>Dashboard Backoffice</h1>
                <div class="meta">
                    <?= htmlspecialchars((string)($storeInfo['nama_toko'] ?? 'Toko')) ?>
                    <?php if (!empty($storeInfo['alamat'])): ?>
                        | <?= htmlspecialchars((string)$storeInfo['alamat']) ?>
                    <?php endif; ?>
                </div>
                <div class="welcome">
                    Selamat datang, <?= htmlspecialchars((string)($user['nama'] ?? 'User')) ?>.
                    Ringkasan ini menampilkan performa harian, kesehatan stok, dan posisi hutang-piutang berdasarkan data transaksi aktual.
                </div>
                <div class="hero-actions">
                    <a class="hero-btn" href="<?= htmlspecialchars(app_url('/public/POS/index.php')) ?>">Buka POS</a>
                    <a class="hero-btn" href="<?= htmlspecialchars(app_url('/public/admin/produk/master_barang.php')) ?>">Master Barang</a>
                    <a class="hero-btn" href="<?= htmlspecialchars(app_url('/public/admin/purchase_order/index.php')) ?>">Purchase Order</a>
                    <a class="hero-btn" href="<?= htmlspecialchars(app_url('/public/admin/laporan_stok.php')) ?>">Laporan Stok</a>
                </div>
            </div>
            <div class="hero-side">
                <div class="label">Omzet Hari Ini</div>
                <div class="value"><?= to_idr($omzetToday) ?></div>
                <div class="sub"><?= number_format($trxToday) ?> transaksi | Rata-rata <?= to_idr($avgToday) ?> / transaksi</div>
                <div class="sub" style="margin-top:10px;">
                    Pembelian hari ini: <?= number_format($trxBeliToday) ?> transaksi (<?= to_idr($totalBeliToday) ?>)
                </div>
            </div>
        </section>

        <section class="kpi-grid">
            <article class="kpi-card">
                <p class="kpi-title">Transaksi Bulan Ini</p>
                <div class="kpi-value"><?= number_format($trxMonth) ?></div>
                <div class="kpi-sub">Omzet bulan ini <?= to_idr($omzetMonth) ?></div>
            </article>
            <article class="kpi-card">
                <p class="kpi-title">Katalog Produk</p>
                <div class="kpi-value"><?= number_format($totalProduk) ?></div>
                <div class="kpi-sub"><?= number_format($aktifProduk) ?> produk aktif</div>
            </article>
            <article class="kpi-card">
                <p class="kpi-title">Pelanggan</p>
                <div class="kpi-value"><?= number_format($totalPelanggan) ?></div>
                <div class="kpi-sub">Pelanggan terdaftar</div>
            </article>
            <article class="kpi-card">
                <p class="kpi-title">Nilai Stok</p>
                <div class="kpi-value"><?= to_idr($nilaiStok) ?></div>
                <div class="kpi-sub"><?= number_format($lowStock) ?> produk stok rendah</div>
            </article>
        </section>

        <section class="main-grid">
            <div class="panel">
                <div class="panel-head">
                    <h3>Tren Omzet 7 Hari Terakhir</h3>
                    <span class="panel-note">Berdasarkan tabel penjualan</span>
                </div>
                <div class="chart">
                    <?php foreach ($sales7 as $row): ?>
                        <?php
                            $height = $maxOmzet7 > 0 ? max(8, (int)round(($row['omzet'] / $maxOmzet7) * 140)) : 8;
                        ?>
                        <div class="bar-wrap">
                            <div class="bar-value"><?= to_idr((float)$row['omzet']) ?></div>
                            <div class="bar" style="height: <?= $height ?>px;" title="<?= htmlspecialchars($row['label']) ?> - <?= to_idr((float)$row['omzet']) ?>"></div>
                            <div class="bar-label"><?= htmlspecialchars($row['label']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h3>Status Keuangan Operasional</h3>
                    <span class="panel-note">Hutang/Piutang</span>
                </div>
                <div class="badges">
                    <a class="badge danger" href="<?= htmlspecialchars(app_url('/public/admin/piutang/index.php')) ?>">Piutang: <?= to_idr($sisaPiutang) ?></a>
                    <a class="badge warn" href="<?= htmlspecialchars(app_url('/public/admin/hutang_supplier/index.php')) ?>">Hutang Supplier: <?= to_idr($sisaHutang) ?></a>
                </div>

                <h3 style="margin-top:14px;">Metode Pembayaran Hari Ini</h3>
                <?php if (empty($paymentToday)): ?>
                    <div class="empty">Belum ada pembayaran hari ini.</div>
                <?php else: ?>
                    <div class="list">
                        <?php foreach ($paymentToday as $p): ?>
                            <div class="list-item">
                                <div>
                                    <div class="title"><?= strtoupper(htmlspecialchars((string)$p['metode'])) ?></div>
                                    <div class="subtitle">Dari pembayaran penjualan</div>
                                </div>
                                <div class="amount"><?= to_idr((float)$p['total']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h3 style="margin-top:14px;">Akses Cepat</h3>
                <div class="quick-links">
                    <a href="<?= htmlspecialchars(app_url('/public/admin/pembelian/index.php')) ?>">Pembelian</a>
                    <a href="<?= htmlspecialchars(app_url('/public/admin/retur/index.php')) ?>">Retur</a>
                    <a href="<?= htmlspecialchars(app_url('/public/admin/piutang/index.php')) ?>">Piutang Customer</a>
                    <a href="<?= htmlspecialchars(app_url('/public/admin/hutang_supplier/index.php')) ?>">Hutang Supplier</a>
                    <a href="<?= htmlspecialchars(app_url('/public/admin/pelanggan/index.php')) ?>">Pelanggan</a>
                    <a href="<?= htmlspecialchars(app_url('/public/admin/users.php')) ?>">Manajemen User</a>
                </div>
            </div>
        </section>

        <section class="main-grid" style="margin-top:12px;">
            <div class="panel">
                <div class="panel-head">
                    <h3>Top Produk Terjual Bulan Ini</h3>
                    <span class="panel-note">Berdasarkan penjualan_detail</span>
                </div>
                <?php if (empty($topProducts)): ?>
                    <div class="empty">Belum ada data penjualan di bulan ini.</div>
                <?php else: ?>
                    <div class="list">
                        <?php foreach ($topProducts as $tp): ?>
                            <div class="list-item">
                                <div>
                                    <div class="title"><?= htmlspecialchars((string)$tp['nama_produk']) ?></div>
                                    <div class="subtitle">Qty terjual: <?= number_format((float)$tp['qty'], 0, ',', '.') ?></div>
                                </div>
                                <div class="amount"><?= to_idr((float)$tp['nilai']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h3>Transaksi Terbaru</h3>
                    <span class="panel-note">8 invoice terakhir</span>
                </div>
                <?php if (empty($recentSales)): ?>
                    <div class="empty">Belum ada transaksi penjualan.</div>
                <?php else: ?>
                    <div class="list">
                        <?php foreach ($recentSales as $tx): ?>
                            <div class="list-item">
                                <div>
                                    <div class="title"><?= htmlspecialchars((string)$tx['nomor_invoice']) ?></div>
                                    <div class="subtitle">
                                        <?= htmlspecialchars((string)$tx['kasir']) ?> |
                                        <?= htmlspecialchars((string)date('d M Y H:i', strtotime((string)$tx['dibuat_pada']))) ?>
                                    </div>
                                </div>
                                <div class="amount"><?= to_idr((float)$tx['total_akhir']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
