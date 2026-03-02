<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/header.php';

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$db = $pos_db;
$q = trim((string)($_GET['q'] ?? ''));

try {
    $db->query("
        CREATE TABLE IF NOT EXISTS promo (
            promo_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            toko_id BIGINT NOT NULL,
            nama_promo VARCHAR(100) NOT NULL,
            tipe ENUM('persen','nominal','gratis') NOT NULL,
            nilai DECIMAL(15,2) NOT NULL DEFAULT 0,
            minimal_belanja DECIMAL(15,2) DEFAULT 0,
            berlaku_dari DATETIME NOT NULL,
            berlaku_sampai DATETIME NOT NULL,
            aktif TINYINT(1) DEFAULT 1,
            dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            KEY idx_promo_toko (toko_id),
            KEY idx_promo_active (toko_id, aktif, berlaku_dari, berlaku_sampai)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {}

$where = "WHERE toko_id=? AND deleted_at IS NULL";
$types = 'i';
$params = [$tokoId];
if ($q !== '') {
    $where .= " AND nama_promo LIKE CONCAT('%',?,'%')";
    $types .= 's';
    $params[] = $q;
}

$stmt = $db->prepare("
    SELECT p.promo_id, p.nama_promo, p.tipe, p.nilai, p.minimal_belanja, p.berlaku_dari, p.berlaku_sampai, p.aktif,
           (SELECT COUNT(*) FROM promo_produk pp WHERE pp.promo_id = p.promo_id) AS total_produk
    FROM promo p
    $where
    ORDER BY p.dibuat_pada DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$promos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$today = date('Y-m-d H:i:s');
$activeNow = 0;
foreach ($promos as $r) {
    if ((int)$r['aktif'] === 1 && $today >= $r['berlaku_dari'] && $today <= $r['berlaku_sampai']) $activeNow++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Promosi</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
:root{--primary:#0ea5e9;--ink:#0f172a;--muted:#64748b;--border:#e2e8f0;--card:#fff;--bg:#f5f7fb;--shadow:0 14px 40px rgba(15,23,42,.08);--ok:#16a34a;--warn:#d97706;}
*{box-sizing:border-box}body{margin:0;font-family:'Plus Jakarta Sans','Inter',system-ui;background:var(--bg);color:var(--ink)}
.page{padding:28px 20px 48px}.container{max-width:1200px;margin:0 auto}
.hero{background:linear-gradient(135deg,#0f172a 0%,#075985 65%,#0ea5e9 100%);color:#eaf6ff;border-radius:18px;padding:22px 24px;box-shadow:var(--shadow)}
.hero h1{margin:4px 0 8px;font-size:26px}.hero p{margin:0 0 14px;color:rgba(255,255,255,.9)}
.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{padding:10px 14px;border-radius:10px;border:1px solid transparent;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.btn-primary{background:#fff;color:#0f172a}.btn-ghost{background:rgba(255,255,255,.14);color:#fff;border-color:rgba(255,255,255,.3)}.btn-outline{background:#fff;color:#0f172a;border:1px solid var(--border)}
.metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}.metric{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.25);border-radius:12px;padding:12px 14px}
.metric small{color:rgba(255,255,255,.8);letter-spacing:.06em;font-weight:700}.metric-value{font-size:20px;font-weight:800;margin-top:6px}
.panel{margin-top:18px;background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden}
.toolbar{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:center;flex-wrap:wrap}.toolbar input{padding:10px 12px;border:1px solid var(--border);border-radius:10px;min-width:260px}
.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:14px}thead th{background:#f8fafc;padding:12px 14px;text-align:left;color:var(--muted);border-bottom:1px solid var(--border)}
tbody td{padding:12px 14px;border-bottom:1px solid #eef2f6}tbody tr:hover td{background:#f8fbff}
.badge{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}.ok{background:#dcfce7;color:#166534}.off{background:#fee2e2;color:#991b1b}.meta{color:var(--muted);font-size:12px}
.modal{display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);align-items:center;justify-content:center;z-index:1000}
.modal-box{background:#fff;border-radius:14px;width:92%;max-width:600px;box-shadow:0 18px 40px rgba(15,23,42,.18);overflow:hidden}
.modal-header{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
.modal-body{padding:16px;display:grid;gap:12px}.modal-body label{display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:6px}
.modal-body input,.modal-body select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px}
.grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.modal-footer{padding:14px 16px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end}
@media(max-width:760px){.metrics{grid-template-columns:1fr}.grid2{grid-template-columns:1fr}.toolbar{flex-direction:column;align-items:stretch}}
</style>
</head>
<body>
<div class="page">
  <div class="container">
    <div class="hero">
      <p style="margin:0;letter-spacing:.08em;font-weight:800">PROMO</p>
      <h1>Daftar Promosi</h1>
      <p>Atur promo umum yang bisa otomatis dipakai saat transaksi POS disimpan.</p>
      <div class="actions">
        <button class="btn btn-primary" onclick="openModal()">+ Promo Baru</button>
        <a class="btn btn-ghost" href="produk.php">Umum Perbarang</a>
      </div>
      <div class="metrics">
        <div class="metric"><small>Total Promo</small><div class="metric-value"><?=count($promos)?></div></div>
        <div class="metric"><small>Aktif Sekarang</small><div class="metric-value"><?=$activeNow?></div></div>
        <div class="metric"><small>Pencarian</small><div class="metric-value"><?=$q !== '' ? htmlspecialchars($q) : 'Semua'?></div></div>
      </div>
    </div>

    <div class="panel">
      <form class="toolbar" method="get">
        <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari nama promo...">
        <button class="btn btn-outline" type="submit">Cari</button>
      </form>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nama Promo</th>
              <th>Tipe</th>
              <th>Nilai</th>
              <th>Minimal Belanja</th>
              <th>Periode</th>
              <th>Produk</th>
              <th>Status</th>
              <th style="text-align:right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($promos as $p): ?>
              <?php
                $isActiveNow = ((int)$p['aktif'] === 1 && $today >= $p['berlaku_dari'] && $today <= $p['berlaku_sampai']);
                $nilaiText = $p['tipe'] === 'persen' ? number_format((float)$p['nilai'], 2) . '%' : 'Rp ' . number_format((float)$p['nilai'], 0, ',', '.');
              ?>
              <tr>
                <td>
                  <strong><?=htmlspecialchars($p['nama_promo'])?></strong>
                  <div class="meta">ID #<?=$p['promo_id']?></div>
                </td>
                <td><?=htmlspecialchars($p['tipe'])?></td>
                <td><?=$nilaiText?></td>
                <td>Rp <?=number_format((float)$p['minimal_belanja'], 0, ',', '.')?></td>
                <td class="meta"><?=date('d M Y H:i', strtotime($p['berlaku_dari']))?> - <?=date('d M Y H:i', strtotime($p['berlaku_sampai']))?></td>
                <td><?=number_format((int)$p['total_produk'])?></td>
                <td><span class="badge <?=$isActiveNow ? 'ok' : 'off'?>"><?=$isActiveNow ? 'Aktif' : 'Nonaktif'?></span></td>
                <td style="text-align:right">
                  <button class="btn btn-outline" onclick="editPromo(<?=$p['promo_id']?>)">Edit</button>
                  <button class="btn btn-outline" style="color:#b91c1c" onclick="deletePromo(<?=$p['promo_id']?>)">Hapus</button>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$promos): ?>
              <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:20px">Belum ada promo</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="promoModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modalTitle" style="margin:0">Promo Baru</h3>
      <span style="cursor:pointer;font-size:22px" onclick="closeModal()">&times;</span>
    </div>
    <form id="promoForm">
      <input type="hidden" name="promo_id">
      <div class="modal-body">
        <div>
          <label>Nama Promo</label>
          <input type="text" name="nama_promo" required placeholder="Contoh: Promo Awal Bulan">
        </div>
        <div class="grid2">
          <div>
            <label>Tipe</label>
            <select name="tipe" onchange="syncTipeHint()">
              <option value="persen">Persen</option>
              <option value="nominal">Nominal</option>
              <option value="gratis">Gratis (placeholder)</option>
            </select>
          </div>
          <div>
            <label>Nilai</label>
            <input type="number" step="0.01" min="0" name="nilai" required>
          </div>
        </div>
        <div class="grid2">
          <div>
            <label>Minimal Belanja</label>
            <input type="number" step="0.01" min="0" name="minimal_belanja" value="0">
          </div>
          <div>
            <label>Status</label>
            <select name="aktif">
              <option value="1">Aktif</option>
              <option value="0">Nonaktif</option>
            </select>
          </div>
        </div>
        <div class="grid2">
          <div>
            <label>Berlaku Dari</label>
            <input type="datetime-local" name="berlaku_dari" required>
          </div>
          <div>
            <label>Berlaku Sampai</label>
            <input type="datetime-local" name="berlaku_sampai" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" type="button" onclick="closeModal()">Batal</button>
        <button class="btn btn-primary" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById('promoModal');
const form = document.getElementById('promoForm');
function toLocalInputValue(v){
  if(!v) return '';
  const d = new Date(v.replace(' ', 'T'));
  if (isNaN(d.getTime())) return '';
  const off = d.getTimezoneOffset();
  const local = new Date(d.getTime() - (off * 60000));
  return local.toISOString().slice(0,16);
}
function syncTipeHint(){
  const tipe = form.tipe.value;
  if (tipe === 'gratis') {
    form.nilai.value = '0';
    form.nilai.readOnly = true;
  } else {
    form.nilai.readOnly = false;
    if (Number(form.nilai.value || 0) <= 0) form.nilai.value = '0';
  }
}
function openModal(){
  form.reset();
  form.promo_id.value = '';
  document.getElementById('modalTitle').innerText = 'Promo Baru';
  const now = new Date();
  const plus7 = new Date(now.getTime() + (7*24*60*60*1000));
  form.berlaku_dari.value = toLocalInputValue(now.toISOString());
  form.berlaku_sampai.value = toLocalInputValue(plus7.toISOString());
  syncTipeHint();
  modal.style.display = 'flex';
}
function closeModal(){ modal.style.display = 'none'; }

async function editPromo(id){
  const r = await fetch('/api/promo_get.php?id=' + id);
  const d = await r.json();
  if(!d.ok) return alert(d.msg || 'Promo tidak ditemukan');
  form.promo_id.value = d.data.promo_id;
  form.nama_promo.value = d.data.nama_promo || '';
  form.tipe.value = d.data.tipe || 'persen';
  form.nilai.value = d.data.nilai || 0;
  form.minimal_belanja.value = d.data.minimal_belanja || 0;
  form.aktif.value = Number(d.data.aktif || 0) === 1 ? '1' : '0';
  form.berlaku_dari.value = toLocalInputValue(d.data.berlaku_dari || '');
  form.berlaku_sampai.value = toLocalInputValue(d.data.berlaku_sampai || '');
  document.getElementById('modalTitle').innerText = 'Edit Promo';
  syncTipeHint();
  modal.style.display = 'flex';
}

async function deletePromo(id){
  if(!confirm('Hapus promo ini?')) return;
  const fd = new FormData();
  fd.set('id', String(id));
  const r = await fetch('/api/promo_delete.php', { method:'POST', body: fd });
  const d = await r.json();
  if(!d.ok) return alert(d.msg || 'Gagal hapus');
  location.reload();
}

form.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true; btn.textContent = 'Menyimpan...';
  try {
    const fd = new FormData(form);
    const r = await fetch('/api/promo_save.php', { method:'POST', body: fd });
    const d = await r.json();
    if(!d.ok) throw new Error(d.msg || 'Gagal simpan');
    location.reload();
  } catch (err) {
    alert(err.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Simpan';
  }
});

window.addEventListener('click', (e)=>{ if(e.target === modal) closeModal(); });
</script>
</body>
</html>
