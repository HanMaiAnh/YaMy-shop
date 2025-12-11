<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

// TẮT hiển thị lỗi ra màn hình ở môi trường thật
// (khi debug có thể bật lại)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

$error = '';
$success = '';

// Nếu không có email đang chờ xác thực thì quay lại trang đăng ký
if (empty($_SESSION['pending_email'])) {
    header('Location: register.php');
    exit;
}

$email = $_SESSION['pending_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    if ($otp === '') {
        $error = 'Vui lòng nhập mã OTP!';
    } else {
        // Lấy user theo email
        $stmt = $pdo->prepare("
            SELECT id, otp_code, otp_expires_at, is_verified 
            FROM users 
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Không tìm thấy tài khoản để xác thực.';
        } elseif ((int)$user['is_verified'] === 1) {
            $error = 'Tài khoản này đã được xác thực trước đó.';
        } elseif ($user['otp_code'] !== $otp) {
            $error = 'Mã OTP không chính xác.';
        } elseif (strtotime($user['otp_expires_at']) < time()) {
            $error = 'Mã OTP đã hết hạn. Vui lòng đăng ký lại.';
        } else {
            // Xác thực thành công: cập nhật is_verified = 1 và xoá OTP
            $update = $pdo->prepare("
                UPDATE users
                SET is_verified = 1,
                    otp_code = NULL,
                    otp_expires_at = NULL
                WHERE id = ?
            ");
            $update->execute([$user['id']]);

            // Xoá email chờ xác thực trong session
            unset($_SESSION['pending_email']);

            $success = 'Xác thực thành công! Bạn có thể đăng nhập ngay bây giờ.';
            echo "<script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 2000);
                  </script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác thực OTP - FashionStore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .otp-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
        }
        .otp-header {
            background: #667eea;
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        .otp-header h3 {
            margin: 0;
            font-weight: 700;
        }
        .otp-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            text-align: center;
            letter-spacing: 4px;
            font-size: 1.4rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-verify {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="otp-card mx-auto">
                <div class="otp-header">
                    <h3>Xác thực tài khoản</h3>
                    <p class="mb-0 opacity-75">
                        Mã OTP đã được gửi tới email:<br>
                        <strong><?= htmlspecialchars($email) ?></strong>
                    </p>
                </div>
                <div class="otp-body">
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
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-center d-block">
                                Nhập mã OTP (6 số)
                            </label>
                            <input type="text" name="otp" maxlength="6" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-verify w-100 text-white">
                            <i class="fas fa-shield-alt me-2"></i>Xác thực
                        </button>
                    </form>

                    <p class="text-center mt-4 mb-0">
                        <a href="register.php" class="text-decoration-none" style="color:#667eea;">
                            Đăng ký lại
                        </a>
                        |
                        <a href="login.php" class="text-decoration-none" style="color:#667eea;">
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
