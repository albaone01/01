<?php
require_once '../inc/config.php';
session_destroy();
header('Location: login.php');
exit;
?>