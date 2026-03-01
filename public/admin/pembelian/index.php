<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/functions.php';

requireLogin();
requireDevice();

$tokoId = $_SESSION['toko_id'] ?? 0;

// daftar PO siap diterima (approved)
$poList = [];
$stmt = $pos_db->prepare("SELECT po_id, nomor, tanggal, status, s.nama_supplier 
                          FROM purchase_order po
                          LEFT JOIN supplier s ON s.supplier_id = po.supplier_id
                          WHERE po.toko_id=? AND po.status='approved'
                          ORDER BY po.tanggal DESC");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$res = $stmt->get_result();
if($res) $poList = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Penerimaan Barang</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        :root{
            --primary:#0ea5e9;
            --primary-strong:#0284c7;
            --bg:#f5f7fb; --card:#fff; --border:#e2e8f0; --muted:#64748b; --text:#0f172a;
        }
        *{box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans','Inter',system-ui; background:var(--bg); margin:0; color:var(--text);}
        .page{max-width:1100px;margin:32px auto;padding:0 16px;}
        .card{background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow:0 16px 40px rgba(15,23,42,0.08); padding:24px;}
        h1{margin:0 0 6px; font-size:24px;}
        p.lead{margin:0 0 16px; color:var(--muted);}
        label{font-weight:600; font-size:13px;}
        input,select,textarea{width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:10px; font-size:14px; margin-top:6px; background:#f8fafc;}
        textarea{min-height:60px; resize:vertical;}
        .grid{display:grid; gap:14px;}
        .g2{grid-template-columns:repeat(auto-fit,minmax(240px,1fr));}
        .row{display:flex; gap:10px; flex-wrap:wrap;}
        .btn{padding:10px 14px; border:none; border-radius:10px; font-weight:600; cursor:pointer;}
        .btn-primary{background:var(--primary); color:#fff;}
        .btn-ghost{background:#eef2f7; color:var(--text);}
        .btn-outline{background:#fff; color:var(--text); border:1px solid var(--border);}
        .lookup-wrap{display:flex; gap:8px; align-items:center;}
        .lookup-display{flex:1; background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:10px 12px;}
        table{width:100%; border-collapse:collapse; margin-top:12px; border:1px solid var(--border);}
        th,td{padding:10px; border-bottom:1px solid var(--border); font-size:13px; text-align:left;}
        th{background:#f8fafc; color:var(--muted); font-weight:700;}
        .text-right{text-align:right;}
        .totals{max-width:360px; margin-left:auto;}
        .totals div{display:flex; justify-content:space-between; margin:6px 0; font-weight:600;}
        .small{font-size:12px; color:var(--muted);}
        .modal{position:fixed; inset:0; background:rgba(15,23,42,0.4); display:none; align-items:center; justify-content:center; z-index:50;}
        .modal .content{background:#fff; border-radius:14px; width:90%; max-width:760px; max-height:80vh; overflow:hidden; display:flex; flex-direction:column; border:1px solid var(--border);}
        .modal header{padding:14px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;}
        .modal main{padding:12px 18px; overflow:auto; flex:1;}
        .modal table{border:1px solid var(--border);}
        .btn-back-floating {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.9);
            /* Glassmorphism */
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            
            color: #4361ee;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: 50px;
            border: 1px solid rgba(67, 97, 238, 0.2);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer; /* Karena bertingkah seperti tombol */
        }

        .btn-back-floating:hover {
            background: #4361ee;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
        }

        @media (max-width: 375px) {
            .btn-back-floating {
                bottom: 15px;
                left: 15px;
                padding: 8px 14px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Penerimaan Barang</h1>
        <p class="lead">Posting penerimaan terhadap Purchase Order. Stok masuk setelah posting.</p>

        <form id="receiveForm" action="/api/receipt_save.php" method="post">
            <input type="hidden" name="po_id" id="po_id">
            <div class="grid g2">
                <div>
                    <label>Nomor PO</label>
                    <div class="lookup-wrap">
                        <input type="text" id="po_label" class="lookup-display" placeholder="Pilih PO" readonly required>
                        <button type="button" class="btn btn-outline" onclick="openPO()">🔍 Cari</button>
                    </div>
                    <div class="small" id="po_meta" style="color:var(--muted); margin-top:4px;">Belum memilih PO</div>
                </div>
                <div>
                    <label>Tanggal Terima</label>
                    <input type="date" name="tanggal_terima" id="tanggal_terima" value="<?=date('Y-m-d')?>" required>
                </div>
            </div>

            <div class="grid g2">
                <div>
                    <label>Supplier</label>
                    <input type="text" id="supplier_name" class="lookup-display" readonly placeholder="-">
                </div>
                <div>
                    <label>Catatan</label>
                    <textarea name="catatan" id="catatan" placeholder="Catatan penerimaan"></textarea>
                </div>
            </div>

            <div style="margin-top:12px; font-weight:700;">Detail Barang</div>
            <table id="itemTable">
                <thead>
                    <tr>
                        <th >Barang</th>
                        <th style="width:10%;">Satuan</th>
                        <th style="width:10%;">Qty PO</th>
                        <th style="width:12%;">Qty Terima</th>
                        <th style="width:14%;">Harga Final</th>
                        <th style="width:14%;">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="itemBody"><tr><td colspan="6" style="text-align:center; color:var(--muted); padding:16px;">Pilih PO terlebih dahulu</td></tr></tbody>
            </table>

            <div class="totals">
                <div><span>Subtotal</span><span id="t_subtotal">Rp 0</span></div>
                <div><span>Pajak (<span id="jenis_ppn_label">-</span>)</span><span id="t_pajak">Rp 0</span></div>
                <div><span>Total</span><span id="t_total">Rp 0</span></div>
            </div>

            <input type="hidden" name="subtotal" id="subtotal">
            <input type="hidden" name="pajak" id="pajak">
            <input type="hidden" name="total" id="total">
            <input type="hidden" name="supplier_id" id="supplier_id">

            <div class="row" style="justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-ghost" onclick="window.location.href='/public/admin/purchase_order/index.php'">← Kembali ke PO</button>
                <button type="submit" class="btn btn-primary">📦 Posting Penerimaan</button>
            </div>
        </form>
    </div>
</div>

<!-- PO Lookup -->
<div class="modal" id="poModal">
    <div class="content">
        <header>
            <div style="font-weight:700;">Pilih Purchase Order</div>
            <button class="btn btn-outline" onclick="closePO()">Tutup</button>
        </header>
        <main>
            <input type="text" id="poSearch" placeholder="Cari nomor / supplier..." oninput="renderPO()" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
            <div style="max-height:420px; overflow:auto; margin-top:10px;">
                <table>
                    <thead><tr><th>Nomor</th><th>Supplier</th><th>Status</th></tr></thead>
                    <tbody id="poBody"></tbody>
                </table>
            </div>
        </main>
    </div>
</div>
<div class="btn-back-floating" onclick="location.replace('../dashboard.php');">
    <i class="fas fa-chevron-left"></i>
    <span>Kembali</span>
</div>
<script>
const poData = <?php echo json_encode($poList, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const rupiah = v => 'Rp '+Number(v||0).toLocaleString('id-ID');
function openPO(){ document.getElementById('poModal').style.display='flex'; renderPO(); }
function closePO(){ document.getElementById('poModal').style.display='none'; }
function renderPO(){
    const term = document.getElementById('poSearch').value.toLowerCase();
    const body = document.getElementById('poBody');
    body.innerHTML='';
    poData.filter(p => (p.nomor||'').toLowerCase().includes(term) || (p.nama_supplier||'').toLowerCase().includes(term))
          .slice(0,100)
          .forEach(p=>{
            const tr = document.createElement('tr');
            tr.innerHTML = `<td style="cursor:pointer;">${p.nomor}</td><td>${p.nama_supplier||'-'}</td><td>${p.status}</td>`;
            tr.onclick = ()=> selectPO(p.po_id, p.nomor, p.nama_supplier, p.tanggal, p.status);
            body.appendChild(tr);
          });
}

async function selectPO(id, nomor, supplier, tgl, status){
    document.getElementById('po_id').value = id;
    document.getElementById('po_label').value = nomor;
    document.getElementById('supplier_name').value = supplier || '-';
    document.getElementById('po_meta').innerText = `Tanggal: ${tgl || '-'} · Status: ${status}`;
    closePO();

    const res = await fetch(`/api/load_po_items.php?po_id=${id}`);
    const data = await res.json();
    if(data && data.ok === false){
        alert(data.msg || 'PO tidak bisa diproses');
        return;
    }
    const body = document.getElementById('itemBody');
    body.innerHTML='';
    document.getElementById('supplier_id').value = data.supplier_id || '';
    
    // Set pajak info from PO
    document.getElementById('jenis_ppn_label').innerText = data.jenis_ppn || '-';
    const pajakValue = parseFloat(data.pajak) || 0;
    
    (data.items||[]).forEach((it)=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${it.nama_barang}<input type="hidden" name="barang[]" value="${it.nama_barang}"><input type="hidden" name="produk_id[]" value="${it.produk_id||0}"></td>
            <td>${it.satuan || '-'}</td>
            <td>${it.qty}</td>
            <td><input type="number" name="qty[]" min="0" max="${it.qty}" value="${it.qty}" step="0.01" oninput="recalc()" required></td>
            <td><input type="number" name="harga[]" min="0" value="${it.harga}" step="0.01" oninput="recalc()" required></td>
            <td class="text-right subtotal">Rp 0</td>
        `;
        body.appendChild(tr);
    });
    recalc(pajakValue);
}

function recalc(pajakValue = 0){
    let subtotal=0;
    document.querySelectorAll('#itemBody tr').forEach(tr=>{
        const qty = parseFloat(tr.querySelector('input[name="qty[]"]').value||0);
        const harga = parseFloat(tr.querySelector('input[name="harga[]"]').value||0);
        const sub = qty*harga;
        tr.querySelector('.subtotal').innerText = rupiah(sub);
        subtotal += sub;
    });
    const pajak = pajakValue;
    const total = subtotal + pajak;
    document.getElementById('t_subtotal').innerText = rupiah(subtotal);
    document.getElementById('t_pajak').innerText = rupiah(pajak);
    document.getElementById('t_total').innerText = rupiah(total);
    document.getElementById('subtotal').value = subtotal;
    document.getElementById('pajak').value = pajak;
    document.getElementById('total').value = total;
}

document.getElementById('receiveForm').addEventListener('submit', async function(e){
    e.preventDefault();
    if(!document.getElementById('po_id').value){ alert('Pilih PO dulu'); return; }
    const fd = new FormData(this);
    try{
        const r = await fetch(this.action, {method:'POST', body:fd});
        const d = await r.json();
        if(!d.ok) throw new Error(d.msg||'Gagal simpan');
        alert('Penerimaan tersimpan');
        if(d.redirect) window.location = d.redirect; else location.reload();
    }catch(err){ alert(err.message); }
});
</script>
</body>
</html>
