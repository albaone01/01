<?php
require_once 'db.php';
require_once 'url.php';

function isPosRequestPath() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return strpos($uri, '/public/POS/') !== false || strpos($uri, '/ritel4/public/POS/') !== false;
}

function isLoggedIn() {
    return isset($_SESSION['pengguna_id']);
}

function isDeviceRegistered() {
    return isset($_SESSION['device_id']) && isset($_SESSION['toko_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        if (isPosRequestPath()) {
            header('Location: ' . app_url('/public/POS/login.php'));
        } else {
            header('Location: ' . app_url('/public/admin/login.php'));
        }
        exit;
    }
}

function requireDevice() {
    if (!isDeviceRegistered()) {
        if (isPosRequestPath()) {
            header('Location: ' . app_url('/public/POS/login.php') . '?need_device=1');
        } else {
            header('Location: /public/device_check.php');
        }
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
