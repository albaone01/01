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

function resolve_auto_level_id(Database $db, int $tokoId, float $belanjaBulanan, string $thresholdColumn): ?int {
    $st = $db->prepare("
        SELECT level_id
        FROM member_level
        WHERE toko_id = ?
          AND deleted_at IS NULL
          AND {$thresholdColumn} <= ?
        ORDER BY {$thresholdColumn} DESC, level_id DESC
        LIMIT 1
    ");
    $st->bind_param('id', $tokoId, $belanjaBulanan);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? (int)$row['level_id'] : null;
}

function get_monthly_spending(Database $db, int $tokoId, int $pelangganId): float {
    $st = $db->prepare("
        SELECT COALESCE(SUM(total_akhir), 0) AS total_belanja
        FROM penjualan
        WHERE toko_id = ?
          AND pelanggan_id = ?
          AND DATE_FORMAT(dibuat_pada, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')
    ");
    $st->bind_param('ii', $tokoId, $pelangganId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (float)($row['total_belanja'] ?? 0);
}

$toko_id = (int)($_SESSION['toko_id'] ?? 0);
$pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);
$poin_raw = $_POST['poin'] ?? null;
$poin = ($poin_raw === null || $poin_raw === '') ? null : max(0, (int)$poin_raw);
$limit_kredit = (float)($_POST['limit_kredit'] ?? 0);
if ($limit_kredit < 0) $limit_kredit = 0;

// New fields
$tanggal_daftar = trim($_POST['tanggal_daftar'] ?? '');
// Auto set tanggal daftar jika kosong
if (empty($tanggal_daftar)) {
    $tanggal_daftar = date('Y-m-d');
}

$masa_berlaku = (int)($_POST['masa_berlaku'] ?? 0);
$exp = trim($_POST['exp'] ?? '');
$masa_tenggang = (int)($_POST['masa_tenggang'] ?? 0);
$exp_poin = trim($_POST['exp_poin'] ?? '');
$poin_awal = (int)($_POST['poin_awal'] ?? 0);
$poin_akhir = (int)($_POST['poin_akhir'] ?? 0);

if ($toko_id <= 0 || $pelanggan_id <= 0) {
    header("Location: index.php");
    exit;
}

// Pastikan pelanggan milik toko ini dan aktif.
$chk = $pos_db->prepare("SELECT pelanggan_id FROM pelanggan WHERE pelanggan_id=? AND toko_id=? AND deleted_at IS NULL LIMIT 1");
$chk->bind_param("ii", $pelanggan_id, $toko_id);
$chk->execute();
$okPelanggan = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$okPelanggan) {
    header("Location: index.php");
    exit;
}

$find = $pos_db->prepare("SELECT id, poin FROM pelanggan_toko WHERE pelanggan_id=? AND toko_id=? LIMIT 1");
$find->bind_param("ii", $pelanggan_id, $toko_id);
$find->execute();
$exist = $find->get_result()->fetch_assoc();
$find->close();
if ($poin === null) {
    $poin = $exist ? max(0, (int)($exist['poin'] ?? 0)) : 0;
}
$thresholdColumn = table_has_column($pos_db, 'member_level', 'minimal_belanja')
    ? 'minimal_belanja'
    : 'minimal_poin';
$belanjaBulanan = get_monthly_spending($pos_db, $toko_id, $pelanggan_id);
$level_id = resolve_auto_level_id($pos_db, $toko_id, $belanjaBulanan, $thresholdColumn);

if ($exist) {
    $id = (int)$exist['id'];
    $up = $pos_db->prepare("UPDATE pelanggan_toko SET level_id=?, poin=?, limit_kredit=?, tanggal_daftar=?, masa_berlaku=?, exp=?, masa_tenggang=?, exp_poin=?, poin_awal=?, poin_akhir=?, deleted_at=NULL WHERE id=?");
    $up->bind_param("iidsisisiii", $level_id, $poin, $limit_kredit, $tanggal_daftar, $masa_berlaku, $exp, $masa_tenggang, $exp_poin, $poin_awal, $poin_akhir, $id);
    $up->execute();
    $up->close();
} else {
    $ins = $pos_db->prepare("INSERT INTO pelanggan_toko (pelanggan_id, toko_id, level_id, poin, limit_kredit, tanggal_daftar, masa_berlaku, exp, masa_tenggang, exp_poin, poin_awal, poin_akhir) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $ins->bind_param("iiiidsisisii", $pelanggan_id, $toko_id, $level_id, $poin, $limit_kredit, $tanggal_daftar, $masa_berlaku, $exp, $masa_tenggang, $exp_poin, $poin_awal, $poin_akhir);
    $ins->execute();
    $ins->close();
}

header("Location: index.php");
exit;
