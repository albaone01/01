<?php
session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/functions.php';

requireLogin();
requireDevice();

$tokoId = $_SESSION['toko_id'] ?? 0;

// Supplier & produk data untuk lookup
$suppliers = [];
$supStmt = $pos_db->prepare("SELECT supplier_id, nama_supplier, telepon, alamat FROM supplier WHERE toko_id=? ORDER BY nama_supplier");
$supStmt->bind_param("i", $tokoId);
$supStmt->execute();
$res = $supStmt->get_result();
if($res) $suppliers = $res->fetch_all(MYSQLI_ASSOC);
$supStmt->close();

$products = [];
$prodStmt = $pos_db->prepare("SELECT produk_id, nama_produk, sku, satuan, harga_modal FROM produk WHERE toko_id=? AND deleted_at IS NULL ORDER BY nama_produk LIMIT 500");
$prodStmt->bind_param("i", $tokoId);
$prodStmt->execute();
$resP = $prodStmt->get_result();
if($resP) $products = $resP->fetch_all(MYSQLI_ASSOC);
$prodStmt->close();

// nomor PO default
$defaultNo = 'PO-' . date('Ymd') . '-' . substr(str_shuffle('1234567890'), 0, 3);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        :root{
            --primary:#0ea5e9;
            --primary-strong:#0284c7;
            --bg:#f5f7fb;
            --card:#ffffff;
            --border:#e2e8f0;
            --muted:#64748b;
            --text:#0f172a;
        }
        *{box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans','Inter',system-ui; background:var(--bg); margin:0; color:var(--text);}
        .page{max-width:1100px;margin:32px auto;padding:0 16px;}
        .card{background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow:0 16px 40px rgba(15,23,42,0.08); padding:24px;}
        h1{margin:0 0 4px; font-size:24px;}
        p.lead{margin:0 0 16px; color:var(--muted);}
        label{font-weight:600; font-size:13px; color:var(--text);}
        input, select, textarea{width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:10px; font-size:14px; margin-top:6px; background:#f8fafc;}
        textarea{min-height:70px; resize:vertical;}
        .grid{display:grid; gap:14px;}
        .g2{grid-template-columns:repeat(auto-fit,minmax(240px,1fr));}
        .row{display:flex; gap:12px; flex-wrap:wrap;}
        .btn{padding:10px 14px; border:none; border-radius:10px; font-weight:600; cursor:pointer;}
        .btn-primary{background:var(--primary); color:#fff;}
        .btn-ghost{background:#eef2f7; color:var(--text);}
        .btn-outline{background:#fff; color:var(--text); border:1px solid var(--border);}
        .section-title{margin-top:20px; font-size:16px; font-weight:700;}
        table{width:100%; border-collapse:collapse; margin-top:10px; border:1px solid var(--border);}
        th,td{padding:10px; border-bottom:1px solid var(--border); font-size:13px; text-align:left;}
        th{background:#f8fafc; color:var(--muted); font-weight:700;}
        td input{width:100%;}
        .text-right{text-align:right;}
        .pill{padding:6px 10px; background:#e2e8f0; border-radius:8px; font-size:12px; display:inline-block;}
        .lookup-wrap{display:flex; gap:8px; align-items:center;}
        .lookup-display{flex:1; background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:10px 12px;}
        .modal{position:fixed; inset:0; background:rgba(15,23,42,0.4); display:none; align-items:center; justify-content:center; z-index:50;}
        .modal .content{background:#fff; border-radius:14px; width:90%; max-width:760px; max-height:80vh; overflow:hidden; display:flex; flex-direction:column; border:1px solid var(--border);}
        .modal header{padding:14px 18px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;}
        .modal main{padding:12px 18px; overflow:auto; flex:1;}
        .modal table{border:1px solid var(--border);}
        .chip{padding:6px 10px; background:#e0f2fe; color:#0369a1; border-radius:999px; font-size:12px;}
        .totals{max-width:360px; margin-left:auto;}
        .totals div{display:flex; justify-content:space-between; margin:6px 0; font-weight:600;}
        .danger{color:#ef4444;}
        .small{font-size:12px; color:var(--muted);}
        tr.active{background:#e0f2fe;}
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
        <h1>Purchase Order</h1>
        <p class="lead">Rencana pembelian barang ke supplier. Barang belum menambah stok sampai dibuatkan penerimaan.</p>

        <form id="poForm" method="post" action="../../../api/purchase_order_save.php">
            <div class="grid g2">
                <div>
                    <label>Nomor PO</label>
                    <input name="nomor" id="nomor" required value="<?=htmlspecialchars($defaultNo)?>">
                </div>
                <div>
                    <label>Tanggal PO</label>
                    <input type="date" name="tanggal" id="tanggal" required value="<?=date('Y-m-d')?>" onchange="updateTempoDate()">
                </div>
            </div>

            <div class="grid g2">
                <div>
                    <label>Supplier</label>
                    <div class="lookup-wrap">
                        <input type="hidden" name="supplier_id" id="supplier_id" required>
                        <input type="text" id="supplier_name" class="lookup-display" placeholder="Pilih supplier" readonly required>
                        <button type="button" class="btn btn-outline" onclick="openLookup('supplier')">🔍 Cari</button>
                    </div>
                    <div class="small" id="supplier_extra">Pilih supplier untuk mengisi detail kontak.</div>
                </div>
                <div>
                    <label>Tipe Faktur</label>
                    <div class="row" style="gap:8px;">
                        <label class="pill"><input type="radio" name="tipe_faktur" value="cash" checked onchange="toggleTempo()"> Cash</label>
                        <label class="pill"><input type="radio" name="tipe_faktur" value="tempo" onchange="toggleTempo()"> Tempo</label>
                    </div>
                    <div class="row" style="margin-top:6px;">
                        <div style="flex:1;">
                            <label>Tempo (hari)</label>
                            <input type="number" min="0" name="tempo_hari" id="tempo_hari" value="0" disabled oninput="updateTempoDate()">
                        </div>
                        <div style="flex:1;">
                            <label>Jatuh Tempo</label>
                            <input type="date" name="jatuh_tempo" id="jatuh_tempo" disabled>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid g2">
                <div>
                    <label>Jenis PPN</label>
                    <select name="jenis_ppn" id="jenis_ppn" onchange="recalc()">
                        <option value="">Non PPN</option>
                        <option value="PPN 11%">PPN 11%</option>
                        <option value="PPN 1.1%">PPN 1.1%</option>
                    </select>
                </div>
                <div>
                    <label>Salesman / PIC</label>
                    <input name="salesman" id="salesman" placeholder="Opsional">
                </div>
            </div>

            <div class="grid g2">
                <div>
                    <label>Catatan</label>
                    <textarea name="catatan" id="catatan" placeholder="Catatan untuk supplier atau internal"></textarea>
                </div>
                <div class="grid g2">
                    <div>
                        <label>Diskon (Rp)</label>
                        <input type="number" step="0.01" name="diskon" id="diskon" value="0" oninput="recalc()">
                    </div>
                    <div>
                        <label>Ongkir (Rp)</label>
                        <input type="number" step="0.01" name="ongkir" id="ongkir" value="0" oninput="recalc()">
                    </div>
                </div>
            </div>

            <div class="section-title">Daftar Barang</div>
            <table id="itemTable">
                <thead>
                    <tr>
                        <th style="width:34%;">Produk</th>
                        <th style="width:10%;">Qty</th>
                        <th style="width:12%;">Satuan</th>
                        <th style="width:16%;">Harga</th>
                        <th style="width:16%;">Subtotal</th>
                        <th style="width:6%;"></th>
                    </tr>
                </thead>
                <tbody id="itemBody"></tbody>
            </table>
            <div style="margin-top:8px;">
                <button type="button" class="btn btn-outline" onclick="addRow()">+ Tambah Baris</button>
            </div>

            <div class="totals">
                <div><span>Subtotal</span><span id="t_subtotal">Rp 0</span></div>
                <div><span>Pajak</span><span id="t_pajak">Rp 0</span></div>
                <div><span>Diskon</span><span id="t_diskon">Rp 0</span></div>
                <div><span>Ongkir</span><span id="t_ongkir">Rp 0</span></div>
                <div style="font-size:17px;"><span>Total</span><span id="t_total">Rp 0</span></div>
            </div>

            <input type="hidden" name="subtotal" id="subtotal">
            <input type="hidden" name="pajak" id="pajak">
            <input type="hidden" name="total" id="total">
            <input type="hidden" name="status" id="statusField" value="draft">

            <div class="row" style="margin-top:18px; justify-content:flex-end;">
                <button type="button" class="btn btn-ghost" onclick="submitPO('draft')">💾 Simpan Draft</button>
                <button type="button" class="btn btn-primary" onclick="submitPO('approved')">✅ Ajukan PO</button>
            </div>
        </form>
    </div>
</div>

<!-- Lookup Modal -->
<div class="modal" id="lookupModal">
    <div class="content">
        <header>
            <div id="lookupTitle" style="font-weight:700;">Pilih</div>
            <button class="btn btn-outline" onclick="closeLookup()">Tutup</button>
        </header>
        <main>
            <input type="text" id="lookupSearch" placeholder="Ketik untuk mencari..." oninput="renderLookup()" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
            <div style="max-height:420px; overflow:auto; margin-top:10px;">
                <table>
                    <thead><tr><th>Nama</th><th style="width:120px;">Info</th></tr></thead>
                    <tbody id="lookupBody"></tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Lookup Produk Modal -->
<div class="modal" id="productModal">
    <div class="content">
        <header>
            <div style="font-weight:700;">Pilih Produk</div>
            <button class="btn btn-outline" onclick="closeProduct()">Tutup</button>
        </header>
        <main>
            <input type="text" id="productSearch" placeholder="Cari nama / SKU..." oninput="renderProduct()" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:10px;">
            <div style="max-height:420px; overflow:auto; margin-top:10px;">
                <table>
                    <thead><tr><th>Produk</th><th>SKU</th><th>Harga Modal</th></tr></thead>
                    <tbody id="productBody"></tbody>
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
        const supplierData = <?php echo json_encode($suppliers, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
        const productData  = <?php echo json_encode($products, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
        const rupiah = (v)=> 'Rp ' + Number(v||0).toLocaleString('id-ID');

        let lookupType = null;
        let currentRowIndex = null;
        let lookupList = [];
        let productList = [];
        let lookupHighlight = -1;
        let productHighlight = -1;

        function attachAutoClear(input){
            if(!input) return;
            input.addEventListener('focus', ()=>{
                if(input.value === '0' || input.value === '0.00'){ input.value=''; }
            });
            input.addEventListener('blur', ()=>{
                if(input.value === '') input.value = 0;
            });
        }

        function focusFirstItem(){
            let target = document.querySelector('#itemBody .item-name');
            if(!target){
                addRow();
                target = document.querySelector('#itemBody .item-name');
            }
            if(target){ target.focus(); target.select && target.select(); }
        }

        function highlightRow(body, idx){
            [...body.children].forEach((tr,i)=>{
                tr.classList.toggle('active', i===idx);
            });
        }

        function ensureTrailingRow(currentRow){
            const rows = document.querySelectorAll('#itemBody tr');
            const isLast = rows.length && currentRow === rows[rows.length-1];
            if(isLast && currentRow.querySelector('.item-name').value.trim() !== ''){
                addRow();
                const nextInput = document.querySelector('#itemBody tr:last-child .item-name');
                if(nextInput) nextInput.focus();
            } else if(!isLast){
                const next = currentRow.nextElementSibling;
                if(next){
                    const nextInput = next.querySelector('.item-name');
                    if(nextInput) nextInput.focus();
                }
            }
        }

        // ===== FULL KEYBOARD NAVIGATION =====

        // Supplier lookup keyboard navigation
        function openLookup(type){
            lookupType = type;
            lookupHighlight = -1;
            document.getElementById('lookupTitle').innerText = 'Pilih ' + (type==='supplier'?'Supplier':'');
            document.getElementById('lookupSearch').value = '';
            renderLookup();
            document.getElementById('lookupModal').style.display='flex';
            setTimeout(()=>{
                document.getElementById('lookupSearch').focus();
            },100);
        }
        function closeLookup(){ 
            document.getElementById('lookupModal').style.display='none'; 
            // Return focus to supplier button
            const supplierBtn = document.querySelector('button[onclick="openLookup(\'supplier\')"]');
            if(supplierBtn) supplierBtn.focus();
        }

        function renderLookup(){
            const term = document.getElementById('lookupSearch').value.toLowerCase();
            const body = document.getElementById('lookupBody');
            body.innerHTML = '';
            lookupList = lookupType==='supplier' ? supplierData : [];
            lookupList = lookupList.filter(r => (r.nama_supplier||'').toLowerCase().includes(term)).slice(0,100);
            
            lookupList.forEach((r, i)=>{
                const tr = document.createElement('tr');
                tr.dataset.index = i;
                tr.innerHTML = `<td style="cursor:pointer;">${r.nama_supplier}</td><td class="small">${r.telepon||'-'}</td>`;
                tr.onclick = ()=>selectLookupItem(i);
                body.appendChild(tr);
            });
            
            if(lookupList.length > 0){
                highlightLookupRow();
            }
        }

        function highlightLookupRow(){
            const rows = document.querySelectorAll('#lookupBody tr');
            rows.forEach((tr, i)=>{
                tr.classList.toggle('active', i === lookupHighlight);
                if(i === lookupHighlight){
                    tr.scrollIntoView({block:'nearest'});
                }
            });
        }

        function selectLookupItem(idx){
            if(idx >= 0 && idx < lookupList.length){
                const r = lookupList[idx];
                document.getElementById('supplier_id').value = r.supplier_id;
                document.getElementById('supplier_name').value = r.nama_supplier;
                document.getElementById('supplier_extra').innerText = (r.telepon||'-') + (r.alamat ? ' · '+r.alamat : '');
                closeLookup();
                // Move to next field (Tipe Faktur)
                setTimeout(()=>{
                    const tempoRadio = document.querySelector('input[name="tipe_faktur"][value="tempo"]');
                    if(tempoRadio) tempoRadio.focus();
                },100);
            }
        }

        // Supplier lookup keyboard handler
        document.addEventListener('keydown', function(e){
            if(document.getElementById('lookupModal').style.display === 'flex'){
                const body = document.getElementById('lookupBody');
                const rows = body.querySelectorAll('tr');
                
                if(e.key === 'ArrowDown'){
                    e.preventDefault();
                    if(lookupHighlight < rows.length - 1){
                        lookupHighlight++;
                        highlightLookupRow();
                    }
                }else if(e.key === 'ArrowUp'){
                    e.preventDefault();
                    if(lookupHighlight > 0){
                        lookupHighlight--;
                        highlightLookupRow();
                    }
                }else if(e.key === 'Enter'){
                    e.preventDefault();
                    selectLookupItem(lookupHighlight >= 0 ? lookupHighlight : 0);
                }else if(e.key === 'Escape'){
                    e.preventDefault();
                    closeLookup();
                }
            }
        });

        // Product lookup keyboard navigation
        function openProduct(idx){
            currentRowIndex = idx;
            productHighlight = 0; // Start at first item
            document.getElementById('productSearch').value='';
            renderProduct();
            document.getElementById('productModal').style.display='flex';
            setTimeout(()=>{
                // Don't focus on search, Arrow Down goes directly to list
                highlightProductRow();
            },100);
        }
        function closeProduct(){ 
            document.getElementById('productModal').style.display='none';
            // Return focus to the search button
            const row = document.querySelector(`[data-row="${currentRowIndex}"]`);
            if(row){
                const searchBtn = row.querySelector('button[onclick^="openProduct"]');
                if(searchBtn) searchBtn.focus();
            }
        }

        function renderProduct(){
            const term = document.getElementById('productSearch').value.toLowerCase();
            const body = document.getElementById('productBody');
            body.innerHTML='';
            productList = productData.filter(p=>(p.nama_produk||'').toLowerCase().includes(term) || (p.sku||'').toLowerCase().includes(term)).slice(0,200);
            
            productList.forEach((p, i)=>{
                const tr=document.createElement('tr');
                tr.dataset.index = i;
                tr.innerHTML=`<td style="cursor:pointer;">${p.nama_produk}</td><td>${p.sku||'-'}</td><td>${rupiah(p.harga_modal)}</td>`;
                tr.onclick=()=>selectProductItem(i);
                body.appendChild(tr);
            });
            
            if(productList.length > 0){
                highlightProductRow();
            }
        }

        function highlightProductRow(){
            const rows = document.querySelectorAll('#productBody tr');
            rows.forEach((tr, i)=>{
                tr.classList.toggle('active', i === productHighlight);
                if(i === productHighlight){
                    tr.scrollIntoView({block:'nearest'});
                }
            });
        }

        function selectProductItem(idx){
            if(idx >= 0 && idx < productList.length){
                const p = productList[idx];
                const row = document.querySelector(`[data-row="${currentRowIndex}"]`);
                if(row){
                    row.querySelector('.item-name').value = p.nama_produk;
                    row.querySelector('.item-nama-hidden').value = p.nama_produk;
                    row.querySelector('.item-product-id').value = p.produk_id;
                    row.querySelector('.item-satuan').value = p.satuan || '';
                    row.querySelector('.item-harga').value = p.harga_modal || 0;
                    recalcRow(row);
                    closeProduct();
                    // Move to qty field
                    setTimeout(()=>{
                        const qtyInput = row.querySelector('.item-qty');
                        if(qtyInput){
                            qtyInput.focus();
                            qtyInput.select();
                        }
                    },100);
                    return;
                }
                closeProduct();
            }
        }

        // Product lookup keyboard handler - Arrow Down goes to first product immediately
        document.addEventListener('keydown', function(e){
            if(document.getElementById('productModal').style.display === 'flex'){
                const body = document.getElementById('productBody');
                const rows = body.querySelectorAll('tr');
                
                if(e.key === 'ArrowDown'){
                    e.preventDefault();
                    // Always go to first product when Arrow Down is pressed from search
                    if(productHighlight < rows.length - 1){
                        productHighlight++;
                    } else {
                        productHighlight = 0; // Loop back to top
                    }
                    highlightProductRow();
                }else if(e.key === 'ArrowUp'){
                    e.preventDefault();
                    if(productHighlight > 0){
                        productHighlight--;
                    } else {
                        productHighlight = rows.length - 1; // Loop to bottom
                    }
                    highlightProductRow();
                }else if(e.key === 'Enter'){
                    e.preventDefault();
                    // Select currently highlighted product
                    if(productList.length > 0){
                        selectProductItem(productHighlight >= 0 ? productHighlight : 0);
                    }
                }else if(e.key === 'Escape'){
                    e.preventDefault();
                    closeProduct();
                }
            }
        });

        function addRow(){
            const body = document.getElementById('itemBody');
            const idx = body.children.length;
            const tr = document.createElement('tr');
            tr.dataset.row = idx;
            tr.innerHTML = `
                <td>
                    <div class="lookup-wrap">
                        <input type="hidden" class="item-product-id" name="item_product_id[]">
                        <input type="hidden" class="item-nama-hidden" name="item_nama[]">
                        <input type="text" class="item-name lookup-display" placeholder="Ketik nama / pilih produk" required>
                        <button type="button" class="btn btn-outline btn-search-product" data-idx="${idx}">🔍</button>
                    </div>
                </td>
                <td><input type="number" class="item-qty" min="0" step="0.01" value="1" oninput="recalcRow(this.closest('tr'))"></td>
                <td><input type="text" class="item-satuan" placeholder="PCS"></td>
                <td><input type="number" class="item-harga" min="0" step="0.01" value="0" oninput="recalcRow(this.closest('tr'))"></td>
                <td class="text-right item-subtotal">Rp 0</td>
                <td><button type="button" class="btn btn-outline danger" onclick="removeRow(this)">✕</button></td>
            `;
            body.appendChild(tr);
            attachAutoClear(tr.querySelector('.item-harga'));
            
            const nameInput = tr.querySelector('.item-name');
            const searchBtn = tr.querySelector('.btn-search-product');
            const qtyInput = tr.querySelector('.item-qty');
            const satuanInput = tr.querySelector('.item-satuan');
            const hargaInput = tr.querySelector('.item-harga');
            
            if(nameInput){
                nameInput.addEventListener('keydown', e=>{
                    if(e.key === 'Enter'){
                        e.preventDefault();
                        // If no product selected, open product modal
                        if(nameInput.value.trim() === '' || nameInput.dataset.manual === '1'){
                            nameInput.dataset.manual = '1';
                            openProduct(idx);
                        }else{
                            ensureTrailingRow(tr);
                        }
                    }
                });
                nameInput.addEventListener('blur', ()=>{
                    if(nameInput.value.trim()!=='') {
                        nameInput.dataset.manual = '1'; // Mark as manually entered
                        ensureTrailingRow(tr);
                    }
                });
            }
            
            // Search button click handler and keyboard support
            if(searchBtn){
                searchBtn.onclick = function(){
                    openProduct(idx);
                };
                // Add keyboard handler for Enter key
                searchBtn.addEventListener('keydown', function(e){
                    if(e.key === 'Enter'){
                        e.preventDefault();
                        e.stopPropagation();
                        openProduct(idx);
                    }
                });
            }
            
            // Qty field - Enter moves to satuan
            if(qtyInput){
                qtyInput.addEventListener('keydown', e=>{
                    if(e.key === 'Enter'){
                        e.preventDefault();
                        if(satuanInput) satuanInput.focus();
                    }
                });
            }
            
            // Satuan field - Enter moves to harga
            if(satuanInput){
                satuanInput.addEventListener('keydown', e=>{
                    if(e.key === 'Enter'){
                        e.preventDefault();
                        if(hargaInput) {
                            hargaInput.focus();
                            hargaInput.select();
                        }
                    }
                });
            }
            
            // Harga field - Enter adds new row
            if(hargaInput){
                hargaInput.addEventListener('keydown', e=>{
                    if(e.key === 'Enter'){
                        e.preventDefault();
                        recalcRow(tr);
                        // Add new row and focus
                        const rows = document.querySelectorAll('#itemBody tr');
                        const isLast = tr === rows[rows.length-1];
                        if(isLast){
                            addRow();
                            const newRow = document.querySelector('#itemBody tr:last-child');
                            if(newRow){
                                const newNameInput = newRow.querySelector('.item-name');
                                if(newNameInput) newNameInput.focus();
                            }
                        }else{
                            // Move to next row
                            const next = tr.nextElementSibling;
                            if(next){
                                const nextName = next.querySelector('.item-name');
                                if(nextName) nextName.focus();
                            }
                        }
                    }
                });
            }
        }
        function removeRow(btn){
            btn.closest('tr').remove();
            recalc();
        }
        function recalcRow(tr){
            const qty = parseFloat(tr.querySelector('.item-qty').value||0);
            const harga = parseFloat(tr.querySelector('.item-harga').value||0);
            const sub = qty*harga;
            tr.querySelector('.item-subtotal').innerText = rupiah(sub);
            recalc();
        }

        function recalc(){
            let subtotal=0;
            document.querySelectorAll('#itemBody tr').forEach(tr=>{
                const qty = parseFloat(tr.querySelector('.item-qty').value||0);
                const harga = parseFloat(tr.querySelector('.item-harga').value||0);
                subtotal += qty*harga;
            });
            const diskon = parseFloat(document.getElementById('diskon').value||0);
            const ongkir = parseFloat(document.getElementById('ongkir').value||0);
            let pajak = 0;
            const jenis = document.getElementById('jenis_ppn').value;
            if(jenis === 'PPN 11%') pajak = subtotal*0.11;
            else if(jenis === 'PPN 1.1%') pajak = subtotal*0.011;

            const total = subtotal - diskon + ongkir + pajak;

            document.getElementById('t_subtotal').innerText = rupiah(subtotal);
            document.getElementById('t_pajak').innerText = rupiah(pajak);
            document.getElementById('t_diskon').innerText = rupiah(diskon);
            document.getElementById('t_ongkir').innerText = rupiah(ongkir);
            document.getElementById('t_total').innerText = rupiah(total);

            document.getElementById('subtotal').value = subtotal;
            document.getElementById('pajak').value = pajak;
            document.getElementById('total').value = total;
        }

        function toggleTempo(){
            const tempoOn = document.querySelector('input[name="tipe_faktur"]:checked').value === 'tempo';
            document.getElementById('tempo_hari').disabled = !tempoOn;
            document.getElementById('jatuh_tempo').disabled = !tempoOn;
            if(!tempoOn){
                document.getElementById('tempo_hari').value = 0;
                document.getElementById('jatuh_tempo').value = '';
            } else {
                updateTempoDate();
                // Focus on tempo_hari when switching to tempo
                setTimeout(()=>{
                    document.getElementById('tempo_hari').focus();
                },50);
            }
        }

        // Tipe Faktur radio buttons - keyboard navigation
        document.querySelectorAll('input[name="tipe_faktur"]').forEach(radio=>{
            radio.addEventListener('keydown', e=>{
                if(e.key === 'ArrowRight' || e.key === 'ArrowLeft'){
                    e.preventDefault();
                    const radios = Array.from(document.querySelectorAll('input[name="tipe_faktur"]'));
                    const currentIdx = radios.indexOf(e.target);
                    if(e.key === 'ArrowRight'){
                        const nextIdx = (currentIdx + 1) % radios.length;
                        radios[nextIdx].checked = true;
                        radios[nextIdx].focus();
                        toggleTempo();
                    }else{
                        const prevIdx = (currentIdx - 1 + radios.length) % radios.length;
                        radios[prevIdx].checked = true;
                        radios[prevIdx].focus();
                        toggleTempo();
                    }
                }
            });
        });

        function updateTempoDate(){
            const tgl = document.getElementById('tanggal').value;
            const tempo = parseInt(document.getElementById('tempo_hari').value||0,10);
            if(tgl && tempo>0){
                const dt = new Date(tgl);
                dt.setDate(dt.getDate()+tempo);
                document.getElementById('jatuh_tempo').value = dt.toISOString().slice(0,10);
            }
        }

        // Tempo hari - Enter jumps to jatuh tempo
        document.getElementById('tempo_hari').addEventListener('keydown', function(e){
            if(e.key === 'Enter'){
                e.preventDefault();
                updateTempoDate();
                document.getElementById('jatuh_tempo').focus();
            }
        });

        // Jatuh tempo - Enter moves to next field
        document.getElementById('jatuh_tempo').addEventListener('keydown', function(e){
            if(e.key === 'Enter'){
                e.preventDefault();
                document.getElementById('jenis_ppn').focus();
            }
        });

        // Supplier button - Enter opens lookup
        document.querySelector('button[onclick="openLookup(\'supplier\')"]').addEventListener('keydown', function(e){
            if(e.key === 'Enter'){
                e.preventDefault();
                openLookup('supplier');
            }
        });

        function prepareItems(fd){
        const names=[]; const qtys=[]; const hrg=[];
        const pids=[]; const sats=[];
        document.querySelectorAll('#itemBody tr').forEach(tr=>{
        names.push(tr.querySelector('.item-name').value || '');
        qtys.push(tr.querySelector('.item-qty').value || 0);
        hrg.push(tr.querySelector('.item-harga').value || 0);
        pids.push(tr.querySelector('.item-product-id').value || 0);
        sats.push(tr.querySelector('.item-satuan').value || '');
        });
        names.forEach(v=>fd.append('item_nama[]', v));
        qtys.forEach(v=>fd.append('item_qty[]', v));
        hrg.forEach(v=>fd.append('item_harga[]', v));
        pids.forEach(v=>fd.append('item_product_id[]', v));
        sats.forEach(v=>fd.append('item_satuan[]', v));
        }

        ['tempo_hari','diskon','ongkir'].forEach(id=>attachAutoClear(document.getElementById(id)));

        const ongkirInput = document.getElementById('ongkir');
        if(ongkirInput){
            ongkirInput.addEventListener('keydown', e=>{
                if(e.key === 'Enter'){
                    e.preventDefault();
                    focusFirstItem();
                }
            });
            ongkirInput.addEventListener('blur', ()=>{
                const active = document.activeElement;
                const inItems = active && active.closest && active.closest('#itemBody');
                const firstItem = document.querySelector('#itemBody .item-name');
                if(!inItems && firstItem && firstItem.value.trim()==='') focusFirstItem();
            });
        }

        // Keyboard navigation for buttons
        document.querySelectorAll('.btn').forEach(btn=>{
            btn.addEventListener('keydown', e=>{
                if(e.key === 'Enter' && !btn.onclick){
                    e.preventDefault();
                    btn.click();
                }
            });
        });

        async function submitPO(status){
            document.getElementById('statusField').value = status;
            recalc();
            const form = document.getElementById('poForm');
            const fd = new FormData(form);
            prepareItems(fd);
            try{
                const resp = await fetch(form.action, {method:'POST', body:fd});
                if(!resp.ok) throw new Error('Gagal kirim PO');
                const d = await resp.json();
                if(!d.ok) throw new Error(d.msg||'Gagal simpan');
                alert('PO tersimpan!');
                if(d.id) window.location = 'print.php?id='+d.id;
            }catch(err){
                alert(err.message || 'Gagal simpan');
            }
        }

        // init
        addRow();
        recalc();
</script>
</body>
</html>
