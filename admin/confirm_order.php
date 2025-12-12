<?php
// Kiểm tra xem người dùng đã đăng nhập và là admin hay chưa
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['vaitro'] !== 1) {
    header("Location: /clothing_store/view/client/login.php"); // Nếu không phải admin, chuyển hướng về trang login
    exit;
}

if (isset($_GET['id'])) {
    $orderId = $_GET['id'];

    try {
        // Kết nối CSDL
        $conn = new PDO("mysql:host=localhost;dbname=streetsoul_store999;charset=utf8", "root", "");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Cập nhật trạng thái đơn hàng thành "Đã xác nhận"
        $sql = "UPDATE orders SET status = 'Đã xác nhận' WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $orderId, PDO::PARAM_INT);
        $stmt->execute();

        // Chuyển hướng về trang quản lý đơn hàng sau khi cập nhật
        header("Location: /clothing_store/view/admin/orders.php");
        exit;
    } catch (PDOException $e) {
        echo "Lỗi kết nối: " . $e->getMessage();
    }
} else {
    echo "Không có ID đơn hàng để xác nhận.";
}
?>
