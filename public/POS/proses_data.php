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

function table_has_column(Database $db, string $table, string $column): bool {
    $st = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $st->bind_param('ss', $table, $column);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_assoc();
    $st->close();
    return $ok;
}

function write_audit(Database $db, int $tokoId, int $userId, int $deviceId, string $processName, array $payload): void {
    $aksi = 'update';
    $tabel = 'proses_data';
    $recordId = 0;
    $json = json_encode([
        'proses' => $processName,
        'hasil' => $payload,
    ], JSON_UNESCAPED_UNICODE);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $st = $db->prepare("INSERT INTO audit_log (toko_id, pengguna_id, aksi, tabel, record_id, data_baru, device_id, ip_address) VALUES (?,?,?,?,?,?,?,?)");
    $st->bind_param('iissssis', $tokoId, $userId, $aksi, $tabel, $recordId, $json, $deviceId, $ip);
    $st->execute();
    $st->close();
}

$hasPoinAkhir = table_has_column($pos_db, 'pelanggan_toko', 'poin_akhir');
$hasPoinAwal = table_has_column($pos_db, 'pelanggan_toko', 'poin_awal');
$hasTanggalDaftar = table_has_column($pos_db, 'pelanggan_toko', 'tanggal_daftar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect_redirect();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'sync_piutang_status') {
            $sql = "
                UPDATE piutang pt
                INNER JOIN penjualan p ON p.penjualan_id = pt.penjualan_id
                SET pt.status = CASE WHEN pt.sisa <= 0 THEN 'lunas' ELSE 'belum' END
                WHERE p.toko_id = ?
            ";
            $st = $pos_db->prepare($sql);
            $st->bind_param('i', $tokoId);
            $st->execute();
            $affected = $st->affected_rows;
            $st->close();

            $msg = 'Sinkronisasi status piutang selesai. Baris terpengaruh: ' . number_format($affected);
            write_audit($pos_db, $tokoId, $userId, $deviceId, 'sync_piutang_status', ['affected_rows' => $affected]);
        } elseif ($action === 'rebuild_piutang_sisa') {
            $sql = "
                UPDATE piutang pt
                INNER JOIN penjualan p ON p.penjualan_id = pt.penjualan_id
                LEFT JOIN (
                    SELECT piutang_id, COALESCE(SUM(jumlah), 0) AS total_bayar
                    FROM piutang_pembayaran
                    GROUP BY piutang_id
                ) pp ON pp.piutang_id = pt.piutang_id
                SET
                    pt.sisa = GREATEST(pt.total - COALESCE(pp.total_bayar, 0), 0),
                    pt.status = CASE WHEN GREATEST(pt.total - COALESCE(pp.total_bayar, 0), 0) <= 0 THEN 'lunas' ELSE 'belum' END
                WHERE p.toko_id = ?
            ";
            $st = $pos_db->prepare($sql);
            $st->bind_param('i', $tokoId);
            $st->execute();
            $affected = $st->affected_rows;
            $st->close();

            $msg = 'Rebuild nilai sisa piutang selesai. Baris terpengaruh: ' . number_format($affected);
            write_audit($pos_db, $tokoId, $userId, $deviceId, 'rebuild_piutang_sisa', ['affected_rows' => $affected]);
        } elseif ($action === 'sync_poin_member') {
            if ($hasPoinAkhir) {
                $sql = "
                    UPDATE pelanggan_toko pt
                    LEFT JOIN (
                        SELECT pelanggan_id, COALESCE(SUM(poin), 0) AS total_poin
                        FROM poin_member
                        WHERE toko_id = ?
                        GROUP BY pelanggan_id
                    ) pm ON pm.pelanggan_id = pt.pelanggan_id
                    SET pt.poin = COALESCE(pm.total_poin, 0),
                        pt.poin_akhir = COALESCE(pm.total_poin, 0)
                    WHERE pt.toko_id = ?
                      AND pt.deleted_at IS NULL
                ";
                $st = $pos_db->prepare($sql);
                $st->bind_param('ii', $tokoId, $tokoId);
                $st->execute();
                $affected = $st->affected_rows;
                $st->close();
            } else {
                $sql = "
                    UPDATE pelanggan_toko pt
                    LEFT JOIN (
                        SELECT pelanggan_id, COALESCE(SUM(poin), 0) AS total_poin
                        FROM poin_member
                        WHERE toko_id = ?
                        GROUP BY pelanggan_id
                    ) pm ON pm.pelanggan_id = pt.pelanggan_id
                    SET pt.poin = COALESCE(pm.total_poin, 0)
                    WHERE pt.toko_id = ?
                      AND pt.deleted_at IS NULL
                ";
                $st = $pos_db->prepare($sql);
                $st->bind_param('ii', $tokoId, $tokoId);
                $st->execute();
                $affected = $st->affected_rows;
                $st->close();
            }

            $msg = 'Sinkronisasi poin member selesai. Baris terpengaruh: ' . number_format($affected);
            write_audit($pos_db, $tokoId, $userId, $deviceId, 'sync_poin_member', ['affected_rows' => $affected]);
        } else {
            $err = 'Aksi tidak dikenali.';
        }
    } catch (Throwable $e) {
        $err = 'Proses gagal: ' . $e->getMessage();
    }
}

$counts = [
    'piutang' => 0,
    'pelanggan_toko' => 0,
    'poin_member' => 0,
];
$st = $pos_db->prepare("SELECT COUNT(*) c FROM piutang pt INNER JOIN penjualan p ON p.penjualan_id = pt.penjualan_id WHERE p.toko_id = ?");
$st->bind_param('i', $tokoId);
$st->execute();
$counts['piutang'] = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

$st = $pos_db->prepare("SELECT COUNT(*) c FROM pelanggan_toko WHERE toko_id = ? AND deleted_at IS NULL");
$st->bind_param('i', $tokoId);
$st->execute();
$counts['pelanggan_toko'] = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

$st = $pos_db->prepare("SELECT COUNT(*) c FROM poin_member WHERE toko_id = ?");
$st->bind_param('i', $tokoId);
$st->execute();
$counts['poin_member'] = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

$schemaWarnings = [];
if (!$hasPoinAkhir) $schemaWarnings[] = "Kolom pelanggan_toko.poin_akhir belum ada.";
if (!$hasPoinAwal) $schemaWarnings[] = "Kolom pelanggan_toko.poin_awal belum ada.";
if (!$hasTanggalDaftar) $schemaWarnings[] = "Kolom pelanggan_toko.tanggal_daftar belum ada.";
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Proses Data</title>
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
        .head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
        h1 { margin: 0; font-size: 24px; }
        .btn { text-decoration: none; border: 1px solid var(--border); background: #fff; color: var(--text); border-radius: 8px; padding: 10px 14px; font-size: 13px; font-weight: 600; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 18px; margin-bottom: 12px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; }
        .stat { border: 1px solid var(--border); border-radius: 10px; padding: 10px; background: #fff; }
        .stat small { display: block; color: var(--muted); margin-bottom: 6px; font-size: 12px; }
        .stat strong { font-size: 20px; }
        .action {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 8px;
        }
        .action p { margin: 0; color: var(--muted); font-size: 13px; }
        .action h3 { margin: 0 0 5px; font-size: 16px; }
        .run {
            border: 1px solid #0f172a;
            background: #0f172a;
            color: #fff;
            border-radius: 8px;
            padding: 10px 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .alert-ok, .alert-err, .alert-warn {
            border-radius: 8px;
            padding: 10px;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .alert-ok { border: 1px solid #86efac; background: #f0fdf4; color: #166534; }
        .alert-err { border: 1px solid #fca5a5; background: #fef2f2; color: #991b1b; }
        .alert-warn { border: 1px solid #fde68a; background: #fffbeb; color: #92400e; }
        ul { margin: 0; padding-left: 16px; }
        li { margin: 4px 0; font-size: 13px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <h1>Proses Data</h1>
            <a href="index.php" class="btn">Kembali ke Menu POS</a>
        </div>

        <div class="card">
            <p style="margin-top:0;color:#64748b;">Halo, <?= htmlspecialchars($userNama) ?>. Utility ini menjalankan perbaikan data langsung ke database toko aktif.</p>
            <?php if ($msg): ?><div class="alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
            <?php if ($err): ?><div class="alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <?php if ($schemaWarnings): ?>
                <div class="alert-warn">
                    <strong>Catatan skema:</strong>
                    <ul>
                        <?php foreach ($schemaWarnings as $w): ?><li><?= htmlspecialchars($w) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid">
            <div class="stat"><small>Data Piutang</small><strong><?= number_format($counts['piutang']) ?></strong></div>
            <div class="stat"><small>Pelanggan Toko</small><strong><?= number_format($counts['pelanggan_toko']) ?></strong></div>
            <div class="stat"><small>Riwayat Poin Member</small><strong><?= number_format($counts['poin_member']) ?></strong></div>
        </div>

        <div class="card">
            <div class="action">
                <div>
                    <h3>Sinkronisasi Status Piutang</h3>
                    <p>Set kolom `piutang.status` otomatis berdasarkan nilai `sisa` saat ini.</p>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="sync_piutang_status">
                    <button type="submit" class="run">Jalankan</button>
                </form>
            </div>

            <div class="action">
                <div>
                    <h3>Rebuild Nilai Sisa Piutang</h3>
                    <p>Hitung ulang `piutang.sisa` dari total minus akumulasi `piutang_pembayaran`.</p>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="rebuild_piutang_sisa">
                    <button type="submit" class="run">Jalankan</button>
                </form>
            </div>

            <div class="action">
                <div>
                    <h3>Sinkronisasi Poin Pelanggan</h3>
                    <p>Update `pelanggan_toko.poin` (dan `poin_akhir` jika ada) dari akumulasi tabel `poin_member`.</p>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="sync_poin_member">
                    <button type="submit" class="run">Jalankan</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
