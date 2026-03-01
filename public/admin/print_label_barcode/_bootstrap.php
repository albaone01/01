<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/csrf.php';

requireLogin();
requireDevice();

function lb_require_admin_role(): void {
    $role = (string)($_SESSION['peran'] ?? '');
    $allowed = ['owner', 'admin', 'manager', 'gudang'];
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

lb_require_admin_role();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$userId = (int)($_SESSION['pengguna_id'] ?? 0);
if ($tokoId <= 0 || $userId <= 0) {
    http_response_code(403);
    exit('Sesi tidak valid.');
}

function lb_json_response(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function lb_has_table(Database $db, string $table): bool {
    $st = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $st->bind_param('s', $table);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_assoc();
    $st->close();
    return $ok;
}

function lb_has_column(Database $db, string $table, string $column): bool {
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
    $ok = (bool)$st->get_result()->fetch_assoc();
    $st->close();
    return $ok;
}

function lb_ensure_schema(Database $db): void {
    static $ensured = false;
    if ($ensured) return;

    $db->query("
        CREATE TABLE IF NOT EXISTS produk_satuan (
            id BIGINT NOT NULL AUTO_INCREMENT,
            produk_id BIGINT NOT NULL,
            nama_satuan VARCHAR(50) NOT NULL,
            qty_dasar DECIMAL(15,4) NOT NULL DEFAULT 1,
            urutan INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_produk_satuan (produk_id, nama_satuan),
            KEY idx_produk (produk_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $db->query("
        CREATE TABLE IF NOT EXISTS print_label_job (
            job_id BIGINT NOT NULL AUTO_INCREMENT,
            toko_id BIGINT NOT NULL,
            jenis VARCHAR(40) NOT NULL,
            judul VARCHAR(140) DEFAULT NULL,
            opsi_json LONGTEXT DEFAULT NULL,
            price_tier VARCHAR(20) NOT NULL DEFAULT 'ecer',
            total_item INT NOT NULL DEFAULT 0,
            total_copy INT NOT NULL DEFAULT 0,
            dibuat_oleh BIGINT DEFAULT NULL,
            dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (job_id),
            KEY idx_plj_toko_jenis_waktu (toko_id, jenis, dibuat_pada),
            KEY idx_plj_toko_deleted (toko_id, deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!lb_has_column($db, 'print_label_job', 'price_tier')) {
        $db->query("ALTER TABLE print_label_job ADD COLUMN price_tier VARCHAR(20) NOT NULL DEFAULT 'ecer' AFTER opsi_json");
    }

    $db->query("
        CREATE TABLE IF NOT EXISTS print_label_job_item (
            item_id BIGINT NOT NULL AUTO_INCREMENT,
            job_id BIGINT NOT NULL,
            toko_id BIGINT NOT NULL,
            produk_id BIGINT NOT NULL,
            nama_produk VARCHAR(180) NOT NULL,
            sku VARCHAR(80) DEFAULT NULL,
            barcode VARCHAR(120) DEFAULT NULL,
            harga DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            qty_copy INT NOT NULL DEFAULT 1,
            dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (item_id),
            KEY idx_plji_job (job_id),
            KEY idx_plji_toko_produk (toko_id, produk_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $ensured = true;
}

function lb_fetch_products(Database $db, int $tokoId, string $q = '', int $limit = 600): array {
    $where = ["p.toko_id = ?", "p.deleted_at IS NULL"];
    $types = 'i';
    $params = [$tokoId];

    $q = trim($q);
    if ($q !== '') {
        $where[] = "(p.nama_produk LIKE CONCAT('%',?,'%') OR p.sku LIKE CONCAT('%',?,'%') OR p.barcode LIKE CONCAT('%',?,'%'))";
        $types .= 'sss';
        array_push($params, $q, $q, $q);
    }

    $limit = max(50, min(1000, $limit));
    $types .= 'i';
    $params[] = $limit;

    $sql = "
        SELECT
            p.produk_id, p.nama_produk, p.sku, p.barcode, p.satuan, p.harga_modal, p.aktif,
            COALESCE(pe.harga_jual, 0) AS harga_ecer,
            COALESCE(pg.harga_jual, 0) AS harga_grosir,
            COALESCE(pr.harga_jual, 0) AS harga_reseller,
            COALESCE(pm.harga_jual, 0) AS harga_member,
            COALESCE(sg.stok_total, 0) AS stok_total
        FROM produk p
        LEFT JOIN produk_harga pe ON pe.produk_id = p.produk_id AND pe.tipe = 'ecer'
        LEFT JOIN produk_harga pg ON pg.produk_id = p.produk_id AND pg.tipe = 'grosir'
        LEFT JOIN produk_harga pr ON pr.produk_id = p.produk_id AND pr.tipe = 'reseller'
        LEFT JOIN produk_harga pm ON pm.produk_id = p.produk_id AND pm.tipe = 'member'
        LEFT JOIN (
            SELECT produk_id, SUM(stok) AS stok_total
            FROM stok_gudang
            GROUP BY produk_id
        ) sg ON sg.produk_id = p.produk_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.nama_produk ASC
        LIMIT ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$rows) return [];

    $productIds = array_map(static fn($r) => (int)$r['produk_id'], $rows);
    $unitMap = [];
    $ph = implode(',', array_fill(0, count($productIds), '?'));
    $t = str_repeat('i', count($productIds));
    $stUnits = $db->prepare("
        SELECT produk_id, nama_satuan, qty_dasar
        FROM produk_satuan
        WHERE produk_id IN ($ph)
        ORDER BY produk_id ASC, urutan ASC, id ASC
    ");
    $stUnits->bind_param($t, ...$productIds);
    $stUnits->execute();
    $unitRows = $stUnits->get_result()->fetch_all(MYSQLI_ASSOC);
    $stUnits->close();

    foreach ($rows as &$row) {
        $pid = (int)$row['produk_id'];
        $base = trim((string)($row['satuan'] ?? ''));
        if ($base === '') $base = 'PCS';
        $unitMap[$pid] = [[
            'key' => $base,
            'label' => $base,
            'multiplier' => 1.0,
        ]];
    }
    unset($row);

    foreach ($unitRows as $u) {
        $pid = (int)$u['produk_id'];
        if (!isset($unitMap[$pid])) continue;
        $nama = trim((string)($u['nama_satuan'] ?? ''));
        if ($nama === '') continue;
        $mult = (float)($u['qty_dasar'] ?? 0);
        if ($mult <= 0) $mult = 1.0;

        $exists = false;
        foreach ($unitMap[$pid] as $ex) {
            if (strcasecmp((string)$ex['label'], $nama) === 0) {
                $exists = true;
                break;
            }
        }
        if ($exists) continue;
        $unitMap[$pid][] = [
            'key' => $nama,
            'label' => $nama,
            'multiplier' => $mult,
        ];
    }

    foreach ($rows as &$row) {
        $pid = (int)$row['produk_id'];
        $units = $unitMap[$pid] ?? [];
        $row['unit_default'] = (string)($units[0]['label'] ?? 'PCS');
        $unitJson = json_encode($units, JSON_UNESCAPED_UNICODE);
        $row['unit_options_json'] = $unitJson !== false ? $unitJson : '[]';
    }
    unset($row);

    return $rows;
}

function lb_pick_price(array $row, string $priceTier): float {
    switch ($priceTier) {
        case 'modal': return (float)($row['harga_modal'] ?? 0);
        case 'grosir': return (float)($row['harga_grosir'] ?? 0);
        case 'reseller': return (float)($row['harga_reseller'] ?? 0);
        case 'member': return (float)($row['harga_member'] ?? 0);
        case 'ecer':
        default: return (float)($row['harga_ecer'] ?? 0);
    }
}

function lb_save_print_job(
    Database $db,
    int $tokoId,
    int $userId,
    string $jenis,
    string $priceTier,
    string $judul,
    array $opsi,
    array $items
): array {
    $allowedJenis = [
        'barcode_barang',
        'price_card_label',
        'price_card_label_single',
        'price_card_label_multi',
        'price_card_folio'
    ];
    $allowedTier = ['ecer', 'grosir', 'reseller', 'member', 'modal'];
    if (!in_array($jenis, $allowedJenis, true)) {
        throw new RuntimeException('Jenis cetak tidak valid.');
    }
    if (!in_array($priceTier, $allowedTier, true)) {
        $priceTier = 'ecer';
    }

    $normalized = [];
    foreach ($items as $it) {
        $pid = (int)($it['produk_id'] ?? 0);
        $qty = (int)($it['qty_copy'] ?? 0);
        if ($pid <= 0 || $qty <= 0) continue;
        $qty = min(500, $qty);
        $unitLabel = trim((string)($it['unit_label'] ?? ''));
        $mult = (float)($it['unit_multiplier'] ?? 1);
        if ($mult <= 0) $mult = 1;
        if ($mult > 10000) $mult = 10000;
        $key = $pid . '|' . strtolower($unitLabel) . '|' . $mult;
        if (!isset($normalized[$key])) {
            $normalized[$key] = [
                'produk_id' => $pid,
                'qty_copy' => 0,
                'unit_label' => $unitLabel,
                'unit_multiplier' => $mult,
            ];
        }
        $normalized[$key]['qty_copy'] += $qty;
    }
    if (!$normalized) {
        throw new RuntimeException('Tidak ada item yang dipilih.');
    }

    $productIds = array_values(array_unique(array_map(static fn($n) => (int)$n['produk_id'], $normalized)));
    $ph = implode(',', array_fill(0, count($productIds), '?'));
    $types = 'i' . str_repeat('i', count($productIds));
    $params = array_merge([$tokoId], $productIds);

    $sql = "
        SELECT
            p.produk_id, p.nama_produk, p.sku, p.barcode, p.harga_modal,
            COALESCE(pe.harga_jual, 0) AS harga_ecer,
            COALESCE(pg.harga_jual, 0) AS harga_grosir,
            COALESCE(pr.harga_jual, 0) AS harga_reseller,
            COALESCE(pm.harga_jual, 0) AS harga_member
        FROM produk p
        LEFT JOIN produk_harga pe ON pe.produk_id = p.produk_id AND pe.tipe = 'ecer'
        LEFT JOIN produk_harga pg ON pg.produk_id = p.produk_id AND pg.tipe = 'grosir'
        LEFT JOIN produk_harga pr ON pr.produk_id = p.produk_id AND pr.tipe = 'reseller'
        LEFT JOIN produk_harga pm ON pm.produk_id = p.produk_id AND pm.tipe = 'member'
        WHERE p.toko_id = ?
          AND p.deleted_at IS NULL
          AND p.produk_id IN ($ph)
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $productMap = [];
    foreach ($rows as $row) {
        $productMap[(int)$row['produk_id']] = $row;
    }

    $jobItems = [];
    $totalCopy = 0;
    foreach ($normalized as $bundle) {
        $pid = (int)$bundle['produk_id'];
        $qtyCopy = (int)$bundle['qty_copy'];
        $unitLabel = trim((string)$bundle['unit_label']);
        $mult = (float)$bundle['unit_multiplier'];
        if (!isset($productMap[$pid])) continue;
        $row = $productMap[$pid];
        $harga = lb_pick_price($row, $priceTier) * $mult;
        $namaProduk = (string)$row['nama_produk'];
        if ($unitLabel !== '') {
            $namaProduk .= ' [' . $unitLabel . ']';
        }
        $jobItems[] = [
            'produk_id' => $pid,
            'nama_produk' => $namaProduk,
            'sku' => (string)($row['sku'] ?? ''),
            'barcode' => (string)($row['barcode'] ?? ''),
            'harga' => $harga,
            'qty_copy' => $qtyCopy,
        ];
        $totalCopy += $qtyCopy;
    }

    if (!$jobItems) {
        throw new RuntimeException('Produk tidak ditemukan untuk toko aktif.');
    }

    $totalItem = count($jobItems);
    $opsiJson = json_encode($opsi, JSON_UNESCAPED_UNICODE);
    if ($opsiJson === false) $opsiJson = '{}';
    $judul = trim($judul);
    if ($judul === '') $judul = 'Batch Cetak Label';

    $db->begin_transaction();
    try {
        $stJob = $db->prepare("
            INSERT INTO print_label_job
            (toko_id, jenis, judul, opsi_json, price_tier, total_item, total_copy, dibuat_oleh)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stJob->bind_param(
            'issssiii',
            $tokoId,
            $jenis,
            $judul,
            $opsiJson,
            $priceTier,
            $totalItem,
            $totalCopy,
            $userId
        );
        $stJob->execute();
        $jobId = (int)$db->insertId();
        $stJob->close();

        $stItem = $db->prepare("
            INSERT INTO print_label_job_item
            (job_id, toko_id, produk_id, nama_produk, sku, barcode, harga, qty_copy)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($jobItems as $it) {
            $pid = (int)$it['produk_id'];
            $nama = (string)$it['nama_produk'];
            $sku = (string)$it['sku'];
            $barcode = (string)$it['barcode'];
            $harga = (float)$it['harga'];
            $qty = (int)$it['qty_copy'];
            $stItem->bind_param('iiisssdi', $jobId, $tokoId, $pid, $nama, $sku, $barcode, $harga, $qty);
            $stItem->execute();
        }
        $stItem->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }

    return [
        'job_id' => $jobId,
        'total_item' => $totalItem,
        'total_copy' => $totalCopy,
    ];
}

function lb_fetch_recent_jobs(Database $db, int $tokoId, string $jenis, int $limit = 12): array {
    $limit = max(5, min(30, $limit));
    $st = $db->prepare("
        SELECT job_id, judul, price_tier, total_item, total_copy, dibuat_pada
        FROM print_label_job
        WHERE toko_id = ?
          AND jenis = ?
          AND deleted_at IS NULL
        ORDER BY job_id DESC
        LIMIT ?
    ");
    $st->bind_param('isi', $tokoId, $jenis, $limit);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

lb_ensure_schema($pos_db);
