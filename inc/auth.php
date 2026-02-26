<?php
require_once 'db.php';

function isLoggedIn() {
    return isset($_SESSION['pengguna_id']);
}

function isDeviceRegistered() {
    return isset($_SESSION['device_id']) && isset($_SESSION['toko_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /public/login.php');
        exit;
    }
}

function requireDevice() {
    if (!isDeviceRegistered()) {
        header('Location: /public/device_check.php');
        exit;
    }
}

function getCurrentUser() {
    global $pos_db; // pakai $pos_db bukan $db
    if (!isset($_SESSION['pengguna_id'])) return null;
    $id = (int)$_SESSION['pengguna_id'];
    
    $stmt = $pos_db->prepare("SELECT * FROM pengguna WHERE pengguna_id=? AND deleted_at IS NULL");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc();
}

function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['peran'] == $role;
}