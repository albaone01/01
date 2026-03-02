<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/header.php';

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$db = $pos_db;
$message = '';
$error = '';

try {
    $db->query("CREATE TABLE IF NOT EXISTS promo_supplier (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        promo_id BIGINT NOT NULL,
        supplier_id BIGINT NOT NULL,
        UNIQUE KEY uq_promo_supplier (promo_id, supplier_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS promo_kategori (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        promo_id BIGINT NOT NULL,
        kategori_id BIGINT NOT NULL,
        UNIQUE KEY uq_promo_kategori (promo_id, kategori_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS promo_member_produk (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        promo_id BIGINT NOT NULL,
        produk_id BIGINT NOT NULL,
        UNIQUE KEY uq_promo_member_produk (promo_id, produk_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS promo_member_supplier (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        promo_id BIGINT NOT NULL,
        supplier_id BIGINT NOT NULL,
        UNIQUE KEY uq_promo_member_supplier (promo_id, supplier_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS promo_member_kategori (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        promo_id BIGINT NOT NULL,
        kategori_id BIGINT NOT NULL,
        UNIQUE KEY uq_promo_member_kategori (promo_id, kategori_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS voucher_belanja (
        voucher_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        toko_id BIGINT NOT NULL,
        kode_voucher VARCHAR(40) NOT NULL,
        nama_voucher VARCHAR(120) NOT NULL,
        tipe ENUM('nominal','persen') NOT NULL DEFAULT 'nominal',
        nilai DECIMAL(15,2) NOT NULL DEFAULT 0,
        minimal_belanja DECIMAL(15,2) NOT NULL DEFAULT 0,
        kuota INT NOT NULL DEFAULT 1,
        terpakai INT NOT NULL DEFAULT 0,
        berlaku_dari DATETIME NOT NULL,
        berlaku_sampai DATETIME NOT NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        catatan VARCHAR(255) DEFAULT NULL,
        dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY uq_voucher_toko_kode (toko_id, kode_voucher)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS promo_bersyarat (
        rule_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        toko_id BIGINT NOT NULL,
        nama_rule VARCHAR(120) NOT NULL,
        minimal_belanja DECIMAL(15,2) NOT NULL DEFAULT 0,
        minimal_qty INT NOT NULL DEFAULT 0,
        minimal_item INT NOT NULL DEFAULT 0,
        tipe_hadiah ENUM('persen','nominal') NOT NULL DEFAULT 'nominal',
        nilai_hadiah DECIMAL(15,2) NOT NULL DEFAULT 0,
        max_diskon DECIMAL(15,2) NOT NULL DEFAULT 0,
        berlaku_dari DATETIME NOT NULL,
        berlaku_sampai DATETIME NOT NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        KEY idx_rule_toko (toko_id, aktif)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS promo_regular_process_log (
        process_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        toko_id BIGINT NOT NULL,
        diproses_oleh BIGINT NOT NULL,
        total_promo_aktif INT NOT NULL DEFAULT 0,
        total_map_umum INT NOT NULL DEFAULT 0,
        total_map_member INT NOT NULL DEFAULT 0,
        total_voucher_aktif INT NOT NULL DEFAULT 0,
        total_rule_aktif INT NOT NULL DEFAULT 0,
        catatan VARCHAR(255) DEFAULT NULL,
        dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_prpl_toko (toko_id, dibuat_pada)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    $error = 'Gagal menyiapkan tabel: ' . $e->getMessage();
}

function scalarCount(Database $db, string $sql, int $tokoId): int {
    $st = $db->prepare($sql);
    $st->bind_param('i', $tokoId);
    $st->execute();
    $rw = $st->get_result()->fetch_assoc();
    $st->close();
    return (int)($rw['c'] ?? 0);
}

$now = date('Y-m-d H:i:s');

$totalPromoAktif = scalarCount($db, "SELECT COUNT(*) c FROM promo WHERE toko_id=? AND deleted_at IS NULL AND aktif=1 AND NOW() BETWEEN berlaku_dari AND berlaku_sampai", $tokoId);
$totalMapProduk = scalarCount($db, "SELECT COUNT(*) c FROM promo_produk pp JOIN promo p ON p.promo_id=pp.promo_id WHERE p.toko_id=? AND p.deleted_at IS NULL", $tokoId);
$totalMapSupplier = scalarCount($db, "SELECT COUNT(*) c FROM promo_supplier ps JOIN promo p ON p.promo_id=ps.promo_id WHERE p.toko_id=? AND p.deleted_at IS NULL", $tokoId);
$totalMapKategori = scalarCount($db, "SELECT COUNT(*) c FROM promo_kategori pk JOIN promo p ON p.promo_id=pk.promo_id WHERE p.toko_id=? AND p.deleted_at IS NULL", $tokoId);
$totalMemberProduk = scalarCount($db, "SELECT COUNT(*) c FROM promo_member_produk mp JOIN promo p ON p.promo_id=mp.promo_id WHERE p.toko_id=? AND p.deleted_at IS NULL", $tokoId);
$totalMemberSupplier = scalarCount($db, "SELECT COUNT(*) c FROM promo_member_supplier ms JOIN promo p ON p.promo_id=ms.promo_id WHERE p.toko_id=? AND p.deleted_at IS NULL", $tokoId);
$totalMemberKategori = scalarCount($db, "SELECT COUNT(*) c FROM promo_member_kategori mk JOIN promo p ON p.promo_id=mk.promo_id WHERE p.toko_id=? AND p.deleted_at IS NULL", $tokoId);
$totalVoucherAktif = scalarCount($db, "SELECT COUNT(*) c FROM voucher_belanja WHERE toko_id=? AND deleted_at IS NULL AND aktif=1 AND NOW() BETWEEN berlaku_dari AND berlaku_sampai", $tokoId);
$totalRuleAktif = scalarCount($db, "SELECT COUNT(*) c FROM promo_bersyarat WHERE toko_id=? AND deleted_at IS NULL AND aktif=1 AND NOW() BETWEEN berlaku_dari AND berlaku_sampai", $tokoId);

$totalMapUmum = $totalMapProduk + $totalMapSupplier + $totalMapKategori;
$totalMapMember = $totalMemberProduk + $totalMemberSupplier + $totalMemberKategori;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'run') {
        $catatan = trim((string)($_POST['catatan'] ?? ''));
        try {
            $st = $db->prepare("INSERT INTO promo_regular_process_log
                (toko_id, diproses_oleh, total_promo_aktif, total_map_umum, total_map_member, total_voucher_aktif, total_rule_aktif, catatan)
                VALUES (?,?,?,?,?,?,?,?)");
            $st->bind_param('iiiiiiis', $tokoId, $userId, $totalPromoAktif, $totalMapUmum, $totalMapMember, $totalVoucherAktif, $totalRuleAktif, $catatan);
            $st->execute();
            $st->close();
            $message = 'Proses diskon regular berhasil dijalankan dan tercatat.';
        } catch (Throwable $e) {
            $error = 'Gagal menjalankan proses: ' . $e->getMessage();
        }
    }
}

$logs = [];
$ls = $db->prepare("SELECT process_id, diproses_oleh, total_promo_aktif, total_map_umum, total_map_member, total_voucher_aktif, total_rule_aktif, catatan, dibuat_pada
                    FROM promo_regular_process_log WHERE toko_id=? ORDER BY process_id DESC LIMIT 50");
$ls->bind_param('i', $tokoId);
$ls->execute();
$rs = $ls->get_result();
if ($rs) $logs = $rs->fetch_all(MYSQLI_ASSOC);
$ls->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proses Disc. Regular</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
:root{--ink:#0f172a;--muted:#64748b;--border:#e2e8f0;--card:#fff;--bg:#f5f7fb;--shadow:0 14px 40px rgba(15,23,42,.08);}
*{box-sizing:border-box}body{margin:0;font-family:'Plus Jakarta Sans','Inter',system-ui;background:var(--bg);color:var(--ink)}
.page{padding:28px 20px 48px}.container{max-width:1200px;margin:0 auto}
.hero{background:linear-gradient(135deg,#111827 0%,#1d4ed8 70%,#60a5fa 100%);color:#eff6ff;border-radius:18px;padding:22px 24px;box-shadow:var(--shadow)}
.hero h1{margin:4px 0 8px;font-size:26px}.hero p{margin:0 0 14px;color:rgba(255,255,255,.9)}
.btn{padding:10px 14px;border-radius:10px;border:1px solid transparent;font-weight:700;cursor:pointer;text-decoration:none}.btn-primary{background:#fff;color:#0f172a}.btn-outline{background:#fff;color:#0f172a;border-color:var(--border)}
.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:12px}.metric{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.25);border-radius:12px;padding:12px 14px}
.metric small{color:rgba(255,255,255,.8);letter-spacing:.06em;font-weight:700}.metric-value{font-size:20px;font-weight:800;margin-top:6px}
.panel{margin-top:18px;background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden}
.panel-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.panel-body{padding:16px}.panel-body textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;min-height:80px}
.alert{margin-top:16px;padding:10px 12px;border-radius:10px}.alert-ok{background:#dcfce7;color:#166534}.alert-err{background:#fee2e2;color:#991b1b}
.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:14px}thead th{background:#f8fafc;padding:12px 14px;text-align:left;color:var(--muted);border-bottom:1px solid var(--border)}
tbody td{padding:12px 14px;border-bottom:1px solid #eef2f6}
@media(max-width:760px){.metrics{grid-template-columns:repeat(2,minmax(0,1fr));}}
</style>
</head>
<body>
<div class="page">
  <div class="container">
    <div class="hero">
      <p style="margin:0;letter-spacing:.08em;font-weight:800">PROMO ENGINE</p>
      <h1>Proses Disc. Regular</h1>
      <p>Menjalankan proses sinkronisasi indikator promo regular berdasarkan data mapping saat ini.</p>
      <div class="metrics">
        <div class="metric"><small>Promo Aktif</small><div class="metric-value"><?=$totalPromoAktif?></div></div>
        <div class="metric"><small>Mapping Umum</small><div class="metric-value"><?=$totalMapUmum?></div></div>
        <div class="metric"><small>Mapping Member</small><div class="metric-value"><?=$totalMapMember?></div></div>
        <div class="metric"><small>Voucher + Rule Aktif</small><div class="metric-value"><?=$totalVoucherAktif + $totalRuleAktif?></div></div>
      </div>
    </div>

    <?php if ($message): ?><div class="alert alert-ok"><?=htmlspecialchars($message)?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-err"><?=htmlspecialchars($error)?></div><?php endif; ?>

    <div class="panel">
      <div class="panel-head"><strong>Jalankan Proses</strong><span style="color:var(--muted);font-size:13px">Waktu server: <?=date('d M Y H:i:s')?></span></div>
      <div class="panel-body">
        <form method="post">
          <input type="hidden" name="action" value="run">
          <label style="display:block;font-size:13px;color:#334155;font-weight:700;margin-bottom:6px">Catatan Proses</label>
          <textarea name="catatan" placeholder="Contoh: Proses setelah update promo member bulan ini"></textarea>
          <div style="margin-top:12px;display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-outline" type="button" onclick="location.reload()">Refresh Angka</button>
            <button class="btn btn-primary" type="submit">Proses Sekarang</button>
          </div>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head"><strong>Riwayat Proses</strong><span style="color:var(--muted);font-size:13px">50 proses terakhir</span></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Dibuat Pada</th><th>Promo Aktif</th><th>Map Umum</th><th>Map Member</th><th>Voucher Aktif</th><th>Rule Aktif</th><th>Catatan</th></tr></thead>
          <tbody>
            <?php foreach($logs as $r): ?>
            <tr>
              <td>#<?=$r['process_id']?></td>
              <td><?=date('d M Y H:i:s', strtotime($r['dibuat_pada']))?></td>
              <td><?=number_format((int)$r['total_promo_aktif'])?></td>
              <td><?=number_format((int)$r['total_map_umum'])?></td>
              <td><?=number_format((int)$r['total_map_member'])?></td>
              <td><?=number_format((int)$r['total_voucher_aktif'])?></td>
              <td><?=number_format((int)$r['total_rule_aktif'])?></td>
              <td><?=htmlspecialchars($r['catatan'] ?: '-')?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="8" style="text-align:center;color:var(--muted);padding:20px">Belum ada proses dijalankan</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
