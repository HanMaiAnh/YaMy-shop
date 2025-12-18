<?php
session_name('admin_session');
session_start();

// --- Kết nối DB PDO ---
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

// --- Kiểm tra quyền admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Lấy ID danh mục cần sửa
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: categories.php");
    exit;
}

// Lấy thông tin danh mục hiện tại
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
$stmt->execute([':id' => $id]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    header("Location: categories.php");
    exit;
}

$error   = '';
$success = '';

// Xử lý submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $parent_id  = $_POST['parent_id'] ?? '';
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    // Chuyển parent_id rỗng thành NULL
    if ($parent_id === '' || $parent_id == 0) {
        $parent_id = null;
    } else {
        $parent_id = (int)$parent_id;
        // Không cho tự làm cha của chính nó
        if ($parent_id === $id) {
            $error = 'Danh mục không thể là danh mục cha của chính nó.';
        }
    }

    if ($name === '') {
        $error = 'Tên danh mục không được để trống.';
    }

    // Kiểm tra trùng tên (trừ chính nó)
    if ($error === '') {
        $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = :name AND id <> :id");
        $check->execute([
            ':name' => $name,
            ':id'   => $id
        ]);
        if ($check->fetchColumn() > 0) {
            $error = 'Tên danh mục đã tồn tại, vui lòng chọn tên khác.';
        }
    }

    if ($error === '') {
        // Cập nhật
        $sql = "UPDATE categories 
                SET name = :name, parent_id = :parent_id, sort_order = :sort_order 
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':sort_order', $sort_order, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        if ($parent_id === null) {
            $stmt->bindValue(':parent_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
        }

        if ($stmt->execute()) {
            $success  = 'Cập nhật danh mục thành công.';
            // Cập nhật lại biến $category để hiển thị giá trị mới
            $category['name']       = $name;
            $category['parent_id']  = $parent_id;
            $category['sort_order'] = $sort_order;
        } else {
            $error = 'Có lỗi xảy ra khi cập nhật danh mục.';
        }
    }
}

// Lấy danh sách danh mục để chọn làm danh mục cha (trừ chính nó)
$parentStmt = $pdo->prepare("SELECT id, name FROM categories WHERE id <> :id ORDER BY name ASC");
$parentStmt->execute([':id' => $id]);
$allParents = $parentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Sửa danh mục</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat',sans-serif;}
body {display:flex; background:#f5f6fa; color:#111;}

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

/* CONTENT */
.content {margin-left:260px;min-height:100vh;width:calc(100% - 260px);display:flex;flex-direction:column;align-items:center;padding:40px 20px;}
.page-title {font-size:28px;font-weight:700;margin-bottom:25px;text-align:center;}

/* CARD GIỮA MÀN HÌNH */
.form-wrapper {width:100%;max-width:900px;}
.form-card {background:#fff;border-radius:24px;padding:35px 40px;box-shadow:0 12px 30px rgba(15,23,42,0.04);}
.form-group {margin-bottom:20px;}
.form-label {display:block;font-weight:600;margin-bottom:8px;font-size:15px;}
.form-input,
.form-select {
    width:100%;
    padding:11px 12px;
    border-radius:12px;
    border:1px solid #ddd;
    font-size:14px;
    outline:none;
    transition:border-color .2s, box-shadow .2s;
}
.form-input:focus,
.form-select:focus {
    border-color:#8E5DF5;
    box-shadow:0 0 0 2px rgba(142,93,245,0.2);
}
.form-hint {font-size:12px;color:#888;margin-top:4px;}

.btn-primary {
    width:100%;
    border:none;
    padding:13px 16px;
    border-radius:999px;
    background:#8E5DF5;
    color:#fff;
    font-weight:700;
    font-size:15px;
    cursor:pointer;
    margin-top:10px;
}
.btn-primary:hover {background:#E91E63;}

.back-link {
    display:block;
    text-align:center;
    margin-top:14px;
    font-size:14px;
    text-decoration:none;
    color:#8E5DF5;
}
.back-link:hover {text-decoration:underline;}

.alert {padding:10px 14px;border-radius:10px;margin-bottom:15px;font-size:14px;}
.alert-error {background:#ffebee;color:#c62828;border:1px solid #ffcdd2;}
.alert-success {background:#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9;}

@media(max-width: 768px){
    .content {margin-left:0;padding:20px;}
    .sidebar {display:none;}
    .form-card {padding:20px 18px;border-radius:18px;}
}
</style>
</head>
<body>
<div class="sidebar">
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
    <h1 class="page-title">Sửa danh mục</h1>

    <div class="form-wrapper">
        <div class="form-card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label class="form-label" for="name">Tên danh mục:</label>
                    <input type="text" id="name" name="name" class="form-input"
                           value="<?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="parent_id">Danh mục cha:</label>
                    <select name="parent_id" id="parent_id" class="form-select">
                        <option value="">Không có (Danh mục gốc)</option>
                        <?php foreach ($allParents as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"
                                <?= ($category['parent_id'] == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint">Nếu không chọn, danh mục này sẽ là danh mục gốc.</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sort_order">Thứ tự sắp xếp:</label>
                    <input type="number" id="sort_order" name="sort_order" min="0"
                           class="form-input"
                           value="<?= (int)$category['sort_order'] ?>">
                    <p class="form-hint">Số nhỏ sẽ hiển thị trước. Có thể để 0 nếu không cần.</p>
                </div>

                <button type="submit" class="btn-primary">Cập nhật danh mục</button>
            </form>

            <a href="categories.php" class="back-link">← Quay lại danh sách danh mục</a>
        </div>
    </div>
</div>
</body>
</html>
