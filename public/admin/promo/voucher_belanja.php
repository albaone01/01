<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/header.php';

requireLogin();
requireDevice();

$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$db = $pos_db;
$message = '';
$error = '';

try {
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
        UNIQUE KEY uq_voucher_toko_kode (toko_id, kode_voucher),
        KEY idx_voucher_toko (toko_id, aktif, berlaku_dari, berlaku_sampai)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    $error = 'Gagal menyiapkan tabel voucher: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $action = trim((string)($_POST['action'] ?? ''));
    try {
        if ($action === 'save') {
            $id = (int)($_POST['voucher_id'] ?? 0);
            $kode = strtoupper(trim((string)($_POST['kode_voucher'] ?? '')));
            $nama = trim((string)($_POST['nama_voucher'] ?? ''));
            $tipe = trim((string)($_POST['tipe'] ?? 'nominal'));
            $nilai = (float)($_POST['nilai'] ?? 0);
            $minimal = max(0, (float)($_POST['minimal_belanja'] ?? 0));
            $kuota = max(1, (int)($_POST['kuota'] ?? 1));
            $aktif = (int)($_POST['aktif'] ?? 1) === 1 ? 1 : 0;
            $catatan = trim((string)($_POST['catatan'] ?? ''));
            $dari = trim((string)($_POST['berlaku_dari'] ?? ''));
            $sampai = trim((string)($_POST['berlaku_sampai'] ?? ''));

            if ($kode === '' || strlen($kode) < 4) throw new RuntimeException('Kode voucher minimal 4 karakter.');
            if ($nama === '') throw new RuntimeException('Nama voucher wajib diisi.');
            if (!in_array($tipe, ['nominal', 'persen'], true)) throw new RuntimeException('Tipe voucher tidak valid.');
            if ($nilai <= 0) throw new RuntimeException('Nilai voucher harus lebih dari 0.');
            if ($tipe === 'persen' && $nilai > 100) throw new RuntimeException('Voucher persen maksimal 100.');

            $dtDari = date_create($dari);
            $dtSampai = date_create($sampai);
            if (!$dtDari || !$dtSampai) throw new RuntimeException('Periode voucher tidak valid.');
            $dariDb = $dtDari->format('Y-m-d H:i:s');
            $sampaiDb = $dtSampai->format('Y-m-d H:i:s');
            if (strtotime($sampaiDb) < strtotime($dariDb)) throw new RuntimeException('Tanggal selesai harus >= tanggal mulai.');

            if ($id > 0) {
                $st = $db->prepare("UPDATE voucher_belanja
                    SET kode_voucher=?, nama_voucher=?, tipe=?, nilai=?, minimal_belanja=?, kuota=?, berlaku_dari=?, berlaku_sampai=?, aktif=?, catatan=?
                    WHERE voucher_id=? AND toko_id=? AND deleted_at IS NULL");
                $st->bind_param('sssddissisii', $kode, $nama, $tipe, $nilai, $minimal, $kuota, $dariDb, $sampaiDb, $aktif, $catatan, $id, $tokoId);
                $st->execute();
                $st->close();
                $message = 'Voucher berhasil diperbarui.';
            } else {
                $st = $db->prepare("INSERT INTO voucher_belanja (toko_id, kode_voucher, nama_voucher, tipe, nilai, minimal_belanja, kuota, berlaku_dari, berlaku_sampai, aktif, catatan)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $st->bind_param('isssddissis', $tokoId, $kode, $nama, $tipe, $nilai, $minimal, $kuota, $dariDb, $sampaiDb, $aktif, $catatan);
                $st->execute();
                $st->close();
                $message = 'Voucher berhasil ditambahkan.';
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['voucher_id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('Voucher tidak valid.');
            $st = $db->prepare("UPDATE voucher_belanja SET deleted_at=NOW(), aktif=0 WHERE voucher_id=? AND toko_id=?");
            $st->bind_param('ii', $id, $tokoId);
            $st->execute();
            $st->close();
            $message = 'Voucher dihapus.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$where = "WHERE toko_id=? AND deleted_at IS NULL";
$types = 'i';
$params = [$tokoId];
if ($q !== '') {
    $where .= " AND (kode_voucher LIKE CONCAT('%',?,'%') OR nama_voucher LIKE CONCAT('%',?,'%'))";
    $types .= 'ss';
    $params[] = $q;
    $params[] = $q;
}

$st = $db->prepare("SELECT voucher_id, kode_voucher, nama_voucher, tipe, nilai, minimal_belanja, kuota, terpakai, berlaku_dari, berlaku_sampai, aktif, catatan
                    FROM voucher_belanja $where ORDER BY dibuat_pada DESC");
$st->bind_param($types, ...$params);
$st->execute();
$vouchers = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$now = date('Y-m-d H:i:s');
$totalAktif = 0;
foreach ($vouchers as $v) {
    if ((int)$v['aktif'] === 1 && $now >= $v['berlaku_dari'] && $now <= $v['berlaku_sampai']) $totalAktif++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Voucher Belanja</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
:root{--primary:#0f766e;--ink:#0f172a;--muted:#64748b;--border:#e2e8f0;--card:#fff;--bg:#f5f7fb;--shadow:0 14px 40px rgba(15,23,42,.08);}
*{box-sizing:border-box}body{margin:0;font-family:'Plus Jakarta Sans','Inter',system-ui;background:var(--bg);color:var(--ink)}
.page{padding:28px 20px 48px}.container{max-width:1200px;margin:0 auto}
.hero{background:linear-gradient(135deg,#134e4a 0%,#0f766e 70%,#2dd4bf 100%);color:#f0fdfa;border-radius:18px;padding:22px 24px;box-shadow:var(--shadow)}
.hero h1{margin:4px 0 8px;font-size:26px}.hero p{margin:0 0 14px;color:rgba(255,255,255,.9)}
.btn{padding:10px 14px;border-radius:10px;border:1px solid transparent;font-weight:700;cursor:pointer;text-decoration:none}.btn-primary{background:#fff;color:#0f172a}.btn-outline{background:#fff;color:#0f172a;border-color:var(--border)}
.metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}.metric{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.25);border-radius:12px;padding:12px 14px}
.metric small{color:rgba(255,255,255,.8);letter-spacing:.06em;font-weight:700}.metric-value{font-size:20px;font-weight:800;margin-top:6px}
.panel{margin-top:18px;background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden}
.toolbar{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:center;flex-wrap:wrap}.toolbar input{padding:10px 12px;border:1px solid var(--border);border-radius:10px;min-width:260px}
.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:14px}thead th{background:#f8fafc;padding:12px 14px;text-align:left;color:var(--muted);border-bottom:1px solid var(--border)}
tbody td{padding:12px 14px;border-bottom:1px solid #eef2f6}tbody tr:hover td{background:#f8fbff}
.badge{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}.ok{background:#dcfce7;color:#166534}.off{background:#fee2e2;color:#991b1b}
.alert{margin-top:16px;padding:10px 12px;border-radius:10px}.alert-ok{background:#dcfce7;color:#166534}.alert-err{background:#fee2e2;color:#991b1b}
.modal{display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);align-items:center;justify-content:center;z-index:1000}
.modal-box{background:#fff;border-radius:14px;width:92%;max-width:720px;box-shadow:0 18px 40px rgba(15,23,42,.18);overflow:hidden}
.modal-header{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
.modal-body{padding:16px;display:grid;gap:12px}.modal-body label{display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:6px}
.modal-body input,.modal-body select,.modal-body textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px}
.grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.modal-footer{padding:14px 16px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end}
@media(max-width:760px){.metrics{grid-template-columns:1fr}.grid2{grid-template-columns:1fr}.toolbar{flex-direction:column;align-items:stretch}}
</style>
</head>
<body>
<div class="page">
  <div class="container">
    <div class="hero">
      <p style="margin:0;letter-spacing:.08em;font-weight:800">PROMO</p>
      <h1>Voucher Belanja</h1>
      <p>Kelola voucher diskon berbasis kode, periode, kuota, dan minimal belanja.</p>
      <button class="btn btn-primary" onclick="openModal()">+ Voucher Baru</button>
      <div class="metrics">
        <div class="metric"><small>Total Voucher</small><div class="metric-value"><?=count($vouchers)?></div></div>
        <div class="metric"><small>Aktif Sekarang</small><div class="metric-value"><?=$totalAktif?></div></div>
        <div class="metric"><small>Pencarian</small><div class="metric-value"><?=$q !== '' ? htmlspecialchars($q) : 'Semua'?></div></div>
      </div>
    </div>

    <?php if ($message): ?><div class="alert alert-ok"><?=htmlspecialchars($message)?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-err"><?=htmlspecialchars($error)?></div><?php endif; ?>

    <div class="panel">
      <form class="toolbar" method="get">
        <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari kode / nama voucher...">
        <button class="btn btn-outline" type="submit">Cari</button>
      </form>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Nilai</th><th>Minimal Belanja</th><th>Kuota</th><th>Periode</th><th>Status</th><th style="text-align:right">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($vouchers as $v): ?>
            <?php
              $isActiveNow = ((int)$v['aktif'] === 1 && $now >= $v['berlaku_dari'] && $now <= $v['berlaku_sampai']);
              $nilaiText = $v['tipe'] === 'persen' ? number_format((float)$v['nilai'], 2) . '%' : 'Rp ' . number_format((float)$v['nilai'], 0, ',', '.');
            ?>
            <tr>
              <td><strong><?=htmlspecialchars($v['kode_voucher'])?></strong></td>
              <td><?=htmlspecialchars($v['nama_voucher'])?></td>
              <td><?=htmlspecialchars($v['tipe'])?></td>
              <td><?=$nilaiText?></td>
              <td>Rp <?=number_format((float)$v['minimal_belanja'], 0, ',', '.')?></td>
              <td><?=number_format((int)$v['terpakai'])?> / <?=number_format((int)$v['kuota'])?></td>
              <td><?=date('d M Y H:i', strtotime($v['berlaku_dari']))?> - <?=date('d M Y H:i', strtotime($v['berlaku_sampai']))?></td>
              <td><span class="badge <?=$isActiveNow ? 'ok' : 'off'?>"><?=$isActiveNow ? 'Aktif' : 'Nonaktif'?></span></td>
              <td style="text-align:right">
                <button class="btn btn-outline" type="button" onclick='editVoucher(<?=json_encode($v, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>)'>Edit</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus voucher ini?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="voucher_id" value="<?=$v['voucher_id']?>">
                  <button class="btn btn-outline" type="submit" style="color:#b91c1c">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$vouchers): ?><tr><td colspan="9" style="text-align:center;color:var(--muted);padding:20px">Belum ada voucher</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="voucherModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modalTitle" style="margin:0">Voucher Baru</h3>
      <span style="cursor:pointer;font-size:22px" onclick="closeModal()">&times;</span>
    </div>
    <form method="post" id="voucherForm">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="voucher_id">
      <div class="modal-body">
        <div class="grid2">
          <div><label>Kode Voucher</label><input type="text" name="kode_voucher" required></div>
          <div><label>Nama Voucher</label><input type="text" name="nama_voucher" required></div>
        </div>
        <div class="grid2">
          <div><label>Tipe</label><select name="tipe" onchange="syncTipe()"><option value="nominal">Nominal</option><option value="persen">Persen</option></select></div>
          <div><label>Nilai</label><input type="number" name="nilai" min="0.01" step="0.01" required></div>
        </div>
        <div class="grid2">
          <div><label>Minimal Belanja</label><input type="number" name="minimal_belanja" min="0" step="0.01" value="0"></div>
          <div><label>Kuota</label><input type="number" name="kuota" min="1" step="1" value="1" required></div>
        </div>
        <div class="grid2">
          <div><label>Berlaku Dari</label><input type="datetime-local" name="berlaku_dari" required></div>
          <div><label>Berlaku Sampai</label><input type="datetime-local" name="berlaku_sampai" required></div>
        </div>
        <div class="grid2">
          <div><label>Status</label><select name="aktif"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
          <div><label>Catatan</label><input type="text" name="catatan"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" type="button" onclick="closeModal()">Batal</button>
        <button class="btn btn-primary" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById('voucherModal');
const form = document.getElementById('voucherForm');
function toLocalInputValue(v){
  if(!v) return '';
  const d = new Date(v.replace(' ', 'T'));
  if (isNaN(d.getTime())) return '';
  const off = d.getTimezoneOffset();
  const local = new Date(d.getTime() - (off * 60000));
  return local.toISOString().slice(0,16);
}
function syncTipe(){
  if(form.tipe.value === 'persen'){
    form.nilai.max = '100';
  } else {
    form.nilai.removeAttribute('max');
  }
}
function generateCode(){
  const dt = new Date();
  const y = dt.getFullYear().toString().slice(-2);
  const m = String(dt.getMonth()+1).padStart(2,'0');
  const d = String(dt.getDate()).padStart(2,'0');
  const r = Math.random().toString(36).slice(2,6).toUpperCase();
  return `VCR${y}${m}${d}${r}`;
}
function openModal(){
  form.reset();
  form.voucher_id.value = '';
  form.kode_voucher.value = generateCode();
  const now = new Date();
  const plus30 = new Date(now.getTime() + (30*24*60*60*1000));
  form.berlaku_dari.value = toLocalInputValue(now.toISOString());
  form.berlaku_sampai.value = toLocalInputValue(plus30.toISOString());
  form.kuota.value = '1';
  form.minimal_belanja.value = '0';
  form.aktif.value = '1';
  document.getElementById('modalTitle').innerText = 'Voucher Baru';
  syncTipe();
  modal.style.display = 'flex';
}
function editVoucher(v){
  form.voucher_id.value = v.voucher_id || '';
  form.kode_voucher.value = v.kode_voucher || '';
  form.nama_voucher.value = v.nama_voucher || '';
  form.tipe.value = v.tipe || 'nominal';
  form.nilai.value = Number(v.nilai || 0);
  form.minimal_belanja.value = Number(v.minimal_belanja || 0);
  form.kuota.value = Number(v.kuota || 1);
  form.berlaku_dari.value = toLocalInputValue(v.berlaku_dari || '');
  form.berlaku_sampai.value = toLocalInputValue(v.berlaku_sampai || '');
  form.aktif.value = String(Number(v.aktif || 0));
  form.catatan.value = v.catatan || '';
  document.getElementById('modalTitle').innerText = 'Edit Voucher';
  syncTipe();
  modal.style.display = 'flex';
}
function closeModal(){ modal.style.display = 'none'; }
</script>
</body>
</html>
