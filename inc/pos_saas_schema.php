<?php

require_once __DIR__ . '/db.php';

function ensure_pos_saas_schema(Database $db): void {
    static $ensured = false;
    if ($ensured) return;

    // Backfill schema drift on existing installs before FK/index checks.
    ensure_shift_compat_schema($db);

    // Fast path: schema already present, skip running migration SQL each request
    if (
        has_table_exists($db, 'akun_coa') &&
        has_table_exists($db, 'jurnal_umum') &&
        has_table_exists($db, 'jurnal_detail') &&
        has_table_exists($db, 'kasir_shift') &&
        has_table_exists($db, 'jurnal_counter') &&
        has_table_exists($db, 'shift_template') &&
        has_table_exists($db, 'cash_movement') &&
        has_table_column($db, 'pembayaran', 'uang_diterima') &&
        has_table_column($db, 'pembayaran', 'kembalian') &&
        has_table_column($db, 'kasir_shift', 'shift_template_id') &&
        has_table_column($db, 'kasir_shift', 'jam_buka_real') &&
        has_table_column($db, 'kasir_shift', 'jam_tutup_real') &&
        has_table_column($db, 'penjualan', 'shift_id')
    ) {
        ensure_shift_fk_indexes($db);
        $ensured = true;
        return;
    }

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

    ensure_shift_compat_schema($db);
    ensure_shift_fk_indexes($db);

    $ensured = true;
}

function has_table_exists(Database $db, string $table): bool {
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

function has_table_column(Database $db, string $table, string $column): bool {
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

function has_index(Database $db, string $table, string $indexName): bool {
    $st = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
        LIMIT 1
    ");
    $st->bind_param('ss', $table, $indexName);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_assoc();
    $st->close();
    return $ok;
}

function has_fk_constraint(Database $db, string $table, string $constraintName): bool {
    $st = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND CONSTRAINT_NAME = ?
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        LIMIT 1
    ");
    $st->bind_param('ss', $table, $constraintName);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_assoc();
    $st->close();
    return $ok;
}

function ensure_shift_fk_indexes(Database $db): void {
    // allow multi-shift per day; remove old unique constraint if exists
    if (has_table_exists($db, 'kasir_shift') && has_index($db, 'kasir_shift', 'uq_shift_harian')) {
        $db->query("ALTER TABLE kasir_shift DROP INDEX uq_shift_harian");
    }
    if (has_table_exists($db, 'penjualan') && has_table_column($db, 'penjualan', 'shift_id') && !has_index($db, 'penjualan', 'idx_penjualan_shift')) {
        $db->query("ALTER TABLE penjualan ADD INDEX idx_penjualan_shift (shift_id)");
    }
    if (
        has_table_exists($db, 'penjualan') &&
        has_table_exists($db, 'kasir_shift') &&
        has_table_column($db, 'penjualan', 'shift_id') &&
        !has_fk_constraint($db, 'penjualan', 'fk_penjualan_shift')
    ) {
        $db->query("ALTER TABLE penjualan ADD CONSTRAINT fk_penjualan_shift FOREIGN KEY (shift_id) REFERENCES kasir_shift (shift_id) ON DELETE SET NULL");
    }
    if (
        has_table_exists($db, 'kasir_shift') &&
        has_table_exists($db, 'shift_template') &&
        has_table_column($db, 'kasir_shift', 'shift_template_id') &&
        !has_fk_constraint($db, 'kasir_shift', 'fk_kasir_shift_template')
    ) {
        $db->query("ALTER TABLE kasir_shift ADD CONSTRAINT fk_kasir_shift_template FOREIGN KEY (shift_template_id) REFERENCES shift_template (template_id) ON DELETE SET NULL");
    }
}

function ensure_shift_compat_schema(Database $db): void {
    if (has_table_exists($db, 'kasir_shift')) {
        if (!has_table_column($db, 'kasir_shift', 'shift_template_id')) {
            $db->query("ALTER TABLE kasir_shift ADD COLUMN shift_template_id BIGINT DEFAULT NULL AFTER device_id");
        }
        if (!has_table_column($db, 'kasir_shift', 'jam_buka_real')) {
            $db->query("ALTER TABLE kasir_shift ADD COLUMN jam_buka_real DATETIME DEFAULT NULL AFTER tanggal_shift");
        }
        if (!has_table_column($db, 'kasir_shift', 'jam_tutup_real')) {
            $db->query("ALTER TABLE kasir_shift ADD COLUMN jam_tutup_real DATETIME DEFAULT NULL AFTER jam_buka_real");
        }
        if (!has_index($db, 'kasir_shift', 'idx_shift_template')) {
            $db->query("ALTER TABLE kasir_shift ADD INDEX idx_shift_template (shift_template_id)");
        }
    }

    if (has_table_exists($db, 'penjualan') && !has_table_column($db, 'penjualan', 'shift_id')) {
        $db->query("ALTER TABLE penjualan ADD COLUMN shift_id BIGINT DEFAULT NULL AFTER gudang_id");
    }

    if (has_table_exists($db, 'pembayaran')) {
        if (!has_table_column($db, 'pembayaran', 'uang_diterima')) {
            $db->query("ALTER TABLE pembayaran ADD COLUMN uang_diterima DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER jumlah");
        }
        if (!has_table_column($db, 'pembayaran', 'kembalian')) {
            $db->query("ALTER TABLE pembayaran ADD COLUMN kembalian DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER uang_diterima");
        }
    }

    if (!has_table_exists($db, 'shift_template')) {
        $db->query("
            CREATE TABLE shift_template (
              template_id BIGINT NOT NULL AUTO_INCREMENT,
              toko_id BIGINT NOT NULL,
              nama_shift VARCHAR(80) NOT NULL,
              jam_mulai TIME NOT NULL,
              jam_selesai TIME NOT NULL,
              urutan INT NOT NULL DEFAULT 1,
              aktif TINYINT(1) NOT NULL DEFAULT 1,
              PRIMARY KEY (template_id),
              UNIQUE KEY uq_shift_template (toko_id, nama_shift),
              KEY idx_shift_template_toko (toko_id, aktif),
              CONSTRAINT fk_shift_template_toko FOREIGN KEY (toko_id) REFERENCES toko (toko_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    if (!has_table_exists($db, 'shift_template_assignment')) {
        $db->query("
            CREATE TABLE shift_template_assignment (
              assignment_id BIGINT NOT NULL AUTO_INCREMENT,
              toko_id BIGINT NOT NULL,
              kasir_id BIGINT NOT NULL,
              template_id BIGINT NOT NULL,
              aktif TINYINT(1) NOT NULL DEFAULT 1,
              dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (assignment_id),
              UNIQUE KEY uq_shift_assignment (toko_id, kasir_id, template_id),
              KEY idx_shift_assignment_kasir (toko_id, kasir_id, aktif),
              CONSTRAINT fk_shift_assignment_toko FOREIGN KEY (toko_id) REFERENCES toko (toko_id) ON DELETE CASCADE,
              CONSTRAINT fk_shift_assignment_kasir FOREIGN KEY (kasir_id) REFERENCES pengguna (pengguna_id) ON DELETE CASCADE,
              CONSTRAINT fk_shift_assignment_template FOREIGN KEY (template_id) REFERENCES shift_template (template_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    if (
        !has_table_exists($db, 'cash_movement') &&
        has_table_exists($db, 'kasir_shift')
    ) {
        $db->query("
            CREATE TABLE cash_movement (
              movement_id BIGINT NOT NULL AUTO_INCREMENT,
              toko_id BIGINT NOT NULL,
              shift_id BIGINT NOT NULL,
              kasir_id BIGINT NOT NULL,
              tipe ENUM('in','out') NOT NULL,
              kategori VARCHAR(100) NOT NULL,
              jumlah DECIMAL(15,2) NOT NULL,
              catatan VARCHAR(255) DEFAULT NULL,
              dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (movement_id),
              KEY idx_cash_move_shift (shift_id),
              KEY idx_cash_move_toko (toko_id, dibuat_pada),
              CONSTRAINT fk_cash_move_toko FOREIGN KEY (toko_id) REFERENCES toko (toko_id) ON DELETE CASCADE,
              CONSTRAINT fk_cash_move_shift FOREIGN KEY (shift_id) REFERENCES kasir_shift (shift_id) ON DELETE CASCADE,
              CONSTRAINT fk_cash_move_kasir FOREIGN KEY (kasir_id) REFERENCES pengguna (pengguna_id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    $db->query("INSERT IGNORE INTO shift_template (toko_id, nama_shift, jam_mulai, jam_selesai, urutan, aktif) SELECT toko_id, 'Pagi', '08:00:00', '13:00:00', 1, 1 FROM toko WHERE deleted_at IS NULL");
    $db->query("INSERT IGNORE INTO shift_template (toko_id, nama_shift, jam_mulai, jam_selesai, urutan, aktif) SELECT toko_id, 'Siang', '13:00:00', '17:00:00', 2, 1 FROM toko WHERE deleted_at IS NULL");
    $db->query("INSERT IGNORE INTO shift_template (toko_id, nama_shift, jam_mulai, jam_selesai, urutan, aktif) SELECT toko_id, 'Malam', '17:00:00', '21:00:00', 3, 1 FROM toko WHERE deleted_at IS NULL");
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
    $tgl = $tanggal->format('Y-m-d');

    // Ensure row exists
    $stInit = $db->prepare("
        INSERT INTO jurnal_counter (toko_id, tanggal, last_seq)
        VALUES (?, ?, 0)
        ON DUPLICATE KEY UPDATE last_seq = last_seq
    ");
    $stInit->bind_param('is', $tokoId, $tgl);
    $stInit->execute();
    $stInit->close();

    // Lock counter row for this toko+date to prevent race
    $stLock = $db->prepare("SELECT last_seq FROM jurnal_counter WHERE toko_id = ? AND tanggal = ? FOR UPDATE");
    $stLock->bind_param('is', $tokoId, $tgl);
    $stLock->execute();
    $row = $stLock->get_result()->fetch_assoc();
    $stLock->close();

    $next = ((int)($row['last_seq'] ?? 0)) + 1;
    $stUpd = $db->prepare("UPDATE jurnal_counter SET last_seq = ? WHERE toko_id = ? AND tanggal = ?");
    $stUpd->bind_param('iis', $next, $tokoId, $tgl);
    $stUpd->execute();
    $stUpd->close();

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
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
        'issssisddi',
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

function get_open_shift(Database $db, int $tokoId, int $kasirId): ?array {
    $st = $db->prepare("
        SELECT *
        FROM kasir_shift
        WHERE toko_id = ?
          AND kasir_id = ?
          AND status = 'open'
        ORDER BY shift_id DESC
        LIMIT 1
    ");
    $st->bind_param('ii', $tokoId, $kasirId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function has_open_shift_today(Database $db, int $tokoId, int $kasirId): bool {
    return (bool)get_open_shift($db, $tokoId, $kasirId);
}

function get_available_shift_templates(Database $db, int $tokoId, int $kasirId): array {
    $hasAssignmentRows = false;
    if (has_table_exists($db, 'shift_template_assignment')) {
        $st = $db->prepare("
            SELECT 1
            FROM shift_template_assignment
            WHERE toko_id = ?
              AND kasir_id = ?
              AND aktif = 1
            LIMIT 1
        ");
        $st->bind_param('ii', $tokoId, $kasirId);
        $st->execute();
        $hasAssignmentRows = (bool)$st->get_result()->fetch_assoc();
        $st->close();
    }

    if ($hasAssignmentRows) {
        $st = $db->prepare("
            SELECT t.template_id, t.nama_shift, t.jam_mulai, t.jam_selesai
            FROM shift_template t
            INNER JOIN shift_template_assignment a
                ON a.template_id = t.template_id
               AND a.toko_id = t.toko_id
               AND a.aktif = 1
            WHERE t.toko_id = ?
              AND t.aktif = 1
              AND a.kasir_id = ?
            ORDER BY t.urutan, t.jam_mulai, t.template_id
        ");
        $st->bind_param('ii', $tokoId, $kasirId);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        return $rows;
    }

    $st = $db->prepare("
        SELECT template_id, nama_shift, jam_mulai, jam_selesai
        FROM shift_template
        WHERE toko_id = ?
          AND aktif = 1
        ORDER BY urutan, jam_mulai, template_id
    ");
    $st->bind_param('i', $tokoId);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
