<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
$database = new Database();
$conn = $database->conn; // đây là mysqli

// Chỉ admin mới được vào
if (!isset($_SESSION['user']) || $_SESSION['user']['vaitro'] != 1) {
    header("Location: /clothing_store/view/client/login.php");
    exit;
}

// Lấy danh sách banner từ bảng collab_banner
$sql = "SELECT * FROM collab_banner ORDER BY id DESC";
$result = $conn->query($sql);

$banners = [];
if ($result) {
    // fetch_all trả về mảng các bản ghi dạng assoc
    $banners = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản lý Banner Collab</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
body { display: flex; background: #f8f9fa; font-family: Arial, sans-serif; margin: 0; }
.sidebar { width: 220px; height: 100vh; background-color: #a7194b; color: white; padding: 25px 20px; display: flex; flex-direction: column; position: fixed; }
.sidebar h3 { font-size: 24px; font-weight: bold; margin-bottom: 40px; }
.sidebar a { color: white; text-decoration: none; padding: 10px 0; font-size: 15px; display: block; transition: 0.3s; }
.sidebar a:hover { color: #ffe0e9; transform: translateX(4px); }
.content { margin-left: 250px; padding: 30px; width: calc(100% - 250px); }

h2 { font-size: 26px; font-weight: bold; margin-bottom: 15px; }

.btn-add {
    display: inline-block;
    background-color: #8c123d;
    color: white;
    padding: 8px 15px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    transition: 0.3s;
}
.btn-add:hover { background-color: #ff6b1a; }

table {
    width: 100%;
    border-collapse: collapse;
    background-color: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    margin-top: 20px;
}
th, td { padding: 14px 18px; text-align: center; font-size: 14px; }
th { background-color: #a7194b; color: white; font-weight: 600; }
tr:nth-child(even) { background-color: #f8f8f8; }
tr:hover { background-color: #fde6ef; }

.btn-delete {
    background-color: #ff6b1a;
    color: white;
    padding: 6px 14px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: 0.3s;
}
.btn-delete:hover { background-color: #e85b0c; }

.no-data {
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.06);
}
</style>

</head>
<body>

<div class="sidebar">
    <h3>Quản trị</h3>
    <a href="/clothing_store/index.php"><i class="fa fa-home"></i> Trang chủ</a>
    <a href="/clothing_store/view/admin/orders.php"><i class="fa fa-shopping-cart"></i> Quản lý đơn hàng</a>
    <a href="/clothing_store/view/admin/users.php"><i class="fa fa-user"></i> Quản lý người dùng</a>
    <a href="/clothing_store/view/admin/products.php"><i class="fa fa-box"></i> Quản lý sản phẩm</a>
    <a href="/clothing_store/view/client/logout.php"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">

<h2>Quản lý Banner Collab</h2>
<a href="banner_add.php" class="btn-add">+ Thêm Banner</a>

<?php if (count($banners) === 0): ?>
    <div class="no-data" style="margin-top:20px;">
        Chưa có banner nào. Nhấn "Thêm Banner" để tạo mới.
    </div>
<?php else: ?>
<table>
<tr>
    <th>ID</th>
    <th>Tên Banner</th>
    <th>Ảnh</th>
    <th>Trạng thái</th>
    <th>Hành động</th>
</tr>

<?php foreach ($banners as $b): ?>
<tr>
    <td><?= htmlspecialchars($b['id']) ?></td>
    <td><?= htmlspecialchars(isset($b['name']) ? $b['name'] : $b['title'] ?? '') ?></td>
    <td>
        <?php
            // đường dẫn ảnh - sửa theo nơi bạn lưu ảnh
            $imgPath = '/clothing_store/public/images/banner/' . $b['image'];
        ?>
        <img src="<?= $imgPath ?>" width="180" alt="<?= htmlspecialchars($b['name'] ?? '') ?>">
    </td>
    <td><?= htmlspecialchars($b['status']) ?></td>
    <td>
        <a href="banner_delete.php?id=<?= $b['id'] ?>" class="btn-delete" onclick="return confirm('Bạn chắc muốn xóa banner này?')">Xóa</a>
    </td>
</tr>
<?php endforeach; ?>

</table>
<?php endif; ?>

</div>
</body>
</html>
