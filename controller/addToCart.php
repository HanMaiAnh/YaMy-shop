<?php
session_start();
include_once __DIR__ . "/../model/product.model.php";

header('Content-Type: application/json');

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$productModel = new Product();
$productId = (int)$_POST['id'];
$product = $productModel->getProductById($productId);

if (!$product) {
    echo json_encode(['success' => false]);
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_SESSION['cart'][$productId])) {
    $_SESSION['cart'][$productId]['quantity'] += 1;
} else {
    $product['quantity'] = 1;
    $_SESSION['cart'][$productId] = $product;
}

$totalItems = array_sum(array_column($_SESSION['cart'], 'quantity'));

echo json_encode(['success' => true, 'cartCount' => $totalItems]);
