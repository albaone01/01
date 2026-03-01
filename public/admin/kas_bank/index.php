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

$coa = [];
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
while ($row = $res->fetch_assoc()) {
    $coa[(string)$row['kode_akun']] = $row;
}
$st->close();

$hasKas = isset($coa['1101']);
$hasBank = isset($coa['1102']);

$saldoKas = 0.0;
$saldoBank = 0.0;
$mutasiKasIn = 0.0;
$mutasiKasOut = 0.0;
$mutasiBankIn = 0.0;
$mutasiBankOut = 0.0;
$rows = [];

if ($hasKas || $hasBank) {
    $akunIds = [];
    if ($hasKas) $akunIds[] = (int)$coa['1101']['akun_id'];
    if ($hasBank) $akunIds[] = (int)$coa['1102']['akun_id'];
    $ph = implode(',', array_fill(0, count($akunIds), '?'));
    $typesIds = str_repeat('i', count($akunIds));

    $sqlSaldo = "
        SELECT
            jd.akun_id,
            COALESCE(SUM(CASE WHEN ju.jurnal_id IS NOT NULL THEN jd.debit ELSE 0 END),0) AS total_debit,
            COALESCE(SUM(CASE WHEN ju.jurnal_id IS NOT NULL THEN jd.kredit ELSE 0 END),0) AS total_kredit
        FROM jurnal_detail jd
        LEFT JOIN jurnal_umum ju
            ON ju.jurnal_id = jd.jurnal_id
           AND ju.toko_id = ?
           AND ju.tanggal <= ?
        WHERE jd.akun_id IN ($ph)
        GROUP BY jd.akun_id
    ";
    $st = $pos_db->prepare($sqlSaldo);
    $types = 'is' . $typesIds;
    $params = array_merge([$tokoId, $to], $akunIds);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['akun_id'];
        $saldo = (float)$r['total_debit'] - (float)$r['total_kredit'];
        if ($hasKas && $id === (int)$coa['1101']['akun_id']) $saldoKas = $saldo;
        if ($hasBank && $id === (int)$coa['1102']['akun_id']) $saldoBank = $saldo;
    }
    $st->close();

    $sqlMutasi = "
        SELECT
            jd.akun_id,
            COALESCE(SUM(jd.debit),0) AS deb,
            COALESCE(SUM(jd.kredit),0) AS kre
        FROM jurnal_detail jd
        INNER JOIN jurnal_umum ju ON ju.jurnal_id = jd.jurnal_id
        WHERE ju.toko_id = ?
          AND ju.tanggal BETWEEN ? AND ?
          AND jd.akun_id IN ($ph)
        GROUP BY jd.akun_id
    ";
    $st = $pos_db->prepare($sqlMutasi);
    $types = 'iss' . $typesIds;
    $params = array_merge([$tokoId, $from, $to], $akunIds);
    $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $id = (int)$r['akun_id'];
        $deb = (float)$r['deb'];
        $kre = (float)$r['kre'];
        if ($hasKas && $id === (int)$coa['1101']['akun_id']) { $mutasiKasIn = $deb; $mutasiKasOut = $kre; }
        if ($hasBank && $id === (int)$coa['1102']['akun_id']) { $mutasiBankIn = $deb; $mutasiBankOut = $kre; }
    }
    $st->close();

    $sqlRows = "
        SELECT
            ju.tanggal,
            ju.nomor_jurnal,
            ju.sumber,
            ac.kode_akun,
            ac.nama_akun,
            jd.deskripsi,
            jd.debit,
            jd.kredit
        FROM jurnal_umum ju
        INNER JOIN jurnal_detail jd ON jd.jurnal_id = ju.jurnal_id
        INNER JOIN akun_coa ac ON ac.akun_id = jd.akun_id
        WHERE ju.toko_id = ?
          AND ju.tanggal BETWEEN ? AND ?
          AND jd.akun_id IN ($ph)
        ORDER BY ju.tanggal DESC, ju.jurnal_id DESC, jd.detail_id DESC
        LIMIT 400
    ";
    $st = $pos_db->prepare($sqlRows);
    $types = 'iss' . $typesIds;
    $params = array_merge([$tokoId, $from, $to], $akunIds);
    $st->bind_param($types, ...$params);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Kas & Bank</title>
    <style>
        :root { --bg:#f8fafc; --card:#fff; --line:#e2e8f0; --text:#0f172a; --muted:#64748b; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); }
        .wrap { max-width:1180px; margin:16px auto; padding:0 12px 20px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; box-shadow:0 8px 22px rgba(15,23,42,.05); }
        .head { padding:12px 14px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
        .title { margin:0; font-size:20px; }
        .muted { color:var(--muted); font-size:13px; margin:4px 0 0; }
        .toolbar { padding:12px 14px; border-bottom:1px solid var(--line); display:flex; gap:8px; flex-wrap:wrap; align-items:end; }
        label { display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; }
        input[type="date"] { border:1px solid var(--line); border-radius:8px; padding:8px 9px; }
        .btn { border:1px solid var(--line); background:#fff; border-radius:8px; padding:8px 10px; font-weight:700; cursor:pointer; }
        .kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; padding:10px; }
        .kpi { border:1px solid var(--line); border-radius:10px; padding:10px; background:#fff; }
        .kpi small { color:#475569; font-weight:700; }
        .kpi strong { display:block; margin-top:4px; font-size:18px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { border-bottom:1px solid var(--line); padding:8px 10px; text-align:left; }
        th { background:#f8fafc; color:#475569; font-size:12px; }
        td.num, th.num { text-align:right; font-variant-numeric:tabular-nums; }
        .section { padding:10px; }
        .warn { margin:10px; border:1px solid #fecaca; background:#fef2f2; color:#991b1b; border-radius:10px; padding:10px; font-size:13px; }
        @media (max-width:900px){ .kpis{grid-template-columns:repeat(2,minmax(0,1fr));} }
        @media (max-width:560px){ .kpis{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div>
                <h1 class="title">Kas & Bank</h1>
                <p class="muted">Posisi saldo kas/bank dan mutasi berdasarkan jurnal umum.</p>
            </div>
            <button class="btn" type="button" onclick="window.location.replace('../dashboard.php')">Kembali</button>
        </div>
        <form class="toolbar" method="get">
            <div><label>Dari</label><input type="date" name="from" value="<?=h($from)?>"></div>
            <div><label>Sampai</label><input type="date" name="to" value="<?=h($to)?>"></div>
            <div><button class="btn" type="submit">Terapkan</button></div>
        </form>

        <?php if (!$hasKas && !$hasBank): ?>
            <div class="warn">Akun COA `1101` (Kas) dan `1102` (Bank/QRIS) belum tersedia untuk toko ini.</div>
        <?php else: ?>
            <div class="kpis">
                <div class="kpi"><small>Saldo Kas (1101)</small><strong><?=rp($saldoKas)?></strong></div>
                <div class="kpi"><small>Saldo Bank/QRIS (1102)</small><strong><?=rp($saldoBank)?></strong></div>
                <div class="kpi"><small>Total Saldo</small><strong><?=rp($saldoKas + $saldoBank)?></strong></div>
                <div class="kpi"><small>Per Tanggal</small><strong><?=h($to)?></strong></div>
            </div>
            <div class="kpis" style="padding-top:0;">
                <div class="kpi"><small>Mutasi Kas Masuk</small><strong><?=rp($mutasiKasIn)?></strong></div>
                <div class="kpi"><small>Mutasi Kas Keluar</small><strong><?=rp($mutasiKasOut)?></strong></div>
                <div class="kpi"><small>Mutasi Bank Masuk</small><strong><?=rp($mutasiBankIn)?></strong></div>
                <div class="kpi"><small>Mutasi Bank Keluar</small><strong><?=rp($mutasiBankOut)?></strong></div>
            </div>
            <div class="section" style="padding-top:0;">
                <table>
                    <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>No Jurnal</th>
                        <th>Sumber</th>
                        <th>Akun</th>
                        <th>Deskripsi</th>
                        <th class="num">Debit</th>
                        <th class="num">Kredit</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" class="muted">Tidak ada mutasi kas/bank pada rentang tanggal ini.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?=h((string)$r['tanggal'])?></td>
                            <td><?=h((string)$r['nomor_jurnal'])?></td>
                            <td><?=h((string)$r['sumber'])?></td>
                            <td><?=h((string)$r['kode_akun'])?> - <?=h((string)$r['nama_akun'])?></td>
                            <td><?=h((string)($r['deskripsi'] ?? '-'))?></td>
                            <td class="num"><?=rp((float)$r['debit'])?></td>
                            <td class="num"><?=rp((float)$r['kredit'])?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

