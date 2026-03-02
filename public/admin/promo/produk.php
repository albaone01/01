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

try {
    $db->query("
        CREATE TABLE IF NOT EXISTS promo_produk (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            promo_id BIGINT NOT NULL,
            produk_id BIGINT NOT NULL,
            UNIQUE KEY uq_promo_produk (promo_id, produk_id),
            KEY idx_pp_produk (produk_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {}

$promos = [];
$ps = $db->prepare("SELECT promo_id, nama_promo, tipe, nilai, berlaku_dari, berlaku_sampai, aktif FROM promo WHERE toko_id=? AND deleted_at IS NULL ORDER BY aktif DESC, dibuat_pada DESC");
$ps->bind_param('i', $tokoId);
$ps->execute();
$pres = $ps->get_result();
if ($pres) $promos = $pres->fetch_all(MYSQLI_ASSOC);
$ps->close();

$produk = [];
$prd = $db->prepare("SELECT produk_id, nama_produk, sku FROM produk WHERE toko_id=? AND aktif=1 AND deleted_at IS NULL ORDER BY nama_produk");
$prd->bind_param('i', $tokoId);
$prd->execute();
$rprd = $prd->get_result();
if ($rprd) $produk = $rprd->fetch_all(MYSQLI_ASSOC);
$prd->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Umum Perbarang</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
:root{--primary:#0284c7;--ink:#0f172a;--muted:#64748b;--border:#e2e8f0;--card:#fff;--bg:#f5f7fb;--shadow:0 14px 40px rgba(15,23,42,.08);}
*{box-sizing:border-box}body{margin:0;font-family:'Plus Jakarta Sans','Inter',system-ui;background:var(--bg);color:var(--ink)}
.page{padding:28px 20px 48px}.container{max-width:1200px;margin:0 auto}
.hero{background:linear-gradient(135deg,#172554 0%,#075985 70%,#0ea5e9 100%);color:#eaf6ff;border-radius:18px;padding:22px 24px;box-shadow:var(--shadow)}
.hero h1{margin:4px 0 8px;font-size:26px}.hero p{margin:0 0 14px;color:rgba(255,255,255,.9)}
.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{padding:10px 14px;border-radius:10px;border:1px solid transparent;font-weight:700;cursor:pointer;text-decoration:none}
.btn-primary{background:#fff;color:#0f172a}.btn-ghost{background:rgba(255,255,255,.14);color:#fff;border-color:rgba(255,255,255,.3)}.btn-outline{background:#fff;color:#0f172a;border:1px solid var(--border)}
.grid{margin-top:18px;display:grid;grid-template-columns:340px 1fr;gap:16px}
.panel{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden}
.panel h3{margin:0;padding:14px 16px;border-bottom:1px solid var(--border);font-size:16px}
.panel-body{padding:14px 16px}.muted{color:var(--muted);font-size:12px}
.promo-list{display:grid;gap:8px;max-height:560px;overflow:auto}
.promo-item{border:1px solid var(--border);border-radius:10px;padding:10px;cursor:pointer;background:#fff}
.promo-item.active{border-color:#0284c7;background:#f0f9ff}
.search{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;margin-bottom:10px}
.table-wrap{max-height:560px;overflow:auto;border:1px solid var(--border);border-radius:12px}
table{width:100%;border-collapse:collapse;font-size:14px}thead th{position:sticky;top:0;background:#f8fafc;padding:10px 12px;text-align:left;color:var(--muted);border-bottom:1px solid var(--border)}
tbody td{padding:10px 12px;border-bottom:1px solid #eef2f6}.footer-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:12px}
@media(max-width:980px){.grid{grid-template-columns:1fr}.promo-list{max-height:260px}.table-wrap{max-height:420px}}
</style>
</head>
<body>
<div class="page">
  <div class="container">
    <div class="hero">
      <p style="margin:0;letter-spacing:.08em;font-weight:800">PROMO</p>
      <h1>Umum Perbarang</h1>
      <p>Pilih promo, lalu tandai produk yang ikut promo tersebut.</p>
      <div class="actions">
        <a class="btn btn-primary" href="index.php">Daftar Promosi</a>
      </div>
    </div>

    <div class="grid">
      <div class="panel">
        <h3>Daftar Promo</h3>
        <div class="panel-body">
          <div class="promo-list" id="promoList"></div>
        </div>
      </div>
      <div class="panel">
        <h3>Produk Promo</h3>
        <div class="panel-body">
          <input class="search" id="productSearch" placeholder="Cari nama / SKU..." oninput="renderProducts()">
          <div class="table-wrap">
            <table>
              <thead><tr><th style="width:44px"><input type="checkbox" id="checkAll" onclick="toggleAll(this.checked)"></th><th>Produk</th><th>SKU</th></tr></thead>
              <tbody id="productBody"></tbody>
            </table>
          </div>
          <div class="footer-actions">
            <button class="btn btn-outline" onclick="reloadMapping()">Muat Ulang</button>
            <button class="btn btn-primary" onclick="saveMapping()">Simpan Mapping</button>
          </div>
          <div class="muted" style="margin-top:8px">Catatan: promo aktif akan dievaluasi otomatis saat simpan transaksi POS.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const promoData = <?=json_encode($promos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const productData = <?=json_encode($produk, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
let selectedPromoId = 0;
let selectedProductIds = new Set();

function promoLabel(p){
  const val = p.tipe === 'persen' ? `${Number(p.nilai||0).toFixed(2)}%` : `Rp ${Number(p.nilai||0).toLocaleString('id-ID')}`;
  return `${p.nama_promo} (${val})`;
}

function renderPromoList(){
  const wrap = document.getElementById('promoList');
  wrap.innerHTML = '';
  if (!promoData.length) {
    wrap.innerHTML = '<div class="muted">Belum ada promo. Buat di halaman Daftar Promosi.</div>';
    return;
  }
  promoData.forEach(p=>{
    const div = document.createElement('div');
    div.className = 'promo-item' + (selectedPromoId === Number(p.promo_id) ? ' active' : '');
    div.innerHTML = `<strong>${promoLabel(p)}</strong><div class="muted">${p.aktif==1?'Aktif':'Nonaktif'} | ${p.berlaku_dari} - ${p.berlaku_sampai}</div>`;
    div.onclick = async ()=>{ selectedPromoId = Number(p.promo_id); renderPromoList(); await reloadMapping(); };
    wrap.appendChild(div);
  });
}

function renderProducts(){
  const body = document.getElementById('productBody');
  const term = (document.getElementById('productSearch').value || '').toLowerCase().trim();
  body.innerHTML = '';
  const rows = productData.filter(p => {
    const n = String(p.nama_produk || '').toLowerCase();
    const s = String(p.sku || '').toLowerCase();
    return !term || n.includes(term) || s.includes(term);
  });
  rows.forEach(p=>{
    const id = Number(p.produk_id);
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><input type="checkbox" ${selectedProductIds.has(id)?'checked':''} onchange="toggleOne(${id}, this.checked)"></td><td>${escapeHtml(p.nama_produk || '')}</td><td>${escapeHtml(p.sku || '-')}</td>`;
    body.appendChild(tr);
  });
}

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function toggleOne(id, checked){
  if (checked) selectedProductIds.add(id); else selectedProductIds.delete(id);
}

function toggleAll(checked){
  const term = (document.getElementById('productSearch').value || '').toLowerCase().trim();
  productData.forEach(p=>{
    const n = String(p.nama_produk || '').toLowerCase();
    const s = String(p.sku || '').toLowerCase();
    if (!term || n.includes(term) || s.includes(term)) {
      const id = Number(p.produk_id);
      if (checked) selectedProductIds.add(id); else selectedProductIds.delete(id);
    }
  });
  renderProducts();
}

async function reloadMapping(){
  if (!selectedPromoId) {
    selectedProductIds = new Set();
    renderProducts();
    return;
  }
  const r = await fetch(`/api/promo_produk_get.php?promo_id=${selectedPromoId}`);
  const d = await r.json();
  if (!d.ok) { alert(d.msg || 'Gagal load mapping'); return; }
  selectedProductIds = new Set((d.produk_ids || []).map(Number));
  renderProducts();
}

async function saveMapping(){
  if (!selectedPromoId) return alert('Pilih promo terlebih dahulu');
  const fd = new FormData();
  fd.set('promo_id', String(selectedPromoId));
  Array.from(selectedProductIds).forEach(id => fd.append('produk_ids[]', String(id)));
  const r = await fetch('/api/promo_produk_save.php', { method:'POST', body: fd });
  const d = await r.json();
  if (!d.ok) return alert(d.msg || 'Gagal simpan mapping');
  alert(`Mapping tersimpan (${d.count || 0} produk)`);
}

renderPromoList();
if (promoData.length) selectedPromoId = Number(promoData[0].promo_id);
renderPromoList();
reloadMapping();
</script>
</body>
</html>
