<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $id = $_POST['id'] ?? null;
        $name = $_POST['name'] ?? '';
        $price = $_POST['price'] ?? 0;
        $image = !empty($_POST['image']) ? $_POST['image'] : 'default.jpg'; // Kiểm tra hình ảnh hợp lệ

        if (!empty($id) && !empty($name)) {
            if (isset($_SESSION['cart'][$id])) {
                $_SESSION['cart'][$id]['quantity'] += 1;
            } else {
                $_SESSION['cart'][$id] = [
                    'id' => $id,
                    'name' => $name,
                    'price' => $price,
                    'image' => $image,
                    'quantity' => 1
                ];
            }

            echo json_encode([
                'success' => true,
                'cartCount' => count($_SESSION['cart']),
                'cartItems' => $_SESSION['cart']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Dữ liệu sản phẩm không hợp lệ!'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Hành động không hợp lệ!'
        ]);
    }
}

if ($_POST['action'] === 'buyNow') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $image = $_POST['image'] ?? 'default.jpg';

    // Thêm sản phẩm vào session giỏ hàng
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $_SESSION['cart'][$id] = [
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'quantity' => $quantity,
        'image' => $image 
    ];

    // Chuyển hướng sang trang đặt hàng
    header("Location: /yamy_shop/view/client/order.php");
    exit;
}
