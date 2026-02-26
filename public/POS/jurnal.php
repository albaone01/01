<?php
session_start();
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';
require_once '../../inc/csrf.php';
require_once '../../inc/pos_saas_schema.php';

requireLogin();
requireDevice();
ensure_pos_saas_schema($pos_db);

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$userId = (int)($_SESSION['pengguna_id'] ?? 0);
$userNama = (string)($_SESSION['pengguna_nama'] ?? 'User');
$csrf = csrf_token();
$msg = '';
$err = '';

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
$monthStart = date('Y-m-01');
$from = $_REQUEST['from'] ?? $monthStart;
$to = $_REQUEST['to'] ?? $today;
if (!valid_date($from)) $from = $monthStart;
if (!valid_date($to)) $to = $today;
if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }

$coaMap = get_coa_map($pos_db, $tokoId);
$required = ['1101','1102','1103','1201','2101','4101'];
foreach ($required as $kode) {
    if (!isset($coaMap[$kode])) {
        $err = 'COA wajib belum lengkap, akun kode ' . $kode . ' belum ada.';
    }
}

function post_generated_journal(Database $db, int $tokoId, int $userId, string $from, string $to, array $coaMap): int {
    $posted = 0;

    // Remove previous generated posting in range, then rebuild.
    $stDel = $db->prepare("
        DELETE FROM jurnal_umum
        WHERE toko_id = ?
          AND tanggal BETWEEN ? AND ?
          AND referensi_tabel = 'generated_pos'
    ");
    $stDel->bind_param('iss', $tokoId, $from, $to);
    $stDel->execute();
    $stDel->close();

    // Penjualan
    $st = $db->prepare("
        SELECT
            x.tanggal,
            COALESCE(SUM(x.debit_cash), 0) AS debit_cash,
            COALESCE(SUM(x.debit_bank), 0) AS debit_bank,
            COALESCE(SUM(x.total_akhir), 0) AS total_penjualan
        FROM (
            SELECT
                DATE(p.dibuat_pada) AS tanggal,
                p.penjualan_id,
                p.total_akhir,
                COALESCE(SUM(CASE WHEN b.metode = 'cash' THEN b.jumlah ELSE 0 END), 0) AS debit_cash,
                COALESCE(SUM(CASE WHEN b.metode IN ('transfer','qris') THEN b.jumlah ELSE 0 END), 0) AS debit_bank
            FROM penjualan p
            LEFT JOIN pembayaran b ON b.penjualan_id = p.penjualan_id
            WHERE p.toko_id = ?
              AND DATE(p.dibuat_pada) BETWEEN ? AND ?
            GROUP BY DATE(p.dibuat_pada), p.penjualan_id, p.total_akhir
        ) x
        GROUP BY x.tanggal
        ORDER BY x.tanggal
    ");
    $st->bind_param('iss', $tokoId, $from, $to);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    foreach ($rows as $r) {
        $cash = (float)$r['debit_cash'];
        $bank = (float)$r['debit_bank'];
        $total = (float)$r['total_penjualan'];
        $totalBayarRaw = $cash + $bank;
        $bayarEfektif = min($total, max(0, $totalBayarRaw));
        $cashEfektif = 0.0;
        $bankEfektif = 0.0;
        if ($bayarEfektif > 0 && $totalBayarRaw > 0) {
            $cashEfektif = round($bayarEfektif * ($cash / $totalBayarRaw), 2);
            $bankEfektif = round($bayarEfektif - $cashEfektif, 2);
        }
        $piutang = round(max(0, $total - $bayarEfektif), 2);
        if ($total <= 0) continue;
        $lines = [];
        if ($cashEfektif > 0) $lines[] = ['akun_id' => $coaMap['1101'], 'debit' => $cashEfektif, 'kredit' => 0, 'deskripsi' => 'Penjualan tunai'];
        if ($bankEfektif > 0) $lines[] = ['akun_id' => $coaMap['1102'], 'debit' => $bankEfektif, 'kredit' => 0, 'deskripsi' => 'Penjualan non tunai'];
        if ($piutang > 0) $lines[] = ['akun_id' => $coaMap['1103'], 'debit' => $piutang, 'kredit' => 0, 'deskripsi' => 'Penjualan piutang'];
        $totalRounded = round($total, 2);
        $lines[] = ['akun_id' => $coaMap['4101'], 'debit' => 0, 'kredit' => $totalRounded, 'deskripsi' => 'Pendapatan penjualan'];
        try {
            insert_journal_entry(
                $db,
                $tokoId,
                $userId,
                new DateTimeImmutable($r['tanggal']),
                'penjualan',
                'generated_pos',
                null,
                'Posting otomatis penjualan ' . $r['tanggal'],
                $lines
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Penjualan tanggal ' . $r['tanggal'] . ' gagal balance: ' . $e->getMessage());
        }
        $posted++;
    }

    // Pembelian
    $st = $db->prepare("
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
    ");
    $st->bind_param('iss', $tokoId, $from, $to);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    foreach ($rows as $r) {
        $total = (float)$r['total_pembelian'];
        $cash = (float)$r['kredit_cash'];
        $hutang = (float)$r['kredit_hutang'];
        if ($total <= 0) continue;
        $lines = [['akun_id' => $coaMap['1201'], 'debit' => $total, 'kredit' => 0, 'deskripsi' => 'Pembelian barang']];
        if ($cash > 0) $lines[] = ['akun_id' => $coaMap['1101'], 'debit' => 0, 'kredit' => $cash, 'deskripsi' => 'Pembelian tunai'];
        if ($hutang > 0) $lines[] = ['akun_id' => $coaMap['2101'], 'debit' => 0, 'kredit' => $hutang, 'deskripsi' => 'Pembelian tempo'];
        try {
            insert_journal_entry(
                $db,
                $tokoId,
                $userId,
                new DateTimeImmutable($r['tanggal']),
                'pembelian',
                'generated_pos',
                null,
                'Posting otomatis pembelian ' . $r['tanggal'],
                $lines
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Pembelian tanggal ' . $r['tanggal'] . ' gagal balance: ' . $e->getMessage());
        }
        $posted++;
    }

    // Pembayaran piutang
    $st = $db->prepare("
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
    ");
    $st->bind_param('iss', $tokoId, $from, $to);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    foreach ($rows as $r) {
        $cash = (float)$r['debit_cash'];
        $bank = (float)$r['debit_bank'];
        $piutang = (float)$r['kredit_piutang'];
        if ($piutang <= 0) continue;
        $lines = [];
        if ($cash > 0) $lines[] = ['akun_id' => $coaMap['1101'], 'debit' => $cash, 'kredit' => 0, 'deskripsi' => 'Pelunasan piutang tunai'];
        if ($bank > 0) $lines[] = ['akun_id' => $coaMap['1102'], 'debit' => $bank, 'kredit' => 0, 'deskripsi' => 'Pelunasan piutang non tunai'];
        $lines[] = ['akun_id' => $coaMap['1103'], 'debit' => 0, 'kredit' => $piutang, 'deskripsi' => 'Pengurangan piutang'];
        try {
            insert_journal_entry(
                $db,
                $tokoId,
                $userId,
                new DateTimeImmutable($r['tanggal']),
                'piutang_pembayaran',
                'generated_pos',
                null,
                'Posting otomatis pembayaran piutang ' . $r['tanggal'],
                $lines
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Piutang pembayaran tanggal ' . $r['tanggal'] . ' gagal balance: ' . $e->getMessage());
        }
        $posted++;
    }

    // Pembayaran hutang supplier
    $st = $db->prepare("
        SELECT
            DATE(dibayar_pada) AS tanggal,
            COALESCE(SUM(jumlah), 0) AS total_bayar
        FROM pembayaran_hutang
        WHERE toko_id = ?
          AND DATE(dibayar_pada) BETWEEN ? AND ?
        GROUP BY DATE(dibayar_pada)
        ORDER BY DATE(dibayar_pada)
    ");
    $st->bind_param('iss', $tokoId, $from, $to);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    foreach ($rows as $r) {
        $total = (float)$r['total_bayar'];
        if ($total <= 0) continue;
        $lines = [
            ['akun_id' => $coaMap['2101'], 'debit' => $total, 'kredit' => 0, 'deskripsi' => 'Pembayaran hutang supplier'],
            ['akun_id' => $coaMap['1101'], 'debit' => 0, 'kredit' => $total, 'deskripsi' => 'Kas keluar pembayaran hutang supplier'],
        ];
        try {
            insert_journal_entry(
                $db,
                $tokoId,
                $userId,
                new DateTimeImmutable($r['tanggal']),
                'hutang_pembayaran',
                'generated_pos',
                null,
                'Posting otomatis pembayaran hutang ' . $r['tanggal'],
                $lines
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Hutang pembayaran tanggal ' . $r['tanggal'] . ' gagal balance: ' . $e->getMessage());
        }
        $posted++;
    }

    return $posted;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$err) {
    csrf_protect_redirect();
    $action = $_POST['action'] ?? '';
    if ($action === 'repost_generated') {
        try {
            $pos_db->begin_transaction();
            $postedCount = post_generated_journal($pos_db, $tokoId, $userId, $from, $to, $coaMap);
            $pos_db->commit();
            $msg = 'Posting jurnal berhasil. Jumlah jurnal: ' . number_format($postedCount);
        } catch (Throwable $e) {
            $pos_db->rollback();
            $err = 'Posting jurnal gagal: ' . $e->getMessage();
        }
    }
}

$summary = ['jurnal' => 0, 'debit' => 0.0, 'kredit' => 0.0];
$st = $pos_db->prepare("
    SELECT COUNT(*) AS c, COALESCE(SUM(total_debit),0) AS debit, COALESCE(SUM(total_kredit),0) AS kredit
    FROM jurnal_umum
    WHERE toko_id = ?
      AND tanggal BETWEEN ? AND ?
");
$st->bind_param('iss', $tokoId, $from, $to);
$st->execute();
$sr = $st->get_result()->fetch_assoc();
$st->close();
$summary['jurnal'] = (int)($sr['c'] ?? 0);
$summary['debit'] = (float)($sr['debit'] ?? 0);
$summary['kredit'] = (float)($sr['kredit'] ?? 0);

$rows = [];
$st = $pos_db->prepare("
    SELECT ju.jurnal_id, ju.tanggal, ju.nomor_jurnal, ju.sumber, ju.keterangan, ju.total_debit, ju.total_kredit,
           GROUP_CONCAT(CONCAT(ac.kode_akun, ' ', ac.nama_akun, ':D', FORMAT(jd.debit,2), '/K', FORMAT(jd.kredit,2)) SEPARATOR ' || ') AS detail_ringkas
    FROM jurnal_umum ju
    LEFT JOIN jurnal_detail jd ON jd.jurnal_id = ju.jurnal_id
    LEFT JOIN akun_coa ac ON ac.akun_id = jd.akun_id
    WHERE ju.toko_id = ?
      AND ju.tanggal BETWEEN ? AND ?
    GROUP BY ju.jurnal_id, ju.tanggal, ju.nomor_jurnal, ju.sumber, ju.keterangan, ju.total_debit, ju.total_kredit
    ORDER BY ju.tanggal DESC, ju.jurnal_id DESC
    LIMIT 300
");
$st->bind_param('iss', $tokoId, $from, $to);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Jurnal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8fafc; --border: #e2e8f0; --text: #0f172a; --muted: #64748b; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 24px 16px; }
        .head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
        h1 { margin: 0; font-size: 24px; }
        .btn { text-decoration: none; border: 1px solid var(--border); background: #fff; color: var(--text); border-radius: 8px; padding: 10px 14px; font-size: 13px; font-weight: 600; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 18px; margin-bottom: 12px; }
        .filter { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; align-items: end; }
        label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; font-weight: 700; }
        input { width: 100%; border: 1px solid var(--border); border-radius: 8px; padding: 10px; font-size: 13px; background: #fff; }
        button { border: 1px solid #0f172a; border-radius: 8px; padding: 10px 14px; color: #fff; background: #0f172a; font-weight: 600; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
        .stat { border: 1px solid var(--border); border-radius: 10px; padding: 10px; background: #fff; }
        .stat small { display: block; color: var(--muted); margin-bottom: 6px; font-size: 12px; }
        .stat strong { font-size: 20px; }
        .alert-ok, .alert-err { border-radius: 8px; padding: 10px; font-size: 13px; margin-bottom: 8px; }
        .alert-ok { border: 1px solid #86efac; background: #f0fdf4; color: #166534; }
        .alert-err { border: 1px solid #fca5a5; background: #fef2f2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid var(--border); padding: 9px 8px; text-align: left; vertical-align: top; }
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
            <p class="muted">Halo, <?= htmlspecialchars($userNama) ?>. Jurnal sudah tersimpan permanen di `jurnal_umum` dan `jurnal_detail`.</p>
            <?php if ($msg): ?><div class="alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <?php if ($err): ?><div class="alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

            <form method="get" class="filter" style="margin-bottom:8px;">
                <div>
                    <label>Dari Tanggal</label>
                    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
                </div>
                <div>
                    <label>Sampai Tanggal</label>
                    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
                </div>
                <div>
                    <button type="submit">Filter</button>
                </div>
            </form>

            <form method="post" onsubmit="return confirm('Posting ulang jurnal generated untuk periode ini? Data generated lama akan diganti.')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="repost_generated">
                <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
                <button type="submit">Posting Ulang Generated</button>
            </form>
        </div>

        <div class="grid">
            <div class="stat"><small>Jumlah Jurnal</small><strong><?= number_format($summary['jurnal']) ?></strong></div>
            <div class="stat"><small>Total Debit</small><strong><?= rupiah($summary['debit']) ?></strong></div>
            <div class="stat"><small>Total Kredit</small><strong><?= rupiah($summary['kredit']) ?></strong></div>
            <div class="stat"><small>Selisih</small><strong><?= rupiah(abs($summary['debit'] - $summary['kredit'])) ?></strong></div>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Daftar Jurnal</h3>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>No Jurnal</th>
                        <th>Sumber</th>
                        <th>Keterangan</th>
                        <th>Detail</th>
                        <th class="num">Debit</th>
                        <th class="num">Kredit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" class="muted">Belum ada jurnal di periode ini.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$r['tanggal']) ?></td>
                            <td><?= htmlspecialchars((string)$r['nomor_jurnal']) ?></td>
                            <td><?= htmlspecialchars((string)$r['sumber']) ?></td>
                            <td><?= htmlspecialchars((string)($r['keterangan'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($r['detail_ringkas'] ?? '-')) ?></td>
                            <td class="num"><?= rupiah((float)$r['total_debit']) ?></td>
                            <td class="num"><?= rupiah((float)$r['total_kredit']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
