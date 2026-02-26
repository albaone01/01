<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/header.php';

$db = $pos_db;

// supplier list
$suppliers = [];
$tokoId = $_SESSION['toko_id'] ?? 0;
$supStmt = $db->prepare("SELECT supplier_id, nama_supplier FROM supplier WHERE toko_id=? ORDER BY nama_supplier");
$supStmt->bind_param("i", $tokoId);
$supStmt->execute();
$sres = $supStmt->get_result();
if($sres) $suppliers = $sres->fetch_all(MYSQLI_ASSOC);
$supStmt->close();

// Jika tidak ada tabel hutang khusus, gunakan placeholder 0
$totalHutang = 0; $jatuhTempo = 0;
try{
    $res = $db->query("SHOW TABLES LIKE 'hutang_supplier'");
    if($res && $res->num_rows){
        $row = $db->query("SELECT COALESCE(SUM(sisa),0) FROM hutang_supplier")->fetch_row();
        $totalHutang = $row[0] ?? 0;
        $row2 = $db->query("SELECT COUNT(*) FROM hutang_supplier WHERE due_date <= CURDATE()")->fetch_row();
        $jatuhTempo = $row2[0] ?? 0;
    }
} catch(Exception $e){}
?>
<!DOCTYPE html>
<html>
<head>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
:root{--primary:#0ea5e9;--muted:#64748b;--border:#e2e8f0;--card:#fff;--shadow:0 14px 40px rgba(15,23,42,0.08);}
body{margin:0;font-family:'Plus Jakarta Sans','Inter',system-ui;background:#f5f7fb;color:#0f172a;}
.page{padding:32px 20px 48px;}
.container{max-width:1200px;margin:0 auto;}
.hero{background:linear-gradient(135deg,#0b0f1f 0%,#111827 60%,#0ea5e9 100%);color:#eaf6ff;border-radius:18px;padding:22px 24px;box-shadow:var(--shadow);}
.hero h1{margin:6px 0 8px;font-size:26px;}
.hero p{margin:0 0 14px;color:rgba(255,255,255,0.85);}
.actions{display:flex;gap:10px;flex-wrap:wrap;}
.btn{padding:10px 14px;border-radius:10px;border:1px solid transparent;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:.2s ease;}
.btn-primary{background:#fff;color:#0f172a;}
.btn-ghost{background:rgba(255,255,255,0.14);color:#fff;border-color:rgba(255,255,255,0.3);}
.panel{margin-top:18px;background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;}
.toolbar{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:center;flex-wrap:wrap;}
.toolbar input{padding:10px 12px;border:1px solid var(--border);border-radius:10px;min-width:240px;font-size:14px;}
.table-wrap{overflow:auto;}
table{width:100%;border-collapse:collapse;font-size:14px;}
thead th{background:#f8fafc;padding:12px 14px;text-align:left;color:var(--muted);border-bottom:1px solid var(--border);}
tbody td{padding:12px 14px;border-bottom:1px solid #eef2f6;}
tbody tr:hover td{background:#f8fbff;}
.metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px;}
.metric{background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.25);border-radius:12px;padding:12px 14px;}
.metric small{color:rgba(255,255,255,0.8);letter-spacing:0.06em;font-weight:700;}
.metric-value{font-size:20px;font-weight:800;margin-top:6px;}
.meta{color:var(--muted);font-size:12px;}
@media(max-width:760px){.metrics{grid-template-columns:1fr;}.toolbar{flex-direction:column;align-items:stretch;}}
.modal{position:fixed; inset:0; background:rgba(15,23,42,0.45); display:none; align-items:center; justify-content:center; z-index:50;}
.modal-box{background:#fff; border-radius:14px; width:90%; max-width:520px; box-shadow:0 18px 40px rgba(15,23,42,0.12); overflow:hidden;}
.modal-header{padding:14px 16px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border);}
.modal-body{padding:16px; display:grid; gap:12px;}
.modal-body label{font-weight:600; font-size:13px; color:#0f172a;}
.modal-body input, .modal-body select{
    width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:10px;
    background:#f8fafc; font-size:14px;
}
.modal-footer{padding:14px 16px; display:flex; gap:10px; justify-content:flex-end; border-top:1px solid var(--border);}
.lookup-wrap{display:flex; gap:8px; align-items:center;}
.lookup-display{flex:1; background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:10px 12px;}
.btn{padding:10px 12px; border-radius:10px; border:1px solid transparent; font-weight:700; cursor:pointer;}
.btn-outline{background:#fff; color:#0f172a; border:1px solid var(--border);}
</style>
</head>
<body>
<div class="page">
  <div class="container">
    <div class="hero">
        <p style="letter-spacing:0.08em;font-weight:800;margin:0;">Hutang</p>
        <h1>Hutang ke Supplier</h1>
        <p>Pantau saldo hutang, jatuh tempo, dan pembayaran yang perlu diprioritaskan.</p>
        <div class="actions">
            <button class="btn btn-primary" onclick="openModal()">+ Catat Hutang</button>
            <button class="btn btn-ghost" onclick="window.location='/api/hutang_supplier_export.php'">Export</button>
        </div>
        <div class="metrics">
            <div class="metric"><small>Total Hutang</small><div class="metric-value">Rp <?=number_format($totalHutang,0,',','.')?></div></div>
            <div class="metric"><small>Jatuh Tempo</small><div class="metric-value"><?=$jatuhTempo?></div></div>
            <div class="metric"><small>Status</small><div class="metric-value"><?=$totalHutang>0?'Ada kewajiban':'Nol'?></div></div>
        </div>
    </div>

    <div class="panel">
        <form class="toolbar" method="get">
            <input type="text" name="q" placeholder="Cari supplier / invoice" value="<?=htmlspecialchars($_GET['q'] ?? '')?>">
            <button class="btn btn-primary" type="submit">Cari</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Supplier</th><th>Invoice</th><th>Sisa</th><th>Jatuh Tempo</th><th>Status</th></tr></thead>
                <tbody>
                    <?php
                    $res = $db->query("SHOW TABLES LIKE 'hutang_supplier'");
                    if($res && $res->num_rows){
                        $tokoId = $_SESSION['toko_id'] ?? 0;
                        $rows = $db->query("SELECT supplier, invoice, sisa, due_date, status FROM hutang_supplier WHERE toko_id={$tokoId} ORDER BY dibuat_pada DESC LIMIT 100");
                        if($rows && $rows->num_rows){
                            while($r=$rows->fetch_assoc()){
                                echo '<tr>';
                                echo '<td>'.htmlspecialchars($r['supplier']).'</td>';
                                echo '<td>'.htmlspecialchars($r['invoice']).'</td>';
                                echo '<td>Rp '.number_format($r['sisa'],0,',','.').'</td>';
                                echo '<td class=\"meta\">'.htmlspecialchars($r['due_date'] ?? '-').'</td>';
                                echo '<td><span class=\"badge\">'.htmlspecialchars($r['status']).'</span></td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan=\"5\" style=\"text-align:center;color:var(--muted);padding:18px;\">Belum ada hutang.</td></tr>';
                        }
                    } else {
                        echo '<tr><td colspan=\"5\" style=\"text-align:center;color:var(--muted);padding:18px;\">Belum ada hutang.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
  </div>
</div>

<div class="modal" id="hutangModal">
  <div class="modal-box">
    <div class="modal-header">
        <h3 style="margin:0;">Catat Hutang</h3>
        <span style="cursor:pointer;font-size:22px;" onclick="closeModal()">&times;</span>
    </div>
    <form id="hutangForm">
        <div class="modal-body">
            <div>
                <label>Supplier</label>
                <div class="lookup-wrap">
                    <input type="hidden" name="supplier" id="supplier_value" required>
                    <input type="text" id="supplier_name" class="lookup-display" placeholder="Pilih supplier" readonly required>
                    <button type="button" class="btn btn-outline" onclick="openSup()">🔍</button>
                </div>
            </div>
            <div><label>Invoice</label><input name="invoice" required></div>
            <div><label>Sisa</label><input type="number" step="0.01" name="sisa" required></div>
            <div><label>Jatuh Tempo</label><input type="date" name="due_date"></div>
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="tercatat">Tercatat</option>
                    <option value="jatuh tempo">Jatuh Tempo</option>
                    <option value="lunas">Lunas</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal()">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
  </div>
</div>

<!-- Supplier lookup -->
<div class="modal" id="supModal" style="display:none;">
  <div class="modal-box">
    <div class="modal-header">
        <h3 style="margin:0;">Pilih Supplier</h3>
        <span style="cursor:pointer;font-size:22px;" onclick="closeSup()">&times;</span>
    </div>
    <div class="modal-body">
        <input type="text" id="supSearch" placeholder="Cari supplier..." oninput="renderSup()" style="width:100%;padding:8px 10px;margin-bottom:10px;">
        <div style="max-height:320px;overflow:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr><th style="text-align:left;padding:8px;">Nama</th></tr></thead>
                <tbody id="supBody"></tbody>
            </table>
        </div>
    </div>
  </div>
</div>

<script>
const modal = document.getElementById('hutangModal');
const form = document.getElementById('hutangForm');
function openModal(){ modal.style.display='block'; }
function closeModal(){ modal.style.display='none'; }
form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const btn = form.querySelector('button[type=\"submit\"]');
    btn.textContent='Menyimpan...'; btn.disabled=true;
    try{
        const fd = new FormData(form);
        const r = await fetch('/api/hutang_supplier_save.php',{method:'POST',body:fd});
        const d = await r.json();
        if(!d.ok) throw new Error(d.msg || 'Gagal simpan');
        location.reload();
    }catch(err){ alert(err.message); }
    finally{ btn.textContent='Simpan'; btn.disabled=false; }
});
window.onclick = (e)=>{ if(e.target===modal) closeModal(); };

const supData = <?php echo json_encode($suppliers, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const supModal = document.getElementById('supModal');
const supBody = document.getElementById('supBody');
function openSup(){ document.getElementById('supSearch').value=''; renderSup(); supModal.style.display='block'; }
function closeSup(){ supModal.style.display='none'; }
function renderSup(){
    const term = document.getElementById('supSearch').value.toLowerCase();
    supBody.innerHTML='';
    supData.filter(s=> (s.nama_supplier||'').toLowerCase().includes(term)).slice(0,100).forEach(s=>{
        const tr=document.createElement('tr');
        tr.innerHTML=`<td style="padding:8px;cursor:pointer;">${s.nama_supplier}</td>`;
        tr.onclick=()=>{
            document.getElementById('supplier_value').value = s.nama_supplier;
            document.getElementById('supplier_name').value = s.nama_supplier;
            closeSup();
        };
        supBody.appendChild(tr);
    });
}
window.addEventListener('click',(e)=>{ if(e.target===supModal) closeSup(); });
</script>
</body>
</html>
