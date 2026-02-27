<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/pos_saas_schema.php';

ensure_pos_saas_schema($pos_db);

function assert_true(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function one_row(Database $db, string $sql, string $types = '', array $params = []): array {
    $st = $db->prepare($sql);
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: [];
    $st->close();
    return $row;
}

$ctx = one_row(
    $pos_db,
    "SELECT t.toko_id, p.pengguna_id, g.gudang_id
     FROM toko t
     JOIN pengguna p ON p.toko_id = t.toko_id AND p.peran = 'kasir' AND p.deleted_at IS NULL
     JOIN gudang g ON g.toko_id = t.toko_id AND g.aktif = 1 AND g.deleted_at IS NULL
     WHERE t.aktif = 1 AND t.deleted_at IS NULL
     LIMIT 1"
);
if (!$ctx) {
    throw new RuntimeException('Context test tidak ditemukan (toko/kasir/gudang).');
}
$tokoId = (int)$ctx['toko_id'];
$kasirId = (int)$ctx['pengguna_id'];
$gudangId = (int)$ctx['gudang_id'];

$invoicePrefix = 'TEST-' . date('YmdHis') . '-';

$results = [
    'cash_overpay' => 'pending',
    'hutang_dp' => 'pending',
    'reopen_close_shift' => 'pending',
    'journal_number_unique' => 'pending',
];

// 1) Cash overpay
$pos_db->begin_transaction();
try {
    $inv = $invoicePrefix . 'CASH';
    $subtotal = 2000.0;
    $diskon = 0.0;
    $total = 2000.0;
    $st = $pos_db->prepare("INSERT INTO penjualan (nomor_invoice, kasir_id, pelanggan_id, toko_id, gudang_id, subtotal, diskon, total_akhir) VALUES (?,?,?,?,?,?,?,?)");
    $null = null;
    $st->bind_param('siiiiddd', $inv, $kasirId, $null, $tokoId, $gudangId, $subtotal, $diskon, $total);
    $st->execute();
    $penjualanId = (int)$pos_db->insertId();
    $st->close();

    $metode = 'cash';
    $net = 2000.0;
    $received = 3000.0;
    $change = 1000.0;
    $st = $pos_db->prepare("INSERT INTO pembayaran (penjualan_id, metode, jumlah, uang_diterima, kembalian) VALUES (?,?,?,?,?)");
    $st->bind_param('isddd', $penjualanId, $metode, $net, $received, $change);
    $st->execute();
    $st->close();

    $r = one_row($pos_db, "SELECT jumlah, uang_diterima, kembalian FROM pembayaran WHERE penjualan_id = ? LIMIT 1", 'i', [$penjualanId]);
    assert_true((float)$r['jumlah'] === 2000.0, 'cash overpay: jumlah harus net 2000');
    assert_true((float)$r['uang_diterima'] === 3000.0, 'cash overpay: uang_diterima harus 3000');
    assert_true((float)$r['kembalian'] === 1000.0, 'cash overpay: kembalian harus 1000');

    $results['cash_overpay'] = 'ok';
    $pos_db->rollback();
} catch (Throwable $e) {
    $pos_db->rollback();
    $results['cash_overpay'] = 'fail: ' . $e->getMessage();
}

// 2) Hutang + DP
$pos_db->begin_transaction();
try {
    $inv = $invoicePrefix . 'HUT';
    $subtotal = 10000.0;
    $diskon = 0.0;
    $total = 10000.0;
    $st = $pos_db->prepare("INSERT INTO penjualan (nomor_invoice, kasir_id, pelanggan_id, toko_id, gudang_id, subtotal, diskon, total_akhir) VALUES (?,?,?,?,?,?,?,?)");
    $pelangganDummy = 1;
    $st->bind_param('siiiiddd', $inv, $kasirId, $pelangganDummy, $tokoId, $gudangId, $subtotal, $diskon, $total);
    $st->execute();
    $penjualanId = (int)$pos_db->insertId();
    $st->close();

    $metode = 'hutang';
    $dp = 4000.0;
    $st = $pos_db->prepare("INSERT INTO pembayaran (penjualan_id, metode, jumlah, uang_diterima, kembalian) VALUES (?,?,?,?,0)");
    $st->bind_param('isdd', $penjualanId, $metode, $dp, $dp);
    $st->execute();
    $st->close();

    $sisa = 6000.0;
    $status = 'belum';
    $st = $pos_db->prepare("INSERT INTO piutang (pelanggan_id, penjualan_id, total, sisa, status) VALUES (?,?,?,?,?)");
    $st->bind_param('iidds', $pelangganDummy, $penjualanId, $total, $sisa, $status);
    $st->execute();
    $st->close();

    $r = one_row($pos_db, "SELECT total, sisa, status FROM piutang WHERE penjualan_id = ? LIMIT 1", 'i', [$penjualanId]);
    assert_true((float)$r['total'] === 10000.0, 'hutang+dp: total piutang harus 10000');
    assert_true((float)$r['sisa'] === 6000.0, 'hutang+dp: sisa piutang harus 6000');
    assert_true((string)$r['status'] === 'belum', 'hutang+dp: status harus belum');

    $results['hutang_dp'] = 'ok';
    $pos_db->rollback();
} catch (Throwable $e) {
    $pos_db->rollback();
    $results['hutang_dp'] = 'fail: ' . $e->getMessage();
}

// 3) Reopen + close shift
$pos_db->begin_transaction();
try {
    $tanggal = '2099-01-01';
    $status = 'open';
    $modalAwal = 5000.0;
    $deviceId = null;

    $st = $pos_db->prepare("INSERT INTO kasir_shift (toko_id, kasir_id, device_id, tanggal_shift, modal_awal, status) VALUES (?,?,?,?,?,?)");
    $st->bind_param('iiisds', $tokoId, $kasirId, $deviceId, $tanggal, $modalAwal, $status);
    $st->execute();
    $shiftId = (int)$pos_db->insertId();
    $st->close();

    $statusClosed = 'closed';
    $kasSistem = 7000.0;
    $kasFisik = 7000.0;
    $selisih = 0.0;
    $st = $pos_db->prepare("UPDATE kasir_shift SET jam_tutup = NOW(), kas_sistem = ?, kas_fisik = ?, selisih = ?, status = ? WHERE shift_id = ?");
    $st->bind_param('dddsi', $kasSistem, $kasFisik, $selisih, $statusClosed, $shiftId);
    $st->execute();
    $st->close();

    $statusOpen = 'open';
    $st = $pos_db->prepare("UPDATE kasir_shift SET jam_tutup = NULL, kas_sistem = 0, kas_fisik = 0, selisih = 0, status = ? WHERE shift_id = ?");
    $st->bind_param('si', $statusOpen, $shiftId);
    $st->execute();
    $st->close();

    $statusClosed2 = 'closed';
    $kasSistem2 = 9000.0;
    $kasFisik2 = 9000.0;
    $selisih2 = 0.0;
    $st = $pos_db->prepare("UPDATE kasir_shift SET jam_tutup = NOW(), kas_sistem = ?, kas_fisik = ?, selisih = ?, status = ? WHERE shift_id = ?");
    $st->bind_param('dddsi', $kasSistem2, $kasFisik2, $selisih2, $statusClosed2, $shiftId);
    $st->execute();
    $st->close();

    $r = one_row($pos_db, "SELECT status, kas_sistem, kas_fisik, selisih FROM kasir_shift WHERE shift_id = ? LIMIT 1", 'i', [$shiftId]);
    assert_true((string)$r['status'] === 'closed', 'reopen+close: status akhir harus closed');
    assert_true((float)$r['kas_sistem'] === 9000.0, 'reopen+close: kas_sistem akhir harus 9000');
    assert_true((float)$r['kas_fisik'] === 9000.0, 'reopen+close: kas_fisik akhir harus 9000');
    assert_true((float)$r['selisih'] === 0.0, 'reopen+close: selisih akhir harus 0');

    $results['reopen_close_shift'] = 'ok';
    $pos_db->rollback();
} catch (Throwable $e) {
    $pos_db->rollback();
    $results['reopen_close_shift'] = 'fail: ' . $e->getMessage();
}

// 4) Journal number unique sequence same date
$pos_db->begin_transaction();
try {
    $coa = get_coa_map($pos_db, $tokoId);
    assert_true(isset($coa['1101']) && isset($coa['4101']), 'COA minimum test jurnal tidak lengkap.');
    $tgl = new DateTimeImmutable('2099-01-02');
    $lines = [
        ['akun_id' => $coa['1101'], 'debit' => 1000.0, 'kredit' => 0.0, 'deskripsi' => 'test debit'],
        ['akun_id' => $coa['4101'], 'debit' => 0.0, 'kredit' => 1000.0, 'deskripsi' => 'test kredit'],
    ];
    $j1 = insert_journal_entry($pos_db, $tokoId, $kasirId, $tgl, 'manual', 'test', null, 'test 1', $lines);
    $j2 = insert_journal_entry($pos_db, $tokoId, $kasirId, $tgl, 'manual', 'test', null, 'test 2', $lines);

    $r1 = one_row($pos_db, "SELECT nomor_jurnal FROM jurnal_umum WHERE jurnal_id = ?", 'i', [$j1]);
    $r2 = one_row($pos_db, "SELECT nomor_jurnal FROM jurnal_umum WHERE jurnal_id = ?", 'i', [$j2]);
    assert_true(($r1['nomor_jurnal'] ?? '') !== ($r2['nomor_jurnal'] ?? ''), 'nomor jurnal harus unik.');

    $results['journal_number_unique'] = 'ok';
    $pos_db->rollback();
} catch (Throwable $e) {
    $pos_db->rollback();
    $results['journal_number_unique'] = 'fail: ' . $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;

$hasFail = false;
foreach ($results as $v) {
    if (str_starts_with((string)$v, 'fail:')) {
        $hasFail = true;
        break;
    }
}
exit($hasFail ? 1 : 0);
