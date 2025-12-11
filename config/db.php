<?php
// --- VÔ HIỆU HIỂN THỊ LỖI TRONG YÊU CẦU AJAX ---
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// --- THÔNG SỐ KẾT NỐI CƠ SỞ DỮ LIỆU ---
$host = 'localhost';
$db   = 'clothing_store';
$user = 'root'; // Thay bằng user MySQL của bạn
$pass = '';     // Thay bằng mật khẩu nếu có
$charset = 'utf8mb4';

// --- CẤU HÌNH DSN VÀ TÙY CHỌN PDO ---
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Ném lỗi exception
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Mặc định trả mảng kết hợp
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Bảo mật hơn
];

// --- KHỞI TẠO PDO ---
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Ghi lỗi ra file log thay vì hiển thị
    error_log("DATABASE CONNECTION ERROR: " . $e->getMessage());
    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Không thể kết nối CSDL.']);
        exit;
    } else {
        die("<h3 style='color:red'>Kết nối CSDL thất bại. Vui lòng thử lại sau.</h3>");
    }
}
?>
