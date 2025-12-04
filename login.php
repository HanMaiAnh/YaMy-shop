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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
        }
        .login-header {
            background: #667eea;
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-header h3 {
            margin: 0;
            font-weight: 700;
        }
        .login-body {
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
        .btn-login {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        .logo {
            width: 60px;
            height: 60px;
            background: white;
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
                <div class="login-card mx-auto">
                    <div class="login-header">
                        <div class="logo">
                            <i class="fas fa-tshirt fa-2x text-primary"></i>
                        </div>
                        <h3>Chào mừng trở lại!</h3>
                        <p class="mb-0 opacity-75">Đăng nhập để tiếp tục mua sắm</p>
                    </div>

                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email / Tên đăng nhập</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" name="email" class="form-control border-start-0" 
                                           value="<?= htmlspecialchars($email) ?>" required 
                                           placeholder="Nhập email hoặc tên đăng nhập">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mật khẩu</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" name="password" class="form-control border-start-0" 
                                           required placeholder="Nhập mật khẩu">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember">
                                    <label class="form-check-label text-muted" for="remember">Ghi nhớ đăng nhập</label>
                                </div>
                                <a href="#" class="text-decoration-none small" style="color: #667eea;">
                                    Quên mật khẩu?
                                </a>
                            </div>

                            <button type="submit" class="btn btn-primary btn-login w-100 text-white">
                                <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                            </button>
                        </form>

                        <p class="text-center mt-4 mb-0">
                            Chưa có tài khoản? 
                            <a href="register.php" class="fw-bold text-decoration-none" style="color: #667eea;">
                                Đăng ký ngay
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