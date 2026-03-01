<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/pos_saas_schema.php';

requireLogin();
requireDevice();
ensure_pos_saas_schema($pos_db);

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if ($tokoId <= 0) {
    header('Location: ../pilih_gudang.php');
    exit;
}

$asOf = trim((string)($_GET['as_of'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
    $asOf = date('Y-m-d');
}

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function rp(float $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }

$accounts = [];
$sql = "
    SELECT
        a.akun_id,
        a.kode_akun,
        a.nama_akun,
        a.tipe,
        COALESCE(SUM(CASE WHEN ju.jurnal_id IS NOT NULL THEN jd.debit ELSE 0 END),0) AS debit,
        COALESCE(SUM(CASE WHEN ju.jurnal_id IS NOT NULL THEN jd.kredit ELSE 0 END),0) AS kredit
    FROM akun_coa a
    LEFT JOIN jurnal_detail jd ON jd.akun_id = a.akun_id
    LEFT JOIN jurnal_umum ju ON ju.jurnal_id = jd.jurnal_id
        AND ju.toko_id = a.toko_id
        AND ju.tanggal <= ?
    WHERE a.toko_id = ?
      AND a.aktif = 1
    GROUP BY a.akun_id, a.kode_akun, a.nama_akun, a.tipe
    ORDER BY a.kode_akun ASC
";
$st = $pos_db->prepare($sql);
$st->bind_param('si', $asOf, $tokoId);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) {
    $debit = (float)$row['debit'];
    $kredit = (float)$row['kredit'];
    $type = (string)$row['tipe'];
    if (in_array($type, ['asset', 'expense'], true)) {
        $saldo = $debit - $kredit;
    } else {
        $saldo = $kredit - $debit;
    }
    $row['saldo'] = $saldo;
    $accounts[] = $row;
}
$st->close();

$assets = [];
$liabilities = [];
$equity = [];
$totalAsset = 0.0;
$totalLiability = 0.0;
$totalEquity = 0.0;
$totalRevenue = 0.0;
$totalExpense = 0.0;

foreach ($accounts as $a) {
    $type = (string)$a['tipe'];
    $saldo = (float)$a['saldo'];
    if ($type === 'asset') {
        if (abs($saldo) > 0.0001) $assets[] = $a;
        $totalAsset += $saldo;
    } elseif ($type === 'liability') {
        if (abs($saldo) > 0.0001) $liabilities[] = $a;
        $totalLiability += $saldo;
    } elseif ($type === 'equity') {
        if (abs($saldo) > 0.0001) $equity[] = $a;
        $totalEquity += $saldo;
    } elseif ($type === 'revenue') {
        $totalRevenue += $saldo;
    } elseif ($type === 'expense') {
        $totalExpense += $saldo;
    }
}

$labaBerjalan = $totalRevenue - $totalExpense;
if (abs($labaBerjalan) > 0.0001) {
    $equity[] = [
        'kode_akun' => '-',
        'nama_akun' => 'Laba Berjalan',
        'saldo' => $labaBerjalan,
    ];
}
$totalEquityFinal = $totalEquity + $labaBerjalan;
$rightSide = $totalLiability + $totalEquityFinal;
$selisih = $totalAsset - $rightSide;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Neraca</title>
    <style>
        :root { --bg:#f6f8fc; --card:#fff; --line:#e2e8f0; --text:#0f172a; --muted:#64748b; --ok:#166534; --bad:#991b1b; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; }
        .wrap { max-width:1200px; margin:16px auto; padding:0 12px 20px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; box-shadow:0 10px 24px rgba(15,23,42,.05); }
        .head { padding:12px 14px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
        .title { margin:0; font-size:20px; }
        .muted { color:var(--muted); font-size:13px; margin:4px 0 0; }
        .btn { border:1px solid var(--line); background:#fff; color:var(--text); border-radius:8px; padding:8px 10px; cursor:pointer; font-weight:700; }
        .toolbar { padding:12px 14px; border-bottom:1px solid var(--line); display:flex; gap:8px; align-items:end; flex-wrap:wrap; }
        label { display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; }
        input[type="date"] { border:1px solid var(--line); border-radius:8px; padding:8px 9px; }
        .summary { padding:10px 14px; border-bottom:1px solid var(--line); display:flex; gap:14px; flex-wrap:wrap; }
        .pill { border:1px solid var(--line); background:#f8fafc; border-radius:999px; padding:6px 10px; font-size:12px; font-weight:700; }
        .pill.ok { color:var(--ok); border-color:#bbf7d0; background:#f0fdf4; }
        .pill.bad { color:var(--bad); border-color:#fecaca; background:#fef2f2; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; padding:10px; }
        .box { border:1px solid var(--line); border-radius:10px; overflow:hidden; background:#fff; }
        .box h3 { margin:0; padding:10px 12px; font-size:14px; background:#f8fafc; border-bottom:1px solid var(--line); }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { border-bottom:1px solid var(--line); padding:8px 10px; text-align:left; }
        th { color:#475569; font-size:12px; }
        td.num, th.num { text-align:right; font-variant-numeric:tabular-nums; }
        tfoot td { font-weight:800; background:#f8fafc; }
        @media (max-width:980px){ .grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div>
                <h1 class="title">Neraca</h1>
                <p class="muted">Posisi keuangan per tanggal berdasarkan saldo jurnal.</p>
            </div>
            <button class="btn" type="button" onclick="window.location.replace('../dashboard.php')">Kembali</button>
        </div>
        <form class="toolbar" method="get">
            <div>
                <label>Per Tanggal</label>
                <input type="date" name="as_of" value="<?=h($asOf)?>">
            </div>
            <div><button class="btn" type="submit">Terapkan</button></div>
        </form>
        <div class="summary">
            <span class="pill">Aset: <?=rp($totalAsset)?></span>
            <span class="pill">Kewajiban: <?=rp($totalLiability)?></span>
            <span class="pill">Ekuitas: <?=rp($totalEquityFinal)?></span>
            <span class="pill <?=abs($selisih) < 0.01 ? 'ok' : 'bad'?>">Selisih Neraca: <?=rp($selisih)?></span>
        </div>
        <div class="grid">
            <div class="box">
                <h3>Aset</h3>
                <table>
                    <thead><tr><th>Kode</th><th>Akun</th><th class="num">Saldo</th></tr></thead>
                    <tbody>
                    <?php if (!$assets): ?>
                        <tr><td colspan="3" class="muted">Belum ada saldo aset.</td></tr>
                    <?php else: foreach ($assets as $a): ?>
                        <tr>
                            <td><?=h((string)$a['kode_akun'])?></td>
                            <td><?=h((string)$a['nama_akun'])?></td>
                            <td class="num"><?=rp((float)$a['saldo'])?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                    <tfoot><tr><td colspan="2">Total Aset</td><td class="num"><?=rp($totalAsset)?></td></tr></tfoot>
                </table>
            </div>
            <div style="display:grid; gap:10px;">
                <div class="box">
                    <h3>Kewajiban</h3>
                    <table>
                        <thead><tr><th>Kode</th><th>Akun</th><th class="num">Saldo</th></tr></thead>
                        <tbody>
                        <?php if (!$liabilities): ?>
                            <tr><td colspan="3" class="muted">Belum ada saldo kewajiban.</td></tr>
                        <?php else: foreach ($liabilities as $a): ?>
                            <tr>
                                <td><?=h((string)$a['kode_akun'])?></td>
                                <td><?=h((string)$a['nama_akun'])?></td>
                                <td class="num"><?=rp((float)$a['saldo'])?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                        <tfoot><tr><td colspan="2">Total Kewajiban</td><td class="num"><?=rp($totalLiability)?></td></tr></tfoot>
                    </table>
                </div>
                <div class="box">
                    <h3>Ekuitas</h3>
                    <table>
                        <thead><tr><th>Kode</th><th>Akun</th><th class="num">Saldo</th></tr></thead>
                        <tbody>
                        <?php if (!$equity): ?>
                            <tr><td colspan="3" class="muted">Belum ada saldo ekuitas.</td></tr>
                        <?php else: foreach ($equity as $a): ?>
                            <tr>
                                <td><?=h((string)$a['kode_akun'])?></td>
                                <td><?=h((string)$a['nama_akun'])?></td>
                                <td class="num"><?=rp((float)$a['saldo'])?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                        <tfoot><tr><td colspan="2">Total Ekuitas</td><td class="num"><?=rp($totalEquityFinal)?></td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
