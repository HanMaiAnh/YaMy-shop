<?php
session_name('admin_session');
session_start();
require_once __DIR__ . '/../config/db.php'; // db.php tạo ra $pdo

// --- Kiểm tra quyền admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$errors = [];

// Giá trị mặc định để giữ lại form khi lỗi
$username = '';
$fullname = '';
$phone    = '';
$email    = '';
$sex      = 'male';    // mặc định Nam
$role     = 'user';    // mặc định người dùng
$active   = 1;         // mặc định hoạt động
$address  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $fullname  = trim($_POST['fullname'] ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $sex       = $_POST['sex'] ?? 'male'; // 'male' / 'female'
    $role      = $_POST['role'] ?? 'user';   // 'admin' / 'user'
    $active    = isset($_POST['active']) ? 1 : 0;
    $address   = trim($_POST['address'] ?? '');

    // --- Validate cơ bản ---
    if ($username === '')  $errors[] = "Vui lòng nhập tên đăng nhập.";
    if ($email === '')     $errors[] = "Vui lòng nhập email.";
    if ($password === '')  $errors[] = "Vui lòng nhập mật khẩu.";
    if ($password !== $password2) {
        $errors[] = "Mật khẩu xác nhận không khớp.";
    }

    // --- Kiểm tra trùng username ---
    if ($username !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Tên đăng nhập đã tồn tại.";
        }
    }

    // --- Kiểm tra trùng email ---
    if ($email !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email đã tồn tại.";
        }
    }

    // --- Kiểm tra trùng fullname ---
    if ($fullname !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE fullname = ?");
        $stmt->execute([$fullname]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Họ và tên đã được sử dụng.";
        }
    }

    // --- Kiểm tra trùng số điện thoại ---
    if ($phone !== '') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Số điện thoại đã tồn tại.";
        }
    }

    // --- Nếu không có lỗi thì thêm user + địa chỉ ---
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);

            // 1. Thêm user (lưu luôn address vào bảng users vì có cột address)
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, email, fullname, phone, address, sex, role, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $username,
                $hash,
                $email,
                $fullname,
                $phone,
                $address,
                $sex,   // 'male' / 'female'
                $role,  // 'admin' / 'user'
                $active
            ]);

            $userId = $pdo->lastInsertId();

            // 2. Nếu có nhập địa chỉ -> thêm vào user_addresses, đánh dấu is_default = 1
            if ($address !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO user_addresses (user_id, fullname, phone, address, is_default)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmt->execute([$userId, $fullname, $phone, $address]);
            }

            $pdo->commit();
            header("Location: users.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Có lỗi khi lưu dữ liệu: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm người dùng</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }
        body {
            display: flex;
            background: #f9f9f9;
            color: #333;
        }

        /* SIDEBAR */
.sidebar{
    width:260px;
    background:#fff;
    height:100vh;
    padding:30px 20px;
    position:fixed;
    border-right:1px solid #ddd;
}
.sidebar h3{
    font-size:22px;
    font-weight:700;
    margin-bottom:25px;
}
.sidebar a{
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px;
    color:#333;
    text-decoration:none;
    border-radius:8px;
    margin-bottom:8px;
    transition:.25s;
    font-weight:500;
    font-size:15px;
}
.sidebar a:hover{
    background:#f2e8ff;
    color:#8E5DF5;
    transform:translateX(4px);
}
.sidebar .logout{
    color:#e53935;
    margin-top:20px;
}

        /* Content */
        .content {
            margin-left: 280px;
            padding: 40px;
            width: calc(100% - 280px);
            min-height: 100vh;
        }
        h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        p.subtitle {
            color: #aaa;
            margin-bottom: 20px;
        }

        /* Form */
        form {
            background: #fff;
            padding: 40px;
            border-radius: 14px;
            border: 1px solid #e5e5e5;
            max-width: 700px;
            margin: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        h2 {
            text-align: center;
            color: #8E5DF5;
            margin-bottom: 25px;
            font-size: 24px;
            font-weight: 700;
        }
        .form-control,
        .form-select,
        textarea.form-control {
            width: 100%;
            background: #fff;
            color: #333;
            border: 1px solid #dcdcdc;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 15px;
            margin-bottom: 16px;
            transition: border-color 0.3s;
        }
        .form-control:focus,
        .form-select:focus,
        textarea.form-control:focus {
            border-color: #8E5DF5;
            outline: none;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .form-check-input {
            accent-color: #8E5DF5;
        }

        /* 2 ô Sex + Role nằm cùng hàng */
        .row-2col {
            display: flex;
            gap: 20px;
            margin-bottom: 16px;
        }
        .row-2col .form-select {
            flex: 1;
        }

        /* Button */
        .btn-success {
            background: #8E5DF5;
            color: #fff;
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-success:hover {
            background: #a57bff;
        }

        /* Error message */
        .form-error {
            background: #ffe8e8;
            color: #d40000;
            border: 1px solid #ffb3b3;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .form-error p {
            margin-bottom: 4px;
        }

        /* Link quay lại – đồng bộ với news_add, news_edit */
        .back-link {
            margin-top: 14px;
            font-size: 14px;
        }
        .back-link a {
            color: #8E5DF5;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
            color: #E91E63;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .content {
                margin-left: 0;
                padding: 20px;
                width: 100%;
            }
            form {
                padding: 25px;
            }
        }

        /* Mobile: 2 ô sex/role xếp dọc lại */
        @media (max-width: 600px) {
            .row-2col {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h3>YAMY ADMIN</h3>
    <a href="dashboard.php"><i class="fa fa-gauge"></i> Trang Quản Trị</a>
    <a href="orders.php"><i class="fa fa-shopping-cart"></i> Quản lý đơn hàng</a>
    <a href="users.php"><i class="fa fa-user"></i> Quản lý người dùng</a>
    <a href="products.php"><i class="fa fa-box"></i> Quản lý sản phẩm</a>
    <a href="categories.php"><i class="fa fa-list"></i> Quản lý danh mục</a>
    <a href="sizes_colors.php"><i class="fa fa-ruler-combined"></i> Size & Màu</a>
    <a href="vouchers.php"><i class="fa-solid fa-tags"></i> Quản lý vouchers</a>
    <a href="news.php"><i class="fa fa-newspaper"></i> Quản lý tin tức</a>
    <a href="reviews.php"><i class="fa fa-comment"></i> Quản lý bình luận</a>
    <a href="logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">
    <h1>Thêm người dùng mới</h1>
    <p class="subtitle">Điền thông tin bên dưới để tạo tài khoản người dùng mới.</p>

    <form method="post">
        <h2>Thông tin tài khoản</h2>

        <?php if (!empty($errors)): ?>
            <div class="form-error">
                <?php foreach ($errors as $e): ?>
                    <p><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <input
            name="username"
            class="form-control"
            placeholder="Tên đăng nhập"
            required
            value="<?= htmlspecialchars($username) ?>"
        >
        <input
            name="fullname"
            class="form-control"
            placeholder="Họ và tên"
            required
            value="<?= htmlspecialchars($fullname) ?>"
        >
        <input
            type="password"
            name="password"
            class="form-control"
            placeholder="Mật khẩu"
            required
        >

        <input
            type="password"
            name="password2"
            class="form-control"
            placeholder="Xác nhận mật khẩu"
            required
        >
        <input
            name="phone"
            class="form-control"
            placeholder="Số điện thoại"
            required
            value="<?= htmlspecialchars($phone) ?>"
        >

        <input
            name="email"
            type="email"
            class="form-control"
            placeholder="Email"
            value="<?= htmlspecialchars($email) ?>"
        >

        <textarea
            name="address"
            class="form-control"
            placeholder="Địa chỉ (số nhà, đường, quận...)"
            rows="3"
        ><?= htmlspecialchars($address) ?></textarea>

        <!-- Sex + Role cùng 1 hàng -->
        <div class="row-2col">
            <select name="sex" class="form-select">
                <option value="male"   <?= $sex === 'male'   ? 'selected' : '' ?>>Nam</option>
                <option value="female" <?= $sex === 'female' ? 'selected' : '' ?>>Nữ</option>
            </select>

            <select name="role" class="form-select">
                <option value="user"  <?= $role === 'user'  ? 'selected' : '' ?>>Người dùng</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>

        <div class="form-check">
            <input
                class="form-check-input"
                type="checkbox"
                name="active"
                id="active"
                <?= $active ? 'checked' : '' ?>
            >
            <label for="active">Hoạt động</label>
        </div>

        <button class="btn-success">Lưu người dùng</button>
        <div class="back-link">
            <a href="users.php">← Quay lại danh sách người dùng</a>
        </div>
    </form>

</div>

</body>
</html>
