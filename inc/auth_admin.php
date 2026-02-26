<?php
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['owner','admin'])) {
    header("Location: /public/login.php");
    exit;
}