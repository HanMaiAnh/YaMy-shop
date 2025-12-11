<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

if (!isset($_SESSION['order_id'])) {
    header("Location: checkout.php");
    exit;
}

$orderId = $_SESSION['order_id'];

// --- Giả lập cập nhật trạng thái đơn hàng thành 'paid' ---
$stmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
$stmt->execute([$orderId]);

// --- Xóa giỏ hàng ---
unset($_SESSION['cart']);
unset($_SESSION['order_id']);
unset($_SESSION['order_total']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thanh toán MoMo</title>
    <style>
        body { font-family: Arial, sans-serif; background: #fafafa; padding: 50px; text-align: center; }
        .success { color: green; }
        a { display: inline-block; margin-top: 20px; color: #d32f2f; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <h2 class="success">Thanh toán thành công (test local)!</h2>
    <p>Đơn hàng #<?= $orderId ?> đã được ghi nhận.</p>
    <a href="index.php">← Quay về trang chủ</a>
</body>
</html>
