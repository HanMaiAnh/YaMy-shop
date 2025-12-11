<?php
session_name('admin_session');
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

// Lấy danh sách danh mục để chọn làm danh mục cha
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")
                      ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Lỗi khi lấy dữ liệu: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $parent_id  = $_POST['parent_id'] ?? '';
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    // xử lý parent_id rỗng
    if ($parent_id === '' || $parent_id == 0) {
        $parent_id = null;
    } else {
        $parent_id = (int)$parent_id;
    }

    if ($name === '') {
        $error = 'Tên danh mục không được để trống.';
    }

    // kiểm tra trùng tên
    if ($error === '') {
        $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = :name");
        $check->execute([':name' => $name]);
        if ($check->fetchColumn() > 0) {
            $error = 'Tên danh mục đã tồn tại, vui lòng chọn tên khác.';
        }
    }

    if ($error === '') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO categories (name, parent_id, sort_order)
                VALUES (:name, :parent_id, :sort_order)
            ");

            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':sort_order', $sort_order, PDO::PARAM_INT);
            if ($parent_id === null) {
                $stmt->bindValue(':parent_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
            }

            if ($stmt->execute()) {
                header("Location: categories.php");
                exit;
            } else {
                $error = 'Có lỗi xảy ra khi thêm danh mục.';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi khi lưu danh mục: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<title>Thêm danh mục - YaMy Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{display:flex;background:#f5f6fa;color:#111;}
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
.content{margin-left:280px;padding:40px;width:100%;}
.page-title{font-size:26px;font-weight:700;margin-bottom:25px;text-align:center;}
.form-card{background:#fff;border-radius:16px;padding:30px;max-width:700px;margin:0 auto;box-shadow:0 4px 14px rgba(0,0,0,0.08);}
label{display:block;margin-top:12px;font-weight:600;color:#444;}
input[type="text"],input[type="number"],select{
  width:100%;margin-top:8px;padding:12px 14px;border:1px solid #ddd;border-radius:10px;background:#fff;font-size:14px;transition:.18s;
}
input:focus,select:focus{border-color:#8E5DF5;outline:none;}
.meta-row{display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-top:6px;}
.meta-item{flex:1 1 200px;min-width:160px;}
.button-submit-main {
    width:100%;
    padding:14px 0;
    background:#8E5DF5;
    color:#fff;
    border:none;
    border-radius:10px;
    font-weight:700;
    font-size:16px;
    cursor:pointer;
    transition: background .18s ease;
    margin-top:18px;
}
.button-submit-main:hover{background:#E91E63;}

/* responsive tweaks */
@media (max-width:600px){
  .meta-item { flex:1 1 100%; }
}
.back-link{text-align:center;margin-top:18px;}
.back-link a{color:#8E5DF5;text-decoration:none;font-weight:600;}
.back-link a:hover{text-decoration:underline;}
.error{color:#ff4d4d;margin-bottom:10px;font-weight:600;}
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
    <a href="comments.php"><i class="fa fa-comment"></i> Quản lý bình luận</a>
    <a href="logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">
  <h1 class="page-title">Thêm danh mục mới</h1>

  <div class="form-card">
    <?php if (!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="name">Tên danh mục:</label>
      <input type="text" id="name" name="name" placeholder="Ví dụ: T-SHIRTS & POLO SHIRTS" required>

      <div class="meta-row">
        <div class="meta-item">
          <label for="parent_id">Danh mục cha:</label>
          <select name="parent_id" id="parent_id">
            <option value="">-- Không có (Danh mục gốc) --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>">
                <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="meta-item">
          <label for="sort_order">Thứ tự sắp xếp:</label>
          <input type="number" id="sort_order" name="sort_order" min="0" value="0">
        </div>
      </div>

      <button type="submit" class="button-submit-main">Thêm danh mục</button>
    </form>

    <div class="back-link">
      <a href="categories.php">← Quay lại danh sách danh mục</a>
    </div>
  </div>
</div>
</body>
</html>
