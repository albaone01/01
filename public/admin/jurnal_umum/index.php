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
$source = trim((string)($_GET['source'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $monthStart;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = $today;
if ($from > $to) [$from, $to] = [$to, $from];

$allowedSources = ['penjualan','pembelian','piutang_pembayaran','hutang_pembayaran','manual','closing_kasir'];
if ($source !== '' && !in_array($source, $allowedSources, true)) {
    $source = '';
}

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function rp(float $v): string { return 'Rp ' . number_format($v, 0, ',', '.'); }

$summary = ['jurnal' => 0, 'debit' => 0.0, 'kredit' => 0.0];
$rows = [];

$where = "ju.toko_id = ? AND ju.tanggal BETWEEN ? AND ?";
$types = 'iss';
$params = [$tokoId, $from, $to];
if ($source !== '') {
    $where .= " AND ju.sumber = ?";
    $types .= 's';
    $params[] = $source;
}

$sqlSummary = "
    SELECT COUNT(*) AS jurnal, COALESCE(SUM(total_debit),0) AS debit, COALESCE(SUM(total_kredit),0) AS kredit
    FROM jurnal_umum ju
    WHERE $where
";
$st = $pos_db->prepare($sqlSummary);
$st->bind_param($types, ...$params);
$st->execute();
$summary = $st->get_result()->fetch_assoc() ?: $summary;
$st->close();

$sqlRows = "
    SELECT
        ju.jurnal_id,
        ju.tanggal,
        ju.nomor_jurnal,
        ju.sumber,
        ju.keterangan,
        ju.total_debit,
        ju.total_kredit,
        GROUP_CONCAT(
            CONCAT(ac.kode_akun, ' ', ac.nama_akun, ' (D:', FORMAT(jd.debit,2), ' K:', FORMAT(jd.kredit,2), ')')
            ORDER BY jd.detail_id ASC
            SEPARATOR ' | '
        ) AS detail_ringkas
    FROM jurnal_umum ju
    LEFT JOIN jurnal_detail jd ON jd.jurnal_id = ju.jurnal_id
    LEFT JOIN akun_coa ac ON ac.akun_id = jd.akun_id
    WHERE $where
    GROUP BY ju.jurnal_id, ju.tanggal, ju.nomor_jurnal, ju.sumber, ju.keterangan, ju.total_debit, ju.total_kredit
    ORDER BY ju.tanggal DESC, ju.jurnal_id DESC
    LIMIT 400
";
$st = $pos_db->prepare($sqlRows);
$st->bind_param($types, ...$params);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Jurnal Umum</title>
    <style>
        :root { --bg:#f8fafc; --card:#fff; --line:#e2e8f0; --text:#0f172a; --muted:#64748b; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); }
        .wrap { max-width:1220px; margin:16px auto; padding:0 12px 20px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; box-shadow:0 8px 22px rgba(15,23,42,.05); }
        .head { padding:12px 14px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
        .title { margin:0; font-size:20px; }
        .muted { color:var(--muted); font-size:13px; margin:4px 0 0; }
        .toolbar { padding:12px 14px; border-bottom:1px solid var(--line); display:flex; gap:8px; flex-wrap:wrap; align-items:end; }
        label { display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; }
        input[type="date"], select { border:1px solid var(--line); border-radius:8px; padding:8px 9px; min-width:150px; }
        .btn { border:1px solid var(--line); background:#fff; border-radius:8px; padding:8px 10px; font-weight:700; cursor:pointer; }
        .kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; padding:10px; }
        .kpi { border:1px solid var(--line); border-radius:10px; padding:10px; background:#fff; }
        .kpi small { color:#475569; font-weight:700; }
        .kpi strong { display:block; margin-top:4px; font-size:18px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { border-bottom:1px solid var(--line); padding:8px 10px; text-align:left; vertical-align:top; }
        th { background:#f8fafc; color:#475569; font-size:12px; }
        td.num, th.num { text-align:right; font-variant-numeric:tabular-nums; }
        .section { padding:10px; padding-top:0; }
        @media (max-width:980px){ .kpis{grid-template-columns:repeat(2,minmax(0,1fr));} }
        @media (max-width:560px){ .kpis{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="wrap">
    <div class="card">
        <div class="head">
            <div>
                <h1 class="title">Jurnal Umum</h1>
                <p class="muted">Daftar jurnal akuntansi dengan filter tanggal dan sumber transaksi.</p>
            </div>
            <button class="btn" type="button" onclick="window.location.replace('../dashboard.php')">Kembali</button>
        </div>
        <form class="toolbar" method="get">
            <div><label>Dari</label><input type="date" name="from" value="<?=h($from)?>"></div>
            <div><label>Sampai</label><input type="date" name="to" value="<?=h($to)?>"></div>
            <div>
                <label>Sumber</label>
                <select name="source">
                    <option value="">Semua</option>
                    <?php foreach ($allowedSources as $s): ?>
                        <option value="<?=h($s)?>" <?=$source === $s ? 'selected' : ''?>><?=h($s)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><button class="btn" type="submit">Terapkan</button></div>
        </form>
        <div class="kpis">
            <div class="kpi"><small>Jumlah Jurnal</small><strong><?=number_format((int)$summary['jurnal'])?></strong></div>
            <div class="kpi"><small>Total Debit</small><strong><?=rp((float)$summary['debit'])?></strong></div>
            <div class="kpi"><small>Total Kredit</small><strong><?=rp((float)$summary['kredit'])?></strong></div>
            <div class="kpi"><small>Selisih</small><strong><?=rp(abs((float)$summary['debit'] - (float)$summary['kredit']))?></strong></div>
        </div>
        <div class="section">
            <table>
                <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No Jurnal</th>
                    <th>Sumber</th>
                    <th>Keterangan</th>
                    <th>Detail Akun</th>
                    <th class="num">Debit</th>
                    <th class="num">Kredit</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="muted">Tidak ada data jurnal pada filter ini.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?=h((string)$r['tanggal'])?></td>
                        <td><?=h((string)$r['nomor_jurnal'])?></td>
                        <td><?=h((string)$r['sumber'])?></td>
                        <td><?=h((string)($r['keterangan'] ?? '-'))?></td>
                        <td><?=h((string)($r['detail_ringkas'] ?? '-'))?></td>
                        <td class="num"><?=rp((float)$r['total_debit'])?></td>
                        <td class="num"><?=rp((float)$r['total_kredit'])?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>

