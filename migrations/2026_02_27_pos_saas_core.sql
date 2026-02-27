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
  shift_template_id BIGINT DEFAULT NULL,
  tanggal_shift DATE NOT NULL,
  jam_buka_real DATETIME DEFAULT NULL,
  jam_tutup_real DATETIME DEFAULT NULL,
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
  KEY idx_shift_template (shift_template_id),
  CONSTRAINT fk_shift_toko FOREIGN KEY (toko_id) REFERENCES toko (toko_id) ON DELETE CASCADE,
  CONSTRAINT fk_shift_kasir FOREIGN KEY (kasir_id) REFERENCES pengguna (pengguna_id) ON DELETE RESTRICT,
  CONSTRAINT fk_shift_device FOREIGN KEY (device_id) REFERENCES device (device_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS jurnal_counter (
  toko_id BIGINT NOT NULL,
  tanggal DATE NOT NULL,
  last_seq INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (toko_id, tanggal),
  CONSTRAINT fk_jcounter_toko FOREIGN KEY (toko_id) REFERENCES toko (toko_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shift_template (
  template_id BIGINT NOT NULL AUTO_INCREMENT,
  toko_id BIGINT NOT NULL,
  nama_shift VARCHAR(80) NOT NULL,
  jam_mulai TIME NOT NULL,
  jam_selesai TIME NOT NULL,
  urutan INT NOT NULL DEFAULT 1,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (template_id),
  UNIQUE KEY uq_shift_template (toko_id, nama_shift),
  KEY idx_shift_template_toko (toko_id, aktif),
  CONSTRAINT fk_shift_template_toko FOREIGN KEY (toko_id) REFERENCES toko (toko_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shift_template_assignment (
  assignment_id BIGINT NOT NULL AUTO_INCREMENT,
  toko_id BIGINT NOT NULL,
  kasir_id BIGINT NOT NULL,
  template_id BIGINT NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (assignment_id),
  UNIQUE KEY uq_shift_assignment (toko_id, kasir_id, template_id),
  KEY idx_shift_assignment_kasir (toko_id, kasir_id, aktif),
  CONSTRAINT fk_shift_assignment_toko FOREIGN KEY (toko_id) REFERENCES toko (toko_id) ON DELETE CASCADE,
  CONSTRAINT fk_shift_assignment_kasir FOREIGN KEY (kasir_id) REFERENCES pengguna (pengguna_id) ON DELETE CASCADE,
  CONSTRAINT fk_shift_assignment_template FOREIGN KEY (template_id) REFERENCES shift_template (template_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cash_movement (
  movement_id BIGINT NOT NULL AUTO_INCREMENT,
  toko_id BIGINT NOT NULL,
  shift_id BIGINT NOT NULL,
  kasir_id BIGINT NOT NULL,
  tipe ENUM('in','out') NOT NULL,
  kategori VARCHAR(100) NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL,
  catatan VARCHAR(255) DEFAULT NULL,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (movement_id),
  KEY idx_cash_move_shift (shift_id),
  KEY idx_cash_move_toko (toko_id, dibuat_pada),
  CONSTRAINT fk_cash_move_toko FOREIGN KEY (toko_id) REFERENCES toko (toko_id) ON DELETE CASCADE,
  CONSTRAINT fk_cash_move_shift FOREIGN KEY (shift_id) REFERENCES kasir_shift (shift_id) ON DELETE CASCADE,
  CONSTRAINT fk_cash_move_kasir FOREIGN KEY (kasir_id) REFERENCES pengguna (pengguna_id) ON DELETE RESTRICT
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

ALTER TABLE penjualan
  ADD COLUMN IF NOT EXISTS shift_id BIGINT DEFAULT NULL AFTER gudang_id;

-- Payment detail for accurate cashflow tracking
ALTER TABLE pembayaran
  ADD COLUMN IF NOT EXISTS uang_diterima DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER jumlah,
  ADD COLUMN IF NOT EXISTS kembalian DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER uang_diterima;

UPDATE pembayaran
SET uang_diterima = CASE WHEN uang_diterima <= 0 THEN jumlah ELSE uang_diterima END,
    kembalian = CASE WHEN metode = 'cash' THEN LEAST(kembalian, uang_diterima) ELSE 0 END
WHERE uang_diterima = 0 OR kembalian < 0;

INSERT IGNORE INTO shift_template (toko_id, nama_shift, jam_mulai, jam_selesai, urutan, aktif)
SELECT toko_id, 'Pagi', '08:00:00', '13:00:00', 1, 1 FROM toko WHERE deleted_at IS NULL;
INSERT IGNORE INTO shift_template (toko_id, nama_shift, jam_mulai, jam_selesai, urutan, aktif)
SELECT toko_id, 'Siang', '13:00:00', '17:00:00', 2, 1 FROM toko WHERE deleted_at IS NULL;
INSERT IGNORE INTO shift_template (toko_id, nama_shift, jam_mulai, jam_selesai, urutan, aktif)
SELECT toko_id, 'Malam', '17:00:00', '21:00:00', 3, 1 FROM toko WHERE deleted_at IS NULL;
