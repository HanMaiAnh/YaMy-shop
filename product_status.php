<?php
session_name('admin_session');
session_start();
require_once __DIR__ . '/../config/db.php'; // File này tạo ra $pdo

// Kiểm tra nếu chưa đăng nhập hoặc không phải admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id > 0) {
    // Đảo trạng thái sản phẩm: 1 -> 0, 0 -> 1
    $stmt = $pdo->prepare("UPDATE products SET status = NOT status WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: products.php");
exit;
