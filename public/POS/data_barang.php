<?php
session_start();
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';

requireLogin();
requireDevice();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Data Barang | Kasir</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #f59e0b;
            --primary-dark: #d97706;
            --bg: #f1f5f9;
            --border: #e2e8f0;
            --text-main: #1e293b;
        }

        * { box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            margin: 0;
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Body tidak scroll */
        }

        /* Container Utama */
        .app-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            max-width: 1600px; /* Lebih lebar untuk tabel */
            margin: 0 auto;
            width: 100%;
            padding: 12px 16px;
            gap: 12px;
        }

        /* Mini Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .header h2 { margin: 0; font-size: 18px; font-weight: 700; color: #0f172a; }

        .top-actions { display: flex; gap: 8px; }

        .btn {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 12px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        .btn:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-primary { background: var(--primary); color: #fff; border: none; }
        .btn-primary:hover { background: var(--primary-dark); }

        /* Toolbar Pencarian Ringkas */
        .toolbar {
            background: #fff;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            display: flex;
            gap: 10px;
            align-items: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .search-box { flex-grow: 1; position: relative; }
        
        input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
            background: #f8fafc;
        }
        input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); }

        /* Area Tabel (Minim Scroll Body) */
        .table-wrapper {
            flex-grow: 1;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: auto; /* Scroll hanya di sini */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12px; }
        
        thead th {
            position: sticky;
            top: 0;
            background: #f8fafc;
            z-index: 10;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.025em;
            padding: 10px 12px;
            border-bottom: 2px solid var(--border);
            text-align: left;
        }

        tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap;
        }

        tr:nth-child(even) td { background: #fafafa; }
        tr:hover td { background: #f1f5f9 !important; }

        .r { text-align: right; }
        .muted { color: #94a3b8; font-size: 11px; margin-top: 2px; }
        
        .badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            display: inline-block;
        }
        .ok { background: #dcfce7; color: #166534; }
        .low { background: #fee2e2; color: #991b1b; }

        /* Custom Scrollbar */
        .table-wrapper::-webkit-scrollbar { width: 6px; height: 6px; }
        .table-wrapper::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .header h2 { font-size: 16px; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <header class="header">
        <h2>Data Barang</h2>
        <div class="top-actions">
            <a class="btn" href="index.php">POS</a>
            <a class="btn btn-primary" href="../admin/produk/master_barang.php">Master</a>
        </div>
    </header>

    <div class="toolbar">
        <div class="search-box">
            <input id="q" type="text" placeholder="Cari nama, SKU, atau scan barcode..." autofocus autocomplete="off">
        </div>
        <button id="reloadBtn" class="btn" type="button">Refresh</button>
    </div>

    <div class="table-wrapper" id="scrollContainer">
        <table>
            <thead>
                <tr>
                    <th>Produk</th>
                    <th class="hide-mobile">SKU / Barcode</th>
                    <th>Satuan</th>
                    <th class="r">Stok</th>
                    <th class="r">Ecer</th>
                    <th class="r hide-mobile">Grosir</th>
                    <th class="r hide-mobile">Member</th>
                    <th class="r hide-mobile">Reseller</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="tbody">
                </tbody>
        </table>
    </div>
</div>

<script>
    const q = document.getElementById('q');
    const tbody = document.getElementById('tbody');
    const scrollContainer = document.getElementById('scrollContainer');
    const rp = (n) => 'Rp' + Number(n || 0).toLocaleString('id-ID');
    
    let allData = [];
    let displayedCount = 30; // Load 30 data pertama
    const increment = 30;

    function render() {
        const toShow = allData.slice(0, displayedCount);
        
        if (toShow.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:#94a3b8;">Data tidak ditemukan</td></tr>';
            return;
        }

        tbody.innerHTML = toShow.map(p => {
            const stok = Number(p.stok || 0);
            const low = Number(p.min_stok || 0) > 0 && stok <= Number(p.min_stok);
            return `
                <tr>
                    <td>
                        <div style="font-weight:600; color:#0f172a;">${p.nama_produk || '-'}</div>
                        <div class="muted">Modal: ${rp(p.harga_modal)}</div>
                    </td>
                    <td class="hide-mobile">
                        <div>${p.sku || '-'}</div>
                        <div class="muted">${p.barcode || '-'}</div>
                    </td>
                    <td>${p.satuan || '-'}</td>
                    <td class="r" style="font-weight:700; color:${low ? '#dc2626':'#0f172a'}">${stok}</td>
                    <td class="r" style="font-weight:600;">${rp(p.harga_ecer)}</td>
                    <td class="r hide-mobile">${rp(p.harga_grosir)}</td>
                    <td class="r hide-mobile">${rp(p.harga_member)}</td>
                    <td class="r hide-mobile">${rp(p.harga_reseller)}</td>
                    <td><span class="badge ${low ? 'low' : 'ok'}">${low ? 'TIPIS' : 'AMAN'}</span></td>
                </tr>
            `;
        }).join('');
    }

    async function loadData(query = '') {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:#94a3b8;">Memuat data...</td></tr>';
        try {
            const r = await fetch(`../../api/produk_search.php?q=${encodeURIComponent(query)}`);
            const d = await r.json();
            allData = d?.ok ? d.data : [];
            displayedCount = increment;
            render();
        } catch (_) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:#dc2626;">Gagal mengambil data</td></tr>';
        }
    }

    // Lazy Loading on Scroll
    scrollContainer.addEventListener('scroll', () => {
        if (scrollContainer.scrollTop + scrollContainer.clientHeight >= scrollContainer.scrollHeight - 50) {
            if (displayedCount < allData.length) {
                displayedCount += increment;
                render();
            }
        }
    });

    let timer = null;
    q.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadData(q.value.trim()), 300);
    });

    document.getElementById('reloadBtn').addEventListener('click', () => loadData(q.value.trim()));

    // Load awal
    loadData('');
</script>

</body>
</html>