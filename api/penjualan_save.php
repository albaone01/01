<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/csrf.php';
require_once '../inc/inventory.php';
require_once '../inc/pos_saas_schema.php';
require_once '../inc/promo_engine.php';

header('Content-Type: application/json');

// Guard
requireLogin();
requireDevice();
csrf_protect_json();

$tokoId   = (int)($_SESSION['toko_id'] ?? 0);
$userId   = (int)($_SESSION['pengguna_id'] ?? 0);
$deviceId = (int)($_SESSION['device_id'] ?? 0);
$gudangId = (int)($_SESSION['gudang_id'] ?? 0);

if (!$tokoId || !$userId) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Sesi tidak lengkap']));
}
ensure_pos_saas_schema($pos_db);
$openShift = get_open_shift($pos_db, $tokoId, $userId);
if (!$openShift) {
    http_response_code(400);
    exit(json_encode([
        'ok' => false,
        'msg' => 'Shift kasir belum dibuka. Buka shift terlebih dahulu di menu Tutup Kasir.'
    ]));
}
$shiftId = (int)($openShift['shift_id'] ?? 0);
if ($shiftId <= 0) {
    http_response_code(400);
    exit(json_encode([
        'ok' => false,
        'msg' => 'Shift aktif tidak valid. Silakan tutup lalu buka ulang shift.'
    ]));
}
ensure_inventory_snapshot_columns($pos_db);
try {
    $pos_db->query("ALTER TABLE penjualan_detail MODIFY tipe_harga ENUM('ecer','grosir','member','reseller') NOT NULL");
} catch (Exception $e) {}
promo_ensure_tables($pos_db);
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

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Payload tidak valid']));
}

$items       = $body['items'] ?? [];
$payment     = $body['payment'] ?? [];
$pelangganId = (int)($body['pelanggan_id'] ?? 0);
$catatan     = trim($body['catatan'] ?? '');

if (empty($items) || !is_array($items)) {
    exit(json_encode(['ok' => false, 'msg' => 'Cart kosong']));
}

// Normalisasi & validasi item
$normItems = [];
foreach ($items as $it) {
    $pid   = (int)($it['produk_id'] ?? 0);
    $qty   = (float)($it['qty'] ?? 0);
    $price = (float)($it['price'] ?? 0);
    $disc  = (float)($it['discount'] ?? 0);
    $taxP  = (float)($it['tax_percent'] ?? 0);
    $ptype = strtolower((string)($it['price_type'] ?? 'ecer'));
    if (!in_array($ptype, ['ecer', 'grosir', 'member', 'reseller'], true)) {
        $ptype = 'ecer';
    }

    $qtyInt = (int)round($qty);
    if (abs($qty - $qtyInt) > 0.0001) {
        exit(json_encode(['ok' => false, 'msg' => 'Qty harus bilangan bulat untuk stok gudang']));
    }

    if ($pid <= 0 || $qtyInt <= 0 || $price < 0) {
        exit(json_encode(['ok' => false, 'msg' => 'Data item tidak valid']));
    }
    $normItems[] = [
        'produk_id'   => $pid,
        'qty'         => $qtyInt,
        'price'       => $price,
        'discount'    => max(0, $disc),
        'tax_percent' => max(0, $taxP),
        'price_type'  => $ptype,
    ];
}

$payMethod = strtolower($payment['method'] ?? 'cash');
$payAmount = (float)($payment['amount'] ?? 0);
$payAmount = max(0, $payAmount);
$redeemPointsRequest = max(0, (int)($payment['redeem_points'] ?? 0));
$voucherCode = strtoupper(trim((string)($payment['voucher_code'] ?? '')));

// Hitung ulang di server
$subtotal = 0.0;
$diskon   = 0.0;
$pajak    = 0.0;
$lines    = [];

// Ambil harga_modal dan stok per produk
$pidList = array_column($normItems, 'produk_id');
$in  = implode(',', array_fill(0, count($pidList), '?'));
$typ = str_repeat('i', count($pidList));

$stmt = $pos_db->prepare("SELECT p.produk_id, p.nama_produk, p.harga_modal, p.satuan, p.supplier_id, p.kategori_id, COALESCE(p.hpp_aktif,0) AS hpp_aktif, COALESCE(p.is_jasa,0) AS is_jasa,
                                 COALESCE(SUM(sg.stok),0) AS stok
                          FROM produk p
                          LEFT JOIN stok_gudang sg ON sg.produk_id = p.produk_id AND sg.gudang_id = ?
                          WHERE p.produk_id IN ($in) AND p.toko_id=? AND p.aktif=1 AND p.deleted_at IS NULL
                          GROUP BY p.produk_id");
$types = 'i' . $typ . 'i';
$params = array_merge([$gudangId], $pidList, [$tokoId]);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$prodMap = [];
while ($r = $res->fetch_assoc()) {
    $prodMap[$r['produk_id']] = $r;
}
$stmt->close();

foreach ($normItems as $it) {
    if (!isset($prodMap[$it['produk_id']])) {
        exit(json_encode(['ok' => false, 'msg' => 'Produk tidak ditemukan / nonaktif']));
    }
    $pinfo = $prodMap[$it['produk_id']];

    $lineGross = $it['qty'] * $it['price'];
    $lineAfterDisc = max(0, $lineGross - $it['discount']);
    $lineTax = $lineAfterDisc * ($it['tax_percent'] / 100);
    $lineTotal = $lineAfterDisc + $lineTax;

    $subtotal += $lineGross;
    $diskon   += $it['discount'];
    $pajak    += $lineTax;

    $lines[] = [
        'produk_id'  => $it['produk_id'],
        'qty'        => $it['qty'],
        'price'      => $it['price'],
        'discount'   => $it['discount'],
        'tax_percent'=> $it['tax_percent'],
        'line_total' => $lineTotal,
        'line_after_disc' => $lineAfterDisc,
        'harga_modal'=> ((float)$pinfo['hpp_aktif'] > 0 ? (float)$pinfo['hpp_aktif'] : (float)$pinfo['harga_modal']),
        'satuan'     => $pinfo['satuan'],
        'supplier_id'=> (int)($pinfo['supplier_id'] ?? 0),
        'kategori_id'=> (int)($pinfo['kategori_id'] ?? 0),
        'price_type' => $it['price_type'],
        'stok'       => (float)$pinfo['stok'],
        'nama_produk'=> $pinfo['nama_produk'],
        'is_jasa'    => (int)$pinfo['is_jasa'] === 1 ? 1 : 0,
    ];
}

$promoEval = promo_evaluate(
    $pos_db,
    $tokoId,
    $lines,
    $subtotal,
    $diskon,
    $pelangganId > 0,
    $voucherCode
);
$promoDiscount = max(0, (float)($promoEval['promo_discount'] ?? 0));
$ruleDiscount = max(0, (float)($promoEval['rule_discount'] ?? 0));
$voucherDiscount = max(0, (float)($promoEval['voucher_discount'] ?? 0));
$promoUsed = $promoEval['promo'] ?? null;
$ruleUsed = $promoEval['rule'] ?? null;
$voucherUsed = $promoEval['voucher'] ?? null;

$totalAkhir = $subtotal - ($diskon + $promoDiscount + $ruleDiscount + $voucherDiscount) + $pajak;
if ($redeemPointsRequest > 0 && $pelangganId <= 0) {
    exit(json_encode(['ok' => false, 'msg' => 'Penukaran poin memerlukan pelanggan']));
}

// Validasi stok tidak minus
foreach ($lines as $ln) {
    if ($ln['is_jasa'] !== 1 && $ln['qty'] > $ln['stok']) {
        exit(json_encode(['ok' => false, 'msg' => 'Stok kurang untuk ' . $ln['nama_produk']]));
    }
}
// Generate nomor invoice unik per toko
function generateInvoice(Database $db, int $tokoId): string {
    $prefix = 'POS-' . date('Ymd') . '-';
    for ($i=0; $i<5; $i++) {
        $rand = str_pad((string)rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $no = $prefix . $rand;
        $st = $db->prepare('SELECT 1 FROM penjualan WHERE toko_id=? AND nomor_invoice=? LIMIT 1');
        $st->bind_param('is', $tokoId, $no);
        $st->execute();
        $has = $st->get_result()->num_rows;
        $st->close();
        if (!$has) return $no;
    }
    return $prefix . time();
}

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

function resolve_auto_level_id(Database $db, int $tokoId, float $belanjaBulanan): ?int {
    $thresholdColumn = table_has_column($db, 'member_level', 'minimal_belanja')
        ? 'minimal_belanja'
        : 'minimal_poin';
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

function get_toko_config(Database $db, int $tokoId, string $key, string $default = ''): string {
    $st = $db->prepare("SELECT nilai FROM toko_config WHERE toko_id = ? AND nama_konfigurasi = ? LIMIT 1");
    $st->bind_param('is', $tokoId, $key);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return isset($row['nilai']) ? (string)$row['nilai'] : $default;
}

$pointNominal = (float)get_toko_config($pos_db, $tokoId, 'member_point_nominal', '1000');
if ($pointNominal <= 0) $pointNominal = 1000.0;
$redeemNominal = (float)get_toko_config($pos_db, $tokoId, 'member_redeem_nominal', '1');
if ($redeemNominal <= 0) $redeemNominal = 1.0;
$poinDidapat = 0;
$poinDitukar = 0;
$potonganPoin = 0.0;
$payAmountFinal = 0.0; // jumlah efektif yang diakui sebagai pembayaran (net)
$payReceived = 0.0;    // uang yang diterima dari pelanggan
$payChange = 0.0;      // kembalian
$totalAkhirFinal = $totalAkhir;
$diskonFinal = $diskon + $promoDiscount + $ruleDiscount + $voucherDiscount;
$sisa = 0.0;

$pos_db->begin_transaction();
try {
    $nomor = generateInvoice($pos_db, $tokoId);
    $pelangganIdBind = $pelangganId > 0 ? $pelangganId : null;
    $ptRow = null;
    if ($pelangganId > 0) {
        $pt = $pos_db->prepare("SELECT id, poin, tanggal_daftar, masa_berlaku, masa_tenggang FROM pelanggan_toko WHERE pelanggan_id = ? AND toko_id = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE");
        $pt->bind_param('ii', $pelangganId, $tokoId);
        $pt->execute();
        $ptRow = $pt->get_result()->fetch_assoc();
        $pt->close();
    }

    if ($redeemPointsRequest > 0) {
        $availablePoint = $ptRow ? max(0, (int)$ptRow['poin']) : 0;
        $poinDitukar = min($redeemPointsRequest, $availablePoint);
        $maxRedeemByTotal = (int)floor(max(0, $totalAkhir) / $redeemNominal);
        $poinDitukar = min($poinDitukar, $maxRedeemByTotal);
        $potonganPoin = $poinDitukar * $redeemNominal;
    }

    $diskonFinal = $diskon + $promoDiscount + $ruleDiscount + $voucherDiscount + $potonganPoin;
    $totalAkhirFinal = max(0, $totalAkhir - $potonganPoin);
    if (in_array($payMethod, ['qris', 'transfer'], true)) {
        // transfer/qris selalu dicatat lunas sesuai total akhir
        $payReceived = $totalAkhirFinal;
        $payAmountFinal = $totalAkhirFinal;
        $payChange = 0.0;
    } elseif ($payMethod === 'cash') {
        // cash bisa lebih bayar; yang diakui ke kas = minimum(received, total)
        $payReceived = $payAmount > 0 ? $payAmount : $totalAkhirFinal;
        $payAmountFinal = min($payReceived, $totalAkhirFinal);
        $payChange = max(0, $payReceived - $totalAkhirFinal);
    } elseif ($payMethod === 'hutang') {
        // hutang bisa DP (partial) atau 0
        $payReceived = max(0, $payAmount);
        $payAmountFinal = min($payReceived, $totalAkhirFinal);
        $payChange = 0.0;
    } else {
        $payMethod = 'cash';
        $payReceived = $payAmount > 0 ? $payAmount : $totalAkhirFinal;
        $payAmountFinal = min($payReceived, $totalAkhirFinal);
        $payChange = max(0, $payReceived - $totalAkhirFinal);
    }

    $sisa = max(0, $totalAkhirFinal - $payAmountFinal);

    if ($sisa > 0 && !$pelangganId) {
        throw new RuntimeException('Transaksi hutang memerlukan pelanggan');
    }

    $poinDidapat = ($pelangganId > 0) ? (int)floor(max(0, $totalAkhirFinal) / $pointNominal) : 0;

    $stmt = $pos_db->prepare("INSERT INTO penjualan (nomor_invoice, kasir_id, pelanggan_id, toko_id, gudang_id, shift_id, subtotal, diskon, total_akhir) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('siiiiiddd', $nomor, $userId, $pelangganIdBind, $tokoId, $gudangId, $shiftId, $subtotal, $diskonFinal, $totalAkhirFinal);
    $stmt->execute();
    $penjualanId = (int)$pos_db->insert_id;
    $stmt->close();

    // Detail + stok
    $detailStmt = $pos_db->prepare("INSERT INTO penjualan_detail (penjualan_id, produk_id, qty, tipe_harga, harga_jual, harga_modal_snapshot, diskon, subtotal) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($lines as $ln) {
        $detailStmt->bind_param(
            'iiisdddd',
            $penjualanId,
            $ln['produk_id'],
            $ln['qty'],
            $ln['price_type'],
            $ln['price'],
            $ln['harga_modal'],
            $ln['discount'],
            $ln['line_total']
        );
        $detailStmt->execute();

        if ($ln['is_jasa'] !== 1) {
            apply_stock_mutation($pos_db, $tokoId, $gudangId, (int)$ln['produk_id'], (int)$ln['qty'], 'keluar', $nomor);
        }
    }
    $detailStmt->close();

    // Pembayaran
    if ($payAmountFinal > 0) {
        // normalisasi metode sesuai enum: cash|transfer|qris|hutang
        $metodeAllowed = ['cash','transfer','qris','hutang'];
        if (!in_array($payMethod, $metodeAllowed, true)) {
            $payMethod = 'cash';
        }
        $hasUangDiterima = has_table_column($pos_db, 'pembayaran', 'uang_diterima');
        $hasKembalian = has_table_column($pos_db, 'pembayaran', 'kembalian');
        if ($hasUangDiterima && $hasKembalian) {
            $pb = $pos_db->prepare("INSERT INTO pembayaran (penjualan_id, metode, jumlah, uang_diterima, kembalian) VALUES (?,?,?,?,?)");
            $pb->bind_param('isddd', $penjualanId, $payMethod, $payAmountFinal, $payReceived, $payChange);
        } else {
            $pb = $pos_db->prepare("INSERT INTO pembayaran (penjualan_id, metode, jumlah) VALUES (?,?,?)");
            $pb->bind_param('isd', $penjualanId, $payMethod, $payAmountFinal);
        }
        $pb->execute();
        $pb->close();
    }

    // Piutang jika belum lunas
    if ($sisa > 0) {
        $pstmt = $pos_db->prepare("INSERT INTO piutang (pelanggan_id, penjualan_id, total, sisa, status) VALUES (?,?,?,?,?)");
        $status = 'belum';
        $pstmt->bind_param('iidds', $pelangganId, $penjualanId, $totalAkhirFinal, $sisa, $status);
        $pstmt->execute();
        $pstmt->close();
    }

    if ($voucherUsed && (int)($voucherUsed['voucher_id'] ?? 0) > 0 && $voucherDiscount > 0) {
        $voucherIdUsed = (int)$voucherUsed['voucher_id'];
        $vu = $pos_db->prepare("UPDATE voucher_belanja SET terpakai = terpakai + 1 WHERE voucher_id=? AND toko_id=? AND terpakai < kuota");
        $vu->bind_param('ii', $voucherIdUsed, $tokoId);
        $vu->execute();
        $vu->close();
    }

    // Poin member dari transaksi: rasio nominal/koin mengikuti pengaturan admin toko.
    if ($pelangganId > 0) {
        $earnedPoint = $poinDidapat;
        if ($poinDitukar > 0) {
            $pmSpend = $pos_db->prepare("INSERT INTO poin_member (pelanggan_id, toko_id, sumber, referensi_id, poin) VALUES (?, ?, 'manual', ?, ?)");
            $usedPointNegative = -1 * $poinDitukar;
            $pmSpend->bind_param('iiii', $pelangganId, $tokoId, $penjualanId, $usedPointNegative);
            $pmSpend->execute();
            $pmSpend->close();
        }
        if ($earnedPoint > 0) {
            $pm = $pos_db->prepare("INSERT INTO poin_member (pelanggan_id, toko_id, sumber, referensi_id, poin) VALUES (?, ?, 'penjualan', ?, ?)");
            $pm->bind_param('iiii', $pelangganId, $tokoId, $penjualanId, $earnedPoint);
            $pm->execute();
            $pm->close();
        }

        if ($ptRow) {
            $newPoin = max(0, (int)$ptRow['poin'] - $poinDitukar + $earnedPoint);
            $monthlySpending = get_monthly_spending($pos_db, $tokoId, $pelangganId);
            $newLevelId = resolve_auto_level_id($pos_db, $tokoId, $monthlySpending);
            $ptId = (int)$ptRow['id'];
            $uPt = $pos_db->prepare("UPDATE pelanggan_toko SET poin = ?, poin_akhir = ?, level_id = ? WHERE id = ?");
            $uPt->bind_param('iiii', $newPoin, $newPoin, $newLevelId, $ptId);
            $uPt->execute();
            $uPt->close();
        } else {
            $tanggalDaftar = date('Y-m-d');
            $masaBerlaku = 1;
            $masaTenggang = 7;
            $exp = date('Y-m-d', strtotime('+1 year', strtotime($tanggalDaftar)));
            $expPoin = $exp;
            $newPoin = max(0, $earnedPoint - $poinDitukar);
            $monthlySpending = get_monthly_spending($pos_db, $tokoId, $pelangganId);
            $newLevelId = resolve_auto_level_id($pos_db, $tokoId, $monthlySpending);
            $iPt = $pos_db->prepare("
                INSERT INTO pelanggan_toko
                (pelanggan_id, toko_id, level_id, poin, tanggal_daftar, masa_berlaku, exp, masa_tenggang, exp_poin, poin_awal, poin_akhir)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)
            ");
            $iPt->bind_param('iiiisisisi', $pelangganId, $tokoId, $newLevelId, $newPoin, $tanggalDaftar, $masaBerlaku, $exp, $masaTenggang, $expPoin, $newPoin);
            $iPt->execute();
            $iPt->close();
        }
    }

    // (Optional) catatan ke audit_log
    $al = $pos_db->prepare("INSERT INTO audit_log (toko_id, pengguna_id, aksi, tabel, record_id, data_baru, device_id, ip_address) VALUES (?,?,?,?,?,?,?,?)");
    $aksi = 'insert';
    $tabel = 'penjualan';
    $jsonBaru = json_encode(['penjualan_id'=>$penjualanId,'nomor_invoice'=>$nomor]);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $al->bind_param('iissssis', $tokoId, $userId, $aksi, $tabel, $penjualanId, $jsonBaru, $deviceId, $ip);
    $al->execute();
    $al->close();

    $pos_db->commit();
    echo json_encode([
        'ok' => true,
        'penjualan_id' => $penjualanId,
        'nomor' => $nomor,
        'total' => $totalAkhirFinal,
        'kembalian' => $payChange,
        'dibayar' => $payAmountFinal,
        'uang_diterima' => $payReceived,
        'sisa' => $sisa,
        'poin_didapat' => $poinDidapat,
        'poin_ditukar' => $poinDitukar,
        'potongan_poin' => $potonganPoin,
        'promo_id' => $promoUsed['promo_id'] ?? null,
        'promo_nama' => $promoUsed['nama_promo'] ?? '',
        'promo_diskon' => $promoDiscount,
        'promo_scope_base' => $promoUsed['scope_base'] ?? 0,
        'rule_id' => $ruleUsed['rule_id'] ?? null,
        'rule_nama' => $ruleUsed['nama_rule'] ?? '',
        'rule_diskon' => $ruleDiscount,
        'voucher_id' => $voucherUsed['voucher_id'] ?? null,
        'voucher_kode' => $voucherUsed['kode_voucher'] ?? '',
        'voucher_nama' => $voucherUsed['nama_voucher'] ?? '',
        'voucher_diskon' => $voucherDiscount,
    ]);
} catch (Throwable $e) {
    $pos_db->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
