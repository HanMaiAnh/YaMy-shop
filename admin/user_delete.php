<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
$database = new Database();
$conn = $database->conn;

if (!isset($_SESSION['user']) || $_SESSION['user']['vaitro'] != 1) {
    header("Location: /streetsoul_store1/view/client/login.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("DELETE FROM users WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: users.php");
exit;
    