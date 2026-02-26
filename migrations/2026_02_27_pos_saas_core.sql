-- POS SaaS Core Schema
-- Added: akun_coa, jurnal_umum, jurnal_detail, kasir_shift

CREATE TABLE IF NOT EXISTS akun_coa (
  akun_id BIGINT NOT NULL AUTO_INCREMENT,
  toko_id BIGINT NOT NULL,
  kode_akun VARCHAR(30) NOT NULL,
  nama_akun VARCHAR(120) NOT NULL,
  tipe ENUM('asset','liability','equity','revenue','expense') NOT NULL,
  parent_id BIGINT DEFAULT NULL,
  is_header TINYINT(1) NOT NULL DEFAULT 0,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  system_flag TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (akun_id),
  UNIQUE KEY uq_coa_toko_kode (toko_id, kode_akun),
  KEY idx_coa_toko (toko_id),
  KEY idx_coa_parent (parent_id),
  CONSTRAINT fk_coa_toko FOREIGN KEY (toko_id) REFERENCES toko (toko_id) ON DELETE CASCADE,
  CONSTRAINT fk_coa_parent FOREIGN KEY (parent_id) REFERENCES akun_coa (akun_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS jurnal_umum (
  jurnal_id BIGINT NOT NULL AUTO_INCREMENT,
  toko_id BIGINT NOT NULL,
  tanggal DATE NOT NULL,
  nomor_jurnal VARCHAR(50) NOT NULL,
  sumber ENUM('penjualan','pembelian','piutang_pembayaran','hutang_pembayaran','manual','closing_kasir') NOT NULL,
  referensi_tabel VARCHAR(100) DEFAULT NULL,
  referensi_id BIGINT DEFAULT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  total_debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_kredit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  dibuat_oleh BIGINT DEFAULT NULL,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (jurnal_id),
  UNIQUE KEY uq_jurnal_toko_nomor (toko_id, nomor_jurnal),
  KEY idx_jurnal_tanggal (toko_id, tanggal),
  KEY idx_jurnal_referensi (referensi_tabel, referensi_id),
  CONSTRAINT fk_jurnal_toko FOREIGN KEY (toko_id) REFERENCES toko (toko_id) ON DELETE CASCADE,
  CONSTRAINT fk_jurnal_pengguna FOREIGN KEY (dibuat_oleh) REFERENCES pengguna (pengguna_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS jurnal_detail (
  detail_id BIGINT NOT NULL AUTO_INCREMENT,
  jurnal_id BIGINT NOT NULL,
  akun_id BIGINT NOT NULL,
  deskripsi VARCHAR(255) DEFAULT NULL,
  debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  kredit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (detail_id),
  KEY idx_detail_jurnal (jurnal_id),
  KEY idx_detail_akun (akun_id),
  CONSTRAINT fk_detail_jurnal FOREIGN KEY (jurnal_id) REFERENCES jurnal_umum (jurnal_id) ON DELETE CASCADE,
  CONSTRAINT fk_detail_akun FOREIGN KEY (akun_id) REFERENCES akun_coa (akun_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS kasir_shift (
  shift_id BIGINT NOT NULL AUTO_INCREMENT,
  toko_id BIGINT NOT NULL,
  kasir_id BIGINT NOT NULL,
  device_id BIGINT DEFAULT NULL,
  tanggal_shift DATE NOT NULL,
  jam_buka DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  jam_tutup DATETIME DEFAULT NULL,
  modal_awal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  kas_sistem DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  kas_fisik DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  selisih DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  catatan TEXT DEFAULT NULL,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (shift_id),
  UNIQUE KEY uq_shift_harian (toko_id, kasir_id, tanggal_shift),
  KEY idx_shift_toko_tanggal (toko_id, tanggal_shift),
  KEY idx_shift_status (status),
  CONSTRAINT fk_shift_toko FOREIGN KEY (toko_id) REFERENCES toko (toko_id) ON DELETE CASCADE,
  CONSTRAINT fk_shift_kasir FOREIGN KEY (kasir_id) REFERENCES pengguna (pengguna_id) ON DELETE RESTRICT,
  CONSTRAINT fk_shift_device FOREIGN KEY (device_id) REFERENCES device (device_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed default COA per toko (idempotent)
INSERT IGNORE INTO akun_coa (toko_id, kode_akun, nama_akun, tipe, system_flag) SELECT toko_id, '1101', 'Kas', 'asset', 1 FROM toko WHERE deleted_at IS NULL;
INSERT IGNORE INTO akun_coa (toko_id, kode_akun, nama_akun, tipe, system_flag) SELECT toko_id, '1102', 'Bank/QRIS', 'asset', 1 FROM toko WHERE deleted_at IS NULL;
INSERT IGNORE INTO akun_coa (toko_id, kode_akun, nama_akun, tipe, system_flag) SELECT toko_id, '1103', 'Piutang Usaha', 'asset', 1 FROM toko WHERE deleted_at IS NULL;
INSERT IGNORE INTO akun_coa (toko_id, kode_akun, nama_akun, tipe, system_flag) SELECT toko_id, '1201', 'Persediaan', 'asset', 1 FROM toko WHERE deleted_at IS NULL;
INSERT IGNORE INTO akun_coa (toko_id, kode_akun, nama_akun, tipe, system_flag) SELECT toko_id, '2101', 'Hutang Dagang', 'liability', 1 FROM toko WHERE deleted_at IS NULL;
INSERT IGNORE INTO akun_coa (toko_id, kode_akun, nama_akun, tipe, system_flag) SELECT toko_id, '4101', 'Penjualan', 'revenue', 1 FROM toko WHERE deleted_at IS NULL;
INSERT IGNORE INTO akun_coa (toko_id, kode_akun, nama_akun, tipe, system_flag) SELECT toko_id, '4201', 'Pendapatan Selisih Kas', 'revenue', 1 FROM toko WHERE deleted_at IS NULL;
INSERT IGNORE INTO akun_coa (toko_id, kode_akun, nama_akun, tipe, system_flag) SELECT toko_id, '5101', 'Beban Selisih Kas', 'expense', 1 FROM toko WHERE deleted_at IS NULL;
