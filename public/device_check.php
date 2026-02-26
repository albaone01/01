<?php
require_once '../inc/config.php';
require_once '../inc/db.php';
require_once '../inc/device_guard.php';

if (checkDevice()) {
    header('Location: login.php');
    exit;
} else {
    header('Location: device_register.php');
    exit;
}