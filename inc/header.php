<?php
if (!isset($no_header)) {
    require_once __DIR__ . '/url.php';
    // Selalu pakai URL absolut dari root aplikasi agar aman saat dibuka dari subfolder mana pun.
    $base = app_url('/public/admin/');
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap');

    :root {
        --primary-color: #6c5ce7;
        --text-color: #2d3436;
        --bg-header: #fff;
        --bg-dropdown: #fff;
        --transition: all 0.3s ease;
        --dropdown-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    /* Header */
    header {
        background: var(--bg-header);
        padding: 1rem 2rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    /* Nav utama */
    nav ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        gap: 20px;
    }

    nav ul li {
        position: relative;
    }

    nav ul li a {
        text-decoration: none;
        color: var(--text-color);
        font-weight: 500;
        font-family: 'Inter', sans-serif;
        padding: 8px 16px;
        border-radius: 6px;
        transition: var(--transition);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    nav ul li a:hover {
        background: rgba(108, 92, 231, 0.1);
        color: var(--primary-color);
    }

    /* Ikon panah */
    nav ul li a .arrow {
        font-size: 0.7rem;
        margin-left: 6px;
    }

    /* Dropdown */
    nav ul li ul.dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        background: var(--bg-dropdown);
        min-width: 220px;
        box-shadow: var(--dropdown-shadow);
        border-radius: 6px;
        padding: 10px 0;
        z-index: 1000;
    }

    nav ul li:hover > ul.dropdown {
        display: block;
    }

    nav ul li ul.dropdown li {
        width: 100%;
        position: relative;
    }

    nav ul li ul.dropdown li a {
        display: flex;
        justify-content: space-between;
        padding: 8px 20px;
        color: var(--text-color);
    }

    nav ul li ul.dropdown li a:hover {
        background: rgba(108, 92, 231, 0.05);
        color: var(--primary-color);
    }

    /* Multi-level dropdown */
    nav ul li ul.dropdown li ul.dropdown {
        top: 0;
        left: 100%;
        padding: 0;
    }

    /* Responsive mobile */
    @media (max-width: 768px) {
        nav ul {
            flex-direction: column;
            display: none;
            position: fixed;
            top: 0;
            left: -100%;
            width: 250px;
            height: 100%;
            background: var(--bg-header);
            padding-top: 60px;
            transition: var(--transition);
        }

        nav.active ul {
            left: 0;
            display: flex;
        }

        nav ul li ul.dropdown {
            position: relative;
            top: 0;
            left: 0;
            box-shadow: none;
            padding-left: 10px;
        }
    }

    .menu-toggle {
        display: none;
        font-size: 1.5rem;
        cursor: pointer;
    }

    @media (max-width: 768px) {
        .menu-toggle { display: block; }
    }
</style>

<header>
    <nav>
        <span class="menu-toggle">&#9776;</span>
        <ul>

            <!-- ================= INVENTORI ================= -->
            <li>
                <a href="#">Inventori <span class="arrow">&#9654;</span></a>
                <ul class="dropdown">

                    <!-- Master -->
                    <li><a href="<?=$base?>dashboard.php">Dashboard</a></li>
                    <li><a href="<?=$base?>produk/master_barang.php">Master Barang</a></li>
                    <li><a href="<?=$base?>kategori/">Kategori Produk</a></li>
                    <li><a href="<?=$base?>supplier/index.php">Supplier</a></li>

                    <!-- Customer -->
                    <li>
                        <a href="#">Customer <span class="arrow">&#9654;</span></a>
                        <ul class="dropdown">
                            <li><a href="<?=$base?>pelanggan/">Pelanggan</a></li>
                            <li><a href="<?=$base?>pelanggan_toko/">Pelanggan Toko</a></li>
                        </ul>
                    </li>

                    <!-- Stok -->
                    <li>
                        <a href="#">Stok Barang <span class="arrow">&#9654;</span></a>
                        <ul class="dropdown">
                            <li><a href="<?=$base?>stok_gudang/">Stok Gudang</a></li>
                            <li><a href="<?=$base?>stok_mutasi/">Mutasi Barang</a></li>
                            <li><a href="<?=$base?>stok_opname/">Stok Opname</a></li>
                        </ul>
                    </li>

                    <!-- Pembelian -->
                    <li>
                        <a href="#">Pembelian <span class="arrow">&#9654;</span></a>
                        <ul class="dropdown">
                            <li><a href="<?=$base?>purchase_order/">Order Saya</a></li>
                            <li><a href="<?=$base?>pembelian/">Pembelian Saya</a></li>
                            <li><a href="<?=$base?>hutang_supplier/">Hutang Ke Supplier</a></li>
                            <li><a href="<?=$base?>pembayaran_hutang/">Pembayaran Hutang</a></li>
                        </ul>
                    </li>

                    <!-- Penjualan -->
                    <li>
                        <a href="#">Penjualan <span class="arrow">&#9654;</span></a>
                        <ul class="dropdown">
                            <li><a href="<?=$base?>penjualan/">Transaksi Penjualan</a></li>
                            <li><a href="<?=$base?>retur/">Retur Penjualan</a></li>
                            <li><a href="<?=$base?>piutang/">Piutang Customer</a></li>
                            <li><a href="<?=$base?>piutang_pembayaran/">Pembayaran Piutang</a></li>
                        </ul>
                    </li>

                    <!-- Promo -->
                    <li>
                        <a href="#">Promo <span class="arrow">&#9654;</span></a>
                        <ul class="dropdown">
                            <li><a href="<?=$base?>promo/">Promo</a></li>
                            <li><a href="<?=$base?>promo_produk/">Promo Produk</a></li>
                        </ul>
                    </li>

                </ul>
            </li>


            <!-- ================= LAPORAN ================= -->
            <li>
                <a href="#">Laporan <span class="arrow">&#9654;</span></a>
                <ul class="dropdown">

                    <li><a href="<?=$base?>laporan_penjualan.php">Laporan Penjualan</a></li>
                    <li><a href="<?=$base?>laporan_pembelian.php">Laporan Pembelian</a></li>
                    <li><a href="<?=$base?>laporan_stok.php">Laporan Stok</a></li>
                    <li><a href="<?=$base?>laporan_piutang.php">Laporan Piutang</a></li>
                    <li><a href="<?=$base?>laporan_laba_rugi.php">Laporan Laba Rugi</a></li>
                    <li><a href="<?=$base?>laporan_produk.php">Laporan per Produk</a></li>
                    <li><a href="<?=$base?>laporan_customer.php">Laporan per Customer</a></li>
                    <li><a href="<?=$base?>laporan_kasir.php">Laporan per Kasir</a></li>
                    <li><a href="<?=$base?>laporan_stok_minimum.php">Laporan Stok Minimum</a></li>
                    <li><a href="<?=$base?>aging_piutang.php">Aging Piutang</a></li>
                    <li><a href="<?=$base?>laporan_ppn.php">Laporan PPN</a></li>

                </ul>
            </li>


            <!-- ================= UTILITY ================= -->
            <li>
                <a href="#">Utility <span class="arrow">&#9654;</span></a>
                <ul class="dropdown">

                    <!-- Keuangan Operasional -->
                    <li><a href="<?=$base?>kas_bank/">Kas & Bank</a></li>
                    <li><a href="<?=$base?>jurnal_umum/">Jurnal Umum</a></li>
                    <li><a href="<?=$base?>neraca/">Neraca</a></li>
                    <li><a href="<?=$base?>arus_kas/">Arus Kas</a></li>

                    <!-- Analytics -->
                    <li>
                        <a href="#">Analytics <span class="arrow">&#9654;</span></a>
                        <ul class="dropdown">
                            <li><a href="<?=$base?>insight_penjualan/">Insight Penjualan</a></li>
                            <li><a href="<?=$base?>performa_produk/">Performa Produk</a></li>
                            <li><a href="<?=$base?>performa_kategori/">Performa Kategori</a></li>
                            <li><a href="<?=$base?>customer_behavior/">Customer Behavior</a></li>
                        </ul>
                    </li>

                    <!-- Print Label Barcode -->
                    <li>
                        <a href="#">Print Label Barcode <span class="arrow">&#9654;</span></a>
                        <ul class="dropdown">
                            <li><a href="<?=$base?>print_label_barcode/barcode_barang.php">Barcode Barang</a></li>
                            <li>
                                <a href="#">Price Card Label <span class="arrow">&#9654;</span></a>
                                <ul class="dropdown">
                                    <li><a href="<?=$base?>print_label_barcode/price_card_label_single.php">Single Satuan</a></li>
                                    <li><a href="<?=$base?>print_label_barcode/price_card_label_multi.php">Multy Satuan</a></li>
                                </ul>
                            </li>
                            <li><a href="<?=$base?>print_label_barcode/price_card_folio.php">Price Card Kertas Folio</a></li>
                            <li><a href="<?=$base?>print_label_barcode/price_card_harga_naik.php">Print Harga Naik</a></li>
                        </ul>
                    </li>

                    <!-- System -->
                    <li><a href="<?=$base?>backup.php">Backup DB</a></li>
                    <li><a href="<?=$base?>restore.php">Restore DB</a></li>

                </ul>
            </li>


            <!-- ================= MAINTENANCE ================= -->
            <li>
                <a href="#">Maintenance <span class="arrow">&#9654;</span></a>
                <ul class="dropdown">

                    <li><a href="<?=$base?>settings.php">Profil Perusahaan</a></li>
                    <li><a href="<?=$base?>users.php">User Management</a></li>
                    <li><a href="<?=$base?>roles/">Role & Hak Akses</a></li>

                    <!-- Settings -->
                    <li>
                        <a href="#">Settings <span class="arrow">&#9654;</span></a>
                        <ul class="dropdown">
                            <li><a href="<?=$base?>format_nota/">Format Nota</a></li>
                            <li><a href="<?=$base?>pajak/">Pajak</a></li>
                            <li><a href="<?=$base?>printer/">Printer Thermal</a></li>
                            <li><a href="<?=$base?>metode_pembayaran/">Metode Pembayaran</a></li>
                            <li><a href="<?=$base?>integrasi_api/">Integrasi API</a></li>
                            <li><a href="<?=$base?>notifikasi/">Notifikasi WA & Email</a></li>
                        </ul>
                    </li>

                    <li><a href="<?=$base?>../logout.php">Keluar</a></li>

                </ul>
            </li>

        </ul>
    </nav>
</header>


<script>
// Toggle mobile menu
const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('nav');
toggle.addEventListener('click', () => {
    nav.classList.toggle('active');
});

// Navigasi tanpa menambah history (replace)
document.querySelectorAll('header nav a').forEach(a=>{
    a.addEventListener('click', e=>{
        const url = a.getAttribute('href');
        if(!url || url === '#') return;
        e.preventDefault();
        window.location.replace(url);
    });
});
</script>

<?php } ?>
