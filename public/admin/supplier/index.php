<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../.../../../inc/config.php';
require_once '../../.../../../inc/db.php';
require_once '../../.../../../inc/auth.php';

$db = $pos_db;
$q  = trim($_GET['q'] ?? '');

function fetch_all(mysqli_stmt $stmt): array {
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

$where = ["deleted_at IS NULL"];
$types = '';
$params = [];
if($q !== ''){
    $where[] = "nama_supplier LIKE CONCAT('%',?,'%')";
    $types .= 's';
    $params[] = $q;
}
$sql = "SELECT supplier_id, nama_supplier, telepon, alamat, dibuat_pada
        FROM supplier
        WHERE ".implode(' AND ', $where)."
        ORDER BY nama_supplier";
$stmt = $db->prepare($sql);
if($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$suppliers = fetch_all($stmt);
$stmt->close();

$totalSup = count($suppliers);
$lastAdded = $totalSup ? date('d M Y', strtotime($suppliers[array_key_last($suppliers)]['dibuat_pada'])) : '-';
$withPhone = array_sum(array_map(fn($s)=> $s['telepon'] ? 1 : 0, $suppliers));
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
:root {
    --primary: #0ea5e9;
    --primary-strong: #0284c7;
    --danger: #ef4444;
    --muted: #64748b;
    --border: #e2e8f0;
    --card: #ffffff;
    --shadow: 0 16px 40px rgba(15,23,42,0.08);
}
body { font-family:'Plus Jakarta Sans','Inter',system-ui; background: #f1f5f9; margin:0; }
.page { padding:32px 20px 48px; }
.container { max-width: 1200px; margin:0 auto; }
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
.btn:hover { transform: translateY(-1px); }
.metrics { display:grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap:10px; }
.metric { background: rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.25); border-radius:12px; padding:12px 14px; }
.metric small { color:rgba(255,255,255,0.8); letter-spacing:0.06em; font-weight:700; }
.metric-value { font-size:20px; font-weight:800; margin-top:6px; }
.panel { margin-top:20px; background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow: var(--shadow); overflow:hidden; }
.toolbar { padding:14px 16px; border-bottom:1px solid var(--border); display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
.toolbar input { padding:10px 12px; border:1px solid var(--border); border-radius:10px; min-width:240px; font-size:14px; }
.table-wrap { overflow:auto; }
table { width:100%; border-collapse:collapse; font-size:14px; }
thead th { text-align:left; padding:12px 14px; background:#f8fafc; color:var(--muted); border-bottom:1px solid var(--border); }
tbody td { padding:12px 14px; border-bottom:1px solid #eef2f6; }
tbody tr:hover td { background:#f8fbff; }
.name { font-weight:700; }
.meta { color:var(--muted); font-size:12px; }
.actions { display:flex; gap:8px; justify-content:flex-end; }
.badge { padding:6px 10px; border-radius:999px; background:#f1f5f9; color:var(--muted); font-weight:700; font-size:12px; }
.modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); backdrop-filter: blur(5px); z-index:100; }
.modal-box { background:#fff; width:90%; max-width:560px; margin:5% auto; border-radius:14px; box-shadow:0 22px 60px rgba(15,23,42,0.3); }
.modal-header { padding:16px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
.modal-body { padding:18px; display:grid; gap:12px; }
.modal-body label { font-weight:700; color:var(--muted); font-size:13px; }
.modal-body input, .modal-body textarea {
    width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:10px; font-family:inherit;
}
.modal-footer { padding:14px 18px; border-top:1px solid var(--border); text-align:right; }
textarea { min-height:90px; resize:vertical; }
@media(max-width:820px){ .hero{grid-template-columns:1fr;} .metrics{grid-template-columns: repeat(2,minmax(0,1fr));} }
@media(max-width:640px){ .toolbar{flex-direction:column; align-items:stretch;} .metrics{grid-template-columns:1fr;} }
</style>

<div class="page">
    <div class="container">
        <div class="hero">
            <div>
                <p style="letter-spacing:0.08em;font-weight:800;margin:0;">Vendor</p>
                <h1>Supplier & Mitra</h1>
                <p>Kelola daftar pemasok, kontak, dan alamat dalam satu tempat. Siap dipakai tim purchasing dan gudang.</p>
                <div class="hero-actions">
                    <button class="btn btn-primary" onclick="openModal()">+ Supplier Baru</button>
                    <button class="btn btn-ghost" onclick="exportSup()">Export CSV</button>
                </div>
            </div>
            <div class="metrics">
                <div class="metric">
                    <small>Total Supplier</small>
                    <div class="metric-value"><?=$totalSup?></div>
                </div>
                <div class="metric">
                    <small>Data Telepon</small>
                    <div class="metric-value"><?=$withPhone?></div>
                    <div class="meta">punya kontak</div>
                </div>
                <div class="metric">
                    <small>Update Terakhir</small>
                    <div class="metric-value"><?=$lastAdded?></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <form class="toolbar" method="get">
                <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari nama supplier...">
                <button type="submit" class="btn btn-outline">Cari</button>
            </form>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Kontak</th>
                            <th>Alamat</th>
                            <th>Dibuat</th>
                            <th style="text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($suppliers as $s): ?>
                        <tr>
                            <td class="name"><?=htmlspecialchars($s['nama_supplier'])?></td>
                            <td>
                                <?=htmlspecialchars($s['telepon'] ?: '-')?>
                                <div class="meta"><?=$s['telepon'] ? 'Siap dihubungi' : 'Belum ada nomor'?></div>
                            </td>
                            <td><?=nl2br(htmlspecialchars($s['alamat'] ?: '-'))?></td>
                            <td class="meta"><?=date('d M Y', strtotime($s['dibuat_pada']))?></td>
                            <td>
                                <div class="actions">
                                    <button class="btn btn-outline" onclick="editSupplier(<?=$s['supplier_id']?>)">Edit</button>
                                    <button class="btn btn-ghost" style="color:var(--danger);border-color:rgba(239,68,68,0.25)" onclick="deleteSupplier(<?=$s['supplier_id']?>)">Hapus</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$suppliers): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:20px;">Belum ada supplier</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="supModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle" style="margin:0;">Supplier Baru</h3>
            <span style="cursor:pointer;font-size:22px;" onclick="closeModal()">&times;</span>
        </div>
        <form id="supForm">
            <input type="hidden" name="supplier_id">
            <div class="modal-body">
                <div>
                    <label>Nama Supplier</label>
                    <input name="nama_supplier" required placeholder="Contoh: PT Sumber Makmur">
                </div>
                <div>
                    <label>No. Telepon</label>
                    <input name="telepon" placeholder="08xxx">
                </div>
                <div>
                    <label>Alamat</label>
                    <textarea name="alamat" placeholder="Alamat lengkap"></textarea>
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
const modal = document.getElementById('supModal');
const form  = document.getElementById('supForm');

function openModal() {
    form.reset();
    form.supplier_id.value = '';
    document.getElementById('modalTitle').innerText = 'Supplier Baru';
    modal.style.display = 'block';
}
function closeModal(){ modal.style.display='none'; }

async function editSupplier(id){
    const r = await fetch('../../../api/supplier_get.php?id='+id);
    const d = await r.json();
    if(!d.ok) return alert(d.msg);
    form.supplier_id.value = d.data.supplier_id;
    form.nama_supplier.value = d.data.nama_supplier;
    form.telepon.value = d.data.telepon || '';
    form.alamat.value = d.data.alamat || '';
    document.getElementById('modalTitle').innerText = 'Edit Supplier';
    modal.style.display = 'block';
}

form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const btn = form.querySelector('button[type=\"submit\"]');
    btn.textContent = 'Menyimpan...'; btn.disabled = true;
    try{
        const fd = new FormData(form);
        const r = await fetch('../../../api/supplier_save.php', {method:'POST', body: fd});
        const d = await r.json();
        if(!d.ok) throw new Error(d.msg || 'Gagal simpan');
        location.reload();
    } catch(err){ alert(err.message); }
    finally { btn.textContent='Simpan'; btn.disabled=false; }
});

async function deleteSupplier(id){
    if(!confirm('Hapus supplier ini?')) return;
    const fd = new FormData(); fd.set('id', id);
    const r = await fetch('../../../api/supplier_delete.php', {method:'POST', body: fd});
    const d = await r.json();
    if(!d.ok) return alert(d.msg);
    location.reload();
}

function exportSup(){
    alert('Export CSV akan ditambahkan berikutnya.');
}

window.onclick = function(e){ if(e.target === modal) closeModal(); };
</script>
