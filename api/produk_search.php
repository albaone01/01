<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/csrf.php';

header('Content-Type: application/json');

// Guard
requireLogin();
requireDevice();

$tokoId = $_SESSION['toko_id'] ?? 0;
$gudangId = (int)($_SESSION['gudang_id'] ?? 0);
if (!$tokoId) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Toko tidak ditemukan di sesi']));
}
if ($gudangId <= 0) {
    $stGud = $pos_db->prepare("SELECT gudang_id FROM gudang WHERE toko_id=? AND aktif=1 AND deleted_at IS NULL ORDER BY CASE WHEN nama_gudang='Gudang Utama' THEN 0 ELSE 1 END, gudang_id LIMIT 1");
    $stGud->bind_param('i', $tokoId);
    $stGud->execute();
    $rwGud = $stGud->get_result()->fetch_assoc();
    $stGud->close();
    $gudangId = $rwGud ? (int)$rwGud['gudang_id'] : 0;
}
if ($gudangId <= 0) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Gudang aktif belum dipilih']));
}

$q = trim($_GET['q'] ?? '');
$limit = 20;

$sqlSelect = "SELECT p.produk_id, p.nama_produk, p.sku, p.barcode, p.satuan,
                     p.pajak_persen, p.harga_modal, COALESCE(p.min_stok,0) AS min_stok, COALESCE(p.max_stok,0) AS max_stok,
                     COALESCE(p.is_jasa,0) AS is_jasa, COALESCE(p.is_konsinyasi,0) AS is_konsinyasi,
                     COALESCE(sg.stok,0) AS stok,
                     COALESCE(h_ecer.harga_jual,0) AS harga_ecer,
                     COALESCE(h_grosir.harga_jual,0) AS harga_grosir,
                     COALESCE(h_reseller.harga_jual,0) AS harga_reseller,
                     COALESCE(h_member.harga_jual,0) AS harga_member
              FROM produk p
              LEFT JOIN stok_gudang sg ON sg.produk_id = p.produk_id AND sg.gudang_id = ?
              LEFT JOIN produk_harga h_ecer ON h_ecer.produk_id = p.produk_id AND h_ecer.tipe='ecer'
              LEFT JOIN produk_harga h_grosir ON h_grosir.produk_id = p.produk_id AND h_grosir.tipe='grosir'
              LEFT JOIN produk_harga h_reseller ON h_reseller.produk_id = p.produk_id AND h_reseller.tipe='reseller'
              LEFT JOIN produk_harga h_member ON h_member.produk_id = p.produk_id AND h_member.tipe='member'
              WHERE p.toko_id=? AND p.aktif=1 AND p.deleted_at IS NULL";

$data = [];
if ($q === '') {
    $stmt = $pos_db->prepare($sqlSelect . " ORDER BY p.nama_produk LIMIT ?");
    $stmt->bind_param('iii', $gudangId, $tokoId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    // Jalur cepat scanner: exact lookup barcode/SKU dulu agar hasil muncul lebih responsif.
    $stmtExact = $pos_db->prepare($sqlSelect . " AND (p.barcode = ? OR p.sku = ?) ORDER BY p.nama_produk LIMIT 1");
    $stmtExact->bind_param('iiss', $gudangId, $tokoId, $q, $q);
    $stmtExact->execute();
    $resExact = $stmtExact->get_result();
    $data = $resExact ? $resExact->fetch_all(MYSQLI_ASSOC) : [];
    $stmtExact->close();

    if (empty($data)) {
        $like = "%{$q}%";
        $stmt = $pos_db->prepare($sqlSelect . " AND (p.sku LIKE ? OR p.nama_produk LIKE ?) ORDER BY p.nama_produk LIMIT ?");
        $stmt->bind_param('iissi', $gudangId, $tokoId, $like, $like, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}

if (!empty($data)) {
    $ids = array_map(static fn($r) => (int)$r['produk_id'], $data);
    $ids = array_values(array_unique(array_filter($ids, static fn($x) => $x > 0)));
    if (!empty($ids)) {
        $chk = $pos_db->query("SHOW TABLES LIKE 'produk_satuan'");
        if ($chk && $chk->num_rows > 0) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $tp = str_repeat('i', count($ids));
            $satStmt = $pos_db->prepare("SELECT produk_id, nama_satuan, qty_dasar, urutan FROM produk_satuan WHERE produk_id IN ($ph) ORDER BY produk_id, urutan ASC, id ASC");
            $satStmt->bind_param($tp, ...$ids);
            $satStmt->execute();
            $rSat = $satStmt->get_result();
            $mapSat = [];
            while ($rw = $rSat->fetch_assoc()) {
                $pid = (int)$rw['produk_id'];
                if (!isset($mapSat[$pid])) $mapSat[$pid] = [];
                $mapSat[$pid][] = [
                    'nama_satuan' => (string)$rw['nama_satuan'],
                    'qty_dasar' => (float)$rw['qty_dasar'],
                    'urutan' => (int)$rw['urutan'],
                ];
            }
            $satStmt->close();
            foreach ($data as &$row) {
                $pid = (int)$row['produk_id'];
                $row['multi_satuan'] = $mapSat[$pid] ?? [];
                if (empty($row['multi_satuan'])) {
                    $row['multi_satuan'] = [[
                        'nama_satuan' => (string)($row['satuan'] ?: 'PCS'),
                        'qty_dasar' => 1.0,
                        'urutan' => 1,
                    ]];
                }
            }
            unset($row);
        }
    }
}

echo json_encode(['ok' => true, 'data' => $data]);
