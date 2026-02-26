<?php
require_once '../../../inc/config.php';
require_once '../../../inc/db.php';
require_once '../../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = $_SESSION['toko_id'];
$kategori_id = (int)($_GET['id'] ?? 0);

if($kategori_id){
    // Soft delete: set deleted_at
    $stmt = $pos_db->prepare("UPDATE kategori_produk SET deleted_at=NOW() WHERE kategori_id=? AND toko_id=?");
    $stmt->bind_param("ii", $kategori_id, $toko_id);
    $stmt->execute();
}

header("Location: index.php");
exit;
?>
