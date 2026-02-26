<?php
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';
require_once '../../inc/functions.php';

requireLogin();
requireDevice();

$toko_id = $_SESSION['toko_id'] ?? null;
if (!$toko_id) { header('Location: pilih_gudang.php'); exit; }

$currentUser = getCurrentUser();
$canManage   = in_array($currentUser['peran'] ?? '', ['owner','manager']);

$msg = ['ok'=>[], 'err'=>[]];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $nama  = trim($_POST['nama']);
        $email = trim($_POST['email']);
        $peran = $_POST['peran'] ?? '';
        $pass  = $_POST['password'] ?? '';

        if (!$nama || !$peran || !$pass) {
            $msg['err'][] = 'Nama, peran, dan password wajib diisi';
        } elseif (!in_array($peran, ['owner','manager','kasir','gudang'])) {
            $msg['err'][] = 'Peran tidak valid';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            // Cek email ganda
            $stmt = $pos_db->prepare("SELECT 1 FROM pengguna WHERE email = ? AND deleted_at IS NULL");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows) {
                $msg['err'][] = 'Email sudah dipakai';
            } else {
                $stmt = $pos_db->prepare("
                    INSERT INTO pengguna (toko_id,nama,email,password,peran,aktif,dibuat_pada)
                    VALUES (?,?,?,?,?,1,NOW())
                ");
                $stmt->bind_param('issss', $toko_id, $nama, $email, $hash, $peran);
                $stmt->execute();
                $msg['ok'][] = 'User baru ditambahkan';
            }
        }

    } elseif ($action === 'edit_user') {
        $id    = (int)$_POST['id'];
        $nama  = trim($_POST['nama']);
        $email = trim($_POST['email']);
        $peran = $_POST['peran'] ?? '';

        $cek = $pos_db->prepare("SELECT peran FROM pengguna WHERE pengguna_id=? AND toko_id=? AND deleted_at IS NULL");
        $cek->bind_param('ii', $id, $toko_id);
        $cek->execute();
        $targetPeran = $cek->get_result()->fetch_assoc()['peran'] ?? null;

        if (!$targetPeran) {
            $msg['err'][] = 'User tidak ditemukan.';
        } elseif ($targetPeran === 'owner') {
            $msg['err'][] = 'User owner tidak boleh diubah.';
        } elseif (!$nama || !$peran || !in_array($peran, ['owner','manager','kasir','gudang'])) {
            $msg['err'][] = 'Data tidak lengkap atau peran tidak valid.';
        } else {
            // Cek email ganda kecuali milik sendiri
            $stmt = $pos_db->prepare("SELECT 1 FROM pengguna WHERE email=? AND pengguna_id<>? AND deleted_at IS NULL");
            $stmt->bind_param('si', $email, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows) {
                $msg['err'][] = 'Email sudah dipakai user lain.';
            } else {
                $stmt = $pos_db->prepare("UPDATE pengguna SET nama=?, email=?, peran=? WHERE pengguna_id=? AND toko_id=?");
                $stmt->bind_param('sssii', $nama, $email, $peran, $id, $toko_id);
                $stmt->execute();
                $msg['ok'][] = 'User diperbarui';
            }
        }

    } elseif ($action === 'toggle_user') {
        $id    = (int)$_POST['id'];
        $aktif = (int)$_POST['aktif'];

        // Cegah ubah owner
        $cek = $pos_db->prepare("SELECT peran FROM pengguna WHERE pengguna_id=? AND toko_id=?");
        $cek->bind_param('ii', $id, $toko_id);
        $cek->execute();
        $peranTarget = $cek->get_result()->fetch_assoc()['peran'] ?? null;
        if ($peranTarget === 'owner') {
            $msg['err'][] = 'User owner tidak boleh diubah.';
        } else {
            $stmt  = $pos_db->prepare("UPDATE pengguna SET aktif=? WHERE pengguna_id=? AND toko_id=?");
            $stmt->bind_param('iii', $aktif, $id, $toko_id);
            $stmt->execute();
            $msg['ok'][] = 'Status user diperbarui';
        }

    } elseif ($action === 'delete_user') {
        $id = (int)$_POST['id'];
        $cek = $pos_db->prepare("SELECT peran FROM pengguna WHERE pengguna_id=? AND toko_id=?");
        $cek->bind_param('ii', $id, $toko_id);
        $cek->execute();
        $peranTarget = $cek->get_result()->fetch_assoc()['peran'] ?? null;
        if ($peranTarget === 'owner') {
            $msg['err'][] = 'User owner tidak boleh diarsipkan.';
        } else {
            $stmt = $pos_db->prepare("UPDATE pengguna SET deleted_at=NOW() WHERE pengguna_id=? AND toko_id=?");
            $stmt->bind_param('ii', $id, $toko_id);
            $stmt->execute();
            $msg['ok'][] = 'User diarsipkan';
        }

    } elseif ($action === 'restore_user') {
        $id = (int)$_POST['id'];
        $stmt = $pos_db->prepare("UPDATE pengguna SET deleted_at=NULL, aktif=1 WHERE pengguna_id=? AND toko_id=? AND peran<>'owner'");
        $stmt->bind_param('ii', $id, $toko_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $msg['ok'][] = 'User dipulihkan';
        } else {
            $msg['err'][] = 'User owner tidak dapat dipulihkan/diubah di sini.';
        }

    } elseif ($action === 'add_device') {
        $nama = trim($_POST['nama_device']);
        $ip   = trim($_POST['ip_address']);
        $tipe = $_POST['tipe'] ?? 'kasir';

        if (!$nama || !$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            $msg['err'][] = 'Nama atau IP tidak valid';
        } else {
            // Validasi license & kuota device (master)
            $licenseStmt = $master_db->prepare("
                SELECT max_device, expired_at, grace_days, status
                FROM master_license
                WHERE toko_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $licenseStmt->bind_param('i', $toko_id);
            $licenseStmt->execute();
            $license = $licenseStmt->get_result()->fetch_assoc();

            if (!$license) {
                $msg['err'][] = 'License toko tidak ditemukan. Hubungi admin.';
            } else {
                $today = date('Y-m-d');
                $grace = (int)($license['grace_days'] ?? 0);
                $expiredPlusGrace = date('Y-m-d', strtotime($license['expired_at'].' +'.$grace.' days'));

                if ($license['status'] !== 'active' || $today > $expiredPlusGrace) {
                    $msg['err'][] = 'License sudah tidak aktif / kedaluwarsa.';
                } else {
                    // Hitung device aktif di master (pusat) agar konsisten
                    $countMaster = $master_db->prepare("
                        SELECT COUNT(*) AS total FROM master_device
                        WHERE toko_id=? AND status='active'
                    ");
                    $countMaster->bind_param('i', $toko_id);
                    $countMaster->execute();
                    $currentDevices = $countMaster->get_result()->fetch_assoc()['total'] ?? 0;

                    if ($currentDevices >= (int)$license['max_device']) {
                        $msg['err'][] = 'Kuota device telah mencapai batas license ('.$license['max_device'].').';
                    } else {
                        // Cek IP ganda
                        $stmt = $pos_db->prepare("SELECT 1 FROM device WHERE ip_address=? AND deleted_at IS NULL");
                        $stmt->bind_param('s', $ip);
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows) {
                            $msg['err'][] = 'IP sudah terdaftar';
                        } else {
                            $stmt = $pos_db->prepare("
                                INSERT INTO device (toko_id,nama_device,ip_address,tipe,aktif,dibuat_pada,created_at)
                                VALUES (?,?,?,?,1,NOW(),NOW())
                            ");
                            $stmt->bind_param('isss', $toko_id, $nama, $ip, $tipe);
                            $stmt->execute();

                            // Catat juga ke master_device untuk sinkron kuota
                            $stmtMaster = $master_db->prepare("
                                INSERT INTO master_device (toko_id, device_fingerprint, ip_address, tipe, device_name, last_seen, status)
                                VALUES (?, '', ?, ?, ?, NOW(), 'active')
                            ");
                            $stmtMaster->bind_param('isss', $toko_id, $ip, $tipe, $nama);
                            $stmtMaster->execute();

                            $msg['ok'][] = 'Device baru ditambahkan';
                        }
                    }
                }
            }
        }

    } elseif ($action === 'toggle_device') {
        $id    = (int)$_POST['id'];
        $aktif = (int)$_POST['aktif'];
        $stmt  = $pos_db->prepare("UPDATE device SET aktif=? WHERE device_id=? AND toko_id=?");
        $stmt->bind_param('iii', $aktif, $id, $toko_id);
        $stmt->execute();
        $msg['ok'][] = 'Status device diperbarui';

    } elseif ($action === 'delete_device') {
        $id = (int)$_POST['id'];
        $stmt = $pos_db->prepare("UPDATE device SET deleted_at=NOW() WHERE device_id=? AND toko_id=?");
        $stmt->bind_param('ii', $id, $toko_id);
        $stmt->execute();
        $msg['ok'][] = 'Device diarsipkan';

    } elseif ($action === 'restore_device') {
        $id = (int)$_POST['id'];
        $stmt = $pos_db->prepare("UPDATE device SET deleted_at=NULL, aktif=1 WHERE device_id=? AND toko_id=?");
        $stmt->bind_param('ii', $id, $toko_id);
        $stmt->execute();
        $msg['ok'][] = 'Device dipulihkan';
    }
}

/* Data list */
$users = $pos_db->prepare("
    SELECT pengguna_id,nama,email,peran,aktif,dibuat_pada
    FROM pengguna
    WHERE toko_id=? AND deleted_at IS NULL
    ORDER BY dibuat_pada DESC
");
$users->bind_param('i', $toko_id);
$users->execute();
$userRows = $users->get_result()->fetch_all(MYSQLI_ASSOC);

$users = $pos_db->prepare("
    SELECT pengguna_id,nama,email,peran,aktif,dibuat_pada,deleted_at
    FROM pengguna
    WHERE toko_id=?
    ORDER BY COALESCE(deleted_at,dibuat_pada) DESC
");
$users->bind_param('i', $toko_id);
$users->execute();
$userRows = $users->get_result()->fetch_all(MYSQLI_ASSOC);

$devices = $pos_db->prepare("
    SELECT device_id,nama_device,ip_address,tipe,aktif,terakhir_login,created_at,deleted_at
    FROM device
    WHERE toko_id=?
    ORDER BY COALESCE(deleted_at,created_at) DESC
");
$devices->bind_param('i', $toko_id);
$devices->execute();
$deviceRows = $devices->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage User & Device</title>
<style>
    body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; }
    .container { max-width: 1180px; margin: 30px auto; background:#fff; padding:24px; border-radius:14px; box-shadow:0 12px 30px rgba(0,0,0,0.06); }
    h2 { margin-top:0; letter-spacing:0.2px; }
    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th, td { padding:11px 12px; border-bottom:1px solid #f0f1f3; text-align:left; }
    th { background:#fafbff; font-weight:600; color:#2d3436; position:sticky; top:0; z-index:1; }
    .badge { padding:4px 8px; border-radius:6px; font-size:12px; color:#fff; }
    .on { background:#12b76a; }
    .off { background:#b42318; }
    form.inline { display:inline; }
    input, select { padding:10px 12px; width:100%; margin:6px 0; border:1px solid #dfe3e6; border-radius:10px; background:#fafbff; }
    button { padding:10px 14px; border:none; border-radius:10px; cursor:pointer; font-weight:600; }
    .btn-primary { background:#6366f1; color:#fff; box-shadow:0 8px 16px rgba(99,102,241,0.2); }
    .btn-ghost { background:#f4f5f7; color:#1f2937; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(360px,1fr)); gap:18px; }
    .alert { padding:10px; border-radius:6px; margin-bottom:10px; }
    .alert.ok { background:#ecfdf3; color:#027a48; }
    .alert.err { background:#fff2f0; color:#b42318; }
    .pill { display:inline-block; padding:7px 12px; border-radius:999px; background:#eef2ff; margin-right:8px; font-size:13px; color:#4338ca; }
    .section-head { display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .hidden { display:none; }
    .card { border:1px solid #eef1f4; border-radius:12px; padding:14px 16px; background:#fafbff; box-shadow:0 8px 18px rgba(17,24,39,0.04); }
    .table-wrap { background:#fff; border:1px solid #eef1f4; border-radius:12px; padding:6px 4px; box-shadow:0 6px 14px rgba(0,0,0,0.04); margin-top:14px; }
    .actions { position:relative; display:inline-block; }
    .actions button { margin-right:0; margin-bottom:0; }
    .action-menu { position:absolute; right:0; top:120%; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 10px 20px rgba(0,0,0,0.12); padding:6px; display:none; min-width:150px; z-index:20; }
    .action-menu button { width:100%; text-align:left; margin:2px 0; }
    .muted { color:#6b7280; font-size:13px; }
    /* FAB & drawer */
    .fab { position:fixed; right:24px; bottom:24px; width:54px; height:54px; border-radius:50%; background:#6366f1; color:#fff; border:none; box-shadow:0 14px 28px rgba(99,102,241,0.35); font-size:26px; cursor:pointer; z-index:1200; }
    .fab-menu { position:fixed; right:24px; bottom:90px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 12px 30px rgba(0,0,0,0.12); padding:10px; display:none; z-index:1200; min-width:180px; }
    .fab-menu button { width:100%; margin:4px 0; }
    /* Modal */
    .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.35); display:none; z-index:1400; }
    .modal { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:12px; padding:18px; width:420px; max-width:95%; box-shadow:0 16px 40px rgba(0,0,0,0.18); display:none; z-index:1401; }
    .modal h4 { margin:0 0 10px 0; }
</style>
</head>
<body>
    <?php include '../../inc/header.php'; ?>
    <div class="container">
        <h2>Manage User & Device</h2>
        <div class="pill">Login sebagai: <?=htmlspecialchars($currentUser['nama'] ?? '—')?> (<?=htmlspecialchars($currentUser['peran'] ?? '—')?>)</div>

        <?php foreach ($msg['ok'] as $m): ?><div class="alert ok"><?=htmlspecialchars($m)?></div><?php endforeach; ?>
        <?php foreach ($msg['err'] as $m): ?><div class="alert err"><?=htmlspecialchars($m)?></div><?php endforeach; ?>

        <?php if ($canManage): ?>
        <?php endif; ?>

        <h3 style="margin-top:30px;">Daftar User</h3>
        <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th>Dibuat</th><th>Arsip</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($userRows as $u): ?>
                <?php $isArchived = !is_null($u['deleted_at']); ?>
                <tr>
                    <td><?=htmlspecialchars($u['nama'])?></td>
                    <td><?=htmlspecialchars($u['email'])?></td>
                    <td><?=htmlspecialchars($u['peran'])?></td>
                    <td>
                        <?php if ($isArchived): ?>
                            <span class="badge off">Arsip</span>
                        <?php else: ?>
                            <span class="badge <?=$u['aktif']?'on':'off'?>"><?=$u['aktif']?'Aktif':'Nonaktif'?></span>
                        <?php endif; ?>
                    </td>
                    <td><?=htmlspecialchars($u['dibuat_pada'])?></td>
                    <td><?=$isArchived ? htmlspecialchars($u['deleted_at']) : '—'?></td>
                    <td>
                        <?php if ($canManage && $u['peran'] !== 'owner'): ?>
                        <div class="actions">
                            <button class="btn-ghost" type="button" onclick="toggleActionMenu(this)">⋯</button>
                            <div class="action-menu">
                                <?php if (!$isArchived): ?>
                                    <button type="button" onclick="openEditModal(<?=htmlspecialchars($u['pengguna_id'])?>,'<?=htmlspecialchars($u['nama'], ENT_QUOTES)?>','<?=htmlspecialchars($u['email'], ENT_QUOTES)?>','<?=htmlspecialchars($u['peran'])?>')">Edit</button>
                                    <form method="post">
                                        <input type="hidden" name="action" value="toggle_user">
                                        <input type="hidden" name="id" value="<?=$u['pengguna_id']?>">
                                        <input type="hidden" name="aktif" value="<?=$u['aktif']?0:1?>">
                                        <button><?=$u['aktif']?'Nonaktifkan':'Aktifkan'?></button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Arsipkan user ini?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?=$u['pengguna_id']?>">
                                        <button>Arsip</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" onsubmit="return confirm('Pulihkan user ini?');">
                                        <input type="hidden" name="action" value="restore_user">
                                        <input type="hidden" name="id" value="<?=$u['pengguna_id']?>">
                                        <button>Pulihkan</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php else: ?>
                            <span class="muted">Owner dikunci</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <h3 style="margin-top:30px;">Daftar Device</h3>
        <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Nama</th><th>IP</th><th>Tipe</th><th>Status</th><th>Last Login</th><th>Created</th><th>Arsip</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($deviceRows as $d): ?>
                <?php $isDArchived = !is_null($d['deleted_at']); ?>
                <tr>
                    <td><?=htmlspecialchars($d['nama_device'])?></td>
                    <td><?=htmlspecialchars($d['ip_address'])?></td>
                    <td><?=htmlspecialchars($d['tipe'])?></td>
                    <td>
                        <?php if ($isDArchived): ?>
                            <span class="badge off">Arsip</span>
                        <?php else: ?>
                            <span class="badge <?=$d['aktif']?'on':'off'?>"><?=$d['aktif']?'Aktif':'Nonaktif'?></span>
                        <?php endif; ?>
                    </td>
                    <td><?=htmlspecialchars($d['terakhir_login'] ?: '-')?></td>
                    <td><?=htmlspecialchars($d['created_at'])?></td>
                    <td><?=$isDArchived ? htmlspecialchars($d['deleted_at']) : '—'?></td>
                    <td>
                        <?php if ($canManage): ?>
                        <div class="actions">
                            <button class="btn-ghost" type="button" onclick="toggleActionMenu(this)">⋯</button>
                            <div class="action-menu">
                                <?php if (!$isDArchived): ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="toggle_device">
                                        <input type="hidden" name="id" value="<?=$d['device_id']?>">
                                        <input type="hidden" name="aktif" value="<?=$d['aktif']?0:1?>">
                                        <button><?=$d['aktif']?'Nonaktifkan':'Aktifkan'?></button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Arsipkan device ini?');">
                                        <input type="hidden" name="action" value="delete_device">
                                        <input type="hidden" name="id" value="<?=$d['device_id']?>">
       							    <button>Arsip</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" onsubmit="return confirm('Pulihkan device ini?');">
                                        <input type="hidden" name="action" value="restore_device">
                                        <input type="hidden" name="id" value="<?=$d['device_id']?>">
                                        <button>Pulihkan</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<button class="fab" id="fabBtn">+</button>
<div class="fab-menu" id="fabMenu">
    <button class="btn-primary" onclick="openAdd('user')">Tambah User</button>
    <button class="btn-ghost" onclick="openAdd('device')">Tambah Device</button>
</div>
<?php endif; ?>

<div class="modal-backdrop" id="editBackdrop"></div>
<div class="modal" id="editModal">
    <h4>Edit User</h4>
    <form method="post">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="id" id="editId">
        <input id="editNama" name="nama" placeholder="Nama lengkap" required>
        <input id="editEmail" name="email" placeholder="Email">
        <select id="editPeran" name="peran" required>
            <option value="owner">Owner</option>
            <option value="manager">Manager</option>
            <option value="kasir">Kasir</option>
            <option value="gudang">Gudang</option>
        </select>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
            <button type="button" class="btn-ghost" onclick="closeEdit()">Batal</button>
            <button class="btn-primary" type="submit">Simpan</button>
        </div>
    </form>
</div>

<div class="modal-backdrop" id="addBackdrop"></div>
<div class="modal" id="addModal">
    <h4 id="addTitle">Tambah</h4>
    <form method="post" id="addForm">
        <input type="hidden" name="action" id="addAction" value="add_user">
        <div id="addUserFields">
            <input name="nama" placeholder="Nama lengkap" required>
            <input name="email" placeholder="Email (opsional)">
            <select name="peran" required>
                <option value="">Pilih peran</option>
                <option value="owner">Owner</option>
                <option value="manager">Manager</option>
                <option value="kasir">Kasir</option>
                <option value="gudang">Gudang</option>
            </select>
            <input type="password" name="password" placeholder="Password" required>
        </div>
        <div id="addDeviceFields" class="hidden">
            <input name="nama_device" placeholder="Nama device" required>
            <input name="ip_address" placeholder="IP address" value="<?=$_SERVER['REMOTE_ADDR']?>" required>
            <select name="tipe" required>
                <option value="kasir">Kasir</option>
                <option value="admin">Admin</option>
                <option value="gudang">Gudang</option>
            </select>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
            <button type="button" class="btn-ghost" onclick="closeAdd()">Batal</button>
            <button class="btn-primary" type="submit">Simpan</button>
        </div>
    </form>
</div>

<script>
const fabBtn = document.getElementById('fabBtn');
const fabMenu = document.getElementById('fabMenu');
const editModal = document.getElementById('editModal');
const editBackdrop = document.getElementById('editBackdrop');
const addModal = document.getElementById('addModal');
const addBackdrop = document.getElementById('addBackdrop');
const addTitle = document.getElementById('addTitle');
const addAction = document.getElementById('addAction');
const addUserFields = document.getElementById('addUserFields');
const addDeviceFields = document.getElementById('addDeviceFields');

if (fabBtn) {
    fabBtn.addEventListener('click', () => {
        fabMenu.style.display = fabMenu.style.display === 'block' ? 'none' : 'block';
    });
}

function openAdd(mode) {
    if (!addModal || !addBackdrop) return;
    addModal.querySelector('form').reset();
    if (mode === 'user') {
        addTitle.textContent = 'Tambah User';
        addAction.value = 'add_user';
        addUserFields.classList.remove('hidden');
        addDeviceFields.classList.add('hidden');
    } else {
        addTitle.textContent = 'Tambah Device';
        addAction.value = 'add_device';
        addUserFields.classList.add('hidden');
        addDeviceFields.classList.remove('hidden');
    }
    fabMenu.style.display = 'none';
    addModal.style.display = 'block';
    addBackdrop.style.display = 'block';
}
function closeAdd() {
    if (!addModal || !addBackdrop) return;
    addModal.style.display = 'none';
    addBackdrop.style.display = 'none';
}
if (addBackdrop) addBackdrop.addEventListener('click', closeAdd);

function openEditModal(id, nama, email, peran) {
    if (!editModal || !editBackdrop) return;
    document.getElementById('editId').value = id;
    document.getElementById('editNama').value = nama;
    document.getElementById('editEmail').value = email;
    document.getElementById('editPeran').value = peran;
    editModal.style.display = 'block';
    editBackdrop.style.display = 'block';
}
function closeEdit() {
    if (!editModal || !editBackdrop) return;
    editModal.style.display = 'none';
    editBackdrop.style.display = 'none';
}
if (editBackdrop) editBackdrop.addEventListener('click', closeEdit);

function toggleActionMenu(btn) {
    const menu = btn.nextElementSibling;
    if (!menu) return;
    const openMenus = document.querySelectorAll('.action-menu');
    openMenus.forEach(m => { if (m !== menu) m.style.display='none'; });
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    document.addEventListener('click', (e) => {
        if (!btn.parentElement.contains(e.target)) {
            menu.style.display = 'none';
        }
    }, { once:true });
}
</script>
</body>
</html>
