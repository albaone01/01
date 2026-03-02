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
$scope = trim((string)($_POST['scope'] ?? ''));

if ($tokoId <= 0 || $promoId <= 0 || $scope === '') {
    exit(json_encode(['ok' => false, 'msg' => 'Parameter tidak valid']));
}

$mapCfg = [
    'umum_supplier' => [
        'table' => 'promo_supplier',
        'field' => 'supplier_id',
        'source_table' => 'supplier',
        'source_field' => 'supplier_id'
    ],
    'umum_kategori' => [
        'table' => 'promo_kategori',
        'field' => 'kategori_id',
        'source_table' => 'kategori_produk',
        'source_field' => 'kategori_id'
    ],
    'member_produk' => [
        'table' => 'promo_member_produk',
        'field' => 'produk_id',
        'source_table' => 'produk',
        'source_field' => 'produk_id'
    ],
    'member_supplier' => [
        'table' => 'promo_member_supplier',
        'field' => 'supplier_id',
        'source_table' => 'supplier',
        'source_field' => 'supplier_id'
    ],
    'member_kategori' => [
        'table' => 'promo_member_kategori',
        'field' => 'kategori_id',
        'source_table' => 'kategori_produk',
        'source_field' => 'kategori_id'
    ],
];

if (!isset($mapCfg[$scope])) {
    exit(json_encode(['ok' => false, 'msg' => 'Scope tidak valid']));
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

$chk = $pos_db->prepare("SELECT promo_id FROM promo WHERE promo_id=? AND toko_id=? AND deleted_at IS NULL LIMIT 1");
$chk->bind_param('ii', $promoId, $tokoId);
$chk->execute();
$promo = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$promo) {
    exit(json_encode(['ok' => false, 'msg' => 'Promo tidak ditemukan']));
}

$rawIds = $_POST['ref_ids'] ?? [];
if (!is_array($rawIds)) $rawIds = [];
$inputIds = [];
foreach ($rawIds as $rid) {
    $n = (int)$rid;
    if ($n > 0) $inputIds[$n] = $n;
}
$inputIds = array_values($inputIds);

$cfg = $mapCfg[$scope];
$validIds = [];

if (!empty($inputIds)) {
    $ph = implode(',', array_fill(0, count($inputIds), '?'));
    $types = str_repeat('i', count($inputIds) + 1);
    $params = array_merge([$tokoId], $inputIds);
    $sqlValid = "SELECT {$cfg['source_field']} AS ref_id
                 FROM {$cfg['source_table']}
                 WHERE toko_id=? AND deleted_at IS NULL AND {$cfg['source_field']} IN ($ph)";
    $stVal = $pos_db->prepare($sqlValid);
    $stVal->bind_param($types, ...$params);
    $stVal->execute();
    $rsVal = $stVal->get_result();
    while ($rw = $rsVal->fetch_assoc()) $validIds[] = (int)$rw['ref_id'];
    $stVal->close();
}

$table = $cfg['table'];
$field = $cfg['field'];

$pos_db->begin_transaction();
try {
    $del = $pos_db->prepare("DELETE FROM $table WHERE promo_id=?");
    $del->bind_param('i', $promoId);
    $del->execute();
    $del->close();

    if (!empty($validIds)) {
        $ins = $pos_db->prepare("INSERT INTO $table (promo_id, $field) VALUES (?,?)");
        foreach ($validIds as $id) {
            $ins->bind_param('ii', $promoId, $id);
            $ins->execute();
        }
        $ins->close();
    }

    $pos_db->commit();
    echo json_encode(['ok' => true, 'msg' => 'Mapping tersimpan', 'count' => count($validIds)]);
} catch (Throwable $e) {
    $pos_db->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
