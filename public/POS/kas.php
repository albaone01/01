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
$msg = '';
$err = '';
$csrf = csrf_token();

if ($tokoId <= 0 || $userId <= 0) {
    http_response_code(400);
    exit('Sesi tidak valid.');
}

function rupiah(float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function valid_date(string $v): bool {
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}

function get_shift_summary(Database $db, int $shiftId): array {
    $sql = "
        SELECT
            COUNT(x.penjualan_id) AS trx,
            COALESCE(SUM(x.total_akhir), 0) AS omzet,
            COALESCE(SUM(x.bayar_cash), 0) AS cash
        FROM (
            SELECT
                p.penjualan_id,
                p.total_akhir,
                COALESCE(SUM(CASE WHEN b.metode = 'cash' THEN b.jumlah ELSE 0 END), 0) AS bayar_cash
            FROM penjualan p
            LEFT JOIN pembayaran b ON b.penjualan_id = p.penjualan_id
            WHERE p.shift_id = ?
            GROUP BY p.penjualan_id, p.total_akhir
        ) x
    ";
    $st = $db->prepare($sql);
    $st->bind_param('i', $shiftId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: [];
    $st->close();
    return [
        'trx' => (int)($row['trx'] ?? 0),
        'omzet' => (float)($row['omzet'] ?? 0),
        'cash' => (float)($row['cash'] ?? 0),
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

$shift = get_open_shift($pos_db, $tokoId, $userId);
if (!$shift) {
    http_response_code(200);
}

$today = date('Y-m-d');
$from = (string)($_GET['from'] ?? $today);
$to = (string)($_GET['to'] ?? $today);
if (!valid_date($from)) $from = $today;
if (!valid_date($to)) $to = $today;
if ($from > $to) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}

$kategoriStandar = [
    'Setor Bank',
    'Beli ATK',
    'Biaya Operasional',
    'Uang Kecil',
    'Kasbon Karyawan',
    'Refund Pelanggan',
    'Penyesuaian Kas',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect_redirect();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'cash_movement') {
        if (!$shift || ($shift['status'] ?? '') !== 'open') {
            $err = 'Tidak ada shift open untuk mencatat kas masuk/keluar.';
        } else {
            $tipe = strtolower(trim((string)($_POST['tipe'] ?? '')));
            $kategoriSelect = trim((string)($_POST['kategori_select'] ?? ''));
            $kategoriCustom = trim((string)($_POST['kategori_custom'] ?? ''));
            $kategori = $kategoriSelect === '__custom__' ? $kategoriCustom : $kategoriSelect;
            $jumlah = max(0, (float)($_POST['jumlah'] ?? 0));
            $catatan = trim((string)($_POST['catatan'] ?? ''));

            if (!in_array($tipe, ['in', 'out'], true)) {
                $err = 'Tipe cash movement harus in/out.';
            } elseif ($kategori === '') {
                $err = 'Kategori wajib diisi.';
            } elseif ($jumlah <= 0) {
                $err = 'Jumlah harus lebih dari 0.';
            } else {
                $shiftId = (int)$shift['shift_id'];
                $st = $pos_db->prepare("
                    INSERT INTO cash_movement (toko_id, shift_id, kasir_id, tipe, kategori, jumlah, catatan)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $st->bind_param('iiissds', $tokoId, $shiftId, $userId, $tipe, $kategori, $jumlah, $catatan);
                $st->execute();
                $st->close();
                $msg = 'Cash movement tersimpan.';
            }
        }
    }

    $shift = get_open_shift($pos_db, $tokoId, $userId);
}

$summary = ['trx' => 0, 'omzet' => 0, 'cash' => 0];
$cashIn = 0.0;
$cashOut = 0.0;
$kasSistem = 0.0;
$rowsShiftAktif = [];
$rowsHistory = [];

if ($shift) {
    $shiftId = (int)$shift['shift_id'];
    $summary = get_shift_summary($pos_db, $shiftId);
    $totals = get_shift_cash_movement_totals($pos_db, $shiftId);
    $cashIn = (float)$totals['in'];
    $cashOut = (float)$totals['out'];
    $modalAwal = (float)($shift['modal_awal'] ?? 0);
    $kasSistem = $modalAwal + (float)$summary['cash'] + $cashIn - $cashOut;

    $st = $pos_db->prepare("
        SELECT movement_id, tipe, kategori, jumlah, catatan, dibuat_pada
        FROM cash_movement
        WHERE shift_id = ?
        ORDER BY movement_id DESC
        LIMIT 20
    ");
    $st->bind_param('i', $shiftId);
    $st->execute();
    $rowsShiftAktif = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}

$stHist = $pos_db->prepare("
    SELECT
        m.movement_id,
        m.tipe,
        m.kategori,
        m.jumlah,
        m.catatan,
        m.dibuat_pada,
        s.shift_id,
        s.tanggal_shift,
        COALESCE(tpl.nama_shift, '-') AS nama_shift,
        u.nama AS nama_kasir
    FROM cash_movement m
    INNER JOIN kasir_shift s ON s.shift_id = m.shift_id
    LEFT JOIN shift_template tpl ON tpl.template_id = s.shift_template_id
    INNER JOIN pengguna u ON u.pengguna_id = m.kasir_id
    WHERE m.toko_id = ?
      AND DATE(m.dibuat_pada) BETWEEN ? AND ?
    ORDER BY m.movement_id DESC
    LIMIT 300
");
$stHist->bind_param('iss', $tokoId, $from, $to);
$stHist->execute();
$rowsHistory = $stHist->get_result()->fetch_all(MYSQLI_ASSOC);
$stHist->close();

$historyTotalIn = 0.0;
$historyTotalOut = 0.0;
foreach ($rowsHistory as $hr) {
    $v = (float)($hr['jumlah'] ?? 0);
    if (($hr['tipe'] ?? '') === 'in') {
        $historyTotalIn += $v;
    } else {
        $historyTotalOut += $v;
    }
}
$historyNet = $historyTotalIn - $historyTotalOut;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cash Movement</title>
    <style>
        :root { --bg:#f8fafc; --border:#e2e8f0; --text:#0f172a; --muted:#64748b; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Inter, sans-serif; background:var(--bg); color:var(--text); }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 24px 16px; }
        .head { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:16px; }
        .card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:16px; margin-bottom:12px; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(170px,1fr)); gap:10px; }
        .stat small { color:var(--muted); display:block; margin-bottom:5px; font-size:12px; }
        .stat strong { font-size:20px; }
        .btn { text-decoration:none; border:1px solid var(--border); border-radius:8px; background:#fff; color:var(--text); padding:10px 14px; font-size:13px; }
        .btn-submit { border:1px solid #0f172a; border-radius:8px; background:#0f172a; color:#fff; padding:10px 14px; font-weight:700; cursor:pointer; }
        input, select, textarea { width:100%; border:1px solid var(--border); border-radius:8px; padding:10px; font-size:13px; margin-bottom:8px; }
        textarea { min-height:84px; resize:vertical; }
        .ok { border:1px solid #86efac; background:#f0fdf4; color:#166534; border-radius:8px; padding:10px; margin-bottom:8px; }
        .err { border:1px solid #fca5a5; background:#fef2f2; color:#991b1b; border-radius:8px; padding:10px; margin-bottom:8px; }
        .warn { border:1px solid #fde68a; background:#fffbeb; color:#92400e; border-radius:8px; padding:10px; margin-bottom:8px; }
        table { width:100%; border-collapse: collapse; font-size:13px; }
        th, td { border-bottom:1px solid var(--border); padding:9px 8px; text-align:left; }
        th { font-size:11px; text-transform:uppercase; color:var(--muted); }
        .num { text-align:right; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <h1 style="margin:0;font-size:24px;">Cash Movement</h1>
            <div style="display:flex;gap:8px;">
                <a class="btn" href="index.php">POS Utama</a>
                <a class="btn" href="tutup_kasir.php">Shift Kasir</a>
            </div>
        </div>

        <?php if ($msg): ?><div class="ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <?php if (!$shift): ?>
            <div class="warn">Belum ada shift open. Buka shift dulu di menu Shift Kasir untuk input cash movement baru.</div>
        <?php else: ?>
            <div class="card">
                <p style="margin:0 0 10px;color:var(--muted);">Kasir: <strong><?= htmlspecialchars($userNama) ?></strong> | Shift: <strong>#<?= (int)$shift['shift_id'] ?></strong> | Tanggal: <strong><?= htmlspecialchars((string)$shift['tanggal_shift']) ?></strong></p>
                <div class="grid">
                    <div class="stat"><small>Modal Awal</small><strong><?= rupiah((float)$shift['modal_awal']) ?></strong></div>
                    <div class="stat"><small>Cash Net Transaksi</small><strong><?= rupiah((float)$summary['cash']) ?></strong></div>
                    <div class="stat"><small>Cash In</small><strong><?= rupiah($cashIn) ?></strong></div>
                    <div class="stat"><small>Cash Out</small><strong><?= rupiah($cashOut) ?></strong></div>
                    <div class="stat"><small>Kas Sistem</small><strong><?= rupiah($kasSistem) ?></strong></div>
                </div>
                <p style="margin:10px 0 0;color:var(--muted);font-size:13px;">Rumus: Modal Awal + Cash Net Transaksi + Cash In - Cash Out</p>
            </div>

            <div class="card">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="cash_movement">
                    <label>Tipe</label>
                    <select name="tipe" required>
                        <option value="out">Cash Out</option>
                        <option value="in">Cash In</option>
                    </select>
                    <label>Kategori</label>
                    <select name="kategori_select" id="kategoriSelect" required onchange="toggleKategoriCustom()">
                        <option value="">Pilih kategori</option>
                        <?php foreach ($kategoriStandar as $kat): ?>
                            <option value="<?= htmlspecialchars($kat) ?>"><?= htmlspecialchars($kat) ?></option>
                        <?php endforeach; ?>
                        <option value="__custom__">Lainnya (isi manual)</option>
                    </select>
                    <input type="text" name="kategori_custom" id="kategoriCustom" placeholder="Isi kategori manual" style="display:none;">
                    <label>Jumlah (Rp)</label>
                    <input type="number" name="jumlah" min="0.01" step="0.01" required>
                    <label>Catatan</label>
                    <textarea name="catatan" placeholder="Opsional"></textarea>
                    <button class="btn-submit" type="submit">Simpan Cash Movement</button>
                </form>
            </div>

            <div class="card">
                <h3 style="margin-top:0;">Riwayat Cash Movement Shift Aktif</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Tipe</th>
                            <th>Kategori</th>
                            <th>Catatan</th>
                            <th class="num">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rowsShiftAktif): ?>
                            <tr><td colspan="5" style="color:var(--muted);">Belum ada cash movement.</td></tr>
                        <?php else: foreach ($rowsShiftAktif as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$r['dibuat_pada']) ?></td>
                                <td><?= htmlspecialchars(strtoupper((string)$r['tipe'])) ?></td>
                                <td><?= htmlspecialchars((string)$r['kategori']) ?></td>
                                <td><?= htmlspecialchars((string)($r['catatan'] ?? '-')) ?></td>
                                <td class="num"><?= rupiah((float)$r['jumlah']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3 style="margin-top:0;">Riwayat Cash Movement (Filter Tanggal)</h3>
            <form method="get" style="display:grid;grid-template-columns:1fr 1fr 180px;gap:8px;align-items:end;margin-bottom:10px;">
                <div>
                    <label>Dari Tanggal</label>
                    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
                </div>
                <div>
                    <label>Sampai Tanggal</label>
                    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
                </div>
                <div>
                    <button class="btn-submit" type="submit">Terapkan Filter</button>
                </div>
            </form>
            <div class="grid" style="margin-bottom:10px;">
                <div class="stat"><small>Total Cash In</small><strong><?= rupiah($historyTotalIn) ?></strong></div>
                <div class="stat"><small>Total Cash Out</small><strong><?= rupiah($historyTotalOut) ?></strong></div>
                <div class="stat"><small>Net</small><strong><?= rupiah($historyNet) ?></strong></div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Shift</th>
                        <th>Kasir</th>
                        <th>Tipe</th>
                        <th>Kategori</th>
                        <th>Catatan</th>
                        <th class="num">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rowsHistory): ?>
                        <tr><td colspan="7" style="color:var(--muted);">Tidak ada data pada rentang tanggal ini.</td></tr>
                    <?php else: foreach ($rowsHistory as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$r['dibuat_pada']) ?></td>
                            <td>#<?= (int)$r['shift_id'] ?> - <?= htmlspecialchars((string)$r['nama_shift']) ?><br><span style="color:var(--muted);font-size:12px;"><?= htmlspecialchars((string)$r['tanggal_shift']) ?></span></td>
                            <td><?= htmlspecialchars((string)$r['nama_kasir']) ?></td>
                            <td><?= htmlspecialchars(strtoupper((string)$r['tipe'])) ?></td>
                            <td><?= htmlspecialchars((string)$r['kategori']) ?></td>
                            <td><?= htmlspecialchars((string)($r['catatan'] ?? '-')) ?></td>
                            <td class="num"><?= rupiah((float)$r['jumlah']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        function toggleKategoriCustom() {
            var select = document.getElementById('kategoriSelect');
            var custom = document.getElementById('kategoriCustom');
            if (!select || !custom) return;
            if (select.value === '__custom__') {
                custom.style.display = 'block';
                custom.required = true;
            } else {
                custom.style.display = 'none';
                custom.required = false;
                custom.value = '';
            }
        }
        toggleKategoriCustom();
    </script>
</body>
</html>
