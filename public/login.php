<?php
// Backward-compatible entrypoint: login umum dipindah ke /public/admin/login.php
require_once '../inc/url.php';
header('Location: ' . app_url('/public/admin/login.php'));
exit;
