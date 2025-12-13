<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

$error = '';
$email = $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!$email || !$password) {
        $error = 'Vui lòng nhập đầy đủ email/tên đăng nhập và mật khẩu!';
    } else {

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'] ?? 'user';

            if ($_SESSION['role'] === 'admin') {
                header("Location: ../admin/index.php");
            } else {
                header("Location: ../view/index.php");
            }
            exit;
        } else {
            $error = 'Email/Tên đăng nhập hoặc mật khẩu không đúng!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập - FashionStore</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
    body {
        background: #fff;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
    }

    .login-wrapper {
        width: 100%;
        min-height: 75vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px 0;
    }

    .login-box {
        width: 380px;
        background: white;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .login-title {
        text-align: center;
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 25px;
    }

    .input-group-custom {
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
    }

    .btn-login {
        width: 100%;
        background: #DC3545;
        border: none;
        padding: 10px;
        color: white;
        font-size: 15px;
        border-radius: 3px;
        font-weight: 600;
    }

    .btn-login:hover {
        opacity: 0.9;
    }

    .register-text {
        text-align: center;
        margin-top: 15px;
        font-size: 14px;
    }

    .register-text a {
        color: #DC3545;
        text-decoration: none;
        font-weight: bold;
    }
    </style>
</head>

<body>

<?php include '../includes/header.php'; ?>

<div class="login-wrapper">
    <div class="login-box">

        <div class="login-title">Đăng nhập vào YaMy Shop</div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">

            <label class="fw-semibold mb-1">Email hoặc tên đăng nhập</label>
            <div class="input-group-custom">
                <input type="text" name="email" 
                       required placeholder="Nhập email hoặc username"
                       value="<?= htmlspecialchars($email) ?>">
            </div>

            <label class="fw-semibold mb-1">Mật khẩu</label>
            <div class="input-group-custom">
                <input type="password" name="password" required placeholder="Nhập mật khẩu">
            </div>

            <button type="submit" class="btn-login">Đăng nhập</button>
        </form>

        <div class="register-text">
            Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
