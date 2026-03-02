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
        KEY idx_rule_toko (toko_id, aktif, berlaku_dari, berlaku_sampai)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    $error = 'Gagal menyiapkan tabel rule: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $action = trim((string)($_POST['action'] ?? ''));
    try {
        if ($action === 'save') {
            $id = (int)($_POST['rule_id'] ?? 0);
            $nama = trim((string)($_POST['nama_rule'] ?? ''));
            $minimalBelanja = max(0, (float)($_POST['minimal_belanja'] ?? 0));
            $minimalQty = max(0, (int)($_POST['minimal_qty'] ?? 0));
            $minimalItem = max(0, (int)($_POST['minimal_item'] ?? 0));
            $tipe = trim((string)($_POST['tipe_hadiah'] ?? 'nominal'));
            $nilai = (float)($_POST['nilai_hadiah'] ?? 0);
            $maxDiskon = max(0, (float)($_POST['max_diskon'] ?? 0));
            $aktif = (int)($_POST['aktif'] ?? 1) === 1 ? 1 : 0;
            $dari = trim((string)($_POST['berlaku_dari'] ?? ''));
            $sampai = trim((string)($_POST['berlaku_sampai'] ?? ''));

            if ($nama === '') throw new RuntimeException('Nama rule wajib diisi.');
            if (!in_array($tipe, ['persen','nominal'], true)) throw new RuntimeException('Tipe hadiah tidak valid.');
            if ($nilai <= 0) throw new RuntimeException('Nilai hadiah harus lebih dari 0.');
            if ($tipe === 'persen' && $nilai > 100) throw new RuntimeException('Hadiah persen maksimal 100.');
            if ($minimalBelanja <= 0 && $minimalQty <= 0 && $minimalItem <= 0) {
                throw new RuntimeException('Minimal salah satu syarat harus diisi (belanja/qty/item).');
            }

            $dtDari = date_create($dari);
            $dtSampai = date_create($sampai);
            if (!$dtDari || !$dtSampai) throw new RuntimeException('Periode rule tidak valid.');
            $dariDb = $dtDari->format('Y-m-d H:i:s');
            $sampaiDb = $dtSampai->format('Y-m-d H:i:s');
            if (strtotime($sampaiDb) < strtotime($dariDb)) throw new RuntimeException('Tanggal selesai harus >= tanggal mulai.');

            if ($id > 0) {
                $st = $db->prepare("UPDATE promo_bersyarat
                    SET nama_rule=?, minimal_belanja=?, minimal_qty=?, minimal_item=?, tipe_hadiah=?, nilai_hadiah=?, max_diskon=?, berlaku_dari=?, berlaku_sampai=?, aktif=?
                    WHERE rule_id=? AND toko_id=? AND deleted_at IS NULL");
                $st->bind_param('sdiisddssiii', $nama, $minimalBelanja, $minimalQty, $minimalItem, $tipe, $nilai, $maxDiskon, $dariDb, $sampaiDb, $aktif, $id, $tokoId);
                $st->execute();
                $st->close();
                $message = 'Rule promo bersyarat diperbarui.';
            } else {
                $st = $db->prepare("INSERT INTO promo_bersyarat
                    (toko_id, nama_rule, minimal_belanja, minimal_qty, minimal_item, tipe_hadiah, nilai_hadiah, max_diskon, berlaku_dari, berlaku_sampai, aktif)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $st->bind_param('isdiisddssi', $tokoId, $nama, $minimalBelanja, $minimalQty, $minimalItem, $tipe, $nilai, $maxDiskon, $dariDb, $sampaiDb, $aktif);
                $st->execute();
                $st->close();
                $message = 'Rule promo bersyarat ditambahkan.';
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['rule_id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('Rule tidak valid.');
            $st = $db->prepare("UPDATE promo_bersyarat SET deleted_at=NOW(), aktif=0 WHERE rule_id=? AND toko_id=?");
            $st->bind_param('ii', $id, $tokoId);
            $st->execute();
            $st->close();
            $message = 'Rule dihapus.';
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
    $where .= " AND nama_rule LIKE CONCAT('%',?,'%')";
    $types .= 's';
    $params[] = $q;
}

$st = $db->prepare("SELECT rule_id, nama_rule, minimal_belanja, minimal_qty, minimal_item, tipe_hadiah, nilai_hadiah, max_diskon, berlaku_dari, berlaku_sampai, aktif
                    FROM promo_bersyarat $where ORDER BY dibuat_pada DESC");
$st->bind_param($types, ...$params);
$st->execute();
$rules = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$now = date('Y-m-d H:i:s');
$totalAktif = 0;
foreach ($rules as $r) {
    if ((int)$r['aktif'] === 1 && $now >= $r['berlaku_dari'] && $now <= $r['berlaku_sampai']) $totalAktif++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Promo Bersyarat</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
:root{--ink:#0f172a;--muted:#64748b;--border:#e2e8f0;--card:#fff;--bg:#f5f7fb;--shadow:0 14px 40px rgba(15,23,42,.08);}
*{box-sizing:border-box}body{margin:0;font-family:'Plus Jakarta Sans','Inter',system-ui;background:var(--bg);color:var(--ink)}
.page{padding:28px 20px 48px}.container{max-width:1200px;margin:0 auto}
.hero{background:linear-gradient(135deg,#3f1d0d 0%,#9a3412 70%,#fb923c 100%);color:#fff7ed;border-radius:18px;padding:22px 24px;box-shadow:var(--shadow)}
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
.modal-box{background:#fff;border-radius:14px;width:92%;max-width:760px;box-shadow:0 18px 40px rgba(15,23,42,.18);overflow:hidden}
.modal-header{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
.modal-body{padding:16px;display:grid;gap:12px}.modal-body label{display:block;font-size:13px;font-weight:700;color:#334155;margin-bottom:6px}
.modal-body input,.modal-body select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px}
.grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.modal-footer{padding:14px 16px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end}
@media(max-width:760px){.metrics{grid-template-columns:1fr}.grid2{grid-template-columns:1fr}.toolbar{flex-direction:column;align-items:stretch}}
</style>
</head>
<body>
<div class="page">
  <div class="container">
    <div class="hero">
      <p style="margin:0;letter-spacing:.08em;font-weight:800">PROMO</p>
      <h1>Promo Bersyarat</h1>
      <p>Atur rule promo berdasarkan syarat minimal belanja, kuantitas, dan jumlah item.</p>
      <button class="btn btn-primary" onclick="openModal()">+ Rule Baru</button>
      <div class="metrics">
        <div class="metric"><small>Total Rule</small><div class="metric-value"><?=count($rules)?></div></div>
        <div class="metric"><small>Aktif Sekarang</small><div class="metric-value"><?=$totalAktif?></div></div>
        <div class="metric"><small>Pencarian</small><div class="metric-value"><?=$q !== '' ? htmlspecialchars($q) : 'Semua'?></div></div>
      </div>
    </div>

    <?php if ($message): ?><div class="alert alert-ok"><?=htmlspecialchars($message)?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-err"><?=htmlspecialchars($error)?></div><?php endif; ?>

    <div class="panel">
      <form class="toolbar" method="get">
        <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari nama rule...">
        <button class="btn btn-outline" type="submit">Cari</button>
      </form>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nama Rule</th><th>Syarat</th><th>Hadiah</th><th>Periode</th><th>Status</th><th style="text-align:right">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($rules as $r): ?>
            <?php $isActiveNow = ((int)$r['aktif'] === 1 && $now >= $r['berlaku_dari'] && $now <= $r['berlaku_sampai']); ?>
            <tr>
              <td><strong><?=htmlspecialchars($r['nama_rule'])?></strong></td>
              <td>
                Min Belanja: Rp <?=number_format((float)$r['minimal_belanja'], 0, ',', '.')?><br>
                Min Qty: <?=number_format((int)$r['minimal_qty'])?> | Min Item: <?=number_format((int)$r['minimal_item'])?>
              </td>
              <td>
                <?=$r['tipe_hadiah'] === 'persen' ? number_format((float)$r['nilai_hadiah'], 2) . '%' : 'Rp ' . number_format((float)$r['nilai_hadiah'], 0, ',', '.')?>
                <?php if ((float)$r['max_diskon'] > 0): ?><br>Maks: Rp <?=number_format((float)$r['max_diskon'], 0, ',', '.')?><?php endif; ?>
              </td>
              <td><?=date('d M Y H:i', strtotime($r['berlaku_dari']))?> - <?=date('d M Y H:i', strtotime($r['berlaku_sampai']))?></td>
              <td><span class="badge <?=$isActiveNow ? 'ok' : 'off'?>"><?=$isActiveNow ? 'Aktif' : 'Nonaktif'?></span></td>
              <td style="text-align:right">
                <button class="btn btn-outline" type="button" onclick='editRule(<?=json_encode($r, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>)'>Edit</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus rule ini?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="rule_id" value="<?=$r['rule_id']?>">
                  <button class="btn btn-outline" type="submit" style="color:#b91c1c">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rules): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:20px">Belum ada rule promo bersyarat</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="ruleModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modalTitle" style="margin:0">Rule Baru</h3>
      <span style="cursor:pointer;font-size:22px" onclick="closeModal()">&times;</span>
    </div>
    <form method="post" id="ruleForm">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="rule_id">
      <div class="modal-body">
        <div><label>Nama Rule</label><input type="text" name="nama_rule" required></div>
        <div class="grid2">
          <div><label>Minimal Belanja</label><input type="number" name="minimal_belanja" min="0" step="0.01" value="0"></div>
          <div><label>Minimal Qty (Total)</label><input type="number" name="minimal_qty" min="0" step="1" value="0"></div>
        </div>
        <div class="grid2">
          <div><label>Minimal Item Berbeda</label><input type="number" name="minimal_item" min="0" step="1" value="0"></div>
          <div><label>Tipe Hadiah</label><select name="tipe_hadiah" onchange="syncTipe()"><option value="nominal">Nominal</option><option value="persen">Persen</option></select></div>
        </div>
        <div class="grid2">
          <div><label>Nilai Hadiah</label><input type="number" name="nilai_hadiah" min="0.01" step="0.01" required></div>
          <div><label>Maksimal Diskon</label><input type="number" name="max_diskon" min="0" step="0.01" value="0"></div>
        </div>
        <div class="grid2">
          <div><label>Berlaku Dari</label><input type="datetime-local" name="berlaku_dari" required></div>
          <div><label>Berlaku Sampai</label><input type="datetime-local" name="berlaku_sampai" required></div>
        </div>
        <div><label>Status</label><select name="aktif"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" type="button" onclick="closeModal()">Batal</button>
        <button class="btn btn-primary" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById('ruleModal');
const form = document.getElementById('ruleForm');
function toLocalInputValue(v){
  if(!v) return '';
  const d = new Date(v.replace(' ', 'T'));
  if (isNaN(d.getTime())) return '';
  const off = d.getTimezoneOffset();
  const local = new Date(d.getTime() - (off * 60000));
  return local.toISOString().slice(0,16);
}
function syncTipe(){
  if(form.tipe_hadiah.value === 'persen') form.nilai_hadiah.max = '100';
  else form.nilai_hadiah.removeAttribute('max');
}
function openModal(){
  form.reset();
  form.rule_id.value = '';
  const now = new Date();
  const plus30 = new Date(now.getTime() + (30*24*60*60*1000));
  form.berlaku_dari.value = toLocalInputValue(now.toISOString());
  form.berlaku_sampai.value = toLocalInputValue(plus30.toISOString());
  form.aktif.value = '1';
  document.getElementById('modalTitle').innerText = 'Rule Baru';
  syncTipe();
  modal.style.display = 'flex';
}
function editRule(r){
  form.rule_id.value = r.rule_id || '';
  form.nama_rule.value = r.nama_rule || '';
  form.minimal_belanja.value = Number(r.minimal_belanja || 0);
  form.minimal_qty.value = Number(r.minimal_qty || 0);
  form.minimal_item.value = Number(r.minimal_item || 0);
  form.tipe_hadiah.value = r.tipe_hadiah || 'nominal';
  form.nilai_hadiah.value = Number(r.nilai_hadiah || 0);
  form.max_diskon.value = Number(r.max_diskon || 0);
  form.berlaku_dari.value = toLocalInputValue(r.berlaku_dari || '');
  form.berlaku_sampai.value = toLocalInputValue(r.berlaku_sampai || '');
  form.aktif.value = String(Number(r.aktif || 0));
  document.getElementById('modalTitle').innerText = 'Edit Rule';
  syncTipe();
  modal.style.display = 'flex';
}
function closeModal(){ modal.style.display = 'none'; }
</script>
</body>
</html>
