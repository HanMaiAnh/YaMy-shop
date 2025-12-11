<?php
// view/change_password.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php'; // đảm bảo trong file này có $pdo

// Bắt buộc phải đăng nhập
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Chỉ chấp nhận POST từ form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$user_id          = (int) $_SESSION['user_id'];
$current_password = trim($_POST['current_password'] ?? '');
$new_password     = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

// ===== Validate dữ liệu =====
if ($current_password === '' || $new_password === '' || $confirm_password === '') {
    $_SESSION['flash_error'] = 'Vui lòng nhập đầy đủ thông tin!';
    header('Location: profile.php');
    exit;
}

if (strlen($new_password) < 6) {
    $_SESSION['flash_error'] = 'Mật khẩu mới phải có ít nhất 6 ký tự!';
    header('Location: profile.php');
    exit;
}

if ($new_password !== $confirm_password) {
    $_SESSION['flash_error'] = 'Mật khẩu mới và xác nhận không khớp!';
    header('Location: profile.php');
    exit;
}

// ===== Lấy mật khẩu hiện tại trong DB =====
try {
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user password: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Có lỗi xảy ra. Vui lòng thử lại sau!';
    header('Location: profile.php');
    exit;
}

if (!$user) {
    $_SESSION['flash_error'] = 'Không tìm thấy tài khoản!';
    header('Location: profile.php');
    exit;
}

// ===== Kiểm tra mật khẩu hiện tại =====
if (!password_verify($current_password, $user['password'])) {
    $_SESSION['flash_error'] = 'Mật khẩu hiện tại không đúng!';
    header('Location: profile.php');
    exit;
}

// ===== Cập nhật mật khẩu mới =====
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->execute([$hashed_password, $user_id]);

    $_SESSION['flash_success'] = 'Đổi mật khẩu thành công!';
    header('Location: profile.php');
    exit;
} catch (Exception $e) {
    error_log("Error updating password: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Không thể cập nhật mật khẩu. Vui lòng thử lại!';
    header('Location: profile.php');
    exit;
}
