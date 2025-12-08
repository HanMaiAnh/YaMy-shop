<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/send_mail.php'; // file dùng PHPMailer

$error = '';
$success = '';
$username = $_POST['username'] ?? '';
$email    = $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --------- VALIDATE ----------
    if (!$username || !$email || !$password || !$confirm_password) {
        $error = 'Vui lòng nhập đầy đủ thông tin!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ!';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp!';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự!';
    } else {
        // Kiểm tra email đã tồn tại
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email này đã được đăng ký!';
        } else {
            // --------- XỬ LÝ ĐĂNG KÝ + OTP  ----------
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Tạo OTP 6 số và thời gian hết hạn (10 phút)
            $otp           = random_int(100000, 999999);
            $otp_expires_at = date('Y-m-d H:i:s', time() + 10 * 60);

            try {
                // Thêm user với trạng thái CHƯA xác thực
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, otp_code, otp_expires_at, is_verified, created_at)
                    VALUES (?, ?, ?, ?, ?, 0, NOW())
                ");
                $stmt->execute([$username, $email, $hashed_password, $otp, $otp_expires_at]);

                // Gửi email OTP bằng PHPMailer (trong send_mail.php)
                $mailSent = sendOtpMail($email, $otp, $username);

                $result = sendOtpMail($email, $otp, $username);

                if ($result === true) {
                    $_SESSION['pending_email'] = $email;
                    $success = 'Đăng ký thành công! Vui lòng kiểm tra email để lấy mã OTP.';
                    echo "<script>setTimeout(() => { window.location.href = 'verify_otp.php'; }, 2000);</script>";
                } else {
                    $error = 'Đăng ký thành công nhưng không gửi được email OTP: ' . $result;
                }
            } catch (PDOException $e) {
                $error = 'Lỗi hệ thống: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng ký - FashionStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
        }
        .register-header {
            background: #667eea;
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        .register-header h3 {
            margin: 0;
            font-weight: 700;
        }
        .register-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        .logo {
            width: 60px;
            height: 60px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="register-card mx-auto">
                <div class="register-header">
                    <div class="logo">
                        <i class="fas fa-user-plus fa-2x text-primary"></i>
                    </div>
                    <h3>Tạo tài khoản mới</h3>
                    <p class="mb-0 opacity-75">Tham gia cùng FashionStore ngay hôm nay!</p>
                </div>

                <div class="register-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <!-- USERNAME -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tên đăng nhập</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-user text-muted"></i>
                                </span>
                                <input type="text" name="username" class="form-control border-start-0"
                                       value="<?= htmlspecialchars($username) ?>" required
                                       placeholder="Nhập tên đăng nhập">
                            </div>
                        </div>

                        <!-- EMAIL -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-envelope text-muted"></i>
                                </span>
                                <input type="email" name="email" class="form-control border-start-0"
                                       value="<?= htmlspecialchars($email) ?>" required
                                       placeholder="Nhập email của bạn">
                            </div>
                            <small class="text-muted">
                                Sau khi đăng ký, mã OTP sẽ được gửi đến email này.
                            </small>
                        </div>

                        <!-- PASSWORD -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mật khẩu</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password" name="password" class="form-control border-start-0"
                                       required minlength="6" placeholder="Tối thiểu 6 ký tự">
                            </div>
                        </div>

                        <!-- CONFIRM PASSWORD -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Xác nhận mật khẩu</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password" name="confirm_password" class="form-control border-start-0"
                                       required placeholder="Nhập lại mật khẩu">
                            </div>
                        </div>

                        <!-- REGISTER BUTTON -->
                        <button type="submit" class="btn btn-primary btn-register w-100 text-white">
                            <i class=""></i>Đăng ký 
                        </button>
                    </form>

                    <p class="text-center mt-4 mb-0">
                        Đã có tài khoản?
                        <a href="login.php" class="fw-bold text-decoration-none" style="color: #667eea;">
                            Đăng nhập
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
