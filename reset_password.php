<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['reset_user'])) {
    header("Location: forgot_password.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$pass || !$confirm) {
        $error = "Vui lòng nhập đủ mật khẩu!";
    } elseif ($pass !== $confirm) {
        $error = "Mật khẩu xác nhận không trùng!";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $_SESSION['reset_user']]);

        unset($_SESSION['reset_user']);
        $success = "Đổi mật khẩu thành công!";
        echo "<script>setTimeout(()=>{window.location='login.php'}, 1500);</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt mật khẩu mới</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .card-custom {
            background: #fff;
            border-radius: 16px;
            max-width: 420px;
            margin: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .card-header-custom {
            background: #667eea;
            padding: 2rem;
            text-align: center;
            color: #fff;
        }

        .btn-custom {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 10px;
            border-radius: 12px;
            font-weight: bold;
            color: #fff;
            width: 100%;
            transition: 0.3s;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0px 8px 18px rgba(102, 126, 234, .4);
        }
    </style>
</head>

<body>

<div class="card-custom">
    <div class="card-header-custom">
        <i class="fas fa-key fa-3x mb-3"></i>
        <h3>Đặt mật khẩu mới</h3>
        <p class="mb-0">Nhập mật khẩu mới cho tài khoản của bạn</p>
    </div>

    <div class="p-4">

        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                <button class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
                <button class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label fw-semibold">Mật khẩu mới</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu mới" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Xác nhận mật khẩu</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-check"></i></span>
                    <input type="password" name="confirm" class="form-control" placeholder="Xác nhận mật khẩu" required>
                </div>
            </div>

            <button class="btn-custom">
                <i class="fas fa-save me-2"></i>Đổi mật khẩu
            </button>

        </form>

        <p class="text-center mt-4">
            <a href="login.php" class="text-decoration-none fw-semibold" style="color:#667eea;">
                <i class="fas fa-arrow-left me-1"></i>Quay lại đăng nhập
            </a>
        </p>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
