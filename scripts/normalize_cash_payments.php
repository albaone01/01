<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/pos_saas_schema.php';

ensure_pos_saas_schema($pos_db);

if (!has_table_column($pos_db, 'pembayaran', 'uang_diterima') || !has_table_column($pos_db, 'pembayaran', 'kembalian')) {
    throw new RuntimeException('Kolom pembayaran.uang_diterima/kembalian belum tersedia.');
}

$overpaid = [];
$st = $pos_db->prepare("
    SELECT p.penjualan_id, p.total_akhir, COALESCE(SUM(b.jumlah),0) AS total_bayar
    FROM penjualan p
    LEFT JOIN pembayaran b ON b.penjualan_id = p.penjualan_id
    GROUP BY p.penjualan_id, p.total_akhir
    HAVING total_bayar > p.total_akhir
");
$st->execute();
$overpaid = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$fixedSales = 0;
$fixedRows = 0;
$skipped = 0;

foreach ($overpaid as $sale) {
    $penjualanId = (int)$sale['penjualan_id'];
    $totalAkhir = (float)$sale['total_akhir'];
    $totalBayar = (float)$sale['total_bayar'];
    $excess = $totalBayar - $totalAkhir;
    if ($excess <= 0.0001) {
        continue;
    }

    $pos_db->begin_transaction();
    try {
        $rows = [];
        $stPay = $pos_db->prepare("
            SELECT pembayaran_id, jumlah, uang_diterima, kembalian
            FROM pembayaran
            WHERE penjualan_id = ?
              AND metode = 'cash'
            ORDER BY pembayaran_id DESC
            FOR UPDATE
        ");
        $stPay->bind_param('i', $penjualanId);
        $stPay->execute();
        $rows = $stPay->get_result()->fetch_all(MYSQLI_ASSOC);
        $stPay->close();

        if (!$rows) {
            $pos_db->rollback();
            $skipped++;
            continue;
        }

        foreach ($rows as $r) {
            if ($excess <= 0.0001) break;
            $jumlah = (float)$r['jumlah'];
            if ($jumlah <= 0) continue;
            $reduce = min($excess, $jumlah);
            $newJumlah = $jumlah - $reduce;
            $newKembalian = (float)$r['kembalian'] + $reduce;
            $newUangDiterima = max((float)$r['uang_diterima'], $newJumlah + $newKembalian);

            $stUpd = $pos_db->prepare("
                UPDATE pembayaran
                SET jumlah = ?, uang_diterima = ?, kembalian = ?
                WHERE pembayaran_id = ?
            ");
            $pid = (int)$r['pembayaran_id'];
            $stUpd->bind_param('dddi', $newJumlah, $newUangDiterima, $newKembalian, $pid);
            $stUpd->execute();
            $stUpd->close();

            $fixedRows++;
            $excess -= $reduce;
        }

        if ($excess > 0.0001) {
            $pos_db->rollback();
            $skipped++;
            continue;
        }

        $fixedSales++;
        $pos_db->commit();
    } catch (Throwable $e) {
        $pos_db->rollback();
        throw $e;
    }
}

echo json_encode([
    'overpaid_sales' => count($overpaid),
    'fixed_sales' => $fixedSales,
    'fixed_rows' => $fixedRows,
    'skipped' => $skipped,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
