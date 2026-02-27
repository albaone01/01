<?php
require_once __DIR__ . '/url.php';
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['owner','admin'])) {
    header("Location: " . app_url('/public/admin/login.php'));
    exit;
}
