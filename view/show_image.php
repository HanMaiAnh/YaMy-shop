<?php
// show_image.php
// Hiển thị ảnh sản phẩm trực tiếp từ DATABASE (không dùng thư mục assets/images)
// Fallback: hiển thị ảnh no-image.png nếu không có dữ liệu

require_once __DIR__ . '/../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid product ID');
}

// Truy vấn lấy ảnh từ DB
$stmt = $pdo->prepare("SELECT image_data, image_type FROM products WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if ($product && !empty($product['image_data'])) {
    $mime = $product['image_type'] ?: 'image/jpeg';
    header("Content-Type: $mime");
    header("Cache-Control: public, max-age=86400");
    echo $product['image_data'];
    exit;
}

// Nếu không có ảnh trong DB → dùng ảnh mặc định
$default = __DIR__ . '/../assets/images/no-image.png';
if (file_exists($default)) {
    header("Content-Type: image/png");
    readfile($default);
    exit;
}

http_response_code(404);
exit('No image found');
