<?php
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = $_SESSION['toko_id'] ?? null;
if (!$toko_id) { header('Location: pilih_gudang.php'); exit; }

// Ambil data toko
$stmtToko = $pos_db->prepare("SELECT nama_toko, alamat FROM toko WHERE toko_id=? AND deleted_at IS NULL");
$stmtToko->bind_param('i', $toko_id);
$stmtToko->execute();
$toko = $stmtToko->get_result()->fetch_assoc();

// Ambil konfigurasi k/v
$configs = [];
$stmtCfg = $pos_db->prepare("SELECT nama_konfigurasi, nilai FROM toko_config WHERE toko_id=?");
$stmtCfg->bind_param('i', $toko_id);
$stmtCfg->execute();
$resCfg = $stmtCfg->get_result();
while ($row = $resCfg->fetch_assoc()) {
    $configs[$row['nama_konfigurasi']] = $row['nilai'];
}

function cfg($key, $default='') {
    global $configs;
    return $configs[$key] ?? $default;
}

function save_cfg(string $key, string $val) {
    global $pos_db, $toko_id;
    $del = $pos_db->prepare("DELETE FROM toko_config WHERE toko_id=? AND nama_konfigurasi=?");
    $del->bind_param('is', $toko_id, $key);
    $del->execute();
    $ins = $pos_db->prepare("INSERT INTO toko_config (toko_id,nama_konfigurasi,nilai) VALUES (?,?,?)");
    $ins->bind_param('iss', $toko_id, $key, $val);
    $ins->execute();
}

$msg = ['ok'=>[], 'err'=>[]];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_general') {
        $nama_toko = trim($_POST['nama_toko'] ?? '');
        $timezone  = trim($_POST['timezone'] ?? 'UTC');
        $language  = trim($_POST['language'] ?? 'id');
        $currency  = trim($_POST['currency'] ?? 'IDR');
        $numberfmt = trim($_POST['number_format'] ?? '1.234,56');
        $datefmt   = trim($_POST['date_format'] ?? 'd/m/Y');
        $phone     = trim($_POST['phone'] ?? '');
        $email_cs  = trim($_POST['email_cs'] ?? '');
        $npwp      = trim($_POST['npwp'] ?? '');
        $kota      = trim($_POST['kota'] ?? '');
        $provinsi  = trim($_POST['provinsi'] ?? '');
        $kode_pos  = trim($_POST['kode_pos'] ?? '');
        $member_point_nominal = (float)($_POST['member_point_nominal'] ?? 1000);

        if ($member_point_nominal <= 0) {
            $msg['err'][] = 'Nominal per 1 koin harus lebih besar dari 0.';
        } elseif (!$nama_toko) {
            $msg['err'][] = 'Nama toko wajib diisi.';
        } else {
            // Update toko
            $stmt = $pos_db->prepare("UPDATE toko SET nama_toko=? WHERE toko_id=?");
            $stmt->bind_param('si', $nama_toko, $toko_id);
            $stmt->execute();

            // Logo upload (opsional)
            $logoPath = cfg('logo_path', '');
            if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/png'=>'png','image/jpeg'=>'jpg','image/jpg'=>'jpg'];
                $mime = mime_content_type($_FILES['logo']['tmp_name']);
                if (!isset($allowed[$mime])) {
                    $msg['err'][] = 'Logo harus PNG atau JPG.';
                } else {
                    $ext = $allowed[$mime];
                    $uploadDir = dirname(__DIR__) . '/assets/uploads';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                    $filename = 'logo_toko_'.$toko_id.'.'.$ext;
                    $dest = $uploadDir . '/' . $filename;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                        $logoPath = '/assets/uploads/'.$filename;
                    } else {
                        $msg['err'][] = 'Gagal mengunggah logo.';
                    }
                }
            }

            save_cfg('timezone', $timezone);
            save_cfg('language', $language);
            save_cfg('currency', $currency);
            save_cfg('number_format', $numberfmt);
            save_cfg('date_format', $datefmt);
            save_cfg('phone', $phone);
            save_cfg('email_cs', $email_cs);
            save_cfg('npwp', $npwp);
            save_cfg('kota', $kota);
            save_cfg('provinsi', $provinsi);
            save_cfg('kode_pos', $kode_pos);
            save_cfg('member_point_nominal', number_format($member_point_nominal, 2, '.', ''));
            if ($logoPath) save_cfg('logo_path', $logoPath);

            $msg['ok'][] = 'Pengaturan disimpan.';
        }
    } elseif ($action === 'save_ppn') {
        $scheme = $_POST['ppn_scheme'] ?? 'ppn'; // ppn | non_ppn | lainnya
        $enable = $scheme === 'non_ppn' ? 0 : 1;
        $ppn_in = (float)($_POST['ppn_persen'] ?? 0);
        if ($enable && ($ppn_in < 0 || $ppn_in > 100)) {
            $msg['err'][] = 'PPN harus 0 - 100%.';
        } else {
            $mode_in = ($_POST['ppn_mode'] ?? 'exclude') === 'include' ? 'include' : 'exclude';
            save_cfg('ppn_scheme', $scheme);
            save_cfg('ppn_enabled', (string)$enable);
            save_cfg('ppn_persen', number_format($ppn_in, 2, '.', ''));
            save_cfg('ppn_mode', $mode_in);
            $msg['ok'][] = 'Setting PPN disimpan.';
        }
    }
}

// Refresh cfg after save
$configs = [];
$stmtCfg->execute();
$resCfg = $stmtCfg->get_result();
while ($row = $resCfg->fetch_assoc()) {
    $configs[$row['nama_konfigurasi']] = $row['nilai'];
}
$toko['nama_toko'] = $nama_toko ?? ($toko['nama_toko'] ?? '');
$ppn_val    = cfg('ppn_persen','11.00');
$ppn_mode   = cfg('ppn_mode','exclude');
$ppn_scheme = cfg('ppn_scheme','ppn'); // ppn | non_ppn | lainnya
$ppn_on     = $ppn_scheme !== 'non_ppn';
$member_point_nominal = (float)cfg('member_point_nominal', '1000');
if ($member_point_nominal <= 0) $member_point_nominal = 1000.0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings</title>
<style>
    body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; }
    .container { max-width: 960px; margin: 30px auto; background:#fff; padding:24px; border-radius:12px; box-shadow:0 10px 24px rgba(0,0,0,0.06); }
    h2 { margin-top:0; letter-spacing:0.2px; }
    .card { border:1px solid #eef1f4; border-radius:12px; padding:18px; margin-top:16px; background:#fafbff; box-shadow:0 4px 12px rgba(0,0,0,0.04); }
    label { display:block; margin:8px 0 4px; font-weight:600; color:#374151; }
    input, select { width:100%; padding:10px 12px; border:1px solid #dfe3e6; border-radius:10px; background:#fff; }
    .row { display:grid; grid-template-columns: repeat(auto-fit,minmax(240px,1fr)); gap:14px; }
    button { padding:10px 16px; border:none; border-radius:10px; background:#6366f1; color:#fff; font-weight:600; cursor:pointer; box-shadow:0 8px 16px rgba(99,102,241,0.2); }
    .alert { padding:10px; border-radius:8px; margin-bottom:12px; }
    .ok { background:#ecfdf3; color:#027a48; }
    .err { background:#fff2f0; color:#b42318; }
</style>
</head>
<body>
<?php include '../../inc/header.php'; ?>
<div class="container">
    <h2>Settings</h2>
    <?php foreach ($msg['ok'] as $m): ?><div class="alert ok"><?=htmlspecialchars($m)?></div><?php endforeach; ?>
    <?php foreach ($msg['err'] as $m): ?><div class="alert err"><?=htmlspecialchars($m)?></div><?php endforeach; ?>

    <div class="card">
        <h3>General</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_general">
            <div class="row">
                <div>
                    <label>Nama Toko / Brand</label>
                    <input name="nama_toko" value="<?=htmlspecialchars($toko['nama_toko'] ?? '')?>" required>
                </div>
                <div>
                    <label>Timezone</label>
                    <select name="timezone">
                        <?php
                        $zones = ['Asia/Jakarta','Asia/Makassar','Asia/Jayapura','UTC'];
                        $tzVal = cfg('timezone','Asia/Jakarta');
                        foreach ($zones as $z) {
                            $sel = $tzVal===$z?'selected':'';
                            echo "<option value=\"$z\" $sel>$z</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label>Bahasa</label>
                    <select name="language">
                        <?php $lang = cfg('language','id'); ?>
                        <option value="id" <?=$lang==='id'?'selected':''?>>Bahasa Indonesia</option>
                        <option value="en" <?=$lang==='en'?'selected':''?>>English</option>
                    </select>
                </div>
                <div>
                    <label>Mata Uang</label>
                    <select name="currency">
                        <?php $cur = cfg('currency','IDR'); ?>
                        <option value="IDR" <?=$cur==='IDR'?'selected':''?>>IDR (Rp)</option>
                        <option value="USD" <?=$cur==='USD'?'selected':''?>>USD ($)</option>
                    </select>
                </div>
                <div>
                    <label>Format Angka</label>
                    <?php $nf = cfg('number_format','1.234,56'); ?>
                    <select name="number_format">
                        <option value="1.234,56" <?=$nf==='1.234,56'?'selected':''?>>1.234,56 (Eropa/ID)</option>
                        <option value="1,234.56" <?=$nf==='1,234.56'?'selected':''?>>1,234.56 (US)</option>
                    </select>
                </div>
                <div>
                    <label>Format Tanggal</label>
                    <?php $df = cfg('date_format','d/m/Y'); ?>
                    <select name="date_format">
                        <option value="d/m/Y" <?=$df==='d/m/Y'?'selected':''?>>31/12/2026</option>
                        <option value="Y-m-d" <?=$df==='Y-m-d'?'selected':''?>>2026-12-31</option>
                        <option value="m/d/Y" <?=$df==='m/d/Y'?'selected':''?>>12/31/2026</option>
                    </select>
                </div>
                <div>
                    <label>Logo</label>
                    <input type="file" name="logo" accept="image/png,image/jpeg">
                    <?php if (cfg('logo_path')): ?>
                        <div style="margin-top:6px;"><img src="<?=htmlspecialchars(cfg('logo_path'))?>" alt="Logo" style="max-height:60px;"></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label>Telepon</label>
                    <input name="phone" value="<?=htmlspecialchars(cfg('phone'))?>" placeholder="+62...">
                </div>
                <div>
                    <label>Email CS</label>
                    <input name="email_cs" value="<?=htmlspecialchars(cfg('email_cs'))?>" placeholder="cs@domain.com">
                </div>
                <div>
                    <label>NPWP / Tax ID</label>
                    <input name="npwp" value="<?=htmlspecialchars(cfg('npwp'))?>" placeholder="NPWP / Tax ID">
                </div>
                <div>
                    <label>Kota</label>
                    <input name="kota" value="<?=htmlspecialchars(cfg('kota'))?>">
                </div>
                <div>
                    <label>Provinsi</label>
                    <input name="provinsi" value="<?=htmlspecialchars(cfg('provinsi'))?>">
                </div>
                <div>
                    <label>Kode Pos</label>
                    <input name="kode_pos" value="<?=htmlspecialchars(cfg('kode_pos'))?>">
                </div>
                <div>
                    <label>Nominal per 1 Koin Member (Rp)</label>
                    <input type="number" min="1" step="1" name="member_point_nominal" value="<?=htmlspecialchars((string)number_format($member_point_nominal, 0, '.', ''))?>">
                    <small style="color:#6b7280;">Contoh: 1000 berarti setiap belanja Rp1.000 mendapat 1 koin.</small>
                </div>
            </div>
            <div style="margin-top:16px;">
                <button type="submit">Simpan Perubahan</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>PPN / Non-PPN</h3>
        <form method="post">
            <input type="hidden" name="action" value="save_ppn">
            <div class="row">
                <div>
                    <label>Skema</label>
                    <select name="ppn_scheme" id="ppn_scheme">
                        <option value="ppn" <?=$ppn_scheme==='ppn'?'selected':''?>>PPN (standar)</option>
                        <option value="non_ppn" <?=$ppn_scheme==='non_ppn'?'selected':''?>>Non PPN</option>
                        <option value="lainnya" <?=$ppn_scheme==='lainnya'?'selected':''?>>Lainnya</option>
                    </select>
                    <small style="color:#6b7280;">Pilih Non PPN jika usaha tidak memungut PPN.</small>
                </div>
                <div>
                    <label>Tarif PPN (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="ppn_persen" value="<?=htmlspecialchars($ppn_val)?>" required <?= $ppn_on ? '' : 'disabled' ?>>
                </div>
                <div>
                    <label>Mode Harga</label>
                    <select name="ppn_mode" <?= $ppn_on ? '' : 'disabled' ?>>
                        <option value="exclude" <?=$ppn_mode==='exclude'?'selected':''?>>Harga belum termasuk PPN</option>
                        <option value="include" <?=$ppn_mode==='include'?'selected':''?>>Harga sudah termasuk PPN</option>
                    </select>
                </div>
            </div>
            <p style="margin-top:8px;color:#4b5563;font-size:13px;">
                Non PPN: pilih opsi Non PPN (tarif & mode akan diabaikan). "Lainnya" bisa dipakai untuk skema khusus dengan tarif/mode yang Anda set.
            </p>
            <div style="margin-top:16px;">
                <button type="submit">Simpan PPN</button>
            </div>
        </form>
    </div>
</div>
<script>
// Enable/disable input based on scheme
const schemeSel = document.getElementById('ppn_scheme');
const ppnFields = ['ppn_persen','ppn_mode'].map(id=>document.getElementsByName(id)[0]);
function togglePpnFields(){
    const non = schemeSel && schemeSel.value === 'non_ppn';
    ppnFields.forEach(el=>{ if(el){ el.disabled = non; }});
}
schemeSel?.addEventListener('change', togglePpnFields);
togglePpnFields();
</script>
</body>
</html>
