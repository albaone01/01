<?php
session_start();
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';
require_once '../../inc/csrf.php';

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$userId = (int)($_SESSION['pengguna_id'] ?? 0);
$deviceId = (int)($_SESSION['device_id'] ?? 0);
$userNama = (string)($_SESSION['pengguna_nama'] ?? 'User');
$csrf = csrf_token();
$msg = '';
$err = '';

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

function get_today_closing(Database $db, int $tokoId, int $kasirId): ?array {
    $st = $db->prepare("
        SELECT audit_id, dibuat_pada, data_baru
        FROM audit_log
        WHERE toko_id = ?
          AND tabel = 'kasir_tutup'
          AND record_id = ?
          AND DATE(dibuat_pada) = CURRENT_DATE()
        ORDER BY audit_id DESC
        LIMIT 1
    ");
    $st->bind_param('ii', $tokoId, $kasirId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect_redirect();
    $action = $_POST['action'] ?? '';
    if ($action === 'close_shift') {
        $closed = get_today_closing($pos_db, $tokoId, $userId);
        if ($closed) {
            $err = 'Shift hari ini sudah pernah ditutup.';
        } else {
            $summary = get_shift_summary($pos_db, $tokoId, $userId);
            $catatan = trim((string)($_POST['catatan'] ?? ''));
            $data = [
                'shift_tanggal' => date('Y-m-d'),
                'kasir_id' => $userId,
                'kasir_nama' => $userNama,
                'ringkasan' => $summary,
                'catatan' => $catatan,
            ];
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $aksi = 'insert';
            $tabel = 'kasir_tutup';

            $st = $pos_db->prepare("INSERT INTO audit_log (toko_id, pengguna_id, aksi, tabel, record_id, data_baru, device_id, ip_address) VALUES (?,?,?,?,?,?,?,?)");
            $st->bind_param('iissssis', $tokoId, $userId, $aksi, $tabel, $userId, $jsonData, $deviceId, $ip);
            $st->execute();
            $st->close();
            $msg = 'Tutup kasir berhasil disimpan.';
        }
    }
}

$shift = get_shift_summary($pos_db, $tokoId, $userId);
$todayClosed = get_today_closing($pos_db, $tokoId, $userId);

$history = [];
$stHist = $pos_db->prepare("
    SELECT audit_id, dibuat_pada, data_baru
    FROM audit_log
    WHERE toko_id = ?
      AND tabel = 'kasir_tutup'
      AND record_id = ?
    ORDER BY audit_id DESC
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
    <title>Tutup Kasir</title>
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
        .wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 12px; }
        .stat { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 12px; }
        .stat small { color: var(--muted); display: block; margin-bottom: 6px; font-size: 12px; }
        .stat strong { font-size: 20px; }
        .head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
        h1 { margin: 0; font-size: 24px; }
        .btn { text-decoration: none; border: 1px solid var(--border); background: #fff; color: var(--text); border-radius: 8px; padding: 10px 14px; font-size: 13px; font-weight: 600; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 18px; }
        textarea { width: 100%; border: 1px solid var(--border); border-radius: 8px; min-height: 90px; padding: 10px; font-size: 13px; margin-bottom: 8px; resize: vertical; }
        .btn-submit { border: 1px solid #0f172a; background: #0f172a; color: #fff; border-radius: 8px; padding: 10px 14px; font-weight: 700; cursor: pointer; }
        .alert-ok, .alert-err { border-radius: 8px; padding: 10px; font-size: 13px; margin-bottom: 8px; }
        .alert-ok { border: 1px solid #86efac; background: #f0fdf4; color: #166534; }
        .alert-err { border: 1px solid #fca5a5; background: #fef2f2; color: #991b1b; }
        .warn { border: 1px solid #fde68a; background: #fffbeb; color: #92400e; border-radius: 8px; padding: 10px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid var(--border); padding: 9px 8px; text-align: left; }
        th { font-size: 11px; text-transform: uppercase; color: var(--muted); }
        .muted { color: var(--muted); font-size: 14px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <h1>Tutup Kasir</h1>
            <a href="index.php" class="btn">Kembali ke Menu POS</a>
        </div>

        <div class="grid">
            <div class="stat"><small>Transaksi Hari Ini</small><strong><?= number_format($shift['trx']) ?></strong></div>
            <div class="stat"><small>Omzet</small><strong><?= rupiah($shift['omzet']) ?></strong></div>
            <div class="stat"><small>Tunai</small><strong><?= rupiah($shift['cash']) ?></strong></div>
            <div class="stat"><small>Non Tunai</small><strong><?= rupiah($shift['non_tunai']) ?></strong></div>
            <div class="stat"><small>Piutang</small><strong><?= rupiah($shift['piutang']) ?></strong></div>
        </div>

        <div class="card">
            <p class="muted">Halo, <?= htmlspecialchars($userNama) ?>. Proses ini menyimpan snapshot tutup kasir ke tabel audit_log.</p>
            <?php if ($msg): ?><div class="alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <?php if ($err): ?><div class="alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

            <?php if ($todayClosed): ?>
                <div class="warn">
                    Shift tanggal <?= htmlspecialchars(date('d-m-Y', strtotime($todayClosed['dibuat_pada']))) ?> sudah ditutup pada <?= htmlspecialchars(date('H:i:s', strtotime($todayClosed['dibuat_pada']))) ?>.
                </div>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="close_shift">
                    <textarea name="catatan" placeholder="Catatan tutup kasir (opsional)"></textarea>
                    <button type="submit" class="btn-submit">Simpan Tutup Kasir Hari Ini</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top: 12px;">
            <h3 style="margin-top:0;">Riwayat Tutup Kasir Saya</h3>
            <table>
                <thead>
                    <tr>
                        <th>Waktu Simpan</th>
                        <th>Transaksi</th>
                        <th>Omzet</th>
                        <th>Tunai</th>
                        <th>Non Tunai</th>
                        <th>Piutang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="6" class="muted">Belum ada riwayat tutup kasir.</td></tr>
                    <?php else: foreach ($history as $h):
                        $data = json_decode((string)$h['data_baru'], true);
                        $r = $data['ringkasan'] ?? [];
                    ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($h['dibuat_pada']))) ?></td>
                            <td><?= number_format((int)($r['trx'] ?? 0)) ?></td>
                            <td><?= rupiah((float)($r['omzet'] ?? 0)) ?></td>
                            <td><?= rupiah((float)($r['cash'] ?? 0)) ?></td>
                            <td><?= rupiah((float)($r['non_tunai'] ?? 0)) ?></td>
                            <td><?= rupiah((float)($r['piutang'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
