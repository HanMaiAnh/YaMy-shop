<?php
include_once __DIR__ . "/../model/product.model.php";

$productModel = new Product();
$allProducts = $productModel->getAllProducts();




?>
