<?php
class Database {
    private $pdo;

    public function __construct() {
        $host = "localhost";
        $dbname = "otask";
        $username = "root";
        $password = "root";

        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=UTF8", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
?>
