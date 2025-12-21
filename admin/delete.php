<?php
require_once __DIR__ . '/../../config/db.php';
$db = new Database();
$conn = $db->conn;

$id = $_GET['id'];
$conn->query("DELETE FROM products WHERE id=$id");
header("Location: products.php");
?>
