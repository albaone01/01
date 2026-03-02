<?php
require_once __DIR__ . '/_bootstrap.php';

$db = $pos_db;
$tokoId = (int)($_SESSION['toko_id'] ?? 0);
so_ensure_tables($db);

$opnameId = (int)($_GET['opname_id'] ?? $_POST['opname_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$msg = isset($_GET['created']) ? 'Draft baru dibuat. Silakan input data fisik.' : '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($opnameId <= 0) throw new RuntimeException('Pilih dokumen opname terlebih dahulu.');
        $fisik = $_POST['fisik'] ?? [];
        $alasan = $_POST['alasan'] ?? [];
        if (!is_array($fisik)) $fisik = [];
        if (!is_array($alasan)) $alasan = [];
        so_save_physical($db, $tokoId, $opnameId, $fisik, $alasan);
        $msg = 'Data fisik berhasil disimpan dan status dokumen menjadi COUNTED.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$headers = [];
$st = $db->prepare("SELECT opname_id, nomor_opname, status, tanggal_opname FROM stock_opname_header WHERE toko_id=? AND deleted_at IS NULL ORDER BY opname_id DESC LIMIT 200");
$st->bind_param('i', $tokoId);
$st->execute();
$headers = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
if ($opnameId <= 0 && !empty($headers)) $opnameId = (int)$headers[0]['opname_id'];

$header = $opnameId > 0 ? so_get_header($db, $tokoId, $opnameId) : null;
$detail = $header ? so_get_detail($db, $opnameId, $tokoId, $q) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stok Opname Data Fisik</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
:root{--ink:#0f172a;--muted:#64748b;--border:#e2e8f0;--card:#fff;--bg:#f5f7fb;--shadow:0 14px 40px rgba(15,23,42,.08);}
*{box-sizing:border-box}body{margin:0;font-family:'Plus Jakarta Sans','Inter',system-ui;background:var(--bg);color:var(--ink)}
.page{padding:26px 20px 44px}.container{max-width:1300px;margin:0 auto}
.hero{background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 70%,#60a5fa 100%);color:#eff6ff;border-radius:18px;padding:22px 24px;box-shadow:var(--shadow)}
.hero h1{margin:6px 0 10px;font-size:26px}.hero p{margin:0;color:rgba(255,255,255,.92)}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.tab{padding:9px 12px;border-radius:10px;text-decoration:none;font-weight:700;font-size:13px;border:1px solid rgba(255,255,255,.35);color:#fff;background:rgba(255,255,255,.08)}
.tab.active{background:#fff;color:#0f172a}
.panel{margin-top:16px;background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden}
.head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
.body{padding:16px}
input,select{padding:10px 12px;border:1px solid var(--border);border-radius:10px}
.btn{padding:10px 14px;border-radius:10px;border:1px solid transparent;font-weight:700;cursor:pointer}
.btn-primary{background:#0f172a;color:#fff}.btn-outline{background:#fff;border-color:var(--border)}
table{width:100%;border-collapse:collapse;font-size:14px}th,td{padding:10px 12px;border-bottom:1px solid #edf2f7;text-align:left}thead th{background:#f8fafc;color:var(--muted);position:sticky;top:0}
.muted{color:var(--muted)} .num{text-align:right}
.alert{margin-top:10px;padding:10px 12px;border-radius:10px}.ok{background:#dcfce7;color:#166534}.er{background:#fee2e2;color:#991b1b}
.badge{padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#e2e8f0;color:#334155}
</style>
</head>
<body>
<div class="page"><div class="container">
  <div class="hero">
    <p style="margin:0;letter-spacing:.08em;font-weight:800">STOK OPNAME</p>
    <h1>Data Fisik</h1>
    <p>Input hitung fisik, lihat selisih qty/nilai secara real-time per item.</p>
    <div class="tabs">
      <a class="tab" href="index.php">Data/Sistem</a>
      <a class="tab active" href="fisik.php">Data Fisik</a>
      <a class="tab" href="adjustment.php">Adjustment</a>
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
        <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari produk / SKU / barcode">
        <button class="btn btn-outline" type="submit">Tampilkan</button>
      </form>
      <?php if($header): ?>
        <div class="muted">
          Gudang: <strong><?=htmlspecialchars($header['nama_gudang'] ?? '-')?></strong> |
          Status: <span class="badge"><?=strtoupper(htmlspecialchars($header['status']))?></span>
        </div>
      <?php endif; ?>
    </div>
    <?php if($header): ?>
      <form method="post">
        <input type="hidden" name="opname_id" value="<?=$opnameId?>">
        <div class="body" style="padding:0">
          <table>
            <thead><tr><th>Produk</th><th>SKU</th><th class="num">Stok Sistem</th><th class="num">Stok Fisik</th><th class="num">Selisih Qty</th><th class="num">Selisih Nominal</th><th>Alasan</th></tr></thead>
            <tbody>
              <?php foreach($detail as $d): ?>
                <tr>
                  <td><?=htmlspecialchars($d['nama_produk'])?></td>
                  <td><?=htmlspecialchars($d['sku'] ?: '-')?></td>
                  <td class="num"><?=$d['stok_sistem']?></td>
                  <td class="num"><input style="width:120px;text-align:right" type="number" min="0" step="1" name="fisik[<?=$d['detail_id']?>]" value="<?=$d['stok_fisik']?>"></td>
                  <td class="num"><?=$d['selisih_qty']?></td>
                  <td class="num">Rp <?=number_format((float)$d['selisih_nominal'],0,',','.')?></td>
                  <td><input type="text" name="alasan[<?=$d['detail_id']?>]" value="<?=htmlspecialchars((string)$d['alasan'])?>" placeholder="Opsional"></td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$detail): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:20px">Detail tidak ditemukan.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="body" style="display:flex;justify-content:flex-end;gap:10px">
          <a class="btn btn-outline" style="text-decoration:none" href="adjustment.php?opname_id=<?=$opnameId?>">Lanjut ke Adjustment</a>
          <button class="btn btn-primary" type="submit">Simpan Data Fisik</button>
        </div>
      </form>
    <?php else: ?>
      <div class="body muted">Belum ada dokumen opname. Buat dari halaman Data/Sistem.</div>
    <?php endif; ?>
  </div>
</div></div>
</body>
</html>
