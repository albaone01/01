<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';

header('Content-Type: application/json');

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$promoId = (int)($_POST['promo_id'] ?? 0);
if ($tokoId <= 0 || $promoId <= 0) {
    exit(json_encode(['ok' => false, 'msg' => 'Parameter tidak valid']));
}

try {
    $pos_db->query("
        CREATE TABLE IF NOT EXISTS promo_produk (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            promo_id BIGINT NOT NULL,
            produk_id BIGINT NOT NULL,
            UNIQUE KEY uq_promo_produk (promo_id, produk_id),
            KEY idx_pp_produk (produk_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {}

$chk = $pos_db->prepare("SELECT promo_id FROM promo WHERE promo_id=? AND toko_id=? AND deleted_at IS NULL LIMIT 1");
$chk->bind_param('ii', $promoId, $tokoId);
$chk->execute();
$promo = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$promo) {
    exit(json_encode(['ok' => false, 'msg' => 'Promo tidak ditemukan']));
}

$rawIds = $_POST['produk_ids'] ?? [];
if (!is_array($rawIds)) $rawIds = [];
$produkIds = [];
foreach ($rawIds as $pid) {
    $n = (int)$pid;
    if ($n > 0) $produkIds[$n] = $n;
}
$produkIds = array_values($produkIds);

$validIds = [];
if (!empty($produkIds)) {
    $ph = implode(',', array_fill(0, count($produkIds), '?'));
    $types = str_repeat('i', count($produkIds) + 1);
    $params = array_merge([$tokoId], $produkIds);
    $stmtVal = $pos_db->prepare("SELECT produk_id FROM produk WHERE toko_id=? AND deleted_at IS NULL AND produk_id IN ($ph)");
    $stmtVal->bind_param($types, ...$params);
    $stmtVal->execute();
    $resVal = $stmtVal->get_result();
    while ($r = $resVal->fetch_assoc()) $validIds[] = (int)$r['produk_id'];
    $stmtVal->close();
}

$pos_db->begin_transaction();
try {
    $del = $pos_db->prepare("DELETE FROM promo_produk WHERE promo_id=?");
    $del->bind_param('i', $promoId);
    $del->execute();
    $del->close();

    if (!empty($validIds)) {
        $ins = $pos_db->prepare("INSERT INTO promo_produk (promo_id, produk_id) VALUES (?,?)");
        foreach ($validIds as $pid) {
            $ins->bind_param('ii', $promoId, $pid);
            $ins->execute();
        }
        $ins->close();
    }
    $pos_db->commit();
    echo json_encode(['ok' => true, 'msg' => 'Produk promo tersimpan', 'count' => count($validIds)]);
} catch (Throwable $e) {
    $pos_db->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
