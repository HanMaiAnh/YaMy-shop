<?php
require '../config/db.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        $error = "Vui lòng nhập email!";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "Email không tồn tại!";
        } else {
            session_start();
            $_SESSION['reset_user'] = $user['id'];
            header("Location: reset_password.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quên mật khẩu</title>

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
            overflow: hidden;
        }

        .card-header-custom {
            background: #667eea;
            padding: 2rem;
            text-align: center;
            color: #fff;
        }

        .card-header-custom h3 {
            font-weight: 700;
            margin: 0;
        }

        .input-group-text {
            background: #f8f9fa;
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
        <i class="fas fa-unlock-alt fa-3x mb-3"></i>
        <h3>Quên mật khẩu</h3>
        <p class="mb-0 opacity-75">Nhập email để tiếp tục đặt lại mật khẩu</p>
    </div>

    <div class="p-4">

        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                <button class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label fw-semibold">Email</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-envelope text-muted"></i>
                    </span>
                    <input type="email" name="email" class="form-control" placeholder="Nhập email của bạn" required>
                </div>
            </div>

            <button class="btn-custom">
                <i class="fas fa-arrow-right me-2"></i>Tiếp tục
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
