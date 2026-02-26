-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Waktu pembuatan: 22 Feb 2026 pada 09.30
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hyeepos`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `audit_log`
--

CREATE TABLE `audit_log` (
  `audit_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `pengguna_id` bigint(20) DEFAULT NULL,
  `aksi` enum('insert','update','delete','login','logout') NOT NULL,
  `tabel` varchar(100) NOT NULL,
  `record_id` bigint(20) DEFAULT NULL,
  `data_lama` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_lama`)),
  `data_baru` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_baru`)),
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` bigint(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `device`
--

CREATE TABLE `device` (
  `device_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `nama_device` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `tipe` enum('kasir','admin','gudang') DEFAULT 'kasir',
  `aktif` tinyint(1) DEFAULT 1,
  `terakhir_login` timestamp NULL DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `device`
--

INSERT INTO `device` (`device_id`, `toko_id`, `nama_device`, `ip_address`, `tipe`, `aktif`, `terakhir_login`, `dibuat_pada`, `created_at`, `updated_at`, `deleted_at`) VALUES
(32, 3, 'AlbaOne31', '::1', 'admin', 1, NULL, '2026-02-17 12:28:24', '2026-02-17 19:28:24', '2026-02-19 05:12:53', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `gudang`
--

CREATE TABLE `gudang` (
  `gudang_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `nama_gudang` varchar(100) NOT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `gudang`
--

INSERT INTO `gudang` (`gudang_id`, `toko_id`, `nama_gudang`, `aktif`, `deleted_at`) VALUES
(1, 3, 'Gudang Utama', 1, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `hutang_supplier`
--

CREATE TABLE `hutang_supplier` (
  `hutang_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL DEFAULT 0,
  `supplier_id` bigint(20) NOT NULL,
  `supplier` varchar(150) NOT NULL,
  `invoice` varchar(80) NOT NULL,
  `sisa` decimal(15,2) NOT NULL DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'tercatat',
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data untuk tabel `hutang_supplier`
--

INSERT INTO `hutang_supplier` (`hutang_id`, `toko_id`, `supplier_id`, `supplier`, `invoice`, `sisa`, `due_date`, `status`, `dibuat_pada`) VALUES
(1, 3, 2, 'PT SETIA ABADI', 'RC-20260220-4172', 0.00, '2026-03-12', 'lunas', '2026-02-20 01:30:23'),
(2, 3, 1, 'PT COBA COBA', 'PO-20260220-193', 0.00, '2026-03-22', 'lunas', '2026-02-20 02:04:01'),
(3, 3, 2, 'PT SETIA ABADI', 'PO-20260220-425', 671000.00, '2026-02-21', 'tercatat', '2026-02-20 12:19:50'),
(4, 3, 1, 'PT COBA COBA', 'PO-20260220-156', 743700.00, '2026-03-04', 'tercatat', '2026-02-20 21:34:24');

--
-- Trigger `hutang_supplier`
--
DELIMITER $$
CREATE TRIGGER `trg_hutang_status_auto` BEFORE UPDATE ON `hutang_supplier` FOR EACH ROW BEGIN
  IF NEW.sisa <= 0 THEN
    SET NEW.sisa = 0;
    SET NEW.status = 'lunas';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_hutang_status_auto_ins` BEFORE INSERT ON `hutang_supplier` FOR EACH ROW BEGIN
  IF NEW.sisa <= 0 THEN
    SET NEW.sisa = 0;
    SET NEW.status = 'lunas';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_produk`
--

CREATE TABLE `kategori_produk` (
  `kategori_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `induk_id` bigint(20) DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori_produk`
--

INSERT INTO `kategori_produk` (`kategori_id`, `toko_id`, `nama_kategori`, `induk_id`, `dibuat_pada`, `deleted_at`) VALUES
(1, 3, 'MINUMAN1', NULL, '2026-02-17 12:40:21', '2026-02-20 20:41:26'),
(2, 3, 'MAKANAN', NULL, '2026-02-17 12:50:49', NULL),
(6, 3, 'minuman', NULL, '2026-02-20 20:42:15', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `master_license`
--

CREATE TABLE `master_license` (
  `id` int(11) NOT NULL,
  `license_key` varchar(255) NOT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `expired_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `member_level`
--

CREATE TABLE `member_level` (
  `level_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `nama_level` varchar(50) NOT NULL,
  `minimal_poin` int(11) NOT NULL,
  `diskon_persen` decimal(5,2) DEFAULT 0.00,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pajak`
--

CREATE TABLE `pajak` (
  `pajak_id` bigint(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `persen` decimal(5,2) NOT NULL DEFAULT 0.00,
  `deskripsi` varchar(255) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `diupdate_pada` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pajak`
--

INSERT INTO `pajak` (`pajak_id`, `nama`, `persen`, `deskripsi`, `aktif`, `dibuat_pada`, `diupdate_pada`) VALUES
(1, 'PPN', 11.00, 'PAJAK', 1, '2026-02-19 12:34:18', '2026-02-19 12:34:18'),
(2, 'NON PAJAK', 0.00, 'TIDAK ADA PAJAK', 1, '2026-02-19 12:34:54', '2026-02-19 12:34:54');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggan`
--

CREATE TABLE `pelanggan` (
  `pelanggan_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `nama_pelanggan` varchar(150) NOT NULL,
  `telepon` varchar(50) DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pelanggan`
--

INSERT INTO `pelanggan` (`pelanggan_id`, `toko_id`, `nama_pelanggan`, `telepon`, `dibuat_pada`, `deleted_at`) VALUES
(1, 3, 'mahi', '085756541254', '2026-02-17 15:20:36', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggan_toko`
--

CREATE TABLE `pelanggan_toko` (
  `id` bigint(20) NOT NULL,
  `pelanggan_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `level_id` bigint(20) DEFAULT NULL,
  `poin` int(11) DEFAULT 0,
  `limit_kredit` decimal(15,2) DEFAULT 0.00,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `pembayaran_id` bigint(20) NOT NULL,
  `penjualan_id` bigint(20) NOT NULL,
  `metode` enum('cash','transfer','qris','hutang') NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `dibayar_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Trigger `pembayaran`
--
DELIMITER $$
CREATE TRIGGER `trg_pembayaran_hutang_wajib_pelanggan` BEFORE INSERT ON `pembayaran` FOR EACH ROW BEGIN
    IF NEW.metode = 'hutang' THEN
        IF (
            SELECT pelanggan_id
            FROM penjualan
            WHERE penjualan_id = NEW.penjualan_id
        ) IS NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Pembayaran hutang wajib memiliki pelanggan';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran_hutang`
--

CREATE TABLE `pembayaran_hutang` (
  `bayar_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL DEFAULT 0,
  `supplier_id` bigint(20) NOT NULL,
  `hutang_id` bigint(20) DEFAULT NULL,
  `supplier` varchar(150) NOT NULL,
  `referensi` varchar(120) DEFAULT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `kelebihan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) DEFAULT NULL,
  `dibayar_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembayaran_hutang`
--

INSERT INTO `pembayaran_hutang` (`bayar_id`, `toko_id`, `supplier_id`, `hutang_id`, `supplier`, `referensi`, `jumlah`, `kelebihan`, `catatan`, `dibayar_pada`) VALUES
(1, 3, 1, 1, 'PT COBA COBA', '', 25000.00, 0.00, 'dikit dikit', '2026-02-20 01:31:47'),
(2, 3, 2, 1, 'PT SETIA ABADI', '213', 45000.00, 0.00, 'ada', '2026-02-20 01:38:23'),
(3, 3, 2, 1, 'PT SETIA ABADI', '2131', 700000.00, 0.00, 'adaa', '2026-02-20 01:40:49'),
(4, 3, 1, 2, 'PT COBA COBA', 'PO-20260220-193', 650000.00, 48930.00, 'dikit dikit', '2026-02-20 02:06:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembelian`
--

CREATE TABLE `pembelian` (
  `pembelian_id` bigint(20) NOT NULL,
  `supplier_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `gudang_id` bigint(20) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `tanggal` date DEFAULT NULL,
  `jatuh_tempo` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pajak` decimal(15,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ongkir` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) DEFAULT NULL,
  `tipe_faktur` enum('cash','tempo') NOT NULL DEFAULT 'cash',
  `salesman` varchar(100) DEFAULT NULL,
  `tempo_hari` int(11) DEFAULT NULL,
  `jenis_ppn` varchar(20) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `nomor_faktur` varchar(80) NOT NULL,
  `po_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembelian`
--

INSERT INTO `pembelian` (`pembelian_id`, `supplier_id`, `toko_id`, `gudang_id`, `total`, `dibuat_pada`, `tanggal`, `jatuh_tempo`, `subtotal`, `pajak`, `diskon`, `ongkir`, `catatan`, `tipe_faktur`, `salesman`, `tempo_hari`, `jenis_ppn`, `status`, `nomor_faktur`, `po_id`) VALUES
(6, 1, 3, 1, 71000.00, '2026-02-20 00:41:23', '2026-02-20', NULL, 71000.00, 0.00, 0.00, 0.00, '', 'cash', NULL, NULL, NULL, 'posted', 'RC-20260220-9287', NULL),
(7, 2, 3, 1, 670000.00, '2026-02-20 01:30:23', '2026-02-20', '2026-03-12', 670000.00, 0.00, 0.00, 0.00, '', 'tempo', NULL, 20, NULL, 'posted', 'RC-20260220-4172', 13),
(8, 1, 3, 1, 601070.00, '2026-02-20 02:04:01', '2026-02-20', '2026-03-22', 601070.00, 0.00, 0.00, 0.00, '', 'tempo', NULL, 30, NULL, 'posted', 'PO-20260220-193', 14),
(9, 2, 3, 1, 671000.00, '2026-02-20 12:19:50', '2026-02-20', '2026-02-21', 671000.00, 0.00, 0.00, 0.00, '', 'tempo', NULL, 1, NULL, 'posted', 'PO-20260220-425', 15),
(10, 1, 3, 1, 77.70, '2026-02-20 13:42:24', '2026-02-20', NULL, 70.00, 7.70, 0.00, 0.00, '', 'cash', NULL, 0, NULL, 'posted', 'PO-20260220-091', 23),
(11, 1, 3, 1, 743700.00, '2026-02-20 21:34:24', '2026-02-20', '2026-03-04', 670000.00, 73700.00, 0.00, 0.00, 'a', 'tempo', NULL, 12, NULL, 'posted', 'PO-20260220-156', 16),
(12, 2, 3, 1, 1110.00, '2026-02-20 21:37:04', '2026-02-20', NULL, 1000.00, 110.00, 0.00, 0.00, '', 'cash', NULL, 0, NULL, 'posted', 'PO-20260220-518', 17);

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembelian_detail`
--

CREATE TABLE `pembelian_detail` (
  `detail_id` bigint(20) NOT NULL,
  `pembelian_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  `qty` int(11) NOT NULL,
  `harga_beli` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `nama_barang` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembelian_detail`
--

INSERT INTO `pembelian_detail` (`detail_id`, `pembelian_id`, `produk_id`, `qty`, `harga_beli`, `subtotal`, `nama_barang`) VALUES
(3, 6, 4, 1, 70000.00, 70000.00, 'SGM 3PLUS MADU 800GR + 8g'),
(4, 6, 3, 1, 1000.00, 1000.00, 'SOFTEX 2PH'),
(5, 6, 4, 0, 0.00, 0.00, 'SGM 3PLUS MADU 800GR + 8g'),
(6, 6, 3, 0, 0.00, 0.00, 'SOFTEX 2PH'),
(7, 7, 2, 1, 600000.00, 600000.00, 'jamu 1'),
(8, 7, 4, 1, 70000.00, 70000.00, 'SGM 3PLUS MADU 800GR + 8g'),
(9, 7, 2, 0, 0.00, 0.00, 'jamu 1'),
(10, 7, 4, 0, 0.00, 0.00, 'SGM 3PLUS MADU 800GR + 8g'),
(11, 8, 2, 1, 600000.00, 600000.00, 'jamu 1'),
(12, 8, 1, 1, 70.00, 70.00, 'SGM 3PLUS MADU 800GR'),
(13, 8, 3, 1, 1000.00, 1000.00, 'SOFTEX 2PH'),
(14, 9, 2, 1, 600000.00, 600000.00, 'jamu 1'),
(15, 9, 4, 1, 70000.00, 70000.00, 'SGM 3PLUS MADU 800GR + 8g'),
(16, 9, 3, 1, 1000.00, 1000.00, 'SOFTEX 2PH'),
(17, 10, 1, 1, 70.00, 70.00, 'SGM 3PLUS MADU 800GR'),
(18, 11, 4, 1, 70000.00, 70000.00, 'SGM 3PLUS MADU 800GR + 8g'),
(19, 11, 2, 1, 600000.00, 600000.00, 'jamu 1'),
(20, 12, 3, 1, 1000.00, 1000.00, 'SOFTEX 2PH');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengguna`
--

CREATE TABLE `pengguna` (
  `pengguna_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `peran` enum('owner','manager','kasir','gudang') NOT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengguna`
--

INSERT INTO `pengguna` (`pengguna_id`, `toko_id`, `nama`, `email`, `password`, `peran`, `aktif`, `dibuat_pada`, `deleted_at`) VALUES
(5, 3, 'muhyi31', 'admin_3@local', '$2y$10$j699qVY9C8tyTYXFrAfYheXAjNX13Zi4KHDKJWAHp9JgdEPUW9s5i', 'owner', 1, '2026-02-17 12:28:04', NULL),
(6, 3, 'muhyi31', 'www.sergiomuhye@gmail.com', '$2y$10$/SzjxWhOvd6okx/gMPkC0.kxLrWB/QmlCSSco0qDYPz618yq/kgEm', 'owner', 1, '2026-02-17 12:28:24', NULL),
(7, 3, 'admin asli', 'hyeecode@gmail.com', '$2y$10$fnJQErsZOVhNd5TcBOr5Huz.t2zTtSdnT76sWWepZoRwPKxLkPwuO', 'owner', 1, '2026-02-18 21:31:09', NULL),
(8, 3, 'Kasir1', 'kasir1@gmail.com', '$2y$10$CCHqwQP3vxlG6h40rngDEe1WokHB02dE82ufBt5rB1B9lqAc6xcki', 'kasir', 1, '2026-02-18 22:31:42', NULL),
(9, 3, 'manager', 'manager@gmail.com', '$2y$10$BrBX2NsNJG0C3yMDn2opteLjOI5ZeMfZ6rmbNB0UmhCNQqCbNyYSG', 'manager', 1, '2026-02-18 22:34:45', NULL),
(10, 3, 'Mas gudang', 'gudang@gmail.com', '$2y$10$hJe3WoJTB7KivsrF5qU5yOX13ir2fQegjx8.tEofnZWOFzlcQAjW2', 'gudang', 1, '2026-02-18 22:35:59', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `penjualan`
--

CREATE TABLE `penjualan` (
  `penjualan_id` bigint(20) NOT NULL,
  `nomor_invoice` varchar(100) NOT NULL,
  `kasir_id` bigint(20) NOT NULL,
  `pelanggan_id` bigint(20) DEFAULT NULL,
  `toko_id` bigint(20) NOT NULL,
  `gudang_id` bigint(20) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `diskon` decimal(15,2) DEFAULT 0.00,
  `total_akhir` decimal(15,2) NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `penjualan_detail`
--

CREATE TABLE `penjualan_detail` (
  `detail_id` bigint(20) NOT NULL,
  `penjualan_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  `qty` int(11) NOT NULL,
  `tipe_harga` enum('ecer','grosir','member') NOT NULL,
  `harga_jual` decimal(15,2) NOT NULL,
  `harga_modal_snapshot` decimal(15,2) NOT NULL,
  `diskon` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `piutang`
--

CREATE TABLE `piutang` (
  `piutang_id` bigint(20) NOT NULL,
  `pelanggan_id` bigint(20) NOT NULL,
  `penjualan_id` bigint(20) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  `sisa` decimal(15,2) NOT NULL,
  `status` enum('lunas','belum') DEFAULT 'belum',
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Trigger `piutang`
--
DELIMITER $$
CREATE TRIGGER `trg_piutang_status_auto` BEFORE UPDATE ON `piutang` FOR EACH ROW BEGIN
    IF NEW.sisa <= 0 THEN
        SET NEW.status = 'lunas';
    ELSE
        SET NEW.status = 'belum';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `piutang_pembayaran`
--

CREATE TABLE `piutang_pembayaran` (
  `pembayaran_id` bigint(20) NOT NULL,
  `piutang_id` bigint(20) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `metode` enum('cash','transfer','qris') NOT NULL,
  `dibayar_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `poin_member`
--

CREATE TABLE `poin_member` (
  `poin_id` bigint(20) NOT NULL,
  `pelanggan_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `sumber` enum('penjualan','promo','manual') NOT NULL,
  `referensi_id` bigint(20) DEFAULT NULL,
  `poin` int(11) NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Trigger `poin_member`
--
DELIMITER $$
CREATE TRIGGER `trg_poin_penjualan_valid` BEFORE INSERT ON `poin_member` FOR EACH ROW BEGIN
    IF NEW.sumber = 'penjualan' THEN
        IF NOT EXISTS (
            SELECT 1 FROM penjualan
            WHERE penjualan_id = NEW.referensi_id
        ) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Referensi penjualan tidak valid untuk poin member';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `printer_setting`
--

CREATE TABLE `printer_setting` (
  `id` bigint(20) NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis` enum('network','usb','bluetooth') NOT NULL,
  `alamat` varchar(255) NOT NULL,
  `lebar` enum('58','80') NOT NULL DEFAULT '80',
  `driver` enum('escpos','star') NOT NULL DEFAULT 'escpos',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `printer_setting`
--

INSERT INTO `printer_setting` (`id`, `device_id`, `user_id`, `nama`, `jenis`, `alamat`, `lebar`, `driver`, `is_default`, `created_at`) VALUES
(1, '32', NULL, 'EPSON L1110 Series', 'usb', 'USB001', '80', 'escpos', 1, '2026-02-19 10:55:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `produk_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `kategori_id` bigint(20) NOT NULL,
  `supplier_id` bigint(20) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `sku` varchar(100) NOT NULL,
  `merk` varchar(100) DEFAULT NULL,
  `nama_produk` varchar(200) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `harga_modal` decimal(15,2) NOT NULL,
  `satuan` varchar(50) NOT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `min_stok` int(11) DEFAULT 0,
  `pajak_persen` decimal(5,2) DEFAULT 0.00,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk`
--

INSERT INTO `produk` (`produk_id`, `toko_id`, `kategori_id`, `supplier_id`, `barcode`, `sku`, `merk`, `nama_produk`, `foto`, `harga_modal`, `satuan`, `aktif`, `min_stok`, `pajak_persen`, `dibuat_pada`, `deleted_at`) VALUES
(1, 3, 1, 2, '321', 'SGM', '', 'SGM 3PLUS MADU 800GR', 'p_6996bd7ae9fce.webp', 70.00, 'PCS', 1, 10, 11.00, '2026-02-17 12:54:56', NULL),
(2, 3, 1, 1, '123', 'j1', '', 'jamu 1', 'p_6996bc9b00ba5.jpeg', 600000.00, 'karton', 1, 3, 11.00, '2026-02-19 07:01:14', NULL),
(3, 3, 1, 1, '098', 'SF', '', 'SOFTEX 2PH', 'p_699719b85142f.webp', 1000.00, 'PCS', 1, 2000, 0.00, '2026-02-19 14:09:42', NULL),
(4, 3, 2, 2, '14354564', '325', '', 'SGM 3PLUS MADU 800GR + 8g', 'p_69979fccb5442.webp', 70000.00, 'PCS', 1, 3, 11.00, '2026-02-19 23:42:04', NULL),
(7, 3, 1, 2, '143545643', '325a', '', 'SURYA', 'p_6998c072e2bf5.webp', 70000.00, 'PCS', 1, 10, 11.00, '2026-02-20 20:13:38', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk_harga`
--

CREATE TABLE `produk_harga` (
  `id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  `tipe` enum('ecer','grosir','member') NOT NULL DEFAULT 'ecer',
  `harga_jual` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk_harga`
--

INSERT INTO `produk_harga` (`id`, `produk_id`, `tipe`, `harga_jual`) VALUES
(1, 2, 'ecer', 60000.00),
(2, 2, 'grosir', 500000.00),
(3, 1, 'ecer', 30000.00),
(9, 3, 'ecer', 2000.00),
(14, 4, 'ecer', 200000.00),
(15, 7, 'ecer', 3000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `promo`
--

CREATE TABLE `promo` (
  `promo_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `nama_promo` varchar(100) NOT NULL,
  `tipe` enum('persen','nominal','gratis') NOT NULL,
  `nilai` decimal(15,2) NOT NULL,
  `minimal_belanja` decimal(15,2) DEFAULT 0.00,
  `berlaku_dari` datetime NOT NULL,
  `berlaku_sampai` datetime NOT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `promo_produk`
--

CREATE TABLE `promo_produk` (
  `id` bigint(20) NOT NULL,
  `promo_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `purchase_order`
--

CREATE TABLE `purchase_order` (
  `po_id` bigint(20) NOT NULL,
  `supplier` varchar(150) NOT NULL,
  `nomor` varchar(60) NOT NULL,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `supplier_id` bigint(20) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `jatuh_tempo` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pajak` decimal(15,2) NOT NULL DEFAULT 0.00,
  `diskon` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ongkir` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` varchar(255) DEFAULT NULL,
  `tipe_faktur` enum('cash','tempo') NOT NULL DEFAULT 'cash',
  `salesman` varchar(100) DEFAULT NULL,
  `tempo_hari` int(11) DEFAULT NULL,
  `jenis_ppn` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `purchase_order`
--

INSERT INTO `purchase_order` (`po_id`, `supplier`, `nomor`, `total`, `status`, `dibuat_pada`, `supplier_id`, `tanggal`, `jatuh_tempo`, `subtotal`, `pajak`, `diskon`, `ongkir`, `catatan`, `tipe_faktur`, `salesman`, `tempo_hari`, `jenis_ppn`) VALUES
(12, '', 'PO-20260220-340', 79809.00, 'received', '2026-02-20 00:40:52', 1, '2026-02-20', '2026-03-02', 71000.00, 7810.00, 1.00, 1000.00, 'ada', 'tempo', 'NIAN', 10, 'PPN 11%'),
(13, '', 'PO-20260220-849', 748699.00, 'received', '2026-02-20 01:17:57', 2, '2026-02-20', '2026-03-12', 670000.00, 73700.00, 1.00, 5000.00, 'ada', 'tempo', 'NIAN', 20, 'PPN 11%'),
(14, '', 'PO-20260220-193', 667286.70, 'received', '2026-02-20 01:54:43', 1, '2026-02-20', '2026-03-22', 601070.00, 66117.70, 1.00, 100.00, 'ADA', 'tempo', 'NIAN', 30, 'PPN 11%'),
(15, '', 'PO-20260220-425', 745809.00, 'received', '2026-02-20 01:58:05', 2, '2026-02-20', '2026-02-21', 671000.00, 73810.00, 1.00, 1000.00, 'ADA', 'tempo', 'NIAN', 1, 'PPN 11%'),
(16, '', 'PO-20260220-156', 755695.00, 'received', '2026-02-20 12:26:57', 1, '2026-02-20', '2026-03-04', 670000.00, 73700.00, 5.00, 12000.00, 'ADS', 'tempo', 'AS', 12, 'PPN 11%'),
(17, '', 'PO-20260220-518', 1055.00, 'received', '2026-02-20 12:34:52', 2, '2026-02-20', NULL, 1000.00, 110.00, 55.00, 0.00, '', 'cash', 'NIAN', 0, 'PPN 11%'),
(23, '', 'PO-20260220-091', 74.70, 'received', '2026-02-20 13:16:56', 1, '2026-02-20', NULL, 70.00, 7.70, 3.00, 0.00, '', 'cash', 'AS', 0, 'PPN 11%'),
(24, '', 'PO-20260220-751', 79699.00, 'approved', '2026-02-20 21:30:26', 1, '2026-02-20', '2026-03-04', 70000.00, 7700.00, 1.00, 2000.00, 'd', 'tempo', '', 12, 'PPN 11%');

-- --------------------------------------------------------

--
-- Struktur dari tabel `purchase_order_detail`
--

CREATE TABLE `purchase_order_detail` (
  `detail_id` bigint(20) NOT NULL,
  `po_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) DEFAULT NULL,
  `nama_barang` varchar(200) NOT NULL,
  `qty` decimal(15,2) NOT NULL DEFAULT 0.00,
  `harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `satuan` varchar(50) DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `purchase_order_detail`
--

INSERT INTO `purchase_order_detail` (`detail_id`, `po_id`, `produk_id`, `nama_barang`, `qty`, `harga`, `subtotal`, `satuan`, `dibuat_pada`) VALUES
(7, 12, 4, 'SGM 3PLUS MADU 800GR + 8g', 1.00, 70000.00, 70000.00, NULL, '2026-02-20 00:40:52'),
(8, 12, 3, 'SOFTEX 2PH', 1.00, 1000.00, 1000.00, NULL, '2026-02-20 00:40:52'),
(9, 12, 4, 'SGM 3PLUS MADU 800GR + 8g', 0.00, 0.00, 0.00, NULL, '2026-02-20 00:40:52'),
(10, 12, 3, 'SOFTEX 2PH', 0.00, 0.00, 0.00, NULL, '2026-02-20 00:40:52'),
(11, 13, 2, 'jamu 1', 1.00, 600000.00, 600000.00, NULL, '2026-02-20 01:17:57'),
(12, 13, 4, 'SGM 3PLUS MADU 800GR + 8g', 1.00, 70000.00, 70000.00, NULL, '2026-02-20 01:17:57'),
(13, 13, 2, 'jamu 1', 0.00, 0.00, 0.00, NULL, '2026-02-20 01:17:57'),
(14, 13, 4, 'SGM 3PLUS MADU 800GR + 8g', 0.00, 0.00, 0.00, NULL, '2026-02-20 01:17:57'),
(15, 14, 2, 'jamu 1', 1.00, 600000.00, 600000.00, NULL, '2026-02-20 01:54:43'),
(16, 14, 1, 'SGM 3PLUS MADU 800GR', 1.00, 70.00, 70.00, NULL, '2026-02-20 01:54:43'),
(17, 14, 3, 'SOFTEX 2PH', 1.00, 1000.00, 1000.00, NULL, '2026-02-20 01:54:43'),
(18, 14, 2, 'jamu 1', 0.00, 0.00, 0.00, NULL, '2026-02-20 01:54:43'),
(19, 14, 1, 'SGM 3PLUS MADU 800GR', 0.00, 0.00, 0.00, NULL, '2026-02-20 01:54:43'),
(20, 14, 3, 'SOFTEX 2PH', 0.00, 0.00, 0.00, NULL, '2026-02-20 01:54:43'),
(21, 15, 2, 'jamu 1', 1.00, 600000.00, 600000.00, NULL, '2026-02-20 01:58:05'),
(22, 15, 4, 'SGM 3PLUS MADU 800GR + 8g', 1.00, 70000.00, 70000.00, NULL, '2026-02-20 01:58:05'),
(23, 15, 3, 'SOFTEX 2PH', 1.00, 1000.00, 1000.00, NULL, '2026-02-20 01:58:05'),
(24, 16, 4, 'SGM 3PLUS MADU 800GR + 8g', 1.00, 70000.00, 70000.00, NULL, '2026-02-20 12:26:57'),
(25, 16, 2, 'jamu 1', 1.00, 600000.00, 600000.00, NULL, '2026-02-20 12:26:57'),
(26, 17, 3, 'SOFTEX 2PH', 1.00, 1000.00, 1000.00, NULL, '2026-02-20 12:34:52'),
(27, 23, 1, 'SGM 3PLUS MADU 800GR', 1.00, 70.00, 70.00, 'PCS', '2026-02-20 13:16:56'),
(28, 24, 4, 'SGM 3PLUS MADU 800GR + 8g', 1.00, 70000.00, 70000.00, 'PCS', '2026-02-20 21:30:26');

-- --------------------------------------------------------

--
-- Struktur dari tabel `retur`
--

CREATE TABLE `retur` (
  `retur_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `jenis` enum('penjualan','pembelian') NOT NULL,
  `referensi_id` bigint(20) NOT NULL,
  `alasan` text DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `retur_detail`
--

CREATE TABLE `retur_detail` (
  `detail_id` bigint(20) NOT NULL,
  `retur_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  `qty` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `satuan`
--

CREATE TABLE `satuan` (
  `satuan_id` bigint(20) NOT NULL,
  `nama` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `stok_gudang`
--

CREATE TABLE `stok_gudang` (
  `id` bigint(20) NOT NULL,
  `gudang_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `min_stok` int(11) DEFAULT 0,
  `toko_id` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `stok_mutasi`
--

CREATE TABLE `stok_mutasi` (
  `mutasi_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `gudang_id` bigint(20) NOT NULL,
  `produk_id` bigint(20) NOT NULL,
  `qty` int(11) NOT NULL,
  `stok_sebelum` int(11) NOT NULL,
  `stok_sesudah` int(11) NOT NULL,
  `tipe` enum('masuk','keluar') NOT NULL,
  `referensi` varchar(100) DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `supplier`
--

CREATE TABLE `supplier` (
  `supplier_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL DEFAULT 0,
  `nama_supplier` varchar(150) NOT NULL,
  `telepon` varchar(50) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `supplier`
--

INSERT INTO `supplier` (`supplier_id`, `toko_id`, `nama_supplier`, `telepon`, `alamat`, `dibuat_pada`, `deleted_at`) VALUES
(1, 3, 'PT COBA COBA', '0857565412543', 'AA', '2026-02-19 14:16:16', NULL),
(2, 3, 'PT SETIA ABADI', '085745445119', 'demak', '2026-02-19 23:01:31', NULL),
(3, 0, 'cv mitra alam', '08574544511244', 'lpg', '2026-02-20 20:48:48', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `toko`
--

CREATE TABLE `toko` (
  `toko_id` bigint(20) NOT NULL,
  `nama_toko` varchar(100) NOT NULL,
  `license_key` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `db_host` varchar(255) DEFAULT NULL,
  `db_name` varchar(100) DEFAULT NULL,
  `db_user` varchar(50) DEFAULT NULL,
  `db_pass` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `toko`
--

INSERT INTO `toko` (`toko_id`, `nama_toko`, `license_key`, `alamat`, `aktif`, `dibuat_pada`, `deleted_at`, `db_host`, `db_name`, `db_user`, `db_pass`) VALUES
(3, 'AlbaOne31', '', 'lampung', 1, '2026-02-17 12:28:04', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `toko_config`
--

CREATE TABLE `toko_config` (
  `config_id` bigint(20) NOT NULL,
  `toko_id` bigint(20) NOT NULL,
  `nama_konfigurasi` varchar(100) NOT NULL,
  `nilai` varchar(255) NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `toko_config`
--

INSERT INTO `toko_config` (`config_id`, `toko_id`, `nama_konfigurasi`, `nilai`, `dibuat_pada`) VALUES
(35, 3, 'ppn_persen', '11.00', '2026-02-19 06:52:36'),
(36, 3, 'ppn_mode', 'exclude', '2026-02-19 06:52:36'),
(37, 3, 'timezone', 'Asia/Jakarta', '2026-02-20 14:16:34'),
(38, 3, 'language', 'id', '2026-02-20 14:16:34'),
(39, 3, 'currency', 'IDR', '2026-02-20 14:16:34'),
(40, 3, 'number_format', '1.234,56', '2026-02-20 14:16:34'),
(41, 3, 'date_format', 'm/d/Y', '2026-02-20 14:16:34'),
(42, 3, 'phone', '', '2026-02-20 14:16:34'),
(43, 3, 'email_cs', 'albaone01@gmail.com', '2026-02-20 14:16:34'),
(44, 3, 'npwp', '321123', '2026-02-20 14:16:34'),
(45, 3, 'kota', 'Bintoro', '2026-02-20 14:16:34'),
(46, 3, 'provinsi', 'Jawa Tengah', '2026-02-20 14:16:34'),
(47, 3, 'kode_pos', '59511', '2026-02-20 14:16:34'),
(48, 3, 'logo_path', 'assets/uploads/logo_toko_3.png', '2026-02-20 14:16:34');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `fk_audit_toko` (`toko_id`),
  ADD KEY `fk_audit_pengguna` (`pengguna_id`);

--
-- Indeks untuk tabel `device`
--
ALTER TABLE `device`
  ADD PRIMARY KEY (`device_id`),
  ADD UNIQUE KEY `uq_device_ip` (`toko_id`,`ip_address`);

--
-- Indeks untuk tabel `gudang`
--
ALTER TABLE `gudang`
  ADD PRIMARY KEY (`gudang_id`),
  ADD UNIQUE KEY `uq_gudang_toko` (`toko_id`,`nama_gudang`);

--
-- Indeks untuk tabel `hutang_supplier`
--
ALTER TABLE `hutang_supplier`
  ADD PRIMARY KEY (`hutang_id`),
  ADD KEY `idx_toko` (`toko_id`),
  ADD KEY `idx_supplier` (`supplier_id`);

--
-- Indeks untuk tabel `kategori_produk`
--
ALTER TABLE `kategori_produk`
  ADD PRIMARY KEY (`kategori_id`),
  ADD UNIQUE KEY `uq_kategori_toko` (`toko_id`,`nama_kategori`),
  ADD KEY `fk_kategori_induk` (`induk_id`);

--
-- Indeks untuk tabel `master_license`
--
ALTER TABLE `master_license`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_key` (`license_key`);

--
-- Indeks untuk tabel `member_level`
--
ALTER TABLE `member_level`
  ADD PRIMARY KEY (`level_id`,`toko_id`),
  ADD UNIQUE KEY `uq_level_toko` (`toko_id`,`nama_level`);

--
-- Indeks untuk tabel `pajak`
--
ALTER TABLE `pajak`
  ADD PRIMARY KEY (`pajak_id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indeks untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`pelanggan_id`),
  ADD KEY `fk_pelanggan_toko` (`toko_id`),
  ADD KEY `idx_pelanggan_nama` (`nama_pelanggan`);

--
-- Indeks untuk tabel `pelanggan_toko`
--
ALTER TABLE `pelanggan_toko`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pelanggan_toko` (`pelanggan_id`,`toko_id`),
  ADD KEY `fk_pt_toko` (`toko_id`),
  ADD KEY `fk_pt_level` (`level_id`,`toko_id`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`pembayaran_id`),
  ADD KEY `fk_pembayaran_penjualan` (`penjualan_id`);

--
-- Indeks untuk tabel `pembayaran_hutang`
--
ALTER TABLE `pembayaran_hutang`
  ADD PRIMARY KEY (`bayar_id`),
  ADD KEY `idx_toko` (`toko_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_hutang` (`hutang_id`);

--
-- Indeks untuk tabel `pembelian`
--
ALTER TABLE `pembelian`
  ADD PRIMARY KEY (`pembelian_id`),
  ADD KEY `fk_pembelian_supplier` (`supplier_id`),
  ADD KEY `fk_pembelian_toko` (`toko_id`),
  ADD KEY `fk_pembelian_gudang` (`gudang_id`);

--
-- Indeks untuk tabel `pembelian_detail`
--
ALTER TABLE `pembelian_detail`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `fk_detail_pembelian` (`pembelian_id`),
  ADD KEY `fk_detail_produk_beli` (`produk_id`);

--
-- Indeks untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`pengguna_id`),
  ADD UNIQUE KEY `uq_email_toko` (`toko_id`,`email`);

--
-- Indeks untuk tabel `penjualan`
--
ALTER TABLE `penjualan`
  ADD PRIMARY KEY (`penjualan_id`),
  ADD UNIQUE KEY `uq_invoice_toko` (`toko_id`,`nomor_invoice`),
  ADD KEY `fk_penjualan_kasir` (`kasir_id`),
  ADD KEY `fk_penjualan_pelanggan` (`pelanggan_id`),
  ADD KEY `fk_penjualan_gudang` (`gudang_id`),
  ADD KEY `idx_penjualan_tanggal` (`toko_id`,`dibuat_pada`);

--
-- Indeks untuk tabel `penjualan_detail`
--
ALTER TABLE `penjualan_detail`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `fk_detail_penjualan` (`penjualan_id`),
  ADD KEY `fk_detail_produk` (`produk_id`);

--
-- Indeks untuk tabel `piutang`
--
ALTER TABLE `piutang`
  ADD PRIMARY KEY (`piutang_id`),
  ADD UNIQUE KEY `uq_piutang_penjualan` (`penjualan_id`),
  ADD KEY `fk_piutang_pelanggan` (`pelanggan_id`),
  ADD KEY `idx_piutang_status` (`status`);

--
-- Indeks untuk tabel `piutang_pembayaran`
--
ALTER TABLE `piutang_pembayaran`
  ADD PRIMARY KEY (`pembayaran_id`),
  ADD KEY `fk_pembayaran_piutang` (`piutang_id`);

--
-- Indeks untuk tabel `poin_member`
--
ALTER TABLE `poin_member`
  ADD PRIMARY KEY (`poin_id`),
  ADD KEY `idx_poin_pelanggan` (`pelanggan_id`,`toko_id`),
  ADD KEY `fk_poin_toko` (`toko_id`);

--
-- Indeks untuk tabel `printer_setting`
--
ALTER TABLE `printer_setting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_device_nama` (`device_id`,`nama`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`produk_id`),
  ADD UNIQUE KEY `uq_sku_toko` (`toko_id`,`sku`),
  ADD UNIQUE KEY `uq_barcode_toko` (`toko_id`,`barcode`),
  ADD KEY `fk_produk_kategori` (`kategori_id`),
  ADD KEY `idx_produk_aktif` (`toko_id`,`aktif`,`deleted_at`),
  ADD KEY `idx_produk_supplier` (`supplier_id`);

--
-- Indeks untuk tabel `produk_harga`
--
ALTER TABLE `produk_harga`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_produk_tipe` (`produk_id`,`tipe`);

--
-- Indeks untuk tabel `promo`
--
ALTER TABLE `promo`
  ADD PRIMARY KEY (`promo_id`),
  ADD KEY `fk_promo_toko` (`toko_id`);

--
-- Indeks untuk tabel `promo_produk`
--
ALTER TABLE `promo_produk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pp_promo` (`promo_id`),
  ADD KEY `fk_pp_produk` (`produk_id`);

--
-- Indeks untuk tabel `purchase_order`
--
ALTER TABLE `purchase_order`
  ADD PRIMARY KEY (`po_id`),
  ADD UNIQUE KEY `nomor` (`nomor`);

--
-- Indeks untuk tabel `purchase_order_detail`
--
ALTER TABLE `purchase_order_detail`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `idx_po` (`po_id`);

--
-- Indeks untuk tabel `retur`
--
ALTER TABLE `retur`
  ADD PRIMARY KEY (`retur_id`),
  ADD KEY `fk_retur_toko` (`toko_id`);

--
-- Indeks untuk tabel `retur_detail`
--
ALTER TABLE `retur_detail`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `fk_retur_detail` (`retur_id`),
  ADD KEY `fk_retur_produk` (`produk_id`);

--
-- Indeks untuk tabel `satuan`
--
ALTER TABLE `satuan`
  ADD PRIMARY KEY (`satuan_id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indeks untuk tabel `stok_gudang`
--
ALTER TABLE `stok_gudang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stok` (`gudang_id`,`produk_id`),
  ADD KEY `fk_stok_produk` (`produk_id`);

--
-- Indeks untuk tabel `stok_mutasi`
--
ALTER TABLE `stok_mutasi`
  ADD PRIMARY KEY (`mutasi_id`),
  ADD KEY `fk_mutasi_gudang` (`gudang_id`),
  ADD KEY `fk_mutasi_produk` (`produk_id`),
  ADD KEY `fk_mutasi_toko` (`toko_id`);

--
-- Indeks untuk tabel `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplier_id`),
  ADD KEY `idx_supplier_toko` (`toko_id`);

--
-- Indeks untuk tabel `toko`
--
ALTER TABLE `toko`
  ADD PRIMARY KEY (`toko_id`);

--
-- Indeks untuk tabel `toko_config`
--
ALTER TABLE `toko_config`
  ADD PRIMARY KEY (`config_id`),
  ADD KEY `fk_config_toko` (`toko_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `audit_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `device`
--
ALTER TABLE `device`
  MODIFY `device_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT untuk tabel `gudang`
--
ALTER TABLE `gudang`
  MODIFY `gudang_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000;

--
-- AUTO_INCREMENT untuk tabel `hutang_supplier`
--
ALTER TABLE `hutang_supplier`
  MODIFY `hutang_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `kategori_produk`
--
ALTER TABLE `kategori_produk`
  MODIFY `kategori_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `master_license`
--
ALTER TABLE `master_license`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `member_level`
--
ALTER TABLE `member_level`
  MODIFY `level_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pajak`
--
ALTER TABLE `pajak`
  MODIFY `pajak_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `pelanggan_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pelanggan_toko`
--
ALTER TABLE `pelanggan_toko`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `pembayaran_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pembayaran_hutang`
--
ALTER TABLE `pembayaran_hutang`
  MODIFY `bayar_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `pembelian`
--
ALTER TABLE `pembelian`
  MODIFY `pembelian_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `pembelian_detail`
--
ALTER TABLE `pembelian_detail`
  MODIFY `detail_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `pengguna_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `penjualan`
--
ALTER TABLE `penjualan`
  MODIFY `penjualan_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `penjualan_detail`
--
ALTER TABLE `penjualan_detail`
  MODIFY `detail_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `piutang`
--
ALTER TABLE `piutang`
  MODIFY `piutang_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `piutang_pembayaran`
--
ALTER TABLE `piutang_pembayaran`
  MODIFY `pembayaran_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `poin_member`
--
ALTER TABLE `poin_member`
  MODIFY `poin_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `printer_setting`
--
ALTER TABLE `printer_setting`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `produk_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `produk_harga`
--
ALTER TABLE `produk_harga`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `promo`
--
ALTER TABLE `promo`
  MODIFY `promo_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `promo_produk`
--
ALTER TABLE `promo_produk`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `purchase_order`
--
ALTER TABLE `purchase_order`
  MODIFY `po_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT untuk tabel `purchase_order_detail`
--
ALTER TABLE `purchase_order_detail`
  MODIFY `detail_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT untuk tabel `retur`
--
ALTER TABLE `retur`
  MODIFY `retur_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `retur_detail`
--
ALTER TABLE `retur_detail`
  MODIFY `detail_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `satuan`
--
ALTER TABLE `satuan`
  MODIFY `satuan_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `stok_gudang`
--
ALTER TABLE `stok_gudang`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `stok_mutasi`
--
ALTER TABLE `stok_mutasi`
  MODIFY `mutasi_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `supplier`
--
ALTER TABLE `supplier`
  MODIFY `supplier_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `toko`
--
ALTER TABLE `toko`
  MODIFY `toko_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `toko_config`
--
ALTER TABLE `toko_config`
  MODIFY `config_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`pengguna_id`),
  ADD CONSTRAINT `fk_audit_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`);

--
-- Ketidakleluasaan untuk tabel `device`
--
ALTER TABLE `device`
  ADD CONSTRAINT `fk_device_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `gudang`
--
ALTER TABLE `gudang`
  ADD CONSTRAINT `fk_gudang_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `hutang_supplier`
--
ALTER TABLE `hutang_supplier`
  ADD CONSTRAINT `fk_hutang_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`);

--
-- Ketidakleluasaan untuk tabel `kategori_produk`
--
ALTER TABLE `kategori_produk`
  ADD CONSTRAINT `fk_kategori_induk` FOREIGN KEY (`induk_id`) REFERENCES `kategori_produk` (`kategori_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_kategori_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `member_level`
--
ALTER TABLE `member_level`
  ADD CONSTRAINT `fk_level_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD CONSTRAINT `fk_pelanggan_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pelanggan_toko`
--
ALTER TABLE `pelanggan_toko`
  ADD CONSTRAINT `fk_pt_level` FOREIGN KEY (`level_id`,`toko_id`) REFERENCES `member_level` (`level_id`, `toko_id`),
  ADD CONSTRAINT `fk_pt_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pelanggan_id`),
  ADD CONSTRAINT `fk_pt_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`);

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `fk_pembayaran_penjualan` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`penjualan_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pembayaran_hutang`
--
ALTER TABLE `pembayaran_hutang`
  ADD CONSTRAINT `fk_bayar_hutang` FOREIGN KEY (`hutang_id`) REFERENCES `hutang_supplier` (`hutang_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bayar_hutang_link` FOREIGN KEY (`hutang_id`) REFERENCES `hutang_supplier` (`hutang_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bayar_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`);

--
-- Ketidakleluasaan untuk tabel `pembelian`
--
ALTER TABLE `pembelian`
  ADD CONSTRAINT `fk_pembelian_gudang` FOREIGN KEY (`gudang_id`) REFERENCES `gudang` (`gudang_id`),
  ADD CONSTRAINT `fk_pembelian_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`),
  ADD CONSTRAINT `fk_pembelian_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`);

--
-- Ketidakleluasaan untuk tabel `pembelian_detail`
--
ALTER TABLE `pembelian_detail`
  ADD CONSTRAINT `fk_detail_pembelian` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian` (`pembelian_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_detail_produk_beli` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`);

--
-- Ketidakleluasaan untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  ADD CONSTRAINT `fk_pengguna_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `penjualan`
--
ALTER TABLE `penjualan`
  ADD CONSTRAINT `fk_penjualan_gudang` FOREIGN KEY (`gudang_id`) REFERENCES `gudang` (`gudang_id`),
  ADD CONSTRAINT `fk_penjualan_kasir` FOREIGN KEY (`kasir_id`) REFERENCES `pengguna` (`pengguna_id`),
  ADD CONSTRAINT `fk_penjualan_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pelanggan_id`),
  ADD CONSTRAINT `fk_penjualan_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`);

--
-- Ketidakleluasaan untuk tabel `penjualan_detail`
--
ALTER TABLE `penjualan_detail`
  ADD CONSTRAINT `fk_detail_penjualan` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`penjualan_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_detail_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`);

--
-- Ketidakleluasaan untuk tabel `piutang`
--
ALTER TABLE `piutang`
  ADD CONSTRAINT `fk_piutang_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pelanggan_id`),
  ADD CONSTRAINT `fk_piutang_penjualan` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`penjualan_id`);

--
-- Ketidakleluasaan untuk tabel `piutang_pembayaran`
--
ALTER TABLE `piutang_pembayaran`
  ADD CONSTRAINT `fk_pembayaran_piutang` FOREIGN KEY (`piutang_id`) REFERENCES `piutang` (`piutang_id`);

--
-- Ketidakleluasaan untuk tabel `poin_member`
--
ALTER TABLE `poin_member`
  ADD CONSTRAINT `fk_poin_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`pelanggan_id`),
  ADD CONSTRAINT `fk_poin_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`);

--
-- Ketidakleluasaan untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_produk` (`kategori_id`),
  ADD CONSTRAINT `fk_produk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`),
  ADD CONSTRAINT `fk_produk_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`);

--
-- Ketidakleluasaan untuk tabel `produk_harga`
--
ALTER TABLE `produk_harga`
  ADD CONSTRAINT `produk_harga_ibfk_1` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`);

--
-- Ketidakleluasaan untuk tabel `promo`
--
ALTER TABLE `promo`
  ADD CONSTRAINT `fk_promo_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `promo_produk`
--
ALTER TABLE `promo_produk`
  ADD CONSTRAINT `fk_pp_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`),
  ADD CONSTRAINT `fk_pp_promo` FOREIGN KEY (`promo_id`) REFERENCES `promo` (`promo_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `retur`
--
ALTER TABLE `retur`
  ADD CONSTRAINT `fk_retur_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`);

--
-- Ketidakleluasaan untuk tabel `retur_detail`
--
ALTER TABLE `retur_detail`
  ADD CONSTRAINT `fk_retur_detail` FOREIGN KEY (`retur_id`) REFERENCES `retur` (`retur_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_retur_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`);

--
-- Ketidakleluasaan untuk tabel `stok_gudang`
--
ALTER TABLE `stok_gudang`
  ADD CONSTRAINT `fk_stok_gudang` FOREIGN KEY (`gudang_id`) REFERENCES `gudang` (`gudang_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stok_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`);

--
-- Ketidakleluasaan untuk tabel `stok_mutasi`
--
ALTER TABLE `stok_mutasi`
  ADD CONSTRAINT `fk_mutasi_gudang` FOREIGN KEY (`gudang_id`) REFERENCES `gudang` (`gudang_id`),
  ADD CONSTRAINT `fk_mutasi_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`produk_id`),
  ADD CONSTRAINT `fk_mutasi_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `toko_config`
--
ALTER TABLE `toko_config`
  ADD CONSTRAINT `fk_config_toko` FOREIGN KEY (`toko_id`) REFERENCES `toko` (`toko_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
