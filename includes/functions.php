<?php
// Định nghĩa đường dẫn gốc
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', 'http://localhost/clothing_store'); // Thay bằng domain thật nếu deploy

// Hàm include an toàn
function include_view($file) {
    $path = BASE_PATH . '/view/' . $file;
    if (file_exists($path)) {
        return include $path;
    } else {
        die("File không tồn tại: $path");
    }
}

function include_admin($file) {
    $path = BASE_PATH . '/admin/' . $file;
    if (file_exists($path)) {
        return include $path;
    } else {
        die("File không tồn tại: $path");
    }
}

function asset($path) {
    return BASE_URL . '/assets/' . $path;
}

function upload($path) {
    $path = ltrim($path, '/');
    if (strpos($path, '..') !== false || strpos($path, '\\') !== false) {
        return BASE_URL . '/uploads/default.jpg';
    }
    $filename = basename($path);
    return BASE_URL . '/uploads/' . $filename;
}

// === TÍNH TỔNG GIỎ HÀNG – ĐÃ FIX GIÁ GIẢM ===
function calculate_cart_total($pdo, $cart) {
    if (empty($cart)) return 0;

    // Chỉ lấy id (không cần size|color)
    $ids = array_keys(array_filter($cart, function($k) {
        return strpos($k, '|') === false; // chỉ lấy key dạng id đơn
    }, ARRAY_FILTER_USE_KEY));

    if (empty($ids)) return 0;

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT id, 
               COALESCE(discounted_price, price * (1 - COALESCE(discount_percent, 0)/100)) as final_price
        FROM products 
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($ids);

    $total = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = $row['id'];
        // Tìm tất cả item có id này (có thể nhiều size/màu)
        foreach ($cart as $key => $item) {
            if (strpos($key, $pid . '|') === 0) {
                $total += $row['final_price'] * $item['quantity'];
            }
        }
    }
    return $total;
}
?>