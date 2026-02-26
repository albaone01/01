<?php

require_once __DIR__ . '/db.php';

function ensure_pos_saas_schema(Database $db): void {
    static $ensured = false;
    if ($ensured) return;

    $sqlFile = __DIR__ . '/../migrations/2026_02_27_pos_saas_core.sql';
    if (!file_exists($sqlFile)) {
        throw new RuntimeException('File migrasi POS SaaS tidak ditemukan.');
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Isi file migrasi POS SaaS tidak valid.');
    }

    // run multi statement migration once per request
    if (!$db->multi_query($sql)) {
        throw new RuntimeException('Gagal menjalankan migrasi POS SaaS.');
    }
    do {
        if ($result = $db->store_result()) {
            $result->free();
        }
    } while ($db->more_results() && $db->next_result());

    $ensured = true;
}

function get_coa_map(Database $db, int $tokoId): array {
    $st = $db->prepare("SELECT kode_akun, akun_id FROM akun_coa WHERE toko_id = ? AND aktif = 1");
    $st->bind_param('i', $tokoId);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    $map = [];
    foreach ($rows as $r) {
        $map[(string)$r['kode_akun']] = (int)$r['akun_id'];
    }
    return $map;
}

function next_journal_number(Database $db, int $tokoId, DateTimeInterface $tanggal): string {
    $prefix = 'JRN-' . $tanggal->format('Ymd') . '-';
    $st = $db->prepare("SELECT COUNT(*) AS c FROM jurnal_umum WHERE toko_id = ? AND tanggal = ?");
    $tgl = $tanggal->format('Y-m-d');
    $st->bind_param('is', $tokoId, $tgl);
    $st->execute();
    $cnt = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
    return $prefix . str_pad((string)($cnt + 1), 4, '0', STR_PAD_LEFT);
}

function insert_journal_entry(
    Database $db,
    int $tokoId,
    int $userId,
    DateTimeInterface $tanggal,
    string $sumber,
    ?string $referensiTabel,
    ?int $referensiId,
    string $keterangan,
    array $lines
): int {
    $totalDebit = 0.0;
    $totalKredit = 0.0;
    foreach ($lines as $ln) {
        $totalDebit += (float)($ln['debit'] ?? 0);
        $totalKredit += (float)($ln['kredit'] ?? 0);
    }
    if (abs($totalDebit - $totalKredit) > 0.0001) {
        throw new RuntimeException('Jurnal tidak balance: debit dan kredit tidak sama.');
    }

    $tgl = $tanggal->format('Y-m-d');
    $nomor = next_journal_number($db, $tokoId, $tanggal);
    $st = $db->prepare("
        INSERT INTO jurnal_umum
        (toko_id, tanggal, nomor_jurnal, sumber, referensi_tabel, referensi_id, keterangan, total_debit, total_kredit, dibuat_oleh)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $st->bind_param(
        'isssssiddi',
        $tokoId,
        $tgl,
        $nomor,
        $sumber,
        $referensiTabel,
        $referensiId,
        $keterangan,
        $totalDebit,
        $totalKredit,
        $userId
    );
    $st->execute();
    $jurnalId = (int)$db->insertId();
    $st->close();

    $stDetail = $db->prepare("INSERT INTO jurnal_detail (jurnal_id, akun_id, deskripsi, debit, kredit) VALUES (?,?,?,?,?)");
    foreach ($lines as $ln) {
        $akunId = (int)$ln['akun_id'];
        $desk = (string)($ln['deskripsi'] ?? '');
        $debit = (float)($ln['debit'] ?? 0);
        $kredit = (float)($ln['kredit'] ?? 0);
        $stDetail->bind_param('iisdd', $jurnalId, $akunId, $desk, $debit, $kredit);
        $stDetail->execute();
    }
    $stDetail->close();

    return $jurnalId;
}

function get_today_shift(Database $db, int $tokoId, int $kasirId): ?array {
    $st = $db->prepare("
        SELECT *
        FROM kasir_shift
        WHERE toko_id = ?
          AND kasir_id = ?
          AND tanggal_shift = CURRENT_DATE()
        LIMIT 1
    ");
    $st->bind_param('ii', $tokoId, $kasirId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function has_open_shift_today(Database $db, int $tokoId, int $kasirId): bool {
    $st = $db->prepare("
        SELECT 1
        FROM kasir_shift
        WHERE toko_id = ?
          AND kasir_id = ?
          AND tanggal_shift = CURRENT_DATE()
          AND status = 'open'
        LIMIT 1
    ");
    $st->bind_param('ii', $tokoId, $kasirId);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_assoc();
    $st->close();
    return $ok;
}
