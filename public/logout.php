<?php
require_once '../inc/config.php';
require_once '../inc/url.php';
session_destroy();
header('Location: ' . app_url('/public/admin/login.php'));
exit;
?>
