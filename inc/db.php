<?php
require_once __DIR__ . '/config.php'; // pastikan path sesuai

class Database {
    private $conn;

    public function __construct($host, $user, $pass, $name, $port = 3306) {
        // Throw exceptions on error so transaction try/catch works
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->conn = new mysqli($host, $user, $pass, $name, $port);
        if ($this->conn->connect_error) die("Koneksi gagal: " . $this->conn->connect_error);
        $this->conn->set_charset("utf8mb4");
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function insertId() {
        return $this->conn->insert_id;
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }

    // Transaction helpers to mirror mysqli methods
    public function begin_transaction() {
        return $this->conn->begin_transaction();
    }

    public function commit() {
        return $this->conn->commit();
    }

    public function rollback() {
        return $this->conn->rollback();
    }

    // tambahkan di sini
    public function __get($name) {
        return property_exists($this->conn, $name) ? $this->conn->$name : null;
    }
}

// ==============================
// Instance database dari config
// ==============================
$pos_db = new Database(DB_POS_HOST, DB_POS_USER, DB_POS_PASS, DB_POS_NAME, DB_POS_PORT);
$master_db = new Database(DB_MASTER_HOST, DB_MASTER_USER, DB_MASTER_PASS, DB_MASTER_NAME, DB_MASTER_PORT);
