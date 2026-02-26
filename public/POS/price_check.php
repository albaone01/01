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
    <title>Price Check Mini</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #10b981;
            --bg: #f8fafc;
            --border: #e2e8f0;
        }

        * { box-sizing: border-box; transition: all 0.15s ease; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            margin: 0;
            color: #0f172a;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header Ringkas */
        .top-bar {
            background: #fff;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            gap: 12px;
            flex-shrink: 0;
        }

        .search-area {
            flex-grow: 1;
            max-width: 600px;
            position: relative;
        }

        input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            background: #f1f5f9;
        }

        input:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .btn-back {
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            white-space: nowrap;
        }

        /* Grid Utama - Minim Padding */
        #result-container {
            flex-grow: 1;
            overflow-y: auto;
            padding: 8px; /* Minim padding */
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 8px;
        }

        /* Card Padat */
        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .name {
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .meta {
            font-size: 11px;
            color: #64748b;
        }

        .price-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            font-size: 12px;
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px solid #f1f5f9;
        }

        .price-row div b { color: var(--primary); }

        .badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
            display: inline-block;
        }
        .ok { background: #dcfce7; color: #166534; }
        .low { background: #fee2e2; color: #991b1b; }

        /* Loader */
        #loader {
            text-align: center;
            padding: 15px;
            font-size: 12px;
            color: #64748b;
            display: none;
        }

        @media (max-width: 640px) {
            .grid { grid-template-columns: 1fr; }
            .top-bar h2 { display: none; }
        }
    </style>
</head>
<body>

    <div class="top-bar">
        <h2 style="margin:0; font-size: 16px;">Price Check</h2>
        <div class="search-area">
            <input id="q" type="text" placeholder="Scan Barcode / Nama Produk..." autofocus autocomplete="off">
        </div>
        <a href="index.php" class="btn-back">KEMBALI</a>
    </div>

    <div id="result-container">
        <div id="result" class="grid">
            </div>
        <div id="loader">Memuat produk lainnya...</div>
    </div>

    <script>
        const q = document.getElementById('q');
        const result = document.getElementById('result');
        const container = document.getElementById('result-container');
        const loader = document.getElementById('loader');
        
        let allData = [];
        let displayedCount = 20; // Tampilkan 20 dulu
        const increment = 20; // Tambah 20 setiap scroll

        const rp = (n) => 'Rp' + Number(n || 0).toLocaleString('id-ID');

        function createCard(p) {
            const stok = Number(p.stok || 0);
            const isLow = Number(p.min_stok || 0) > 0 && stok <= Number(p.min_stok);
            
            return `
                <div class="card">
                    <div class="name">${p.nama_produk || '-'}</div>
                    <div class="meta">BC: ${p.barcode || '-'} | SKU: ${p.sku || '-'}</div>
                    <div><span class="badge ${isLow ? 'low' : 'ok'}">Stok: ${stok} ${p.satuan || ''}</span></div>
                    <div class="price-row">
                        <div>Ecer: <b>${rp(p.harga_ecer)}</b></div>
                        <div>Grosir: <b>${rp(p.harga_grosir)}</b></div>
                        <div>Member: <b>${rp(p.harga_member)}</b></div>
                        <div>Reseller: <b>${rp(p.harga_reseller)}</b></div>
                    </div>
                </div>
            `;
        }

        function render() {
            const toShow = allData.slice(0, displayedCount);
            result.innerHTML = toShow.map(p => createCard(p)).join('');
            
            if (displayedCount < allData.length) {
                loader.style.display = 'block';
            } else {
                loader.style.display = 'none';
            }
        }

        async function loadData(query = '') {
            try {
                const r = await fetch(`../../api/produk_search.php?q=${encodeURIComponent(query)}`);
                const d = await r.json();
                allData = d?.ok ? d.data : [];
                displayedCount = increment; // Reset jumlah tampilan
                render();
            } catch (_) {
                result.innerHTML = '<div style="padding:20px; color:#64748b; font-size:13px">Gagal memuat data.</div>';
            }
        }

        // Infinite Scroll Logic
        container.addEventListener('scroll', () => {
            if (container.scrollTop + container.clientHeight >= container.scrollHeight - 20) {
                if (displayedCount < allData.length) {
                    displayedCount += increment;
                    render();
                }
            }
        });

        // Search Input Logic
        let t = null;
        q.addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(() => loadData(q.value.trim()), 250);
        });

        // Inisialisasi awal
        loadData('');
    </script>
</body>
</html>