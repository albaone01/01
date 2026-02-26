<?php
session_start();
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';
require_once '../../inc/functions.php';
require_once '../../inc/csrf.php';
require_once '../../inc/pos_saas_schema.php';

requireLogin();
requireDevice();
ensure_pos_saas_schema($pos_db);

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$kasirId = (int)($_SESSION['pengguna_id'] ?? 0);
if ($tokoId > 0 && $kasirId > 0 && !has_open_shift_today($pos_db, $tokoId, $kasirId)) {
    header('Location: /public/POS/tutup_kasir.php?need_open_shift=1');
    exit;
}
$csrf = csrf_token();
$memberPointNominal = 1000.0;
$memberRedeemNominal = 1.0;
if ($tokoId > 0) {
    $stmtCfg = $pos_db->prepare("SELECT nilai FROM toko_config WHERE toko_id = ? AND nama_konfigurasi = 'member_point_nominal' LIMIT 1");
    $stmtCfg->bind_param('i', $tokoId);
    $stmtCfg->execute();
    $cfgRow = $stmtCfg->get_result()->fetch_assoc();
    $stmtCfg->close();
    if ($cfgRow && isset($cfgRow['nilai'])) {
        $v = (float)$cfgRow['nilai'];
        if ($v > 0) $memberPointNominal = $v;
    }
    $stmtCfgRedeem = $pos_db->prepare("SELECT nilai FROM toko_config WHERE toko_id = ? AND nama_konfigurasi = 'member_redeem_nominal' LIMIT 1");
    $stmtCfgRedeem->bind_param('i', $tokoId);
    $stmtCfgRedeem->execute();
    $cfgRedeem = $stmtCfgRedeem->get_result()->fetch_assoc();
    $stmtCfgRedeem->close();
    if ($cfgRedeem && isset($cfgRedeem['nilai'])) {
        $vRedeem = (float)$cfgRedeem['nilai'];
        if ($vRedeem > 0) $memberRedeemNominal = $vRedeem;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POSAlbaOne - Pro Cashier</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        
        :root {
            --primary: #6366f1; --bg: #f8fafc; --text: #0f172a; --danger: #ef4444; --success: #22c55e; --warning: #f59e0b;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; margin:0; background: var(--bg); height: 100vh; overflow: hidden; }

        header { 
            background: #fff; padding: 12px 25px; border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .logo { font-weight: 800; font-size: 22px; color: var(--primary); }
        .logo span { color: var(--text); }

        .app-grid { display: grid; grid-template-columns: 1fr; height: calc(100vh - 60px); }
        
        /* Main Panel */
        .main-panel { padding: 20px; display: flex; flex-direction: column; overflow: hidden; }
        .action-bar { display: flex; gap: 10px; margin-bottom: 15px; }
        
        .main-input {
            flex: 1; padding: 12px 15px; font-size: 18px; font-weight: 700;
            border: 2px solid #e2e8f0; border-radius: 10px; outline: none; color: var(--primary);
        }

        /* Table Style */
        .table-wrapper { flex: 1; background: #fff; border-radius: 12px; overflow-y: auto; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; padding: 12px; text-align: left; font-size: 11px; position: sticky; top: 0; color: #64748b; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; cursor: pointer; }
        tr.selected { background: #eef2ff !important; border-left: 4px solid var(--primary); }
        tr:hover { background: #f8fafc; }

        .btn-icon { border: none; background: none; cursor: pointer; padding: 8px; border-radius: 6px; transition: 0.2s; }
        .btn-icon:hover { background: #f1f5f9; }
        .text-danger { color: var(--danger); }
        .text-warning { color: var(--warning); }
        .text-primary { color: var(--primary); }

        .checkout-panel {
            margin-top: 14px;
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 12px;
            align-items: start;
        }
        .checkout-controls {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .checkout-pay {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
        }
        .total-display { background: var(--text); color: #fff; padding: 18px 16px; border-radius: 12px; margin-bottom: 10px; position: relative; overflow: hidden; }
        .transaction-tools {
            margin-top: 10px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 8px;
        }
        
        .btn-clear { background: #fff1f2; color: var(--danger); border: 1px solid #fecdd3; padding: 12px; border-radius: 8px; font-weight: 700; cursor: pointer; margin-bottom: 15px; width: 100%; transition: 0.3s; }
        .btn-clear:hover { background: var(--danger); color: #fff; }
        .btn-soft { background:#f8fafc; color:#334155; border:1px solid #cbd5e1; padding:10px; border-radius:8px; font-weight:700; cursor:pointer; width:100%; }
        .btn-soft:hover { background:#e2e8f0; }
        .action-stack { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:12px; }
        @media (max-width: 1024px) {
            .checkout-panel { grid-template-columns: 1fr; }
            .checkout-controls { grid-template-columns: 1fr; }
            .transaction-tools { grid-template-columns: 1fr 1fr; }
        }

        /* Modals */
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 100; align-items: center; justify-content: center; }
        .modal-content { background: #fff; padding: 25px; border-radius: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }

        .copy-badge { position: absolute; top: 10px; right: 10px; font-size: 11px; background: rgba(255,255,255,0.1); padding: 5px 10px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px; }
        .copy-badge:hover { background: rgba(255,255,255,0.2); }

        /* Search Table Highlighting */
        .search-row-active { background: #eef2ff !important; }
        
        /* Customer Selection */
        .customer-btn {
            width: 100%;
            padding: 10px;
            background: #f1f5f9;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            transition: 0.3s;
            margin-bottom: 15px;
        }
        .customer-btn:hover {
            background: #e2e8f0;
            border-color: var(--primary);
            color: var(--primary);
        }
        .customer-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 15px;
            display: none;
        }
        .customer-info.active {
            display: block;
        }
    </style>
</head>
<body>

<header>
    <div class="logo">POS<span>AlbaOne</span></div>
    <div id="realtime" style="font-weight: 600; font-size: 14px; color: #64748b;"></div>
</header>

<div class="app-grid">
    <div class="main-panel">
        <div class="action-bar">
            <input type="text" id="cmdInput" class="main-input" placeholder="Scan Barcode / Perintah (*barang, /harga, -diskon)..." autofocus autocomplete="off">
            <button class="btn-icon" onclick="openSearchModal()" title="Cari Barang (F1)"><i class="fa fa-search fa-xl"></i></button>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">#</th>
                        <th>PRODUK</th>
                        <th style="text-align: center;">SATUAN</th>
                        <th style="text-align: right;">HARGA</th>
                        <th style="text-align: center;">QTY</th>
                        <th style="text-align: right;">DISKON</th>
                        <th style="text-align: right;">SUBTOTAL</th>
                        <th style="text-align: center; width: 100px;">AKSI</th>
                    </tr>
                </thead>
                <tbody id="cartBody"></tbody>
            </table>
        </div>

        <div class="checkout-panel">
            <div class="checkout-controls">
                <div>
                    <button class="customer-btn" onclick="openCustomerModal()" style="margin-bottom:10px;">
                        <i class="fa fa-user"></i> PILIH PELANGGAN
                    </button>
                    <div id="customerInfo" class="customer-info" style="margin-bottom:0;">
                        <div style="font-weight: 700; color: #166534;">
                            <i class="fa fa-user-check"></i> <span id="customerName">-</span>
                        </div>
                        <div style="font-size: 12px; color: #64748b;">
                            <span id="customerPhone">-</span>
                        </div>
                        <div style="font-size: 12px; color: #065f46; margin-top:4px;">
                            Belanja bulan ini: <strong id="customerMonthlySpend">Rp 0</strong>
                        </div>
                        <div style="font-size: 12px; color: #065f46;">
                            Saldo poin: <strong id="customerPointBalance">0</strong>
                        </div>
                        <button onclick="clearCustomer()" style="margin-top: 5px; padding: 4px 8px; font-size: 11px; background: #fee2e2; border: none; border-radius: 4px; cursor: pointer; color: #dc2626;">Ganti</button>
                    </div>
                </div>
                <div>
                    <div style="font-size:12px; color:#64748b; background:#f8fafc; border:1px dashed #cbd5e1; padding:10px; border-radius:10px;">
                        Level member mengikuti <strong>nominal belanja bulanan</strong>.
                        Poin member dipakai untuk <strong>potongan belanja</strong> saat checkout.
                    </div>
                </div>
            </div>

            <div class="checkout-pay">
                <div class="total-display">
                    <div class="copy-badge" onclick="copyTrxID()" title="Klik untuk Salin">
                        <i class="fa fa-copy"></i> <span id="trxID">TRX-<?= date('YmdHis') ?></span>
                    </div>
                    <div style="font-size: 12px; opacity: 0.7; margin-bottom: 5px;">TOTAL TAGIHAN</div>
                    <div id="grandTotal" style="font-size: 36px; font-weight: 800; letter-spacing: -1px;">Rp 0</div>
                </div>
                <button onclick="openPayment()" style="width: 100%; padding: 16px; background: var(--primary); color: #fff; border: none; border-radius: 12px; font-weight: 800; font-size: 20px; cursor: pointer; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);">
                    BAYAR [F10]
                </button>
            </div>
        </div>

        <div class="transaction-tools">
            <button class="btn-clear" onclick="clearAll()" style="margin-bottom:0;"><i class="fa fa-trash-can"></i> HAPUS SEMUA</button>
            <button class="btn-soft" onclick="holdCurrentTransaction()"><i class="fa fa-pause"></i> TAHAN [F6]</button>
            <button class="btn-soft" onclick="openHoldModal()"><i class="fa fa-layer-group"></i> PANGGIL [F7]</button>
            <button class="btn-soft" onclick="startNewOrder()"><i class="fa fa-plus"></i> KASIR BARU [F8]</button>
        </div>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content" style="width: 460px;">
        <h3 id="editTitle" style="margin-top:0; color: var(--text);">Edit Produk</h3>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div>
                <label style="font-size:12px; font-weight:700;">TIPE HARGA</label>
                <select id="editPriceType" class="main-input" style="width:100%; margin-top:5px; font-size:14px;">
                    <option value="ecer">ECER</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px; font-weight:700;">SATUAN JUAL</label>
                <select id="editUnit" class="main-input" style="width:100%; margin-top:5px; font-size:14px;"></select>
            </div>
            <div>
                <label style="font-size:12px; font-weight:700;">JUMLAH (QTY)</label>
                <input type="number" id="editQty" class="main-input" style="width:100%; margin-top:5px;" min="1" step="1">
            </div>
            <div>
                <label style="font-size:12px; font-weight:700;">HARGA / SATUAN</label>
                <input type="number" id="editPrice" class="main-input" style="width:100%; margin-top:5px;" min="0" step="0.01">
            </div>
            <div>
                <label style="font-size:12px; font-weight:700;">DISKON (RP)</label>
                <input type="number" id="editDisc" class="main-input" style="width:100%; margin-top:5px;">
            </div>
        </div>
        <button onclick="saveEdit()" style="width:100%; margin-top: 25px; padding:15px; background:var(--primary); color:#fff; border:none; border-radius:10px; font-weight:700; cursor:pointer;">SIMPAN PERUBAHAN</button>
        <button onclick="closeModals()" style="width:100%; margin-top: 10px; background:none; border:none; color:#64748b; cursor:pointer; font-weight:600;">BATAL (ESC)</button>
    </div>
</div>

<div id="searchModal" class="modal">
    <div class="modal-content" style="width: 85%; max-width: 900px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0; font-weight:800;"><i class="fa fa-box-open"></i> Cari Produk</h2>
            <span style="color:#64748b; font-size:13px;">Gunakan <kbd>â†‘â†“</kbd> & <kbd>Enter</kbd></span>
        </div>
        <input type="text" id="dbSearchInput" class="main-input" placeholder="Ketik nama produk / scan barcode..." style="width:100%; margin-bottom:20px; background:#f1f5f9;">
        <div style="max-height: 450px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 12px;">
            <table style="width:100%;">
                <thead style="background:#f8fafc; position:sticky; top:0;">
                    <tr>
                        <th style="padding:12px;">NAMA PRODUK</th>
                        <th style="padding:12px;">BARCODE</th>
                        <th style="padding:12px; text-align:right;">HARGA</th>
                        <th style="padding:12px; text-align:center;">STOK</th>
                    </tr>
                </thead>
                <tbody id="dbSearchBody"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="payModal" class="modal">
    <div class="modal-content" style="width: 450px;">
        <h2 style="margin-top:0; font-weight:800;">Pembayaran</h2>
        <div style="background:#f8fafc; padding:20px; border-radius:12px; margin-bottom:20px; text-align:center;">
            <div style="font-size:14px; color:#64748b;">Total Yang Harus Dibayar</div>
            <div id="modalTotal" style="font-size: 32px; font-weight: 800; color: var(--primary);">Rp 0</div>
            <div id="paymentPointWrap" style="margin-top:12px; padding:12px 14px; border-radius:12px; background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%); border:1px solid #f59e0b; box-shadow:0 8px 18px rgba(245,158,11,0.22); text-align:left;">
                <div style="font-size:11px; font-weight:800; letter-spacing:0.08em; color:#92400e;">MEMBER SUMMARY</div>
                <div id="paymentPointInfo" style="margin-top:4px; font-size:18px; font-weight:800; color:#7c2d12;">Estimasi poin masuk: 0</div>
                <div id="paymentPointSub" style="margin-top:3px; font-size:12px; color:#78350f;">Pilih pelanggan agar poin tersimpan ke akun member.</div>
            </div>
        </div>
        <div style="margin-top:-8px; margin-bottom:16px; padding:12px 14px; border-radius:12px; border:1px solid #cbd5e1; background:#f8fafc;">
            <div style="font-size:12px; font-weight:800; color:#334155; margin-bottom:8px;">TUKAR POIN</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; align-items:end;">
                <div>
                    <label style="font-weight:700; font-size:12px;">Poin Ditukar</label>
                    <input type="number" id="redeemPoints" class="main-input" min="0" step="1" value="0" style="width:100%; font-size:16px; padding:10px 12px;">
                </div>
                <div>
                    <div id="redeemBalance" style="font-size:12px; color:#334155; font-weight:700;">Saldo poin pelanggan: 0</div>
                    <div id="redeemHint" style="font-size:12px; color:#64748b;">Pilih pelanggan untuk menukar poin.</div>
                    <div id="redeemValue" style="font-size:15px; font-weight:800; color:#0f172a; margin-top:5px;">Potongan: Rp 0</div>
                    <div id="redeemRemain" style="font-size:12px; color:#475569; margin-top:4px;">Sisa poin setelah tukar: 0</div>
                </div>
            </div>
        </div>
        <label style="font-weight:700; font-size:13px;">METODE PEMBAYARAN</label>
        <select id="payMethod" style="width:100%; padding:12px; margin:6px 0 14px; border-radius:10px; border:2px solid #e2e8f0; font-weight: 600;">
            <option value="cash">TUNAI (CASH)</option>
            <option value="qris">QRIS / E-WALLET</option>
            <option value="transfer">TRANSFER BANK</option>
        </select>
        <label style="font-weight:700; font-size:13px;">UANG DITERIMA (CASH)</label>
        <input type="number" id="cashIn" class="main-input" placeholder="Masukkan nominal" style="width:100%; font-size:24px; margin-top:5px;">
        <div style="margin-top:25px; padding:15px; display:flex; justify-content:space-between; align-items:center; background:#f0fdf4; border-radius:10px;">
            <span style="font-weight:700; color:#166534;">KEMBALIAN</span>
            <span id="changeAmount" style="font-size:24px; font-weight:800; color:var(--success)">Rp 0</span>
        </div>
<button onclick="processFinal()" style="width:100%; margin-top: 25px; padding:18px; background:var(--text); color:#fff; border:none; border-radius:12px; font-weight:700; font-size:18px; cursor:pointer;">SELESAIKAN [ENTER]</button>
<button onclick="cancelPayment()" style="width:100%; margin-top:10px; padding:12px; background:#fff1f2; color:#dc2626; border:1px solid #fecaca; border-radius:12px; font-weight:700; font-size:14px; cursor:pointer;">BATALKAN PEMBAYARAN [F9]</button>
    </div>
</div>

<div id="customerModal" class="modal">
    <div class="modal-content" style="width: 85%; max-width: 600px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0; font-weight:800;"><i class="fa fa-users"></i> Pilih Pelanggan</h2>
            <span style="color:#64748b; font-size:13px;">Gunakan <kbd>â†‘â†“</kbd> & <kbd>Enter</kbd></span>
        </div>
        <input type="text" id="customerSearchInput" class="main-input" placeholder="Ketik nama pelanggan / nomor telepon..." style="width:100%; margin-bottom:20px; background:#f1f5f9;">
        <div style="max-height: 350px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 12px;">
            <table style="width:100%;">
                <thead style="background:#f8fafc; position:sticky; top:0;">
                    <tr>
                        <th style="padding:12px;">NAMA PELANGGAN</th>
                        <th style="padding:12px;">TELEPON</th>
                    </tr>
                </thead>
                <tbody id="customerSearchBody"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="holdModal" class="modal">
    <div class="modal-content" style="width: 85%; max-width: 760px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0; font-weight:800;"><i class="fa fa-layer-group"></i> Transaksi Ditahan</h2>
            <span style="color:#64748b; font-size:13px;">Pilih lalu Enter</span>
        </div>
        <div style="max-height: 380px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 12px;">
            <table style="width:100%;">
                <thead style="background:#f8fafc; position:sticky; top:0;">
                    <tr>
                        <th style="padding:12px;">KODE</th>
                        <th style="padding:12px;">PELANGGAN</th>
                        <th style="padding:12px; text-align:center;">ITEM</th>
                        <th style="padding:12px; text-align:right;">TOTAL</th>
                        <th style="padding:12px;">WAKTU</th>
                        <th style="padding:12px; text-align:center;">AKSI</th>
                    </tr>
                </thead>
                <tbody id="holdBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
let cart = [];
let selectedIdx = -1;
let searchIdx = -1;
let selectedCustomer = null;
let customerSearchIdx = -1;
let holdIdx = -1;

const CSRF_TOKEN = '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>';
const cmdInput = document.getElementById('cmdInput');
const dbInput = document.getElementById('dbSearchInput');
const payMethodEl = document.getElementById('payMethod');
const formatRp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
const HOLD_KEY = 'pos_hold_transactions_v1';
const ACTIVE_KEY = 'pos_active_transaction_v1';
const POINT_NOMINAL = Number(<?= json_encode($memberPointNominal) ?>) > 0 ? Number(<?= json_encode($memberPointNominal) ?>) : 1000;
const REDEEM_NOMINAL = Number(<?= json_encode($memberRedeemNominal) ?>) > 0 ? Number(<?= json_encode($memberRedeemNominal) ?>) : 1;

function getHeldList() {
    try {
        const raw = localStorage.getItem(HOLD_KEY);
        const arr = raw ? JSON.parse(raw) : [];
        return Array.isArray(arr) ? arr : [];
    } catch (e) {
        return [];
    }
}

function setHeldList(list) {
    localStorage.setItem(HOLD_KEY, JSON.stringify(Array.isArray(list) ? list : []));
}

function applyCustomerUI() {
    if (selectedCustomer) {
        const levelName = selectedCustomer.level_nama ? ` (${selectedCustomer.level_nama})` : '';
        document.getElementById('customerName').innerText = `${selectedCustomer.nama || '-'}${levelName}`;
        document.getElementById('customerPhone').innerText = selectedCustomer.telepon || '-';
        document.getElementById('customerMonthlySpend').innerText = formatRp(Number(selectedCustomer.total_belanja_bulan || 0));
        document.getElementById('customerPointBalance').innerText = Number(selectedCustomer.saldo_poin || 0).toLocaleString('id-ID');
        document.getElementById('customerInfo').classList.add('active');
        document.querySelector('.customer-btn').style.display = 'none';
    } else {
        document.getElementById('customerName').innerText = '-';
        document.getElementById('customerPhone').innerText = '-';
        document.getElementById('customerMonthlySpend').innerText = formatRp(0);
        document.getElementById('customerPointBalance').innerText = '0';
        document.getElementById('customerInfo').classList.remove('active');
        document.querySelector('.customer-btn').style.display = 'block';
    }
}

function saveActiveState() {
    try {
        const payload = {
            cart: Array.isArray(cart) ? cart : [],
            selectedIdx: Number(selectedIdx ?? -1),
            selectedCustomer: selectedCustomer || null,
            payMethod: String(payMethodEl?.value || 'cash'),
            trxID: String(document.getElementById('trxID')?.innerText || ''),
            redeemPoints: Number(document.getElementById('redeemPoints')?.value || 0),
        };
        localStorage.setItem(ACTIVE_KEY, JSON.stringify(payload));
    } catch (e) {}
}

function loadActiveState() {
    try {
        const raw = localStorage.getItem(ACTIVE_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed?.cart)) {
            cart = parsed.cart;
        }
        selectedIdx = Math.max(-1, Math.min(Number(parsed?.selectedIdx ?? -1), cart.length - 1));
        selectedCustomer = parsed?.selectedCustomer ? {
            id: Number(parsed.selectedCustomer.id || 0),
            nama: String(parsed.selectedCustomer.nama || ''),
            telepon: String(parsed.selectedCustomer.telepon || ''),
            level_nama: String(parsed.selectedCustomer.level_nama || ''),
            member_discount_percent: Number(parsed.selectedCustomer.member_discount_percent || 0),
            saldo_poin: Number(parsed.selectedCustomer.saldo_poin || 0),
            total_belanja_bulan: Number(parsed.selectedCustomer.total_belanja_bulan || 0),
        } : null;
        if (payMethodEl) {
            const savedMethod = String(parsed?.payMethod || '').toLowerCase();
            if (['cash', 'qris', 'transfer'].includes(savedMethod)) payMethodEl.value = savedMethod;
        }
        if (parsed?.trxID) {
            document.getElementById('trxID').innerText = String(parsed.trxID);
        }
        const redeemSaved = Math.max(0, parseInt(parsed?.redeemPoints ?? 0, 10));
        const redeemInput = document.getElementById('redeemPoints');
        if (redeemInput) redeemInput.value = String(redeemSaved);
        applyCustomerUI();
        applyMemberDiscountToCart();
    } catch (e) {}
}

function getMemberDiscountPercent() {
    if (!selectedCustomer) return 0;
    const p = Number(selectedCustomer.member_discount_percent || 0);
    if (!Number.isFinite(p)) return 0;
    return Math.max(0, Math.min(100, p));
}

function getSelectedPriceType() {
    return 'ecer';
}

function recalcItem(item) {
    const qty = Math.max(1, parseInt(item.qty || 0, 10));
    item.qty = qty;
    item.unit_factor = Math.max(1, Number(item.unit_factor || 1));
    item.qty_base = Math.round(qty * item.unit_factor);
    const basePrice = Number(item.manual_price_base ?? item.base_price ?? 0);
    const gross = item.qty_base * basePrice;
    const manualDisc = Math.max(0, Number(item.diskon || 0));
    const memberDiscPercent = Math.max(0, Math.min(100, Number(item.member_discount_percent || 0)));
    const memberDisc = gross * (memberDiscPercent / 100);
    const disc = Math.min(gross, manualDisc + memberDisc);
    item.member_discount_value = memberDisc;
    item.total_discount = disc;
    const net = Math.max(0, gross - item.total_discount);
    const taxPercent = Math.max(0, Number(item.tax_percent || 0));
    item.tax_value = net * (taxPercent / 100);
    item.subtotal = net + item.tax_value;
}

function unitPriceDisplay(item) {
    const basePrice = Number(item.manual_price_base ?? item.base_price ?? 0);
    return basePrice * Number(item.unit_factor || 1);
}

function applyPriceType(item, type) {
    item.price_type = 'ecer';
    item.base_price = Number(item.prices?.ecer ?? 0);
    item.manual_price_base = null;
    recalcItem(item);
}

function applyMemberDiscountToCart() {
    const memberDisc = getMemberDiscountPercent();
    cart.forEach((item) => {
        item.member_discount_percent = memberDisc;
        item.price_type = 'ecer';
        if (item.prices) item.base_price = Number(item.prices.ecer ?? item.base_price ?? 0);
        recalcItem(item);
    });
}

function buildUnits(p) {
    const src = Array.isArray(p.multi_satuan) && p.multi_satuan.length ? p.multi_satuan : [{ nama_satuan: (p.satuan || 'PCS'), qty_dasar: 1 }];
    return src.map((u) => ({
        nama: String(u.nama_satuan || p.satuan || 'PCS'),
        factor: Math.max(1, Number(u.qty_dasar || 1)),
    }));
}

function validateStockClient(item) {
    if (item.is_jasa) return true;
    if (item.qty_base > Number(item.stok || 0)) {
        alert(`Stok ${item.nama} tidak cukup (${item.stok}).`);
        return false;
    }
    return true;
}

function render() {
    const body = document.getElementById('cartBody');
    body.innerHTML = '';
    let total = 0;

    cart.forEach((item, i) => {
        recalcItem(item);
        total += item.subtotal;
        const tr = document.createElement('tr');
        if (i === selectedIdx) tr.classList.add('selected');
        tr.onclick = () => { selectedIdx = i; render(); };
        tr.ondblclick = () => { openEditModal(i); };
        const satuanBadge = item.is_jasa ? `${item.satuan} (JASA)` : item.satuan;
        tr.innerHTML = `
            <td style="text-align:center; color:#94a3b8;">${i + 1}</td>
            <td><strong>${item.nama}</strong><div style="font-size:11px;color:#94a3b8;">${item.price_type.toUpperCase()}${item.is_konsinyasi ? ' | KONSINYASI' : ''}</div></td>
            <td style="text-align:center"><span style="background:#f1f5f9; padding:4px 10px; border-radius:6px; font-weight:700;">${satuanBadge}</span></td>
            <td style="text-align:right">${formatRp(unitPriceDisplay(item))}</td>
            <td style="text-align:center"><span style="background:#f1f5f9; padding:4px 10px; border-radius:6px; font-weight:700;">${item.qty}</span></td>
            <td style="text-align:right; color:var(--danger)">${formatRp(item.total_discount || 0)}</td>
            <td style="text-align:right; font-weight:800;">${formatRp(item.subtotal)}</td>
            <td style="text-align:center">
                <button class="btn-icon text-warning" onclick="openEditModal(${i})"><i class="fa fa-edit"></i></button>
                <button class="btn-icon text-danger" onclick="removeItem(${i})"><i class="fa fa-trash"></i></button>
            </td>
        `;
        body.appendChild(tr);
    });
    document.getElementById('grandTotal').innerText = formatRp(total);
    document.getElementById('modalTotal').innerText = formatRp(total);
    saveActiveState();
}

cmdInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === 'NumpadEnter' || e.key === 'Tab') {
        if (e.key === 'Tab') e.preventDefault();
        const val = this.value.trim();
        if (!val) return;
        handleCommand(val);
        this.value = '';
    }
    if (e.key === 'ArrowDown') { e.preventDefault(); changeSelection(1); }
    if (e.key === 'ArrowUp') { e.preventDefault(); changeSelection(-1); }
    if (e.key === 'Delete') removeItem(selectedIdx);
});

function changeSelection(dir) {
    if (cart.length === 0) return;
    selectedIdx += dir;
    if (selectedIdx < 0) selectedIdx = 0;
    if (selectedIdx >= cart.length) selectedIdx = cart.length - 1;
    render();
    const activeRow = document.querySelector('tr.selected');
    if (activeRow) activeRow.scrollIntoView({ block: 'nearest' });
}

dbInput.addEventListener('input', (e) => searchProductFromDB(e.target.value));
dbInput.addEventListener('keydown', function (e) {
    const rows = document.querySelectorAll('#dbSearchBody tr');
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        searchIdx = Math.min(searchIdx + 1, rows.length - 1);
        highlightSearch(rows);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        searchIdx = Math.max(searchIdx - 1, 0);
        highlightSearch(rows);
    } else if (e.key === 'Enter') {
        if (searchIdx > -1 && rows[searchIdx]) rows[searchIdx].click();
    }
});

function highlightSearch(rows) {
    rows.forEach((r, i) => {
        r.className = (i === searchIdx) ? 'search-row-active' : '';
        if (i === searchIdx) r.scrollIntoView({ block: 'nearest' });
    });
}

async function searchProductFromDB(query) {
    try {
        const r = await fetch(`../../api/produk_search.php?q=${encodeURIComponent(query)}`);
        const d = await r.json();
        const body = document.getElementById('dbSearchBody');
        body.innerHTML = '';
        searchIdx = -1;
        const currentType = getSelectedPriceType();
        if (d.ok && Array.isArray(d.data)) {
            d.data.forEach((p) => {
                const tr = document.createElement('tr');
                tr.onclick = () => { selectFromSearch(p.barcode || p.produk_id); };
                const h = Number(p[`harga_${currentType}`] ?? p.harga_ecer ?? 0);
                tr.innerHTML = `
                    <td style="padding:12px;">${p.nama_produk}</td>
                    <td style="padding:12px;">${p.barcode || '-'}</td>
                    <td style="padding:12px; text-align:right;">${formatRp(h)}</td>
                    <td style="padding:12px; text-align:center;">${Number(p.stok || 0)}</td>
                `;
                body.appendChild(tr);
            });
        }
    } catch (e) {}
}

function openSearchModal() {
    document.getElementById('searchModal').style.display = 'flex';
    dbInput.value = '';
    searchProductFromDB('');
    setTimeout(() => dbInput.focus(), 100);
}

function selectFromSearch(key) {
    fetchProduct(String(key), 1);
    closeModals();
}

function removeItem(idx) {
    if (idx > -1 && confirm('Hapus item ini?')) {
        cart.splice(idx, 1);
        selectedIdx = cart.length - 1;
        render();
    }
}

function clearAll() {
    if (confirm('Kosongkan semua transaksi?')) {
        cart = [];
        selectedIdx = -1;
        render();
        cmdInput.focus();
    }
}

function startNewOrder() {
    if (cart.length > 0) {
        const keep = confirm('Transaksi saat ini belum selesai. Tahan dulu transaksi ini?');
        if (keep) holdCurrentTransaction(false);
    }
    cart = [];
    selectedIdx = -1;
    clearCustomer();
    render();
    cmdInput.focus();
}

function holdCurrentTransaction(showAlert = true) {
    if (cart.length === 0) {
        if (showAlert) alert('Keranjang kosong.');
        return;
    }
    const now = new Date();
    const code = `HOLD-${now.getHours().toString().padStart(2, '0')}${now.getMinutes().toString().padStart(2, '0')}${now.getSeconds().toString().padStart(2, '0')}`;
    const payload = {
        code,
        time: now.toISOString(),
        customer: selectedCustomer ? { ...selectedCustomer } : null,
        items: cart.map((x) => ({ ...x })),
    };
    const list = getHeldList();
    list.unshift(payload);
    setHeldList(list.slice(0, 30));
    cart = [];
    selectedIdx = -1;
    clearCustomer();
    render();
    if (showAlert) alert(`Transaksi ditahan: ${code}`);
    cmdInput.focus();
}

function openHoldModal() {
    const list = getHeldList();
    const body = document.getElementById('holdBody');
    holdIdx = -1;
    body.innerHTML = '';
    if (!list.length) {
        body.innerHTML = '<tr><td colspan="6" style="padding:16px; text-align:center; color:#94a3b8;">Belum ada transaksi ditahan</td></tr>';
    } else {
        list.forEach((h, i) => {
            const total = (h.items || []).reduce((s, it) => s + Number(it.subtotal || 0), 0);
            const tr = document.createElement('tr');
            tr.onclick = () => recallHeld(i);
            tr.innerHTML = `
                <td style="padding:12px; font-weight:700;">${h.code || ('HOLD-' + (i + 1))}</td>
                <td style="padding:12px;">${h.customer?.nama || '-'}</td>
                <td style="padding:12px; text-align:center;">${(h.items || []).length}</td>
                <td style="padding:12px; text-align:right;">${formatRp(total)}</td>
                <td style="padding:12px;">${new Date(h.time || Date.now()).toLocaleTimeString('id-ID')}</td>
                <td style="padding:12px; text-align:center;">
                    <button class="btn-icon text-primary" onclick="event.stopPropagation(); recallHeld(${i});"><i class="fa fa-share"></i></button>
                    <button class="btn-icon text-danger" onclick="event.stopPropagation(); deleteHeld(${i});"><i class="fa fa-trash"></i></button>
                </td>
            `;
            body.appendChild(tr);
        });
    }
    document.getElementById('holdModal').style.display = 'flex';
}

function recallHeld(index) {
    const list = getHeldList();
    if (!list[index]) return;
    if (cart.length > 0) {
        const ok = confirm('Keranjang saat ini akan diganti. Lanjut?');
        if (!ok) return;
    }
    const h = list[index];
    cart = (h.items || []).map((x) => ({ ...x }));
    selectedCustomer = h.customer ? { ...h.customer } : null;
    applyCustomerUI();
    applyMemberDiscountToCart();
    list.splice(index, 1);
    setHeldList(list);
    selectedIdx = cart.length ? 0 : -1;
    render();
    closeModals();
}

function deleteHeld(index) {
    const list = getHeldList();
    if (!list[index]) return;
    list.splice(index, 1);
    setHeldList(list);
    openHoldModal();
}

function fillEditUnitOptions(item) {
    const unitEl = document.getElementById('editUnit');
    unitEl.innerHTML = '';
    item.units.forEach((u, idx) => {
        const op = document.createElement('option');
        op.value = String(idx);
        op.textContent = `${u.nama} x${u.factor}`;
        unitEl.appendChild(op);
    });
    unitEl.value = String(item.unit_idx || 0);
}

function refreshEditPriceHint() {
    if (selectedIdx < 0) return;
    const item = cart[selectedIdx];
    const unitEl = document.getElementById('editUnit');
    const idx = Math.max(0, parseInt(unitEl.value || '0', 10));
    const unit = item.units[idx] || item.units[0];
    const basePrice = Number(item.manual_price_base ?? item.base_price ?? 0);
    document.getElementById('editPrice').value = (basePrice * unit.factor).toFixed(2);
}

function openEditModal(idx) {
    selectedIdx = idx;
    const item = cart[idx];
    document.getElementById('editTitle').innerText = item.nama;
    document.getElementById('editQty').value = item.qty;
    document.getElementById('editDisc').value = item.diskon;
    document.getElementById('editPriceType').value = item.price_type || 'ecer';
    fillEditUnitOptions(item);
    refreshEditPriceHint();
    document.getElementById('editModal').style.display = 'flex';
    setTimeout(() => document.getElementById('editQty').focus(), 100);
}

function saveEdit() {
    if (selectedIdx < 0) return;
    const item = cart[selectedIdx];
    const q = Math.max(1, parseInt(document.getElementById('editQty').value || '1', 10));
    const d = Math.max(0, Number(document.getElementById('editDisc').value || 0));
    const type = String(document.getElementById('editPriceType').value || 'ecer');
    const unitIdx = Math.max(0, parseInt(document.getElementById('editUnit').value || '0', 10));
    const priceUnit = Math.max(0, Number(document.getElementById('editPrice').value || 0));

    applyPriceType(item, type);
    item.unit_idx = unitIdx;
    item.satuan = item.units[unitIdx]?.nama || item.satuan;
    item.unit_factor = Number(item.units[unitIdx]?.factor || 1);
    item.qty = q;
    item.diskon = d;
    item.manual_price_base = item.unit_factor > 0 ? (priceUnit / item.unit_factor) : 0;
    recalcItem(item);
    if (!validateStockClient(item)) return;
    render();
    closeModals();
}

document.getElementById('editPriceType').addEventListener('change', function () {
    if (selectedIdx < 0) return;
    applyPriceType(cart[selectedIdx], this.value);
    refreshEditPriceHint();
});
document.getElementById('editUnit').addEventListener('change', refreshEditPriceHint);

const editFlowIds = ['editPriceType', 'editUnit', 'editQty', 'editPrice', 'editDisc'];
editFlowIds.forEach((id, idx) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== 'NumpadEnter') return;
        e.preventDefault();
        const nextId = editFlowIds[idx + 1];
        if (nextId) {
            const nextEl = document.getElementById(nextId);
            if (nextEl) {
                nextEl.focus();
                if (typeof nextEl.select === 'function') nextEl.select();
            }
            return;
        }
        saveEdit();
    });
});

function toggleCashInput() {
    const cashIn = document.getElementById('cashIn');
    const summary = getPaymentSummary();
    const method = (payMethodEl?.value || 'cash').toLowerCase();
    if (method === 'cash') {
        cashIn.disabled = false;
        cashIn.placeholder = 'Masukkan nominal';
        cashIn.value = '';
    } else {
        cashIn.disabled = true;
        cashIn.placeholder = 'Auto sesuai total';
        cashIn.value = String(summary.totalFinal);
    }
    updatePaymentInfo();
}

function getPaymentSummary() {
    const total = cart.reduce((s, i) => s + Number(i.subtotal || 0), 0);
    const redeemReq = Math.max(0, parseInt(document.getElementById('redeemPoints')?.value || '0', 10));
    const available = selectedCustomer ? Math.max(0, Number(selectedCustomer.saldo_poin || 0)) : 0;
    const bySaldo = Math.min(redeemReq, available);
    const byTotal = Math.floor(Math.max(0, total) / REDEEM_NOMINAL);
    const redeemPointsUsed = Math.max(0, Math.min(bySaldo, byTotal));
    const redeemDiscount = redeemPointsUsed * REDEEM_NOMINAL;
    const totalFinal = Math.max(0, total - redeemDiscount);
    return { total, redeemReq, redeemPointsUsed, redeemDiscount, totalFinal };
}

function updatePaymentInfo() {
    const summary = getPaymentSummary();
    const method = (payMethodEl?.value || 'cash').toLowerCase();
    if (method !== 'cash') {
        document.getElementById('cashIn').value = String(summary.totalFinal);
    }
    const cash = Number(document.getElementById('cashIn').value || 0);
    const change = cash - summary.totalFinal;
    document.getElementById('changeAmount').innerText = formatRp(change > 0 ? change : 0);

    const poin = selectedCustomer ? Math.floor(Math.max(0, summary.totalFinal) / POINT_NOMINAL) : 0;
    const infoEl = document.getElementById('paymentPointInfo');
    const subEl = document.getElementById('paymentPointSub');
    const wrapEl = document.getElementById('paymentPointWrap');
    const redeemHint = document.getElementById('redeemHint');
    const redeemValue = document.getElementById('redeemValue');
    const redeemInput = document.getElementById('redeemPoints');
    const redeemBalance = document.getElementById('redeemBalance');
    const redeemRemain = document.getElementById('redeemRemain');
    const saldoPoin = selectedCustomer ? Math.max(0, Number(selectedCustomer.saldo_poin || 0)) : 0;
    if (redeemInput) {
        redeemInput.disabled = !selectedCustomer || saldoPoin <= 0;
        if (!selectedCustomer) redeemInput.value = '0';
        if (selectedCustomer && saldoPoin <= 0) redeemInput.value = '0';
    }
    if (redeemValue) {
        redeemValue.innerText = `Potongan: ${formatRp(summary.redeemDiscount)}`;
    }
    if (redeemBalance) {
        redeemBalance.innerText = `Saldo poin pelanggan: ${saldoPoin.toLocaleString('id-ID')}`;
    }
    if (redeemRemain) {
        const sisaPoin = Math.max(0, saldoPoin - summary.redeemPointsUsed);
        redeemRemain.innerText = `Sisa poin setelah tukar: ${sisaPoin.toLocaleString('id-ID')}`;
    }
    if (selectedCustomer) {
        if (redeemInput && Number(redeemInput.value || 0) !== summary.redeemPointsUsed) {
            redeemInput.value = String(summary.redeemPointsUsed);
        }
        infoEl.innerText = `Estimasi poin masuk: ${poin}`;
        const level = selectedCustomer.level_nama ? ` (${selectedCustomer.level_nama})` : '';
        const disc = Number(selectedCustomer.member_discount_percent || 0);
        subEl.innerText = `Member: ${selectedCustomer.nama || '-'}${level} | Diskon level: ${disc.toFixed(2)}% | Level dari belanja bulanan`;
        if (redeemHint) {
            redeemHint.innerText = saldoPoin > 0
                ? `Gunakan poin untuk potongan belanja | 1 poin = ${formatRp(REDEEM_NOMINAL)}`
                : 'Saldo poin kosong, tidak bisa tukar poin.';
        }
        wrapEl.style.background = 'linear-gradient(135deg,#dcfce7 0%,#bbf7d0 100%)';
        wrapEl.style.borderColor = '#22c55e';
        wrapEl.style.boxShadow = '0 8px 18px rgba(34,197,94,0.22)';
    } else {
        infoEl.innerText = 'Estimasi poin masuk: 0';
        subEl.innerText = 'Pilih pelanggan agar poin tersimpan ke akun member.';
        if (redeemHint) redeemHint.innerText = 'Pilih pelanggan untuk menukar poin.';
        if (redeemBalance) redeemBalance.innerText = 'Saldo poin pelanggan: 0';
        if (redeemRemain) redeemRemain.innerText = 'Sisa poin setelah tukar: 0';
        wrapEl.style.background = 'linear-gradient(135deg,#fef3c7 0%,#fde68a 100%)';
        wrapEl.style.borderColor = '#f59e0b';
        wrapEl.style.boxShadow = '0 8px 18px rgba(245,158,11,0.22)';
    }
    document.getElementById('modalTotal').innerText = formatRp(summary.totalFinal);
    saveActiveState();
}

function openPayment() {
    if (cart.length === 0) return alert('Keranjang kosong!');
    document.getElementById('payModal').style.display = 'flex';
    toggleCashInput();
    updatePaymentInfo();
    setTimeout(() => {
        const method = (payMethodEl?.value || 'cash').toLowerCase();
        if (method === 'cash') {
            document.getElementById('cashIn').focus();
            return;
        }
        const btn = document.querySelector('#payModal button[onclick="processFinal()"]');
        if (btn) btn.focus();
    }, 100);
}

function cancelPayment() {
    document.getElementById('payModal').style.display = 'none';
    document.getElementById('cashIn').value = '';
    document.getElementById('changeAmount').innerText = formatRp(0);
    document.getElementById('redeemPoints').value = '0';
    cmdInput.focus();
}

if (payMethodEl) {
    payMethodEl.addEventListener('change', () => {
        toggleCashInput();
        saveActiveState();
    });
}

document.getElementById('cashIn').addEventListener('input', function () {
    updatePaymentInfo();
});
document.getElementById('redeemPoints').addEventListener('input', function () {
    updatePaymentInfo();
});
document.getElementById('redeemPoints').addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== 'NumpadEnter') return;
    e.preventDefault();
    const cashEl = document.getElementById('cashIn');
    if (cashEl && !cashEl.disabled) {
        cashEl.focus();
        cashEl.select();
    }
});

async function processFinal() {
    if (cart.length === 0) return alert('Keranjang kosong!');
    const summary = getPaymentSummary();
    const method = payMethodEl?.value || 'cash';
    let amount = Number(document.getElementById('cashIn').value || 0);
    if (method !== 'cash') amount = summary.totalFinal;
    if (method === 'cash') {
        if (amount <= 0) {
            return alert('Masukkan nominal uang diterima terlebih dahulu.');
        }
        if (amount < summary.totalFinal) {
            return alert('Nominal cash kurang dari total bayar. Lengkapi nominal pembayaran.');
        }
    }

    const items = cart.map((i) => ({
        produk_id: i.id,
        qty: Number(i.qty_base || 0),
        price: Number(i.manual_price_base ?? i.base_price ?? 0),
        discount: Number(i.total_discount ?? i.diskon ?? 0),
        tax_percent: Number(i.tax_percent || 0),
        price_type: i.price_type || 'ecer',
    }));

    try {
        const res = await fetch('../../api/penjualan_save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
            },
            body: JSON.stringify({
                items,
                pelanggan_id: selectedCustomer?.id || 0,
                payment: { method, amount, redeem_points: summary.redeemPointsUsed },
            }),
        });
        const raw = await res.text();
        let d = null;
        try {
            d = JSON.parse(raw);
        } catch (_) {
            d = null;
        }
        if (!res.ok) {
            const serverMsg = d?.msg || raw || `HTTP ${res.status}`;
            return alert(`Gagal simpan transaksi: ${serverMsg}`);
        }
        if (!d) return alert('Gagal simpan transaksi: response server tidak valid');
        if (!d.ok) return alert(d.msg || 'Gagal simpan transaksi');
        const bayarInfo = `\nUang diterima: ${formatRp(Number(d.uang_diterima || d.dibayar || 0))}\nDibayar (net): ${formatRp(Number(d.dibayar || 0))}\nKembalian: ${formatRp(Number(d.kembalian || 0))}`;
        const poinInfo = `\nPoin didapat: ${Number(d.poin_didapat || 0).toLocaleString('id-ID')}\nPoin ditukar: ${Number(d.poin_ditukar || 0).toLocaleString('id-ID')}`;
        alert(`Transaksi berhasil\nInvoice: ${d.nomor}\nTotal: ${formatRp(d.total)}${bayarInfo}${poinInfo}`);
        if (selectedCustomer) {
            selectedCustomer.saldo_poin = Math.max(0, Number(selectedCustomer.saldo_poin || 0) - Number(d.poin_ditukar || 0) + Number(d.poin_didapat || 0));
            selectedCustomer.total_belanja_bulan = Math.max(0, Number(selectedCustomer.total_belanja_bulan || 0) + Number(d.total || 0));
            applyCustomerUI();
        }
        document.getElementById('redeemPoints').value = '0';
        cart = [];
        selectedIdx = -1;
        clearCustomer();
        render();
        closeModals();
        document.getElementById('trxID').innerText = d.nomor || ('TRX-' + new Date().getTime());
        saveActiveState();
    } catch (e) {
        alert(`Gagal koneksi ke server transaksi. ${e?.message || ''}`.trim());
    }
}

async function fetchProduct(key, qty, opts = {}) {
    try {
        const r = await fetch(`../../api/produk_search.php?q=${encodeURIComponent(key)}`);
        const d = await r.json();
        if (!(d.ok && Array.isArray(d.data) && d.data.length > 0)) return;
        const needle = String(key).trim().toLowerCase();
        let p = d.data.find((x) => String(x.barcode || '').toLowerCase() === needle);
        if (!p) p = d.data.find((x) => String(x.sku || '').toLowerCase() === needle);
        if (!p) p = d.data[0];

        const units = buildUnits(p);
        const priceType = getSelectedPriceType();
        const prices = {
            ecer: Number(p.harga_ecer || 0),
            grosir: Number(p.harga_grosir || p.harga_ecer || 0),
            reseller: Number(p.harga_reseller || p.harga_ecer || 0),
            member: Number(p.harga_member || p.harga_ecer || 0),
        };
        const exist = cart.findIndex((i) => i.id === p.produk_id && i.price_type === priceType && i.unit_idx === 0);
        if (exist > -1) {
            if (!opts.noIncrementIfExists) {
                cart[exist].qty += Math.max(1, parseInt(qty || 1, 10));
                recalcItem(cart[exist]);
                if (!validateStockClient(cart[exist])) {
                    cart[exist].qty -= Math.max(1, parseInt(qty || 1, 10));
                    recalcItem(cart[exist]);
                    return;
                }
            }
            selectedIdx = exist;
        } else {
            const item = {
                id: Number(p.produk_id),
                nama: String(p.nama_produk || ''),
                satuan_dasar: String(p.satuan || 'PCS'),
                satuan: String(units[0]?.nama || p.satuan || 'PCS'),
                unit_idx: 0,
                unit_factor: Number(units[0]?.factor || 1),
                units,
                qty: Math.max(1, parseInt(qty || 1, 10)),
                qty_base: 1,
                diskon: 0,
                tax_percent: Number(p.pajak_persen || 0),
                price_type: priceType,
                prices,
                base_price: Number(prices[priceType] || 0),
                manual_price_base: null,
                is_jasa: Number(p.is_jasa || 0) === 1,
                is_konsinyasi: Number(p.is_konsinyasi || 0) === 1,
                stok: Number(p.stok || 0),
                member_discount_percent: getMemberDiscountPercent(),
                member_discount_value: 0,
                total_discount: 0,
                subtotal: 0,
                tax_value: 0,
            };
            recalcItem(item);
            if (!validateStockClient(item)) return;
            cart.push(item);
            selectedIdx = cart.length - 1;
        }
        render();
        if (opts.focusQty === true && selectedIdx > -1) {
            openEditModal(selectedIdx);
        }
    } catch (e) {}
}

function handleCommand(val) {
    if (val.startsWith('*')) {
        const payload = val.substring(1).trim();
        if (payload === '') {
            if (selectedIdx < 0) return;
            openEditModal(selectedIdx);
            return;
        }
        fetchProduct(payload, 1, { focusQty: true, noIncrementIfExists: true });
        return;
    }
    if (val.startsWith('/')) {
        if (selectedIdx < 0) return;
        const priceUnit = Math.max(0, Number(val.substring(1) || 0));
        const item = cart[selectedIdx];
        item.manual_price_base = item.unit_factor > 0 ? (priceUnit / item.unit_factor) : 0;
        recalcItem(item);
        render();
        return;
    }
    if (val.startsWith('-')) {
        if (selectedIdx < 0) return;
        const disc = Math.max(0, Number(val.substring(1) || 0));
        cart[selectedIdx].diskon = disc;
        recalcItem(cart[selectedIdx]);
        render();
        return;
    }
    fetchProduct(val, 1);
}

function copyTrxID() {
    const id = document.getElementById('trxID').innerText;
    navigator.clipboard.writeText(id);
    alert('ID Struk disalin!');
}

function closeModals() {
    document.querySelectorAll('.modal').forEach((m) => { m.style.display = 'none'; });
    cmdInput.focus();
}

window.addEventListener('keydown', (e) => {
    const holdOpen = document.getElementById('holdModal').style.display === 'flex';
    if (holdOpen) {
        const rows = document.querySelectorAll('#holdBody tr');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            holdIdx = Math.min(holdIdx + 1, rows.length - 1);
            rows.forEach((r, i) => { r.className = (i === holdIdx) ? 'search-row-active' : ''; });
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            holdIdx = Math.max(holdIdx - 1, 0);
            rows.forEach((r, i) => { r.className = (i === holdIdx) ? 'search-row-active' : ''; });
            return;
        }
        if (e.key === 'Enter' && holdIdx > -1 && rows[holdIdx]) {
            e.preventDefault();
            rows[holdIdx].click();
            return;
        }
    }
    if (e.key === 'F1') { e.preventDefault(); openSearchModal(); }
    if (e.key === 'F2') { e.preventDefault(); cmdInput.focus(); cmdInput.select(); }
    if (e.key === 'F6') { e.preventDefault(); holdCurrentTransaction(); }
    if (e.key === 'F7') { e.preventDefault(); openHoldModal(); }
    if (e.key === 'F8') { e.preventDefault(); startNewOrder(); }
    if (e.key === 'F10') { e.preventDefault(); openPayment(); }
    if (e.key === 'F9' && document.getElementById('payModal').style.display === 'flex') { e.preventDefault(); cancelPayment(); }
    if (e.key === 'Escape') closeModals();
    if (e.key === 'Enter' && document.getElementById('payModal').style.display === 'flex') {
        const activeTag = (document.activeElement?.tagName || '').toLowerCase();
        const activeId = document.activeElement?.id || '';
        const isFormControl = ['input', 'select', 'textarea'].includes(activeTag);
        if (isFormControl && activeId !== 'cashIn') return;
        processFinal();
    }
});

setInterval(() => {
    const now = new Date();
    document.getElementById('realtime').innerText = now.toLocaleString('id-ID');
}, 1000);

document.addEventListener('click', (e) => {
    const tag = (e.target.tagName || '').toLowerCase();
    const inModal = e.target.closest('.modal-content');
    if (tag === 'input' || tag === 'select' || tag === 'textarea' || inModal) return;
    const hasOpenModal = Array.from(document.querySelectorAll('.modal')).some((m) => m.style.display === 'flex');
    if (!hasOpenModal) cmdInput.focus();
});

function openCustomerModal() {
    document.getElementById('customerModal').style.display = 'flex';
    document.getElementById('customerSearchInput').value = '';
    searchCustomerFromDB('');
    setTimeout(() => document.getElementById('customerSearchInput').focus(), 100);
}

async function searchCustomerFromDB(query) {
    try {
        const r = await fetch(`../../api/pelanggan_search.php?q=${encodeURIComponent(query)}`);
        const d = await r.json();
        const body = document.getElementById('customerSearchBody');
        body.innerHTML = '';
        customerSearchIdx = -1;
        if (d.ok && Array.isArray(d.data)) {
            d.data.forEach((c) => {
                const tr = document.createElement('tr');
                tr.onclick = () => {
                    selectCustomer(
                        c.pelanggan_id,
                        c.nama_pelanggan,
                        c.telepon,
                        c.level_nama || '',
                        c.level_diskon_persen ?? c.flat_diskon ?? 0,
                        c.saldo_poin ?? 0,
                        c.total_belanja_bulan ?? 0
                    );
                };
                tr.innerHTML = `<td style="padding:12px;">${c.nama_pelanggan}${c.level_nama ? ` <small style="color:#64748b;">(${c.level_nama} - ${Number(c.level_diskon_persen || 0).toFixed(2)}%)</small>` : ''}<div style="font-size:11px; color:#94a3b8;">Belanja bln ini: ${formatRp(Number(c.total_belanja_bulan || 0))} | Saldo poin: ${Number(c.saldo_poin || 0).toLocaleString('id-ID')}</div></td><td style="padding:12px;">${c.telepon || '-'}</td>`;
                body.appendChild(tr);
            });
        }
    } catch (e) {}
}

function selectCustomer(id, nama, telepon, levelNama = '', memberDiscountPercent = 0, saldoPoin = 0, totalBelanjaBulan = 0) {
    selectedCustomer = {
        id: Number(id),
        nama,
        telepon,
        level_nama: String(levelNama || ''),
        member_discount_percent: Math.max(0, Math.min(100, Number(memberDiscountPercent || 0))),
        saldo_poin: Math.max(0, Number(saldoPoin || 0)),
        total_belanja_bulan: Math.max(0, Number(totalBelanjaBulan || 0)),
    };
    applyCustomerUI();
    applyMemberDiscountToCart();
    render();
    saveActiveState();
    closeModals();
}

function clearCustomer() {
    selectedCustomer = null;
    document.getElementById('redeemPoints').value = '0';
    applyCustomerUI();
    applyMemberDiscountToCart();
    render();
    saveActiveState();
}

const custSearchInput = document.getElementById('customerSearchInput');
if (custSearchInput) {
    custSearchInput.addEventListener('input', (e) => searchCustomerFromDB(e.target.value));
    custSearchInput.addEventListener('keydown', function (e) {
        const rows = document.querySelectorAll('#customerSearchBody tr');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            customerSearchIdx = Math.min(customerSearchIdx + 1, rows.length - 1);
            rows.forEach((r, i) => { r.className = (i === customerSearchIdx) ? 'search-row-active' : ''; });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            customerSearchIdx = Math.max(customerSearchIdx - 1, 0);
            rows.forEach((r, i) => { r.className = (i === customerSearchIdx) ? 'search-row-active' : ''; });
        } else if (e.key === 'Enter') {
            if (customerSearchIdx > -1 && rows[customerSearchIdx]) rows[customerSearchIdx].click();
        }
    });
}

loadActiveState();
render();
</script>
</body>
</html>
