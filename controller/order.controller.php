<?php
session_start(); // Bắt buộc để dùng $_SESSION

// Bật hiển thị lỗi để dễ debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Kiểm tra phương thức POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Lấy dữ liệu từ form
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $cart = $_SESSION['cart'] ?? [];
    $shippingFee = 30000; // Phí ship cố định

    // Kiểm tra dữ liệu hợp lệ
    if (!empty($name) && !empty($phone) && !empty($address)) {

        // Include file cấu hình DB và model
        include_once __DIR__ . '/../config/db.php';
        include_once __DIR__ . '/../model/order.model.php';

        // Lưu đơn hàng vào cơ sở dữ liệu
        $order_id = insertOrder($name, $phone, $address, $cart, $shippingFee);

        if ($order_id) {
            // Đặt hàng thành công, xóa giỏ hàng
            unset($_SESSION['cart']);

            // Chuyển hướng tới trang thành công
            header('Location: ../view/client/order-success.php');
            exit(); // Dừng quá trình script để tránh in ra dữ liệu nữa
        } else {
            // Nếu có lỗi khi lưu đơn hàng
            echo "❌ Có lỗi khi lưu đơn hàng!";
        }
    } else {
        // Nếu dữ liệu không hợp lệ
        echo "⚠️ Vui lòng điền đầy đủ thông tin!";
    }
} else {
    // Nếu không phải là POST request
    echo "⛔️ Yêu cầu không hợp lệ (chỉ chấp nhận POST)!"; 
}
?>
