<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/mail_otp.php';

$error   = '';
$success = '';

$username = $_POST['username'] ?? '';
$email    = $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // VALIDATE
    if (!$username || !$email || !$password || !$confirm_password) {
        $error = 'Vui lòng nhập đầy đủ thông tin!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ!';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp!';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự!';
    } else {
        // Kiểm tra email tồn tại
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email này đã được đăng ký!';
        } else {
            // Tạo OTP
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $otp             = random_int(100000, 999999);
            $otp_expires_at  = date('Y-m-d H:i:s', time() + 10 * 60);

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, otp_code, otp_expires_at, is_verified, created_at)
                    VALUES (?, ?, ?, ?, ?, 0, NOW())
                ");
                $stmt->execute([$username, $email, $hashed_password, $otp, $otp_expires_at]);

                // Gửi OTP
                $send = sendOtpMail($email, $otp, $username);

                if ($send === true) {
                    $_SESSION['pending_email'] = $email;

                    $success = 'Đăng ký thành công! Vui lòng kiểm tra email để lấy mã OTP.';

                    echo "<script>
                        setTimeout(() => { 
                            window.location.href = 'verify_otp.php';
                        }, 1500);
                    </script>";
                } else {
                    $error = 'Đăng ký thành công nhưng không gửi được email OTP: ' . $send;
                }

            } catch (PDOException $e) {
                $error = 'Lỗi hệ thống: ' . $e->getMessage();
            }
        }
    }
}
?>

<?php include dirname(__DIR__) . '/includes/header.php'; ?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng ký - FashionStore</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">

<style>
    body {
        background: #fff;
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
    }

    /* BOX FORM NẰM GIỮA */
    .register-wrapper {
    width: 100%;
    min-height: 100vh; /* CHO CAO BẰNG CHIỀU CAO MÀN HÌNH */
    display: flex;
    justify-content: center;
    align-items: center;
    background: #fff;
    padding: 0;
    margin: 0;
}


    .register-box {
        width: 420px;
        background: #fff;
        border-radius: 8px;
        padding: 30px 35px;
        border: 1px solid #e5e5e5;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .title {
        text-align: center;
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .subtitle {
        text-align: center;
        font-size: 12px;
        color: #999;
        margin-bottom: 25px;
    }

    /* INPUT KHÔNG ICON */
    .input-group-custom {
        width: 100%;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 8px 10px;
        margin-bottom: 15px;
    }

    .input-group-custom input {
        border: none;
        width: 100%;
        outline: none;
        font-size: 14px;
        background: none;
    }

    .gender-box {
        margin-bottom: 15px;
    }

    .gender-title {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .btn-register {
        width: 100%;
        background: #DC3545;
        border: none;
        padding: 10px 0;
        color: #fff;
        font-size: 15px;
        font-weight: 600;
        border-radius: 3px;
        cursor: pointer;
        margin-top: 10px;
    }

    .btn-register:hover {
        opacity: 0.9;
    }

    .login-text {
        text-align: center;
        font-size: 14px;
        margin-top: 12px;
    }

    .login-text a {
        color: #DC3545;
        text-decoration: none;
        font-weight: bold;
    }
</style>
</head>

<body>

<div class="register-wrapper">
<div class="register-box">

    <div class="title">Tạo tài khoản mới</div>
    <div class="subtitle">Tham gia cùng YaMy Shop ngay hôm nay!</div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">

        <label class="fw-semibold mb-1">Họ tên</label>
        <div class="input-group-custom">
            <input type="text" name="fullname" placeholder="Nhập họ và tên" required>
        </div>

        <label class="fw-semibold mb-1">Tên đăng nhập</label>
        <div class="input-group-custom">
            <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" placeholder="Nhập tên đăng nhập" required>
        </div>

        <label class="fw-semibold mb-1">Mật khẩu</label>
        <div class="input-group-custom">
            <input type="password" name="password" placeholder="Nhập mật khẩu" required>
        </div>

        <label class="fw-semibold mb-1">Xác nhận mật khẩu</label>
        <div class="input-group-custom">
            <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
        </div>

        <label class="fw-semibold mb-1">Email</label>
        <div class="input-group-custom">
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="Nhập email" required>
        </div>

        <label class="fw-semibold mb-1">Ngày sinh</label>
        <div class="input-group-custom">
            <input type="text" name="birthday" placeholder="dd/mm/yyyy">
        </div>

        <div class="gender-box">
            <div class="gender-title">Giới tính</div>
            <input type="radio" name="gender" value="nam"> Nam
            &nbsp;&nbsp;
            <input type="radio" name="gender" value="nu"> Nữ
        </div>

        <button type="submit" class="btn-register">Đăng ký</button>
    </form>

    <div class="login-text">
        Bạn đã có tài khoản? <a href="login.php">Đăng Nhập</a>
    </div>

</div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
