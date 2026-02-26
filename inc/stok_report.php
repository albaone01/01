<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function validate_stok_period_input(string $from, string $to): void {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        throw new Exception('Parameter from/to wajib format YYYY-MM-DD');
    }
    if ($from > $to) {
        throw new Exception('Tanggal from tidak boleh lebih besar dari to');
    }
}

function get_stok_period_report(Database $db, int $tokoId, string $from, string $to, int $gudangId = 0, int $produkId = 0): array {
    validate_stok_period_input($from, $to);

    $filterGudangCur = $gudangId > 0 ? " AND gudang_id=$gudangId" : "";
    $filterGudangMut = $gudangId > 0 ? " AND m.gudang_id=$gudangId" : "";
    $filterProdukP   = $produkId > 0 ? " AND p.produk_id=$produkId" : "";

    $sql = "
    SELECT
        p.produk_id,
        p.nama_produk,
        COALESCE(cur.stok_current,0) AS stok_current,
        COALESCE(aft.delta_after_to,0) AS delta_after_to,
        COALESCE(per.masuk_periode,0) AS masuk_periode,
        COALESCE(per.keluar_periode,0) AS keluar_periode
    FROM produk p
    LEFT JOIN (
        SELECT produk_id, SUM(stok) AS stok_current
        FROM stok_gudang
        WHERE toko_id=? $filterGudangCur
        GROUP BY produk_id
    ) cur ON cur.produk_id = p.produk_id
    LEFT JOIN (
        SELECT m.produk_id,
               SUM(CASE WHEN m.tipe='masuk' THEN m.qty ELSE -m.qty END) AS delta_after_to
        FROM stok_mutasi m
        WHERE m.toko_id=? $filterGudangMut AND DATE(m.dibuat_pada) > ?
        GROUP BY m.produk_id
    ) aft ON aft.produk_id = p.produk_id
    LEFT JOIN (
        SELECT m.produk_id,
               SUM(CASE WHEN m.tipe='masuk' THEN m.qty ELSE 0 END) AS masuk_periode,
               SUM(CASE WHEN m.tipe='keluar' THEN m.qty ELSE 0 END) AS keluar_periode
        FROM stok_mutasi m
        WHERE m.toko_id=? $filterGudangMut AND DATE(m.dibuat_pada) BETWEEN ? AND ?
        GROUP BY m.produk_id
    ) per ON per.produk_id = p.produk_id
    WHERE p.toko_id=? AND p.deleted_at IS NULL $filterProdukP
      AND (cur.produk_id IS NOT NULL OR aft.produk_id IS NOT NULL OR per.produk_id IS NOT NULL)
    ORDER BY p.nama_produk
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param("iisissi", $tokoId, $tokoId, $to, $tokoId, $from, $to, $tokoId);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    $totAwal = 0;
    $totMasuk = 0;
    $totKeluar = 0;
    $totAkhir = 0;
    while ($r = $res->fetch_assoc()) {
        $stokAkhir = (int)$r['stok_current'] - (int)$r['delta_after_to'];
        $stokAwal  = $stokAkhir - ((int)$r['masuk_periode'] - (int)$r['keluar_periode']);
        $row = [
            'produk_id' => (int)$r['produk_id'],
            'nama_produk' => $r['nama_produk'],
            'stok_awal' => $stokAwal,
            'masuk' => (int)$r['masuk_periode'],
            'keluar' => (int)$r['keluar_periode'],
            'stok_akhir' => $stokAkhir,
        ];
        $data[] = $row;
        $totAwal += $row['stok_awal'];
        $totMasuk += $row['masuk'];
        $totKeluar += $row['keluar'];
        $totAkhir += $row['stok_akhir'];
    }
    $stmt->close();

    return [
        'filter' => ['from' => $from, 'to' => $to, 'gudang_id' => $gudangId, 'produk_id' => $produkId],
        'summary' => ['stok_awal' => $totAwal, 'masuk' => $totMasuk, 'keluar' => $totKeluar, 'stok_akhir' => $totAkhir],
        'data' => $data,
    ];
}
