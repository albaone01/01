<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';

header('Content-Type: application/json');

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$promoId = (int)($_GET['promo_id'] ?? 0);
$scope = trim((string)($_GET['scope'] ?? ''));

if ($tokoId <= 0 || $promoId <= 0 || $scope === '') {
    exit(json_encode(['ok' => false, 'msg' => 'Parameter tidak valid']));
}

$mapCfg = [
    'umum_supplier' => ['table' => 'promo_supplier', 'field' => 'supplier_id'],
    'umum_kategori' => ['table' => 'promo_kategori', 'field' => 'kategori_id'],
    'member_produk' => ['table' => 'promo_member_produk', 'field' => 'produk_id'],
    'member_supplier' => ['table' => 'promo_member_supplier', 'field' => 'supplier_id'],
    'member_kategori' => ['table' => 'promo_member_kategori', 'field' => 'kategori_id'],
];

if (!isset($mapCfg[$scope])) {
    exit(json_encode(['ok' => false, 'msg' => 'Scope tidak valid']));
}

$chk = $pos_db->prepare("SELECT promo_id FROM promo WHERE promo_id=? AND toko_id=? AND deleted_at IS NULL LIMIT 1");
$chk->bind_param('ii', $promoId, $tokoId);
$chk->execute();
$promo = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$promo) {
    exit(json_encode(['ok' => false, 'msg' => 'Promo tidak ditemukan']));
}

try {
    $pos_db->query("
        CREATE TABLE IF NOT EXISTS promo_supplier (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            promo_id BIGINT NOT NULL,
            supplier_id BIGINT NOT NULL,
            UNIQUE KEY uq_promo_supplier (promo_id, supplier_id),
            KEY idx_ps_supplier (supplier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pos_db->query("
        CREATE TABLE IF NOT EXISTS promo_kategori (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            promo_id BIGINT NOT NULL,
            kategori_id BIGINT NOT NULL,
            UNIQUE KEY uq_promo_kategori (promo_id, kategori_id),
            KEY idx_pk_kategori (kategori_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pos_db->query("
        CREATE TABLE IF NOT EXISTS promo_member_produk (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            promo_id BIGINT NOT NULL,
            produk_id BIGINT NOT NULL,
            UNIQUE KEY uq_promo_member_produk (promo_id, produk_id),
            KEY idx_pmp_produk (produk_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pos_db->query("
        CREATE TABLE IF NOT EXISTS promo_member_supplier (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            promo_id BIGINT NOT NULL,
            supplier_id BIGINT NOT NULL,
            UNIQUE KEY uq_promo_member_supplier (promo_id, supplier_id),
            KEY idx_pms_supplier (supplier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pos_db->query("
        CREATE TABLE IF NOT EXISTS promo_member_kategori (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            promo_id BIGINT NOT NULL,
            kategori_id BIGINT NOT NULL,
            UNIQUE KEY uq_promo_member_kategori (promo_id, kategori_id),
            KEY idx_pmk_kategori (kategori_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Throwable $e) {}

$table = $mapCfg[$scope]['table'];
$field = $mapCfg[$scope]['field'];
$sql = "SELECT $field AS ref_id FROM $table WHERE promo_id=?";
$st = $pos_db->prepare($sql);
$st->bind_param('i', $promoId);
$st->execute();
$res = $st->get_result();
$ids = [];
while ($r = $res->fetch_assoc()) $ids[] = (int)$r['ref_id'];
$st->close();

echo json_encode(['ok' => true, 'ref_ids' => $ids]);
