<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

header('Content-Type: application/json');

$toko_id = (int)($_SESSION['toko_id'] ?? 0);
$pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);

if (!$toko_id) {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

$kode_pelanggan = trim($_POST['kode_pelanggan'] ?? '');
$nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '');
$telepon = trim($_POST['telepon'] ?? '');
$alamat = trim($_POST['alamat'] ?? '');
$jenis_customer = trim($_POST['jenis_customer'] ?? '');
$flat_diskon = (float)($_POST['flat_diskon'] ?? 0);

// Membership fields
$tanggal_daftar = trim($_POST['tanggal_daftar'] ?? '');
$masa_berlaku = (int)($_POST['masa_berlaku'] ?? 1);
$masa_tenggang = (int)($_POST['masa_tenggang'] ?? 7);
$poin_awal = (int)($_POST['poin_awal'] ?? 0);
$poin = (int)($_POST['poin'] ?? 0);

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
    if ($pelangganId <= 0) {
        return 0.0;
    }
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

if ($nama_pelanggan === '') {
    echo json_encode(['ok' => false, 'msg' => 'Nama pelanggan wajib diisi']);
    exit;
}

// Set default tanggal_daftar if empty
if ($tanggal_daftar === '') {
    $tanggal_daftar = date('Y-m-d');
}

$thresholdColumn = table_has_column($pos_db, 'member_level', 'minimal_belanja')
    ? 'minimal_belanja'
    : 'minimal_poin';
$belanjaBulanan = get_monthly_spending($pos_db, $toko_id, $pelanggan_id);
$level_id = resolve_auto_level_id($pos_db, $toko_id, $belanjaBulanan, $thresholdColumn);

// Calculate exp date
$exp_date = '';
if ($masa_berlaku > 0) {
    $exp_date = date('Y-m-d', strtotime("+{$masa_berlaku} years", strtotime($tanggal_daftar)));
}

// Calculate exp_poin (1 year from tanggal_daftar by default)
$exp_poin = date('Y-m-d', strtotime("+1 year", strtotime($tanggal_daftar)));

if ($pelanggan_id > 0) {
    // Update existing pelanggan
    $stmt = $pos_db->prepare("
        UPDATE pelanggan 
        SET kode_pelanggan = ?, nama_pelanggan = ?, telepon = ?, 
            alamat = ?, jenis_customer = ?, flat_diskon = ?
        WHERE pelanggan_id = ? AND toko_id = ?
    ");
    $stmt->bind_param("sssssdii", $kode_pelanggan, $nama_pelanggan, $telepon, $alamat, $jenis_customer, $flat_diskon, $pelanggan_id, $toko_id);
    $stmt->execute();
    $stmt->close();
    $new_id = $pelanggan_id;
    
    // Check if pelanggan_toko exists
    $stmt = $pos_db->prepare("SELECT id FROM pelanggan_toko WHERE pelanggan_id = ? AND toko_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("ii", $pelanggan_id, $toko_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pt_exists = $result->num_rows > 0;
    $stmt->close();
    
    if ($pt_exists) {
        // Update pelanggan_toko
        $stmt = $pos_db->prepare("
            UPDATE pelanggan_toko 
            SET level_id = ?, tanggal_daftar = ?, masa_berlaku = ?, exp = ?, 
                masa_tenggang = ?, exp_poin = ?, poin_awal = ?, poin = ?
            WHERE pelanggan_id = ? AND toko_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param("isisisiiii", $level_id, $tanggal_daftar, $masa_berlaku, $exp_date, $masa_tenggang, $exp_poin, $poin_awal, $poin, $pelanggan_id, $toko_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new pelanggan_toko
        $stmt = $pos_db->prepare("
            INSERT INTO pelanggan_toko (pelanggan_id, toko_id, level_id, tanggal_daftar, masa_berlaku, exp, masa_tenggang, exp_poin, poin_awal, poin)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiisisisii", $pelanggan_id, $toko_id, $level_id, $tanggal_daftar, $masa_berlaku, $exp_date, $masa_tenggang, $exp_poin, $poin_awal, $poin);
        $stmt->execute();
        $stmt->close();
    }
} else {
    // Insert new pelanggan
    $stmt = $pos_db->prepare("
        INSERT INTO pelanggan (toko_id, kode_pelanggan, nama_pelanggan, telepon, alamat, jenis_customer, flat_diskon)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssssd", $toko_id, $kode_pelanggan, $nama_pelanggan, $telepon, $alamat, $jenis_customer, $flat_diskon);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();

    $belanjaBulanan = get_monthly_spending($pos_db, $toko_id, $new_id);
    $level_id = resolve_auto_level_id($pos_db, $toko_id, $belanjaBulanan, $thresholdColumn);
    
    // Auto create pelanggan_toko entry
    $stmt = $pos_db->prepare("
        INSERT INTO pelanggan_toko (pelanggan_id, toko_id, level_id, tanggal_daftar, masa_berlaku, exp, masa_tenggang, exp_poin, poin_awal, poin)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiisisisii", $new_id, $toko_id, $level_id, $tanggal_daftar, $masa_berlaku, $exp_date, $masa_tenggang, $exp_poin, $poin_awal, $poin);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['ok' => true, 'id' => $new_id]);
