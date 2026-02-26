<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/image_helper.php';
require_once '../inc/csrf.php';
require_once '../inc/inventory.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);
ob_start();
set_error_handler(function($severity, $message, $file, $line){
    throw new ErrorException($message, 0, $severity, $file, $line);
});
csrf_protect_json();

function fail_json(string $msg): void {
    ob_clean();
    echo json_encode(['ok' => false, 'msg' => $msg]);
    exit;
}

function find_default_gudang(Database $db, int $tokoId): int {
    $sessionGudang = (int)($_SESSION['gudang_id'] ?? 0);
    if ($sessionGudang > 0) {
        $stmt = $db->prepare("SELECT gudang_id FROM gudang WHERE toko_id=? AND gudang_id=? AND aktif=1 AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param("ii", $tokoId, $sessionGudang);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) return (int)$row['gudang_id'];
    }

    $stmt = $db->prepare("SELECT gudang_id FROM gudang WHERE toko_id=? AND aktif=1 AND deleted_at IS NULL ORDER BY CASE WHEN nama_gudang='Gudang Utama' THEN 0 ELSE 1 END, gudang_id LIMIT 1");
    $stmt->bind_param("i", $tokoId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['gudang_id'] : 0;
}

function ensure_reseller_price_type(Database $db): void {
    $rs = $db->query("SHOW COLUMNS FROM produk_harga LIKE 'tipe'");
    $col = $rs ? $rs->fetch_assoc() : null;
    if (!$col || !isset($col['Type'])) {
        throw new Exception('Kolom tipe pada produk_harga tidak ditemukan');
    }
    $type = strtolower((string)$col['Type']);
    if (strpos($type, "'reseller'") === false) {
        $db->query("ALTER TABLE produk_harga MODIFY tipe ENUM('ecer','grosir','member','reseller') NOT NULL DEFAULT 'ecer'");
    }
}

function ensure_produk_flags_columns(Database $db): void {
    $need = [];
    $checkJasa = $db->query("SHOW COLUMNS FROM produk LIKE 'is_jasa'");
    if (!$checkJasa || !$checkJasa->fetch_assoc()) $need[] = "ADD COLUMN is_jasa TINYINT(1) NOT NULL DEFAULT 0";
    $checkKon = $db->query("SHOW COLUMNS FROM produk LIKE 'is_konsinyasi'");
    if (!$checkKon || !$checkKon->fetch_assoc()) $need[] = "ADD COLUMN is_konsinyasi TINYINT(1) NOT NULL DEFAULT 0";
    if ($need) {
        $db->query("ALTER TABLE produk " . implode(', ', $need));
    }
}

function ensure_produk_inventory_columns(Database $db): void {
    ensure_inventory_snapshot_columns($db);
}

function ensure_produk_max_stok_column(Database $db): void {
    $chk = $db->query("SHOW COLUMNS FROM produk LIKE 'max_stok'");
    if (!$chk || !$chk->fetch_assoc()) {
        $db->query("ALTER TABLE produk ADD COLUMN max_stok INT NOT NULL DEFAULT 0 AFTER min_stok");
    }
}

function ensure_produk_satuan_table(Database $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS produk_satuan (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        produk_id BIGINT NOT NULL,
        nama_satuan VARCHAR(50) NOT NULL,
        qty_dasar DECIMAL(15,4) NOT NULL DEFAULT 1,
        urutan INT NOT NULL DEFAULT 0,
        UNIQUE KEY uq_produk_satuan (produk_id, nama_satuan),
        KEY idx_produk (produk_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$tokoId = (int)($_SESSION['toko_id'] ?? 3);
$id     = (int)($_POST['produk_id'] ?? 0);

$nama       = trim($_POST['nama_produk'] ?? '');
$sku        = trim($_POST['sku'] ?? '');
$barcode    = trim($_POST['barcode'] ?? '');
$merk       = trim($_POST['merk'] ?? '');
$kat        = (int)($_POST['kategori_id'] ?? 0);
$supplierId = (int)($_POST['supplier_id'] ?? 0);
$satuanId   = (int)($_POST['satuan_id'] ?? 0);
$satuan     = trim($_POST['satuan'] ?? '');
$hm         = (float)($_POST['harga_modal'] ?? 0);
$ecer       = (float)($_POST['harga_ecer'] ?? 0);
$grosir     = (float)($_POST['harga_grosir'] ?? 0);
$reseller   = (float)($_POST['harga_reseller'] ?? 0);
$member     = (float)($_POST['harga_member'] ?? 0);
$minStk     = (int)($_POST['min_stok'] ?? 0);
$maxStk     = (int)($_POST['max_stok'] ?? 0);
$pajak      = (float)($_POST['pajak_persen'] ?? 0);
$aktif      = isset($_POST['aktif']) && (int)$_POST['aktif'] === 1 ? 1 : 0;
$isJasa     = isset($_POST['is_jasa']) && (int)$_POST['is_jasa'] === 1 ? 1 : 0;
$isKonsinyasi = isset($_POST['is_konsinyasi']) && (int)$_POST['is_konsinyasi'] === 1 ? 1 : 0;

$isiStokAwal      = isset($_POST['isi_stok_awal']) && (int)$_POST['isi_stok_awal'] === 1;
$stokAwalGudangId = (int)($_POST['gudang_id'] ?? 0);
$stokAwalQty      = (int)($_POST['stok_awal_qty'] ?? 0);
$stokAwalHmRaw    = trim((string)($_POST['stok_awal_harga_modal'] ?? ''));
$stokAwalHm       = ($stokAwalHmRaw === '') ? $hm : (float)$stokAwalHmRaw;

if ($nama === '' || $sku === '' || !$kat) fail_json('Nama, SKU, kategori wajib');
if ($supplierId <= 0) fail_json('Supplier wajib dipilih');
if ($minStk < 0) fail_json('Min stok tidak boleh negatif');
if ($maxStk < 0) fail_json('Max stok tidak boleh negatif');
if ($maxStk > 0 && $maxStk < $minStk) fail_json('Max stok tidak boleh lebih kecil dari min stok');
if ($hm < 0) fail_json('Harga modal tidak boleh negatif');
if ($ecer < $hm || $grosir < $hm || $reseller < $hm || $member < $hm) {
    fail_json('Harga jual (ecer/grosir/reseller/member) tidak boleh lebih kecil dari harga modal');
}
if ($pajak < 0 || $pajak > 100) fail_json('Pajak harus di antara 0 sampai 100');
if ($isJasa === 1) {
    $isiStokAwal = false;
    $stokAwalQty = 0;
    $minStk = 0;
    $maxStk = 0;
}
if ($isiStokAwal && $stokAwalQty <= 0) fail_json('Qty stok awal harus lebih dari 0');
if ($isiStokAwal && $stokAwalHm < 0) fail_json('Harga modal stok awal tidak valid');

$satuanNama = '';
if ($satuanId > 0) {
    $stmt = $pos_db->prepare("SELECT nama FROM satuan WHERE satuan_id=? LIMIT 1");
    $stmt->bind_param("i", $satuanId);
    $stmt->execute();
    $rowSat = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$rowSat) fail_json('Satuan tidak ditemukan');
    $satuanNama = (string)$rowSat['nama'];
} elseif ($satuan !== '') {
    $satuanNama = $satuan;
} else {
    fail_json('Satuan wajib diisi');
}

$stmt = $pos_db->prepare("SELECT produk_id FROM produk WHERE toko_id=? AND sku=? AND deleted_at IS NULL AND (?=0 OR produk_id<>?) LIMIT 1");
$stmt->bind_param("isii", $tokoId, $sku, $id, $id);
$stmt->execute();
$dupSku = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($dupSku) fail_json('SKU sudah digunakan');

if ($barcode !== '') {
    $stmt = $pos_db->prepare("SELECT produk_id FROM produk WHERE toko_id=? AND barcode=? AND deleted_at IS NULL AND (?=0 OR produk_id<>?) LIMIT 1");
    $stmt->bind_param("isii", $tokoId, $barcode, $id, $id);
    $stmt->execute();
    $dupBc = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($dupBc) fail_json('Barcode sudah digunakan');
}

$multiNama = $_POST['multi_satuan_nama'] ?? [];
$multiIsi  = $_POST['multi_satuan_isi'] ?? [];
$multiSatuan = [];
if (is_array($multiNama) && is_array($multiIsi)) {
    $c = min(count($multiNama), count($multiIsi));
    for ($i = 0; $i < $c; $i++) {
        $nm = strtoupper(trim((string)$multiNama[$i]));
        $isi = (float)$multiIsi[$i];
        if ($nm === '') continue;
        if ($isi <= 0) fail_json('Isi satuan harus lebih dari 0');
        $multiSatuan[] = ['nama' => $nm, 'isi' => $isi];
    }
}
if (empty($multiSatuan) && $satuanNama !== '') {
    $multiSatuan[] = ['nama' => strtoupper($satuanNama), 'isi' => 1];
}

$gudangFinal = 0;
if ($id === 0 || $isiStokAwal) {
    if ($stokAwalGudangId > 0) {
        $stmt = $pos_db->prepare("SELECT gudang_id FROM gudang WHERE toko_id=? AND gudang_id=? AND aktif=1 AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param("ii", $tokoId, $stokAwalGudangId);
        $stmt->execute();
        $rowGud = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$rowGud) fail_json('Gudang stok awal tidak valid');
        $gudangFinal = (int)$rowGud['gudang_id'];
    } else {
        $gudangFinal = find_default_gudang($pos_db, $tokoId);
    }
    if ($gudangFinal <= 0) {
        fail_json('Belum ada gudang aktif. Tambahkan gudang lalu simpan produk lagi.');
    }
}

$pos_db->begin_transaction();
try {
    ensure_reseller_price_type($pos_db);
    ensure_produk_flags_columns($pos_db);
    ensure_produk_inventory_columns($pos_db);
    ensure_produk_max_stok_column($pos_db);
    ensure_produk_satuan_table($pos_db);

    $fotoName = null;
    if (!empty($_FILES['foto']['name'])) {
        $fotoName = process_upload_image(
            $_FILES['foto'],
            __DIR__ . '/../public/uploads/produk',
            ['prefix' => 'p_', 'max_width' => 1200, 'max_height' => 1200, 'quality' => 82, 'max_bytes' => 5 * 1024 * 1024]
        );
    }

    if ($id > 0) {
        $sql = "UPDATE produk SET supplier_id=?, nama_produk=?, sku=?, barcode=?, merk=?, kategori_id=?, satuan=?, harga_modal=?, min_stok=?, max_stok=?, pajak_persen=?, aktif=?, is_jasa=?, is_konsinyasi=?"
            . ($fotoName ? ", foto=?" : "")
            . " WHERE produk_id=? AND toko_id=?";
        $types = "issssisdiidiii";
        $params = [$supplierId, $nama, $sku, $barcode, $merk, $kat, $satuanNama, $hm, $minStk, $maxStk, $pajak, $aktif, $isJasa, $isKonsinyasi];
        if ($fotoName) {
            $types .= "s";
            $params[] = $fotoName;
        }
        $types .= "ii";
        $params[] = $id;
        $params[] = $tokoId;

        $stmt = $pos_db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            $chk = $pos_db->prepare("SELECT produk_id FROM produk WHERE produk_id=? AND toko_id=? LIMIT 1");
            $chk->bind_param("ii", $id, $tokoId);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$exists) throw new Exception('Produk tidak ditemukan');
        }
        $stmt->close();
    } else {
        $sql = "INSERT INTO produk (toko_id,supplier_id,kategori_id,sku,barcode,nama_produk,merk,satuan,harga_modal,min_stok,max_stok,pajak_persen,foto,aktif,is_jasa,is_konsinyasi)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $pos_db->prepare($sql);
        $types = "iiisssssdiidsiii";
        $stmt->bind_param($types, $tokoId, $supplierId, $kat, $sku, $barcode, $nama, $merk, $satuanNama, $hm, $minStk, $maxStk, $pajak, $fotoName, $aktif, $isJasa, $isKonsinyasi);
        $stmt->execute();
        $id = (int)$pos_db->insertId();
        $stmt->close();
    }

    $delSat = $pos_db->prepare("DELETE FROM produk_satuan WHERE produk_id=?");
    $delSat->bind_param("i", $id);
    $delSat->execute();
    $delSat->close();
    if (!empty($multiSatuan)) {
        $insSat = $pos_db->prepare("INSERT INTO produk_satuan (produk_id,nama_satuan,qty_dasar,urutan) VALUES (?,?,?,?)");
        $urut = 1;
        foreach ($multiSatuan as $s) {
            $nm = $s['nama'];
            $isi = $s['isi'];
            $insSat->bind_param("isdi", $id, $nm, $isi, $urut);
            $insSat->execute();
            $urut++;
        }
        $insSat->close();
    }

    $stmt = $pos_db->prepare("INSERT INTO produk_harga (produk_id,tipe,harga_jual) VALUES (?,?,?) ON DUPLICATE KEY UPDATE harga_jual=VALUES(harga_jual)");
    $stmt->bind_param("isd", $id, $tipe, $harga);

    $tipe = 'ecer'; $harga = $ecer; $stmt->execute();
    $tipe = 'grosir'; $harga = $grosir; $stmt->execute();
    $tipe = 'reseller'; $harga = $reseller; $stmt->execute();
    $tipe = 'member'; $harga = $member; $stmt->execute();
    $stmt->close();

    if ($isiStokAwal) {
        if ($gudangFinal <= 0) throw new Exception('Gudang default tidak ditemukan');
        $ref = 'SALDO AWAL';
        if ($stokAwalHm >= 0) {
            $ref .= ' HM:' . number_format($stokAwalHm, 2, '.', '');
        }
        $stock = apply_stock_mutation($pos_db, $tokoId, $gudangFinal, $id, $stokAwalQty, 'masuk', $ref, $minStk);
        update_produk_hpp_on_masuk($pos_db, $id, (int)$stock['stok_sebelum'], $stokAwalQty, $stokAwalHm);
    }

    $pos_db->commit();
    ob_clean();
    echo json_encode(['ok' => true, 'msg' => 'Tersimpan']);
} catch (Exception $e) {
    $pos_db->rollback();
    ob_clean();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
