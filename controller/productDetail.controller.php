<?php
include_once __DIR__ . "/../config/db.php";

class Product {
    private $db;
    public function __construct() {
        $this->db = new Database();
    }

    public function getAllProducts() {
        $query = "SELECT * FROM products";
        return $this->db->conn->query($query);
    }
}
?>
