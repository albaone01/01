<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/lokasi_rak.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if ($tokoId <= 0) exit(json_encode(['ok' => false, 'msg' => 'Sesi toko tidak valid']));

$id = (int)($_POST['id'] ?? 0);
$gudangId = (int)($_POST['gudang_id'] ?? 0);
$produkId = (int)($_POST['produk_id'] ?? 0);
$lokasiId = (int)($_POST['lokasi_id'] ?? 0);
$qtyDisplay = (int)($_POST['qty_display'] ?? 0);
$minDisplay = (int)($_POST['min_display'] ?? 0);
$maxDisplay = (int)($_POST['max_display'] ?? 0);
$isDefault = isset($_POST['is_default']) && (int)$_POST['is_default'] === 1 ? 1 : 0;

if ($gudangId <= 0 || $produkId <= 0 || $lokasiId <= 0) {
    exit(json_encode(['ok' => false, 'msg' => 'Gudang, produk, dan lokasi wajib dipilih']));
}
if ($qtyDisplay < 0 || $minDisplay < 0 || $maxDisplay < 0) {
    exit(json_encode(['ok' => false, 'msg' => 'Nilai qty/min/max tidak boleh negatif']));
}
if ($maxDisplay > 0 && $maxDisplay < $minDisplay) {
    exit(json_encode(['ok' => false, 'msg' => 'Max display tidak boleh lebih kecil dari min display']));
}

try {
    lokasi_rak_ensure_schema($pos_db);

    $stP = $pos_db->prepare("SELECT produk_id FROM produk WHERE produk_id=? AND toko_id=? AND deleted_at IS NULL LIMIT 1");
    $stP->bind_param("ii", $produkId, $tokoId);
    $stP->execute();
    $okP = $stP->get_result()->fetch_assoc();
    $stP->close();
    if (!$okP) exit(json_encode(['ok' => false, 'msg' => 'Produk tidak valid']));

    $stL = $pos_db->prepare("
        SELECT lokasi_id
        FROM lokasi_rak
        WHERE lokasi_id=? AND toko_id=? AND gudang_id=? AND deleted_at IS NULL
        LIMIT 1
    ");
    $stL->bind_param("iii", $lokasiId, $tokoId, $gudangId);
    $stL->execute();
    $okL = $stL->get_result()->fetch_assoc();
    $stL->close();
    if (!$okL) exit(json_encode(['ok' => false, 'msg' => 'Lokasi rak tidak valid untuk gudang ini']));

    $pos_db->begin_transaction();

    if ($isDefault === 1) {
        $clr = $pos_db->prepare("
            UPDATE produk_lokasi_rak
            SET is_default=0
            WHERE toko_id=? AND gudang_id=? AND produk_id=? AND deleted_at IS NULL
        ");
        $clr->bind_param("iii", $tokoId, $gudangId, $produkId);
        $clr->execute();
        $clr->close();
    }

    if ($id > 0) {
        $stmt = $pos_db->prepare("
            UPDATE produk_lokasi_rak
            SET gudang_id=?, produk_id=?, lokasi_id=?, qty_display=?, min_display=?, max_display=?, is_default=?
            WHERE id=? AND toko_id=? AND deleted_at IS NULL
        ");
        $stmt->bind_param("iiiiiiiii", $gudangId, $produkId, $lokasiId, $qtyDisplay, $minDisplay, $maxDisplay, $isDefault, $id, $tokoId);
        $stmt->execute();
        $stmt->close();
    } else {
        $dup = $pos_db->prepare("
            SELECT id
            FROM produk_lokasi_rak
            WHERE toko_id=? AND gudang_id=? AND produk_id=? AND lokasi_id=? AND deleted_at IS NULL
            LIMIT 1
        ");
        $dup->bind_param("iiii", $tokoId, $gudangId, $produkId, $lokasiId);
        $dup->execute();
        $rowDup = $dup->get_result()->fetch_assoc();
        $dup->close();

        if ($rowDup) {
            $dupId = (int)$rowDup['id'];
            $stmt = $pos_db->prepare("
                UPDATE produk_lokasi_rak
                SET qty_display=?, min_display=?, max_display=?, is_default=?
                WHERE id=? AND toko_id=?
            ");
            $stmt->bind_param("iiiiii", $qtyDisplay, $minDisplay, $maxDisplay, $isDefault, $dupId, $tokoId);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $pos_db->prepare("
                INSERT INTO produk_lokasi_rak
                (toko_id,gudang_id,produk_id,lokasi_id,qty_display,min_display,max_display,is_default)
                VALUES (?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param("iiiiiiii", $tokoId, $gudangId, $produkId, $lokasiId, $qtyDisplay, $minDisplay, $maxDisplay, $isDefault);
            $stmt->execute();
            $stmt->close();
        }
    }

    $pos_db->commit();
    echo json_encode(['ok' => true, 'msg' => 'Penempatan produk tersimpan']);
} catch (Throwable $e) {
    $pos_db->rollback();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
