<?php
// Check if class is already defined to prevent duplicate declaration
if (!class_exists('Database')) {
    class Database {
        private $host = "localhost";
        private $db_name = "koteng_db";
        private $username = "kotengca_db";
        private $password = "P@ssw0rd";
        public $conn;
        
        public function getConnection() {
            $this->conn = null;
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password
                );
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->exec("set names utf8");
            } catch(PDOException $exception) {
                error_log("Database connection error: " . $exception->getMessage());
            }
            return $this->conn;
        }
    }
}
?>