<?php
require_once __DIR__ . '/url.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'kasir') {
    header("Location: " . app_url('/public/POS/login.php'));
    exit;
}
