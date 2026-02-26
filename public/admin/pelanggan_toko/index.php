<?php
// ... (Bagian PHP Logic di atas tetap sama sampai $levels) ...
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../.../../../inc/config.php';
require_once '../../.../../../inc/db.php';
require_once '../../.../../../inc/auth.php';
require_once '../../.../../../inc/functions.php';

requireLogin();
requireDevice();

$db = $pos_db;
$toko_id = (int)($_SESSION['toko_id'] ?? 0);
$q = trim($_GET['q'] ?? '');

function fetch_all(mysqli_stmt $stmt): array {
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function table_has_column($db, $table, $column) {
    $st = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $st->bind_param('ss', $table, $column);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (bool)$row;
}

$levelThresholdColumn = table_has_column($db, 'member_level', 'minimal_belanja') ? 'minimal_belanja' : 'minimal_poin';

$where = ["p.toko_id = ?", "p.deleted_at IS NULL"];
$types = 'i';
$params = [$toko_id];

if($q !== ''){
    $where[] = "(p.nama_pelanggan LIKE CONCAT('%',?,'%') OR p.telepon LIKE CONCAT('%',?,'%') OR p.kode_pelanggan LIKE CONCAT('%',?,'%'))";
    $types .= 'sss';
    $params[] = $q; $params[] = $q; $params[] = $q;
}

$sql = "SELECT p.pelanggan_id, p.nama_pelanggan, p.telepon, p.kode_pelanggan,
               pt.id AS pelanggan_toko_id, pt.level_id, pt.poin, pt.limit_kredit,
               pt.tanggal_daftar, pt.masa_berlaku, pt.exp, pt.masa_tenggang, 
               pt.exp_poin, pt.poin_awal, pt.poin_akhir,
               ml.nama_level as level_nama, ml.diskon_persen,
               COALESCE(pb.total_belanja_bulan, 0) AS total_belanja_bulan
        FROM pelanggan p
        LEFT JOIN pelanggan_toko pt ON pt.pelanggan_id = p.pelanggan_id AND pt.toko_id = p.toko_id AND pt.deleted_at IS NULL
        LEFT JOIN member_level ml ON ml.level_id = pt.level_id AND ml.toko_id = p.toko_id
        LEFT JOIN (
            SELECT pelanggan_id, toko_id, SUM(total_akhir) AS total_belanja_bulan
            FROM penjualan
            WHERE pelanggan_id IS NOT NULL
              AND DATE_FORMAT(dibuat_pada, '%Y-%m') = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')
            GROUP BY pelanggan_id, toko_id
        ) pb ON pb.pelanggan_id = p.pelanggan_id AND pb.toko_id = p.toko_id
        WHERE ".implode(' AND ', $where)."
        ORDER BY p.nama_pelanggan ASC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$pelanggan = fetch_all($stmt);
$stmt->close();

$lvSql = "SELECT level_id, nama_level, {$levelThresholdColumn} AS minimal_belanja, diskon_persen FROM member_level WHERE toko_id = ? AND deleted_at IS NULL ORDER BY {$levelThresholdColumn} ASC";
$lvStmt = $db->prepare($lvSql);
$lvStmt->bind_param("i", $toko_id);
$lvStmt->execute();
$levels = fetch_all($lvStmt);
$lvStmt->close();

$totalPlg = count($pelanggan);
$memberAktif = array_sum(array_map(fn($p)=> $p['level_id'] ? 1 : 0, $pelanggan));
$totalBelanjaBulan = array_sum(array_map(fn($p)=> (float)($p['total_belanja_bulan'] ?? 0), $pelanggan));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loyalty Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --success: #10b981;
            --danger: #ef4444;
            --bg: #f8fafc;
            --border: #e2e8f0;
            --text: #1e293b;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; }
        .wrapper { padding: 24px; max-width: 1600px; margin: 0 auto; }

        /* Header Style */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .stats-row { display: flex; gap: 12px; }
        .stat-box { background: #fff; padding: 12px 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-label { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 600; }
        .stat-val { font-size: 18px; font-weight: 700; color: var(--primary); }

        /* Buttons */
        .btn { padding: 10px 18px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-family: inherit; transition: 0.2s; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-settings { background: #fff; border: 1px solid var(--primary); color: var(--primary); }
        .btn-settings:hover { background: var(--primary); color: #fff; }
        .btn-save { background: var(--success); color: #fff; width: 100%; justify-content: center; margin-top: 15px; }

        /* Modal / Pop-up */
        .modal-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
            display: none; justify-content: center; align-items: center; z-index: 1000; 
        }
        .modal-content { 
            background: #fff; width: 90%; max-width: 500px; padding: 24px; 
            border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); 
            position: relative; animation: slideUp 0.3s ease;
        }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
        .close-modal { background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8; }

        /* Level Grid in Modal */
        .level-row { display: grid; grid-template-columns: 1fr 120px 80px; gap: 10px; margin-bottom: 12px; align-items: center; }
        .level-label { font-size: 13px; font-weight: 600; }
        input[type="number"], input[type="text"], input[type="date"] { padding: 8px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; width: 100%; }

        /* Table Design */
        .card-table { background: #fff; border-radius: 12px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .table-scroll { overflow-x: auto; max-height: 65vh; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th { position: sticky; top: 0; background: #f8fafc; padding: 14px; text-align: left; color: #64748b; border-bottom: 1px solid var(--border); z-index: 5; }
        tbody td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .badge-bronze { background: #ffedd5; color: #9a3412; }
        .badge-silver { background: #f1f5f9; color: #475569; }
        .badge-gold { background: #fef9c3; color: #854d0e; }
        .badge-platinum { background: #e0e7ff; color: #3730a3; }

        /* Search bar */
        .toolbar { padding: 16px; background: #fff; display: flex; justify-content: space-between; gap: 10px; }
        .search-box { flex-grow: 1; position: relative; }
        .search-box input { padding-left: 35px; width: 300px; }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="header">
        <div>
            <h1 style="margin:0; font-size: 22px;">Membership Pelanggan</h1>
            <p style="margin:4px 0 0; color: #64748b; font-size: 13px;">Kelola level otomatis berdasarkan belanja bulanan.</p>
        </div>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-label">Total Pelanggan</div>
                <div class="stat-val"><?=number_format($totalPlg)?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Omset Bulan Ini</div>
                <div class="stat-val">Rp <?=number_format($totalBelanjaBulan,0,',','.')?></div>
            </div>
            <button class="btn btn-settings" onclick="toggleModal(true)">
                ⚙️
            </button>
        </div>
    </div>

    <div class="card-table">
        <form class="toolbar" method="get">
            <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari pelanggan..." style="width: 300px; padding: 10px; border-radius: 8px; border: 1px solid #ddd;">
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Cari</button>
                <a href="../pelanggan/index.php" class="btn btn-outline" style="border:1px solid #ddd">+ Pelanggan</a>
            </div>
        </form>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Pelanggan</th>
                        <th>Level Saat Ini</th>
                        <th class="text-right">Belanja Bln Ini</th>
                        <th>Masa Berlaku / Exp</th>
                        <th>Poin (Sld/Akhir)</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pelanggan as $p): 
                        $badgeClass = match($p['level_nama'] ?? '') {
                            'Bronze' => 'badge-bronze', 'Silver' => 'badge-silver',
                            'Gold' => 'badge-gold', 'Platinum' => 'badge-platinum',
                            default => 'badge-none'
                        };
                    ?>
                    <tr>
                        <form method="post" action="save.php">
                            <input type="hidden" name="pelanggan_id" value="<?= (int)$p['pelanggan_id'] ?>">
                            <td>
                                <div style="font-weight:700;"><?=htmlspecialchars($p['nama_pelanggan'])?></div>
                                <div style="font-size:11px; color:#94a3b8;"><?=htmlspecialchars($p['kode_pelanggan'] ?: '-')?></div>
                            </td>
                            <td>
                                <span class="badge <?=$badgeClass?>"><?=htmlspecialchars($p['level_nama'] ?: 'Normal')?></span>
                            </td>
                            <td class="text-right" style="font-weight:600;">
                                Rp<?=number_format((float)($p['total_belanja_bulan'] ?? 0), 0, ',', '.')?>
                            </td>
                            <td>
                                <input type="date" name="exp" value="<?=htmlspecialchars($p['exp'] ?? '')?>" style="width:120px; font-size:11px;">
                            </td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <input type="number" name="poin" value="<?=(int)$p['poin']?>" style="width:50px;">
                                    <input type="number" name="poin_akhir" value="<?=(int)$p['poin_akhir']?>" style="width:50px; background:#f8fafc;">
                                </div>
                            </td>
                            <td>
                                <button type="submit" class="btn btn-primary" style="padding: 5px 10px; font-size:11px;">Simpan</button>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="levelModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0">⚙️ Aturan Level Member</h3>
            <button class="close-modal" onclick="toggleModal(false)">&times;</button>
        </div>
        <form action="level_rules_save.php" method="post">
            <p style="font-size: 12px; color: #64748b; margin-bottom: 20px;">
                Tentukan nilai belanja minimum dalam sebulan untuk mencapai level tertentu.
            </p>
            
            <div style="background: #f8fafc; padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                <div class="level-row" style="color: #64748b; font-size: 11px; font-weight: bold;">
                    <span>NAMA LEVEL</span>
                    <span>MIN. BELANJA (RP)</span>
                    <span>DISC %</span>
                </div>
                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 8px 0;">
                
                <?php foreach($levels as $lv): ?>
                <div class="level-row">
                    <span class="level-label"><?=htmlspecialchars($lv['nama_level'])?></span>
                    <input type="number" name="minimal_belanja[<?=(int)$lv['level_id']?>]" value="<?=(int)$lv['minimal_belanja']?>">
                    <input type="number" step="0.01" name="diskon_persen[<?=(int)$lv['level_id']?>]" value="<?=(float)$lv['diskon_persen']?>">
                </div>
                <?php endforeach; ?>
            </div>

            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                <input type="checkbox" name="apply_to_all" value="1" checked> 
                Terapkan & Sinkronkan ke semua member sekarang
            </label>

            <button type="submit" class="btn btn-save">Simpan Perubahan Aturan</button>
        </form>
    </div>
</div>

<script>
    function toggleModal(show) {
        const modal = document.getElementById('levelModal');
        modal.style.display = show ? 'flex' : 'none';
    }

    // Menutup modal jika klik di luar area box
    window.onclick = function(event) {
        const modal = document.getElementById('levelModal');
        if (event.target == modal) toggleModal(false);
    }
</script>

</body>
</html>