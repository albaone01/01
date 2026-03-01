<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../.../../../inc/config.php';
require_once '../../.../../../inc/db.php';
require_once '../../.../../../inc/auth.php';
require_once '../../.../../../inc/functions.php';

requireLogin();
requireDevice();

$db = $pos_db;
$toko_id = (int)($_SESSION['toko_id'] ?? 0);
$q = trim($_GET['q'] ?? '');

function fetch_all(mysqli_stmt $stmt): array {
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

$where = ["p.toko_id = ?", "p.deleted_at IS NULL"];
$types = 'i';
$params = [$toko_id];

if($q !== ''){
    $where[] = "(p.nama_pelanggan LIKE CONCAT('%',?,'%') OR p.telepon LIKE CONCAT('%',?,'%') OR p.kode_pelanggan LIKE CONCAT('%',?,'%'))";
    $types .= 'sss';
    $params[] = $q; $params[] = $q; $params[] = $q;
}

$sql = "SELECT p.pelanggan_id, p.kode_pelanggan, p.nama_pelanggan, p.telepon, p.alamat, 
               p.jenis_customer, p.flat_diskon, p.dibuat_pada,
               ml.nama_level as kelompok_harga,
               pt.tanggal_daftar, pt.masa_berlaku, pt.exp, pt.masa_tenggang, 
               pt.exp_poin, pt.poin_awal, pt.poin_akhir, pt.poin as poin_saat_ini,
               COALESCE(pb.total_belanja_bulan, 0) AS total_belanja_bulan
        FROM pelanggan p
        LEFT JOIN pelanggan_toko pt ON pt.pelanggan_id = p.pelanggan_id AND pt.toko_id = p.toko_id AND pt.deleted_at IS NULL
        LEFT JOIN member_level ml ON ml.level_id = pt.level_id AND ml.toko_id = p.toko_id
        LEFT JOIN (
            SELECT pelanggan_id, toko_id, SUM(total_akhir) AS total_belanja_bulan
            FROM penjualan
            WHERE pelanggan_id IS NOT NULL
              AND DATE_FORMAT(dibuat_pada, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')
            GROUP BY pelanggan_id, toko_id
        ) pb ON pb.pelanggan_id = p.pelanggan_id AND pb.toko_id = p.toko_id
        WHERE ".implode(' AND ', $where)."
        ORDER BY p.nama_pelanggan ASC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$pelanggan = fetch_all($stmt);
$stmt->close();

$totalPlg = count($pelanggan);
$withPhone = array_sum(array_map(fn($p)=> $p['telepon'] ? 1 : 0, $pelanggan));
$withAlamat = array_sum(array_map(fn($p)=> $p['alamat'] ? 1 : 0, $pelanggan));
$totalPoin = array_sum(array_map(fn($p)=> intval($p['poin_saat_ini'] ?? 0), $pelanggan));
$totalBelanjaBulan = array_sum(array_map(fn($p)=> (float)($p['total_belanja_bulan'] ?? 0), $pelanggan));

// Helper functions
function displayDate($val) {
    if (empty($val) || $val === '0000-00-00' || $val === null) return '-';
    return date('d M Y', strtotime($val));
}
function displayNum($val, $isDecimal = false) {
    if ($val === null || $val === '' || $val == 0) return '-';
    if ($isDecimal) return number_format(floatval($val), 2);
    return number_format(intval($val));
}
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
:root {
    --primary: #0ea5e9;
    --primary-strong: #0284c7;
    --danger: #ef4444;
    --success: #10b981;
    --warning: #f59e0b;
    --muted: #64748b;
    --border: #e2e8f0;
    --card: #ffffff;
    --shadow: 0 16px 40px rgba(15,23,42,0.08);
}
body { font-family:'Plus Jakarta Sans','Inter',system-ui; background: #f1f5f9; margin:0; }
.page { padding:12px 12px 20px; }
.container { max-width: 1400px; margin:0 auto; }
.hero {
    background: linear-gradient(135deg, #0b5fa1 0%, #0ea5e9 60%, #67e8f9 100%);
    color:#eaf6ff;
    padding:14px 16px;
    border-radius:12px;
    display:grid;
    grid-template-columns: 1.3fr 1fr;
    gap:10px;
    box-shadow: var(--shadow);
}
.hero-head { display:flex; align-items:center; justify-content:space-between; gap:10px; }
.hero h1 { margin:2px 0 8px; font-size:22px; }
.hero-actions { display:flex; gap:8px; flex-wrap:wrap; }
.btn {
    padding:8px 12px; border-radius:8px; border:1px solid transparent;
    font-weight:700; cursor:pointer; transition:.2s ease; display:inline-flex; align-items:center; gap:8px;
}
.btn-primary { background:#fff; color:#0b5fa1; }
.btn-ghost { background:rgba(255,255,255,0.14); color:#fff; border-color:rgba(255,255,255,0.3); }
.btn-outline { background:#fff; color:#0f172a; border:1px solid var(--border); }
.btn-danger { background:#fee2e2; color:#dc2626; border-color:rgba(239,68,68,0.2); }
.btn:hover { transform: translateY(-1px); }
.metrics { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-start; }
.metric {
    background: rgba(255,255,255,0.12);
    border:1px solid rgba(255,255,255,0.25);
    border-radius:10px;
    padding:6px 8px;
    min-width:76px;
    transition: padding .18s ease, min-width .18s ease, background .18s ease;
}
.metric small { color:rgba(255,255,255,0.8); letter-spacing:0.06em; font-weight:700; }
.metric-value { font-size:17px; font-weight:800; margin-top:0; white-space:nowrap; }
.metric small {
    opacity: 0;
    max-height: 0;
    overflow: hidden;
    transform: translateY(3px);
    transition: opacity .18s ease, transform .18s ease, max-height .18s ease;
}
.metric:hover,
.metric:active {
    min-width:170px;
    padding:8px 10px;
    background: rgba(255,255,255,0.2);
}
.metric:hover small,
.metric:active small {
    opacity: 1;
    max-height: 24px;
    transform: translateY(0);
}
.metric:hover .metric-value,
.metric:active .metric-value { margin-top:4px; }
.info-toggle {
    width:28px; height:28px; border-radius:999px; border:1px solid rgba(255,255,255,0.5);
    background:rgba(255,255,255,0.2); color:#fff; font-weight:800; cursor:pointer;
    display:inline-flex; align-items:center; justify-content:center;
}
.info-panel { margin-top:8px; background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.25); border-radius:10px; padding:8px 10px; }
.info-panel[hidden] { display:none; }
.info-panel p { margin:0 0 6px; color:rgba(255,255,255,0.95); font-size:12px; line-height:1.4; }
.info-panel ul { margin:0; padding-left:16px; display:grid; gap:2px; }
.info-panel li { font-size:12px; color:rgba(255,255,255,0.92); }
.panel { margin-top:10px; background:var(--card); border:1px solid var(--border); border-radius:10px; box-shadow: var(--shadow); overflow:hidden; }
.toolbar { padding:8px 10px; border-bottom:1px solid var(--border); display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:space-between; }
.toolbar-left { display:flex; gap:8px; align-items:center; }
.toolbar input { padding:8px 10px; border:1px solid var(--border); border-radius:8px; min-width:200px; font-size:13px; }
.table-wrap { overflow:auto; max-height:600px; }
table { width:100%; border-collapse:collapse; font-size:13px; }
thead th { text-align:left; padding:8px 10px; background:#f8fafc; color:var(--muted); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:10; }
tbody td { padding:7px 10px; border-bottom:1px solid #eef2f6; vertical-align:middle; }
tbody tr:hover td { background:#f8fbff; }
.name { font-weight:700; color:#1e293b; }
.meta { color:var(--muted); font-size:12px; }
.badge { padding:4px 10px; border-radius:999px; font-weight:700; font-size:11px; display:inline-block; }
.badge-retail { background:#dbeafe; color:#1d4ed8; }
.badge-grosir { background:#dcfce7; color:#16a34a; }
.badge-reseller { background:#fef3c7; color:#d97706; }
.badge-member { background:#f3e8ff; color:#9333ea; }
.badge-none { background:#f1f5f9; color:#64748b; }
.actions { display:flex; gap:4px; justify-content:flex-end; }
.btn-xs { padding:5px 8px; font-size:11px; border-radius:7px; }
.modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); backdrop-filter: blur(5px); z-index:100; }
.modal-box { background:#fff; width:90%; max-width:500px; margin:5% auto; border-radius:14px; box-shadow:0 22px 60px rgba(15,23,42,0.3); }
.modal-header { padding:16px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
.modal-body { padding:18px; display:grid; gap:14px; }
.modal-body label { font-weight:700; color:var(--muted); font-size:13px; display:block; margin-bottom:4px; }
.modal-body input, .modal-body select, .modal-body textarea {
    width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:10px; font-family:inherit; box-sizing:border-box;
}
.modal-body input:focus, .modal-body select:focus, .modal-body textarea:focus {
    outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(14,165,233,0.15);
}
.modal-footer { padding:14px 18px; border-top:1px solid var(--border); text-align:right; display:flex; gap:10px; justify-content:flex-end; }
textarea { min-height:80px; resize:vertical; }
.cols-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.text-right { text-align:right; }
.detail-grid { display:grid; grid-template-columns: 1fr 1fr; gap:8px 12px; }
.detail-item { border:1px solid var(--border); border-radius:8px; padding:8px 10px; background:#f8fafc; }
.detail-item strong { display:block; font-size:12px; color:var(--muted); margin-bottom:2px; }
.detail-item span { font-size:13px; color:#0f172a; font-weight:600; word-break:break-word; }
@media(max-width:640px){ .detail-grid{grid-template-columns:1fr;} }
@media(max-width:1024px){ .hero{grid-template-columns:1fr;} }
@media(max-width:768px){
    .cols-2{grid-template-columns:1fr;}
    .toolbar{flex-direction:column; align-items:stretch;}
    .toolbar input{min-width:unset; width:100%;}
    .metric{min-width:calc(50% - 8px);}
    .metric:hover, .metric:active{min-width:calc(50% - 8px);}
}
</style>

<div class="page">
    <div class="container">
        <div class="hero">
            <div>
                <div class="hero-head">
                    <p style="letter-spacing:0.08em;font-weight:800;margin:0;">CRM</p>
                    <button type="button" class="info-toggle" id="infoToggle" title="Tampilkan informasi" aria-expanded="false" aria-controls="infoPanel">!</button>
                </div>
                <h1>Data Pelanggan</h1>
                <div class="hero-actions">
                    <button type="button" class="btn btn-ghost" onclick="goBackToDashboard()">Kembali</button>
                    <button class="btn btn-primary" onclick="openModal()">+ Pelanggan Baru</button>
                    <a href="../pelanggan_toko/index.php" class="btn btn-ghost">Atur Membership</a>
                </div>
                <div class="info-panel" id="infoPanel" hidden>
                    <p>Level member dihitung dari nominal belanja bulanan. Poin tetap aktif untuk ditukar menjadi potongan belanja.</p>
                    <ul>
                        <li>Level member ditentukan otomatis dari total belanja bulan berjalan.</li>
                        <li>Poin reward disimpan sebagai saldo untuk pengurangan nilai belanja.</li>
                    </ul>
                </div>
            </div>
            <div class="metrics">
                <div class="metric">
                    <small>Total Pelanggan</small>
                    <div class="metric-value"><?=number_format($totalPlg)?></div>
                </div>
                <div class="metric">
                    <small>Punya Telepon</small>
                    <div class="metric-value"><?=number_format($withPhone)?></div>
                </div>
                <div class="metric">
                    <small>Total Saldo Poin</small>
                    <div class="metric-value"><?=number_format($totalPoin)?></div>
                </div>
                <div class="metric">
                    <small>Total Belanja Bulan Ini</small>
                    <div class="metric-value">Rp <?=number_format($totalBelanjaBulan, 0, ',', '.')?></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <form class="toolbar" method="get">
                <div class="toolbar-left">
                    <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari nama, telepon, atau kode...">
                    <button type="submit" class="btn btn-outline">Cari</button>
                    <?php if($q): ?>
                        <a href="index.php" class="btn btn-outline">Reset</a>
                    <?php endif; ?>
                </div>
                <div class="toolbar-right">
                    <span class="meta"><?=count($pelanggan)?> data</span>
                </div>
            </form>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Kontak</th>
                            <th>Belanja Bulan Ini</th>
                            <th style="text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pelanggan as $p): ?>
                        <tr>
                            <td><?=htmlspecialchars($p['kode_pelanggan'] ?: '-')?></td>
                            <td class="name"><?=htmlspecialchars($p['nama_pelanggan'])?></td>
                            <td>
                                <?=htmlspecialchars($p['telepon'] ?: '-')?>
                                <div class="meta"><?=$p['telepon'] ? '' : 'Belum ada nomor'?></div>
                            </td>
                            <td class="text-right">Rp <?=number_format((float)($p['total_belanja_bulan'] ?? 0), 0, ',', '.')?></td>
                            <td>
                                <div class="actions">
                                    <button type="button" class="btn btn-outline btn-xs" title="Detail" onclick="showDetailPelanggan(<?=$p['pelanggan_id']?>, <?=json_encode((float)($p['total_belanja_bulan'] ?? 0))?>)">&#9432;</button>
                                    <button type="button" class="btn btn-outline btn-xs" onclick="editPelanggan(<?=$p['pelanggan_id']?>)">Edit</button>
                                    <button type="button" class="btn btn-danger btn-xs" onclick="deletePelanggan(<?=$p['pelanggan_id']?>)">Hapus</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$pelanggan): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:30px;">
                            <div style="font-size:18px;margin-bottom:8px;">Belum ada pelanggan</div>
                            <button class="btn btn-primary" onclick="openModal()">+ Tambah Pelanggan Pertama</button>
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="plgModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle" style="margin:0;">Pelanggan Baru</h3>
            <span style="cursor:pointer;font-size:22px;" onclick="closeModal()">&times;</span>
        </div>
        <form id="plgForm">
            <input type="hidden" name="pelanggan_id">
            <div class="modal-body">
                <div class="cols-2">
                    <div>
                        <label>Kode Pelanggan</label>
                        <input name="kode_pelanggan" placeholder="Auto jika kosong">
                    </div>
                    <div>
                        <label>Jenis Customer</label>
                        <select name="jenis_customer">
                            <option value="">- Pilih Jenis -</option>
                            <option value="Retail">Retail</option>
                            <option value="Grosir">Grosir</option>
                            <option value="Reseller">Reseller</option>
                            <option value="Member">Member</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label>Nama Pelanggan <span style="color:var(--danger)">*</span></label>
                    <input name="nama_pelanggan" required placeholder="Nama lengkap pelanggan">
                </div>
                <div class="cols-2">
                    <div>
                        <label>No. Telepon</label>
                        <input name="telepon" placeholder="08xxx">
                    </div>
                    <div>
                        <label>Flat Diskon (%)</label>
                        <input name="flat_diskon" type="number" step="0.01" min="0" max="100" placeholder="0" value="0">
                    </div>
                </div>
                <div>
                    <label>Alamat</label>
                    <textarea name="alamat" placeholder="Alamat lengkap"></textarea>
                </div>
                <hr style="border:none;border-top:1px solid var(--border);margin:4px 0;">
                <div style="color:var(--muted);font-weight:700;font-size:13px;margin-bottom:8px;">PENGATURAN MEMBERSHIP (LEVEL OTOMATIS)</div>
                <div class="cols-2">
                    <div>
                        <label>Level Member</label>
                        <input value="Otomatis dari nominal belanja bulan ini (atur di menu Atur Membership)" readonly>
                    </div>
                    <div>
                        <label>Tanggal Daftar</label>
                        <input name="tanggal_daftar" type="date" value="<?=date('Y-m-d')?>">
                    </div>
                </div>
                <div class="cols-2">
                    <div>
                        <label>Masa Berlaku (Tahun)</label>
                        <input name="masa_berlaku" type="number" min="0" placeholder="1" value="1">
                    </div>
                    <div>
                        <label>Masa Tenggang (Hari)</label>
                        <input name="masa_tenggang" type="number" min="0" placeholder="7" value="7">
                    </div>
                </div>
                <div class="cols-2">
                    <div>
                        <label>Saldo Poin Awal</label>
                        <input name="poin_awal" type="number" min="0" placeholder="0" value="0">
                    </div>
                    <div>
                        <label>Saldo Poin Saat Ini</label>
                        <input name="poin" type="number" min="0" placeholder="0" value="0">
                    </div>
                </div>
                <div style="font-size:12px;color:var(--muted);margin-top:-4px;">
                    Poin digunakan untuk pengurangan belanja saat proses penukaran di kasir.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="detailModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="margin:0;">Detail Pelanggan</h3>
            <span style="cursor:pointer;font-size:22px;" onclick="closeDetailModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="detail-grid" id="detailGrid">
                <div class="detail-item"><strong>Status</strong><span>Memuat data...</span></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeDetailModal()">Tutup</button>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('plgModal');
const detailModal = document.getElementById('detailModal');
const detailGrid = document.getElementById('detailGrid');
const form = document.getElementById('plgForm');
const infoToggle = document.getElementById('infoToggle');
const infoPanel = document.getElementById('infoPanel');

infoToggle.addEventListener('click', () => {
    const isOpen = !infoPanel.hasAttribute('hidden');
    if (isOpen) {
        infoPanel.setAttribute('hidden', '');
    } else {
        infoPanel.removeAttribute('hidden');
    }
    infoToggle.setAttribute('aria-expanded', String(!isOpen));
});

function openModal() {
    form.reset();
    form.pelanggan_id.value = '';
    document.getElementById('modalTitle').innerText = 'Pelanggan Baru';
    modal.style.display = 'block';
}
function closeModal(){ modal.style.display='none'; }
function closeDetailModal(){ detailModal.style.display='none'; }
function goBackToDashboard(){ window.location.replace('../dashboard.php'); }

function formatRp(value){
    return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
}

function formatDate(value){
    if(!value || value === '0000-00-00') return '-';
    const d = new Date(value);
    if(Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
}

function escapeHtml(value){
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function renderDetailItems(p){
    const items = [
        ['Kode Pelanggan', p.kode_pelanggan || '-'],
        ['Nama Pelanggan', p.nama_pelanggan || '-'],
        ['Telepon', p.telepon || '-'],
        ['Alamat', p.alamat || '-'],
        ['Jenis Customer', p.jenis_customer || '-'],
        ['Level Member', p.kelompok_harga || '-'],
        ['Flat Diskon', `${Number(p.flat_diskon || 0).toFixed(2)}%`],
        ['Tanggal Daftar', formatDate(p.tanggal_daftar)],
        ['Tanggal Exp', formatDate(p.exp)],
        ['Masa Berlaku (Tahun)', p.masa_berlaku ?? '-'],
        ['Masa Tenggang (Hari)', p.masa_tenggang ?? '-'],
        ['Saldo Poin Awal', Number(p.poin_awal || 0).toLocaleString('id-ID')],
        ['Saldo Poin Saat Ini', Number(p.poin || 0).toLocaleString('id-ID')],
        ['Belanja Bulan Ini', formatRp(p.total_belanja_bulan || 0)]
    ];
    detailGrid.innerHTML = items.map(([k,v]) =>
        `<div class="detail-item"><strong>${escapeHtml(k)}</strong><span>${escapeHtml(v)}</span></div>`
    ).join('');
}

async function showDetailPelanggan(id, belanjaBulanIni = 0){
    detailGrid.innerHTML = '<div class="detail-item"><strong>Status</strong><span>Memuat data...</span></div>';
    detailModal.style.display = 'block';
    try{
        const r = await fetch('../../../api/pelanggan_get.php?id=' + id);
        if(!r.ok) throw new Error('Gagal mengambil data');
        const d = await r.json();
        if(!d.ok || !d.data) throw new Error(d.msg || 'Data tidak ditemukan');
        const data = d.data;
        if (!('total_belanja_bulan' in data) || data.total_belanja_bulan === null) {
            data.total_belanja_bulan = belanjaBulanIni;
        }
        renderDetailItems(data);
    } catch(err){
        detailGrid.innerHTML = `<div class="detail-item"><strong>Error</strong><span>${err.message}</span></div>`;
    }
}

async function editPelanggan(id){
    const r = await fetch('../../../api/pelanggan_get.php?id='+id);
    if(!r.ok){ alert('Gagal mengambil data'); return; }
    const d = await r.json();
    if(!d.ok){ alert(d.msg); return; }
    const p = d.data;
    form.pelanggan_id.value = p.pelanggan_id;
    form.kode_pelanggan.value = p.kode_pelanggan || '';
    form.nama_pelanggan.value = p.nama_pelanggan;
    form.telepon.value = p.telepon || '';
    form.alamat.value = p.alamat || '';
    form.jenis_customer.value = p.jenis_customer || '';
    form.flat_diskon.value = p.flat_diskon || 0;
    // Membership fields
    form.tanggal_daftar.value = p.tanggal_daftar || '';
    form.masa_berlaku.value = p.masa_berlaku || 1;
    form.masa_tenggang.value = p.masa_tenggang || 7;
    form.poin_awal.value = p.poin_awal || 0;
    form.poin.value = p.poin || 0;
    document.getElementById('modalTitle').innerText = 'Edit Pelanggan';
    modal.style.display = 'block';
}

form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    btn.textContent = 'Menyimpan...'; btn.disabled = true;
    try{
        const fd = new FormData(form);
        const r = await fetch('../../../api/pelanggan_save.php', {method:'POST', body: fd});
        const d = await r.json();
        if(!d.ok) throw new Error(d.msg || 'Gagal simpan');
        location.reload();
    } catch(err){ alert(err.message); }
    finally { btn.textContent='Simpan'; btn.disabled=false; }
});

async function deletePelanggan(id){
    if(!confirm('Hapus pelanggan ini?')) return;
    const fd = new FormData(); fd.set('id', id);
    const r = await fetch('../../../api/pelanggan_delete.php', {method:'POST', body: fd});
    const d = await r.json();
    if(!d.ok) return alert(d.msg);
    location.reload();
}

window.onclick = function(e){
    if(e.target === modal) closeModal();
    if(e.target === detailModal) closeDetailModal();
};
</script>
