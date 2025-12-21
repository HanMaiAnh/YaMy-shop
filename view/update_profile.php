<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$fullname = trim($_POST['fullname'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$address  = trim($_POST['address'] ?? '');
$gender   = $_POST['gender'] ?? null;

$day   = (int)($_POST['dob_day'] ?? 0);
$month = (int)($_POST['dob_month'] ?? 0);
$year  = (int)($_POST['dob_year'] ?? 0);

/* ================= VALIDATE ================= */

// Email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Email không hợp lệ';
    header('Location: profile.php');
    exit;
}

// Fullname
if ($fullname === '' || mb_strlen($fullname) < 3) {
    $_SESSION['flash_error'] = 'Họ và tên không hợp lệ';
    header('Location: profile.php');
    exit;
}

// Phone
if ($phone !== '' && !preg_match('/^(0|\+84)[0-9]{9}$/', $phone)) {
    $_SESSION['flash_error'] = 'Số điện thoại không hợp lệ';
    header('Location: profile.php');
    exit;
}

// Address (chặn nhập rác)
if (!empty($address)) {
    if (!preg_match('/^(?=.*[0-9])(?=.*[\p{L}]).{5,}$/u', $address)) {
        $_SESSION['flash_error'] = 'Địa chỉ phải có chữ, số nhà và tối thiểu 5 ký tự';
        header('Location: profile.php');
        exit;
    }
}

// Birthday
$birthday = null;
if ($day && $month && $year) {
    if (!checkdate($month, $day, $year)) {
        $_SESSION['flash_error'] = 'Ngày sinh không hợp lệ';
        header('Location: profile.php');
        exit;
    }

    if ($year < 1900 || $year > date('Y')) {
        $_SESSION['flash_error'] = 'Năm sinh không hợp lệ';
        header('Location: profile.php');
        exit;
    }

    $birthday = sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/* ================= UPDATE ================= */

try {
    $updates = [];
    $params  = [];

    $updates[] = "email = ?";
    $params[]  = $email;

    $updates[] = "fullName = ?";
    $params[]  = $fullname;

    $updates[] = "phone = ?";
    $params[]  = $phone;

    $updates[] = "address = ?";
    $params[]  = $address;

    $updates[] = "sex = ?";
    $params[]  = $gender;

    $updates[] = "birthday = ?";
    $params[]  = $birthday;

    $params[] = $user_id;

    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $_SESSION['flash_success'] = 'Cập nhật hồ sơ thành công';
    header('Location: profile.php');
    exit;

} catch (Exception $e) {
    error_log($e->getMessage());
    $_SESSION['flash_error'] = 'Có lỗi xảy ra, vui lòng thử lại';
    header('Location: profile.php');
    exit;
}
