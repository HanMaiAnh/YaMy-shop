<?php
require_once __DIR__ . '/../config/db.php';

$order_id = $_GET['order_id'] ?? 0;

// Giả lập quá trình thanh toán thành công
$stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', status = 'completed' WHERE id = ?");
$stmt->execute([$order_id]);

echo "<h2 style='text-align:center; margin-top:100px;'>Thanh toán online thành công! 🎉</h2>";
echo "<p style='text-align:center;'><a href='index.php'>Quay về trang chủ</a></p>";
