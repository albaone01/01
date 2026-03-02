<?php
require_once __DIR__ . '/_bootstrap.php';

$db = $pos_db;
$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$userId = (int)($_SESSION['pengguna_id'] ?? 0);
so_ensure_tables($db);

$opnameId = (int)($_GET['opname_id'] ?? $_POST['opname_id'] ?? 0);
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    try {
        if ($action === 'apply_adjustment') {
            if ($opnameId <= 0) throw new RuntimeException('Dokumen opname tidak valid.');
            $res = so_apply_adjustment($db, $tokoId, $opnameId, $userId);
            $msg = 'Adjustment selesai. Mutasi: ' . number_format((int)$res['mutated_rows']) .
                   ' item, selisih nominal: Rp ' . number_format((float)$res['total_nominal'], 0, ',', '.') .
                   ($res['jurnal_id'] ? ', jurnal #' . (int)$res['jurnal_id'] : '');
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$headers = [];
$st = $db->prepare("SELECT opname_id, nomor_opname, status, tanggal_opname, total_item, total_selisih_qty, total_selisih_nominal FROM stock_opname_header WHERE toko_id=? AND deleted_at IS NULL ORDER BY opname_id DESC LIMIT 200");
$st->bind_param('i', $tokoId);
$st->execute();
$headers = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
if ($opnameId <= 0 && !empty($headers)) $opnameId = (int)$headers[0]['opname_id'];

$header = $opnameId > 0 ? so_get_header($db, $tokoId, $opnameId) : null;
$detail = $header ? so_get_detail($db, $opnameId, $tokoId, '') : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ajustment Stok Opname</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
:root{--ink:#0f172a;--muted:#64748b;--border:#e2e8f0;--card:#fff;--bg:#f5f7fb;--shadow:0 14px 40px rgba(15,23,42,.08);}
*{box-sizing:border-box}body{margin:0;font-family:'Plus Jakarta Sans','Inter',system-ui;background:var(--bg);color:var(--ink)}
.page{padding:26px 20px 44px}.container{max-width:1300px;margin:0 auto}
.hero{background:linear-gradient(135deg,#3f1d0d 0%,#9a3412 70%,#fb923c 100%);color:#fff7ed;border-radius:18px;padding:22px 24px;box-shadow:var(--shadow)}
.hero h1{margin:6px 0 10px;font-size:26px}.hero p{margin:0;color:rgba(255,255,255,.92)}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.tab{padding:9px 12px;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px;border:1px solid rgba(255,255,255,.35);color:#fff;background:rgba(255,255,255,.08)}
.tab.active{background:#fff;color:#0f172a}
.panel{margin-top:16px;background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden}
.head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
.body{padding:16px}
input,select{padding:10px 12px;border:1px solid var(--border);border-radius:10px}
.btn{padding:10px 14px;border-radius:10px;border:1px solid transparent;font-weight:700;cursor:pointer}
.btn-primary{background:#0f172a;color:#fff}.btn-outline{background:#fff;border-color:var(--border)}
table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:10px 12px;border-bottom:1px solid #edf2f7;text-align:left}thead th{background:#f8fafc;color:var(--muted)}
.num{text-align:right}.muted{color:var(--muted)}
.badge{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
.draft{background:#e2e8f0;color:#334155}.counted{background:#fef3c7;color:#92400e}.adjusted{background:#dcfce7;color:#166534}
.alert{margin-top:10px;padding:10px 12px;border-radius:10px}.ok{background:#dcfce7;color:#166534}.er{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
<div class="page"><div class="container">
  <div class="hero">
    <p style="margin:0;letter-spacing:.08em;font-weight:800">STOK OPNAME</p>
    <h1>Ajustment Stok Opname</h1>
    <p>Posting selisih ke stok gudang dan jurnal akuntansi secara otomatis.</p>
    <div class="tabs">
      <a class="tab" href="index.php">Data/Sistem</a>
      <a class="tab" href="fisik.php">Data Fisik</a>
      <a class="tab active" href="adjustment.php">Adjustment</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert ok"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert er"><?=htmlspecialchars($err)?></div><?php endif; ?>

  <div class="panel">
    <div class="head">
      <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <select name="opname_id" required>
          <option value="">Pilih dokumen opname...</option>
          <?php foreach($headers as $h): ?>
            <option value="<?=$h['opname_id']?>" <?=$opnameId===(int)$h['opname_id']?'selected':''?>>
              <?=htmlspecialchars($h['nomor_opname'])?> (<?=strtoupper(htmlspecialchars($h['status']))?>)
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline" type="submit">Tampilkan</button>
      </form>
      <?php if($header): ?>
        <div class="muted">Gudang: <strong><?=htmlspecialchars($header['nama_gudang'] ?? '-')?></strong> | Status:
          <span class="badge <?=htmlspecialchars($header['status'])?>"><?=strtoupper(htmlspecialchars($header['status']))?></span>
        </div>
      <?php endif; ?>
    </div>

    <?php if($header): ?>
      <div class="body">
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px">
          <div style="background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:10px"><small class="muted">Total Item</small><div style="font-weight:800;font-size:20px"><?=number_format((int)$header['total_item'])?></div></div>
          <div style="background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:10px"><small class="muted">Selisih Qty</small><div style="font-weight:800;font-size:20px"><?=number_format((int)$header['total_selisih_qty'])?></div></div>
          <div style="background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:10px"><small class="muted">Selisih Nominal</small><div style="font-weight:800;font-size:20px">Rp <?=number_format((float)$header['total_selisih_nominal'],0,',','.')?></div></div>
          <div style="background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:10px"><small class="muted">Jurnal Referensi</small><div style="font-weight:800;font-size:20px"><?=htmlspecialchars((string)($header['adjusted_jurnal_id'] ?: '-'))?></div></div>
        </div>
      </div>

      <div class="body" style="padding:0">
        <table>
          <thead><tr><th>Produk</th><th>SKU</th><th class="num">Stok Sistem</th><th class="num">Stok Fisik</th><th class="num">Selisih Qty</th><th class="num">HPP</th><th class="num">Selisih Nominal</th></tr></thead>
          <tbody>
          <?php foreach($detail as $d): ?>
            <tr>
              <td><?=htmlspecialchars($d['nama_produk'])?></td>
              <td><?=htmlspecialchars($d['sku'] ?: '-')?></td>
              <td class="num"><?=$d['stok_sistem']?></td>
              <td class="num"><?=$d['stok_fisik']?></td>
              <td class="num"><?=$d['selisih_qty']?></td>
              <td class="num">Rp <?=number_format((float)$d['hpp_snapshot'],0,',','.')?></td>
              <td class="num">Rp <?=number_format((float)$d['selisih_nominal'],0,',','.')?></td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$detail): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:20px">Detail tidak ada.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="body" style="display:flex;justify-content:flex-end;gap:10px">
        <a class="btn btn-outline" style="text-decoration:none" href="fisik.php?opname_id=<?=$opnameId?>">Kembali ke Data Fisik</a>
        <?php if($header['status']==='counted'): ?>
          <form method="post" onsubmit="return confirm('Proses adjustment stok dan jurnal akuntansi sekarang?')">
            <input type="hidden" name="action" value="apply_adjustment">
            <input type="hidden" name="opname_id" value="<?=$opnameId?>">
            <button class="btn btn-primary" type="submit">Proses Adjustment</button>
          </form>
        <?php else: ?>
          <button class="btn btn-outline" type="button" disabled>Dokumen harus COUNTED</button>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="body muted">Belum ada dokumen opname.</div>
    <?php endif; ?>
  </div>
</div></div>
</body>
</html>
