<?php

function promo_ensure_tables(Database $db): void {
    try {
        $db->query("
            CREATE TABLE IF NOT EXISTS promo (
                promo_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                toko_id BIGINT NOT NULL,
                nama_promo VARCHAR(100) NOT NULL,
                tipe ENUM('persen','nominal','gratis') NOT NULL,
                nilai DECIMAL(15,2) NOT NULL DEFAULT 0,
                minimal_belanja DECIMAL(15,2) DEFAULT 0,
                berlaku_dari DATETIME NOT NULL,
                berlaku_sampai DATETIME NOT NULL,
                aktif TINYINT(1) DEFAULT 1,
                dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                KEY idx_promo_toko (toko_id),
                KEY idx_promo_active (toko_id, aktif, berlaku_dari, berlaku_sampai)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->query("
            CREATE TABLE IF NOT EXISTS promo_produk (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                promo_id BIGINT NOT NULL,
                produk_id BIGINT NOT NULL,
                UNIQUE KEY uq_promo_produk (promo_id, produk_id),
                KEY idx_pp_produk (produk_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->query("
            CREATE TABLE IF NOT EXISTS promo_supplier (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                promo_id BIGINT NOT NULL,
                supplier_id BIGINT NOT NULL,
                UNIQUE KEY uq_promo_supplier (promo_id, supplier_id),
                KEY idx_ps_supplier (supplier_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->query("
            CREATE TABLE IF NOT EXISTS promo_kategori (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                promo_id BIGINT NOT NULL,
                kategori_id BIGINT NOT NULL,
                UNIQUE KEY uq_promo_kategori (promo_id, kategori_id),
                KEY idx_pk_kategori (kategori_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->query("
            CREATE TABLE IF NOT EXISTS promo_member_produk (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                promo_id BIGINT NOT NULL,
                produk_id BIGINT NOT NULL,
                UNIQUE KEY uq_promo_member_produk (promo_id, produk_id),
                KEY idx_pmp_produk (produk_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->query("
            CREATE TABLE IF NOT EXISTS promo_member_supplier (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                promo_id BIGINT NOT NULL,
                supplier_id BIGINT NOT NULL,
                UNIQUE KEY uq_promo_member_supplier (promo_id, supplier_id),
                KEY idx_pms_supplier (supplier_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->query("
            CREATE TABLE IF NOT EXISTS promo_member_kategori (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                promo_id BIGINT NOT NULL,
                kategori_id BIGINT NOT NULL,
                UNIQUE KEY uq_promo_member_kategori (promo_id, kategori_id),
                KEY idx_pmk_kategori (kategori_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->query("
            CREATE TABLE IF NOT EXISTS promo_bersyarat (
                rule_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                toko_id BIGINT NOT NULL,
                nama_rule VARCHAR(120) NOT NULL,
                minimal_belanja DECIMAL(15,2) NOT NULL DEFAULT 0,
                minimal_qty INT NOT NULL DEFAULT 0,
                minimal_item INT NOT NULL DEFAULT 0,
                tipe_hadiah ENUM('persen','nominal') NOT NULL DEFAULT 'nominal',
                nilai_hadiah DECIMAL(15,2) NOT NULL DEFAULT 0,
                max_diskon DECIMAL(15,2) NOT NULL DEFAULT 0,
                berlaku_dari DATETIME NOT NULL,
                berlaku_sampai DATETIME NOT NULL,
                aktif TINYINT(1) NOT NULL DEFAULT 1,
                dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                KEY idx_rule_toko (toko_id, aktif, berlaku_dari, berlaku_sampai)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->query("
            CREATE TABLE IF NOT EXISTS voucher_belanja (
                voucher_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                toko_id BIGINT NOT NULL,
                kode_voucher VARCHAR(40) NOT NULL,
                nama_voucher VARCHAR(120) NOT NULL,
                tipe ENUM('nominal','persen') NOT NULL DEFAULT 'nominal',
                nilai DECIMAL(15,2) NOT NULL DEFAULT 0,
                minimal_belanja DECIMAL(15,2) NOT NULL DEFAULT 0,
                kuota INT NOT NULL DEFAULT 1,
                terpakai INT NOT NULL DEFAULT 0,
                berlaku_dari DATETIME NOT NULL,
                berlaku_sampai DATETIME NOT NULL,
                aktif TINYINT(1) NOT NULL DEFAULT 1,
                catatan VARCHAR(255) DEFAULT NULL,
                dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_voucher_toko_kode (toko_id, kode_voucher),
                KEY idx_voucher_toko (toko_id, aktif, berlaku_dari, berlaku_sampai)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
    }
}

function promo_get_mapping(Database $db, array $promoIds, string $table, string $field): array {
    if (empty($promoIds)) return [];
    $ph = implode(',', array_fill(0, count($promoIds), '?'));
    $types = str_repeat('i', count($promoIds));
    $sql = "SELECT promo_id, $field AS ref_id FROM $table WHERE promo_id IN ($ph)";
    $st = $db->prepare($sql);
    $st->bind_param($types, ...$promoIds);
    $st->execute();
    $rs = $st->get_result();
    $out = [];
    while ($rw = $rs->fetch_assoc()) {
        $pid = (int)$rw['promo_id'];
        if (!isset($out[$pid])) $out[$pid] = [];
        $out[$pid][(int)$rw['ref_id']] = 1;
    }
    $st->close();
    return $out;
}

function promo_calc_discount(string $tipe, float $nilai, float $base): float {
    $nilai = max(0, $nilai);
    if ($base <= 0) return 0.0;
    if ($tipe === 'persen') return min($base, $base * $nilai / 100);
    if ($tipe === 'nominal') return min($base, $nilai);
    return 0.0;
}

function promo_evaluate(
    Database $db,
    int $tokoId,
    array $lines,
    float $subtotal,
    float $diskonItem,
    bool $isMember,
    string $voucherCode = ''
): array {
    promo_ensure_tables($db);
    $baseTotal = max(0, $subtotal - $diskonItem);
    $result = [
        'base_total' => $baseTotal,
        'promo_discount' => 0.0,
        'promo' => null,
        'rule_discount' => 0.0,
        'rule' => null,
        'voucher_discount' => 0.0,
        'voucher' => null,
    ];
    if ($baseTotal <= 0) return $result;

    $lineByProduk = [];
    $lineBySupplier = [];
    $lineByKategori = [];
    $totalQty = 0;
    $distinctProduk = [];
    foreach ($lines as $ln) {
        $produkId = (int)($ln['produk_id'] ?? 0);
        $supplierId = (int)($ln['supplier_id'] ?? 0);
        $kategoriId = (int)($ln['kategori_id'] ?? 0);
        $lineAfterDisc = (float)($ln['line_after_disc'] ?? 0);
        $qty = (int)($ln['qty'] ?? 0);
        $totalQty += max(0, $qty);
        if ($produkId > 0) {
            $distinctProduk[$produkId] = 1;
            $lineByProduk[$produkId] = ($lineByProduk[$produkId] ?? 0) + $lineAfterDisc;
        }
        if ($supplierId > 0) $lineBySupplier[$supplierId] = ($lineBySupplier[$supplierId] ?? 0) + $lineAfterDisc;
        if ($kategoriId > 0) $lineByKategori[$kategoriId] = ($lineByKategori[$kategoriId] ?? 0) + $lineAfterDisc;
    }
    $distinctItem = count($distinctProduk);

    $stPromo = $db->prepare("
        SELECT promo_id, nama_promo, tipe, nilai, minimal_belanja
        FROM promo
        WHERE toko_id=?
          AND aktif=1
          AND deleted_at IS NULL
          AND NOW() BETWEEN berlaku_dari AND berlaku_sampai
          AND minimal_belanja <= ?
    ");
    $stPromo->bind_param('id', $tokoId, $baseTotal);
    $stPromo->execute();
    $promos = $stPromo->get_result()->fetch_all(MYSQLI_ASSOC);
    $stPromo->close();

    if (!empty($promos)) {
        $promoIds = array_map(static fn($p) => (int)$p['promo_id'], $promos);
        $mProduk = promo_get_mapping($db, $promoIds, 'promo_produk', 'produk_id');
        $mSup = promo_get_mapping($db, $promoIds, 'promo_supplier', 'supplier_id');
        $mKat = promo_get_mapping($db, $promoIds, 'promo_kategori', 'kategori_id');
        $mmProduk = $isMember ? promo_get_mapping($db, $promoIds, 'promo_member_produk', 'produk_id') : [];
        $mmSup = $isMember ? promo_get_mapping($db, $promoIds, 'promo_member_supplier', 'supplier_id') : [];
        $mmKat = $isMember ? promo_get_mapping($db, $promoIds, 'promo_member_kategori', 'kategori_id') : [];

        $best = 0.0;
        $bestPromo = null;
        foreach ($promos as $p) {
            $promoId = (int)$p['promo_id'];
            $setProduk = $mProduk[$promoId] ?? [];
            $setSup = $mSup[$promoId] ?? [];
            $setKat = $mKat[$promoId] ?? [];
            $setMProduk = $mmProduk[$promoId] ?? [];
            $setMSup = $mmSup[$promoId] ?? [];
            $setMKat = $mmKat[$promoId] ?? [];

            $hasMapping =
                !empty($setProduk) || !empty($setSup) || !empty($setKat) ||
                !empty($setMProduk) || !empty($setMSup) || !empty($setMKat);

            $scopeBase = $baseTotal;
            if ($hasMapping) {
                $scopeBase = 0.0;
                foreach ($lines as $ln) {
                    $pid = (int)($ln['produk_id'] ?? 0);
                    $sid = (int)($ln['supplier_id'] ?? 0);
                    $kid = (int)($ln['kategori_id'] ?? 0);
                    $v = (float)($ln['line_after_disc'] ?? 0);
                    $matched =
                        isset($setProduk[$pid]) || isset($setSup[$sid]) || isset($setKat[$kid]) ||
                        isset($setMProduk[$pid]) || isset($setMSup[$sid]) || isset($setMKat[$kid]);
                    if ($matched) $scopeBase += $v;
                }
            }
            if ($scopeBase <= 0) continue;

            $disc = promo_calc_discount((string)$p['tipe'], (float)$p['nilai'], $scopeBase);
            if ($disc > $best) {
                $best = $disc;
                $bestPromo = [
                    'promo_id' => $promoId,
                    'nama_promo' => (string)$p['nama_promo'],
                    'tipe' => (string)$p['tipe'],
                    'nilai' => (float)$p['nilai'],
                    'scope_base' => $scopeBase,
                ];
            }
        }
        $result['promo_discount'] = $best;
        $result['promo'] = $bestPromo;
    }

    $stRule = $db->prepare("
        SELECT rule_id, nama_rule, tipe_hadiah, nilai_hadiah, max_diskon
        FROM promo_bersyarat
        WHERE toko_id=?
          AND aktif=1
          AND deleted_at IS NULL
          AND NOW() BETWEEN berlaku_dari AND berlaku_sampai
          AND minimal_belanja <= ?
          AND minimal_qty <= ?
          AND minimal_item <= ?
    ");
    $stRule->bind_param('idii', $tokoId, $baseTotal, $totalQty, $distinctItem);
    $stRule->execute();
    $rules = $stRule->get_result()->fetch_all(MYSQLI_ASSOC);
    $stRule->close();
    if (!empty($rules)) {
        $bestRuleDisc = 0.0;
        $bestRule = null;
        foreach ($rules as $r) {
            $d = promo_calc_discount((string)$r['tipe_hadiah'], (float)$r['nilai_hadiah'], $baseTotal);
            $max = max(0, (float)($r['max_diskon'] ?? 0));
            if ($max > 0) $d = min($d, $max);
            if ($d > $bestRuleDisc) {
                $bestRuleDisc = $d;
                $bestRule = [
                    'rule_id' => (int)$r['rule_id'],
                    'nama_rule' => (string)$r['nama_rule'],
                    'tipe_hadiah' => (string)$r['tipe_hadiah'],
                    'nilai_hadiah' => (float)$r['nilai_hadiah'],
                    'max_diskon' => $max,
                ];
            }
        }
        $result['rule_discount'] = $bestRuleDisc;
        $result['rule'] = $bestRule;
    }

    $voucherCode = strtoupper(trim($voucherCode));
    if ($voucherCode !== '') {
        $stV = $db->prepare("
            SELECT voucher_id, kode_voucher, nama_voucher, tipe, nilai, minimal_belanja, kuota, terpakai
            FROM voucher_belanja
            WHERE toko_id=?
              AND kode_voucher=?
              AND deleted_at IS NULL
              AND aktif=1
              AND NOW() BETWEEN berlaku_dari AND berlaku_sampai
            LIMIT 1
        ");
        $stV->bind_param('is', $tokoId, $voucherCode);
        $stV->execute();
        $voucher = $stV->get_result()->fetch_assoc();
        $stV->close();
        if ($voucher) {
            $kuota = (int)($voucher['kuota'] ?? 0);
            $terpakai = (int)($voucher['terpakai'] ?? 0);
            $minBelanja = (float)($voucher['minimal_belanja'] ?? 0);
            if ($baseTotal >= $minBelanja && $terpakai < $kuota) {
                $remainingBase = max(0, $baseTotal - $result['promo_discount'] - $result['rule_discount']);
                $vd = promo_calc_discount((string)$voucher['tipe'], (float)$voucher['nilai'], $remainingBase);
                $result['voucher_discount'] = $vd;
                $result['voucher'] = [
                    'voucher_id' => (int)$voucher['voucher_id'],
                    'kode_voucher' => (string)$voucher['kode_voucher'],
                    'nama_voucher' => (string)$voucher['nama_voucher'],
                    'tipe' => (string)$voucher['tipe'],
                    'nilai' => (float)$voucher['nilai'],
                    'minimal_belanja' => $minBelanja,
                ];
            }
        }
    }

    return $result;
}
