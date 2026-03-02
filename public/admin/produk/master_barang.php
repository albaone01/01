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
$supplierId = $_GET['supplier'] ?? '';
$status = $_GET['status'] ?? '';
$low    = $_GET['low'] ?? '';
$page   = $_GET['page'] ?? 1;
$limit  = 50;
$offset = ($page - 1) * $limit;
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
$stmt = $db->prepare("SELECT supplier_id,nama_supplier FROM supplier WHERE toko_id=? AND deleted_at IS NULL ORDER BY nama_supplier");
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
$where  = ["p.toko_id = ?", "p.deleted_at IS NULL"];
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
if ($supplierId !== '') {
    $where[] = "p.supplier_id = ?";
    $types  .= "i";
    $params[] = (int)$supplierId;
}
if ($status !== '') {
    $where[] = "p.aktif = ?";
    $types  .= "i";
    $params[] = (int)$status;
}

// Hitung total untuk load more
$countSql = "
SELECT COUNT(DISTINCT p.produk_id) as total
FROM produk p
LEFT JOIN kategori_produk k ON k.kategori_id = p.kategori_id
LEFT JOIN stok_gudang sg ON sg.produk_id = p.produk_id
WHERE " . implode(' AND ', $where);
$stmt = $db->prepare($countSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totalResult = $stmt->get_result()->fetch_assoc();
$totalProduk = $totalResult['total'] ?? 0;
$stmt->close();

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
LIMIT ? OFFSET ?";

$types .= "ii";
array_push($params, $limit, $offset);

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

$aktifProduk = array_sum(array_column($items, 'aktif'));
$nilaiStok   = 0;
$lowStock    = 0;
foreach ($items as $it) {
    $nilaiStok += (float)$it['stok_total'] * (float)$it['harga_modal'];
    if ((int)$it['stok_total'] <= (int)$it['min_stok']) $lowStock++;
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --bg: #f9fafb;
        --card: #ffffff;
        --text: #1e293b;
        --text-light: #64748b;
        --border: #e2e8f0;
        --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--bg);
        color: var(--text);
    }

    .page {
        padding: 16px;
        max-width: 1440px;
        margin: 0 auto;
    }

    /* Header Section */
    .header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .header-title h1 {
        font-size: 24px;
        font-weight: 600;
        color: var(--text);
    }

    .header-title p {
        color: var(--text-light);
        font-size: 14px;
        margin-top: 4px;
    }

    .header-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    /* Buttons */
    .btn {
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 14px;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-primary {
        background: var(--primary);
        color: white;
        box-shadow: var(--shadow);
    }

    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 6px 10px -1px rgb(0 0 0 / 0.15);
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    .btn-outline {
        background: white;
        border-color: var(--border);
        color: var(--text);
    }

    .btn-outline:hover {
        background: var(--bg);
        border-color: #cbd5e1;
    }

    .btn-icon {
        padding: 8px;
        border-radius: 8px;
        background: white;
        border: 1px solid var(--border);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-icon:hover {
        background: var(--bg);
        border-color: #cbd5e1;
    }

    /* Metric Cards - Minimal */
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }

    .metric-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 12px;
        transition: all 0.2s ease;
    }

    .metric-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow);
    }

    .metric-label {
        font-size: 12px;
        font-weight: 500;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .metric-value {
        font-size: 20px;
        font-weight: 600;
        color: var(--text);
        margin-top: 4px;
    }

    .metric-trend {
        font-size: 11px;
        color: var(--text-light);
        margin-top: 4px;
    }

    /* Search Bar */
    .search-container {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 16px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .search-box {
        flex: 1;
        min-width: 280px;
        display: flex;
        align-items: center;
        gap: 8px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 0 8px;
    }

    .search-box i {
        color: var(--text-light);
        font-size: 16px;
    }

    .search-box input {
        flex: 1;
        padding: 8px 0;
        border: none;
        outline: none;
        font-size: 14px;
        background: transparent;
    }

    .filter-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .filter-select {
        position: relative;
        min-width: 160px;
    }

    .filter-select select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        background: white;
        cursor: pointer;
        outline: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
    }

    .filter-select select:hover {
        border-color: var(--primary);
    }

    .status-pills {
        display: flex;
        gap: 4px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 3px;
    }

    .status-pill {
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        background: transparent;
        color: var(--text-light);
        border: none;
    }

    .status-pill.active {
        background: var(--primary);
        color: white;
    }

    /* Table */
    .table-container {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    th {
        text-align: left;
        padding: 10px 8px;
        background: #f8fafc;
        color: var(--text-light);
        font-weight: 600;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    td {
        padding: 8px;
        border-bottom: 1px solid #f1f5f9;
        color: var(--text);
    }

    tr:hover td {
        background: #f8fafc;
    }

    .product-name {
        font-weight: 500;
        margin-bottom: 2px;
    }

    .product-meta {
        font-size: 11px;
        color: var(--text-light);
    }

    /* Badges */
    .badge {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-warning {
        background: #fed7aa;
        color: #92400e;
    }

    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-muted {
        background: #f1f5f9;
        color: var(--text-light);
    }

    .badge-active {
        background: #dbeafe;
        color: #1e40af;
    }

    /* Action Buttons */
    .action-btn {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 12px;
        background: white;
        border: 1px solid var(--border);
        cursor: pointer;
        transition: all 0.2s ease;
        color: var(--text);
    }

    .action-btn:hover {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    /* Load More */
    .load-more {
        padding: 12px;
        text-align: center;
        border-top: 1px solid var(--border);
    }

    .load-more-btn {
        padding: 8px 16px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        color: var(--text);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .load-more-btn:hover {
        background: var(--bg);
        border-color: #cbd5e1;
    }

    .loading-spinner {
        display: none;
        width: 20px;
        height: 20px;
        border: 2px solid var(--border);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
        align-items: center;
        justify-content: center;
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 700px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1);
    }

    .modal-header {
        padding: 16px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
    }

    .modal-header h2 {
        font-size: 18px;
        font-weight: 600;
    }

    .close-btn {
        font-size: 24px;
        cursor: pointer;
        color: var(--text-light);
        transition: color 0.2s ease;
    }

    .close-btn:hover {
        color: var(--text);
    }

    .modal-body {
        padding: 16px;
    }

    /* Form */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .full-width {
        grid-column: 1 / -1;
    }

    .form-group {
        margin-bottom: 12px;
    }

    .form-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 4px;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 13px;
        outline: none;
        transition: all 0.2s ease;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .form-group input[type="checkbox"] {
        width: auto;
        margin-right: 8px;
    }

    /* Harga Group */
    .harga-group {
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 12px;
    }

    .harga-group-title {
        font-weight: 600;
        font-size: 13px;
        color: var(--text);
        margin-bottom: 8px;
    }

    .harga-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .harga-item label {
        font-size: 11px;
        color: var(--text-light);
        display: block;
        margin-bottom: 2px;
    }

    .harga-item input {
        padding: 6px 8px;
        font-size: 12px;
    }

    /* Stok Awal Group */
    .stok-awal-group {
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 12px;
        margin-top: 12px;
    }

    .stok-awal-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-top: 8px;
    }

    /* Multi Satuan */
    .multi-satuan-container {
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 12px;
        margin-top: 12px;
    }

    .sat-row {
        display: grid;
        grid-template-columns: 1fr 100px 30px;
        gap: 8px;
        margin-bottom: 8px;
        align-items: center;
    }

    .sat-row input {
        padding: 6px 8px;
    }

    .remove-sat {
        padding: 6px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .remove-sat:hover {
        background: #fee2e2;
        border-color: var(--danger);
        color: var(--danger);
    }

    .add-sat-btn {
        padding: 6px 12px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .add-sat-btn:hover {
        background: var(--bg);
        border-color: var(--primary);
        color: var(--primary);
    }

    .modal-footer {
        padding: 16px;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        position: sticky;
        bottom: 0;
        background: white;
        z-index: 10;
    }

    /* Error States */
    .field-error {
        color: var(--danger);
        font-size: 11px;
        margin-top: 2px;
        display: block;
    }

    .input-invalid {
        border-color: var(--danger) !important;
    }

    .error-banner {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 13px;
        margin-bottom: 12px;
        display: none;
    }

    .error-banner.show {
        display: block;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .metrics-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 768px) {
        .metrics-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .search-container {
            flex-direction: column;
        }
        
        .filter-group {
            flex-direction: column;
        }
        
        .filter-select {
            width: 100%;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .harga-grid {
            grid-template-columns: 1fr;
        }
        
        .stok-awal-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .metrics-grid {
            grid-template-columns: 1fr;
        }
        
        .header-section {
            flex-direction: column;
            gap: 12px;
            align-items: flex-start;
        }
    }
</style>

<div class="page">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-title">
            <h1>Master Barang</h1>
            <p>Kelola data produk dan stok</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-icon" onclick="exportData()" title="Export Excel">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
            </button>
            <button class="btn btn-primary" onclick="openModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Tambah Produk
            </button>
        </div>
    </div>

    <!-- Metric Cards - Minimal -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-label">Total SKU</div>
            <div class="metric-value"><?=number_format($totalProduk)?></div>
            <div class="metric-trend"><?=$aktifProduk?> aktif</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Nilai Stok</div>
            <div class="metric-value"><?=formatRupiah($nilaiStok)?></div>
            <div class="metric-trend">Harga modal</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Stok Rendah</div>
            <div class="metric-value"><?=$lowStock?></div>
            <div class="metric-trend">Perlu restock</div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="search-container">
        <div class="search-box">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="searchInput" placeholder="Cari nama, SKU, atau barcode..." value="<?=htmlspecialchars($q)?>" autocomplete="off">
        </div>
        <div class="filter-group">
            <div class="filter-select">
                <select id="kategoriFilter">
                    <option value="">Semua Kategori</option>
                    <?php foreach($kategori as $k): ?>
                        <option value="<?=$k['kategori_id']?>" <?=$kat==$k['kategori_id']?'selected':''?>>
                            <?=htmlspecialchars($k['nama_kategori'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-select">
                <select id="supplierFilter">
                    <option value="">Semua Supplier</option>
                    <?php foreach($supplier as $s): ?>
                        <option value="<?=$s['supplier_id']?>" <?=((string)$supplierId === (string)$s['supplier_id']) ? 'selected' : ''?>>
                            <?=htmlspecialchars($s['nama_supplier'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="status-pills">
                <button class="status-pill <?=$status===''?'active':''?>" onclick="setStatus('')">Semua</button>
                <button class="status-pill <?=$status==='1'?'active':''?>" onclick="setStatus('1')">Aktif</button>
                <button class="status-pill <?=$status==='0'?'active':''?>" onclick="setStatus('0')">Nonaktif</button>
                <button class="status-pill <?=$low==='1'?'active':''?>" onclick="setLowStock()">Stok Rendah</button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Kategori</th>
                    <th>Supplier</th>
                    <th>Harga Modal</th>
                    <th>Harga Jual</th>
                    <th>Stok</th>
                    <th>Satuan</th>
                    <th>Status</th>
                    <th style="text-align:right">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php foreach($items as $it):
                    $isLowStock = $it['stok_total'] <= $it['min_stok'];
                ?>
                <tr data-id="<?=$it['produk_id']?>">
                    <td>
                        <div class="product-name"><?=htmlspecialchars($it['nama_produk'])?></div>
                        <div class="product-meta"><?=htmlspecialchars($it['sku'] ?: '-')?></div>
                    </td>
                    <td><?=htmlspecialchars($it['nama_kategori'] ?? '-')?></td>
                    <td><?=htmlspecialchars($it['nama_supplier'] ?? '-')?></td>
                    <td><?=formatRupiah($it['harga_modal'])?></td>
                    <td><strong><?=formatRupiah($harga[$it['produk_id']]['ecer'] ?? 0)?></strong></td>
                    <td>
                        <span class="badge <?=$isLowStock ? 'badge-warning' : 'badge-muted'?>">
                            <?=$it['stok_total']?>
                        </span>
                    </td>
                    <td><?=htmlspecialchars($it['satuan'])?></td>
                    <td>
                        <span class="badge <?=$it['aktif'] ? 'badge-success' : 'badge-danger'?>">
                            <?=$it['aktif'] ? 'Aktif' : 'Nonaktif'?>
                        </span>
                    </td>
                    <td style="text-align:right">
                        <button class="action-btn" onclick="editBarang(<?=$it['produk_id']?>)">Edit</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if($totalProduk > count($items)): ?>
        <div class="load-more">
            <button class="load-more-btn" id="loadMoreBtn" onclick="loadMore()">
                <span id="loadMoreText">Muat lebih banyak</span>
                <span id="loadMoreSpinner" class="loading-spinner"></span>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Tambah Produk</h2>
            <span class="close-btn" onclick="closeModal()">&times;</span>
        </div>
        <form id="barangForm">
            <div class="modal-body">
                <input type="hidden" name="produk_id">
                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
                <div id="formErrorBanner" class="error-banner"></div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Kode SKU <span style="color:var(--danger);">*</span></label>
                        <input type="text" name="sku" required placeholder="Contoh: PRD001">
                        <button type="button" class="add-sat-btn" style="margin-top:4px;" onclick="generateSku()">Auto Generate</button>
                        <small class="field-error" data-for="sku"></small>
                    </div>
                    
                    <div class="form-group">
                        <label>Barcode</label>
                        <input type="text" name="barcode" placeholder="Scan barcode">
                        <small class="field-error" data-for="barcode"></small>
                    </div>
                    
                    <div class="full-width form-group">
                        <label>Nama Produk <span style="color:var(--danger);">*</span></label>
                        <input type="text" name="nama_produk" required placeholder="Contoh: Kopi Susu Gula Aren">
                        <small class="field-error" data-for="nama_produk"></small>
                    </div>
                    
                    <div class="form-group">
                        <label>Supplier <span style="color:var(--danger);">*</span></label>
                        <select name="supplier_id" id="supplier_id" required>
                            <option value="">Pilih Supplier</option>
                            <?php foreach($supplier as $s): ?>
                                <option value="<?=$s['supplier_id']?>"><?=htmlspecialchars($s['nama_supplier'])?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-error" data-for="supplier_id"></small>
                    </div>
                    
                    <div class="form-group">
                        <label>Kategori <span style="color:var(--danger);">*</span></label>
                        <select name="kategori_id" id="kategori_id" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach($kategori as $k): ?>
                                <option value="<?=$k['kategori_id']?>"><?=htmlspecialchars($k['nama_kategori'])?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-error" data-for="kategori_id"></small>
                    </div>
                    
                    <div class="form-group">
                        <label>Satuan Dasar <span style="color:var(--danger);">*</span></label>
                        <input type="text" name="satuan" required placeholder="Contoh: PCS">
                        <small class="field-error" data-for="satuan"></small>
                    </div>
                    
                    <!-- Harga Group -->
                    <div class="full-width harga-group">
                        <div class="harga-group-title">💰 Harga</div>
                        <div class="harga-grid">
                            <div class="harga-item">
                                <label>Harga Modal</label>
                                <input type="number" name="harga_modal" min="0" step="100" required>
                                <small class="field-error" data-for="harga_modal"></small>
                            </div>
                            <div class="harga-item">
                                <label>Harga Ecer</label>
                                <input type="number" name="harga_ecer" min="0" step="100" required>
                                <small class="field-error" data-for="harga_ecer"></small>
                            </div>
                            <div class="harga-item">
                                <label>Harga Grosir</label>
                                <input type="number" name="harga_grosir" min="0" step="100" required>
                                <small class="field-error" data-for="harga_grosir"></small>
                            </div>
                            <div class="harga-item">
                                <label>Harga Reseller</label>
                                <input type="number" name="harga_reseller" min="0" step="100" required>
                                <small class="field-error" data-for="harga_reseller"></small>
                            </div>
                            <div class="harga-item">
                                <label>Harga Member</label>
                                <input type="number" name="harga_member" min="0" step="100" required>
                                <small class="field-error" data-for="harga_member"></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Min Stok</label>
                        <input type="number" name="min_stok" min="0" value="0">
                        <small class="field-error" data-for="min_stok"></small>
                    </div>
                    
                    <div class="form-group">
                        <label>Max Stok</label>
                        <input type="number" name="max_stok" min="0" value="0">
                        <small class="field-error" data-for="max_stok"></small>
                    </div>
                    
                    <!-- Multi Satuan -->
                    <div class="full-width multi-satuan-container">
                        <div class="harga-group-title">📦 Multi Satuan</div>
                        <div id="multiSatuanList"></div>
                        <button type="button" class="add-sat-btn" onclick="addSatuanRow()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Tambah Satuan
                        </button>
                        <small class="field-error" data-for="multi_satuan"></small>
                    </div>
                    
                    <!-- Stok Awal Group -->
                    <div class="full-width stok-awal-group">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="isi_stok_awal" id="isi_stok_awal" value="1" style="width:auto;">
                            <label for="isi_stok_awal" style="margin:0;">Isi Stok Awal</label>
                        </div>
                        
                        <div id="stokAwalFields" style="display:none;">
                            <div class="stok-awal-grid">
                                <div>
                                    <label>Gudang</label>
                                    <select name="gudang_id" id="stok_awal_gudang">
                                        <option value="">Pilih Gudang</option>
                                        <?php foreach($gudangList as $g): ?>
                                            <option value="<?=$g['gudang_id']?>" <?=$defaultGudangId===(int)$g['gudang_id']?'selected':''?>>
                                                <?=htmlspecialchars($g['nama_gudang'])?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label>Qty</label>
                                    <input type="number" name="stok_awal_qty" id="stok_awal_qty" min="1" step="1" value="1">
                                </div>
                                <div>
                                    <label>Harga Modal</label>
                                    <input type="number" name="stok_awal_harga_modal" id="stok_awal_harga_modal" min="0" step="100">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Pajak (%)</label>
                        <select name="pajak_select" id="pajak_select">
                            <option value="0">Tanpa Pajak</option>
                            <?php foreach($pajakList as $p): ?>
                                <option value="<?=$p['persen']?>"><?=htmlspecialchars($p['nama'])?> (<?=$p['persen']?>%)</option>
                            <?php endforeach; ?>
                            <option value="custom">Custom</option>
                        </select>
                        <input type="number" name="pajak_persen" id="pajak_persen" step="0.01" min="0" max="100" value="0" style="margin-top:4px; display:none;">
                    </div>
                    
                    <div class="form-group" style="display:flex; align-items:center; gap:16px;">
                        <label style="display:flex; align-items:center; gap:4px;">
                            <input type="checkbox" name="is_jasa" id="is_jasa" value="1"> Produk Jasa
                        </label>
                        <label style="display:flex; align-items:center; gap:4px;">
                            <input type="checkbox" name="is_konsinyasi" id="is_konsinyasi" value="1"> Konsinyasi
                        </label>
                        <label style="display:flex; align-items:center; gap:4px;">
                            <input type="checkbox" name="aktif" value="1" checked> Aktif
                        </label>
                    </div>
                    
                    <div class="full-width form-group">
                        <label>Foto Produk</label>
                        <input type="file" name="foto" accept="image/*">
                        <small class="field-error" data-for="foto"></small>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan Produk</button>
            </div>
        </form>
    </div>
</div>

<script>
// Data untuk lookup
const supplierData = <?php echo json_encode($supplier, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const kategoriData = <?php echo json_encode($kategori, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const gudangData = <?php echo json_encode($gudangList, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const defaultGudangId = <?=(int)$defaultGudangId?>;
const satuanMaster = <?php echo json_encode(array_column($satuanList, 'nama'), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
let currentPage = <?=$page?>;
let totalProducts = <?=$totalProduk?>;
let isLoading = false;

// Modal elements
const modal = document.getElementById('productModal');
const form = document.getElementById('barangForm');
const stokAwalCheck = document.getElementById('isi_stok_awal');
const stokAwalFields = document.getElementById('stokAwalFields');
const pajakSelect = document.getElementById('pajak_select');
const pajakInput = document.getElementById('pajak_persen');
const isJasaCheck = document.getElementById('is_jasa');

// ==================== UTILITY FUNCTIONS ====================
function toNumber(v) {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
}

function formatIdr(v) {
    return new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(toNumber(v))));
}

function clearErrors() {
    document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
    document.querySelectorAll('#barangForm .input-invalid').forEach(el => el.classList.remove('input-invalid'));
    const banner = document.getElementById('formErrorBanner');
    banner.classList.remove('show');
    banner.textContent = '';
}

function setFieldError(fieldName, msg) {
    const err = form.querySelector(`.field-error[data-for="${fieldName}"]`);
    if (err) err.textContent = msg;
    const target = form[fieldName];
    if (target) target.classList.add('input-invalid');
}

function showFormError(msg) {
    const banner = document.getElementById('formErrorBanner');
    banner.textContent = msg;
    banner.classList.add('show');
}

// ==================== MODAL FUNCTIONS ====================
function openModal() {
    form.reset();
    clearErrors();
    form.produk_id.value = '';
    document.getElementById('modalTitle').innerText = 'Tambah Produk';
    
    // Reset form
    if (pajakSelect) pajakSelect.value = '0';
    if (pajakInput) {
        pajakInput.value = 0;
        pajakInput.style.display = 'none';
    }
    
    // Set default gudang
    if (defaultGudangId > 0) {
        document.getElementById('stok_awal_gudang').value = defaultGudangId;
    }
    
    // Reset multi satuan
    document.getElementById('multiSatuanList').innerHTML = '';
    addSatuanRow();
    
    // Hide stok awal
    stokAwalFields.style.display = 'none';
    stokAwalCheck.checked = false;
    
    modal.classList.add('show');
    form.sku.focus();
}

function closeModal() {
    modal.classList.remove('show');
}

// ==================== SEARCH & FILTER ====================
let searchTimeout;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage = 1;
        performSearch();
    }, 500);
});

document.getElementById('kategoriFilter').addEventListener('change', performSearch);
document.getElementById('supplierFilter').addEventListener('change', performSearch);

function setStatus(status) {
    const url = new URL(window.location.href);
    if (status === '') {
        url.searchParams.delete('status');
    } else {
        url.searchParams.set('status', status);
    }
    url.searchParams.delete('low');
    window.location.href = url.toString();
}

function setLowStock() {
    const url = new URL(window.location.href);
    url.searchParams.set('low', '1');
    url.searchParams.delete('status');
    window.location.href = url.toString();
}

function performSearch() {
    const url = new URL(window.location.href);
    const searchTerm = document.getElementById('searchInput').value;
    const kategori = document.getElementById('kategoriFilter').value;
    const supplier = document.getElementById('supplierFilter').value;
    
    if (searchTerm) {
        url.searchParams.set('q', searchTerm);
    } else {
        url.searchParams.delete('q');
    }
    
    if (kategori) {
        url.searchParams.set('kat', kategori);
    } else {
        url.searchParams.delete('kat');
    }
    
    if (supplier) {
        url.searchParams.set('supplier', supplier);
    } else {
        url.searchParams.delete('supplier');
    }
    
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

// ==================== LOAD MORE ====================
function loadMore() {
    if (isLoading) return;
    
    isLoading = true;
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const loadMoreText = document.getElementById('loadMoreText');
    const loadMoreSpinner = document.getElementById('loadMoreSpinner');
    
    loadMoreText.style.display = 'none';
    loadMoreSpinner.style.display = 'inline-block';
    loadMoreBtn.disabled = true;
    
    const nextPage = currentPage + 1;
    const url = new URL(window.location.href);
    url.searchParams.set('page', nextPage);
    
    fetch(url.toString(), {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newRows = doc.querySelectorAll('#tableBody tr');
        const tableBody = document.getElementById('tableBody');
        
        newRows.forEach(row => {
            tableBody.appendChild(row.cloneNode(true));
        });
        
        currentPage = nextPage;
        
        // Hide load more if no more data
        if (currentPage * 50 >= totalProducts) {
            document.querySelector('.load-more').style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error loading more:', error);
        alert('Gagal memuat data. Silakan coba lagi.');
    })
    .finally(() => {
        isLoading = false;
        loadMoreText.style.display = 'inline';
        loadMoreSpinner.style.display = 'none';
        loadMoreBtn.disabled = false;
    });
}

// ==================== PRODUCT CRUD ====================
async function editBarang(id) {
    try {
        const response = await fetch(`../../../api/produk_get.php?id=${id}`);
        const data = await response.json();
        
        if (!data.ok) {
            throw new Error(data.msg || 'Gagal memuat data');
        }
        
        document.getElementById('modalTitle').innerText = 'Edit Produk';
        clearErrors();
        
        // Fill form
        const product = data.data;
        for (const key in product) {
            if (form[key]) {
                form[key].value = product[key];
            }
        }
        
        // Set selects
        if (form.supplier_id) form.supplier_id.value = product.supplier_id || '';
        if (form.kategori_id) form.kategori_id.value = product.kategori_id || '';
        
        // Set pajak
        const pajakPersen = parseFloat(product.pajak_persen || 0);
        if (pajakPersen > 0) {
            const optionExists = [...pajakSelect.options].some(opt => opt.value == pajakPersen);
            if (optionExists) {
                pajakSelect.value = pajakPersen;
                pajakInput.style.display = 'none';
            } else {
                pajakSelect.value = 'custom';
                pajakInput.style.display = 'block';
                pajakInput.value = pajakPersen;
            }
        } else {
            pajakSelect.value = '0';
            pajakInput.style.display = 'none';
        }
        
        // Set checkboxes
        form.aktif.checked = product.aktif == 1;
        document.getElementById('is_jasa').checked = product.is_jasa == 1;
        document.getElementById('is_konsinyasi').checked = product.is_konsinyasi == 1;
        
        // Set multi satuan
        const multiSatuan = product.multi_satuan || [];
        document.getElementById('multiSatuanList').innerHTML = '';
        if (multiSatuan.length > 0) {
            multiSatuan.forEach(s => addSatuanRow(s.nama_satuan, s.qty_dasar));
        } else {
            addSatuanRow(product.satuan, '1');
        }
        
        // Handle jasa mode
        applyJasaMode();
        
        modal.classList.add('show');
        
    } catch (error) {
        alert('Gagal memuat data produk: ' + error.message);
    }
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Validate form
    const validationMsg = validateForm();
    if (validationMsg) {
        showFormError(validationMsg);
        return;
    }
    
    // Submit form
    const formData = new FormData(form);
    formData.set('aktif', form.aktif.checked ? 1 : 0);
    formData.set('is_jasa', document.getElementById('is_jasa').checked ? 1 : 0);
    formData.set('is_konsinyasi', document.getElementById('is_konsinyasi').checked ? 1 : 0);
    
    // Handle stok awal
    if (!stokAwalCheck.checked) {
        formData.delete('gudang_id');
        formData.delete('stok_awal_qty');
        formData.delete('stok_awal_harga_modal');
    }
    
    // Handle multi satuan
    const satRows = document.querySelectorAll('.sat-row');
    formData.delete('multi_satuan_nama[]');
    formData.delete('multi_satuan_isi[]');
    
    satRows.forEach(row => {
        const nama = row.querySelector('input[name="multi_satuan_nama[]"]').value.trim();
        const isi = row.querySelector('input[name="multi_satuan_isi[]"]').value;
        if (nama) {
            formData.append('multi_satuan_nama[]', nama);
            formData.append('multi_satuan_isi[]', isi || '1');
        }
    });
    
    // Handle pajak
    if (pajakSelect.value === 'custom') {
        formData.set('pajak_persen', pajakInput.value || 0);
    } else {
        formData.set('pajak_persen', pajakSelect.value || 0);
    }
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Menyimpan...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('../../../api/produk_save.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.ok) {
            window.location.reload();
        } else {
            throw new Error(result.msg || 'Gagal menyimpan');
        }
    } catch (error) {
        showFormError(error.message);
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});

function validateForm() {
    clearErrors();
    
    const errors = [];
    
    // Required fields
    if (!form.sku.value.trim()) {
        errors.push('SKU wajib diisi');
        setFieldError('sku', 'Wajib diisi');
    }
    
    if (!form.nama_produk.value.trim()) {
        errors.push('Nama produk wajib diisi');
        setFieldError('nama_produk', 'Wajib diisi');
    }
    
    if (!form.supplier_id.value) {
        errors.push('Supplier wajib dipilih');
        setFieldError('supplier_id', 'Wajib dipilih');
    }
    
    if (!form.kategori_id.value) {
        errors.push('Kategori wajib dipilih');
        setFieldError('kategori_id', 'Wajib dipilih');
    }
    
    if (!form.satuan.value.trim()) {
        errors.push('Satuan wajib diisi');
        setFieldError('satuan', 'Wajib diisi');
    }
    
    // Harga validations
    const hargaModal = toNumber(form.harga_modal.value);
    const hargaEcer = toNumber(form.harga_ecer.value);
    
    if (hargaModal < 0) {
        errors.push('Harga modal tidak boleh negatif');
        setFieldError('harga_modal', 'Tidak boleh negatif');
    }
    
    if (hargaEcer < hargaModal) {
        errors.push('Harga ecer tidak boleh lebih kecil dari harga modal');
        setFieldError('harga_ecer', 'Harus >= harga modal');
    }
    
    // Stok validations
    const minStok = parseInt(form.min_stok.value || '0');
    const maxStok = parseInt(form.max_stok.value || '0');
    
    if (minStok < 0) {
        errors.push('Min stok tidak boleh negatif');
        setFieldError('min_stok', 'Tidak boleh negatif');
    }
    
    if (maxStok < 0) {
        errors.push('Max stok tidak boleh negatif');
        setFieldError('max_stok', 'Tidak boleh negatif');
    }
    
    if (maxStok > 0 && maxStok < minStok) {
        errors.push('Max stok tidak boleh lebih kecil dari min stok');
        setFieldError('max_stok', 'Harus >= min stok');
    }
    
    // Multi satuan validation
    const satNama = [...document.querySelectorAll('input[name="multi_satuan_nama[]"]')]
        .map(x => x.value.trim().toUpperCase())
        .filter(Boolean);
    
    if (satNama.length === 0) {
        errors.push('Minimal satu satuan harus diisi');
        setFieldError('multi_satuan', 'Minimal satu satuan');
    }
    
    const satDuplicates = satNama.some((v, i) => satNama.indexOf(v) !== i);
    if (satDuplicates) {
        errors.push('Nama satuan tidak boleh duplikat');
        setFieldError('multi_satuan', 'Nama satuan duplikat');
    }
    
    const satIsi = [...document.querySelectorAll('input[name="multi_satuan_isi[]"]')]
        .map(x => toNumber(x.value));
    
    if (satIsi.some(v => v <= 0)) {
        errors.push('Isi satuan harus lebih dari 0');
        setFieldError('multi_satuan', 'Isi harus > 0');
    }
    
    // Stok awal validation
    if (stokAwalCheck.checked && !document.getElementById('is_jasa').checked) {
        if (!document.getElementById('stok_awal_gudang').value) {
            errors.push('Pilih gudang untuk stok awal');
        }
        
        const qty = parseInt(document.getElementById('stok_awal_qty').value);
        if (qty <= 0) {
            errors.push('Qty stok awal harus lebih dari 0');
        }
    }
    
    return errors.length ? errors[0] : '';
}

// ==================== UI HELPERS ====================
function generateSku() {
    const nama = form.nama_produk.value || 'PRD';
    const prefix = nama.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3) || 'PRD';
    const timestamp = Date.now().toString().slice(-6);
    form.sku.value = `${prefix}${timestamp}`;
}

function addSatuanRow(nama = '', isi = '1') {
    const container = document.getElementById('multiSatuanList');
    const row = document.createElement('div');
    row.className = 'sat-row';
    
    row.innerHTML = `
        <input type="text" name="multi_satuan_nama[]" placeholder="Nama Satuan" value="${nama}" list="satuanDatalist">
        <input type="number" name="multi_satuan_isi[]" min="0.0001" step="0.0001" value="${isi}" placeholder="Isi">
        <button type="button" class="remove-sat" onclick="this.parentElement.remove()">✕</button>
    `;
    
    container.appendChild(row);
}

function applyJasaMode() {
    const isJasa = isJasaCheck.checked;
    
    if (isJasa) {
        form.min_stok.value = 0;
        form.max_stok.value = 0;
        stokAwalCheck.checked = false;
        stokAwalFields.style.display = 'none';
    }
    
    form.min_stok.disabled = isJasa;
    form.max_stok.disabled = isJasa;
    stokAwalCheck.disabled = isJasa;
}

// Event listeners
stokAwalCheck.addEventListener('change', function() {
    stokAwalFields.style.display = this.checked ? 'block' : 'none';
    if (this.checked && !form.stok_awal_harga_modal.value) {
        form.stok_awal_harga_modal.value = form.harga_modal.value || 0;
    }
});

pajakSelect.addEventListener('change', function() {
    if (this.value === 'custom') {
        pajakInput.style.display = 'block';
    } else {
        pajakInput.style.display = 'none';
        pajakInput.value = this.value || 0;
    }
});

isJasaCheck.addEventListener('change', applyJasaMode);

// Initialize
if (!document.getElementById('satuanDatalist')) {
    const datalist = document.createElement('datalist');
    datalist.id = 'satuanDatalist';
    satuanMaster.forEach(sat => {
        const option = document.createElement('option');
        option.value = sat;
        datalist.appendChild(option);
    });
    document.body.appendChild(datalist);
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === modal) {
        closeModal();
    }
});

// Export function
function exportData() {
    const url = new URL(window.location.href);
    url.pathname = url.pathname.replace('produk', 'export_produk');
    window.location.href = url.toString();
}
</script>
</html>
