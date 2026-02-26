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
$deviceId = (int)($_SESSION['device_id'] ?? 0);
$userNama = (string)($_SESSION['pengguna_nama'] ?? 'User');
$csrf = csrf_token();
$msg = '';
$err = '';
$needOpenShift = isset($_GET['need_open_shift']) && $_GET['need_open_shift'] === '1';

if ($tokoId <= 0 || $userId <= 0) {
    http_response_code(400);
    exit('Sesi tidak valid.');
}

function rupiah(float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function get_shift_summary(Database $db, int $tokoId, int $kasirId): array {
    $sql = "
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
              AND p.kasir_id = ?
              AND DATE(p.dibuat_pada) = CURRENT_DATE()
            GROUP BY p.penjualan_id, p.total_akhir
        ) x
    ";
    $st = $db->prepare($sql);
    $st->bind_param('ii', $tokoId, $kasirId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return [
        'trx' => (int)($row['trx'] ?? 0),
        'omzet' => (float)($row['omzet'] ?? 0),
        'cash' => (float)($row['cash'] ?? 0),
        'non_tunai' => (float)($row['non_tunai'] ?? 0),
        'piutang' => (float)($row['piutang'] ?? 0),
    ];
}

$shift = get_today_shift($pos_db, $tokoId, $userId);
$summary = get_shift_summary($pos_db, $tokoId, $userId);
$coaMap = get_coa_map($pos_db, $tokoId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect_redirect();
    $action = $_POST['action'] ?? '';

    if ($action === 'open_shift') {
        if ($shift) {
            if (($shift['status'] ?? '') === 'open') {
                $msg = 'Shift hari ini sudah terbuka.';
            } else {
                $err = 'Shift hari ini sudah ditutup. Tidak bisa dibuka ulang.';
            }
        } else {
            $modalAwal = max(0, (float)($_POST['modal_awal'] ?? 0));
            $status = 'open';
            $st = $pos_db->prepare("
                INSERT INTO kasir_shift (toko_id, kasir_id, device_id, tanggal_shift, modal_awal, kas_sistem, kas_fisik, selisih, status)
                VALUES (?, ?, ?, CURRENT_DATE(), ?, 0, 0, 0, ?)
            ");
            $st->bind_param('iiids', $tokoId, $userId, $deviceId, $modalAwal, $status);
            $st->execute();
            $st->close();
            $msg = 'Shift berhasil dibuka.';
            $shift = get_today_shift($pos_db, $tokoId, $userId);
        }
    }

    if ($action === 'close_shift') {
        if (!$shift || ($shift['status'] ?? '') !== 'open') {
            $err = 'Shift belum terbuka atau sudah ditutup.';
        } else {
            try {
                $kasFisik = max(0, (float)($_POST['kas_fisik'] ?? 0));
                $catatan = trim((string)($_POST['catatan'] ?? ''));
                $kasSistem = (float)$summary['cash'] + (float)($shift['modal_awal'] ?? 0);
                $selisih = $kasFisik - $kasSistem;
                $shiftId = (int)$shift['shift_id'];

                $pos_db->begin_transaction();

                $status = 'closed';
                $st = $pos_db->prepare("
                    UPDATE kasir_shift
                    SET jam_tutup = NOW(),
                        kas_sistem = ?,
                        kas_fisik = ?,
                        selisih = ?,
                        status = ?,
                        catatan = ?
                    WHERE shift_id = ?
                ");
                $st->bind_param('dddssi', $kasSistem, $kasFisik, $selisih, $status, $catatan, $shiftId);
                $st->execute();
                $st->close();

                if (abs($selisih) > 0.0001 && isset($coaMap['1101']) && isset($coaMap['4201']) && isset($coaMap['5101'])) {
                    $lines = [];
                    if ($selisih > 0) {
                        $lines[] = ['akun_id' => $coaMap['1101'], 'debit' => $selisih, 'kredit' => 0, 'deskripsi' => 'Selisih kas lebih'];
                        $lines[] = ['akun_id' => $coaMap['4201'], 'debit' => 0, 'kredit' => $selisih, 'deskripsi' => 'Pendapatan selisih kas'];
                    } else {
                        $v = abs($selisih);
                        $lines[] = ['akun_id' => $coaMap['5101'], 'debit' => $v, 'kredit' => 0, 'deskripsi' => 'Beban selisih kas'];
                        $lines[] = ['akun_id' => $coaMap['1101'], 'debit' => 0, 'kredit' => $v, 'deskripsi' => 'Selisih kas kurang'];
                    }
                    insert_journal_entry(
                        $pos_db,
                        $tokoId,
                        $userId,
                        new DateTimeImmutable('today'),
                        'closing_kasir',
                        'kasir_shift',
                        $shiftId,
                        'Posting selisih kas shift #' . $shiftId,
                        $lines
                    );
                }

                $pos_db->commit();
                $msg = 'Shift berhasil ditutup.';
                $shift = get_today_shift($pos_db, $tokoId, $userId);
            } catch (Throwable $e) {
                $pos_db->rollback();
                $err = 'Gagal tutup kasir: ' . $e->getMessage();
            }
        }
    }
}

$history = [];
$stHist = $pos_db->prepare("
    SELECT shift_id, tanggal_shift, jam_buka, jam_tutup, modal_awal, kas_sistem, kas_fisik, selisih, status
    FROM kasir_shift
    WHERE toko_id = ?
      AND kasir_id = ?
    ORDER BY shift_id DESC
    LIMIT 15
");
$stHist->bind_param('ii', $tokoId, $userId);
$stHist->execute();
$history = $stHist->get_result()->fetch_all(MYSQLI_ASSOC);
$stHist->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Shift Kasir</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8fafc; --border: #e2e8f0; --text: #0f172a; --muted: #64748b; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }
        .wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 12px; }
        .stat { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 12px; }
        .stat small { color: var(--muted); display: block; margin-bottom: 6px; font-size: 12px; }
        .stat strong { font-size: 20px; }
        .head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
        h1 { margin: 0; font-size: 24px; }
        .btn { text-decoration: none; border: 1px solid var(--border); background: #fff; color: var(--text); border-radius: 8px; padding: 10px 14px; font-size: 13px; font-weight: 600; }
        .btn-primary { border-color: #0f172a; background: #0f172a; color: #fff; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 18px; }
        textarea, input[type="number"] { width: 100%; border: 1px solid var(--border); border-radius: 8px; padding: 10px; font-size: 13px; margin-bottom: 8px; }
        textarea { min-height: 90px; resize: vertical; }
        .btn-submit { border: 1px solid #0f172a; background: #0f172a; color: #fff; border-radius: 8px; padding: 10px 14px; font-weight: 700; cursor: pointer; }
        .alert-ok, .alert-err, .warn { border-radius: 8px; padding: 10px; font-size: 13px; margin-bottom: 8px; }
        .alert-ok { border: 1px solid #86efac; background: #f0fdf4; color: #166534; }
        .alert-err { border: 1px solid #fca5a5; background: #fef2f2; color: #991b1b; }
        .warn { border: 1px solid #fde68a; background: #fffbeb; color: #92400e; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid var(--border); padding: 9px 8px; text-align: left; }
        th { font-size: 11px; text-transform: uppercase; color: var(--muted); }
        .muted { color: var(--muted); font-size: 14px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <h1>Shift Kasir</h1>
            <a href="index.php" class="btn">Kembali ke Menu POS</a>
        </div>

        <div class="grid">
            <div class="stat"><small>Transaksi Hari Ini</small><strong><?= number_format($summary['trx']) ?></strong></div>
            <div class="stat"><small>Omzet</small><strong><?= rupiah($summary['omzet']) ?></strong></div>
            <div class="stat"><small>Kas Transaksi</small><strong><?= rupiah($summary['cash']) ?></strong></div>
            <div class="stat"><small>Non Tunai</small><strong><?= rupiah($summary['non_tunai']) ?></strong></div>
            <div class="stat"><small>Piutang</small><strong><?= rupiah($summary['piutang']) ?></strong></div>
        </div>

        <div class="card">
            <p class="muted">Kasir: <?= htmlspecialchars($userNama) ?> | Shift status:
                <strong><?= htmlspecialchars((string)($shift['status'] ?? 'belum dibuka')) ?></strong>
            </p>
            <?php if ($needOpenShift && !$shift): ?>
                <div class="warn">Shift belum dibuka. Buka shift terlebih dahulu sebelum masuk ke kasir.</div>
            <?php endif; ?>
            <?php if ($msg): ?><div class="alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <?php if ($err): ?><div class="alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

            <?php if (!$shift): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="open_shift">
                    <label>Modal Awal Kas (Rp)</label>
                    <input type="number" name="modal_awal" min="0" step="0.01" value="0" required>
                    <button type="submit" class="btn-submit">Buka Shift</button>
                </form>
            <?php elseif (($shift['status'] ?? '') === 'open'): ?>
                <div style="margin-bottom:8px;">
                    <a href="kasir.php" class="btn btn-primary">Masuk ke Kasir</a>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="close_shift">
                    <label>Kas Fisik Saat Tutup (Rp)</label>
                    <input type="number" name="kas_fisik" min="0" step="0.01" value="<?= htmlspecialchars((string)((float)$summary['cash'] + (float)$shift['modal_awal'])) ?>" required>
                    <label>Catatan Tutup Shift</label>
                    <textarea name="catatan" placeholder="Catatan tutup shift (opsional)"></textarea>
                    <button type="submit" class="btn-submit">Tutup Shift</button>
                </form>
            <?php else: ?>
                <div class="warn">
                    Shift hari ini sudah ditutup pada <?= htmlspecialchars((string)$shift['jam_tutup']) ?>.
                    Modal awal: <strong><?= rupiah((float)$shift['modal_awal']) ?></strong>,
                    kas sistem: <strong><?= rupiah((float)$shift['kas_sistem']) ?></strong>,
                    kas fisik: <strong><?= rupiah((float)$shift['kas_fisik']) ?></strong>,
                    selisih: <strong><?= rupiah((float)$shift['selisih']) ?></strong>.
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top: 12px;">
            <h3 style="margin-top:0;">Riwayat Shift Saya</h3>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jam Buka</th>
                        <th>Jam Tutup</th>
                        <th>Modal Awal</th>
                        <th>Kas Sistem</th>
                        <th>Kas Fisik</th>
                        <th>Selisih</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="8" class="muted">Belum ada riwayat shift.</td></tr>
                    <?php else: foreach ($history as $h): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$h['tanggal_shift']) ?></td>
                            <td><?= htmlspecialchars((string)$h['jam_buka']) ?></td>
                            <td><?= htmlspecialchars((string)($h['jam_tutup'] ?? '-')) ?></td>
                            <td><?= rupiah((float)$h['modal_awal']) ?></td>
                            <td><?= rupiah((float)$h['kas_sistem']) ?></td>
                            <td><?= rupiah((float)$h['kas_fisik']) ?></td>
                            <td><?= rupiah((float)$h['selisih']) ?></td>
                            <td><?= htmlspecialchars((string)$h['status']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
