<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/header.php';

$deviceId = $_SESSION['device_id'] ?? 'DEV-' . session_id(); // pastikan device_id ter-set saat login/device_check
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Printer Thermal</title>
<style>
  :root { --border:#e5e7eb; --muted:#6b7280; --primary:#4f46e5; --danger:#dc2626; }
  body { font-family:'Segoe UI',system-ui,sans-serif; margin:0; background:#f7f8fb; color:#111827; }
  .container { max-width:1000px; margin:24px auto; background:#fff; padding:20px; border:1px solid var(--border); border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,0.04); }
  h1 { margin:0 0 12px 0; }
  .form { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px; }
  label { font-weight:600; color:#374151; display:flex; flex-direction:column; gap:6px; }
  input, select { padding:10px; border:1px solid var(--border); border-radius:10px; }
  .actions { margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; }
  button { border:none; border-radius:10px; padding:10px 14px; cursor:pointer; font-weight:600; }
  .primary { background:var(--primary); color:#fff; }
  .secondary { background:#fff; border:1px solid var(--border); }
  .danger { background:var(--danger); color:#fff; }
  .table { width:100%; border-collapse:collapse; margin-top:18px; }
  .table th, .table td { padding:10px; border-bottom:1px solid var(--border); text-align:left; }
  .badge { padding:4px 10px; border-radius:999px; background:#eef2ff; color:#4338ca; font-size:12px; }
  .toast { position:fixed; right:16px; bottom:16px; background:#111827; color:#fff; padding:12px 14px; border-radius:10px; display:none; }
</style>
</head>
<body>
<div class="container">
  <h1>Printer Thermal</h1>
  <p style="color:var(--muted);margin-top:0">Atur printer per perangkat (device_id: <b><?=htmlspecialchars($deviceId)?></b>). Test print via agent lokal.</p>

  <form id="printerForm" class="form">
    <input type="hidden" name="id">
    <input type="hidden" name="device_id" value="<?=htmlspecialchars($deviceId)?>">
    <label>Nama Printer
      <input name="nama" placeholder="Kasir 1 / Dapur" required>
    </label>
    <label>Jenis Koneksi
      <select name="jenis">
        <option value="network">Network (IP)</option>
        <option value="usb">USB / Shared Name</option>
        <option value="bluetooth">Bluetooth</option>
      </select>
    </label>
    <label>Alamat (IP/Share/BT)
      <input name="alamat" placeholder="192.168.1.50 atau \\\\PC\\POS58" required>
    </label>
    <label>Lebar Kertas
      <select name="lebar">
        <option value="58">58 mm</option>
        <option value="80" selected>80 mm</option>
      </select>
    </label>
    <label>Driver / Protocol
      <select name="driver">
        <option value="escpos">ESC/POS</option>
        <option value="star">Star</option>
      </select>
    </label>
    <label>
      <span style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_default" value="1" style="width:auto;"> Jadikan default</span>
    </label>
  </form>
  <div class="actions">
    <button class="primary" onclick="savePrinter()">Simpan</button>
    <button class="secondary" onclick="resetForm()">Reset</button>
    <button class="secondary" onclick="detectOS()">Deteksi Printer OS</button>
    <button class="secondary" onclick="scanNetwork()">Scan IP 9100</button>
    <button class="secondary" onclick="testDefault()">Test Print (Default)</button>
  </div>

  <table class="table" id="printerTable">
    <thead>
      <tr><th>Nama</th><th>Alamat</th><th>Jenis</th><th>Lebar</th><th>Driver</th><th>Default</th><th>Aksi</th></tr>
    </thead>
    <tbody></tbody>
  </table>
</div>
<div class="toast" id="toast"></div>
<script>
const form = document.getElementById('printerForm');
const tableBody = document.querySelector('#printerTable tbody');
const toastEl = document.getElementById('toast');
let state = [];

function toast(msg, ok=true){
  toastEl.textContent = msg;
  toastEl.style.background = ok ? '#111827' : '#dc2626';
  toastEl.style.display = 'block';
  setTimeout(()=>toastEl.style.display='none', 2500);
}
function resetForm(){ form.reset(); form.id.value=''; }
function fillForm(p){
  for(const k in p) if(form[k]) {
    if(form[k].type==='checkbox') form[k].checked = p[k]==1 || p[k]==='1';
    else form[k].value = p[k];
  }
  form.id.value = p.id || '';
}
async function loadData(){
  const r = await fetch('/api/printer_list.php');
  const d = await r.json();
  if(!d.ok) return toast(d.msg,false);
  state = d.data || [];
  render();
}
function render(){
  tableBody.innerHTML = state.map((p,i)=>`
    <tr>
      <td>${p.nama}</td>
      <td>${p.alamat}</td>
      <td><span class="badge">${p.jenis}</span></td>
      <td>${p.lebar} mm</td>
      <td>${p.driver}</td>
      <td>${p.is_default==1?'✔':''}</td>
      <td>
        <button class="secondary" onclick="edit(${i})">Edit</button>
        <button class="danger" onclick="del(${p.id})">Hapus</button>
        <button class="secondary" onclick="test(${i})">Test</button>
      </td>
    </tr>`).join('');
}
function edit(i){ fillForm(state[i]); window.scrollTo({top:0,behavior:'smooth'}); }
async function del(id){
  if(!confirm('Hapus printer ini?')) return;
  const fd = new FormData(); fd.append('id', id); fd.append('device_id', form.device_id.value);
  const r = await fetch('/api/printer_save.php?_method=delete',{method:'POST',body:fd});
  const d = await r.json(); toast(d.msg, d.ok); loadData();
}
async function savePrinter(){
  const fd = new FormData(form);
  const r = await fetch('/api/printer_save.php',{method:'POST',body:fd});
  const d = await r.json(); toast(d.msg, d.ok);
  if(d.ok){ resetForm(); loadData(); }
}
async function test(idx){
  const p = state[idx];
  const fd = new FormData();
  ['alamat','driver','lebar'].forEach(k=>fd.append(k,p[k]));
  const r = await fetch('/api/printer_test.php',{method:'POST',body:fd});
  const d = await r.json(); toast(d.ok?'Test print dikirim':d.msg,d.ok);
}
async function testDefault(){
  const def = state.find(x=>x.is_default==1) || state[0];
  if(!def) return toast('Belum ada printer',false);
  const fd = new FormData();
  ['alamat','driver','lebar'].forEach(k=>fd.append(k,def[k]));
  const r = await fetch('/api/printer_test.php',{method:'POST',body:fd});
  const d = await r.json(); toast(d.ok?'Test print default dikirim':d.msg,d.ok);
}
async function detectOS(){
  try{
    const r = await fetch('http://localhost:19100/list_printers');
    const d = await r.json();
    alert('Ditemukan:\n'+(d.printers||[]).join('\n'));
  }catch(e){ toast('Agent lokal tidak terdeteksi',false); }
}
async function scanNetwork(){
  const base = prompt('Masukkan prefix IP (contoh 192.168.1.):','192.168.1.');
  if(!base) return;
  toast('Scan 9100...');
  // quick scan 5 host saja contoh
  const hosts = [base+'10',base+'20',base+'30',base+'40',base+'50'];
  let found=[];
  await Promise.all(hosts.map(ip=>fetch('/api/ping9100.php?ip='+ip).then(r=>r.json()).then(d=>{if(d.ok)found.push(ip);})));
  alert(found.length ? 'Port 9100 terbuka:\n'+found.join('\n') : 'Tidak ada host terbuka di sample.');
}
loadData();
</script>
</body>
</html>
