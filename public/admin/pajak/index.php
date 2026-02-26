<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/header.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master Pajak</title>
<style>
    body { font-family:'Segoe UI',system-ui,sans-serif; margin:0; background:#f6f7fb; color:#111827; }
    .container { max-width: 1000px; margin:24px auto; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px; box-shadow:0 8px 20px rgba(0,0,0,0.04); }
    h1 { margin:0 0 12px 0; }
    .actions { display:flex; gap:10px; margin-bottom:12px; }
    button { border:none; border-radius:10px; padding:10px 14px; cursor:pointer; font-weight:600; }
    .primary { background:#4f46e5; color:#fff; }
    .secondary { background:#fff; border:1px solid #e5e7eb; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:10px; border-bottom:1px solid #e5e7eb; text-align:left; }
    th { color:#6b7280; font-weight:600; background:#f9fafb; }
    .badge { padding:4px 10px; border-radius:999px; font-size:12px; }
    .badge-on { background:#ecfdf3; color:#027a48; }
    .badge-off { background:#fef2f2; color:#b91c1c; }
    .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:1000; }
    .modal-content { background:#fff; width:90%; max-width:480px; margin:8% auto; border-radius:12px; padding:18px 20px; box-shadow:0 12px 30px rgba(0,0,0,0.15); }
    label { display:block; margin:8px 0 4px; font-weight:600; color:#374151; }
    input, textarea { width:100%; padding:10px 12px; border:1px solid #dfe3e6; border-radius:10px; box-sizing:border-box; }
    textarea { resize:vertical; min-height:70px; }
    .toast { position:fixed; right:16px; bottom:16px; background:#111827; color:#fff; padding:12px 14px; border-radius:10px; display:none; }
</style>
</head>
<body>
<div class="container">
    <h1>Master Pajak</h1>
    <p style="color:#6b7280;margin-top:0;">Tambah/edit/hapus jenis pajak (PPN, Non PPN, layanan, dll).</p>
    <div class="actions">
        <button class="primary" onclick="openModal()">+ Pajak Baru</button>
        <button class="secondary" onclick="loadData()">Refresh</button>
    </div>
    <table>
        <thead>
            <tr><th>Nama</th><th>Tarif</th><th>Deskripsi</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody id="rows"></tbody>
    </table>
</div>

<div class="modal" id="modal">
  <div class="modal-content">
    <h3 id="mTitle" style="margin-top:0;">Tambah Pajak</h3>
    <form id="formPajak">
      <input type="hidden" name="pajak_id">
      <label>Nama Pajak</label>
      <input name="nama" required placeholder="PPN 11%">
      <label>Persentase (%)</label>
      <input type="number" step="0.01" min="0" max="100" name="persen" required>
      <label>Deskripsi</label>
      <textarea name="deskripsi" placeholder="Catatan opsional"></textarea>
      <label style="display:flex;align-items:center;gap:8px;margin-top:10px;">
        <input type="checkbox" name="aktif" value="1" checked style="width:auto;"> Aktif
      </label>
      <div style="margin-top:14px;display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="secondary" onclick="closeModal()">Batal</button>
        <button type="submit" class="primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
<div class="toast" id="toast"></div>

<script>
const rowsEl = document.getElementById('rows');
const modal = document.getElementById('modal');
const form = document.getElementById('formPajak');
const toastEl = document.getElementById('toast');
let data = [];

function toast(msg, ok=true){
  toastEl.textContent = msg;
  toastEl.style.background = ok ? '#111827' : '#b91c1c';
  toastEl.style.display = 'block';
  setTimeout(()=>toastEl.style.display='none',2200);
}

function openModal(item=null){
  form.reset();
  form.pajak_id.value = '';
  document.getElementById('mTitle').innerText = item ? 'Edit Pajak' : 'Tambah Pajak';
  if(item){
    for(const k in item){ if(form[k]) form[k].value = item[k]; }
    form.aktif.checked = item.aktif == 1;
  }
  modal.style.display='block';
}
function closeModal(){ modal.style.display='none'; }
window.onclick = e => { if(e.target===modal) closeModal(); }

async function loadData(){
  const r = await fetch('/api/pajak_list.php');
  const d = await r.json();
  if(!d.ok) return toast(d.msg,false);
  data = d.data || [];
  render();
}
function render(){
  rowsEl.innerHTML = data.map(p=>`
    <tr>
      <td>${p.nama}</td>
      <td>${parseFloat(p.persen).toFixed(2)}%</td>
      <td>${p.deskripsi||''}</td>
      <td><span class="badge ${p.aktif==1?'badge-on':'badge-off'}">${p.aktif==1?'Aktif':'Nonaktif'}</span></td>
      <td>
        <button class="secondary" onclick='openModal(${JSON.stringify(p)})'>Edit</button>
        <button class="secondary" onclick='hapus(${p.pajak_id})' style="color:#b91c1c;border-color:#fecdd3;">Hapus</button>
      </td>
    </tr>
  `).join('');
}

form.addEventListener('submit', async e=>{
  e.preventDefault();
  const fd = new FormData(form);
  fd.set('aktif', form.aktif.checked ? 1 : 0);
  const r = await fetch('/api/pajak_save.php',{method:'POST',body:fd});
  const d = await r.json();
  toast(d.msg, d.ok);
  if(d.ok){ closeModal(); loadData(); }
});

async function hapus(id){
  if(!confirm('Hapus pajak ini?')) return;
  const fd = new FormData(); fd.append('pajak_id', id);
  const r = await fetch('/api/pajak_delete.php',{method:'POST',body:fd});
  const d = await r.json();
  toast(d.msg, d.ok);
  if(d.ok) loadData();
}

loadData();
</script>
</body>
</html>
