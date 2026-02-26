<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/header.php';
require_once '../../../inc/csrf.php';

$db     = $pos_db;
$tokoId = $_SESSION['toko_id'] ?? 3;

$q      = $_GET['q'] ?? '';
$kat    = $_GET['kat'] ?? '';
$status = $_GET['status'] ?? '';
$low    = $_GET['low'] ?? '';
$csrfToken = csrf_token();

function fetch_all_stmt(mysqli_stmt $stmt): array {
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function formatRupiah($v): string {
    return 'Rp ' . number_format((float)$v, 0, ',', '.');
}

// Safeguard skema baru (max stok + multi satuan)
try { $db->query("ALTER TABLE produk ADD COLUMN max_stok INT NOT NULL DEFAULT 0 AFTER min_stok"); } catch(Exception $e) {}
$db->query("CREATE TABLE IF NOT EXISTS produk_satuan (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    produk_id BIGINT NOT NULL,
    nama_satuan VARCHAR(50) NOT NULL,
    qty_dasar DECIMAL(15,4) NOT NULL DEFAULT 1,
    urutan INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_produk_satuan (produk_id, nama_satuan),
    KEY idx_produk (produk_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Dropdown kategori */
$stmt = $db->prepare("SELECT kategori_id,nama_kategori FROM kategori_produk WHERE toko_id=? AND deleted_at IS NULL ORDER BY nama_kategori");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$kategori = fetch_all_stmt($stmt);
$stmt->close();

/* Dropdown supplier */
$stmt = $db->prepare("SELECT supplier_id,nama_supplier FROM supplier WHERE toko_id=? ORDER BY nama_supplier");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$supplier = fetch_all_stmt($stmt);
$stmt->close();

/* Dropdown gudang */
$stmt = $db->prepare("SELECT gudang_id,nama_gudang FROM gudang WHERE toko_id=? AND aktif=1 AND deleted_at IS NULL ORDER BY CASE WHEN nama_gudang='Gudang Utama' THEN 0 ELSE 1 END, nama_gudang");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$gudangList = fetch_all_stmt($stmt);
$stmt->close();

$defaultGudangId = (int)($_SESSION['gudang_id'] ?? 0);
if ($defaultGudangId > 0) {
    $foundDefault = false;
    foreach ($gudangList as $g) {
        if ((int)$g['gudang_id'] === $defaultGudangId) {
            $foundDefault = true;
            break;
        }
    }
    if (!$foundDefault) $defaultGudangId = 0;
}
if ($defaultGudangId <= 0 && !empty($gudangList)) {
    $defaultGudangId = (int)$gudangList[0]['gudang_id'];
}

/* Dropdown satuan */
$stmt = $db->prepare("SELECT satuan_id, nama FROM satuan ORDER BY nama");
$stmt->execute();
$satuanList = fetch_all_stmt($stmt);
$stmt->close();

/* Dropdown pajak */
$pajakList = [];
$stmt = $db->prepare("CREATE TABLE IF NOT EXISTS pajak (
    pajak_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL UNIQUE,
    persen DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    deskripsi VARCHAR(255) NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diupdate_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$stmt->execute();
$stmt->close();
$res = $db->query("SELECT pajak_id,nama,persen FROM pajak WHERE aktif=1 ORDER BY nama");
if($res) $pajakList = $res->fetch_all(MYSQLI_ASSOC);

/* Build filter */
$where  = ["p.toko_id = ?"];
$types  = "i";
$params = [$tokoId];

if ($q !== '') {
    $where[] = "(p.nama_produk LIKE CONCAT('%',?,'%') OR p.sku LIKE CONCAT('%',?,'%') OR p.barcode LIKE CONCAT('%',?,'%'))";
    $types  .= "sss";
    array_push($params, $q, $q, $q);
}
if ($kat !== '') {
    $where[] = "p.kategori_id = ?";
    $types  .= "i";
    $params[] = (int)$kat;
}
if ($status !== '') {
    $where[] = "p.aktif = ?";
    $types  .= "i";
    $params[] = (int)$status;
}

$sql = "
SELECT p.produk_id, p.sku, p.barcode, p.nama_produk, p.merk, p.satuan,
       p.harga_modal, p.aktif, p.min_stok, COALESCE(p.max_stok,0) AS max_stok, p.pajak_persen, p.foto, p.supplier_id,
       k.nama_kategori, COALESCE(SUM(sg.stok),0) AS stok_total,
       s.satuan_id AS satuan_id_ref, s.nama AS satuan_nama,
       sup.nama_supplier
FROM produk p
LEFT JOIN kategori_produk k ON k.kategori_id = p.kategori_id
LEFT JOIN stok_gudang sg ON sg.produk_id = p.produk_id
LEFT JOIN satuan s ON s.nama = p.satuan
LEFT JOIN supplier sup ON sup.supplier_id = p.supplier_id
WHERE " . implode(' AND ', $where) . "
GROUP BY p.produk_id
ORDER BY p.nama_produk
LIMIT 300";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$items = fetch_all_stmt($stmt);
$stmt->close();

if ($low === '1') {
    $items = array_values(array_filter($items, fn($it) => (int)$it['stok_total'] <= (int)$it['min_stok']));
}

/* Harga per tipe */
$harga = [];
$ids = array_column($items, 'produk_id');
if ($ids) {
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT produk_id, tipe, harga_jual FROM produk_harga WHERE produk_id IN ($ph)");
    $t    = str_repeat('i', count($ids));
    $stmt->bind_param($t, ...$ids);
    $stmt->execute();
    foreach ($stmt->get_result() as $r) {
        $harga[$r['produk_id']][$r['tipe']] = $r['harga_jual'];
    }
    $stmt->close();
}

$beli = [];
if ($ids) {
    $ph    = implode(',', array_fill(0, count($ids), '?'));
    $types = 'i' . str_repeat('i', count($ids));
    $stmt  = $db->prepare("
        SELECT pd.produk_id, pd.harga_beli, p.dibuat_pada
        FROM pembelian_detail pd
        JOIN pembelian p ON p.pembelian_id = pd.pembelian_id
        WHERE p.toko_id=? AND pd.produk_id IN ($ph)
        ORDER BY p.dibuat_pada ASC, pd.detail_id ASC
    ");
    $stmt->bind_param($types, $tokoId, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()){
        $pid = $r['produk_id'];
        if(!isset($beli[$pid]['awal'])) $beli[$pid]['awal'] = $r['harga_beli'];
        $beli[$pid]['akhir'] = $r['harga_beli'];
    }
    $stmt->close();
}

$satuanProduk = [];
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT produk_id, nama_satuan, qty_dasar, urutan FROM produk_satuan WHERE produk_id IN ($ph) ORDER BY produk_id, urutan ASC, id ASC");
    $t = str_repeat('i', count($ids));
    $stmt->bind_param($t, ...$ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()){
        $pid = (int)$r['produk_id'];
        $satuanProduk[$pid][] = $r['nama_satuan'] . ' x' . rtrim(rtrim(number_format((float)$r['qty_dasar'], 4, '.', ''), '0'), '.');
    }
    $stmt->close();
}

$totalProduk = count($items);
$aktifProduk = array_sum(array_column($items, 'aktif'));
$nilaiStok   = 0;
$lowStock    = 0;
foreach ($items as $it) {
    $nilaiStok += (float)$it['stok_total'] * (float)$it['harga_modal'];
    if ((int)$it['stok_total'] <= (int)$it['min_stok']) $lowStock++;
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

    :root {
        --primary: #0ea5e9;
        --primary-strong: #0284c7;
        --accent: #f97316;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --bg: #f5f7fb;
        --card: #ffffff;
        --text: #0f172a;
        --muted: #64748b;
        --border: #e2e8f0;
        --shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
    }
    body {
        font-family: 'Plus Jakarta Sans', 'Inter', system-ui, -apple-system, sans-serif;
        background: linear-gradient(135deg, #0f172a 0%, #0b5fa1 55%, #0ea5e9 100%) fixed;
        color: var(--text);
        margin: 0;
    }
    .page {
        min-height: 100vh;
        padding: 14px 12px 20px;
        background: radial-gradient(circle at 10% 20%, rgba(255,255,255,0.08), transparent 24%),
                    radial-gradient(circle at 90% 10%, rgba(14,165,233,0.15), transparent 30%),
                    linear-gradient(180deg, rgba(255,255,255,0.14) 0%, rgba(255,255,255,0.08) 40%, rgba(245,247,251,1) 75%);
    }
    .container {
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Hero */
    .hero {
        background: var(--card);
        border: 1px solid rgba(255,255,255,0.5);
        box-shadow: var(--shadow);
        border-radius: 18px;
        padding: 14px 16px;
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: 12px;
        position: relative;
        overflow: hidden;
    }
    .hero::after {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 80% 10%, rgba(14,165,233,0.12), transparent 45%),
                    radial-gradient(circle at 30% 20%, rgba(249,115,22,0.08), transparent 40%);
        pointer-events: none;
    }
    .hero h1 { margin: 2px 0 4px; font-size: 24px; font-weight: 700; }
    .eyebrow { color: var(--primary-strong); font-weight: 700; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; margin: 0; }
    .hero p { color: var(--muted); margin: 0 0 8px; max-width: 560px; }
    .hero-actions { display: flex; gap: 10px; flex-wrap: wrap; z-index: 1; }

    /* Buttons */
    .btn {
        padding: 8px 12px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid transparent;
        transition: transform .15s ease, box-shadow .2s ease, background .2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 14px;
    }
    .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-strong)); color: #fff; box-shadow: 0 12px 30px rgba(14,165,233,0.25); }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 16px 40px rgba(14,165,233,0.3); }
    .btn-ghost { background: rgba(14,165,233,0.08); color: var(--primary-strong); border-color: rgba(14,165,233,0.2); }
    .btn-ghost:hover { background: rgba(14,165,233,0.12); }
    .btn-outline { background: #fff; border-color: var(--border); color: var(--text); }
    .btn-outline:hover { background: #f8fafc; }

    /* Stat cards */
    .metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; z-index: 1; }
    .metric-card {
        background: linear-gradient(145deg, #0b0f1f, #0f172a);
        color: #e2e8f0;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.06);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.05);
    }
    .metric-card small { color: #94a3b8; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; }
    .metric-value { font-size: 18px; font-weight: 700; margin: 4px 0 2px; color: #e2e8f0; }
    .metric-trend { font-size: 13px; color: #a5b4fc; }

    /* Panel */
    .panel {
        margin-top: 10px;
        background: var(--card);
        border-radius: 16px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        overflow: hidden;
    }
    .toolbar {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border);
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        background: linear-gradient(90deg, rgba(14,165,233,0.06), rgba(255,255,255,1));
    }
    .toolbar .field {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 7px 9px;
    }
    .toolbar input[type="text"], .toolbar select {
        border: none;
        outline: none;
        font-size: 14px;
        color: var(--text);
        min-width: 160px;
        background: transparent;
    }
    .toolbar .pill-group { display: inline-flex; gap: 6px; }
    .pill {
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: #fff;
        font-weight: 600;
        color: var(--muted);
        cursor: pointer;
        transition: all .2s ease;
        text-decoration: none;
    }
    .pill.active { background: rgba(14,165,233,0.1); color: var(--primary-strong); border-color: rgba(14,165,233,0.25); }

    /* Table */
    .table-wrap { overflow: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    thead th {
        background: #f8fafc;
        color: var(--muted);
        text-align: left;
        font-weight: 700;
        padding: 9px 10px;
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 2;
    }
    tbody td {
        padding: 9px 10px;
        border-bottom: 1px solid #eef2f6;
        color: var(--text);
        background: #fff;
    }
    tbody tr:hover td { background: #f8fbff; }

    .product-info { display: flex; align-items: center; gap: 8px; }
    .product-img { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; background: #e2e8f0; border: 1px solid #e2e8f0; }
    .product-name { font-weight: 700; }
    .meta { color: var(--muted); font-size: 12px; }

    .badge { padding: 4px 8px; border-radius: 8px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
    .badge-success { background: rgba(16,185,129,0.12); color: #0f9f6e; }
    .badge-danger { background: rgba(239,68,68,0.12); color: #c53030; }
    .badge-warning { background: rgba(245,158,11,0.16); color: #b45309; }
    .badge-muted { background: #f1f5f9; color: var(--muted); }

    /* Modal */
    .modal { display:none; position:fixed; z-index:100; left:0; top:30; width:100%; height:100%; background:rgba(0,0,0,0.45); backdrop-filter: blur(6px); }
    .modal-content { background:#fff; margin:3% auto; width:92%; max-width:700px; border-radius:14px; box-shadow:0 24px 60px rgba(15,23,42,0.3); }
    .modal-header { padding:10px 12px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
    .modal-body { padding:10px 12px; max-height: 72vh; overflow-y: auto; }
    .form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap:8px; }
    .full-width { grid-column: 1 / -1; }
    
    input, select { width:100%; padding:6px 8px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; margin-top:3px; font-family:inherit; background:#f8fafc; font-size:12px; }
    input:focus, select:focus { outline: 2px solid rgba(14,165,233,0.2); background:#fff; }
    label { font-size:13px; font-weight:700; color: var(--muted); }
    .collapse-card { display: none; margin-top: 6px; padding: 8px; border: 1px dashed rgba(14,165,233,0.4); border-radius: 10px; background: rgba(14,165,233,0.06); }
    .collapse-card.show { display: block; }
    .hint { color: var(--muted); font-size: 12px; margin-top: 4px; }
    .field-error { color: #b42318; font-size: 12px; margin-top: 4px; min-height: 14px; display:block; }
    .input-invalid { border-color: #fca5a5 !important; background: #fff1f2 !important; }
    .error-banner { display:none; margin-bottom:8px; padding:8px 10px; border-radius:8px; border:1px solid #fecaca; background:#fff1f2; color:#991b1b; font-size:12px; }
    .inline-actions { display:flex; gap:6px; margin-top:4px; }
    .price-preview { display:grid; grid-template-columns: repeat(auto-fit, minmax(140px,1fr)); gap:6px; margin-top:6px; }
    .price-preview .item { font-size:11px; color:var(--muted); background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:6px; }
    .sat-row { display:grid; grid-template-columns: 1fr 120px 46px; gap:6px; margin-bottom:6px; }
    .sat-row input { margin-top:0; }

    @media (max-width: 1024px) {
        .hero { grid-template-columns: 1fr; }
        .metrics { grid-template-columns: repeat(2, minmax(0,1fr)); }
    }
    @media (max-width: 720px) {
        .metrics { grid-template-columns: 1fr; }
        .toolbar { flex-direction: column; align-items: stretch; }
        .toolbar .field { width: 100%; }
        .hero-actions { width: 100%; }
        .hero-actions .btn { flex: 1; justify-content: center; }
    }
</style>

<div class="page">
    <div class="container">
        <div class="hero">
            <div style="z-index:1;">
                <p class="eyebrow">Inventori</p>
                <h1>Master Barang</h1>
                <p>Kelola katalog produk dengan cepat, lihat stok lintas gudang, dan jaga harga jual tetap sehat.</p>
                <div class="hero-actions">
                    <button class="btn btn-primary" onclick="openModal()">+ Tambah Produk</button>
                    <button class="btn btn-ghost" onclick="exportData()">Export Excel</button>
                </div>
            </div>
            <div class="metrics">
                <div class="metric-card">
                    <small>Total SKU</small>
                    <div class="metric-value"><?=$totalProduk?></div>
                    <div class="metric-trend"><?=$aktifProduk?> aktif</div>
                </div>
                <div class="metric-card">
                    <small>Nilai Stok</small>
                    <div class="metric-value"><?=formatRupiah($nilaiStok)?></div>
                    <div class="metric-trend">Berbasis harga modal</div>
                </div>
                <div class="metric-card">
                    <small>Stok Rendah</small>
                    <div class="metric-value"><?=$lowStock?></div>
                    <div class="metric-trend">Periksa & isi ulang</div>
                </div>
            </div>
        </div>

        <div class="panel">
            <form class="toolbar" method="get">
                <div class="field" style="flex:1 1 280px">
                    <span style="color:var(--muted);font-weight:700;">🔍</span>
                    <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari nama, SKU, atau barcode">
                </div>
                <div class="field">
                    <span style="color:var(--muted);font-weight:700;">Kategori</span>
                    <select name="kat">
                        <option value="">Semua</option>
                        <?php foreach($kategori as $k): ?>
                            <option value="<?=$k['kategori_id']?>" <?=$kat==$k['kategori_id']?'selected':''?>><?=htmlspecialchars($k['nama_kategori'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <span style="color:var(--muted);font-weight:700;">Status</span>
                    <select name="status">
                        <option value="">Semua</option>
                        <option value="1" <?=$status==='1'?'selected':''?>>Aktif</option>
                        <option value="0" <?=$status==='0'?'selected':''?>>Nonaktif</option>
                    </select>
                </div>
                <div class="pill-group">
                    <?php
                        $urlBase = strtok($_SERVER["REQUEST_URI"], '?');
                        $params  = $_GET;
                        $paramAll = $params; unset($paramAll['low']);
                        $paramLow = $params; $paramLow['low'] = '1';
                        $paramLive = $params; $paramLive['status'] = '1';
                        function buildUrl($base, $query){ return $base . ($query ? '?'.http_build_query($query) : ''); }
                    ?>
                    <a class="pill <?=$low!== '1' ? 'active' : ''?>" href="<?=htmlspecialchars(buildUrl($urlBase, $paramAll))?>">Semua</a>
                    <a class="pill <?=$status==='1'?'active':''?>" href="<?=htmlspecialchars(buildUrl($urlBase, $paramLive))?>">Aktif</a>
                    <a class="pill <?=$low==='1'?'active':''?>" href="<?=htmlspecialchars(buildUrl($urlBase, $paramLow))?>">Stok Rendah</a>
                </div>
                <button type="submit" class="btn btn-outline" style="margin-left:auto;">Terapkan</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th>Supplier</th>
                            <th>Harga Modal</th>
                            <th>Harga Jual</th>
                            <th>Beli Awal</th>
                            <th>Beli Akhir</th>
                            <th>Satuan & Batas</th>
                            <th>Stok</th>
                            <th>Status</th>
                            <th style="text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $it):
                            $isLowStock = $it['stok_total'] <= $it['min_stok'];
                        ?>
                        <tr>
                            <td>
                                <div class="product-info">
                            <?php
                                $uploadBase = '../../uploads/produk/';
                                $imgSrc = $it['foto']
                                    ? $uploadBase.$it['foto']
                                    : 'data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2748%27 height=%2748%27 viewBox=%270 0 48 48%27%3E%3Crect width=%2748%27 height=%2748%27 rx=%2712%27 fill=%27%23e2e8f0%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 text-anchor=%27middle%27 dy=%270.35em%27 font-family=%27Arial%27 font-size=%2710%27 fill=%27%2399a1b3%27%3ENo%20Img%3C/text%3E%3C/svg%3E';
                            ?>
                            <img src="<?= $imgSrc ?>" class="product-img" loading="lazy">
                                    <div>
                                        <div class="product-name"><?=htmlspecialchars($it['nama_produk'])?></div>
                                        <div class="meta"><?=htmlspecialchars($it['sku'] ?: '-')?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?=htmlspecialchars($it['nama_kategori'] ?? '-')?></td>
                            <td><?=htmlspecialchars($it['nama_supplier'] ?? '-')?></td>
                            <td><?=formatRupiah($it['harga_modal'])?></td>
                            <td><strong><?=formatRupiah($harga[$it['produk_id']]['ecer'] ?? 0)?></strong></td>
                            <td class="meta"><?=isset($beli[$it['produk_id']]['awal']) ? formatRupiah($beli[$it['produk_id']]['awal']) : '-'?></td>
                            <td class="meta"><?=isset($beli[$it['produk_id']]['akhir']) ? formatRupiah($beli[$it['produk_id']]['akhir']) : '-'?></td>
                            <td>
                                <div class="meta"><?=htmlspecialchars(implode(', ', $satuanProduk[(int)$it['produk_id']] ?? [$it['satuan']]))?></div>
                                <div class="meta">Min <?=$it['min_stok']?> / Max <?=((int)$it['max_stok']>0 ? (int)$it['max_stok'] : '-')?></div>
                            </td>
                            <td>
                                <span class="badge <?=$isLowStock ? 'badge-warning' : 'badge-muted'?>">
                                    <?=$it['stok_total']?> <?=htmlspecialchars($it['satuan_nama'] ?? $it['satuan'])?>
                                    <?=$isLowStock ? '• Rendah' : ''?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?=$it['aktif']?'badge-success':'badge-danger'?>">
                                    <?=$it['aktif']?'Aktif':'Nonaktif'?>
                                </span>
                            </td>
                            <td style="text-align:right">
                                <button class="btn btn-outline" style="padding:6px 10px" onclick="editBarang(<?=$it['produk_id']?>)">Edit</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="productModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle" style="margin:0; font-size:18px;">Tambah Produk</h2>
            <span style="cursor:pointer; font-size:24px" onclick="closeModal()">&times;</span>
        </div>
        <form id="barangForm">
            <div class="modal-body">
                <input type="hidden" name="produk_id">
                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
                <div id="formErrorBanner" class="error-banner"></div>
                <div class="form-grid">
                    <div>
                        <label>Kode Produk</label>
                        <input name="sku" required placeholder="Kode Produk">
                        <div class="inline-actions">
                            <button class="btn btn-outline" type="button" id="btnGenerateSku" style="padding:6px 10px;">Auto SKU</button>
                        </div>
                        <small class="field-error" data-for="sku"></small>
                    </div>
                    <div class="full-width">
                        <label>Nama Produk</label>
                        <input name="nama_produk" required placeholder="Contoh: Kopi Susu Gula Aren">
                        <small class="field-error" data-for="nama_produk"></small>
                    </div>
                    <div>
                        <label>Barcode</label>
                        <input name="barcode" placeholder="Scan Barcode">
                        <small class="field-error" data-for="barcode"></small>
                    </div>
                    <div>
                        <label>Supplier</label>
                        <div class="lookup-wrap">
                            <input type="hidden" name="supplier_id" id="supplier_id" required>
                            <input type="text" id="supplier_name" class="lookup-display" placeholder="Pilih supplier" readonly required>
                            <button type="button" class="btn btn-outline" onclick="openSup()">🔍</button>
                        </div>
                    </div>
                        <small class="field-error" data-for="supplier_id"></small>
                    <div>
                        <label>Kategori</label>
                        <div class="lookup-wrap">
                            <input type="hidden" name="kategori_id" id="kategori_id" required>
                            <input type="text" id="kategori_name" class="lookup-display" placeholder="Pilih kategori" readonly required>
                            <button type="button" class="btn btn-outline" onclick="openKat()">🔍</button>
                        </div>
                    </div>
                        <small class="field-error" data-for="kategori_id"></small>
                    <div>
                        <label>Satuan Dasar</label>
                        <input name="satuan" required placeholder="Contoh: PCS">
                        <small class="field-error" data-for="satuan"></small>
                    </div>
                    <div class="full-width">
                        <label>Multi Satuan (konversi ke satuan dasar)</label>
                        <div id="multiSatuanList"></div>
                        <button type="button" class="btn btn-outline" style="padding:5px 8px;margin-top:4px;" onclick="addSatuanRow()">+ Tambah Satuan</button>
                        <div class="hint">Contoh: BOX isi 12 PCS, KARTON isi 24 BOX (isi selalu terhadap satuan dasar).</div>
                        <small class="field-error" data-for="multi_satuan"></small>
                    </div>
                    <div>
                        <label>Harga Ambil</label>
                        <input type="number" name="harga_modal" min="0" step="0.01" required>
                        <small class="field-error" data-for="harga_modal"></small>
                    </div>
                    <div>
                        <label>Harga Jual (Ecer)</label>
                        <input type="number" name="harga_ecer" min="0" step="0.01" required>
                        <small class="field-error" data-for="harga_ecer"></small>
                    </div>
                    <div>
                        <label>Harga Grosir</label>
                        <input type="number" name="harga_grosir" min="0" step="0.01" required>
                        <small class="field-error" data-for="harga_grosir"></small>
                    </div>
                    <div>
                        <label>Harga Reseller</label>
                        <input type="number" name="harga_reseller" min="0" step="0.01" required>
                        <small class="field-error" data-for="harga_reseller"></small>
                    </div>
                    <div>
                        <label>Harga Member</label>
                        <input type="number" name="harga_member" min="0" step="0.01" required>
                        <small class="field-error" data-for="harga_member"></small>
                    </div>
                    <div class="full-width">
                        <label>Preview Harga + Pajak</label>
                        <div class="price-preview" id="pricePreviewBox">
                            <div class="item">Ecer: <strong id="preview_ecer">Rp 0</strong></div>
                            <div class="item">Grosir: <strong id="preview_grosir">Rp 0</strong></div>
                            <div class="item">Reseller: <strong id="preview_reseller">Rp 0</strong></div>
                            <div class="item">Member: <strong id="preview_member">Rp 0</strong></div>
                        </div>
                    </div>
                    <div>
                        <label>Min. Stok (Peringatan)</label>
                        <input type="number" name="min_stok" min="0" value="0">
                        <small class="field-error" data-for="min_stok"></small>
                    </div>
                    <div>
                        <label>Max. Stok</label>
                        <input type="number" name="max_stok" min="0" value="0">
                        <small class="field-error" data-for="max_stok"></small>
                    </div>
                    <div>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer">
                            <input type="checkbox" name="is_jasa" id="is_jasa" value="1" style="width:auto; margin:0">
                            Produk Jasa (tanpa stok)
                        </label>
                    </div>
                    <div>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer">
                            <input type="checkbox" name="is_konsinyasi" id="is_konsinyasi" value="1" style="width:auto; margin:0">
                            Produk Konsinyasi
                        </label>
                    </div>
                    <div class="full-width">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer">
                            <input type="checkbox" name="isi_stok_awal" id="isi_stok_awal" value="1" style="width:auto; margin:0">
                            Isi stok awal?
                        </label>
                        <div class="hint">Jika dicentang, produk langsung dibuatkan saldo awal di gudang.</div>
                    </div>
                    <div class="full-width">
                        <div id="stokAwalFields" class="collapse-card">
                            <div class="form-grid">
                                <div>
                                    <label>Gudang</label>
                                    <select name="gudang_id" id="stok_awal_gudang">
                                        <option value="">-- Pilih Gudang --</option>
                                        <?php foreach($gudangList as $g): ?>
                                            <option value="<?=$g['gudang_id']?>" <?=$defaultGudangId===(int)$g['gudang_id']?'selected':''?>>
                                                <?=htmlspecialchars($g['nama_gudang'])?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="field-error" data-for="stok_awal_gudang"></small>
                                </div>
                                <div>
                                    <label>Qty Stok Awal</label>
                                    <input type="number" name="stok_awal_qty" id="stok_awal_qty" min="1" step="1" value="1">
                                    <small class="field-error" data-for="stok_awal_qty"></small>
                                </div>
                                <div>
                                    <label>Harga Modal Stok Awal</label>
                                    <input type="number" name="stok_awal_harga_modal" id="stok_awal_harga_modal" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label>Pajak</label>
                        <select name="pajak_select" id="pajak_select">
                            <option value="">-- Pilih Pajak --</option>
                            <?php foreach($pajakList as $p): ?>
                                <option value="<?=htmlspecialchars($p['persen'])?>" data-id="<?=$p['pajak_id']?>">
                                    <?=htmlspecialchars($p['nama'])?> (<?=number_format($p['persen'],2)?>%)
                                </option>
                            <?php endforeach; ?>
                            <option value="custom">Custom</option>
                        </select>
                        <input type="number" step="0.01" min="0" max="100" name="pajak_persen" id="pajak_persen" value="0" style="margin-top:6px;" disabled>
                    </div>
                    <div class="full-width">
                        <label>Foto Produk</label>
                        <input type="file" name="foto" accept="image/*">
                        <div class="hint">Hanya JPG/PNG/WEBP/GIF. File berbahaya (PHP/SVG/script) akan ditolak.</div>
                        <small class="field-error" data-for="foto"></small>
                    </div>
                    <div class="full-width">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer">
                            <input type="checkbox" name="aktif" value="1" checked style="width:auto; margin:0"> Status Produk Aktif
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding:8px 10px; border-top:1px solid var(--border); text-align:right">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Produk</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('productModal');
const form  = document.getElementById('barangForm');
const gudangData = <?php echo json_encode($gudangList, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const defaultGudangId = <?=(int)$defaultGudangId?>;
const existingProduk = <?php
    $dupeData = array_map(function($it){
        return [
            'produk_id' => (int)$it['produk_id'],
            'sku' => (string)($it['sku'] ?? ''),
            'barcode' => (string)($it['barcode'] ?? '')
        ];
    }, $items);
    echo json_encode($dupeData, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>;
const satuanMaster = <?php
    $sat = array_map(fn($s)=>$s['nama'], $satuanList);
    echo json_encode($sat, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>;

function toNumber(v){
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
}

function formatIdr(v){
    return new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(toNumber(v))));
}

function clearErrors(){
    document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
    document.querySelectorAll('#barangForm .input-invalid').forEach(el => el.classList.remove('input-invalid'));
    const banner = document.getElementById('formErrorBanner');
    if (banner) {
        banner.style.display = 'none';
        banner.textContent = '';
    }
}

function setFieldError(fieldName, msg){
    const err = form.querySelector(`.field-error[data-for="${fieldName}"]`);
    if (err) err.textContent = msg;
    const target = form[fieldName] || document.getElementById(fieldName);
    if (target && target.classList) target.classList.add('input-invalid');
}

function showFormError(msg){
    const banner = document.getElementById('formErrorBanner');
    if (banner) {
        banner.textContent = msg;
        banner.style.display = 'block';
    }
}

function updatePricePreview(){
    const pajak = form.pajak_select?.value === 'custom' ? toNumber(form.pajak_persen?.value) : toNumber(form.pajak_select?.value);
    const factor = 1 + (Math.max(0, pajak) / 100);
    const val = (n) => `Rp ${formatIdr(toNumber(n) * factor)}`;
    const map = {
        ecer: form.harga_ecer?.value,
        grosir: form.harga_grosir?.value,
        reseller: form.harga_reseller?.value,
        member: form.harga_member?.value
    };
    Object.keys(map).forEach(k => {
        const el = document.getElementById(`preview_${k}`);
        if (el) el.textContent = val(map[k]);
    });
}

function addSatuanRow(nama = '', isi = '1'){
    const wrap = document.getElementById('multiSatuanList');
    if(!wrap) return;
    const row = document.createElement('div');
    row.className = 'sat-row';
    const options = satuanMaster.map(s=>`<option value="${String(s).replace(/"/g,'&quot;')}"></option>`).join('');
    row.innerHTML = `
        <input name="multi_satuan_nama[]" placeholder="Nama Satuan (PCS/BOX/KARTON)" value="${String(nama).replace(/"/g,'&quot;')}" list="datalistSatuan">
        <input type="number" name="multi_satuan_isi[]" min="0.0001" step="0.0001" value="${isi}">
        <button type="button" class="btn btn-outline" style="padding:0" title="Hapus">x</button>
    `;
    row.querySelector('button').addEventListener('click', ()=>row.remove());
    wrap.appendChild(row);
    if (!document.getElementById('datalistSatuan')) {
        const dl = document.createElement('datalist');
        dl.id = 'datalistSatuan';
        dl.innerHTML = options;
        document.body.appendChild(dl);
    }
}

function setMultiSatuan(data){
    const wrap = document.getElementById('multiSatuanList');
    if(!wrap) return;
    wrap.innerHTML = '';
    if(Array.isArray(data) && data.length){
        data.forEach(s=>addSatuanRow(s.nama_satuan || '', s.qty_dasar || '1'));
    } else {
        addSatuanRow(form.satuan?.value || '', '1');
    }
}

function applyJasaMode(){
    const jasa = !!document.getElementById('is_jasa')?.checked;
    if (jasa) {
        if (form.min_stok) form.min_stok.value = 0;
        if (form.max_stok) form.max_stok.value = 0;
        const isi = document.getElementById('isi_stok_awal');
        if (isi) isi.checked = false;
        toggleStokAwal(false);
    }
    ['min_stok','max_stok','isi_stok_awal','stok_awal_gudang','stok_awal_qty','stok_awal_harga_modal'].forEach(id=>{
        const el = document.getElementById(id) || form[id];
        if (el) el.disabled = jasa;
    });
}

function toggleStokAwal(forceValue = null){
    const check = document.getElementById('isi_stok_awal');
    const card = document.getElementById('stokAwalFields');
    if(!check || !card) return;
    if (typeof forceValue === 'boolean') check.checked = forceValue;
    card.classList.toggle('show', check.checked);
    if (check.checked && form.stok_awal_harga_modal && !form.stok_awal_harga_modal.value) {
        form.stok_awal_harga_modal.value = form.harga_modal.value || 0;
    }
}

function validateBusinessRules(){
    clearErrors();
    const id = parseInt(form.produk_id.value || '0', 10);
    const sku = (form.sku.value || '').trim().toLowerCase();
    const barcode = (form.barcode.value || '').trim().toLowerCase();
    const hargaModal = toNumber(form.harga_modal.value);
    const hargaEcer = toNumber(form.harga_ecer.value);
    const hargaGrosir = toNumber(form.harga_grosir.value);
    const hargaReseller = toNumber(form.harga_reseller.value);
    const hargaMember = toNumber(form.harga_member.value);
    const minStok = parseInt(form.min_stok.value || '0', 10);
    const maxStok = parseInt(form.max_stok.value || '0', 10);
    const isJasa = !!document.getElementById('is_jasa')?.checked;
    const isiStokAwal = !!document.getElementById('isi_stok_awal')?.checked;
    const gudangStokAwal = document.getElementById('stok_awal_gudang')?.value || '';
    const qtyStokAwal = parseInt(document.getElementById('stok_awal_qty')?.value || '0', 10);
    const errs = [];

    if (!form.nama_produk.value.trim()) { errs.push('Nama produk wajib diisi.'); setFieldError('nama_produk','Wajib diisi'); }
    if (!form.sku.value.trim()) { errs.push('SKU wajib diisi.'); setFieldError('sku','Wajib diisi'); }
    if (!form.supplier_id.value) { errs.push('Supplier wajib dipilih.'); setFieldError('supplier_id','Wajib dipilih'); }
    if (!form.kategori_id.value) { errs.push('Kategori wajib dipilih.'); setFieldError('kategori_id','Wajib dipilih'); }
    if (!form.satuan.value.trim()) { errs.push('Satuan wajib diisi.'); setFieldError('satuan','Wajib diisi'); }
    if (minStok < 0) { errs.push('Min stok tidak boleh negatif.'); setFieldError('min_stok','Tidak boleh negatif'); }
    if (maxStok < 0) { errs.push('Max stok tidak boleh negatif.'); setFieldError('max_stok','Tidak boleh negatif'); }
    if (maxStok > 0 && maxStok < minStok) { errs.push('Max stok tidak boleh lebih kecil dari min stok.'); setFieldError('max_stok','Harus >= min'); }
    if (hargaModal < 0) { errs.push('Harga modal tidak boleh negatif.'); setFieldError('harga_modal','Tidak boleh negatif'); }
    if (hargaEcer < hargaModal || hargaGrosir < hargaModal || hargaReseller < hargaModal || hargaMember < hargaModal) {
        errs.push('Harga jual (ecer/grosir/reseller/member) tidak boleh lebih kecil dari harga modal.');
        setFieldError('harga_ecer','Harus >= harga modal');
        setFieldError('harga_grosir','Harus >= harga modal');
        setFieldError('harga_reseller','Harus >= harga modal');
        setFieldError('harga_member','Harus >= harga modal');
    }
    if (sku) {
        const dupSku = existingProduk.some(p => (p.produk_id !== id) && (String(p.sku || '').toLowerCase() === sku));
        if (dupSku) { errs.push('SKU sudah digunakan produk lain.'); setFieldError('sku','SKU duplikat'); }
    }
    if (barcode) {
        const dupBarcode = existingProduk.some(p => (p.produk_id !== id) && (String(p.barcode || '').toLowerCase() === barcode));
        if (dupBarcode) { errs.push('Barcode sudah digunakan produk lain.'); setFieldError('barcode','Barcode duplikat'); }
    }
    if (!isJasa && isiStokAwal) {
        if (!gudangData.length) { errs.push('Belum ada gudang aktif. Tambahkan gudang terlebih dahulu.'); }
        if (!gudangStokAwal) { errs.push('Pilih gudang untuk stok awal.'); setFieldError('stok_awal_gudang','Wajib dipilih'); }
        if (qtyStokAwal <= 0) { errs.push('Qty stok awal harus lebih dari 0.'); setFieldError('stok_awal_qty','Harus > 0'); }
    }
    const satNama = [...document.querySelectorAll('input[name="multi_satuan_nama[]"]')].map(x=>x.value.trim().toUpperCase()).filter(Boolean);
    const satIsi  = [...document.querySelectorAll('input[name="multi_satuan_isi[]"]')].map(x=>toNumber(x.value));
    if (!satNama.length) {
        errs.push('Minimal 1 baris multi satuan wajib diisi.');
        setFieldError('multi_satuan','Minimal 1 satuan');
    } else {
        const dup = satNama.some((v,i)=>satNama.indexOf(v)!==i);
        if (dup) {
            errs.push('Nama satuan tidak boleh duplikat.');
            setFieldError('multi_satuan','Nama satuan duplikat');
        }
        if (satIsi.some(v=>v<=0)) {
            errs.push('Isi satuan harus lebih dari 0.');
            setFieldError('multi_satuan','Isi harus > 0');
        }
    }
    if (errs.length) {
        showFormError(errs[0]);
    }
    return errs.length ? errs[0] : '';
}

function openModal() {
    form.reset();
    clearErrors();
    form.produk_id.value = '';
    document.getElementById('modalTitle').innerText = 'Tambah Produk';
    if(form.pajak_select) form.pajak_select.value = '';
    if(form.pajak_persen) form.pajak_persen.value = 0;
    document.getElementById('supplier_name').value='';
    form.supplier_id.value='';
    document.getElementById('kategori_name').value='';
    form.kategori_id.value='';
    if (form.gudang_id && defaultGudangId > 0) form.gudang_id.value = String(defaultGudangId);
    if (form.stok_awal_harga_modal) form.stok_awal_harga_modal.value = form.harga_modal.value || 0;
    if (form.is_jasa) form.is_jasa.checked = false;
    if (form.is_konsinyasi) form.is_konsinyasi.checked = false;
    if (form.max_stok) form.max_stok.value = 0;
    setMultiSatuan([]);
    applyJasaMode();
    toggleStokAwal(false);
    updatePricePreview();
    modal.style.display = "block";
    if (form.barcode) form.barcode.focus();
}

function closeModal() {
    modal.style.display = "none";
}

async function editBarang(id){
    const r = await fetch('../../../api/produk_get.php?id='+id);
    const d = await r.json();
    if(!d.ok){ showFormError(d.msg || 'Data produk tidak bisa dibuka'); return; }
    
    document.getElementById('modalTitle').innerText = 'Edit Produk';
    clearErrors();
    for (const k in d.data) if (form[k]) form[k].value = d.data[k];
    if(form.satuan) form.satuan.value = d.data.satuan || '';
    if(form.supplier_id){
        form.supplier_id.value = d.data.supplier_id || '';
        document.getElementById('supplier_name').value = (supplierData.find(s=>s.supplier_id==d.data.supplier_id)?.nama_supplier) || '';
    }
    if(form.kategori_id){
        form.kategori_id.value = d.data.kategori_id || '';
        document.getElementById('kategori_name').value = (kategoriData.find(k=>k.kategori_id==d.data.kategori_id)?.nama_kategori) || '';
    }
    // set pajak select by persen
    if(form.pajak_select){
        const target = [...form.pajak_select.options].find(o=>o.value==d.data.pajak_persen);
        form.pajak_select.value = target ? target.value : 'custom';
        if(form.pajak_persen) form.pajak_persen.value = d.data.pajak_persen ?? 0;
    }
    if(form.harga_grosir) form.harga_grosir.value = d.data.harga_grosir ?? 0;
    if(form.harga_reseller) form.harga_reseller.value = d.data.harga_reseller ?? 0;
    if(form.harga_member) form.harga_member.value = d.data.harga_member ?? 0;
    if(form.max_stok) form.max_stok.value = d.data.max_stok ?? 0;
    setMultiSatuan(d.data.multi_satuan || []);
    if(form.is_jasa) form.is_jasa.checked = Number(d.data.is_jasa || 0) === 1;
    if(form.is_konsinyasi) form.is_konsinyasi.checked = Number(d.data.is_konsinyasi || 0) === 1;
    applyJasaMode();
    toggleStokAwal(false);
    updatePricePreview();
    form.aktif.checked = d.data.aktif == 1;
    modal.style.display = "block";
    if (form.barcode) form.barcode.focus();
}

form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const validationMsg = validateBusinessRules();
    if(validationMsg){
        return;
    }
    const fd = new FormData(form);
    fd.set('aktif', form.aktif.checked ? 1 : 0);
    fd.set('is_jasa', document.getElementById('is_jasa')?.checked ? 1 : 0);
    fd.set('is_konsinyasi', document.getElementById('is_konsinyasi')?.checked ? 1 : 0);
    if(!document.getElementById('isi_stok_awal')?.checked){
        fd.set('isi_stok_awal', 0);
        fd.delete('gudang_id');
        fd.delete('stok_awal_qty');
        fd.delete('stok_awal_harga_modal');
    }
    const satNama = [...document.querySelectorAll('input[name="multi_satuan_nama[]"]')];
    const satIsi = [...document.querySelectorAll('input[name="multi_satuan_isi[]"]')];
    fd.delete('multi_satuan_nama[]');
    fd.delete('multi_satuan_isi[]');
    satNama.forEach((n,i)=>{
        const nama = (n.value || '').trim();
        const isi = satIsi[i] ? satIsi[i].value : '';
        if (nama !== '') {
            fd.append('multi_satuan_nama[]', nama);
            fd.append('multi_satuan_isi[]', isi || '1');
        }
    });
    // ensure pajak_persen populated
    if(form.pajak_select){
        if(form.pajak_select.value !== 'custom'){
            fd.set('pajak_persen', form.pajak_select.value || 0);
        } else {
            fd.set('pajak_persen', form.pajak_persen.value || 0);
        }
    }
    
    // UI Feedback
    const btn = form.querySelector('button[type="submit"]');
    btn.innerText = 'Menyimpan...';
    btn.disabled = true;

    try {
        const r = await fetch('../../../api/produk_save.php', {method:'POST', body: fd});
        if(!r.ok) throw new Error('Gagal menyimpan (HTTP '+r.status+')');
        const d = await r.json();
        if(!d.ok) throw new Error(d.msg || 'Gagal menyimpan');
        location.reload();
    } catch(err){
        showFormError(err.message || 'Gagal menyimpan');
    } finally {
        btn.innerText = 'Simpan Produk';
        btn.disabled = false;
    }
});

function exportData() {
    alert('Fitur Export ke Excel sedang disiapkan.');
    // Di sini Anda bisa mengarahkan ke script PHP yang menghasilkan format .xlsx
}

window.onclick = function(event) {
    if (event.target == modal) closeModal();
}

// Pajak select behavior
const pajakSelect = document.getElementById('pajak_select');
const pajakInput = document.getElementById('pajak_persen');
if(pajakSelect){
    pajakSelect.addEventListener('change', ()=>{
        if(pajakSelect.value === 'custom'){
            pajakInput.removeAttribute('disabled');
            pajakInput.value = pajakInput.value || 0;
            pajakInput.type = 'number';
            pajakInput.step = '0.01';
        } else {
            pajakInput.value = pajakSelect.value || 0;
            pajakInput.setAttribute('disabled','disabled');
        }
        updatePricePreview();
    });
}
['harga_modal','harga_ecer','harga_grosir','harga_reseller','harga_member','pajak_persen'].forEach(name=>{
    if (form[name]) form[name].addEventListener('input', updatePricePreview);
});
const jasaCheck = document.getElementById('is_jasa');
if (jasaCheck) jasaCheck.addEventListener('change', applyJasaMode);
const btnGenerateSku = document.getElementById('btnGenerateSku');
if (btnGenerateSku) {
    btnGenerateSku.addEventListener('click', ()=>{
        const base = (form.nama_produk?.value || 'PRD').toUpperCase().replace(/[^A-Z0-9]+/g,'').slice(0,6) || 'PRD';
        const stamp = Date.now().toString().slice(-6);
        form.sku.value = `${base}${stamp}`;
    });
}
const stokAwalCheck = document.getElementById('isi_stok_awal');
if(stokAwalCheck){
    stokAwalCheck.addEventListener('change', ()=>toggleStokAwal());
}
if(form.harga_modal && form.stok_awal_harga_modal){
    form.harga_modal.addEventListener('input', ()=>{
        if(!form.stok_awal_harga_modal.value){
            form.stok_awal_harga_modal.value = form.harga_modal.value || 0;
        }
    });
}

// Supplier lookup modal (same pattern as PO)
const supplierData = <?php echo json_encode($supplier, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const supModal = document.createElement('div');
supModal.className='modal';
supModal.id='supModal';
supModal.innerHTML = `
  <div class="modal-content" style="max-width:460px;display:flex;flex-direction:column;">
    <div class="modal-header">
      <h3 style="margin:0;">Pilih Supplier</h3>
      <span style="cursor:pointer;font-size:22px;" id="supClose">&times;</span>
    </div>
    <div class="modal-body">
      <input type="text" id="supSearch" placeholder="Cari supplier..." style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;">
      <div style="max-height:320px;overflow:auto;margin-top:10px;">
        <table style="width:100%;border-collapse:collapse;">
          <thead><tr><th style="text-align:left;padding:8px;">Nama</th></tr></thead>
          <tbody id="supBody"></tbody>
        </table>
      </div>
    </div>
  </div>`;
document.body.appendChild(supModal);

function openSup(){ document.getElementById('supSearch').value=''; renderSup(); supModal.style.display='flex'; }
function closeSup(){ supModal.style.display='none'; }
document.getElementById('supClose').onclick=closeSup;
window.addEventListener('click',(e)=>{ if(e.target===supModal) closeSup(); });

function renderSup(){
    const term = document.getElementById('supSearch').value.toLowerCase();
    const body = document.getElementById('supBody');
    body.innerHTML='';
    supplierData.filter(s=>(s.nama_supplier||'').toLowerCase().includes(term)).slice(0,100).forEach(s=>{
        const tr=document.createElement('tr');
        tr.innerHTML=`<td style="padding:8px;cursor:pointer;">${s.nama_supplier}</td>`;
        tr.onclick=()=>{
            form.supplier_id.value = s.supplier_id;
            document.getElementById('supplier_name').value = s.nama_supplier;
            closeSup();
        };
        body.appendChild(tr);
    });
}

// Kategori lookup modal
const kategoriData = <?php echo json_encode($kategori, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const katModal = document.createElement('div');
katModal.className='modal';
katModal.id='katModal';
katModal.innerHTML = `
  <div class="modal-content" style="max-width:460px;display:flex;flex-direction:column;">
    <div class="modal-header">
      <h3 style="margin:0;">Pilih Kategori</h3>
      <span style="cursor:pointer;font-size:22px;" id="katClose">&times;</span>
    </div>
    <div class="modal-body">
      <input type="text" id="katSearch" placeholder="Cari kategori..." style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;">
      <div style="max-height:320px;overflow:auto;margin-top:10px;">
        <table style="width:100%;border-collapse:collapse;">
          <thead><tr><th style="text-align:left;padding:8px;">Nama</th></tr></thead>
          <tbody id="katBody"></tbody>
        </table>
      </div>
    </div>
  </div>`;
document.body.appendChild(katModal);
function openKat(){ document.getElementById('katSearch').value=''; renderKat(); katModal.style.display='flex'; }
function closeKat(){ katModal.style.display='none'; }
document.getElementById('katClose').onclick=closeKat;
window.addEventListener('click',(e)=>{ if(e.target===katModal) closeKat(); });
function renderKat(){
    const term = document.getElementById('katSearch').value.toLowerCase();
    const body = document.getElementById('katBody');
    body.innerHTML='';
    kategoriData.filter(k=>(k.nama_kategori||'').toLowerCase().includes(term)).slice(0,100).forEach(k=>{
        const tr=document.createElement('tr');
        tr.innerHTML=`<td style="padding:8px;cursor:pointer;">${k.nama_kategori}</td>`;
        tr.onclick=()=>{
            form.kategori_id.value = k.kategori_id;
            document.getElementById('kategori_name').value = k.nama_kategori;
            closeKat();
        };
        body.appendChild(tr);
    });
}
</script>
</html>


