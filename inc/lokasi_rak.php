<?php
require_once __DIR__ . '/db.php';

function lokasi_rak_ensure_schema(Database $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS lokasi_rak (
        lokasi_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        toko_id BIGINT NOT NULL,
        gudang_id BIGINT NOT NULL,
        kode_lokasi VARCHAR(60) NOT NULL,
        nama_lokasi VARCHAR(120) NOT NULL,
        zona VARCHAR(40) NULL,
        lorong VARCHAR(40) NULL,
        level_rak VARCHAR(40) NULL,
        bin VARCHAR(40) NULL,
        kapasitas INT NOT NULL DEFAULT 0,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        catatan VARCHAR(255) NULL,
        dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        diupdate_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        KEY idx_lr_toko (toko_id),
        KEY idx_lr_toko_gudang (toko_id, gudang_id),
        KEY idx_lr_kode (kode_lokasi),
        KEY idx_lr_deleted (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS produk_lokasi_rak (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        toko_id BIGINT NOT NULL,
        gudang_id BIGINT NOT NULL,
        produk_id BIGINT NOT NULL,
        lokasi_id BIGINT NOT NULL,
        qty_display INT NOT NULL DEFAULT 0,
        min_display INT NOT NULL DEFAULT 0,
        max_display INT NOT NULL DEFAULT 0,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        diupdate_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        KEY idx_plr_toko (toko_id),
        KEY idx_plr_produk (toko_id, produk_id, deleted_at),
        KEY idx_plr_lokasi (toko_id, lokasi_id, deleted_at),
        KEY idx_plr_gudang (toko_id, gudang_id, deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
