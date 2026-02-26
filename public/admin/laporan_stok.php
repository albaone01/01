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

$stmt = $pos_db->prepare("SELECT gudang_id,nama_gudang FROM gudang WHERE toko_id=? AND aktif=1 AND deleted_at IS NULL ORDER BY nama_gudang");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$gudangList = fetch_all_stmt($stmt);
$stmt->close();

$stmt = $pos_db->prepare("SELECT produk_id,nama_produk,sku FROM produk WHERE toko_id=? AND deleted_at IS NULL ORDER BY nama_produk LIMIT 1000");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$produkList = fetch_all_stmt($stmt);
$stmt->close();
?>
<style>
        body { font-family: Arial, sans-serif; background:#f8fafc; margin:0; }
        .wrap { max-width: 1150px; margin: 12px auto; padding: 0 10px; }
        .card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; }
        h1 { margin: 0 0 10px; font-size: 20px; }
        .filters { display:grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:8px; align-items:end; }
        label { display:block; font-size:12px; color:#334155; margin-bottom:3px; font-weight:700; }
        input, select, button { width:100%; padding:7px 8px; border:1px solid #cbd5e1; border-radius:8px; font-size:12px; box-sizing:border-box; }
        button { cursor:pointer; background:#0ea5e9; color:#fff; border-color:#0ea5e9; font-weight:700; }
        button.secondary { background:#fff; color:#0f172a; border-color:#cbd5e1; }
        .actions { display:flex; gap:6px; }
        .summary { display:grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap:8px; margin:10px 0; }
        .sum-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px; }
        .sum-box small { color:#64748b; display:block; font-size:11px; }
        .sum-box strong { font-size:16px; color:#0f172a; }
        .table-wrap { overflow:auto; border:1px solid #e2e8f0; border-radius:8px; }
        table { width:100%; border-collapse:collapse; font-size:12px; }
        th, td { border-bottom:1px solid #e2e8f0; padding:7px 8px; text-align:left; }
        th { position:sticky; top:0; background:#f8fafc; color:#334155; z-index:1; }
        td.num { text-align:right; font-variant-numeric: tabular-nums; }
        .muted { color:#64748b; font-size:11px; }
        .err { margin-top:8px; padding:8px; border:1px solid #fecaca; background:#fff1f2; color:#b91c1c; border-radius:8px; display:none; }
        .btn-mini { padding:4px 6px; font-size:11px; border-radius:6px; border:1px solid #cbd5e1; background:#fff; cursor:pointer; }
        .modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:100; }
        .modal-content { width:94%; max-width:1000px; margin:4% auto; background:#fff; border-radius:10px; border:1px solid #e2e8f0; padding:10px; max-height:86vh; overflow:auto; }
        .modal-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
        .close-x { cursor:pointer; font-size:22px; line-height:1; }
        .daily { margin:8px 0; display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:6px; }
        .daily .box { border:1px solid #e2e8f0; background:#f8fafc; border-radius:8px; padding:6px; font-size:11px; }
    </style>
<div class="wrap">
    <div class="card">
        <h1>Laporan Stok Periode</h1>
        <input type="hidden" id="csrf_token" value="<?=htmlspecialchars($csrfToken)?>">
        <div class="filters">
            <div>
                <label>Dari Tanggal</label>
                <input type="date" id="from">
            </div>
            <div>
                <label>Sampai Tanggal</label>
                <input type="date" id="to">
            </div>
            <div>
                <label>Gudang</label>
                <select id="gudang_id">
                    <option value="">Semua Gudang</option>
                    <?php foreach($gudangList as $g): ?>
                        <option value="<?=$g['gudang_id']?>"><?=htmlspecialchars($g['nama_gudang'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Produk</label>
                <select id="produk_id">
                    <option value="">Semua Produk</option>
                    <?php foreach($produkList as $p): ?>
                        <option value="<?=$p['produk_id']?>"><?=htmlspecialchars($p['nama_produk'])?><?=($p['sku']?' ('.$p['sku'].')':'')?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="actions">
                <button id="btnLoad" type="button">Tampilkan</button>
                <button id="btnCsv" type="button" class="secondary">Export CSV</button>
                <button id="btnXlsx" type="button" class="secondary">Export XLSX</button>
            </div>
        </div>

        <div class="summary">
            <div class="sum-box"><small>Stok Awal</small><strong id="sum_awal">0</strong></div>
            <div class="sum-box"><small>Masuk</small><strong id="sum_masuk">0</strong></div>
            <div class="sum-box"><small>Keluar</small><strong id="sum_keluar">0</strong></div>
            <div class="sum-box"><small>Stok Akhir</small><strong id="sum_akhir">0</strong></div>
        </div>

        <div id="err" class="err"></div>

        <div class="table-wrap">
            <table id="tbl">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="num">Stok Awal</th>
                        <th class="num">Masuk</th>
                        <th class="num">Keluar</th>
                        <th class="num">Stok Akhir</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="6" class="muted">Belum ada data. Pilih filter lalu klik Tampilkan.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <h3 id="detailTitle" style="margin:0;font-size:16px;">Detail Mutasi Produk</h3>
            <span class="close-x" id="detailClose">&times;</span>
        </div>
        <div class="daily" id="dailyBox"></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Gudang</th>
                        <th>Tipe</th>
                        <th class="num">Qty</th>
                        <th class="num">Stok Sebelum</th>
                        <th class="num">Stok Sesudah</th>
                        <th>Referensi</th>
                    </tr>
                </thead>
                <tbody id="detailBody">
                    <tr><td colspan="7" class="muted">Belum ada data.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const fromEl = document.getElementById('from');
const toEl = document.getElementById('to');
const gudangEl = document.getElementById('gudang_id');
const produkEl = document.getElementById('produk_id');
const tbody = document.getElementById('tbody');
const errEl = document.getElementById('err');
const dataState = { rows: [], filter: null };
const csrfToken = document.getElementById('csrf_token').value;
const detailModal = document.getElementById('detailModal');
const detailBody = document.getElementById('detailBody');
const dailyBox = document.getElementById('dailyBox');
document.getElementById('detailClose').onclick = ()=> detailModal.style.display = 'none';
window.addEventListener('click', (e)=>{ if(e.target === detailModal) detailModal.style.display = 'none'; });

function fmt(n){ return new Intl.NumberFormat('id-ID').format(Number(n || 0)); }
function todayISO(){ return new Date().toISOString().slice(0,10); }
function monthStartISO(){ const d = new Date(); d.setDate(1); return d.toISOString().slice(0,10); }

fromEl.value = monthStartISO();
toEl.value = todayISO();

function showErr(msg){
  errEl.style.display = 'block';
  errEl.textContent = msg;
}
function clearErr(){
  errEl.style.display = 'none';
  errEl.textContent = '';
}
function renderSummary(s){
  document.getElementById('sum_awal').textContent = fmt(s?.stok_awal || 0);
  document.getElementById('sum_masuk').textContent = fmt(s?.masuk || 0);
  document.getElementById('sum_keluar').textContent = fmt(s?.keluar || 0);
  document.getElementById('sum_akhir').textContent = fmt(s?.stok_akhir || 0);
}
function renderRows(rows){
  if(!rows.length){
    tbody.innerHTML = '<tr><td colspan="6" class="muted">Tidak ada data pada periode ini.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map(r => `
    <tr>
      <td>${String(r.nama_produk || '').replace(/</g,'&lt;')}</td>
      <td class="num">${fmt(r.stok_awal)}</td>
      <td class="num">${fmt(r.masuk)}</td>
      <td class="num">${fmt(r.keluar)}</td>
      <td class="num">${fmt(r.stok_akhir)}</td>
      <td><button class="btn-mini" onclick="openDetail(${Number(r.produk_id||0)}, '${String(r.nama_produk||'').replace(/'/g,"\\'")}')">Lihat</button></td>
    </tr>
  `).join('');
}

async function savePreset(){
  const payload = {
    from: fromEl.value || '',
    to: toEl.value || '',
    gudang_id: gudangEl.value || '',
    produk_id: produkEl.value || ''
  };
  const fd = new FormData();
  fd.set('kunci', 'laporan_stok_periode');
  fd.set('nilai', JSON.stringify(payload));
  fd.set('csrf_token', csrfToken);
  try{
    await fetch('../../api/laporan_filter_preset_save.php', {method:'POST', body:fd});
  }catch(_e){}
}

async function loadPreset(){
  try{
    const r = await fetch('../../api/laporan_filter_preset_get.php?kunci=laporan_stok_periode', {cache:'no-store'});
    const d = await r.json();
    if(!r.ok || !d.ok || !d.nilai) return;
    const v = d.nilai;
    if(v.from) fromEl.value = v.from;
    if(v.to) toEl.value = v.to;
    if(typeof v.gudang_id !== 'undefined') gudangEl.value = String(v.gudang_id);
    if(typeof v.produk_id !== 'undefined') produkEl.value = String(v.produk_id);
    // Preset lama sering membuat laporan terlihat kosong; pastikan batas akhir minimal hari ini.
    const today = todayISO();
    if(!toEl.value || toEl.value < today) {
      toEl.value = today;
    }
    if(fromEl.value && fromEl.value > toEl.value) {
      fromEl.value = monthStartISO();
    }
  }catch(_e){}
}

async function loadData(){
  clearErr();
  const from = fromEl.value;
  const to = toEl.value;
  if(!from || !to){ showErr('Tanggal dari dan sampai wajib diisi.'); return; }
  if(from > to){ showErr('Tanggal dari tidak boleh lebih besar dari tanggal sampai.'); return; }

  const qs = new URLSearchParams({
    from, to,
    gudang_id: gudangEl.value || '',
    produk_id: produkEl.value || ''
  });

  try{
    const r = await fetch(`../../api/stok_periode.php?${qs.toString()}`, { cache: 'no-store' });
    const d = await r.json();
    if(!r.ok || !d.ok) throw new Error(d.msg || `HTTP ${r.status}`);
    dataState.rows = d.data || [];
    dataState.filter = d.filter || {from,to};
    renderSummary(d.summary || {});
    renderRows(d.data || []);
    savePreset();
  }catch(err){
    showErr(err.message || 'Gagal memuat laporan');
  }
}

function exportCsv(){
  const rows = dataState.rows || [];
  if(!rows.length){ showErr('Tidak ada data untuk diexport.'); return; }
  const f = dataState.filter || {};
  const header = ['Produk','Stok Awal','Masuk','Keluar','Stok Akhir'];
  const lines = [header.join(',')];
  rows.forEach(r=>{
    const safeName = `"${String(r.nama_produk || '').replace(/"/g,'""')}"`;
    lines.push([safeName, r.stok_awal || 0, r.masuk || 0, r.keluar || 0, r.stok_akhir || 0].join(','));
  });
  const csv = '\ufeff' + lines.join('\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  const suffix = `${(f.from || fromEl.value)}_${(f.to || toEl.value)}`;
  a.href = URL.createObjectURL(blob);
  a.download = `laporan_stok_periode_${suffix}.csv`;
  document.body.appendChild(a);
  a.click();
  a.remove();
}

function exportXlsx(){
  clearErr();
  const from = fromEl.value;
  const to = toEl.value;
  if(!from || !to){ showErr('Tanggal dari dan sampai wajib diisi.'); return; }
  if(from > to){ showErr('Tanggal dari tidak boleh lebih besar dari tanggal sampai.'); return; }
  const qs = new URLSearchParams({
    from, to,
    gudang_id: gudangEl.value || '',
    produk_id: produkEl.value || ''
  });
  window.location.href = `../../api/stok_periode_export_xlsx.php?${qs.toString()}`;
}

async function openDetail(produkId, namaProduk){
  clearErr();
  const from = fromEl.value;
  const to = toEl.value;
  const qs = new URLSearchParams({
    from, to,
    gudang_id: gudangEl.value || '',
    produk_id: String(produkId)
  });
  try{
    const r = await fetch(`../../api/stok_periode_detail.php?${qs.toString()}`, {cache:'no-store'});
    const d = await r.json();
    if(!r.ok || !d.ok) throw new Error(d.msg || `HTTP ${r.status}`);
    document.getElementById('detailTitle').textContent = `Detail Mutasi: ${namaProduk}`;
    dailyBox.innerHTML = `<div class="box"><strong>Stok Awal</strong><br>${fmt(d.stok_awal || 0)}</div>` +
      (d.daily || []).map(x=>`<div class="box"><strong>${x.tanggal}</strong><br>Masuk: ${fmt(x.masuk)} | Keluar: ${fmt(x.keluar)}</div>`).join('');
    const rows = d.mutasi || [];
    detailBody.innerHTML = rows.length ? rows.map(m=>`
      <tr>
        <td>${String(m.dibuat_pada || '')}</td>
        <td>${String(m.nama_gudang || '-')}</td>
        <td>${String(m.tipe || '')}</td>
        <td class="num">${fmt(m.qty)}</td>
        <td class="num">${fmt(m.stok_sebelum)}</td>
        <td class="num">${fmt(m.stok_sesudah)}</td>
        <td>${String(m.referensi || '-')}</td>
      </tr>
    `).join('') : '<tr><td colspan="7" class="muted">Tidak ada mutasi pada periode ini.</td></tr>';
    detailModal.style.display = 'block';
  }catch(err){
    showErr(err.message || 'Gagal memuat detail mutasi');
  }
}
window.openDetail = openDetail;

document.getElementById('btnLoad').addEventListener('click', loadData);
document.getElementById('btnCsv').addEventListener('click', exportCsv);
document.getElementById('btnXlsx').addEventListener('click', exportXlsx);
loadPreset().then(loadData);
</script>
