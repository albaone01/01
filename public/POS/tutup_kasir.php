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

function get_shift_summary(Database $db, int $shiftId): array {
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
            WHERE p.shift_id = ?
            GROUP BY p.penjualan_id, p.total_akhir
        ) x
    ";
    $st = $db->prepare($sql);
    $st->bind_param('i', $shiftId);
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

function get_shift_cash_movement_totals(Database $db, int $shiftId): array {
    $st = $db->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN tipe = 'in' THEN jumlah ELSE 0 END),0) AS total_in,
            COALESCE(SUM(CASE WHEN tipe = 'out' THEN jumlah ELSE 0 END),0) AS total_out
        FROM cash_movement
        WHERE shift_id = ?
    ");
    $st->bind_param('i', $shiftId);
    $st->execute();
    $r = $st->get_result()->fetch_assoc() ?: [];
    $st->close();
    return [
        'in' => (float)($r['total_in'] ?? 0),
        'out' => (float)($r['total_out'] ?? 0),
    ];
}

$shiftTemplates = get_available_shift_templates($pos_db, $tokoId, $userId);

$shift = get_open_shift($pos_db, $tokoId, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect_redirect();
    $action = $_POST['action'] ?? '';

    if ($action === 'open_shift') {
        if ($shift) {
            $msg = 'Sudah ada shift open. Tutup shift aktif terlebih dahulu.';
        } else {
            $templateId = (int)($_POST['shift_template_id'] ?? 0);
            $modalAwal = max(0, (float)($_POST['modal_awal'] ?? 0));

            $allowedTemplateIds = array_map(static function (array $tpl): int {
                return (int)($tpl['template_id'] ?? 0);
            }, get_available_shift_templates($pos_db, $tokoId, $userId));
            if (!in_array($templateId, $allowedTemplateIds, true)) {
                $err = 'Template shift tidak valid.';
            } else {
                $status = 'open';
                $st = $pos_db->prepare("
                    INSERT INTO kasir_shift (toko_id, kasir_id, device_id, shift_template_id, tanggal_shift, jam_buka_real, modal_awal, kas_sistem, kas_fisik, selisih, status)
                    VALUES (?, ?, ?, ?, CURRENT_DATE(), NOW(), ?, 0, 0, 0, ?)
                ");
                $st->bind_param('iiiids', $tokoId, $userId, $deviceId, $templateId, $modalAwal, $status);
                $st->execute();
                $st->close();
                $msg = 'Shift berhasil dibuka.';
                $shift = get_open_shift($pos_db, $tokoId, $userId);
            }
        }
    }

    if ($action === 'close_shift') {
        if (!$shift || ($shift['status'] ?? '') !== 'open') {
            $err = 'Tidak ada shift open yang bisa ditutup.';
        } else {
            try {
                $shiftId = (int)$shift['shift_id'];
                $summary = get_shift_summary($pos_db, $shiftId);
                $cashMovements = get_shift_cash_movement_totals($pos_db, $shiftId);
                $kasSistem = (float)$summary['cash'] + (float)$shift['modal_awal'] + (float)$cashMovements['in'] - (float)$cashMovements['out'];
                $kasFisik = max(0, (float)($_POST['kas_fisik'] ?? 0));
                $catatan = trim((string)($_POST['catatan'] ?? ''));
                $selisih = $kasFisik - $kasSistem;

                $coaMap = get_coa_map($pos_db, $tokoId);

                $pos_db->begin_transaction();

                $status = 'closed';
                $st = $pos_db->prepare("
                    UPDATE kasir_shift
                    SET jam_tutup_real = NOW(),
                        jam_tutup = NOW(),
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
                $shift = get_open_shift($pos_db, $tokoId, $userId);
            } catch (Throwable $e) {
                $pos_db->rollback();
                $err = 'Gagal tutup shift: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'reopen_last_closed') {
        if ($shift) {
            $err = 'Masih ada shift open. Tutup dulu shift aktif.';
        } else {
            $st = $pos_db->prepare("
                SELECT shift_id
                FROM kasir_shift
                WHERE toko_id = ?
                  AND kasir_id = ?
                  AND status = 'closed'
                ORDER BY shift_id DESC
                LIMIT 1
            ");
            $st->bind_param('ii', $tokoId, $userId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$row) {
                $err = 'Tidak ada shift closed untuk dibuka ulang.';
            } else {
                $sid = (int)$row['shift_id'];
                $pos_db->begin_transaction();
                try {
                    $st = $pos_db->prepare("
                        DELETE FROM jurnal_umum
                        WHERE toko_id = ?
                          AND sumber = 'closing_kasir'
                          AND referensi_tabel = 'kasir_shift'
                          AND referensi_id = ?
                    ");
                    $st->bind_param('ii', $tokoId, $sid);
                    $st->execute();
                    $st->close();

                    $status = 'open';
                    $st = $pos_db->prepare("
                        UPDATE kasir_shift
                        SET jam_tutup_real = NULL,
                            jam_tutup = NULL,
                            kas_sistem = 0,
                            kas_fisik = 0,
                            selisih = 0,
                            status = ?,
                            catatan = CONCAT(IFNULL(catatan,''), ' [Reopen ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'), ']')
                        WHERE shift_id = ?
                    ");
                    $st->bind_param('si', $status, $sid);
                    $st->execute();
                    $st->close();
                    $pos_db->commit();
                    $msg = 'Shift terakhir berhasil dibuka ulang.';
                    $shift = get_open_shift($pos_db, $tokoId, $userId);
                } catch (Throwable $e) {
                    $pos_db->rollback();
                    $err = 'Gagal reopen shift: ' . $e->getMessage();
                }
            }
        }
    }
}

$activeSummary = ['trx' => 0, 'omzet' => 0, 'cash' => 0, 'non_tunai' => 0, 'piutang' => 0];
$cashInView = 0.0;
$cashOutView = 0.0;
$modalAwalView = (float)($shift['modal_awal'] ?? 0);
if ($shift) {
    $activeSummary = get_shift_summary($pos_db, (int)$shift['shift_id']);
    $movementTotals = get_shift_cash_movement_totals($pos_db, (int)$shift['shift_id']);
    $cashInView = (float)$movementTotals['in'];
    $cashOutView = (float)$movementTotals['out'];
}
$kasSistemRumusView = $modalAwalView + (float)$activeSummary['cash'] + $cashInView - $cashOutView;
$defaultKasFisikInput = number_format($kasSistemRumusView, 2, '.', '');

$history = [];
$stHist = $pos_db->prepare("
    SELECT s.shift_id, s.tanggal_shift, s.jam_buka_real, s.jam_tutup_real, s.modal_awal, s.kas_sistem, s.kas_fisik, s.selisih, s.status,
           COALESCE(tpl.nama_shift, '-') AS nama_shift
    FROM kasir_shift s
    LEFT JOIN shift_template tpl ON tpl.template_id = s.shift_template_id
    WHERE s.toko_id = ?
      AND s.kasir_id = ?
    ORDER BY s.shift_id DESC
    LIMIT 20
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
        .wrap { max-width: 1080px; margin: 0 auto; padding: 24px 16px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 12px; }
        .stat { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 12px; }
        .stat small { color: var(--muted); display: block; margin-bottom: 6px; font-size: 12px; }
        .stat strong { font-size: 20px; }
        .head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
        h1 { margin: 0; font-size: 24px; }
        .btn { text-decoration: none; border: 1px solid var(--border); background: #fff; color: var(--text); border-radius: 8px; padding: 10px 14px; font-size: 13px; font-weight: 600; }
        .btn-primary { border-color: #0f172a; background: #0f172a; color: #fff; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 18px; margin-bottom: 12px; }
        textarea, input[type="number"], input[type="text"], select { width: 100%; border: 1px solid var(--border); border-radius: 8px; padding: 10px; font-size: 13px; margin-bottom: 8px; background: #fff; }
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

        <?php if ($needOpenShift && !$shift): ?>
            <div class="warn">Belum ada shift open. Buka shift dulu sesuai template (Pagi/Siang/Malam) sebelum transaksi.</div>
        <?php endif; ?>
        <?php if ($msg): ?><div class="alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <div class="grid">
            <div class="stat"><small>Shift Aktif</small><strong><?= $shift ? ('#' . (int)$shift['shift_id']) : '-' ?></strong></div>
            <div class="stat"><small>Transaksi Shift</small><strong><?= number_format($activeSummary['trx']) ?></strong></div>
            <div class="stat"><small>Omzet Shift</small><strong><?= rupiah($activeSummary['omzet']) ?></strong></div>
            <div class="stat"><small>Kas Net Shift</small><strong><?= rupiah($activeSummary['cash']) ?></strong></div>
            <div class="stat"><small>Non Tunai Shift</small><strong><?= rupiah($activeSummary['non_tunai']) ?></strong></div>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Rumus Kas Sistem Shift Aktif</h3>
            <p class="muted" style="margin-bottom:6px;">Kas Sistem = Modal Awal + Kas Net Transaksi + Cash In - Cash Out</p>
            <p style="font-size:14px;line-height:1.8;margin:0;">
                Modal Awal: <strong><?= rupiah($modalAwalView) ?></strong><br>
                Kas Net Transaksi: <strong><?= rupiah((float)$activeSummary['cash']) ?></strong><br>
                Cash In: <strong><?= rupiah($cashInView) ?></strong><br>
                Cash Out: <strong><?= rupiah($cashOutView) ?></strong><br>
                Kas Sistem (Rumus): <strong><?= rupiah($kasSistemRumusView) ?></strong>
            </p>
        </div>

        <div class="card">
            <p class="muted">Kasir: <?= htmlspecialchars($userNama) ?> | Status shift:
                <strong><?= $shift ? htmlspecialchars((string)$shift['status']) : 'tidak ada shift open' ?></strong>
            </p>

            <?php if (!$shift): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="open_shift">
                    <label>Template Shift</label>
                    <select name="shift_template_id" required>
                        <option value="">Pilih shift</option>
                        <?php foreach ($shiftTemplates as $tpl): ?>
                            <option value="<?= (int)$tpl['template_id'] ?>">
                                <?= htmlspecialchars($tpl['nama_shift']) ?> (<?= htmlspecialchars(substr((string)$tpl['jam_mulai'],0,5)) ?> - <?= htmlspecialchars(substr((string)$tpl['jam_selesai'],0,5)) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label>Modal Awal Kas (Rp)</label>
                    <input type="number" name="modal_awal" min="0" step="0.01" value="0" required>
                    <button type="submit" class="btn-submit">Buka Shift</button>
                </form>
                <form method="post" onsubmit="return confirm('Buka ulang shift closed terakhir?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="reopen_last_closed">
                    <button type="submit" class="btn">Buka Ulang Shift Terakhir</button>
                </form>
            <?php else: ?>
                <div style="margin-bottom:8px;">
                    <a href="kasir.php" class="btn btn-primary">Masuk ke Kasir</a>
                    <a href="kas.php" class="btn">Cash Movement</a>
                </div>
                <p class="muted" style="margin-bottom:10px;">Input kas masuk/keluar dipindahkan ke menu <strong>Kas</strong> agar alur kas terpusat.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="close_shift">
                    <label>Kas Sistem Saat Ini (Rp)</label>
                    <input type="text" value="<?= htmlspecialchars(rupiah($kasSistemRumusView)) ?>" readonly>
                    <label>Kas Fisik Saat Tutup (Rp)</label>
                    <input type="number" name="kas_fisik" min="0" step="0.01" value="<?= htmlspecialchars($defaultKasFisikInput) ?>" required>
                    <p class="muted" style="margin-top:-4px;margin-bottom:8px;">Status tetap <strong>open</strong> sampai tombol <strong>Tutup Shift</strong> diklik.</p>
                    <label>Catatan Tutup Shift</label>
                    <textarea name="catatan" placeholder="Catatan tutup shift (opsional)"></textarea>
                    <button type="submit" class="btn-submit">Tutup Shift</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Riwayat Shift Saya</h3>
            <table>
                <thead>
                    <tr>
                        <th>Shift</th>
                        <th>Tanggal</th>
                        <th>Jam Buka Real</th>
                        <th>Jam Tutup Real</th>
                        <th>Modal Awal</th>
                        <th>Kas Sistem</th>
                        <th>Kas Fisik</th>
                        <th>Selisih</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="9" class="muted">Belum ada riwayat shift.</td></tr>
                    <?php else: foreach ($history as $h): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$h['nama_shift']) ?></td>
                            <td><?= htmlspecialchars((string)$h['tanggal_shift']) ?></td>
                            <td><?= htmlspecialchars((string)($h['jam_buka_real'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($h['jam_tutup_real'] ?? '-')) ?></td>
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
