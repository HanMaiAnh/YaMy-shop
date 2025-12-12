<?php
session_name('admin_session');
session_start();
require_once dirname(__DIR__) . '/config/db.php';

$error = '';

// Nếu đã đăng nhập admin rồi thì không cho vào trang login nữa
if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            if ($admin['role'] !== 'admin') {
                $error = "Bạn không có quyền truy cập trang quản trị!";
            } elseif ((int)$admin['active'] === 0) {
                $error = "Tài khoản admin đã bị khóa!";
            } else {
                $_SESSION['user_id']  = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['role']     = $admin['role']; // 'admin'

                header("Location: dashboard.php");
                exit;
            }
        } else {
            $error = "Sai tài khoản hoặc mật khẩu!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập quản trị - YaMy Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

    <style>
        *{box-sizing:border-box;font-family:'Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif;}
        body{
            min-height:100vh;
            margin:0;
            display:flex;
            align-items:center;
            justify-content:center;
            background:radial-gradient(circle at top,#273c75,#192a56 55%,#000 100%);
            color:#fff;
        }
        .login-wrapper{
            width:100%;
            max-width:420px;
            padding:15px;
        }
        .login-card{
            background:#0f172a;
            border-radius:18px;
            padding:28px 26px 26px;
            box-shadow:0 18px 45px rgba(0,0,0,.6);
            border:1px solid rgba(148,163,184,.35);
        }
        .login-logo{
            width:60px;height:60px;border-radius:16px;
            display:flex;align-items:center;justify-content:center;
            background:rgba(15,23,42,.9);
            box-shadow:0 0 0 1px rgba(148,163,184,.4),0 10px 25px rgba(15,23,42,.9);
            margin:0 auto 14px;
        }
        .login-title{
            text-align:center;
            font-size:22px;
            font-weight:700;
            margin-bottom:4px;
        }
        .login-sub{
            text-align:center;
            font-size:13px;
            color:#94a3b8;
            margin-bottom:18px;
        }
        .form-label{
            font-size:13px;
            font-weight:600;
            color:#e5e7eb;
        }
        .input-group-text{
            background:#020617;
            border-radius:12px 0 0 12px;
            border:1px solid #1f2937;
            border-right:0;
        }
        .form-control{
            border-radius:0 12px 12px 0;
            border:1px solid #1f2937;
            border-left:0;
            background:#020617;
            color:#e5e7eb;
            font-size:14px;
            padding:0.65rem .85rem;
        }
        .form-control:focus{
            border-color:#6366f1;
            box-shadow:0 0 0 1px rgba(99,102,241,.6);
            background:#020617;
            color:#e5e7eb;
        }
        .btn-login{
            margin-top:10px;
            border:none;
            width:100%;
            padding:.7rem 1rem;
            border-radius:999px;
            background:linear-gradient(135deg,#6366f1,#ec4899);
            color:#fff;
            font-weight:600;
            font-size:15px;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            box-shadow:0 10px 30px rgba(79,70,229,.55);
            transition:.25s;
        }
        .btn-login:hover{
            transform:translateY(-1px);
            box-shadow:0 14px 40px rgba(79,70,229,.7);
        }
        .small-link{
            color:#64748b;
            font-size:12px;
            text-decoration:none;
        }
        .small-link:hover{
            color:#e5e7eb;
            text-decoration:underline;
        }
        .alert{
            font-size:13px;
            border-radius:10px;
            padding:.55rem .75rem;
        }
        .top-row{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:6px;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <i class="fa-solid fa-shield-halved fa-lg text-primary"></i>
        </div>
        <div class="login-title">YaMy Admin</div>
        <div class="login-sub">Đăng nhập trang quản trị hệ thống</div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 mb-3">
                <i class="fa-solid fa-circle-exclamation me-1"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Tên đăng nhập (Admin)</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fa-solid fa-user-gear text-slate-300"></i>
                    </span>
                    <input type="text"
                           name="username"
                           class="form-control"
                           placeholder="Nhập username admin"
                           required>
                </div>
            </div>

            <div class="mb-2">
                <div class="top-row">
                    <label class="form-label mb-0">Mật khẩu</label>
                </div>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fa-solid fa-lock text-slate-300"></i>
                    </span>
                    <input type="password"
                           name="password"
                           class="form-control"
                           placeholder="Nhập mật khẩu"
                           required>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="remember_admin">
                    <label class="form-check-label small text-secondary" for="remember_admin">
                        Ghi nhớ phiên làm việc
                    </label>
                </div>
                <a href="../view/login.php" class="small-link">
                    ← Về trang đăng nhập khách
                </a>
            </div>

            <button type="submit" class="btn-login">
                <i class="fa-solid fa-right-to-bracket"></i>
                Đăng nhập quản trị
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
