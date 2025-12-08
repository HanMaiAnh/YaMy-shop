<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Nhận dữ liệu từ form
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$address = trim($_POST['address'] ?? '');

$dob_day = $_POST['dob_day'] ?? '';
$dob_month = $_POST['dob_month'] ?? '';
$dob_year = $_POST['dob_year'] ?? '';

$birthday = null;
if ($dob_day && $dob_month && $dob_year) {
    $birthday = "$dob_year-$dob_month-$dob_day";
}

// Kiểm tra email hợp lệ
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = "Email không hợp lệ.";
    header("Location: profile.php");
    exit;
}

// Kiểm tra email trùng lặp
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$email, $user_id]);

if ($stmt->fetch()) {
    $_SESSION['flash_error'] = "Email đã được sử dụng bởi tài khoản khác.";
    header("Location: profile.php");
    exit;
}

// UPDATE
$stmt = $pdo->prepare("
    UPDATE users 
    SET full_name = ?, email = ?, phone = ?, gender = ?, birthday = ?, address = ?
    WHERE id = ?
");

$ok = $stmt->execute([
    $full_name,
    $email,
    $phone,
    $gender,
    $birthday,
    $address,
    $user_id
]);

if ($ok) {
    $_SESSION['flash_success'] = "Cập nhật thông tin thành công!";
} else {
    $_SESSION['flash_error'] = "Lỗi khi cập nhật hồ sơ.";
}

header("Location: profile.php");
exit;
?>
