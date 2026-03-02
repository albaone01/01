<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/header.php';
require_once '../../../inc/inventory.php';
require_once '../../../inc/pos_saas_schema.php';

requireLogin();
requireDevice();
ensure_pos_saas_schema($pos_db);

function so_ensure_tables(Database $db): void {
    try {
        $db->query("
            CREATE TABLE IF NOT EXISTS stock_opname_header (
                opname_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                toko_id BIGINT NOT NULL,
                gudang_id BIGINT NOT NULL,
                nomor_opname VARCHAR(40) NOT NULL,
                tanggal_opname DATETIME NOT NULL,
                status ENUM('draft','counted','adjusted','void') NOT NULL DEFAULT 'draft',
                catatan VARCHAR(255) DEFAULT NULL,
                total_item INT NOT NULL DEFAULT 0,
                total_selisih_qty INT NOT NULL DEFAULT 0,
                total_selisih_nominal DECIMAL(15,2) NOT NULL DEFAULT 0,
                dibuat_oleh BIGINT NOT NULL,
                dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                disahkan_oleh BIGINT DEFAULT NULL,
                disahkan_pada DATETIME DEFAULT NULL,
                adjusted_jurnal_id BIGINT DEFAULT NULL,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_so_nomor_toko (toko_id, nomor_opname),
                KEY idx_so_toko_status (toko_id, status, tanggal_opname),
                KEY idx_so_gudang (gudang_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->query("
            CREATE TABLE IF NOT EXISTS stock_opname_detail (
                detail_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                opname_id BIGINT NOT NULL,
                produk_id BIGINT NOT NULL,
                stok_sistem INT NOT NULL DEFAULT 0,
                stok_fisik INT DEFAULT NULL,
                selisih_qty INT NOT NULL DEFAULT 0,
                hpp_snapshot DECIMAL(15,2) NOT NULL DEFAULT 0,
                selisih_nominal DECIMAL(15,2) NOT NULL DEFAULT 0,
                alasan VARCHAR(255) DEFAULT NULL,
                adjusted TINYINT(1) NOT NULL DEFAULT 0,
                UNIQUE KEY uq_so_detail (opname_id, produk_id),
                KEY idx_so_detail_produk (produk_id),
                KEY idx_so_detail_opname (opname_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {}
}

function so_next_number(Database $db, int $tokoId): string {
    $prefix = 'SO-' . date('Ymd') . '-';
    for ($i = 0; $i < 9; $i++) {
        $num = $prefix . str_pad((string)rand(0, 999), 3, '0', STR_PAD_LEFT);
        $st = $db->prepare("SELECT 1 FROM stock_opname_header WHERE toko_id=? AND nomor_opname=? LIMIT 1");
        $st->bind_param('is', $tokoId, $num);
        $st->execute();
        $has = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$has) return $num;
    }
    return $prefix . time();
}

function so_get_gudang(Database $db, int $tokoId): array {
    $st = $db->prepare("SELECT gudang_id, nama_gudang FROM gudang WHERE toko_id=? AND aktif=1 AND deleted_at IS NULL ORDER BY nama_gudang");
    $st->bind_param('i', $tokoId);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows ?: [];
}

function so_create_from_system(Database $db, int $tokoId, int $gudangId, int $userId, string $catatan = ''): int {
    $nomor = so_next_number($db, $tokoId);
    $now = date('Y-m-d H:i:s');

    $db->begin_transaction();
    try {
        $h = $db->prepare("
            INSERT INTO stock_opname_header
            (toko_id, gudang_id, nomor_opname, tanggal_opname, status, catatan, dibuat_oleh)
            VALUES (?,?,?,?, 'draft', ?, ?)
        ");
        $h->bind_param('iisssi', $tokoId, $gudangId, $nomor, $now, $catatan, $userId);
        $h->execute();
        $opnameId = (int)$db->insertId();
        $h->close();

        $st = $db->prepare("
            SELECT p.produk_id,
                   COALESCE(sg.stok, 0) AS stok_sistem,
                   COALESCE(NULLIF(p.hpp_aktif,0), p.harga_modal, 0) AS hpp_snapshot
            FROM produk p
            LEFT JOIN stok_gudang sg
              ON sg.produk_id = p.produk_id
             AND sg.gudang_id = ?
             AND sg.toko_id = p.toko_id
            WHERE p.toko_id = ?
              AND p.aktif = 1
              AND p.deleted_at IS NULL
            ORDER BY p.nama_produk ASC
        ");
        $st->bind_param('ii', $gudangId, $tokoId);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        $ins = $db->prepare("
            INSERT INTO stock_opname_detail
            (opname_id, produk_id, stok_sistem, stok_fisik, selisih_qty, hpp_snapshot, selisih_nominal, alasan)
            VALUES (?,?,?,?,0,?,0,'')
        ");
        $totalItem = 0;
        foreach ($rows as $r) {
            $pid = (int)$r['produk_id'];
            $stokSistem = (int)$r['stok_sistem'];
            $stokFisik = $stokSistem;
            $hpp = (float)$r['hpp_snapshot'];
            $ins->bind_param('iiiid', $opnameId, $pid, $stokSistem, $stokFisik, $hpp);
            $ins->execute();
            $totalItem++;
        }
        $ins->close();

        $u = $db->prepare("UPDATE stock_opname_header SET total_item=? WHERE opname_id=?");
        $u->bind_param('ii', $totalItem, $opnameId);
        $u->execute();
        $u->close();

        $db->commit();
        return $opnameId;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function so_get_header(Database $db, int $tokoId, int $opnameId): ?array {
    $st = $db->prepare("
        SELECT h.*, g.nama_gudang, u.nama AS dibuat_nama
        FROM stock_opname_header h
        LEFT JOIN gudang g ON g.gudang_id = h.gudang_id
        LEFT JOIN pengguna u ON u.pengguna_id = h.dibuat_oleh
        WHERE h.opname_id=? AND h.toko_id=? AND h.deleted_at IS NULL
        LIMIT 1
    ");
    $st->bind_param('ii', $opnameId, $tokoId);
    $st->execute();
    $rw = $st->get_result()->fetch_assoc();
    $st->close();
    return $rw ?: null;
}

function so_get_detail(Database $db, int $opnameId, int $tokoId, string $q = ''): array {
    $where = "WHERE d.opname_id=? AND p.toko_id=? AND p.deleted_at IS NULL";
    $types = 'ii';
    $params = [$opnameId, $tokoId];
    if ($q !== '') {
        $where .= " AND (p.nama_produk LIKE CONCAT('%',?,'%') OR p.sku LIKE CONCAT('%',?,'%') OR p.barcode LIKE CONCAT('%',?,'%'))";
        $types .= 'sss';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }
    $sql = "
        SELECT d.detail_id, d.produk_id, d.stok_sistem, d.stok_fisik, d.selisih_qty, d.hpp_snapshot, d.selisih_nominal, d.alasan,
               p.nama_produk, p.sku, p.barcode
        FROM stock_opname_detail d
        JOIN produk p ON p.produk_id = d.produk_id
        $where
        ORDER BY p.nama_produk ASC
    ";
    $st = $db->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows ?: [];
}

function so_save_physical(Database $db, int $tokoId, int $opnameId, array $fisikMap, array $alasanMap = []): void {
    $header = so_get_header($db, $tokoId, $opnameId);
    if (!$header) throw new RuntimeException('Dokumen opname tidak ditemukan.');
    if (!in_array((string)$header['status'], ['draft', 'counted'], true)) {
        throw new RuntimeException('Dokumen opname tidak bisa diubah.');
    }

    $db->begin_transaction();
    try {
        $dt = so_get_detail($db, $opnameId, $tokoId, '');
        $upd = $db->prepare("UPDATE stock_opname_detail SET stok_fisik=?, selisih_qty=?, selisih_nominal=?, alasan=? WHERE detail_id=?");
        $totalQty = 0;
        $totalNominal = 0.0;
        foreach ($dt as $r) {
            $detailId = (int)$r['detail_id'];
            $stokSistem = (int)$r['stok_sistem'];
            $stokFisik = isset($fisikMap[$detailId]) ? (int)$fisikMap[$detailId] : (int)$r['stok_fisik'];
            if ($stokFisik < 0) $stokFisik = 0;
            $selisihQty = $stokFisik - $stokSistem;
            $hpp = (float)$r['hpp_snapshot'];
            $selisihNominal = $selisihQty * $hpp;
            $alasan = trim((string)($alasanMap[$detailId] ?? $r['alasan'] ?? ''));
            $upd->bind_param('iidsi', $stokFisik, $selisihQty, $selisihNominal, $alasan, $detailId);
            $upd->execute();
            $totalQty += $selisihQty;
            $totalNominal += $selisihNominal;
        }
        $upd->close();

        $u = $db->prepare("
            UPDATE stock_opname_header
            SET status='counted',
                total_selisih_qty=?,
                total_selisih_nominal=?
            WHERE opname_id=? AND toko_id=?
        ");
        $u->bind_param('idii', $totalQty, $totalNominal, $opnameId, $tokoId);
        $u->execute();
        $u->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function so_ensure_accounts(Database $db, int $tokoId): array {
    $db->query("INSERT IGNORE INTO akun_coa (toko_id, kode_akun, nama_akun, tipe, system_flag) VALUES ($tokoId, '4202', 'Pendapatan Selisih Stok', 'revenue', 1)");
    $db->query("INSERT IGNORE INTO akun_coa (toko_id, kode_akun, nama_akun, tipe, system_flag) VALUES ($tokoId, '5102', 'Beban Selisih Stok', 'expense', 1)");
    return get_coa_map($db, $tokoId);
}

function so_apply_adjustment(Database $db, int $tokoId, int $opnameId, int $userId): array {
    $header = so_get_header($db, $tokoId, $opnameId);
    if (!$header) throw new RuntimeException('Dokumen opname tidak ditemukan.');
    if ((string)$header['status'] !== 'counted') {
        throw new RuntimeException('Dokumen harus status counted sebelum adjustment.');
    }

    $details = so_get_detail($db, $opnameId, $tokoId, '');
    if (empty($details)) throw new RuntimeException('Detail opname kosong.');

    $ref = 'ADJ-' . (string)$header['nomor_opname'];
    $gudangId = (int)$header['gudang_id'];

    $db->begin_transaction();
    try {
        $totalNominal = 0.0;
        $mutatedRows = 0;
        foreach ($details as $d) {
            $diff = (int)$d['selisih_qty'];
            if ($diff === 0) continue;
            $qty = abs($diff);
            if ($diff > 0) {
                apply_stock_mutation($db, $tokoId, $gudangId, (int)$d['produk_id'], $qty, 'masuk', $ref);
            } else {
                apply_stock_mutation($db, $tokoId, $gudangId, (int)$d['produk_id'], $qty, 'keluar', $ref);
            }
            $totalNominal += (float)$d['selisih_nominal'];
            $mutatedRows++;
        }

        $jurnalId = null;
        if (abs($totalNominal) > 0.0001) {
            $coa = so_ensure_accounts($db, $tokoId);
            $akunPersediaan = (int)($coa['1201'] ?? 0);
            $akunPendapatanSelisih = (int)($coa['4202'] ?? 0);
            $akunBebanSelisih = (int)($coa['5102'] ?? 0);
            if (!$akunPersediaan || !$akunPendapatanSelisih || !$akunBebanSelisih) {
                throw new RuntimeException('Akun COA untuk adjustment stok belum lengkap.');
            }

            $nilai = abs($totalNominal);
            if ($totalNominal > 0) {
                $lines = [
                    ['akun_id' => $akunPersediaan, 'deskripsi' => 'Kenaikan persediaan dari stok opname', 'debit' => $nilai, 'kredit' => 0],
                    ['akun_id' => $akunPendapatanSelisih, 'deskripsi' => 'Pendapatan selisih stok', 'debit' => 0, 'kredit' => $nilai],
                ];
            } else {
                $lines = [
                    ['akun_id' => $akunBebanSelisih, 'deskripsi' => 'Beban selisih stok', 'debit' => $nilai, 'kredit' => 0],
                    ['akun_id' => $akunPersediaan, 'deskripsi' => 'Penurunan persediaan dari stok opname', 'debit' => 0, 'kredit' => $nilai],
                ];
            }
            $jurnalId = insert_journal_entry(
                $db,
                $tokoId,
                $userId,
                new DateTimeImmutable(),
                'stok_opname',
                'stock_opname_header',
                $opnameId,
                'Adjustment Stok Opname ' . (string)$header['nomor_opname'],
                $lines
            );
        }

        $u = $db->prepare("
            UPDATE stock_opname_header
            SET status='adjusted',
                disahkan_oleh=?,
                disahkan_pada=NOW(),
                adjusted_jurnal_id=?
            WHERE opname_id=? AND toko_id=?
        ");
        $u->bind_param('iiii', $userId, $jurnalId, $opnameId, $tokoId);
        $u->execute();
        $u->close();

        $db->query("UPDATE stock_opname_detail SET adjusted=1 WHERE opname_id=" . (int)$opnameId);

        $db->commit();
        return [
            'mutated_rows' => $mutatedRows,
            'total_nominal' => $totalNominal,
            'jurnal_id' => $jurnalId,
            'referensi' => $ref,
        ];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
