<?php
// view/update_profile.php
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

$user_id   = (int) $_SESSION['user_id'];

$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = isset($_POST['phone'])   ? trim($_POST['phone'])   : null;
$address   = isset($_POST['address']) ? trim($_POST['address']) : null;
$gender    = isset($_POST['gender'])  ? trim($_POST['gender'])  : null;

$dob_day   = isset($_POST['dob_day'])   ? (int)$_POST['dob_day']   : 0;
$dob_month = isset($_POST['dob_month']) ? (int)$_POST['dob_month'] : 0;
$dob_year  = isset($_POST['dob_year'])  ? (int)$_POST['dob_year']  : 0;

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Email không hợp lệ.';
    header('Location: profile.php');
    exit;
}

// Ghép ngày sinh nếu đủ 3 phần
$birthday = null;
if ($dob_day && $dob_month && $dob_year && checkdate($dob_month, $dob_day, $dob_year)) {
    $birthday = sprintf('%04d-%02d-%02d', $dob_year, $dob_month, $dob_day);
}

try {
    // Lấy danh sách cột hiện có
    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN, 0);
    $existing = is_array($cols) ? $cols : [];

    $updates = [];
    $params  = [];

    // luôn update email
    $updates[] = "email = ?";
    $params[]  = $email;

    // full_name
    if (in_array('full_name', $existing, true)) {
        $updates[] = "full_name = ?";
        $params[]  = $full_name;
    }

    // phone
    if (in_array('phone', $existing, true)) {
        $updates[] = "phone = ?";
        $params[]  = $phone;
    }

    // address
    if (in_array('address', $existing, true)) {
        $updates[] = "address = ?";
        $params[]  = $address;
    }

    // gender
    if (in_array('gender', $existing, true)) {
        $updates[] = "gender = ?";
        $params[]  = $gender;
    }

    // birthday
    if (in_array('birthday', $existing, true)) {
        $updates[] = "birthday = ?";
        $params[]  = $birthday; // có thể null nếu chưa chọn đủ
    }

    // WHERE id = ?
    $params[] = $user_id;

    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $_SESSION['flash_success'] = 'Cập nhật hồ sơ thành công!';
    header('Location: profile.php?updated=1');
    exit;

} catch (Exception $e) {
    error_log("Update profile error: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Cập nhật thất bại. Vui lòng thử lại.';
    header('Location: profile.php');
    exit;
}
