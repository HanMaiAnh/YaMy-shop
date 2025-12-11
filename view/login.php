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
        // Tìm user theo email HOẶC username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Lưu session với các cột CÓ THỰC SỰ TỒN TẠI
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'] ?? $user['email'];
            $_SESSION['role'] = $user['role'] ?? 'user';

            if (($user['role'] ?? 'user') === 'admin') {
                header('Location: ../admin/index.php');
            } else {
                header('Location: ../view/index.php');
            }
            exit;
        } else {
            $error = 'Email/tên đăng nhập hoặc mật khẩu không đúng!';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    body {
        background: #fff;
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
    }

    .login-wrapper {
        width: 100%;
        height: 80vh;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .login-box {
        width: 380px;
        background: #fff;
        border-radius: 8px;
        padding: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border: 1px solid #e5e5e5;
    }

    .login-title {
        text-align: center;
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
    }

    .input-group-custom {
        width: 100%;
        border: 1px solid #ddd;
        border-radius: 5px;
        display: flex;
        align-items: center;
        padding: 6px 10px;
        margin-bottom: 15px;
    }

    .input-group-custom i {
        color: #666;
        font-size: 14px;
        margin-right: 8px;
    }

    .input-group-custom input {
        border: none;
        width: 100%;
        outline: none;
        font-size: 14px;
    }

    .remember-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        font-size: 13px;
    }

    .btn-login {
        width: 100%;
        background: #ee4d2d;
        border: none;
        padding: 10px 0;
        color: #fff;
        font-size: 15px;
        font-weight: 600;
        border-radius: 3px;
        cursor: pointer;
        margin-bottom: 15px;
    }

    .btn-login:hover {
        opacity: 0.9;
    }

    .register-text {
        text-align: center;
        font-size: 14px;
    }

    .register-text a {
        color: #ee4d2d;
        text-decoration: none;
        font-weight: bold;
    }
</style>

<div class="login-wrapper">
    <div class="login-box">

        <div class="login-title">Chào mừng trở lại!</div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Email / Username -->
            <label class="fw-semibold mb-1">Tài khoản</label>
            <div class="input-group-custom">
                <i class="fas fa-user"></i>
                <input type="text" name="email" required placeholder="Nhập email hoặc tên đăng nhập" 
                       value="<?= htmlspecialchars($email) ?>">
            </div>

            <!-- Password -->
            <label class="fw-semibold mb-1">Mật khẩu</label>
            <div class="input-group-custom">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" required placeholder="Nhập mật khẩu">
            </div>

            <!-- Remember / Forgot -->
            <div class="remember-line">
                <div>
                    <input type="checkbox" id="remember"> 
                    <label for="remember">Ghi nhớ tài khoản</label>
                </div>
                <a href="#" style="color:#1677ff; text-decoration:none;">Quên mật khẩu?</a>
            </div>

            <!-- Button -->
            <button type="submit" class="btn-login">Đăng nhập</button>
        </form>

        <div class="register-text">
            Bạn chưa có tài khoản? <a href="register.php">Đăng ký</a>
        </div>

    </div>
</div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>