<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';

$db = $pos_db; // ensure DB handle

// optional auth not enforced for print, but toko data taken from session if available
$tokoId = $_SESSION['toko_id'] ?? null;
$toko = [];
$cfg = [];

if ($tokoId) {
    // ambil profil toko
    $stmtToko = $db->prepare("SELECT nama_toko, alamat FROM toko WHERE toko_id=? AND deleted_at IS NULL");
    $stmtToko->bind_param('i', $tokoId);
    $stmtToko->execute();
    $toko = $stmtToko->get_result()->fetch_assoc() ?: [];
    $stmtToko->close();

    // ambil konfigurasi toko
    $stmtCfg = $db->prepare("SELECT nama_konfigurasi, nilai FROM toko_config WHERE toko_id=?");
    $stmtCfg->bind_param('i', $tokoId);
    $stmtCfg->execute();
    $resCfg = $stmtCfg->get_result();
    while ($row = $resCfg->fetch_assoc()) {
        $cfg[$row['nama_konfigurasi']] = $row['nilai'];
    }
    $stmtCfg->close();
}

function cfg($key, $default=''){
    global $cfg;
    return $cfg[$key] ?? $default;
}

function ensure_po_schema($db){
    $db->query("CREATE TABLE IF NOT EXISTS purchase_order (
        po_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        toko_id BIGINT NOT NULL DEFAULT 0,
        supplier_id BIGINT NULL,
        nomor VARCHAR(60) NOT NULL,
        tanggal DATE NULL,
        jatuh_tempo DATE NULL,
        subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
        pajak DECIMAL(15,2) NOT NULL DEFAULT 0,
        diskon DECIMAL(15,2) NOT NULL DEFAULT 0,
        ongkir DECIMAL(15,2) NOT NULL DEFAULT 0,
        total DECIMAL(15,2) NOT NULL DEFAULT 0,
        catatan VARCHAR(255) DEFAULT NULL,
        tipe_faktur ENUM('cash','tempo') NOT NULL DEFAULT 'cash',
        salesman VARCHAR(100) DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'draft',
        tempo_hari INT DEFAULT NULL,
        jenis_ppn VARCHAR(20) DEFAULT NULL,
        UNIQUE KEY uq_po_toko_nomor (toko_id, nomor),
        dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $need = [
        'toko_id' => "ALTER TABLE purchase_order ADD COLUMN toko_id BIGINT NOT NULL DEFAULT 0 AFTER po_id",
        'supplier_id' => "ALTER TABLE purchase_order ADD COLUMN supplier_id BIGINT NULL",
        'tanggal' => "ALTER TABLE purchase_order ADD COLUMN tanggal DATE NULL",
        'jatuh_tempo' => "ALTER TABLE purchase_order ADD COLUMN jatuh_tempo DATE NULL",
        'subtotal' => "ALTER TABLE purchase_order ADD COLUMN subtotal DECIMAL(15,2) NOT NULL DEFAULT 0",
        'pajak' => "ALTER TABLE purchase_order ADD COLUMN pajak DECIMAL(15,2) NOT NULL DEFAULT 0",
        'diskon' => "ALTER TABLE purchase_order ADD COLUMN diskon DECIMAL(15,2) NOT NULL DEFAULT 0",
        'ongkir' => "ALTER TABLE purchase_order ADD COLUMN ongkir DECIMAL(15,2) NOT NULL DEFAULT 0",
        'catatan' => "ALTER TABLE purchase_order ADD COLUMN catatan VARCHAR(255) DEFAULT NULL",
        'tipe_faktur' => "ALTER TABLE purchase_order ADD COLUMN tipe_faktur ENUM('cash','tempo') NOT NULL DEFAULT 'cash'",
        'salesman' => "ALTER TABLE purchase_order ADD COLUMN salesman VARCHAR(100) DEFAULT NULL",
        'tempo_hari' => "ALTER TABLE purchase_order ADD COLUMN tempo_hari INT DEFAULT NULL",
        'jenis_ppn' => "ALTER TABLE purchase_order ADD COLUMN jenis_ppn VARCHAR(20) DEFAULT NULL",
    ];
    foreach($need as $col=>$ddl){
        try{
            $cRes = $db->query("SHOW COLUMNS FROM purchase_order LIKE '$col'");
            if(!$cRes || $cRes->num_rows==0){
                $db->query($ddl);
            }
        }catch(Exception $e){}
    }

    // Migrasi unique lama: nomor global -> unik per toko
    try{
        $idxRes = $db->query("SHOW INDEX FROM purchase_order");
        $idxMap = [];
        if($idxRes){
            while($ix = $idxRes->fetch_assoc()){
                $k = (string)$ix['Key_name'];
                if(!isset($idxMap[$k])){
                    $idxMap[$k] = [
                        'non_unique' => (int)$ix['Non_unique'],
                        'cols' => []
                    ];
                }
                $idxMap[$k]['cols'][(int)$ix['Seq_in_index']] = (string)$ix['Column_name'];
            }
        }

        $hasComposite = false;
        foreach($idxMap as $k => $meta){
            ksort($meta['cols']);
            $cols = array_values($meta['cols']);
            if($meta['non_unique'] === 0 && count($cols) === 2 && $cols[0] === 'toko_id' && $cols[1] === 'nomor'){
                $hasComposite = true;
            }
        }

        foreach($idxMap as $k => $meta){
            if($k === 'PRIMARY') continue;
            if($meta['non_unique'] !== 0) continue;
            ksort($meta['cols']);
            $cols = array_values($meta['cols']);
            if(count($cols) === 1 && $cols[0] === 'nomor'){
                $db->query("ALTER TABLE purchase_order DROP INDEX `{$k}`");
            }
        }

        if(!$hasComposite){
            $db->query("ALTER TABLE purchase_order ADD UNIQUE KEY uq_po_toko_nomor (toko_id, nomor)");
        }
    }catch(Exception $e){}
}
ensure_po_schema($db);

$id = (int)($_GET['id'] ?? 0);
if(!$id) die('ID PO tidak ditemukan');
if(!$tokoId) die('Sesi toko tidak valid');

$stmt = $db->prepare("SELECT po.*, s.nama_supplier, s.telepon, s.alamat
                      FROM purchase_order po
                      LEFT JOIN supplier s ON s.supplier_id = po.supplier_id
                      WHERE po.po_id=? AND po.toko_id=? LIMIT 1");
$stmt->bind_param("ii", $id, $tokoId);
$stmt->execute();
$res = $stmt->get_result();
$po = $res ? $res->fetch_assoc() : null;
$stmt->close();
if(!$po) die('PO tidak ditemukan');

function rupiah($v){ return 'Rp '.number_format((float)$v,0,',','.'); }
$storeCity = trim((cfg('kota') ?: '') . (cfg('provinsi') ? ', '.cfg('provinsi') : '')); // kota, provinsi
$storeZip  = cfg('kode_pos');
$storeAddr = trim(($toko['alamat'] ?? '') . ($storeCity ? "\n$storeCity" : '') . ($storeZip ? "\n$storeZip" : ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Purchase Order #<?=htmlspecialchars($po['nomor'])?></title>
<style>
    /* Reset & Base */
    * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
    body { 
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
        background: #f1f5f9; 
        color: #1e293b; 
        margin: 0; 
        padding: 40px 20px; 
        line-height: 1.5;
    }

    /* Print Optimization */
    @media print {
        body { background: #fff; padding: 0; }
        .no-print { display: none; }
        .sheet { 
            box-shadow: none !important; 
            border: none !important; 
            margin: 0 !important; 
            max-width: 100% !important;
            padding: 0 !important;
        }
        @page { size: A4; margin: 15mm; }
    }

    /* Container */
    .sheet {
        background: #fff;
        max-width: 850px;
        margin: 0 auto;
        padding: 50px;
        border-radius: 12px;
        box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        border: 1px solid #e2e8f0;
        position: relative;
    }

    /* Header Section */
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
    .header-table td { vertical-align: top; border: none; padding: 0; }
    
    .brand-title { 
        font-size: 28px; 
        font-weight: 800; 
        color: #0f172a; 
        text-transform: uppercase; 
        letter-spacing: -0.025em;
        margin-bottom: 5px;
    }
    
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        background: #f0fdf4;
        color: #16a34a;
        border: 1px solid #dcfce7;
    }

    /* Grid Info */
    .info-grid { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 40px; 
        margin-bottom: 40px; 
        padding-bottom: 30px;
        border-bottom: 2px solid #f1f5f9;
    }
    
    .info-label { 
        font-size: 11px; 
        text-transform: uppercase; 
        color: #64748b; 
        font-weight: 700; 
        letter-spacing: 0.05em; 
        margin-bottom: 8px;
    }
    
    .info-content { font-size: 14px; color: #334155; }
    .info-content strong { color: #0f172a; font-size: 16px; }

    /* Table Styling */
    .main-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    .main-table th { 
        background: #f8fafc; 
        padding: 14px 12px; 
        text-align: left; 
        font-size: 12px; 
        text-transform: uppercase; 
        color: #475569; 
        font-weight: 700;
        border-top: 2px solid #e2e8f0;
        border-bottom: 2px solid #e2e8f0;
    }
    .main-table td { padding: 16px 12px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
    .main-table tr:last-child td { border-bottom: 2px solid #e2e8f0; }

    /* Summary Section */
    .summary-wrapper { display: flex; justify-content: flex-end; }
    .summary-table { width: 300px; border-collapse: collapse; }
    .summary-table td { padding: 8px 0; font-size: 14px; }
    .summary-table td:last-child { text-align: right; font-weight: 600; }
    .total-row td { 
        padding-top: 15px; 
        font-size: 18px !important; 
        font-weight: 800 !important; 
        color: #0ea5e9; 
    }

    /* Footer / Signatures */
    .footer-section { margin-top: 60px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; text-align: center; }
    .sig-box { height: 100px; border-bottom: 1px solid #e2e8f0; margin-bottom: 10px; }
    .sig-label { font-size: 12px; color: #64748b; font-weight: 600; }

    /* Buttons */
    .no-print { position: fixed; top: 20px; right: 20px; z-index: 999; }
    .btn-print { 
        padding: 12px 24px; 
        background: #0ea5e9; 
        color: #fff; 
        border: none; 
        border-radius: 8px; 
        font-weight: 700; 
        cursor: pointer;
        box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.4);
        transition: all 0.2s;
        display: flex; align-items: center; gap: 8px;
    }
    .btn-print:hover { transform: translateY(-2px); background: #0284c7; }
</style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-12 0v4h12v-4m-12 0h12"></path></svg>
            Cetak Purchase Order
        </button>
    </div>

    <div class="sheet">
        <table class="header-table">
            <tr>
                <td>
                    <div class="brand-title">Purchase Order</div>
                    <span class="status-badge"><?=strtoupper($po['status'] ?? 'DRAFT')?></span>
                </td>
                <td style="text-align: right;">
                    <?php if(cfg('logo_path')): ?>
                        <img src="<?=htmlspecialchars(cfg('logo_path'))?>" style="max-height: 60px; margin-bottom: 10px;">
                    <?php endif; ?>
                    <div style="font-weight: 700; font-size: 18px; color: #0f172a;"><?=htmlspecialchars($toko['nama_toko'])?></div>
                    <div style="font-size: 12px; color: #64748b; max-width: 250px; float: right; margin-top: 5px;">
                        <?=nl2br(htmlspecialchars($storeAddr))?>
                    </div>
                </td>
            </tr>
        </table>

        <div class="info-grid">
            <div>
                <div class="info-label">Dipesan Kepada:</div>
                <div class="info-content">
                    <strong><?=htmlspecialchars($po['nama_supplier'])?></strong><br>
                    <?=htmlspecialchars($po['telepon'])?><br>
                    <?=nl2br(htmlspecialchars($po['alamat']))?>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <div class="info-label">Nomor PO</div>
                    <div class="info-content" style="font-weight: 700; color: #0f172a;">#<?=htmlspecialchars($po['nomor'])?></div>
                    
                    <div class="info-label" style="margin-top: 15px;">Tanggal PO</div>
                    <div class="info-content"><?=date('d M Y', strtotime($po['tanggal']))?></div>
                </div>
                <div>
                    <div class="info-label">Syarat Bayar</div>
                    <div class="info-content"><?=strtoupper($po['tipe_faktur'])?> (<?=$po['tempo_hari']?> Hari)</div>

                    <div class="info-label" style="margin-top: 15px;">Jatuh Tempo</div>
                    <div class="info-content"><?=date('d M Y', strtotime($po['jatuh_tempo']))?></div>
                </div>
            </div>
        </div>

        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Deskripsi Produk</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Harga Satuan</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $det = $db->prepare("SELECT nama_barang, qty, harga, subtotal FROM purchase_order_detail WHERE po_id=?");
                $det->bind_param("i", $id);
                $det->execute();
                $detailRes = $det->get_result();
                while($d = $detailRes->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight: 600; color: #0f172a;"><?=htmlspecialchars($d['nama_barang'])?></td>
                        <td style="text-align: center;"><?=number_format($d['qty'])?></td>
                        <td style="text-align: right;"><?=number_format($d['harga'])?></td>
                        <td style="text-align: right; font-weight: 700; color: #0f172a;"><?=number_format($d['subtotal'])?></td>
                    </tr>
                <?php endwhile; $det->close(); ?>
            </tbody>
        </table>

        <div class="summary-wrapper">
            <table class="summary-table">
                <tr>
                    <td style="color: #64748b;">Subtotal</td>
                    <td><?=rupiah($po['subtotal'])?></td>
                </tr>
                <tr>
                    <td style="color: #64748b;">Pajak (PPN)</td>
                    <td><?=rupiah($po['pajak'])?></td>
                </tr>
                <tr>
                    <td style="color: #64748b;">Ongkos Kirim</td>
                    <td><?=rupiah($po['ongkir'])?></td>
                </tr>
                <?php if($po['diskon'] > 0): ?>
                <tr>
                    <td style="color: #ef4444;">Diskon</td>
                    <td style="color: #ef4444;">- <?=rupiah($po['diskon'])?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td>Total Akhir</td>
                    <td><?=rupiah($po['total'])?></td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 40px;">
            <div class="info-label">Catatan Tambahan:</div>
            <div style="font-size: 13px; color: #64748b; background: #f8fafc; padding: 15px; border-radius: 8px;">
                <?=nl2br(htmlspecialchars($po['catatan'] ?: 'Tidak ada catatan khusus.'))?>
            </div>
        </div>

        <div class="footer-section">
            <div>
                <div class="sig-label">Dibuat Oleh,</div>
                <div class="sig-box"></div>
                <div class="info-content" style="font-weight: 600;">Bagian Gudang</div>
            </div>
            <div>
                <div class="sig-label">Disetujui Oleh,</div>
                <div class="sig-box"></div>
                <div class="info-content" style="font-weight: 600;">Manager / Owner</div>
            </div>
            <div>
                <div class="sig-label">Supplier,</div>
                <div class="sig-box"></div>
                <div class="info-content" style="font-weight: 600;"><?=htmlspecialchars($po['nama_supplier'])?></div>
            </div>
        </div>
    </div>

</body>
</html>
