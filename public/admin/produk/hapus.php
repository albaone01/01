<?php
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';

requireLogin();
requireDevice();

$toko_id = $_SESSION['toko_id'];
$produk_id = (int)$_GET['id'];

// Soft delete: set deleted_at
$stmt = $db->prepare("UPDATE produk SET deleted_at = NOW() WHERE produk_id = ? AND toko_id = ?");
$stmt->bind_param('ii', $produk_id, $toko_id);
if ($stmt->execute()) {
    // Redirect ke index
    header('Location: index.php');
    exit;
} else {
    die('Gagal menghapus');
}
?>