<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';
require_once '../../../inc/lokasi_rak.php';

requireLogin();
requireDevice();

$db = $pos_db;
$tokoId = (int)($_SESSION['toko_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

lokasi_rak_ensure_schema($db);

function fetch_all_stmt_lr(mysqli_stmt $stmt): array {
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

/* Gudang aktif */
$stmt = $db->prepare("SELECT gudang_id,nama_gudang FROM gudang WHERE toko_id=? AND aktif=1 AND deleted_at IS NULL ORDER BY nama_gudang");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$gudangList = fetch_all_stmt_lr($stmt);
$stmt->close();

/* Produk aktif */
$stmt = $db->prepare("SELECT produk_id,nama_produk,sku,satuan FROM produk WHERE toko_id=? AND deleted_at IS NULL ORDER BY nama_produk LIMIT 1000");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$produkList = fetch_all_stmt_lr($stmt);
$stmt->close();

/* Lokasi rak untuk dropdown */
$stmt = $db->prepare("
    SELECT lr.lokasi_id, lr.gudang_id, lr.kode_lokasi, lr.nama_lokasi, g.nama_gudang
    FROM lokasi_rak lr
    LEFT JOIN gudang g ON g.gudang_id = lr.gudang_id
    WHERE lr.toko_id=? AND lr.deleted_at IS NULL
    ORDER BY g.nama_gudang, lr.kode_lokasi
");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$lokasiList = fetch_all_stmt_lr($stmt);
$stmt->close();

/* Filter */
$where = ["lr.toko_id = ?", "lr.deleted_at IS NULL"];
$types = "i";
$params = [$tokoId];
if ($q !== '') {
    $where[] = "(lr.kode_lokasi LIKE CONCAT('%',?,'%') OR lr.nama_lokasi LIKE CONCAT('%',?,'%') OR g.nama_gudang LIKE CONCAT('%',?,'%'))";
    $types .= "sss";
    array_push($params, $q, $q, $q);
}

/* Master lokasi rak */
$sql = "
    SELECT
        lr.lokasi_id, lr.gudang_id, lr.kode_lokasi, lr.nama_lokasi, lr.zona, lr.lorong, lr.level_rak, lr.bin,
        lr.kapasitas, lr.aktif, lr.catatan, lr.dibuat_pada, lr.diupdate_pada,
        g.nama_gudang,
        COUNT(DISTINCT plr.id) AS produk_count
    FROM lokasi_rak lr
    LEFT JOIN gudang g ON g.gudang_id = lr.gudang_id
    LEFT JOIN produk_lokasi_rak plr ON plr.lokasi_id = lr.lokasi_id AND plr.deleted_at IS NULL
    WHERE " . implode(' AND ', $where) . "
    GROUP BY lr.lokasi_id
    ORDER BY g.nama_gudang, lr.kode_lokasi
";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$lokasiRows = fetch_all_stmt_lr($stmt);
$stmt->close();

/* Penempatan produk */
$stmt = $db->prepare("
    SELECT
        plr.id, plr.gudang_id, plr.produk_id, plr.lokasi_id, plr.qty_display, plr.min_display, plr.max_display, plr.is_default,
        p.nama_produk, p.sku,
        lr.kode_lokasi, lr.nama_lokasi,
        g.nama_gudang
    FROM produk_lokasi_rak plr
    INNER JOIN produk p ON p.produk_id = plr.produk_id AND p.deleted_at IS NULL
    INNER JOIN lokasi_rak lr ON lr.lokasi_id = plr.lokasi_id AND lr.deleted_at IS NULL
    LEFT JOIN gudang g ON g.gudang_id = plr.gudang_id
    WHERE plr.toko_id=? AND plr.deleted_at IS NULL
    ORDER BY plr.is_default DESC, plr.diupdate_pada DESC
    LIMIT 500
");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$penempatanRows = fetch_all_stmt_lr($stmt);
$stmt->close();

$totalLokasi = count($lokasiRows);
$totalPenempatan = count($penempatanRows);
$lokasiTerisi = array_sum(array_map(fn($r) => ((int)$r['produk_count'] > 0 ? 1 : 0), $lokasiRows));
$lokasiKosong = max(0, $totalLokasi - $lokasiTerisi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lokasi Rak</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        :root {
            --primary:#0ea5e9;
            --primary-dark:#0369a1;
            --bg:#f1f5f9;
            --card:#fff;
            --ink:#0f172a;
            --muted:#64748b;
            --border:#e2e8f0;
            --success:#10b981;
            --danger:#ef4444;
            --warn:#f59e0b;
            --shadow:0 16px 40px rgba(15,23,42,0.08);
        }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:'Plus Jakarta Sans','Inter',system-ui; }
        .page { padding:28px 20px 48px; }
        .container { max-width:1320px; margin:0 auto; }
        .hero {
            background: linear-gradient(135deg, #0b0f1f 0%, #0f172a 45%, #0ea5e9 100%);
            color:#e2f4ff;
            border-radius:18px;
            padding:22px 24px;
            box-shadow:var(--shadow);
            display:grid;
            grid-template-columns:1.2fr 1fr;
            gap:16px;
        }
        .hero h1 { margin:6px 0 10px; font-size:28px; }
        .hero p { margin:0 0 12px; color:rgba(255,255,255,.88); }
        .hero-actions { display:flex; flex-wrap:wrap; gap:10px; }
        .btn {
            border:1px solid transparent;
            border-radius:10px;
            padding:10px 14px;
            cursor:pointer;
            font-weight:700;
            font-size:14px;
            display:inline-flex;
            align-items:center;
            gap:8px;
            transition:all .2s ease;
        }
        .btn:hover { transform:translateY(-1px); }
        .btn-primary { background:#fff; color:#0f172a; }
        .btn-ghost { background:rgba(255,255,255,.12); border-color:rgba(255,255,255,.3); color:#fff; }
        .btn-outline { background:#fff; border-color:var(--border); color:var(--ink); }
        .btn-danger { background:#fff1f2; border-color:#fecdd3; color:#be123c; }
        .metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; }
        .metric {
            background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.25);
            border-radius:12px;
            padding:12px 14px;
        }
        .metric small { color:rgba(255,255,255,.85); font-weight:700; letter-spacing:.05em; text-transform:uppercase; }
        .metric .value { margin-top:6px; font-size:22px; font-weight:800; }

        .panel { margin-top:16px; background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); overflow:hidden; }
        .panel-head { padding:12px 16px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .panel-head h3 { margin:0; font-size:16px; }
        .toolbar { display:flex; gap:10px; flex-wrap:wrap; }
        .toolbar input { border:1px solid var(--border); border-radius:10px; padding:10px 12px; min-width:240px; font-size:14px; }

        .table-wrap { overflow:auto; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:11px 12px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
        th { background:#f8fafc; color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
        tr:hover td { background:#f8fbff; }
        .meta { color:var(--muted); font-size:12px; }
        .tag { display:inline-flex; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; }
        .tag.ok { background:#dcfce7; color:#166534; }
        .tag.off { background:#fee2e2; color:#991b1b; }
        .tag.warn { background:#fef3c7; color:#92400e; }
        .row-actions { display:flex; gap:6px; justify-content:flex-end; }

        .split {
            display:grid;
            grid-template-columns:1fr;
            gap:16px;
        }

        .modal { display:none; position:fixed; inset:0; z-index:100; background:rgba(2,6,23,.48); backdrop-filter:blur(4px); }
        .modal-box { width:94%; max-width:680px; margin:4% auto; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 24px 56px rgba(15,23,42,.25); }
        .modal-head { padding:14px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
        .modal-body { padding:16px; display:grid; gap:10px; }
        .grid2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .grid3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; }
        label { font-size:12px; font-weight:700; color:var(--muted); display:block; margin-bottom:4px; }
        input, select, textarea { width:100%; border:1px solid var(--border); border-radius:10px; padding:10px 12px; font-size:14px; background:#fff; }
        textarea { min-height:84px; resize:vertical; }
        .modal-foot { padding:14px 16px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:8px; }

        @media (max-width: 980px) {
            .hero { grid-template-columns:1fr; }
        }
        @media (max-width: 720px) {
            .metrics { grid-template-columns:1fr; }
            .grid2, .grid3 { grid-template-columns:1fr; }
            .toolbar input { min-width:100%; }
        }
    </style>
</head>
<body>
<?php include '../../../inc/header.php'; ?>
<div class="page">
    <div class="container">
        <section class="hero">
            <div>
                <div style="letter-spacing:.08em;font-weight:800;font-size:12px;text-transform:uppercase;">Inventori Profesional</div>
                <h1>Lokasi Rak</h1>
                <p>Atur penempatan produk per gudang sampai level rak/bin agar picking, restock, dan audit stok lebih cepat.</p>
                <div class="hero-actions">
                    <button class="btn btn-primary" onclick="openLokasiModal()">+ Lokasi Rak</button>
                    <button class="btn btn-ghost" onclick="openAssignModal()">+ Penempatan Produk</button>
                </div>
            </div>
            <div class="metrics">
                <div class="metric">
                    <small>Total Lokasi</small>
                    <div class="value"><?=$totalLokasi?></div>
                </div>
                <div class="metric">
                    <small>Lokasi Kosong</small>
                    <div class="value"><?=$lokasiKosong?></div>
                </div>
                <div class="metric">
                    <small>Produk Ditempatkan</small>
                    <div class="value"><?=$totalPenempatan?></div>
                </div>
            </div>
        </section>

        <section class="split">
            <div class="panel">
                <div class="panel-head">
                    <h3>Master Lokasi Rak</h3>
                    <form class="toolbar" method="get">
                        <input type="text" name="q" placeholder="Cari kode / nama lokasi / gudang..." value="<?=htmlspecialchars($q)?>">
                        <button class="btn btn-outline" type="submit">Cari</button>
                    </form>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Lokasi</th>
                                <th>Gudang</th>
                                <th>Struktur Rak</th>
                                <th>Kapasitas</th>
                                <th>Status</th>
                                <th style="text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$lokasiRows): ?>
                            <tr><td colspan="7" class="meta" style="text-align:center;padding:18px;">Belum ada lokasi rak.</td></tr>
                        <?php else: foreach($lokasiRows as $r): ?>
                            <?php
                                $struct = [];
                                if ($r['zona'] !== '') $struct[] = 'Zona ' . $r['zona'];
                                if ($r['lorong'] !== '') $struct[] = 'Lorong ' . $r['lorong'];
                                if ($r['level_rak'] !== '') $struct[] = 'Level ' . $r['level_rak'];
                                if ($r['bin'] !== '') $struct[] = 'Bin ' . $r['bin'];
                            ?>
                            <tr>
                                <td><strong><?=htmlspecialchars($r['kode_lokasi'])?></strong></td>
                                <td>
                                    <?=htmlspecialchars($r['nama_lokasi'])?>
                                    <div class="meta"><?=htmlspecialchars($r['catatan'] ?: '-')?></div>
                                </td>
                                <td><?=htmlspecialchars($r['nama_gudang'] ?: '-')?></td>
                                <td><?=htmlspecialchars($struct ? implode(' • ', $struct) : '-')?></td>
                                <td>
                                    <?=number_format((int)$r['kapasitas'])?>
                                    <div class="meta"><?=number_format((int)$r['produk_count'])?> produk di lokasi ini</div>
                                </td>
                                <td>
                                    <?php if ((int)$r['aktif'] === 1): ?>
                                        <span class="tag ok">Aktif</span>
                                    <?php else: ?>
                                        <span class="tag off">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="btn btn-outline" onclick="editLokasi(<?=$r['lokasi_id']?>)">Edit</button>
                                        <button class="btn btn-danger" onclick="deleteLokasi(<?=$r['lokasi_id']?>)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h3>Penempatan Produk ke Rak</h3>
                    <button class="btn btn-outline" onclick="openAssignModal()">Tambah Penempatan</button>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Gudang</th>
                                <th>Lokasi</th>
                                <th>Display</th>
                                <th>Default</th>
                                <th style="text-align:right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$penempatanRows): ?>
                            <tr><td colspan="6" class="meta" style="text-align:center;padding:18px;">Belum ada penempatan produk.</td></tr>
                        <?php else: foreach($penempatanRows as $r): ?>
                            <tr
                                data-assign-id="<?=$r['id']?>"
                                data-gudang-id="<?=$r['gudang_id']?>"
                                data-produk-id="<?=$r['produk_id']?>"
                                data-lokasi-id="<?=$r['lokasi_id']?>"
                                data-qty-display="<?=$r['qty_display']?>"
                                data-min-display="<?=$r['min_display']?>"
                                data-max-display="<?=$r['max_display']?>"
                                data-is-default="<?=$r['is_default']?>"
                            >
                                <td>
                                    <strong><?=htmlspecialchars($r['nama_produk'])?></strong>
                                    <div class="meta"><?=htmlspecialchars($r['sku'] ?: '-')?></div>
                                </td>
                                <td><?=htmlspecialchars($r['nama_gudang'] ?: '-')?></td>
                                <td>
                                    <strong><?=htmlspecialchars($r['kode_lokasi'])?></strong>
                                    <div class="meta"><?=htmlspecialchars($r['nama_lokasi'])?></div>
                                </td>
                                <td>
                                    <?=$r['qty_display']?> (min <?=$r['min_display']?> / max <?=$r['max_display']?>)
                                </td>
                                <td>
                                    <?php if ((int)$r['is_default'] === 1): ?>
                                        <span class="tag warn">Lokasi Default</span>
                                    <?php else: ?>
                                        <span class="meta">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button class="btn btn-outline" onclick="editAssignFromRow(this)">Edit</button>
                                        <button class="btn btn-danger" onclick="deleteAssign(<?=$r['id']?>)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Modal Master Lokasi -->
<div class="modal" id="lokasiModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="lokasiModalTitle" style="margin:0;">Tambah Lokasi Rak</h3>
            <span style="cursor:pointer;font-size:22px;" onclick="closeLokasiModal()">&times;</span>
        </div>
        <form id="lokasiForm">
            <input type="hidden" name="lokasi_id">
            <div class="modal-body">
                <div class="grid2">
                    <div>
                        <label>Gudang</label>
                        <select name="gudang_id" required>
                            <option value="">Pilih Gudang</option>
                            <?php foreach($gudangList as $g): ?>
                                <option value="<?=$g['gudang_id']?>"><?=htmlspecialchars($g['nama_gudang'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="aktif">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="grid2">
                    <div>
                        <label>Kode Lokasi</label>
                        <input type="text" name="kode_lokasi" placeholder="Contoh: A-01-02" required>
                    </div>
                    <div>
                        <label>Nama Lokasi</label>
                        <input type="text" name="nama_lokasi" placeholder="Contoh: Rak Minuman A1" required>
                    </div>
                </div>
                <div class="grid3">
                    <div>
                        <label>Zona</label>
                        <input type="text" name="zona" placeholder="A">
                    </div>
                    <div>
                        <label>Lorong</label>
                        <input type="text" name="lorong" placeholder="01">
                    </div>
                    <div>
                        <label>Level Rak</label>
                        <input type="text" name="level_rak" placeholder="02">
                    </div>
                </div>
                <div class="grid2">
                    <div>
                        <label>Bin</label>
                        <input type="text" name="bin" placeholder="B01">
                    </div>
                    <div>
                        <label>Kapasitas</label>
                        <input type="number" name="kapasitas" min="0" value="0">
                    </div>
                </div>
                <div>
                    <label>Catatan</label>
                    <textarea name="catatan" placeholder="Catatan tambahan lokasi rak"></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline" onclick="closeLokasiModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Lokasi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Penempatan -->
<div class="modal" id="assignModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="assignModalTitle" style="margin:0;">Tambah Penempatan Produk</h3>
            <span style="cursor:pointer;font-size:22px;" onclick="closeAssignModal()">&times;</span>
        </div>
        <form id="assignForm">
            <input type="hidden" name="id">
            <div class="modal-body">
                <div class="grid2">
                    <div>
                        <label>Gudang</label>
                        <select name="gudang_id" id="assignGudang" required>
                            <option value="">Pilih Gudang</option>
                            <?php foreach($gudangList as $g): ?>
                                <option value="<?=$g['gudang_id']?>"><?=htmlspecialchars($g['nama_gudang'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Produk</label>
                        <select name="produk_id" required>
                            <option value="">Pilih Produk</option>
                            <?php foreach($produkList as $p): ?>
                                <option value="<?=$p['produk_id']?>"><?=htmlspecialchars($p['nama_produk'])?><?=($p['sku'] ? ' ('.$p['sku'].')' : '')?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label>Lokasi Rak</label>
                    <select name="lokasi_id" id="assignLokasi" required>
                        <option value="">Pilih Lokasi</option>
                        <?php foreach($lokasiList as $l): ?>
                            <option value="<?=$l['lokasi_id']?>" data-gudang="<?=$l['gudang_id']?>">
                                <?=htmlspecialchars(($l['nama_gudang'] ?: '-') . ' - ' . $l['kode_lokasi'] . ' (' . $l['nama_lokasi'] . ')')?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid3">
                    <div>
                        <label>Qty Display</label>
                        <input type="number" name="qty_display" min="0" value="0">
                    </div>
                    <div>
                        <label>Min Display</label>
                        <input type="number" name="min_display" min="0" value="0">
                    </div>
                    <div>
                        <label>Max Display</label>
                        <input type="number" name="max_display" min="0" value="0">
                    </div>
                </div>
                <div>
                    <label style="display:flex;align-items:center;gap:8px;color:var(--ink);font-size:13px;">
                        <input type="checkbox" name="is_default" value="1" style="width:auto;">
                        Jadikan lokasi default produk di gudang ini
                    </label>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline" onclick="closeAssignModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Penempatan</button>
            </div>
        </form>
    </div>
</div>

<script>
const lokasiModal = document.getElementById('lokasiModal');
const lokasiForm = document.getElementById('lokasiForm');
const assignModal = document.getElementById('assignModal');
const assignForm = document.getElementById('assignForm');
const assignGudang = document.getElementById('assignGudang');
const assignLokasi = document.getElementById('assignLokasi');

function openLokasiModal() {
    lokasiForm.reset();
    lokasiForm.lokasi_id.value = '';
    document.getElementById('lokasiModalTitle').innerText = 'Tambah Lokasi Rak';
    lokasiModal.style.display = 'block';
}
function closeLokasiModal() { lokasiModal.style.display = 'none'; }

async function editLokasi(id) {
    try {
        const r = await fetch(`../../../api/lokasi_rak_get.php?id=${id}`);
        const d = await r.json();
        if (!d.ok) throw new Error(d.msg || 'Gagal memuat lokasi');
        lokasiForm.reset();
        Object.keys(d.data).forEach(k => { if (lokasiForm[k]) lokasiForm[k].value = d.data[k] ?? ''; });
        lokasiForm.aktif.value = String(d.data.aktif ?? 1);
        document.getElementById('lokasiModalTitle').innerText = 'Edit Lokasi Rak';
        lokasiModal.style.display = 'block';
    } catch (e) {
        alert(e.message);
    }
}

async function deleteLokasi(id) {
    if (!confirm('Hapus lokasi ini? Penempatan produk di lokasi ini akan ikut dinonaktifkan.')) return;
    const fd = new FormData();
    fd.set('id', id);
    const r = await fetch('../../../api/lokasi_rak_delete.php', { method:'POST', body:fd });
    const d = await r.json();
    if (!d.ok) return alert(d.msg || 'Gagal menghapus');
    location.reload();
}

lokasiForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = lokasiForm.querySelector('button[type="submit"]');
    const old = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Menyimpan...';
    try {
        const fd = new FormData(lokasiForm);
        const r = await fetch('../../../api/lokasi_rak_save.php', { method:'POST', body:fd });
        const d = await r.json();
        if (!d.ok) throw new Error(d.msg || 'Gagal menyimpan');
        location.reload();
    } catch (e2) {
        alert(e2.message);
    } finally {
        btn.disabled = false;
        btn.innerText = old;
    }
});

function filterLokasiByGudang() {
    const gid = assignGudang.value;
    const current = assignLokasi.value;
    let keep = false;
    [...assignLokasi.options].forEach((opt, idx) => {
        if (idx === 0) {
            opt.hidden = false;
            return;
        }
        const ok = gid === '' ? true : String(opt.dataset.gudang) === String(gid);
        opt.hidden = !ok;
        if (ok && opt.value === current) keep = true;
    });
    if (!keep) assignLokasi.value = '';
}
assignGudang.addEventListener('change', filterLokasiByGudang);

function openAssignModal() {
    assignForm.reset();
    assignForm.id.value = '';
    document.getElementById('assignModalTitle').innerText = 'Tambah Penempatan Produk';
    filterLokasiByGudang();
    assignModal.style.display = 'block';
}
function closeAssignModal() { assignModal.style.display = 'none'; }

function editAssignFromRow(btn) {
    const tr = btn.closest('tr');
    if (!tr) return;
    assignForm.reset();
    assignForm.id.value = tr.dataset.assignId || '';
    assignForm.gudang_id.value = tr.dataset.gudangId || '';
    assignForm.produk_id.value = tr.dataset.produkId || '';
    filterLokasiByGudang();
    assignForm.lokasi_id.value = tr.dataset.lokasiId || '';
    assignForm.qty_display.value = tr.dataset.qtyDisplay || '0';
    assignForm.min_display.value = tr.dataset.minDisplay || '0';
    assignForm.max_display.value = tr.dataset.maxDisplay || '0';
    assignForm.is_default.checked = String(tr.dataset.isDefault || '0') === '1';
    document.getElementById('assignModalTitle').innerText = 'Edit Penempatan Produk';
    assignModal.style.display = 'block';
}

async function deleteAssign(id) {
    if (!confirm('Hapus penempatan produk ini?')) return;
    const fd = new FormData();
    fd.set('id', id);
    const r = await fetch('../../../api/lokasi_rak_assign_delete.php', { method:'POST', body:fd });
    const d = await r.json();
    if (!d.ok) return alert(d.msg || 'Gagal menghapus');
    location.reload();
}

assignForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = assignForm.querySelector('button[type="submit"]');
    const old = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Menyimpan...';
    try {
        const fd = new FormData(assignForm);
        fd.set('is_default', assignForm.is_default.checked ? '1' : '0');
        const r = await fetch('../../../api/lokasi_rak_assign_save.php', { method:'POST', body:fd });
        const d = await r.json();
        if (!d.ok) throw new Error(d.msg || 'Gagal menyimpan');
        location.reload();
    } catch (e2) {
        alert(e2.message);
    } finally {
        btn.disabled = false;
        btn.innerText = old;
    }
});

window.addEventListener('click', (e) => {
    if (e.target === lokasiModal) closeLokasiModal();
    if (e.target === assignModal) closeAssignModal();
});
</script>
</body>
</html>
