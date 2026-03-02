<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/csrf.php';
require_once '../inc/promo_engine.php';

header('Content-Type: application/json');

requireLogin();
requireDevice();
csrf_protect_json();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if ($tokoId <= 0) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Sesi toko tidak valid']));
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'msg' => 'Payload tidak valid']));
}

$items = $body['items'] ?? [];
$pelangganId = (int)($body['pelanggan_id'] ?? 0);
$redeemPointsReq = max(0, (int)($body['redeem_points'] ?? 0));
$voucherCode = strtoupper(trim((string)($body['voucher_code'] ?? '')));
$pointNominal = max(1, (float)($body['point_nominal'] ?? 1000));
$redeemNominal = max(1, (float)($body['redeem_nominal'] ?? 1));

if (!is_array($items) || empty($items)) {
    echo json_encode([
        'ok' => true,
        'summary' => [
            'subtotal' => 0,
            'diskon_item' => 0,
            'promo_diskon' => 0,
            'rule_diskon' => 0,
            'voucher_diskon' => 0,
            'potongan_poin' => 0,
            'total_akhir' => 0,
        ],
        'applied' => [],
    ]);
    exit;
}

$norm = [];
foreach ($items as $it) {
    $pid = (int)($it['produk_id'] ?? 0);
    $qty = max(1, (int)round((float)($it['qty'] ?? 0)));
    $price = max(0, (float)($it['price'] ?? 0));
    $disc = max(0, (float)($it['discount'] ?? 0));
    $tax = max(0, (float)($it['tax_percent'] ?? 0));
    if ($pid <= 0) continue;
    $norm[] = [
        'produk_id' => $pid,
        'qty' => $qty,
        'price' => $price,
        'discount' => $disc,
        'tax_percent' => $tax,
    ];
}

if (empty($norm)) {
    echo json_encode(['ok' => true, 'summary' => ['subtotal' => 0, 'diskon_item' => 0, 'promo_diskon' => 0, 'rule_diskon' => 0, 'voucher_diskon' => 0, 'potongan_poin' => 0, 'total_akhir' => 0], 'applied' => []]);
    exit;
}

$pidList = array_column($norm, 'produk_id');
$in = implode(',', array_fill(0, count($pidList), '?'));
$typ = str_repeat('i', count($pidList));
$sql = "SELECT produk_id, supplier_id, kategori_id, COALESCE(aktif,1) AS aktif, deleted_at FROM produk WHERE toko_id=? AND produk_id IN ($in)";
$st = $pos_db->prepare($sql);
$types = 'i' . $typ;
$params = array_merge([$tokoId], $pidList);
$st->bind_param($types, ...$params);
$st->execute();
$rs = $st->get_result();
$pmap = [];
while ($rw = $rs->fetch_assoc()) {
    $pmap[(int)$rw['produk_id']] = $rw;
}
$st->close();

$subtotal = 0.0;
$diskonItem = 0.0;
$pajak = 0.0;
$lines = [];
foreach ($norm as $it) {
    $p = $pmap[$it['produk_id']] ?? null;
    if (!$p || !is_null($p['deleted_at']) || (int)$p['aktif'] !== 1) continue;
    $lineGross = $it['qty'] * $it['price'];
    $lineAfterDisc = max(0, $lineGross - $it['discount']);
    $lineTax = $lineAfterDisc * ($it['tax_percent'] / 100);
    $subtotal += $lineGross;
    $diskonItem += $it['discount'];
    $pajak += $lineTax;
    $lines[] = [
        'produk_id' => (int)$it['produk_id'],
        'supplier_id' => (int)($p['supplier_id'] ?? 0),
        'kategori_id' => (int)($p['kategori_id'] ?? 0),
        'qty' => (int)$it['qty'],
        'line_after_disc' => (float)$lineAfterDisc,
    ];
}

$promoEval = promo_evaluate($pos_db, $tokoId, $lines, $subtotal, $diskonItem, $pelangganId > 0, $voucherCode);
$promoDiscount = max(0, (float)($promoEval['promo_discount'] ?? 0));
$ruleDiscount = max(0, (float)($promoEval['rule_discount'] ?? 0));
$voucherDiscount = max(0, (float)($promoEval['voucher_discount'] ?? 0));

$totalBeforePoint = max(0, $subtotal - ($diskonItem + $promoDiscount + $ruleDiscount + $voucherDiscount) + $pajak);
$maxRedeemByTotal = (int)floor($totalBeforePoint / $redeemNominal);
$redeemPointsUsed = max(0, min($redeemPointsReq, $maxRedeemByTotal));
$potonganPoin = $redeemPointsUsed * $redeemNominal;
$totalAkhir = max(0, $totalBeforePoint - $potonganPoin);
$poinDidapat = $pelangganId > 0 ? (int)floor($totalAkhir / $pointNominal) : 0;

echo json_encode([
    'ok' => true,
    'summary' => [
        'subtotal' => $subtotal,
        'diskon_item' => $diskonItem,
        'promo_diskon' => $promoDiscount,
        'rule_diskon' => $ruleDiscount,
        'voucher_diskon' => $voucherDiscount,
        'potongan_poin' => $potonganPoin,
        'total_akhir' => $totalAkhir,
        'pajak' => $pajak,
        'poin_didapat' => $poinDidapat,
        'redeem_points_used' => $redeemPointsUsed,
    ],
    'applied' => [
        'promo_id' => $promoEval['promo']['promo_id'] ?? null,
        'promo_nama' => $promoEval['promo']['nama_promo'] ?? '',
        'rule_id' => $promoEval['rule']['rule_id'] ?? null,
        'rule_nama' => $promoEval['rule']['nama_rule'] ?? '',
        'voucher_id' => $promoEval['voucher']['voucher_id'] ?? null,
        'voucher_kode' => $promoEval['voucher']['kode_voucher'] ?? '',
        'voucher_nama' => $promoEval['voucher']['nama_voucher'] ?? '',
    ],
]);
