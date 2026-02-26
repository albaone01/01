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
.page { padding:32px 20px 48px; }
.container { max-width: 1400px; margin:0 auto; }
.hero {
    background: linear-gradient(135deg, #0b5fa1 0%, #0ea5e9 60%, #67e8f9 100%);
    color:#eaf6ff;
    padding:22px 24px;
    border-radius:18px;
    display:grid;
    grid-template-columns: 1.3fr 1fr;
    gap:18px;
    box-shadow: var(--shadow);
}
.hero h1 { margin:6px 0 10px; font-size:26px; }
.hero p { margin:0 0 12px; color:rgba(255,255,255,0.9); }
.hero-actions { display:flex; gap:10px; flex-wrap:wrap; }
.btn {
    padding:10px 14px; border-radius:10px; border:1px solid transparent;
    font-weight:700; cursor:pointer; transition:.2s ease; display:inline-flex; align-items:center; gap:8px;
}
.btn-primary { background:#fff; color:#0b5fa1; }
.btn-ghost { background:rgba(255,255,255,0.14); color:#fff; border-color:rgba(255,255,255,0.3); }
.btn-outline { background:#fff; color:#0f172a; border:1px solid var(--border); }
.btn-danger { background:#fee2e2; color:#dc2626; border-color:rgba(239,68,68,0.2); }
.btn:hover { transform: translateY(-1px); }
.metrics { display:grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap:10px; }
.metric { background: rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.25); border-radius:12px; padding:12px 14px; }
.metric small { color:rgba(255,255,255,0.8); letter-spacing:0.06em; font-weight:700; }
.metric-value { font-size:20px; font-weight:800; margin-top:6px; }
.concept-strip { margin-top:14px; display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.concept-card { background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.25); border-radius:12px; padding:12px 14px; }
.concept-card h4 { margin:0 0 6px; font-size:13px; letter-spacing:0.05em; text-transform:uppercase; }
.concept-card p { margin:0; font-size:12px; line-height:1.45; color:rgba(255,255,255,0.92); }
.panel { margin-top:20px; background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow: var(--shadow); overflow:hidden; }
.toolbar { padding:14px 16px; border-bottom:1px solid var(--border); display:flex; gap:12px; align-items:center; flex-wrap:wrap; justify-content:space-between; }
.toolbar-left { display:flex; gap:12px; align-items:center; }
.toolbar input { padding:10px 12px; border:1px solid var(--border); border-radius:10px; min-width:240px; font-size:14px; }
.table-wrap { overflow:auto; max-height:600px; }
table { width:100%; border-collapse:collapse; font-size:13px; }
thead th { text-align:left; padding:12px 14px; background:#f8fafc; color:var(--muted); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:10; }
tbody td { padding:10px 14px; border-bottom:1px solid #eef2f6; vertical-align:middle; }
tbody tr:hover td { background:#f8fbff; }
.name { font-weight:700; color:#1e293b; }
.meta { color:var(--muted); font-size:12px; }
.badge { padding:4px 10px; border-radius:999px; font-weight:700; font-size:11px; display:inline-block; }
.badge-retail { background:#dbeafe; color:#1d4ed8; }
.badge-grosir { background:#dcfce7; color:#16a34a; }
.badge-reseller { background:#fef3c7; color:#d97706; }
.badge-member { background:#f3e8ff; color:#9333ea; }
.badge-none { background:#f1f5f9; color:#64748b; }
.actions { display:flex; gap:6px; justify-content:flex-end; }
.btn-xs { padding:6px 10px; font-size:12px; border-radius:8px; }
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
@media(max-width:1024px){ .hero{grid-template-columns:1fr;} .metrics{grid-template-columns: repeat(2,minmax(0,1fr));} }
@media(max-width:768px){ .cols-2{grid-template-columns:1fr;} .toolbar{flex-direction:column; align-items:stretch;} .metrics{grid-template-columns:1fr;} .concept-strip{grid-template-columns:1fr;} }
</style>

<div class="page">
    <div class="container">
        <div class="hero">
            <div>
                <p style="letter-spacing:0.08em;font-weight:800;margin:0;">CRM</p>
                <h1>Data Pelanggan</h1>
                <p>Level member dihitung dari nominal belanja bulanan. Poin tetap aktif untuk ditukar menjadi potongan belanja.</p>
                <div class="hero-actions">
                    <button class="btn btn-primary" onclick="openModal()">+ Pelanggan Baru</button>
                    <a href="../pelanggan_toko/index.php" class="btn btn-ghost">Atur Membership</a>
                </div>
                <div class="concept-strip">
                    <div class="concept-card">
                        <h4>Level Member</h4>
                        <p>Ditentukan otomatis dari total belanja pelanggan pada bulan berjalan.</p>
                    </div>
                    <div class="concept-card">
                        <h4>Poin Reward</h4>
                        <p>Poin disimpan sebagai saldo dan digunakan untuk pengurangan nilai belanja.</p>
                    </div>
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
                            <th>Alamat</th>
                            <th>Jenis</th>
                            <th>Level</th>
                            <th>Diskon</th>
                            <th>Daftar</th>
                            <th>Exp</th>
                            <th>Belanja Bulan Ini</th>
                            <th>Saldo Poin</th>
                            <th style="text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pelanggan as $p): 
                            $badgeClass = match($p['jenis_customer'] ?? '') {
                                'Retail' => 'badge-retail',
                                'Grosir' => 'badge-grosir',
                                'Reseller' => 'badge-reseller',
                                'Member' => 'badge-member',
                                default => 'badge-none'
                            };
                        ?>
                        <tr>
                            <td><?=htmlspecialchars($p['kode_pelanggan'] ?: '-')?></td>
                            <td class="name"><?=htmlspecialchars($p['nama_pelanggan'])?></td>
                            <td>
                                <?=htmlspecialchars($p['telepon'] ?: '-')?>
                                <div class="meta"><?=$p['telepon'] ? '' : 'Belum ada nomor'?></div>
                            </td>
                            <td><?=nl2br(htmlspecialchars($p['alamat'] ?: '-'))?></td>
                            <td><span class="badge <?=$badgeClass?>"><?=htmlspecialchars($p['jenis_customer'] ?: '-')?></span></td>
                            <td>
                                <?=htmlspecialchars($p['kelompok_harga'] ?: '-')?>
                                <div class="meta">Belanja bulan ini: Rp <?=number_format((float)($p['total_belanja_bulan'] ?? 0), 0, ',', '.')?></div>
                            </td>
                            <td class="text-right"><?=displayNum($p['flat_diskon'], true)?>%</td>
                            <td><?=displayDate($p['tanggal_daftar'])?></td>
                            <td><?=displayDate($p['exp'])?></td>
                            <td class="text-right">Rp <?=number_format((float)($p['total_belanja_bulan'] ?? 0), 0, ',', '.')?></td>
                            <td class="text-right"><strong><?=number_format($p['poin_saat_ini'] ?? 0)?></strong></td>
                            <td>
                                <div class="actions">
                                    <button class="btn btn-outline btn-xs" onclick="editPelanggan(<?=$p['pelanggan_id']?>)">Edit</button>
                                    <button class="btn btn-danger btn-xs" onclick="deletePelanggan(<?=$p['pelanggan_id']?>)">Hapus</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$pelanggan): ?>
                        <tr><td colspan="12" style="text-align:center;color:var(--muted);padding:30px;">
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

<script>
const modal = document.getElementById('plgModal');
const form = document.getElementById('plgForm');

function openModal() {
    form.reset();
    form.pelanggan_id.value = '';
    document.getElementById('modalTitle').innerText = 'Pelanggan Baru';
    modal.style.display = 'block';
}
function closeModal(){ modal.style.display='none'; }

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

window.onclick = function(e){ if(e.target === modal) closeModal(); };
</script>
