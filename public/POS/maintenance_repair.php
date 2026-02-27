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
$csrf = csrf_token();
$msg = '';
$err = '';

if ($tokoId <= 0) {
    http_response_code(400);
    exit('Sesi toko tidak valid.');
}

function scalar_count(Database $db, string $sql, string $types = '', ...$params): int {
    $st = $db->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int)array_values($row ?: ['c' => 0])[0];
}

function run_safe_repair(Database $db, int $tokoId): void {
    $db->begin_transaction();
    try {
        ensure_shift_legacy_backfill($db);
        // normalize pembayaran cashflow fields
        $db->query("
            UPDATE pembayaran
            SET uang_diterima = CASE WHEN uang_diterima <= 0 THEN jumlah ELSE uang_diterima END,
                kembalian = CASE WHEN metode = 'cash' THEN LEAST(kembalian, uang_diterima) ELSE 0 END
            WHERE uang_diterima = 0 OR kembalian < 0
        ");
        // safety relink: invalid shift references to NULL first, then backfill.
        $st = $db->prepare("
            UPDATE penjualan p
            LEFT JOIN kasir_shift s ON s.shift_id = p.shift_id
            SET p.shift_id = NULL
            WHERE p.toko_id = ?
              AND p.shift_id IS NOT NULL
              AND s.shift_id IS NULL
        ");
        $st->bind_param('i', $tokoId);
        $st->execute();
        $st->close();

        $db->query("
            UPDATE penjualan p
            SET p.shift_id = (
                SELECT s.shift_id
                FROM kasir_shift s
                WHERE s.toko_id = p.toko_id
                  AND s.kasir_id = p.kasir_id
                  AND s.tanggal_shift = DATE(p.dibuat_pada)
                ORDER BY s.shift_id DESC
                LIMIT 1
            )
            WHERE p.toko_id = {$tokoId}
              AND p.shift_id IS NULL
        ");

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect_redirect();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'run_safe_repair') {
        try {
            run_safe_repair($pos_db, $tokoId);
            $msg = 'Repair aman selesai dijalankan.';
        } catch (Throwable $e) {
            $err = 'Repair gagal: ' . $e->getMessage();
        }
    }
}

$diag = [];
$diag['penjualan_shift_null'] = scalar_count(
    $pos_db,
    "SELECT COUNT(*) FROM penjualan WHERE toko_id = ? AND shift_id IS NULL",
    'i',
    $tokoId
);
$diag['penjualan_shift_orphan'] = scalar_count(
    $pos_db,
    "SELECT COUNT(*) FROM penjualan p LEFT JOIN kasir_shift s ON s.shift_id = p.shift_id WHERE p.toko_id = ? AND p.shift_id IS NOT NULL AND s.shift_id IS NULL",
    'i',
    $tokoId
);
$diag['shift_template_null'] = scalar_count(
    $pos_db,
    "SELECT COUNT(*) FROM kasir_shift WHERE toko_id = ? AND shift_template_id IS NULL",
    'i',
    $tokoId
);
$diag['closed_shift_jam_tutup_null'] = scalar_count(
    $pos_db,
    "SELECT COUNT(*) FROM kasir_shift WHERE toko_id = ? AND status = 'closed' AND jam_tutup_real IS NULL",
    'i',
    $tokoId
);
$diag['cash_negative_kembalian'] = scalar_count(
    $pos_db,
    "SELECT COUNT(*) FROM pembayaran b INNER JOIN penjualan p ON p.penjualan_id = b.penjualan_id WHERE p.toko_id = ? AND b.kembalian < 0",
    'i',
    $tokoId
);

$riskScore = 0;
foreach ($diag as $v) {
    $riskScore += (int)$v;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Maintenance Repair</title>
    <style>
        :root { --bg:#f8fafc; --border:#e2e8f0; --text:#0f172a; --muted:#64748b; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:Inter,sans-serif; }
        .wrap { max-width:1080px; margin:0 auto; padding:24px 16px; }
        .head { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:14px; }
        .btn { text-decoration:none; border:1px solid var(--border); color:var(--text); background:#fff; border-radius:8px; padding:10px 14px; font-size:13px; }
        .btn-primary { border-color:#0f172a; background:#0f172a; color:#fff; cursor:pointer; font-weight:700; }
        .card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:16px; margin-bottom:12px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:10px; }
        .stat small { display:block; color:var(--muted); font-size:12px; margin-bottom:4px; }
        .stat strong { font-size:20px; }
        .muted { color:var(--muted); font-size:13px; }
        .ok { border:1px solid #86efac; background:#f0fdf4; color:#166534; border-radius:8px; padding:10px; margin-bottom:8px; }
        .err { border:1px solid #fca5a5; background:#fef2f2; color:#991b1b; border-radius:8px; padding:10px; margin-bottom:8px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { border-bottom:1px solid var(--border); padding:9px 8px; text-align:left; }
        th { color:var(--muted); font-size:11px; text-transform:uppercase; }
        .num { text-align:right; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <h1 style="margin:0;font-size:24px;">Maintenance Repair</h1>
            <div style="display:flex;gap:8px;">
                <a class="btn" href="index.php">POS Utama</a>
                <a class="btn" href="maintenance_backup.php">Backup</a>
            </div>
        </div>

        <?php if ($msg): ?><div class="ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <div class="card">
            <div class="grid">
                <div class="stat"><small>Toko ID</small><strong><?= (int)$tokoId ?></strong></div>
                <div class="stat"><small>Total Temuan</small><strong><?= number_format($riskScore) ?></strong></div>
                <div class="stat"><small>Status</small><strong><?= $riskScore > 0 ? 'Perlu Repair' : 'Sehat' ?></strong></div>
            </div>
            <p class="muted" style="margin:10px 0 0;">Repair aman bersifat idempotent: dapat dijalankan berulang tanpa merusak data yang sudah benar.</p>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Diagnosis</h3>
            <table>
                <thead>
                    <tr>
                        <th>Check</th>
                        <th class="num">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Penjualan tanpa shift_id</td><td class="num"><?= number_format($diag['penjualan_shift_null']) ?></td></tr>
                    <tr><td>Penjualan shift_id orphan (tidak ada shift)</td><td class="num"><?= number_format($diag['penjualan_shift_orphan']) ?></td></tr>
                    <tr><td>Kasir shift tanpa template</td><td class="num"><?= number_format($diag['shift_template_null']) ?></td></tr>
                    <tr><td>Shift closed tanpa jam_tutup_real</td><td class="num"><?= number_format($diag['closed_shift_jam_tutup_null']) ?></td></tr>
                    <tr><td>Pembayaran kembalian negatif</td><td class="num"><?= number_format($diag['cash_negative_kembalian']) ?></td></tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Aksi Repair</h3>
            <form method="post" onsubmit="return confirm('Jalankan repair aman sekarang?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="run_safe_repair">
                <button class="btn btn-primary" type="submit">Run Safe Repair</button>
            </form>
            <p class="muted" style="margin-top:10px;">Aksi ini akan: backfill shift legacy, relink penjualan ke shift, dan normalisasi field pembayaran.</p>
        </div>
    </div>
</body>
</html>
