<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function ensure_inventory_snapshot_columns(Database $db): void {
    $adds = [];
    $c1 = $db->query("SHOW COLUMNS FROM produk LIKE 'last_harga_beli'");
    if (!$c1 || !$c1->fetch_assoc()) $adds[] = "ADD COLUMN last_harga_beli DECIMAL(15,2) NOT NULL DEFAULT 0.00";
    $c2 = $db->query("SHOW COLUMNS FROM produk LIKE 'hpp_aktif'");
    if (!$c2 || !$c2->fetch_assoc()) $adds[] = "ADD COLUMN hpp_aktif DECIMAL(15,2) NOT NULL DEFAULT 0.00";
    if ($adds) {
        $db->query("ALTER TABLE produk " . implode(', ', $adds));
    }
}

function get_stok_gudang_now(Database $db, int $tokoId, int $gudangId, int $produkId): int {
    $stmt = $db->prepare("SELECT stok FROM stok_gudang WHERE toko_id=? AND gudang_id=? AND produk_id=? LIMIT 1");
    $stmt->bind_param("iii", $tokoId, $gudangId, $produkId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['stok'] : 0;
}

function apply_stock_mutation(
    Database $db,
    int $tokoId,
    int $gudangId,
    int $produkId,
    int $qty,
    string $tipe,
    string $referensi,
    ?int $minStok = null
): array {
    if ($qty <= 0) {
        throw new Exception('Qty mutasi harus lebih dari 0');
    }
    if (!in_array($tipe, ['masuk', 'keluar'], true)) {
        throw new Exception('Tipe mutasi tidak valid');
    }

    $stokSebelum = get_stok_gudang_now($db, $tokoId, $gudangId, $produkId);
    $stokSesudah = $tipe === 'masuk' ? ($stokSebelum + $qty) : ($stokSebelum - $qty);
    if ($tipe === 'keluar' && $stokSesudah < 0) {
        throw new Exception('Stok tidak cukup untuk mutasi keluar');
    }

    if ($tipe === 'masuk') {
        $stmt = $db->prepare("INSERT INTO stok_gudang (gudang_id,produk_id,stok,min_stok,toko_id)
                              VALUES (?,?,?,?,?)
                              ON DUPLICATE KEY UPDATE stok=stok+VALUES(stok), toko_id=VALUES(toko_id)");
        $stokDelta = $qty;
    } else {
        $stmt = $db->prepare("INSERT INTO stok_gudang (gudang_id,produk_id,stok,min_stok,toko_id)
                              VALUES (?,?,?,?,?)
                              ON DUPLICATE KEY UPDATE stok=stok-VALUES(stok), toko_id=VALUES(toko_id)");
        $stokDelta = $qty;
    }
    $min = $minStok ?? 0;
    $stmt->bind_param("iiiii", $gudangId, $produkId, $stokDelta, $min, $tokoId);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("INSERT INTO stok_mutasi (toko_id,gudang_id,produk_id,qty,stok_sebelum,stok_sesudah,tipe,referensi) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("iiiiiiss", $tokoId, $gudangId, $produkId, $qty, $stokSebelum, $stokSesudah, $tipe, $referensi);
    $stmt->execute();
    $stmt->close();

    return ['stok_sebelum' => $stokSebelum, 'stok_sesudah' => $stokSesudah];
}

function update_produk_hpp_on_masuk(Database $db, int $produkId, int $stokSebelum, int $qtyMasuk, float $hargaBeli): void {
    if ($qtyMasuk <= 0) return;
    ensure_inventory_snapshot_columns($db);

    $stmt = $db->prepare("SELECT hpp_aktif FROM produk WHERE produk_id=? LIMIT 1");
    $stmt->bind_param("i", $produkId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $hppLama = $row ? (float)$row['hpp_aktif'] : 0.0;

    $stokDasar = max(0, $stokSebelum);
    $den = $stokDasar + $qtyMasuk;
    $hppBaru = $den > 0 ? (($hppLama * $stokDasar) + ($hargaBeli * $qtyMasuk)) / $den : $hargaBeli;

    $stmt = $db->prepare("UPDATE produk SET last_harga_beli=?, hpp_aktif=? WHERE produk_id=?");
    $stmt->bind_param("ddi", $hargaBeli, $hppBaru, $produkId);
    $stmt->execute();
    $stmt->close();
}
