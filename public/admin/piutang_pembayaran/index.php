<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';

requireLogin();
requireDevice();

$db = $pos_db;
$tokoId = (int)($_SESSION['toko_id'] ?? 0);

function rp($v): string {
    return 'Rp ' . number_format((float)$v, 0, ',', '.');
}

$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $piutangId = (int)($_POST['piutang_id'] ?? 0);
    $metode = strtolower(trim((string)($_POST['metode'] ?? 'cash')));
    $jumlah = (float)($_POST['jumlah'] ?? 0);

    $allowedMetode = ['cash', 'transfer', 'qris'];
    if (!in_array($metode, $allowedMetode, true)) $metode = 'cash';

    if ($piutangId <= 0) {
        $errorMsg = 'Piutang wajib dipilih.';
    } elseif ($jumlah <= 0) {
        $errorMsg = 'Jumlah pembayaran harus lebih dari 0.';
    } else {
        $stmt = $db->prepare("
            SELECT pt.piutang_id, pt.sisa
            FROM piutang pt
            INNER JOIN penjualan pj ON pj.penjualan_id = pt.penjualan_id
            WHERE pt.piutang_id = ? AND pj.toko_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $piutangId, $tokoId);
        $stmt->execute();
        $piutang = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$piutang) {
            $errorMsg = 'Piutang tidak ditemukan.';
        } elseif ($jumlah > (float)$piutang['sisa']) {
            $errorMsg = 'Jumlah pembayaran melebihi sisa piutang.';
        } else {
            $db->begin_transaction();
            try {
                $stmt = $db->prepare("INSERT INTO piutang_pembayaran (piutang_id, jumlah, metode) VALUES (?, ?, ?)");
                $stmt->bind_param("ids", $piutangId, $jumlah, $metode);
                $stmt->execute();
                $stmt->close();

                $stmt = $db->prepare("
                    UPDATE piutang
                    SET sisa = GREATEST(sisa - ?, 0),
                        status = CASE WHEN (sisa - ?) <= 0 THEN 'lunas' ELSE 'belum' END
                    WHERE piutang_id = ?
                ");
                $stmt->bind_param("ddi", $jumlah, $jumlah, $piutangId);
                $stmt->execute();
                $stmt->close();

                $db->commit();
                $successMsg = 'Pembayaran berhasil dicatat.';
            } catch (Throwable $e) {
                $db->rollback();
                $errorMsg = 'Gagal menyimpan pembayaran: ' . $e->getMessage();
            }
        }
    }
}

$invoicePrefill = trim((string)($_GET['invoice'] ?? ''));
$selectedPiutangId = 0;

$piutangAktif = [];
$stmt = $db->prepare("
    SELECT
        pt.piutang_id,
        pj.nomor_invoice,
        COALESCE(pl.nama_pelanggan, 'Walk-in') AS customer,
        pt.sisa
    FROM piutang pt
    INNER JOIN penjualan pj ON pj.penjualan_id = pt.penjualan_id
    LEFT JOIN pelanggan pl ON pl.pelanggan_id = pt.pelanggan_id AND pl.deleted_at IS NULL
    WHERE pj.toko_id = ?
      AND pt.sisa > 0
      AND pt.status <> 'lunas'
    ORDER BY pj.dibuat_pada DESC, pt.piutang_id DESC
    LIMIT 300
");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$resAktif = $stmt->get_result();
if ($resAktif) $piutangAktif = $resAktif->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($invoicePrefill !== '') {
    foreach ($piutangAktif as $p) {
        if ((string)$p['nomor_invoice'] === $invoicePrefill) {
            $selectedPiutangId = (int)$p['piutang_id'];
            break;
        }
    }
}

$history = [];
$stmt = $db->prepare("
    SELECT
        pp.pembayaran_id,
        pj.nomor_invoice,
        COALESCE(pl.nama_pelanggan, 'Walk-in') AS customer,
        pp.dibayar_pada,
        pp.metode,
        pp.jumlah
    FROM piutang_pembayaran pp
    INNER JOIN piutang pt ON pt.piutang_id = pp.piutang_id
    INNER JOIN penjualan pj ON pj.penjualan_id = pt.penjualan_id
    LEFT JOIN pelanggan pl ON pl.pelanggan_id = pt.pelanggan_id AND pl.deleted_at IS NULL
    WHERE pj.toko_id = ?
    ORDER BY pp.dibayar_pada DESC, pp.pembayaran_id DESC
    LIMIT 100
");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$resHist = $stmt->get_result();
if ($resHist) $history = $resHist->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Piutang</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        :root { --primary:#6366f1; --bg:#f5f7fb; --card:#fff; --border:#e2e8f0; --muted:#64748b; --text:#0f172a; --ok:#15803d; --bad:#b91c1c; }
        * { box-sizing: border-box; }
        body { font-family:'Plus Jakarta Sans','Inter',system-ui; margin:0; background:var(--bg); color:var(--text); }
        .page { max-width:1100px; margin:28px auto; padding:0 16px 48px; }
        .hero { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
        .breadcrumbs { font-size:13px; color:var(--muted); }
        .title { margin:4px 0 0; font-size:24px; font-weight:700; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; }
        .btn { padding:10px 14px; border:none; border-radius:10px; font-weight:600; cursor:pointer; font-size:14px; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-outline { background:#fff; color:var(--text); border:1px solid var(--border); }
        .card { background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow:0 14px 36px rgba(15,23,42,0.08); padding:18px; }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px; margin-top:12px; }
        label { font-weight:600; font-size:13px; }
        input, select, textarea { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:10px; font-size:14px; margin-top:6px; background:#f8fafc; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th, td { padding:12px 10px; border-bottom:1px solid var(--border); text-align:left; font-size:13px; }
        th { background:#f8fafc; color:var(--muted); font-weight:700; }
        tr:hover td { background:#f9fafb; }
        .flash { padding:10px 12px; border-radius:10px; margin-bottom:10px; font-size:13px; }
        .flash.ok { background:#ecfdf3; border:1px solid #bbf7d0; color:var(--ok); }
        .flash.err { background:#fef2f2; border:1px solid #fecaca; color:var(--bad); }
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="page">
    <div class="hero">
        <div>
            <div class="breadcrumbs">Penjualan / Pembayaran Piutang</div>
            <div class="title">Pembayaran Piutang</div>
            <div style="color:var(--muted);">Catat pelunasan dan update saldo piutang secara real-time.</div>
        </div>
        <div class="actions">
            <button class="btn btn-outline" onclick="window.location.href='../piutang/index.php'">Lihat Piutang</button>
        </div>
    </div>

    <div class="card">
        <div style="font-weight:700;">Form Pembayaran</div>

        <?php if ($successMsg !== ''): ?>
            <div class="flash ok"><?=htmlspecialchars($successMsg)?></div>
        <?php endif; ?>
        <?php if ($errorMsg !== ''): ?>
            <div class="flash err"><?=htmlspecialchars($errorMsg)?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-grid">
                <div>
                    <label>Invoice Piutang</label>
                    <select name="piutang_id" required>
                        <option value="">Pilih invoice</option>
                        <?php foreach($piutangAktif as $p): ?>
                            <?php
                                $pid = (int)$p['piutang_id'];
                                $sel = $selectedPiutangId === $pid ? 'selected' : '';
                                $label = $p['nomor_invoice'] . ' | ' . $p['customer'] . ' | Sisa ' . rp($p['sisa']);
                            ?>
                            <option value="<?=$pid?>" <?=$sel?>><?=htmlspecialchars($label)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Metode</label>
                    <select name="metode">
                        <option value="cash">Cash</option>
                        <option value="transfer">Transfer</option>
                        <option value="qris">QRIS</option>
                    </select>
                </div>
                <div>
                    <label>Jumlah</label>
                    <input type="number" name="jumlah" placeholder="0" min="1" step="1" required>
                </div>
                <div>
                    <label>Tanggal Bayar</label>
                    <input type="date" value="<?=date('Y-m-d')?>" disabled>
                </div>
            </div>
            <div style="margin-top:14px; display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Simpan Pembayaran</button>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:14px;">
        <div style="font-weight:700; margin-bottom:6px;">Riwayat Pembayaran Terakhir</div>
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Tanggal</th>
                    <th>Metode</th>
                    <th>Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($history)): ?>
                    <tr><td colspan="5" style="text-align:center; color:var(--muted); padding:18px;">Belum ada pembayaran.</td></tr>
                <?php else: foreach($history as $h): ?>
                    <tr>
                        <td><?=htmlspecialchars((string)$h['nomor_invoice'])?></td>
                        <td><?=htmlspecialchars((string)$h['customer'])?></td>
                        <td><?=htmlspecialchars((string)$h['dibayar_pada'])?></td>
                        <td><?=strtoupper(htmlspecialchars((string)$h['metode']))?></td>
                        <td><?=rp($h['jumlah'])?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
