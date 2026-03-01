<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = (int)($_SESSION['toko_id'] ?? 0);
if ($toko_id <= 0) {
    header('Location: ../pilih_gudang.php');
    exit;
}

function load_all_cfg(): array {
    global $pos_db, $toko_id;
    $cfg = [];
    $st = $pos_db->prepare("SELECT nama_konfigurasi, nilai FROM toko_config WHERE toko_id=?");
    $st->bind_param('i', $toko_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res ? $res->fetch_assoc() : null) {
        if (!$row) break;
        $cfg[(string)$row['nama_konfigurasi']] = (string)$row['nilai'];
    }
    $st->close();
    return $cfg;
}

function cfg(array $configs, string $key, string $default = ''): string {
    return array_key_exists($key, $configs) ? (string)$configs[$key] : $default;
}

function save_cfg_batch(array $pairs): void {
    global $pos_db, $toko_id;
    $pos_db->begin_transaction();
    try {
        $up = $pos_db->prepare("UPDATE toko_config SET nilai=? WHERE toko_id=? AND nama_konfigurasi=?");
        $in = $pos_db->prepare("INSERT INTO toko_config (toko_id,nama_konfigurasi,nilai) VALUES (?,?,?)");
        foreach ($pairs as $key => $value) {
            $k = (string)$key;
            $v = (string)$value;
            $up->bind_param('sis', $v, $toko_id, $k);
            $up->execute();
            if ($up->affected_rows === 0) {
                $in->bind_param('iss', $toko_id, $k, $v);
                $in->execute();
            }
        }
        $up->close();
        $in->close();
        $pos_db->commit();
    } catch (Throwable $e) {
        $pos_db->rollback();
        throw $e;
    }
}

$msgOk = '';
$msgErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paper = ($_POST['nota_paper_width'] ?? '80') === '58' ? '58' : '80';
    $scale = ($_POST['nota_font_scale'] ?? 'normal') === 'compact' ? 'compact' : 'normal';
    $showLogo = isset($_POST['nota_show_logo']) ? '1' : '0';
    $showAlamat = isset($_POST['nota_show_alamat']) ? '1' : '0';
    $showTelepon = isset($_POST['nota_show_telepon']) ? '1' : '0';
    $showKasir = isset($_POST['nota_show_kasir']) ? '1' : '0';
    $showPoin = isset($_POST['nota_show_member_points']) ? '1' : '0';
    $showDiskon = isset($_POST['nota_show_diskon']) ? '1' : '0';
    $showHemat = isset($_POST['nota_show_hemat']) ? '1' : '0';
    $headerText = trim((string)($_POST['nota_header_text'] ?? 'Terima kasih telah berbelanja'));
    $footerText = trim((string)($_POST['nota_footer_text'] ?? 'Barang yang sudah dibeli tidak dapat ditukar/dikembalikan'));

    try {
        save_cfg_batch([
            'nota_paper_width' => $paper,
            'nota_font_scale' => $scale,
            'nota_show_logo' => $showLogo,
            'nota_show_alamat' => $showAlamat,
            'nota_show_telepon' => $showTelepon,
            'nota_show_kasir' => $showKasir,
            'nota_show_member_points' => $showPoin,
            'nota_show_diskon' => $showDiskon,
            'nota_show_hemat' => $showHemat,
            'nota_header_text' => $headerText,
            'nota_footer_text' => $footerText,
        ]);
        $msgOk = 'Format nota berhasil disimpan.';
    } catch (Throwable $e) {
        $msgErr = 'Gagal menyimpan format nota.';
    }
}

$configs = load_all_cfg();

$stToko = $pos_db->prepare("SELECT nama_toko, alamat FROM toko WHERE toko_id=? AND deleted_at IS NULL LIMIT 1");
$stToko->bind_param('i', $toko_id);
$stToko->execute();
$toko = $stToko->get_result()->fetch_assoc() ?: ['nama_toko' => 'Toko Anda', 'alamat' => ''];
$stToko->close();

$paperWidth = cfg($configs, 'nota_paper_width', '80');
$fontScale = cfg($configs, 'nota_font_scale', 'normal');
$showLogo = cfg($configs, 'nota_show_logo', '0') === '1';
$showAlamat = cfg($configs, 'nota_show_alamat', '1') === '1';
$showTelepon = cfg($configs, 'nota_show_telepon', '1') === '1';
$showKasir = cfg($configs, 'nota_show_kasir', '1') === '1';
$showPoin = cfg($configs, 'nota_show_member_points', '1') === '1';
$showDiskon = cfg($configs, 'nota_show_diskon', '1') === '1';
$showHemat = cfg($configs, 'nota_show_hemat', '1') === '1';
$headerText = cfg($configs, 'nota_header_text', 'Terima kasih telah berbelanja');
$footerText = cfg($configs, 'nota_footer_text', 'Barang yang sudah dibeli tidak dapat ditukar/dikembalikan');
$phone = cfg($configs, 'phone', '-');
$logoPath = cfg($configs, 'logo_path', '');

$sampleSubtotal = 54500.0;
$sampleDiskon = 500.0;
$sampleTotal = 54000.0;
$sampleHasPelanggan = false;
$samplePoinDidapat = 0;
$sampleTotalPoin = 0;
$pointNominal = (float)cfg($configs, 'member_point_nominal', '1000');
if ($pointNominal <= 0) {
    $pointNominal = 1000.0;
}
$stSample = $pos_db->prepare("
    SELECT p.subtotal, p.diskon, p.total_akhir, p.pelanggan_id,
           COALESCE(pt.poin, 0) AS total_poin
    FROM penjualan p
    LEFT JOIN pelanggan_toko pt
      ON pt.pelanggan_id = p.pelanggan_id
     AND pt.toko_id = p.toko_id
     AND pt.deleted_at IS NULL
    WHERE p.toko_id = ?
    ORDER BY p.penjualan_id DESC
    LIMIT 1
");
$stSample->bind_param('i', $toko_id);
$stSample->execute();
$sampleRow = $stSample->get_result()->fetch_assoc();
$stSample->close();
if ($sampleRow) {
    $sampleSubtotal = (float)($sampleRow['subtotal'] ?? 0);
    $sampleDiskon = (float)($sampleRow['diskon'] ?? 0);
    $sampleTotal = (float)($sampleRow['total_akhir'] ?? 0);
    $sampleHasPelanggan = (int)($sampleRow['pelanggan_id'] ?? 0) > 0;
    if ($sampleHasPelanggan) {
        $samplePoinDidapat = (int)floor(max(0, $sampleTotal) / $pointNominal);
        $sampleTotalPoin = (int)($sampleRow['total_poin'] ?? 0);
    }
}
$sampleHemat = max(0, $sampleSubtotal - $sampleTotal);
$hasDiskonValue = $sampleDiskon > 0;
$hasHematValue = $sampleHemat > 0;
$showDiskonPreview = $showDiskon && $hasDiskonValue;
$showHematPreview = $showHemat && $hasHematValue;
$showPoinPreview = $showPoin && $sampleHasPelanggan;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Format Nota</title>
<style>
    :root {
        --bg:#f4f6fb;
        --card:#ffffff;
        --line:#e2e8f0;
        --text:#0f172a;
        --muted:#64748b;
        --primary:#0f766e;
        --primary-2:#0d9488;
    }
    * { box-sizing:border-box; }
    body { margin:0; font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); }
    .wrap { max-width:980px; margin:18px auto; padding:0 12px 20px; }
    .card { background:var(--card); border:1px solid var(--line); border-radius:12px; box-shadow:0 8px 26px rgba(2,6,23,.05); }
    .card h1, .card h2 { margin:0; }
    .head { padding:14px 14px 10px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .head p { margin:6px 0 0; color:var(--muted); font-size:13px; }
    .body { padding:14px; }
    .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
    .full { grid-column:1 / -1; }
    label { display:block; font-size:12px; font-weight:700; color:#334155; margin:0 0 5px; }
    input[type="text"], textarea, select {
        width:100%; border:1px solid var(--line); border-radius:10px; padding:9px 10px; font:inherit; color:var(--text);
    }
    textarea { min-height:76px; resize:vertical; }
    .checks { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
    .check-item { border:1px solid var(--line); border-radius:10px; padding:8px 10px; display:flex; align-items:center; gap:8px; font-size:13px; }
    .actions { margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; }
    .btn { border:1px solid transparent; border-radius:10px; padding:9px 12px; cursor:pointer; font-weight:700; font-size:13px; }
    .btn-primary { background:var(--primary); color:#fff; }
    .btn-primary:hover { background:var(--primary-2); }
    .btn-ghost { background:#fff; border-color:var(--line); color:#1e293b; }
    .alert { margin:0 14px 12px; border-radius:10px; padding:9px 10px; font-size:13px; }
    .ok { background:#ecfdf5; border:1px solid #bbf7d0; color:#166534; }
    .err { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }

    .preview-shell { padding:14px; display:flex; justify-content:center; }
    .receipt {
        width: 320px;
        max-width:100%;
        border:1px dashed #94a3b8;
        background:#fff;
        color:#111;
        padding:10px;
        font-family:'Consolas','Courier New',monospace;
        font-size:12px;
        line-height:1.4;
    }
    .receipt.compact { font-size:11px; line-height:1.3; }
    .receipt.w58 { width:220px; }
    .center { text-align:center; }
    .hr { border-top:1px dashed #64748b; margin:8px 0; }
    .row { display:flex; justify-content:space-between; gap:8px; }
    .muted { color:#475569; }
    .logo-mini { max-height:34px; max-width:100%; object-fit:contain; margin-bottom:4px; }
    .preview-modal {
        display:none;
        position:fixed;
        inset:0;
        background:rgba(2,6,23,.45);
        z-index:1300;
        padding:20px 12px;
        overflow:auto;
    }
    .preview-modal.active { display:block; }
    .preview-dialog {
        max-width:440px;
        margin:20px auto;
        background:#fff;
        border:1px solid var(--line);
        border-radius:12px;
        box-shadow:0 22px 56px rgba(2,6,23,.22);
    }
    .preview-head {
        padding:12px 14px;
        border-bottom:1px solid var(--line);
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
    }
    .preview-head h2 { margin:0; font-size:18px; }
    .preview-head p { margin:4px 0 0; color:var(--muted); font-size:12px; }

    @media (max-width: 640px) {
        .grid, .checks { grid-template-columns:1fr; }
    }
</style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="wrap">
    <section class="card">
        <div class="head">
            <div>
                <h1>Format Nota</h1>
                <p>Atur tampilan struk/nota kasir untuk toko ini.</p>
            </div>
            <button type="button" class="btn btn-ghost" onclick="window.location.replace('../dashboard.php')">Kembali</button>
        </div>

        <?php if ($msgOk !== ''): ?><div class="alert ok"><?=htmlspecialchars($msgOk)?></div><?php endif; ?>
        <?php if ($msgErr !== ''): ?><div class="alert err"><?=htmlspecialchars($msgErr)?></div><?php endif; ?>

        <div class="body">
            <form method="post" id="notaForm">
                <div class="grid">
                    <div>
                        <label>Lebar Kertas</label>
                        <select name="nota_paper_width" id="nota_paper_width">
                            <option value="80" <?=$paperWidth === '80' ? 'selected' : ''?>>80 mm</option>
                            <option value="58" <?=$paperWidth === '58' ? 'selected' : ''?>>58 mm</option>
                        </select>
                    </div>
                    <div>
                        <label>Ukuran Font</label>
                        <select name="nota_font_scale" id="nota_font_scale">
                            <option value="normal" <?=$fontScale === 'normal' ? 'selected' : ''?>>Normal</option>
                            <option value="compact" <?=$fontScale === 'compact' ? 'selected' : ''?>>Compact</option>
                        </select>
                    </div>

                    <div class="full">
                        <label>Teks Header Nota</label>
                        <input type="text" name="nota_header_text" id="nota_header_text" value="<?=htmlspecialchars($headerText)?>" maxlength="140" placeholder="Contoh: Terima kasih telah berbelanja">
                    </div>

                    <div class="full">
                        <label>Teks Footer Nota</label>
                        <textarea name="nota_footer_text" id="nota_footer_text" maxlength="240" placeholder="Contoh: Barang yang sudah dibeli tidak dapat ditukar/dikembalikan"><?=htmlspecialchars($footerText)?></textarea>
                    </div>
                </div>

                <div style="margin-top:10px;">
                    <label style="margin-bottom:6px;">Elemen Yang Ditampilkan</label>
                    <div class="checks">
                        <label class="check-item"><input type="checkbox" name="nota_show_logo" id="nota_show_logo" <?=$showLogo ? 'checked' : ''?>> Tampilkan logo</label>
                        <label class="check-item"><input type="checkbox" name="nota_show_alamat" id="nota_show_alamat" <?=$showAlamat ? 'checked' : ''?>> Tampilkan alamat toko</label>
                        <label class="check-item"><input type="checkbox" name="nota_show_telepon" id="nota_show_telepon" <?=$showTelepon ? 'checked' : ''?>> Tampilkan telepon toko</label>
                        <label class="check-item"><input type="checkbox" name="nota_show_kasir" id="nota_show_kasir" <?=$showKasir ? 'checked' : ''?>> Tampilkan nama kasir</label>
                        <label class="check-item"><input type="checkbox" name="nota_show_member_points" id="nota_show_member_points" <?=$showPoin ? 'checked' : ''?>> Tampilkan info poin member</label>
                        <label class="check-item"><input type="checkbox" name="nota_show_diskon" id="nota_show_diskon" <?=$showDiskon ? 'checked' : ''?>> Tampilkan baris diskon</label>
                        <label class="check-item"><input type="checkbox" name="nota_show_hemat" id="nota_show_hemat" <?=$showHemat ? 'checked' : ''?>> Tampilkan "Anda Hemat"</label>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Simpan Format Nota</button>
                    <button type="button" class="btn btn-ghost" id="togglePreviewBtn">Lihat Preview</button>
                    <button type="button" class="btn btn-ghost" onclick="window.location.reload()">Reset</button>
                </div>
            </form>
        </div>
    </section>

</div>

<div class="preview-modal" id="previewModal">
    <div class="preview-dialog">
        <div class="preview-head">
            <div>
                <h2>Preview</h2>
                <p>Contoh tampilan saat cetak.</p>
            </div>
            <button type="button" class="btn btn-ghost" id="closePreviewBtn">Tutup</button>
        </div>
        <div class="preview-shell" id="previewShell">
            <div id="previewReceipt" class="receipt <?=$paperWidth === '58' ? 'w58' : ''?> <?=$fontScale === 'compact' ? 'compact' : ''?>">
                <div class="center">
                    <?php if ($logoPath !== ''): ?>
                        <img id="pvLogo" src="<?=htmlspecialchars($logoPath)?>" class="logo-mini" alt="Logo" style="<?=$showLogo ? '' : 'display:none;'?>">
                    <?php else: ?>
                        <div id="pvLogo" class="muted" style="<?=$showLogo ? '' : 'display:none;'?>">[ LOGO ]</div>
                    <?php endif; ?>
                    <div><strong><?=htmlspecialchars((string)$toko['nama_toko'])?></strong></div>
                    <div id="pvAlamat" class="muted" style="<?=$showAlamat ? '' : 'display:none;'?>"><?=nl2br(htmlspecialchars((string)$toko['alamat']))?></div>
                    <div id="pvTelepon" class="muted" style="<?=$showTelepon ? '' : 'display:none;'?>">Telp: <?=htmlspecialchars($phone !== '' ? $phone : '-')?></div>
                    <div id="pvHeaderText"><?=htmlspecialchars($headerText)?></div>
                </div>
                <div class="hr"></div>
                <div>No: INV-20260301-001</div>
                <div>Tgl: 01-03-2026 10:30</div>
                <div id="pvKasir" style="<?=$showKasir ? '' : 'display:none;'?>">Kasir: <?=htmlspecialchars((string)($_SESSION['pengguna_nama'] ?? 'Kasir'))?></div>
                <div class="hr"></div>
                <div class="row"><span>2x Roti Tawar</span><span>20.000</span></div>
                <div class="row"><span>1x Susu UHT</span><span>8.500</span></div>
                <div class="row"><span>1x Telur 1kg</span><span>26.000</span></div>
                <div class="hr"></div>
                <div class="row"><span>Subtotal</span><span><?=number_format($sampleSubtotal, 0, ',', '.')?></span></div>
                <div class="row" id="pvDiskon" data-has-value="<?=$hasDiskonValue ? '1' : '0'?>" style="<?=$showDiskonPreview ? '' : 'display:none;'?>"><span>Diskon</span><span><?=number_format($sampleDiskon, 0, ',', '.')?></span></div>
                <div class="row" id="pvHemat" data-has-value="<?=$hasHematValue ? '1' : '0'?>" style="<?=$showHematPreview ? '' : 'display:none;'?>"><span>Anda Hemat</span><span><?=number_format($sampleHemat, 0, ',', '.')?></span></div>
                <div class="row"><span><strong>Total</strong></span><span><strong><?=number_format($sampleTotal, 0, ',', '.')?></strong></span></div>
                <div class="row"><span>Tunai</span><span>60.000</span></div>
                <div class="row"><span>Kembali</span><span>6.000</span></div>
                <div id="pvPoin" data-has-pelanggan="<?=$sampleHasPelanggan ? '1' : '0'?>" style="<?=$showPoinPreview ? '' : 'display:none;'?>">
                    <div class="hr"></div>
                    <div class="row"><span>Poin Dapat</span><span><?=number_format($samplePoinDidapat, 0, ',', '.')?></span></div>
                    <div class="row"><span>Total Poin</span><span><?=number_format($sampleTotalPoin, 0, ',', '.')?></span></div>
                </div>
                <div class="hr"></div>
                <div class="center" id="pvFooterText"><?=nl2br(htmlspecialchars($footerText))?></div>
            </div>
        </div>
    </div>
</div>

<script>
const elPaper = document.getElementById('nota_paper_width');
const elScale = document.getElementById('nota_font_scale');
const elHeader = document.getElementById('nota_header_text');
const elFooter = document.getElementById('nota_footer_text');
const elShowLogo = document.getElementById('nota_show_logo');
const elShowAlamat = document.getElementById('nota_show_alamat');
const elShowTelepon = document.getElementById('nota_show_telepon');
const elShowKasir = document.getElementById('nota_show_kasir');
const elShowPoin = document.getElementById('nota_show_member_points');
const elShowDiskon = document.getElementById('nota_show_diskon');
const elShowHemat = document.getElementById('nota_show_hemat');
const togglePreviewBtn = document.getElementById('togglePreviewBtn');
const closePreviewBtn = document.getElementById('closePreviewBtn');
const previewModal = document.getElementById('previewModal');
const previewShell = document.getElementById('previewShell');

const pvReceipt = document.getElementById('previewReceipt');
const pvLogo = document.getElementById('pvLogo');
const pvAlamat = document.getElementById('pvAlamat');
const pvTelepon = document.getElementById('pvTelepon');
const pvKasir = document.getElementById('pvKasir');
const pvPoin = document.getElementById('pvPoin');
const pvDiskon = document.getElementById('pvDiskon');
const pvHemat = document.getElementById('pvHemat');
const pvHeader = document.getElementById('pvHeaderText');
const pvFooter = document.getElementById('pvFooterText');

function updatePreview() {
    pvReceipt.classList.toggle('w58', elPaper.value === '58');
    pvReceipt.classList.toggle('compact', elScale.value === 'compact');
    pvLogo.style.display = elShowLogo.checked ? '' : 'none';
    pvAlamat.style.display = elShowAlamat.checked ? '' : 'none';
    pvTelepon.style.display = elShowTelepon.checked ? '' : 'none';
    pvKasir.style.display = elShowKasir.checked ? '' : 'none';
    const hasPelanggan = pvPoin.dataset.hasPelanggan === '1';
    const hasDiskonValue = pvDiskon.dataset.hasValue === '1';
    const hasHematValue = pvHemat.dataset.hasValue === '1';
    pvPoin.style.display = (elShowPoin.checked && hasPelanggan) ? '' : 'none';
    pvDiskon.style.display = (elShowDiskon.checked && hasDiskonValue) ? '' : 'none';
    pvHemat.style.display = (elShowHemat.checked && hasHematValue) ? '' : 'none';
    pvHeader.textContent = elHeader.value.trim() || '-';
    pvFooter.textContent = elFooter.value.trim() || '-';
}

[elPaper, elScale, elHeader, elFooter, elShowLogo, elShowAlamat, elShowTelepon, elShowKasir, elShowPoin, elShowDiskon, elShowHemat]
    .forEach(el => el.addEventListener('input', updatePreview));
togglePreviewBtn.addEventListener('click', () => {
    previewModal.classList.add('active');
});
closePreviewBtn.addEventListener('click', () => {
    previewModal.classList.remove('active');
});
previewModal.addEventListener('click', (e) => {
    if (e.target === previewModal) previewModal.classList.remove('active');
});

updatePreview();
</script>
</body>
</html>
