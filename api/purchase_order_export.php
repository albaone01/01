<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../inc/config.php';
require_once '../inc/db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="purchase_order.csv"');

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
        tempo_hari INT DEFAULT NULL,
        jenis_ppn VARCHAR(20) DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'draft',
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
ensure_po_schema($pos_db);
$out = fopen('php://output', 'w');
$tokoId = (int)($_SESSION['toko_id'] ?? 0);
if(!$tokoId){
    fputcsv($out, ['Error', 'Sesi toko tidak valid']);
    fclose($out);
    exit;
}
fputcsv($out, ['ID','Nomor','Supplier ID','Tanggal','Jatuh Tempo','Subtotal','Pajak','Diskon','Ongkir','Total','Catatan','Tipe','Salesman','Status','Dibuat']);
$stmt = $pos_db->prepare("SELECT po_id, nomor, supplier_id, tanggal, jatuh_tempo, subtotal, pajak, diskon, ongkir, total, catatan, tipe_faktur, salesman, status, dibuat_pada FROM purchase_order WHERE toko_id=? ORDER BY dibuat_pada DESC");
$stmt->bind_param("i", $tokoId);
$stmt->execute();
$res = $stmt->get_result();
if($res) while($r = $res->fetch_assoc()){
    fputcsv($out, [$r['po_id'],$r['nomor'],$r['supplier_id'],$r['tanggal'],$r['jatuh_tempo'],$r['subtotal'],$r['pajak'],$r['diskon'],$r['ongkir'],$r['total'],$r['catatan'],$r['tipe_faktur'],$r['salesman'],$r['status'],$r['dibuat_pada']]);
}
$stmt->close();
fclose($out);
exit;
