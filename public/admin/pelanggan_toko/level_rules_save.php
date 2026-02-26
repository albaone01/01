<?php
require_once __DIR__ . '/../../../inc/config.php';
require_once __DIR__ . '/../../../inc/db.php';
require_once __DIR__ . '/../../../inc/auth.php';

requireLogin();
requireDevice();

function table_has_column(Database $db, string $table, string $column): bool {
    $st = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $st->bind_param('ss', $table, $column);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (bool)$row;
}

$toko_id = (int)($_SESSION['toko_id'] ?? 0);
$hasMinimalBelanja = table_has_column($pos_db, 'member_level', 'minimal_belanja');
$thresholdColumn = $hasMinimalBelanja ? 'minimal_belanja' : 'minimal_poin';
$minimalBelanja = $_POST['minimal_belanja'] ?? ($_POST['minimal_poin'] ?? []);
$diskonPersen = $_POST['diskon_persen'] ?? [];
$applyToAll = (int)($_POST['apply_to_all'] ?? 0) === 1;

if ($toko_id <= 0 || !is_array($minimalBelanja) || !is_array($diskonPersen)) {
    header('Location: index.php');
    exit;
}

$upd = $pos_db->prepare("UPDATE member_level SET {$thresholdColumn} = ?, diskon_persen = ? WHERE level_id = ? AND toko_id = ? AND deleted_at IS NULL");
foreach ($minimalBelanja as $levelId => $amount) {
    $level_id = (int)$levelId;
    $thresholdValue = $hasMinimalBelanja
        ? max(0, (float)$amount)
        : max(0, (int)$amount);
    $diskon = isset($diskonPersen[$levelId]) ? (float)$diskonPersen[$levelId] : 0.0;
    if ($diskon < 0) $diskon = 0.0;
    if ($diskon > 100) $diskon = 100.0;
    if ($level_id <= 0) {
        continue;
    }
    if ($hasMinimalBelanja) {
        $upd->bind_param('ddii', $thresholdValue, $diskon, $level_id, $toko_id);
    } else {
        $upd->bind_param('idii', $thresholdValue, $diskon, $level_id, $toko_id);
    }
    $upd->execute();
}
$upd->close();

if ($applyToAll) {
    $sync = $pos_db->prepare("
        UPDATE pelanggan_toko pt
        SET pt.level_id = (
            SELECT ml.level_id
            FROM member_level ml
            WHERE ml.toko_id = pt.toko_id
              AND ml.deleted_at IS NULL
              AND ml.{$thresholdColumn} <= COALESCE((
                    SELECT SUM(pj.total_akhir)
                    FROM penjualan pj
                    WHERE pj.toko_id = pt.toko_id
                      AND pj.pelanggan_id = pt.pelanggan_id
                      AND DATE_FORMAT(pj.dibuat_pada, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')
                ), 0)
            ORDER BY ml.{$thresholdColumn} DESC, ml.level_id DESC
            LIMIT 1
        )
        WHERE pt.toko_id = ?
          AND pt.deleted_at IS NULL
    ");
    $sync->bind_param('i', $toko_id);
    $sync->execute();
    $sync->close();
}

header('Location: index.php');
exit;
