<?php
// ==============================
// Database POS (utama)
define('DB_POS_HOST', '127.0.0.1');
define('DB_POS_USER', 'root');
define('DB_POS_PASS', '');
define('DB_POS_NAME', 'hyeepos');
define('DB_POS_PORT', 3306);

// Database Master (license / device)
define('DB_MASTER_HOST', '127.0.0.1');
define('DB_MASTER_USER', 'root');
define('DB_MASTER_PASS', '');
define('DB_MASTER_NAME', 'albaone_master');
define('DB_MASTER_PORT', 3306);

// Backward compatibility - map generic DB_* constants to DB_POS_*
// These are used by Database class in public/login.php
define('DB_HOST', DB_POS_HOST);
define('DB_USER', DB_POS_USER);
define('DB_PASS', DB_POS_PASS);
define('DB_NAME', DB_POS_NAME);
define('DB_PORT', DB_POS_PORT);

// Start session otomatis
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
