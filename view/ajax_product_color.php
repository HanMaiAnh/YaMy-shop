<?php
// view/ajax_product_color.php
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(['ok' => false, 'msg' => 'ID không hợp lệ']);
    exit;
}

// Lấy sản phẩm
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS cat_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy sản phẩm']);
    exit;
}

// Lấy biến thể theo size
$stmtV = $pdo->prepare("
    SELECT 
        v.size_id,
        v.price,
        v.price_reduced,
        v.quantity,
        s.name AS size_name
    FROM product_variants v
    LEFT JOIN sizes s ON v.size_id = s.id
    WHERE v.product_id = ?
    ORDER BY s.id
");
$stmtV->execute([$id]);
$rowsV = $stmtV->fetchAll(PDO::FETCH_ASSOC);

$variants = [];
foreach ($rowsV as $v) {
    $base   = (float)$v['price'];
    $reduced= (float)$v['price_reduced'];
    $final  = ($reduced > 0 && $reduced < $base) ? $reduced : $base;

    $variants[] = [
        'size_id'     => (int)$v['size_id'],
        'size_name'   => $v['size_name'],
        'price'       => $base,
        'price_final' => $final,
        'quantity'    => (int)$v['quantity'],
    ];
}

// chọn size mặc định: ưu tiên size còn hàng
$defaultVariant = null;
foreach ($variants as $v) {
    if ($v['quantity'] > 0) {
        $defaultVariant = $v;
        break;
    }
}
if (!$defaultVariant && $variants) {
    $defaultVariant = $variants[0];
}

// Lấy ảnh
$stmtImg = $pdo->prepare("
    SELECT image_url 
    FROM product_images 
    WHERE product_id = ?
    ORDER BY id
");
$stmtImg->execute([$id]);
$imgFiles = $stmtImg->fetchAll(PDO::FETCH_COLUMN);

$images = [];
if ($imgFiles) {
    foreach ($imgFiles as $f) {
        if ($f !== '') {
            // upload() là helper có sẵn trong project của bạn
            $images[] = upload($f);
        }
    }
}

// fallback: nếu không có product_images thì dùng cột image (nếu có)
if (empty($images) && !empty($product['image'])) {
    $images[] = upload($product['image']);
}

// fallback cuối cùng: placeholder
if (empty($images)) {
    $images[] = asset('images/placeholder-product.png');
}

echo json_encode([
    'ok'              => true,
    'product_id'      => (int)$product['id'],
    'name'            => $product['name'],
    'cat_name'        => $product['cat_name'],
    'variants'        => $variants,
    'default_variant' => $defaultVariant,
    'images'          => $images,
]);
