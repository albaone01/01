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

function table_exists(Database $db, string $table): bool {
    $st = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $st->bind_param('s', $table);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_assoc();
    $st->close();
    return $ok;
}

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$from = $_GET['from'] ?? $monthStart;
$to = $_GET['to'] ?? $today;

if (!valid_date($from)) $from = $monthStart;
if (!valid_date($to)) $to = $today;
if ($from > $to) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}

$entries = [];

// Sumber 1: Penjualan + Pembayaran (cash/bank/piutang)
$sqlSales = "
    SELECT
        DATE(p.dibuat_pada) AS tanggal,
        COALESCE(SUM(CASE WHEN b.metode = 'cash' THEN b.jumlah ELSE 0 END), 0) AS debit_cash,
        COALESCE(SUM(CASE WHEN b.metode IN ('transfer','qris') THEN b.jumlah ELSE 0 END), 0) AS debit_bank,
        COALESCE(SUM(p.total_akhir), 0) AS total_penjualan
    FROM penjualan p
    LEFT JOIN pembayaran b ON b.penjualan_id = p.penjualan_id
    WHERE p.toko_id = ?
      AND DATE(p.dibuat_pada) BETWEEN ? AND ?
    GROUP BY DATE(p.dibuat_pada)
    ORDER BY DATE(p.dibuat_pada)
";
$st = $pos_db->prepare($sqlSales);
$st->bind_param('iss', $tokoId, $from, $to);
$st->execute();
$salesRows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

foreach ($salesRows as $r) {
    $tgl = $r['tanggal'];
    $cash = (float)$r['debit_cash'];
    $bank = (float)$r['debit_bank'];
    $totalPenjualan = (float)$r['total_penjualan'];
    $piutang = max(0, $totalPenjualan - ($cash + $bank));

    if ($cash > 0) {
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Kas', 'debit' => $cash, 'kredit' => 0.0, 'keterangan' => 'Penerimaan penjualan tunai'];
    }
    if ($bank > 0) {
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Bank/QRIS', 'debit' => $bank, 'kredit' => 0.0, 'keterangan' => 'Penerimaan penjualan non tunai'];
    }
    if ($piutang > 0) {
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Piutang Usaha', 'debit' => $piutang, 'kredit' => 0.0, 'keterangan' => 'Penjualan belum lunas'];
    }
    if ($totalPenjualan > 0) {
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Penjualan', 'debit' => 0.0, 'kredit' => $totalPenjualan, 'keterangan' => 'Omzet penjualan'];
    }
}

// Sumber 2: Pembelian (cash / tempo)
$sqlPurch = "
    SELECT
        DATE(dibuat_pada) AS tanggal,
        COALESCE(SUM(total), 0) AS total_pembelian,
        COALESCE(SUM(CASE WHEN tipe_faktur = 'cash' THEN total ELSE 0 END), 0) AS kredit_cash,
        COALESCE(SUM(CASE WHEN tipe_faktur = 'tempo' THEN total ELSE 0 END), 0) AS kredit_hutang
    FROM pembelian
    WHERE toko_id = ?
      AND DATE(dibuat_pada) BETWEEN ? AND ?
    GROUP BY DATE(dibuat_pada)
    ORDER BY DATE(dibuat_pada)
";
$st = $pos_db->prepare($sqlPurch);
$st->bind_param('iss', $tokoId, $from, $to);
$st->execute();
$purchRows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

foreach ($purchRows as $r) {
    $tgl = $r['tanggal'];
    $total = (float)$r['total_pembelian'];
    $kreditCash = (float)$r['kredit_cash'];
    $kreditHutang = (float)$r['kredit_hutang'];
    if ($total > 0) {
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Persediaan', 'debit' => $total, 'kredit' => 0.0, 'keterangan' => 'Pembelian barang'];
    }
    if ($kreditCash > 0) {
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Kas', 'debit' => 0.0, 'kredit' => $kreditCash, 'keterangan' => 'Pembelian tunai'];
    }
    if ($kreditHutang > 0) {
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Hutang Dagang', 'debit' => 0.0, 'kredit' => $kreditHutang, 'keterangan' => 'Pembelian tempo'];
    }
}

// Sumber 3: Pembayaran piutang pelanggan
$sqlPiutangBayar = "
    SELECT
        DATE(pp.dibayar_pada) AS tanggal,
        COALESCE(SUM(CASE WHEN pp.metode = 'cash' THEN pp.jumlah ELSE 0 END), 0) AS debit_cash,
        COALESCE(SUM(CASE WHEN pp.metode IN ('transfer','qris') THEN pp.jumlah ELSE 0 END), 0) AS debit_bank,
        COALESCE(SUM(pp.jumlah), 0) AS kredit_piutang
    FROM piutang_pembayaran pp
    INNER JOIN piutang pt ON pt.piutang_id = pp.piutang_id
    INNER JOIN penjualan p ON p.penjualan_id = pt.penjualan_id
    WHERE p.toko_id = ?
      AND DATE(pp.dibayar_pada) BETWEEN ? AND ?
    GROUP BY DATE(pp.dibayar_pada)
    ORDER BY DATE(pp.dibayar_pada)
";
$st = $pos_db->prepare($sqlPiutangBayar);
$st->bind_param('iss', $tokoId, $from, $to);
$st->execute();
$piuRows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

foreach ($piuRows as $r) {
    $tgl = $r['tanggal'];
    $cash = (float)$r['debit_cash'];
    $bank = (float)$r['debit_bank'];
    $total = (float)$r['kredit_piutang'];
    if ($cash > 0) {
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Kas', 'debit' => $cash, 'kredit' => 0.0, 'keterangan' => 'Penerimaan pelunasan piutang'];
    }
    if ($bank > 0) {
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Bank/QRIS', 'debit' => $bank, 'kredit' => 0.0, 'keterangan' => 'Pelunasan piutang non tunai'];
    }
    if ($total > 0) {
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Piutang Usaha', 'debit' => 0.0, 'kredit' => $total, 'keterangan' => 'Pengurangan piutang pelanggan'];
    }
}

// Sumber 4: Pembayaran hutang supplier
$sqlHutangBayar = "
    SELECT
        DATE(dibayar_pada) AS tanggal,
        COALESCE(SUM(jumlah), 0) AS total_bayar
    FROM pembayaran_hutang
    WHERE toko_id = ?
      AND DATE(dibayar_pada) BETWEEN ? AND ?
    GROUP BY DATE(dibayar_pada)
    ORDER BY DATE(dibayar_pada)
";
$st = $pos_db->prepare($sqlHutangBayar);
$st->bind_param('iss', $tokoId, $from, $to);
$st->execute();
$hutRows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

foreach ($hutRows as $r) {
    $tgl = $r['tanggal'];
    $total = (float)$r['total_bayar'];
    if ($total > 0) {
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Hutang Dagang', 'debit' => $total, 'kredit' => 0.0, 'keterangan' => 'Pembayaran hutang supplier'];
        $entries[] = ['tanggal' => $tgl, 'akun' => 'Kas', 'debit' => 0.0, 'kredit' => $total, 'keterangan' => 'Kas keluar bayar hutang supplier'];
    }
}

usort($entries, static function(array $a, array $b): int {
    if ($a['tanggal'] === $b['tanggal']) {
        return strcmp($a['akun'], $b['akun']);
    }
    return strcmp($a['tanggal'], $b['tanggal']);
});

$totalDebit = 0.0;
$totalKredit = 0.0;
foreach ($entries as $e) {
    $totalDebit += (float)$e['debit'];
    $totalKredit += (float)$e['kredit'];
}

$missingTables = [];
if (!table_exists($pos_db, 'jurnal_umum')) $missingTables[] = 'jurnal_umum';
if (!table_exists($pos_db, 'jurnal_detail')) $missingTables[] = 'jurnal_detail';
if (!table_exists($pos_db, 'kasir_shift')) $missingTables[] = 'kasir_shift';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Jurnal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 24px 16px; }
        .head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
        h1 { margin: 0; font-size: 24px; }
        .btn { text-decoration: none; border: 1px solid var(--border); background: #fff; color: var(--text); border-radius: 8px; padding: 10px 14px; font-size: 13px; font-weight: 600; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 18px; margin-bottom: 12px; }
        .filter { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px; align-items: end; }
        label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; font-weight: 700; }
        input { width: 100%; border: 1px solid var(--border); border-radius: 8px; padding: 10px; font-size: 13px; background: #fff; }
        button { border: 1px solid #0f172a; border-radius: 8px; padding: 10px 14px; color: #fff; background: #0f172a; font-weight: 600; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
        .stat { border: 1px solid var(--border); border-radius: 10px; padding: 10px; background: #fff; }
        .stat small { display: block; color: var(--muted); margin-bottom: 6px; font-size: 12px; }
        .stat strong { font-size: 20px; }
        .warn { border: 1px solid #fde68a; background: #fffbeb; color: #92400e; border-radius: 8px; padding: 10px; font-size: 13px; margin-bottom: 10px; }
        ul { margin: 0; padding-left: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid var(--border); padding: 9px 8px; text-align: left; }
        th { font-size: 11px; text-transform: uppercase; color: var(--muted); }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { color: var(--muted); font-size: 14px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <h1>Jurnal</h1>
            <a href="index.php" class="btn">Kembali ke Menu POS</a>
        </div>

        <div class="card">
            <p class="muted">Halo, <?= htmlspecialchars($userNama) ?>. Halaman ini menghasilkan jurnal otomatis dari data transaksi.</p>
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
                    <button type="submit">Terapkan Filter</button>
                </div>
            </form>
        </div>

        <?php if ($missingTables): ?>
            <div class="warn">
                Tabel berikut belum ada di skema: 
                <ul>
                    <?php foreach ($missingTables as $tb): ?><li><?= htmlspecialchars($tb) ?></li><?php endforeach; ?>
                </ul>
                Saat ini jurnal bersifat generated report, belum posting permanen ke ledger.
            </div>
        <?php endif; ?>

        <div class="grid">
            <div class="stat"><small>Jumlah Baris Jurnal</small><strong><?= number_format(count($entries)) ?></strong></div>
            <div class="stat"><small>Total Debit</small><strong><?= rupiah($totalDebit) ?></strong></div>
            <div class="stat"><small>Total Kredit</small><strong><?= rupiah($totalKredit) ?></strong></div>
            <div class="stat"><small>Selisih</small><strong><?= rupiah(abs($totalDebit - $totalKredit)) ?></strong></div>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Jurnal Generated</h3>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Akun</th>
                        <th>Keterangan</th>
                        <th class="num">Debit</th>
                        <th class="num">Kredit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$entries): ?>
                        <tr><td colspan="5" class="muted">Tidak ada data jurnal pada periode ini.</td></tr>
                    <?php else: foreach ($entries as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d-m-Y', strtotime($e['tanggal']))) ?></td>
                            <td><?= htmlspecialchars($e['akun']) ?></td>
                            <td><?= htmlspecialchars($e['keterangan']) ?></td>
                            <td class="num"><?= $e['debit'] > 0 ? rupiah((float)$e['debit']) : '-' ?></td>
                            <td class="num"><?= $e['kredit'] > 0 ? rupiah((float)$e['kredit']) : '-' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="num">Total</th>
                        <th class="num"><?= rupiah($totalDebit) ?></th>
                        <th class="num"><?= rupiah($totalKredit) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>
</html>
