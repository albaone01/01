<?php
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';
require_once '../../inc/header.php';
require_once '../../inc/csrf.php';

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if (!$tokoId) die('Sesi toko tidak valid');
$csrfToken = csrf_token();

function fetch_all_stmt(mysqli_stmt $stmt): array {
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

// Data Gudang
$stmt = $pos_db->prepare("SELECT gudang_id,nama_gudang FROM gudang WHERE toko_id=? AND aktif=1 AND deleted_at IS NULL ORDER BY nama_gudang");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$gudangList = fetch_all_stmt($stmt);
$stmt->close();

// Data Produk
$stmt = $pos_db->prepare("SELECT produk_id,nama_produk,sku FROM produk WHERE toko_id=? AND deleted_at IS NULL ORDER BY nama_produk LIMIT 1000");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$produkList = fetch_all_stmt($stmt);
$stmt->close();
?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

<style>
    :root {
        --primary: #2563eb;
        --dark: #0f172a;
        --border: #e2e8f0;
        --bg-light: #f8fafc;
        --text-muted: #64748b;
        --radius: 12px;
    }

    body { 
        font-family: 'Inter', -apple-system, sans-serif; 
        background: #f1f5f9; 
        margin: 0; 
        color: var(--dark);
    }

    .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

    /* Header & Action Bar */
    .header-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        background: #fff;
        padding: 12px 20px;
        border-radius: var(--radius);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .header-title h1 { font-size: 18px; margin: 0; font-weight: 700; letter-spacing: -0.5px; }

    .header-actions { display: flex; gap: 8px; align-items: center; }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid var(--border);
        background: #fff;
        transition: 0.2s;
        color: var(--dark);
    }
    .btn:hover { background: var(--bg-light); border-color: var(--text-muted); }
    .btn-primary { background: var(--primary); color: #fff; border: none; }
    .btn-primary:hover { background: #1d4ed8; }
    
    .icon-btn { padding: 8px; border-radius: 8px; }

    /* Dropdown/Panel Filter */
    #filterPanel {
        display: none;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        animation: slideDown 0.2s ease-out;
    }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .field label { display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 5px; text-transform: uppercase; }
    .field input, .field select {
        width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); font-size: 13px;
    }

    /* Dashboard Summary */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }
    .sum-card { background: #fff; padding: 15px; border-radius: var(--radius); border: 1px solid var(--border); }
    .sum-card small { color: var(--text-muted); font-size: 12px; display: block; }
    .sum-card strong { font-size: 20px; display: block; margin-top: 4px; font-variant-numeric: tabular-nums; }

    /* Table Container */
    .table-container {
        background: #fff;
        border-radius: var(--radius);
        border: 1px solid var(--border);
        overflow: hidden;
    }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { background: var(--bg-light); text-align: left; padding: 12px 16px; color: var(--text-muted); font-size: 11px; text-transform: uppercase; border-bottom: 1px solid var(--border); }
    td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
    tr:hover td { background: #fafafa; }
    .num { text-align: right; font-family: 'JetBrains Mono', monospace; font-weight: 600; }

    /* Detail Modal */
    .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); backdrop-filter:blur(4px); z-index:100; }
    .modal-content { width:90%; max-width:900px; margin:40px auto; background:#fff; border-radius:16px; padding:24px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.2); }

    @media (max-width: 768px) {
        .summary-grid { grid-template-columns: repeat(2, 1fr); }
        .header-bar { flex-direction: column; align-items: flex-start; gap: 12px; }
    }
</style>

<div class="container">
    <header class="header-bar">
        <div class="header-title">
            <h1>Laporan Stok Periode</h1>
        </div>
        <div class="header-actions">
            <button class="btn icon-btn" onclick="toggleFilter()" title="Filter Data">
                <span class="material-icons-round">filter_list</span>
            </button>
            
            <div style="width: 1px; height: 24px; background: var(--border); margin: 0 4px;"></div>
            
            <button id="btnCsv" class="btn">
                <span class="material-icons-round" style="font-size:16px">description</span> CSV
            </button>
            <button id="btnXlsx" class="btn">
                <span class="material-icons-round" style="font-size:16px">table_view</span> Excel
            </button>
        </div>
    </header>

    <div id="filterPanel">
        <input type="hidden" id="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
        <div class="filter-grid">
            <div class="field">
                <label>Dari Tanggal</label>
                <input type="date" id="from">
            </div>
            <div class="field">
                <label>Sampai Tanggal</label>
                <input type="date" id="to">
            </div>
            <div class="field">
                <label>Gudang</label>
                <select id="gudang_id">
                    <option value="">Semua Gudang</option>
                    <?php foreach($gudangList as $g): ?>
                        <option value="<?=$g['gudang_id']?>"><?=htmlspecialchars($g['nama_gudang'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Produk</label>
                <select id="produk_id">
                    <option value="">Semua Produk</option>
                    <?php foreach($produkList as $p): ?>
                        <option value="<?=$p['produk_id']?>"><?=htmlspecialchars($p['nama_produk'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top: 15px; display: flex; justify-content: flex-end;">
            <button id="btnLoad" class="btn btn-primary">Terapkan Filter</button>
        </div>
    </div>

    <div class="summary-grid">
        <div class="sum-card"><small>Awal</small><strong id="sum_awal">0</strong></div>
        <div class="sum-card"><small>Masuk</small><strong id="sum_masuk" style="color:#10b981">0</strong></div>
        <div class="sum-card"><small>Keluar</small><strong id="sum_keluar" style="color:#ef4444">0</strong></div>
        <div class="sum-card"><small>Saldo Akhir</small><strong id="sum_akhir" style="color:var(--primary)">0</strong></div>
    </div>

    <div id="err" style="display:none; color:red; margin-bottom:10px;"></div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Produk</th>
                    <th class="num">Awal</th>
                    <th class="num">Masuk</th>
                    <th class="num">Keluar</th>
                    <th class="num">Akhir</th>
                    <th style="text-align:center">Aksi</th>
                </tr>
            </thead>
            <tbody id="tbody">
                <tr><td colspan="6" style="text-align:center; padding:40px; color:#94a3b8">Memuat data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="detailModal" class="modal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 id="detailTitle" style="margin:0; font-size:18px;">Detail Mutasi</h2>
            <span class="material-icons-round" style="cursor:pointer" onclick="closeModal()">close</span>
        </div>
        <div id="dailyBox" style="display:flex; gap:10px; overflow-x:auto; margin-bottom:20px; padding-bottom:10px;"></div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Tipe</th>
                        <th class="num">Qty</th>
                        <th class="num">Saldo</th>
                        <th>Referensi</th>
                    </tr>
                </thead>
                <tbody id="detailBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Toggle Visibility Filter
function toggleFilter() {
    const p = document.getElementById('filterPanel');
    p.style.display = (p.style.display === 'block') ? 'none' : 'block';
}

function closeModal() { document.getElementById('detailModal').style.display = 'none'; }

const fromEl = document.getElementById('from'), toEl = document.getElementById('to');
const gudangEl = document.getElementById('gudang_id'), produkEl = document.getElementById('produk_id');
const tbody = document.getElementById('tbody');

function fmt(n){ return new Intl.NumberFormat('id-ID').format(Number(n || 0)); }
fromEl.value = new Date(new Date().setDate(1)).toISOString().slice(0,10);
toEl.value = new Date().toISOString().slice(0,10);

async function loadData(){
    const qs = new URLSearchParams({ from: fromEl.value, to: toEl.value, gudang_id: gudangEl.value, produk_id: produkEl.value });
    try {
        const r = await fetch(`../../api/stok_periode.php?${qs.toString()}`);
        const d = await r.json();
        if(!d.ok) return;

        // Render Summary
        document.getElementById('sum_awal').textContent = fmt(d.summary.stok_awal);
        document.getElementById('sum_masuk').textContent = fmt(d.summary.masuk);
        document.getElementById('sum_keluar').textContent = fmt(d.summary.keluar);
        document.getElementById('sum_akhir').textContent = fmt(d.summary.stok_akhir);

        // Render Rows
        tbody.innerHTML = d.data.length ? d.data.map(r => `
            <tr>
                <td><strong>${r.nama_produk}</strong></td>
                <td class="num">${fmt(r.stok_awal)}</td>
                <td class="num">${fmt(r.masuk)}</td>
                <td class="num">${fmt(r.keluar)}</td>
                <td class="num">${fmt(r.stok_akhir)}</td>
                <td style="text-align:center">
                    <button class="btn" onclick="openDetail(${r.produk_id}, '${r.nama_produk.replace(/'/g, "\\'")}')">Detail</button>
                </td>
            </tr>
        `).join('') : '<tr><td colspan="6" style="text-align:center; padding:40px;">Data tidak ditemukan</td></tr>';
    } catch(e) {}
}

async function openDetail(id, name) {
    const qs = new URLSearchParams({ from: fromEl.value, to: toEl.value, gudang_id: gudangEl.value, produk_id: id });
    const r = await fetch(`../../api/stok_periode_detail.php?${qs.toString()}`);
    const d = await r.json();
    if(!d.ok) return;

    document.getElementById('detailTitle').textContent = 'Riwayat: ' + name;
    document.getElementById('detailBody').innerHTML = d.mutasi.map(m => `
        <tr>
            <td>${m.dibuat_pada}</td>
            <td>${m.tipe}</td>
            <td class="num">${fmt(m.qty)}</td>
            <td class="num">${fmt(m.stok_sesudah)}</td>
            <td>${m.referensi}</td>
        </tr>
    `).join('');
    document.getElementById('detailModal').style.display = 'block';
}

document.getElementById('btnLoad').onclick = loadData;
// Panggil pertama kali saat halaman dibuka
loadData();
</script>