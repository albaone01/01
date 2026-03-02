<?php
require_once __DIR__ . '/_bootstrap.php';

$db = $pos_db;
$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$userId = (int)($_SESSION['pengguna_id'] ?? 0);
so_ensure_tables($db);

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    try {
        if ($action === 'create_snapshot') {
            $gudangId = (int)($_POST['gudang_id'] ?? 0);
            $catatan = trim((string)($_POST['catatan'] ?? ''));
            if ($gudangId <= 0) throw new RuntimeException('Gudang wajib dipilih.');
            $opnameId = so_create_from_system($db, $tokoId, $gudangId, $userId, $catatan);
            $msg = 'Draft stok opname berhasil dibuat.';
            header('Location: fisik.php?opname_id=' . $opnameId . '&created=1');
            exit;
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$gudang = so_get_gudang($db, $tokoId);
$rows = [];
$st = $db->prepare("
    SELECT h.opname_id, h.nomor_opname, h.tanggal_opname, h.status, h.total_item, h.total_selisih_qty, h.total_selisih_nominal,
           g.nama_gudang, u.nama AS dibuat_nama
    FROM stock_opname_header h
    LEFT JOIN gudang g ON g.gudang_id = h.gudang_id
    LEFT JOIN pengguna u ON u.pengguna_id = h.dibuat_oleh
    WHERE h.toko_id=? AND h.deleted_at IS NULL
    ORDER BY h.opname_id DESC
    LIMIT 120
");
$st->bind_param('i', $tokoId);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stok Opname Data/Sistem</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
:root{--ink:#0f172a;--muted:#64748b;--border:#e2e8f0;--card:#fff;--bg:#f5f7fb;--shadow:0 14px 40px rgba(15,23,42,.08);}
*{box-sizing:border-box}body{margin:0;font-family:'Plus Jakarta Sans','Inter',system-ui;background:var(--bg);color:var(--ink)}
.page{padding:26px 20px 44px}.container{max-width:1250px;margin:0 auto}
.hero{background:linear-gradient(135deg,#0f172a 0%,#0f766e 70%,#22c55e 100%);color:#ecfeff;border-radius:18px;padding:22px 24px;box-shadow:var(--shadow)}
.hero h1{margin:6px 0 10px;font-size:26px}.hero p{margin:0;color:rgba(255,255,255,.92)}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.tab{padding:9px 12px;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px;border:1px solid rgba(255,255,255,.35);color:#fff;background:rgba(255,255,255,.08)}
.tab.active{background:#fff;color:#0f172a}
.panel{margin-top:16px;background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden}
.head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:10px;align-items:center}
.body{padding:16px}
.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px}
.btn{padding:10px 14px;border-radius:10px;border:1px solid transparent;font-weight:700;cursor:pointer}
.btn-primary{background:#0f172a;color:#fff}.btn-outline{background:#fff;border-color:var(--border)}
table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:10px 12px;border-bottom:1px solid #edf2f7;text-align:left}thead th{background:#f8fafc;color:var(--muted)}
.badge{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
.draft{background:#e2e8f0;color:#334155}.counted{background:#fef3c7;color:#92400e}.adjusted{background:#dcfce7;color:#166534}.void{background:#fee2e2;color:#991b1b}
.alert{margin-top:10px;padding:10px 12px;border-radius:10px}.ok{background:#dcfce7;color:#166534}.er{background:#fee2e2;color:#991b1b}
@media(max-width:960px){.grid{grid-template-columns:1fr}.head{flex-direction:column;align-items:flex-start}}
</style>
</head>
<body>
<div class="page">
  <div class="container">
    <div class="hero">
      <p style="margin:0;letter-spacing:.08em;font-weight:800">STOK OPNAME</p>
      <h1>Data Sistem</h1>
      <p>Buat draft opname dari stok sistem per gudang sebagai baseline hitung fisik.</p>
      <div class="tabs">
        <a class="tab active" href="index.php">Data/Sistem</a>
        <a class="tab" href="fisik.php">Data Fisik</a>
        <a class="tab" href="adjustment.php">Adjustment</a>
      </div>
    </div>

    <?php if ($msg): ?><div class="alert ok"><?=htmlspecialchars($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert er"><?=htmlspecialchars($err)?></div><?php endif; ?>

    <div class="panel">
      <div class="head"><strong>Buat Draft Opname dari Sistem</strong><span style="color:var(--muted);font-size:13px">Sumber data: stok gudang saat ini</span></div>
      <div class="body">
        <form method="post" class="grid">
          <input type="hidden" name="action" value="create_snapshot">
          <div>
            <label style="font-size:13px;font-weight:700">Gudang</label>
            <select name="gudang_id" required>
              <option value="">Pilih gudang...</option>
              <?php foreach($gudang as $g): ?>
                <option value="<?=$g['gudang_id']?>"><?=htmlspecialchars($g['nama_gudang'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="grid-column:span 2">
            <label style="font-size:13px;font-weight:700">Catatan</label>
            <input type="text" name="catatan" placeholder="Contoh: Stok opname akhir bulan">
          </div>
          <div><button class="btn btn-primary" type="submit">Buat Draft Opname</button></div>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="head"><strong>Riwayat Dokumen Opname</strong><span style="color:var(--muted);font-size:13px">Maks 120 dokumen terakhir</span></div>
      <div class="body" style="padding:0">
        <table>
          <thead><tr><th>No Opname</th><th>Tanggal</th><th>Gudang</th><th>Status</th><th>Item</th><th>Selisih Qty</th><th>Selisih Nominal</th><th>Dibuat</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <?php $stClass = htmlspecialchars($r['status']); ?>
              <tr>
                <td><strong><?=htmlspecialchars($r['nomor_opname'])?></strong></td>
                <td><?=date('d M Y H:i', strtotime($r['tanggal_opname']))?></td>
                <td><?=htmlspecialchars($r['nama_gudang'] ?? '-')?></td>
                <td><span class="badge <?=$stClass?>"><?=strtoupper(htmlspecialchars($r['status']))?></span></td>
                <td><?=number_format((int)$r['total_item'])?></td>
                <td><?=number_format((int)$r['total_selisih_qty'])?></td>
                <td>Rp <?=number_format((float)$r['total_selisih_nominal'],0,',','.')?></td>
                <td><?=htmlspecialchars($r['dibuat_nama'] ?? '-')?></td>
                <td>
                  <a class="btn btn-outline" style="text-decoration:none;padding:6px 10px" href="fisik.php?opname_id=<?=$r['opname_id']?>">Fisik</a>
                  <a class="btn btn-outline" style="text-decoration:none;padding:6px 10px" href="adjustment.php?opname_id=<?=$r['opname_id']?>">Adjust</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if(!$rows): ?><tr><td colspan="9" style="text-align:center;color:var(--muted);padding:20px">Belum ada dokumen opname</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
