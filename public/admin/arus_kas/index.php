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

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$from = trim((string)($_GET['from'] ?? $monthStart));
$to = trim((string)($_GET['to'] ?? $today));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $monthStart;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = $today;
if ($from > $to) [$from, $to] = [$to, $from];

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function rp(float $v): string { return 'Rp ' . number_format($v, 0, ',', '.'); }

$cashAccounts = [];
$st = $pos_db->prepare("
    SELECT akun_id, kode_akun, nama_akun
    FROM akun_coa
    WHERE toko_id = ?
      AND aktif = 1
      AND kode_akun IN ('1101','1102')
");
$st->bind_param('i', $tokoId);
$st->execute();
$res = $st->get_result();
while ($r = $res->fetch_assoc()) $cashAccounts[] = $r;
$st->close();

$opening = 0.0;
$inflow = 0.0;
$outflow = 0.0;
$netFlow = 0.0;
$closing = 0.0;
$rowsBySource = [];
$rowsByDate = [];

if ($cashAccounts) {
    $akunIds = array_map(fn($x) => (int)$x['akun_id'], $cashAccounts);
    $ph = implode(',', array_fill(0, count($akunIds), '?'));
    $typesIds = str_repeat('i', count($akunIds));

    $sqlOpening = "
        SELECT
            COALESCE(SUM(CASE WHEN ju.jurnal_id IS NOT NULL THEN jd.debit ELSE 0 END),0) AS deb,
            COALESCE(SUM(CASE WHEN ju.jurnal_id IS NOT NULL THEN jd.kredit ELSE 0 END),0) AS kre
        FROM jurnal_detail jd
        LEFT JOIN jurnal_umum ju
          ON ju.jurnal_id = jd.jurnal_id
         AND ju.toko_id = ?
         AND ju.tanggal < ?
        WHERE jd.akun_id IN ($ph)
    ";
    $st = $pos_db->prepare($sqlOpening);
    $types = 'is' . $typesIds;
    $params = array_merge([$tokoId, $from], $akunIds);
    $st->bind_param($types, ...$params);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: ['deb' => 0, 'kre' => 0];
    $st->close();
    $opening = (float)$row['deb'] - (float)$row['kre'];

    $sqlFlow = "
        SELECT
            COALESCE(SUM(jd.debit),0) AS deb,
            COALESCE(SUM(jd.kredit),0) AS kre
        FROM jurnal_detail jd
        INNER JOIN jurnal_umum ju ON ju.jurnal_id = jd.jurnal_id
        WHERE ju.toko_id = ?
          AND ju.tanggal BETWEEN ? AND ?
          AND jd.akun_id IN ($ph)
    ";
    $st = $pos_db->prepare($sqlFlow);
    $types = 'iss' . $typesIds;
    $params = array_merge([$tokoId, $from, $to], $akunIds);
    $st->bind_param($types, ...$params);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: ['deb' => 0, 'kre' => 0];
    $st->close();
    $inflow = (float)$row['deb'];
    $outflow = (float)$row['kre'];
    $netFlow = $inflow - $outflow;
    $closing = $opening + $netFlow;

    $sqlSource = "
        SELECT
            ju.sumber,
            COALESCE(SUM(jd.debit),0) AS inflow,
            COALESCE(SUM(jd.kredit),0) AS outflow
        FROM jurnal_umum ju
        INNER JOIN jurnal_detail jd ON jd.jurnal_id = ju.jurnal_id
        WHERE ju.toko_id = ?
          AND ju.tanggal BETWEEN ? AND ?
          AND jd.akun_id IN ($ph)
        GROUP BY ju.sumber
        ORDER BY (COALESCE(SUM(jd.debit),0) - COALESCE(SUM(jd.kredit),0)) DESC
    ";
    $st = $pos_db->prepare($sqlSource);
    $types = 'iss' . $typesIds;
    $params = array_merge([$tokoId, $from, $to], $akunIds);
    $st->bind_param($types, ...$params);
    $st->execute();
    $rowsBySource = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    $sqlDate = "
        SELECT
            ju.tanggal,
            COALESCE(SUM(jd.debit),0) AS inflow,
            COALESCE(SUM(jd.kredit),0) AS outflow
        FROM jurnal_umum ju
        INNER JOIN jurnal_detail jd ON jd.jurnal_id = ju.jurnal_id
        WHERE ju.toko_id = ?
          AND ju.tanggal BETWEEN ? AND ?
          AND jd.akun_id IN ($ph)
        GROUP BY ju.tanggal
        ORDER BY ju.tanggal ASC
    ";
    $st = $pos_db->prepare($sqlDate);
    $types = 'iss' . $typesIds;
    $params = array_merge([$tokoId, $from, $to], $akunIds);
    $st->bind_param($types, ...$params);
    $st->execute();
    $rowsByDate = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Arus Kas</title>
    <style>
        :root { --bg:#f8fafc; --card:#fff; --line:#e2e8f0; --text:#0f172a; --muted:#64748b; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; }
        .wrap { max-width:1200px; margin:16px auto; padding:0 12px 20px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; box-shadow:0 8px 22px rgba(15,23,42,.05); }
        .head { padding:12px 14px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
        .title { margin:0; font-size:20px; }
        .muted { color:var(--muted); font-size:13px; margin:4px 0 0; }
        .toolbar { padding:12px 14px; border-bottom:1px solid var(--line); display:flex; gap:8px; align-items:end; flex-wrap:wrap; }
        label { display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; }
        input[type="date"] { border:1px solid var(--line); border-radius:8px; padding:8px 9px; }
        .btn { border:1px solid var(--line); background:#fff; border-radius:8px; padding:8px 10px; font-weight:700; cursor:pointer; }
        .kpis { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:8px; padding:10px; }
        .kpi { border:1px solid var(--line); border-radius:10px; padding:10px; background:#fff; }
        .kpi small { color:#475569; font-weight:700; }
        .kpi strong { display:block; margin-top:4px; font-size:17px; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; padding:0 10px 10px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { border-bottom:1px solid var(--line); padding:8px 10px; text-align:left; }
        th { background:#f8fafc; color:#475569; font-size:12px; }
        td.num, th.num { text-align:right; font-variant-numeric:tabular-nums; }
        .box { border:1px solid var(--line); border-radius:10px; overflow:hidden; background:#fff; }
        .box h3 { margin:0; font-size:14px; padding:10px 12px; border-bottom:1px solid var(--line); background:#f8fafc; }
        .warn { margin:10px; border:1px solid #fecaca; background:#fef2f2; color:#991b1b; border-radius:10px; padding:10px; font-size:13px; }
        @media (max-width:980px){ .kpis{grid-template-columns:repeat(2,minmax(0,1fr));} .grid{grid-template-columns:1fr;} }
        @media (max-width:560px){ .kpis{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div>
                <h1 class="title">Arus Kas</h1>
                <p class="muted">Laporan arus kas dari mutasi akun Kas (1101) dan Bank/QRIS (1102).</p>
            </div>
            <button class="btn" type="button" onclick="window.location.replace('../dashboard.php')">Kembali</button>
        </div>
        <form class="toolbar" method="get">
            <div><label>Dari</label><input type="date" name="from" value="<?=h($from)?>"></div>
            <div><label>Sampai</label><input type="date" name="to" value="<?=h($to)?>"></div>
            <div><button class="btn" type="submit">Terapkan</button></div>
        </form>

        <?php if (!$cashAccounts): ?>
            <div class="warn">Akun COA kas/bank (`1101`/`1102`) belum tersedia, sehingga arus kas belum dapat dihitung.</div>
        <?php else: ?>
            <div class="kpis">
                <div class="kpi"><small>Saldo Awal</small><strong><?=rp($opening)?></strong></div>
                <div class="kpi"><small>Kas Masuk</small><strong><?=rp($inflow)?></strong></div>
                <div class="kpi"><small>Kas Keluar</small><strong><?=rp($outflow)?></strong></div>
                <div class="kpi"><small>Arus Bersih</small><strong><?=rp($netFlow)?></strong></div>
                <div class="kpi"><small>Saldo Akhir</small><strong><?=rp($closing)?></strong></div>
            </div>
            <div class="grid">
                <div class="box">
                    <h3>Arus Kas per Sumber</h3>
                    <table>
                        <thead><tr><th>Sumber</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Net</th></tr></thead>
                        <tbody>
                        <?php if (!$rowsBySource): ?>
                            <tr><td colspan="4" class="muted">Belum ada arus kas pada periode ini.</td></tr>
                        <?php else: foreach ($rowsBySource as $r):
                            $net = (float)$r['inflow'] - (float)$r['outflow'];
                        ?>
                            <tr>
                                <td><?=h((string)$r['sumber'])?></td>
                                <td class="num"><?=rp((float)$r['inflow'])?></td>
                                <td class="num"><?=rp((float)$r['outflow'])?></td>
                                <td class="num"><?=rp($net)?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="box">
                    <h3>Arus Kas Harian</h3>
                    <table>
                        <thead><tr><th>Tanggal</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Net</th></tr></thead>
                        <tbody>
                        <?php if (!$rowsByDate): ?>
                            <tr><td colspan="4" class="muted">Belum ada data harian pada periode ini.</td></tr>
                        <?php else: foreach ($rowsByDate as $r):
                            $net = (float)$r['inflow'] - (float)$r['outflow'];
                        ?>
                            <tr>
                                <td><?=h((string)$r['tanggal'])?></td>
                                <td class="num"><?=rp((float)$r['inflow'])?></td>
                                <td class="num"><?=rp((float)$r['outflow'])?></td>
                                <td class="num"><?=rp($net)?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

