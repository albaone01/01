<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/functions.php';

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);

$suppliers = [];
$supStmt = $pos_db->prepare("SELECT supplier_id, nama_supplier FROM supplier WHERE toko_id=? ORDER BY nama_supplier");
$supStmt->bind_param("i", $tokoId);
$supStmt->execute();
$supRes = $supStmt->get_result();
if($supRes) $suppliers = $supRes->fetch_all(MYSQLI_ASSOC);
$supStmt->close();

$poList = [];
$poStmt = $pos_db->prepare("SELECT po.po_id, po.nomor, po.supplier_id, po.tipe_faktur, po.tempo_hari, po.jatuh_tempo, po.jenis_ppn, s.nama_supplier
                            FROM purchase_order po
                            LEFT JOIN supplier s ON s.supplier_id = po.supplier_id
                            WHERE po.toko_id=? AND po.status='approved'
                            ORDER BY po.tanggal DESC, po.po_id DESC");
$poStmt->bind_param("i", $tokoId);
$poStmt->execute();
$poRes = $poStmt->get_result();
if($poRes) $poList = $poRes->fetch_all(MYSQLI_ASSOC);
$poStmt->close();

$gudangId = (int)($_SESSION['gudang_id'] ?? 0);
if($gudangId <= 0){
    $gStmt = $pos_db->prepare("SELECT gudang_id FROM gudang WHERE toko_id=? AND aktif=1 AND deleted_at IS NULL ORDER BY CASE WHEN nama_gudang='Gudang Utama' THEN 0 ELSE 1 END, gudang_id LIMIT 1");
    $gStmt->bind_param("i", $tokoId);
    $gStmt->execute();
    $gRow = $gStmt->get_result()->fetch_assoc();
    $gStmt->close();
    $gudangId = $gRow ? (int)$gRow['gudang_id'] : 1;
}

$defaultNo = 'PB-' . date('Ymd') . '-' . substr(str_shuffle('1234567890'), 0, 4);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penerimaan Barang</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        :root{
            --primary:#0ea5e9;
            --primary-strong:#0284c7;
            --bg:#f5f7fb;
            --card:#fff;
            --border:#e2e8f0;
            --muted:#64748b;
            --text:#0f172a;
            --danger:#ef4444;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:'Plus Jakarta Sans','Inter',system-ui;background:var(--bg);color:var(--text)}
        .page{max-width:1280px;margin:20px auto;padding:0 14px}
        .card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:0 16px 40px rgba(15,23,42,.08);padding:16px}
        h1{margin:0 0 6px;font-size:24px}
        .lead{margin:0 0 14px;color:var(--muted)}
        .grid{display:grid;gap:10px}
        .g6{grid-template-columns:repeat(6,minmax(0,1fr))}
        .g4{grid-template-columns:repeat(4,minmax(0,1fr))}
        .g3{grid-template-columns:repeat(3,minmax(0,1fr))}
        @media(max-width:1024px){.g6,.g4,.g3{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:680px){.g6,.g4,.g3{grid-template-columns:1fr}}
        label{font-size:12px;font-weight:700;color:var(--text)}
        input,select,textarea{width:100%;padding:10px;border:1px solid var(--border);border-radius:10px;background:#f8fafc;font-size:14px}
        input:focus,select:focus,textarea:focus{outline:2px solid rgba(14,165,233,.25);border-color:#7dd3fc;background:#fff}
        .scan-wrap{margin-top:10px;padding:12px;border:1px dashed #7dd3fc;background:#f0f9ff;border-radius:12px}
        .scan-wrap input{font-size:18px;font-weight:700;letter-spacing:.5px;background:#fff}
        .scan-row{display:grid;grid-template-columns:1fr 46px;gap:8px;align-items:center}
        .icon-btn{height:46px;border-radius:10px;border:1px solid #bae6fd;background:#fff;color:#0369a1;font-size:18px;cursor:pointer}
        .icon-btn:hover{background:#e0f2fe}
        .hint{font-size:12px;color:var(--muted);margin-top:6px}
        table{width:100%;border-collapse:collapse;margin-top:10px;border:1px solid var(--border)}
        th,td{padding:8px;border-bottom:1px solid var(--border);font-size:13px;text-align:left}
        th{background:#f8fafc;color:var(--muted);font-weight:700}
        td input,td select{padding:7px;font-size:13px}
        .text-right{text-align:right}
        .totals{max-width:360px;margin-left:auto;margin-top:10px}
        .totals div{display:flex;justify-content:space-between;margin:6px 0;font-weight:600}
        .totals .grand{font-size:18px;color:var(--primary-strong)}
        .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .btn{padding:10px 14px;border:none;border-radius:10px;font-weight:700;cursor:pointer}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-outline{background:#fff;border:1px solid var(--border);color:var(--text)}
        .btn-danger{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .kbd{display:inline-block;padding:2px 6px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;font-size:11px;color:#334155}
        .error{color:var(--danger);font-size:12px;min-height:18px}
        .modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:1000}
        .modal.open{display:flex}
        .modal-card{width:min(920px,94vw);max-height:86vh;background:#fff;border:1px solid var(--border);border-radius:14px;display:flex;flex-direction:column;overflow:hidden}
        .modal-head{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid var(--border)}
        .modal-body{padding:12px;display:flex;flex-direction:column;gap:8px;min-height:240px}
        .modal-list{border:1px solid var(--border);border-radius:10px;overflow:auto;max-height:58vh}
        .modal-list table{margin:0;border:none}
        .modal-list tr:hover{background:#f0f9ff;cursor:pointer}
        .modal-list tr.active{background:#e0f2fe}
        .tiny{font-size:11px;color:var(--muted)}
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Penerimaan Barang</h1>
        <p class="lead">Isi header sekali, lalu scan barcode dan input detail item full keyboard.</p>

        <form id="receiveForm" action="/api/receipt_save.php" method="post">
            <input type="hidden" name="gudang_id" value="<?= (int)$gudangId ?>">
            <div class="grid g6">
                <div>
                    <label>No Faktur</label>
                    <input type="text" id="nomor_faktur" name="nomor_faktur" required value="<?= htmlspecialchars($defaultNo) ?>">
                </div>
                <div>
                    <label>Tgl Terima</label>
                    <input type="date" id="tanggal_terima" name="tanggal_terima" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label>No Order (Opsional)</label>
                    <select id="po_id" name="po_id">
                        <option value="">Tanpa PO</option>
                        <?php foreach($poList as $po): ?>
                            <option value="<?= (int)$po['po_id'] ?>">
                                <?= htmlspecialchars($po['nomor'] . ' - ' . ($po['nama_supplier'] ?: 'Supplier')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Supplier</label>
                    <select id="supplier_id" name="supplier_id" required>
                        <option value="">Pilih supplier</option>
                        <?php foreach($suppliers as $s): ?>
                            <option value="<?= (int)$s['supplier_id'] ?>"><?= htmlspecialchars($s['nama_supplier']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Jenis Pembayaran</label>
                    <select id="tipe_faktur" name="tipe_faktur">
                        <option value="cash">Cash</option>
                        <option value="tempo">Tempo</option>
                    </select>
                </div>
                <div>
                    <label>Tempo (hari)</label>
                    <input type="number" min="0" id="tempo_hari" name="tempo_hari" value="0" disabled>
                </div>
            </div>

            <div class="grid g4" style="margin-top:10px">
                <div>
                    <label>Jatuh Tempo</label>
                    <input type="date" id="jatuh_tempo" name="jatuh_tempo" disabled>
                </div>
                <div>
                    <label>Jenis PPN</label>
                    <select id="jenis_ppn" name="jenis_ppn">
                        <option value="">Non PPN</option>
                        <option value="PPN 11%">PPN 11%</option>
                        <option value="PPN 1.1%">PPN 1.1%</option>
                    </select>
                </div>
                <div>
                    <label>Diskon Header (Rp)</label>
                    <input type="number" min="0" step="0.01" id="diskon_header" name="diskon_header" value="0">
                </div>
            </div>

            <div class="scan-wrap">
                <label>Scan Barcode</label>
                <div class="scan-row">
                    <input type="text" id="barcode_scan" placeholder="Scan barcode lalu Enter">
                    <button type="button" class="icon-btn" id="btnOpenLookup" title="Cari barang (nama)">🔎</button>
                </div>
                <div class="hint">
                    Shortcut: <span class="kbd">F2</span> fokus scan, <span class="kbd">F9</span> posting, <span class="kbd">Enter</span> navigasi antar kolom.
                </div>
            </div>

            <div class="error" id="form_error"></div>

            <table>
                <thead>
                <tr>
                    <th style="width:12%">Barcode/SKU</th>
                    <th>Barang</th>
                    <th style="width:9%">Qty</th>
                    <th style="width:10%">Satuan</th>
                    <th style="width:12%">Harga Beli</th>
                    <th style="width:8%">Disc %</th>
                    <th style="width:8%">Profit %</th>
                    <th style="width:12%">Harga Jual</th>
                    <th style="width:13%">Subtotal</th>
                    <th style="width:5%"></th>
                </tr>
                </thead>
                <tbody id="itemBody">
                <tr><td colspan="10" style="text-align:center;color:var(--muted)">Belum ada item. Scan barcode untuk mulai input.</td></tr>
                </tbody>
            </table>

            <div class="totals">
                <div><span>Subtotal Bruto</span><span id="t_subtotal_bruto">Rp 0</span></div>
                <div><span>Diskon Item</span><span id="t_diskon_item">Rp 0</span></div>
                <div><span>Diskon Header</span><span id="t_diskon_header">Rp 0</span></div>
                <div><span>DPP</span><span id="t_dpp">Rp 0</span></div>
                <div><span>Pajak</span><span id="t_pajak">Rp 0</span></div>
                <div class="grand"><span>Total</span><span id="t_total">Rp 0</span></div>
            </div>

            <input type="hidden" name="subtotal" id="subtotal" value="0">
            <input type="hidden" name="pajak" id="pajak" value="0">
            <input type="hidden" name="diskon" id="diskon" value="0">
            <input type="hidden" name="total" id="total" value="0">

            <div class="row" style="justify-content:flex-end;margin-top:12px">
                <button type="button" class="btn btn-outline" onclick="window.location.href='/public/admin/purchase_order/index.php'">Kembali ke PO</button>
                <button type="submit" class="btn btn-primary" id="btnPost">Posting Penerimaan</button>
            </div>
        </form>
    </div>
</div>
<div class="modal" id="lookupModal" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-head">
            <strong>Cari Barang</strong>
            <button type="button" class="btn btn-outline" id="btnCloseLookup">Tutup</button>
        </div>
        <div class="modal-body">
            <input type="text" id="lookupSearch" placeholder="Cari nama / SKU / barcode...">
            <div class="tiny">Daftar dimuat bertahap saat discroll.</div>
            <div class="modal-list" id="lookupScroll">
                <table>
                    <thead>
                    <tr>
                        <th style="width:18%">Barcode/SKU</th>
                        <th>Nama</th>
                        <th style="width:10%">Satuan</th>
                        <th style="width:16%">Harga Modal</th>
                    </tr>
                    </thead>
                    <tbody id="lookupBody">
                    <tr><td colspan="4" style="text-align:center;color:var(--muted)">Ketik nama barang untuk mencari.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const poData = <?php echo json_encode($poList, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const rupiah = (v)=>'Rp '+Number(v||0).toLocaleString('id-ID');

const form = document.getElementById('receiveForm');
const itemBody = document.getElementById('itemBody');
const errorBox = document.getElementById('form_error');
const poField = document.getElementById('po_id');
const supplierField = document.getElementById('supplier_id');
const payType = document.getElementById('tipe_faktur');
const tempoHari = document.getElementById('tempo_hari');
const jatuhTempo = document.getElementById('jatuh_tempo');
const barcodeScan = document.getElementById('barcode_scan');
const lookupModal = document.getElementById('lookupModal');
const lookupBody = document.getElementById('lookupBody');
const lookupSearch = document.getElementById('lookupSearch');
const lookupScroll = document.getElementById('lookupScroll');
const DRAFT_KEY = 'receipt_draft_toko_<?= (int)$tokoId ?>';

let items = [];
let lookupItems = [];
let lookupOffset = 0;
const lookupLimit = 20;
let lookupLoading = false;
let lookupHasMore = true;
let lookupTerm = '';
let lookupHighlight = -1;

function saveDraft(){
    try{
        const payload = {
            header: {
                nomor_faktur: document.getElementById('nomor_faktur').value || '',
                tanggal_terima: document.getElementById('tanggal_terima').value || '',
                po_id: poField.value || '',
                supplier_id: supplierField.value || '',
                tipe_faktur: payType.value || 'cash',
                tempo_hari: tempoHari.value || '0',
                jatuh_tempo: jatuhTempo.value || '',
                jenis_ppn: document.getElementById('jenis_ppn').value || '',
                diskon_header: document.getElementById('diskon_header').value || '0'
            },
            items
        };
        localStorage.setItem(DRAFT_KEY, JSON.stringify(payload));
    }catch(e){}
}
function clearDraft(){
    try{ localStorage.removeItem(DRAFT_KEY); }catch(e){}
}
function loadDraft(){
    try{
        const raw = localStorage.getItem(DRAFT_KEY);
        if(!raw) return;
        const d = JSON.parse(raw);
        if(!d || typeof d !== 'object') return;
        const h = d.header || {};
        if(h.nomor_faktur) document.getElementById('nomor_faktur').value = h.nomor_faktur;
        if(h.tanggal_terima) document.getElementById('tanggal_terima').value = h.tanggal_terima;
        if(h.po_id !== undefined) poField.value = h.po_id;
        if(h.supplier_id !== undefined) supplierField.value = h.supplier_id;
        if(h.tipe_faktur) payType.value = h.tipe_faktur;
        if(h.tempo_hari !== undefined) tempoHari.value = h.tempo_hari;
        if(h.jatuh_tempo !== undefined) jatuhTempo.value = h.jatuh_tempo;
        if(h.jenis_ppn !== undefined) document.getElementById('jenis_ppn').value = h.jenis_ppn;
        if(h.diskon_header !== undefined) document.getElementById('diskon_header').value = h.diskon_header;
        if(Array.isArray(d.items)) items = d.items;
    }catch(e){}
}

function setError(msg){
    errorBox.textContent = msg || '';
}
function clearError(){
    setError('');
}
function toNum(v){
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
}
function computePpnRate(){
    const jenis = document.getElementById('jenis_ppn').value || '';
    if(jenis.includes('11%')) return 0.11;
    if(jenis.includes('1.1%')) return 0.011;
    return 0;
}
function updateTempoState(){
    const tempoOn = payType.value === 'tempo';
    tempoHari.disabled = !tempoOn;
    jatuhTempo.disabled = !tempoOn;
    if(!tempoOn){
        tempoHari.value = 0;
        jatuhTempo.value = '';
        return;
    }
    const tgl = document.getElementById('tanggal_terima').value;
    const hari = parseInt(tempoHari.value || '0', 10);
    if(tgl && hari >= 0){
        const d = new Date(tgl);
        d.setDate(d.getDate() + hari);
        jatuhTempo.value = d.toISOString().slice(0,10);
    }
    saveDraft();
}
function calcLine(item){
    const qty = Math.max(0, toNum(item.qty));
    const harga = Math.max(0, toNum(item.harga));
    const discPct = Math.max(0, Math.min(100, toNum(item.discPct)));
    const profitPct = Math.max(0, toNum(item.profitPct));
    const gross = qty * harga;
    const discAmt = gross * (discPct / 100);
    const net = gross - discAmt;
    const baseUnit = qty > 0 ? (net / qty) : 0;
    const targetSell = baseUnit * (1 + (profitPct / 100));
    return {qty, harga, discPct, profitPct, gross, discAmt, net, targetSell};
}
function recalc(){
    let grossTotal = 0;
    let itemDiscTotal = 0;
    items.forEach((it)=> {
        const c = calcLine(it);
        it.qty = c.qty;
        it.harga = c.harga;
        it.discPct = c.discPct;
        it.profitPct = c.profitPct;
        it.targetSell = c.targetSell;
        it.subtotal = c.net;
        grossTotal += c.gross;
        itemDiscTotal += c.discAmt;
    });

    const headerDiscInput = Math.max(0, toNum(document.getElementById('diskon_header').value));
    const afterItem = Math.max(0, grossTotal - itemDiscTotal);
    const headerDisc = Math.min(headerDiscInput, afterItem);
    const dpp = Math.max(0, afterItem - headerDisc);
    const rate = computePpnRate();
    const pajak = dpp * rate;
    const total = dpp + pajak;

    document.getElementById('t_subtotal_bruto').innerText = rupiah(grossTotal);
    document.getElementById('t_diskon_item').innerText = rupiah(itemDiscTotal);
    document.getElementById('t_diskon_header').innerText = rupiah(headerDisc);
    document.getElementById('t_dpp').innerText = rupiah(dpp);
    document.getElementById('t_pajak').innerText = rupiah(pajak);
    document.getElementById('t_total').innerText = rupiah(total);

    document.getElementById('subtotal').value = grossTotal.toFixed(2);
    document.getElementById('pajak').value = pajak.toFixed(2);
    document.getElementById('diskon').value = (itemDiscTotal + headerDisc).toFixed(2);
    document.getElementById('total').value = total.toFixed(2);
    saveDraft();
}
function nextField(index, field){
    const order = ['qty','harga','disc','profit'];
    const pos = order.indexOf(field);
    if(pos < 0) return;
    const next = order[pos+1];
    if(next){
        const el = document.querySelector(`[data-row="${index}"][data-field="${next}"]`);
        if(el){ el.focus(); el.select && el.select(); }
    }else{
        barcodeScan.focus();
        barcodeScan.select();
    }
}
function refreshRowCalc(index){
    const tr = itemBody.querySelector(`tr[data-row="${index}"]`);
    if(!tr || !items[index]) return;
    const sellCell = tr.querySelector('.cell-target-sell');
    const subCell = tr.querySelector('.cell-subtotal');
    if(sellCell) sellCell.textContent = rupiah(items[index].targetSell || 0);
    if(subCell) subCell.textContent = rupiah(items[index].subtotal || 0);
}
function renderItems(focusIdx = null, focusField = 'qty'){
    if(!items.length){
        itemBody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#64748b">Belum ada item. Scan barcode untuk mulai input.</td></tr>';
        recalc();
        return;
    }
    itemBody.innerHTML = '';
    items.forEach((it, idx)=>{
        const tr = document.createElement('tr');
        tr.dataset.row = String(idx);
        tr.innerHTML = `
            <td>${it.barcode || it.sku || '-'}</td>
            <td>${it.nama}</td>
            <td><input data-row="${idx}" data-field="qty" type="number" min="0" step="1" value="${it.qty}"></td>
            <td><input data-row="${idx}" data-field="satuan" type="text" value="${it.satuan || 'PCS'}" readonly></td>
            <td><input data-row="${idx}" data-field="harga" type="number" min="0" step="0.01" value="${it.harga}"></td>
            <td><input data-row="${idx}" data-field="disc" type="number" min="0" max="100" step="0.01" value="${it.discPct}"></td>
            <td><input data-row="${idx}" data-field="profit" type="number" min="0" step="0.01" value="${it.profitPct}"></td>
            <td class="text-right cell-target-sell">${rupiah(it.targetSell || 0)}</td>
            <td class="text-right cell-subtotal">${rupiah(it.subtotal || 0)}</td>
            <td><button type="button" class="btn btn-danger" data-row="${idx}" data-remove="1">x</button></td>
        `;
        itemBody.appendChild(tr);
    });
    recalc();
    if(focusIdx !== null){
        const target = document.querySelector(`[data-row="${focusIdx}"][data-field="${focusField}"]`);
        if(target){ target.focus(); target.select && target.select(); }
    }
}

async function fetchProducts(keyword, offset = 0, limit = 20){
    const q = (keyword || '').trim();
    const r = await fetch(`/api/produk_search.php?q=${encodeURIComponent(q)}&offset=${offset}&limit=${limit}`);
    if(!r.ok) throw new Error('Produk tidak bisa dicari');
    const d = await r.json();
    if(!d.ok) throw new Error(d.msg || 'Gagal cari produk');
    return d;
}

async function searchProduct(keyword){
    const q = (keyword || '').trim();
    if(!q) return null;
    const d = await fetchProducts(q, 0, 20);
    if(!Array.isArray(d.data) || !d.data.length) return null;
    return d.data[0];
}

function normalizeProduct(prod){
    const satuanAuto = (Array.isArray(prod.multi_satuan) && prod.multi_satuan.length)
        ? (prod.multi_satuan[0].nama_satuan || prod.satuan || 'PCS')
        : (prod.satuan || 'PCS');
    return {
        produk_id: Number(prod.produk_id || 0),
        nama: prod.nama_produk || '-',
        sku: prod.sku || '',
        barcode: prod.barcode || '',
        qty: 1,
        satuan: satuanAuto,
        harga: toNum(prod.harga_modal || 0),
        discPct: 0,
        profitPct: 0,
        targetSell: 0,
        subtotal: 0
    };
}

function addProductToItems(prod){
    const p = normalizeProduct(prod);
    const existing = items.findIndex((x)=>Number(x.produk_id) === p.produk_id);
    if(existing >= 0){
        items[existing].qty = toNum(items[existing].qty) + 1;
        renderItems(existing, 'qty');
    }else{
        items.push(p);
        renderItems(items.length - 1, 'qty');
    }
}

async function addByBarcode(){
    clearError();
    const code = barcodeScan.value.trim();
    if(!code) return;
    try{
        const prod = await searchProduct(code);
        if(!prod){
            setError('Barcode/SKU tidak ditemukan');
            barcodeScan.select();
            return;
        }
        addProductToItems(prod);
        barcodeScan.value = '';
    }catch(err){
        setError(err.message || 'Gagal tambah item');
    }
}

function applyPOPreset(){
    const poId = parseInt(poField.value || '0', 10);
    if(!poId) return;
    const po = poData.find((p)=>Number(p.po_id) === poId);
    if(!po) return;
    if(po.supplier_id) supplierField.value = String(po.supplier_id);
    if(po.tipe_faktur) payType.value = po.tipe_faktur;
    if(po.tempo_hari !== null && po.tempo_hari !== undefined) tempoHari.value = Number(po.tempo_hari || 0);
    if(po.jatuh_tempo) jatuhTempo.value = po.jatuh_tempo;
    if(po.jenis_ppn){
        const jp = document.getElementById('jenis_ppn');
        const found = Array.from(jp.options).find((o)=>o.value === po.jenis_ppn);
        if(found) jp.value = po.jenis_ppn;
    }
    updateTempoState();
    recalc();
}

function setupHeaderEnterFlow(){
    const headerOrder = [
        'nomor_faktur',
        'tanggal_terima',
        'po_id',
        'supplier_id',
        'tipe_faktur',
        'tempo_hari',
        'jatuh_tempo',
        'jenis_ppn',
        'diskon_header',
        'barcode_scan'
    ];
    headerOrder.forEach((id, idx)=>{
        const el = document.getElementById(id);
        if(!el) return;
        el.addEventListener('keydown', (e)=>{
            if(e.key !== 'Enter') return;
            e.preventDefault();
            for(let i = idx + 1; i < headerOrder.length; i++){
                const nextEl = document.getElementById(headerOrder[i]);
                if(nextEl && !nextEl.disabled){
                    nextEl.focus();
                    if(nextEl.select) nextEl.select();
                    return;
                }
            }
            barcodeScan.focus();
        });
    });
}

function renderLookupRows(){
    if(!lookupItems.length){
        lookupBody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#64748b">Tidak ada barang ditemukan.</td></tr>';
        lookupHighlight = -1;
        return;
    }
    lookupBody.innerHTML = '';
    lookupItems.forEach((p, idx)=>{
        const tr = document.createElement('tr');
        tr.dataset.idx = String(idx);
        tr.innerHTML = `
            <td>${p.barcode || p.sku || '-'}</td>
            <td>${p.nama_produk || '-'}</td>
            <td>${(p.multi_satuan && p.multi_satuan[0] && p.multi_satuan[0].nama_satuan) ? p.multi_satuan[0].nama_satuan : (p.satuan || 'PCS')}</td>
            <td>${rupiah(p.harga_modal || 0)}</td>
        `;
        tr.addEventListener('click', ()=>{
            addProductToItems(p);
            closeLookup();
            barcodeScan.focus();
        });
        lookupBody.appendChild(tr);
    });
    if(lookupHighlight < 0 || lookupHighlight >= lookupItems.length){
        lookupHighlight = 0;
    }
    updateLookupHighlight(false);
}

function updateLookupHighlight(ensureVisible = true){
    const rows = lookupBody.querySelectorAll('tr[data-idx]');
    rows.forEach((tr, idx)=>{
        tr.classList.toggle('active', idx === lookupHighlight);
        if(idx === lookupHighlight && ensureVisible){
            tr.scrollIntoView({block:'nearest'});
        }
    });
}

function chooseLookupIndex(idx){
    if(idx < 0 || idx >= lookupItems.length) return;
    addProductToItems(lookupItems[idx]);
    closeLookup();
    barcodeScan.focus();
    barcodeScan.select();
}

async function loadLookup(reset = false){
    if(lookupLoading) return;
    if(reset){
        lookupItems = [];
        lookupOffset = 0;
        lookupHasMore = true;
        lookupBody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#64748b">Memuat...</td></tr>';
    }
    if(!lookupHasMore) return;
    lookupLoading = true;
    try{
        const resp = await fetchProducts(lookupTerm, lookupOffset, lookupLimit);
        const rows = Array.isArray(resp.data) ? resp.data : [];
        lookupItems = lookupItems.concat(rows);
        lookupOffset += rows.length;
        lookupHasMore = !!(resp.meta && resp.meta.has_more);
        renderLookupRows();
    }catch(err){
        lookupBody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:#ef4444">${(err && err.message) || 'Gagal memuat daftar barang'}</td></tr>`;
    }finally{
        lookupLoading = false;
    }
}

function openLookup(){
    lookupModal.classList.add('open');
    lookupModal.setAttribute('aria-hidden', 'false');
    lookupTerm = '';
    lookupSearch.value = '';
    lookupHighlight = -1;
    loadLookup(true);
    setTimeout(()=>lookupSearch.focus(), 50);
}

function closeLookup(){
    lookupModal.classList.remove('open');
    lookupModal.setAttribute('aria-hidden', 'true');
}

document.addEventListener('keydown', (e)=>{
    if(e.key === 'F2'){
        e.preventDefault();
        barcodeScan.focus();
        barcodeScan.select();
    } else if(e.key === 'F9'){
        e.preventDefault();
        form.requestSubmit();
    }
});

itemBody.addEventListener('input', (e)=>{
    const el = e.target;
    if(!(el instanceof HTMLInputElement)) return;
    const i = parseInt(el.dataset.row || '', 10);
    if(!Number.isInteger(i) || !items[i]) return;
    const field = el.dataset.field;
    if(field === 'qty') items[i].qty = Math.max(0, toNum(el.value));
    if(field === 'harga') items[i].harga = Math.max(0, toNum(el.value));
    if(field === 'disc') items[i].discPct = Math.max(0, Math.min(100, toNum(el.value)));
    if(field === 'profit') items[i].profitPct = Math.max(0, toNum(el.value));
    recalc();
    refreshRowCalc(i);
});
itemBody.addEventListener('keydown', (e)=>{
    const el = e.target;
    if(!(el instanceof HTMLInputElement)) return;
    if(e.key === 'Enter'){
        e.preventDefault();
        const i = parseInt(el.dataset.row || '', 10);
        nextField(i, el.dataset.field || '');
    }
});
itemBody.addEventListener('click', (e)=>{
    const t = e.target;
    if(!(t instanceof HTMLElement)) return;
    const btn = t.closest('button[data-remove="1"]');
    if(!btn) return;
    const i = parseInt(btn.getAttribute('data-row') || '', 10);
    if(Number.isInteger(i)){
        items.splice(i, 1);
        renderItems();
    }
});

document.getElementById('diskon_header').addEventListener('input', recalc);
document.getElementById('jenis_ppn').addEventListener('change', recalc);
document.getElementById('tanggal_terima').addEventListener('change', updateTempoState);
payType.addEventListener('change', updateTempoState);
tempoHari.addEventListener('input', updateTempoState);
jatuhTempo.addEventListener('change', saveDraft);
poField.addEventListener('change', ()=>{ applyPOPreset(); saveDraft(); });
supplierField.addEventListener('change', saveDraft);
document.getElementById('nomor_faktur').addEventListener('input', saveDraft);

barcodeScan.addEventListener('keydown', async (e)=>{
    if(e.key === 'Enter'){
        e.preventDefault();
        await addByBarcode();
    }
});
document.getElementById('btnOpenLookup').addEventListener('click', openLookup);
document.getElementById('btnCloseLookup').addEventListener('click', closeLookup);
lookupModal.addEventListener('click', (e)=>{
    if(e.target === lookupModal) closeLookup();
});
lookupSearch.addEventListener('input', ()=>{
    lookupTerm = lookupSearch.value.trim();
    loadLookup(true);
});
lookupSearch.addEventListener('keydown', (e)=>{
    if(e.key === 'ArrowDown'){
        e.preventDefault();
        if(lookupItems.length){
            lookupHighlight = Math.min(lookupItems.length - 1, lookupHighlight + 1);
            updateLookupHighlight(true);
            const remain = lookupScroll.scrollHeight - lookupScroll.scrollTop - lookupScroll.clientHeight;
            if(remain < 80) loadLookup(false);
        }
        return;
    }
    if(e.key === 'ArrowUp'){
        e.preventDefault();
        if(lookupItems.length){
            lookupHighlight = Math.max(0, lookupHighlight - 1);
            updateLookupHighlight(true);
        }
        return;
    }
    if(e.key === 'Enter'){
        e.preventDefault();
        if(lookupItems.length){
            if(lookupHighlight < 0) lookupHighlight = 0;
            chooseLookupIndex(lookupHighlight);
        }
        return;
    }
    if(e.key === 'Escape'){
        e.preventDefault();
        closeLookup();
        barcodeScan.focus();
    }
});
lookupScroll.addEventListener('scroll', ()=>{
    if(lookupLoading || !lookupHasMore) return;
    const remain = lookupScroll.scrollHeight - lookupScroll.scrollTop - lookupScroll.clientHeight;
    if(remain < 80) loadLookup(false);
});

form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    clearError();
    if(!supplierField.value){
        setError('Supplier wajib dipilih');
        supplierField.focus();
        return;
    }
    if(!items.length){
        setError('Scan minimal 1 barang');
        barcodeScan.focus();
        return;
    }
    const fd = new FormData(form);
    fd.delete('barang[]');
    fd.delete('qty[]');
    fd.delete('harga[]');
    fd.delete('produk_id[]');
    fd.delete('item_diskon_persen[]');
    fd.delete('profit_persen[]');
    fd.delete('item_satuan[]');

    items.forEach((it)=>{
        fd.append('barang[]', it.nama);
        fd.append('qty[]', String(it.qty));
        fd.append('harga[]', String(it.harga));
        fd.append('produk_id[]', String(it.produk_id));
        fd.append('item_diskon_persen[]', String(it.discPct));
        fd.append('profit_persen[]', String(it.profitPct));
        fd.append('item_satuan[]', String(it.satuan || 'PCS'));
    });

    const btn = document.getElementById('btnPost');
    btn.disabled = true;
    btn.textContent = 'Posting...';
    try{
        const r = await fetch(form.action, {method:'POST', body:fd});
        const d = await r.json();
        if(!r.ok || !d.ok){
            throw new Error(d.msg || 'Gagal posting penerimaan');
        }
        clearDraft();
        alert('Penerimaan tersimpan');
        if(d.redirect) window.location = d.redirect;
        else location.reload();
    }catch(err){
        setError(err.message || 'Gagal posting');
    }finally{
        btn.disabled = false;
        btn.textContent = 'Posting Penerimaan';
    }
});

loadDraft();
setupHeaderEnterFlow();
updateTempoState();
renderItems();
setTimeout(()=>document.getElementById('nomor_faktur').focus(), 80);
</script>
</body>
</html>
