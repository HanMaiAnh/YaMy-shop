<?php
session_name('admin_session');
session_start();
require_once __DIR__ . '/../config/db.php'; // sử dụng $pdo của clothing_store

// Kiểm tra quyền admin (theo chuẩn các file admin khác)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Lấy id người dùng từ GET
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: users.php");
    exit;
}

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die("Người dùng không tồn tại");
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hoten   = trim($_POST['hoten'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phai    = isset($_POST['phai']) ? (int)$_POST['phai'] : 0;
    $vaitro  = isset($_POST['vaitro']) ? (int)$_POST['vaitro'] : 0;
    $active  = isset($_POST['active']) ? 1 : 0;
    $matkhau = $_POST['matkhau'] ?? '';

    // Validate nhẹ
    if ($hoten === '') {
        $errors[] = "Họ tên không được để trống.";
    }
    if ($email === '') {
        $errors[] = "Email không được để trống.";
    }

    if (empty($errors)) {
        if ($matkhau !== '') {
            // Đổi mật khẩu
            $hash = password_hash($matkhau, PASSWORD_DEFAULT);
            $sql = "UPDATE users 
                    SET hoten = :hoten,
                        email = :email,
                        phai = :phai,
                        vaitro = :vaitro,
                        active = :active,
                        matkhau = :matkhau
                    WHERE id = :id";
            $params = [
                ':hoten'   => $hoten,
                ':email'   => $email,
                ':phai'    => $phai,
                ':vaitro'  => $vaitro,
                ':active'  => $active,
                ':matkhau' => $hash,
                ':id'      => $id
            ];
        } else {
            // Không đổi mật khẩu
            $sql = "UPDATE users 
                    SET hoten = :hoten,
                        email = :email,
                        phai = :phai,
                        vaitro = :vaitro,
                        active = :active
                    WHERE id = :id";
            $params = [
                ':hoten'  => $hoten,
                ':email'  => $email,
                ':phai'   => $phai,
                ':vaitro' => $vaitro,
                ':active' => $active,
                ':id'     => $id
            ];
        }

        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);

        header("Location: users.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý người dùng</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { display: flex; background: #f8f9fa; }

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

        .content { margin-left: 270px; padding: 20px; width: 100%; }

        /* Container form */
        form {
            width: 100%;
            max-width: 650px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        /* Tiêu đề */
        h2 {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 15px;
            color: #333;
            font-weight: 600;
        }

        /* Input & Select */
        .form-control,
        .form-select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 18px;
            font-size: 15px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #007bff;
            outline: none;
        }

        /* Checkbox */
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-check-input {
            margin-right: 8px;
        }

        /* Nút Lưu */
        .btn-success {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 12px 18px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .btn-success:hover {
            background: #218838;
        }

        /* Thông báo lỗi */
        .alert-danger {
            max-width: 650px;
            margin: 15px auto;
            padding: 12px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <h3>Quản trị</h3>
    <a href="dashboard.php"><i class="fa fa-gauge"></i> Trang Quản Trị</a>
    <a href="orders.php"><i class="fa fa-shopping-cart"></i> Quản lý đơn hàng</a>
    <a href="users.php"><i class="fa fa-user"></i> Quản lý người dùng</a>
    <a href="products.php"><i class="fa fa-box"></i> Quản lý sản phẩm</a>
    <a href="categories.php"><i class="fa fa-list"></i> Quản lý danh mục</a>
    <a href="sizes_colors.php"><i class="fa fa-ruler-combined"></i> Size & Màu</a>
    <a href="vouchers.php"><i class="fa-solid fa-tags"></i> Quản lý vouchers</a>
    <a href="news.php"><i class="fa fa-newspaper"></i> Quản lý tin tức</a>
    <a href="comments.php"><i class="fa fa-comment"></i> Quản lý bình luận</a>
    <a href="logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">
    <h2>Sửa người dùng</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert-danger">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <h2>Sửa thông tin</h2>

        <input name="hoten"
               value="<?= htmlspecialchars($user['hoten'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               class="form-control" placeholder="Họ tên">

        <input name="email" type="email"
               value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               class="form-control" placeholder="Email">

        <select name="phai" class="form-select">
            <option value="0" <?= (int)$user['phai'] === 0 ? 'selected' : '' ?>>Nữ</option>
            <option value="1" <?= (int)$user['phai'] === 1 ? 'selected' : '' ?>>Nam</option>
        </select>

        <select name="vaitro" class="form-select">
            <option value="0" <?= (int)$user['vaitro'] === 0 ? 'selected' : '' ?>>Người dùng</option>
            <option value="1" <?= (int)$user['vaitro'] === 1 ? 'selected' : '' ?>>Admin</option>
        </select>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="active"
                <?= !empty($user['active']) ? 'checked' : '' ?>>
            <label>Hoạt động</label>
        </div>

        <input type="password" name="matkhau"
               class="form-control" placeholder="Mật khẩu mới (bỏ trống nếu không đổi)">

        <button class="btn-success">Cập nhật</button>
    </form>
</div>
</body>
</html>
