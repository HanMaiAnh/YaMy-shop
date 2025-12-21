<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['vaitro'] != 1) {
    header("Location: /clothing_store/view/client/login.php");
    exit;
}

require_once __DIR__ . '/../../config/db.php';
$db = new Database();
$conn = $db->conn;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $description = $_POST['description'];
    $stock_hanoi = $_POST['stock_hanoi'];
    $stock_hcm = $_POST['stock_hcm'];
    $stock_danang = $_POST['stock_danang'];
    $stock_cantho = $_POST['stock_cantho'];

    $imageName = $_FILES['image']['name'];
    $imageTmp  = $_FILES['image']['tmp_name'];
    $imageError = $_FILES['image']['error'];

    $uploadDir = __DIR__ . '/../../public/images/';
    $imagePath = '';

    if ($imageError === 0) {
        $imagePath = uniqid() . '_' . basename($imageName);
        $destination = $uploadDir . $imagePath;

        if (move_uploaded_file($imageTmp, $destination)) {
            $stmt = $conn->prepare("INSERT INTO products (name, price, image, description, stock_hanoi, stock_hcm, stock_danang, stock_cantho) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sdssssss", $name, $price, $imagePath, $description, $stock_hanoi, $stock_hcm, $stock_danang, $stock_cantho);
            $stmt->execute();
            $stmt->close();

            header("Location: products.php");
            exit;
        } else {
            $error = "Không thể lưu ảnh lên máy chủ.";
        }
    } else {
        $error = "Lỗi khi chọn ảnh.";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Thêm sản phẩm - YaMy Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{display:flex;background:#111;color:#eee;}

/* Sidebar */
.sidebar{width:260px;height:100vh;background:#0d0d0d;padding:30px 20px;position:fixed;border-right:1px solid #222;}
.sidebar h3{color:#fff;margin-bottom:25px;font-weight:700;font-size:20px;text-transform:uppercase;letter-spacing:2px;}
.sidebar a{display:flex;align-items:center;gap:10px;color:#ccc;padding:12px 14px;text-decoration:none;border-radius:8px;font-weight:500;transition:.25s;font-size:15px;}
.sidebar a:hover{color:#fff;background:#1a1a1a;transform:translateX(5px);}
.sidebar a.logout{color:#ff4d4d;margin-top:40px;}

/* Content */
.content{margin-left:280px;padding:40px;width:100%;min-height:100vh;}
.form-card{background:#141414;border:1px solid #1f1f1f;border-radius:14px;padding:40px;max-width:700px;margin:auto;box-shadow:0 4px 15px rgba(0,0,0,.4);}
h2{text-align:center;color:#e91e63;margin-bottom:25px;font-size:24px;font-weight:700;}

label{display:block;margin-top:18px;font-weight:600;color:#ddd;}
input[type="text"],input[type="number"],textarea,select,input[type="file"]{
  width:100%;margin-top:6px;padding:12px 14px;border:1px solid #333;border-radius:8px;
  background:#1b1b1b;color:#eee;font-size:15px;transition:border-color .3s;
}
input:focus,textarea:focus,select:focus{border-color:#e91e63;outline:none;}

fieldset{margin-top:20px;padding:20px;border:1px solid #333;border-radius:10px;background:#1a1a1a;}
legend{color:#8e5df5;font-weight:600;}

button{margin-top:30px;width:100%;padding:14px;border:none;border-radius:8px;
background:#8e5df5;color:#fff;font-weight:700;font-size:16px;cursor:pointer;transition:.3s;}
button:hover{background:#7c47e0;}

.back-link{text-align:center;margin-top:20px;}
.back-link a{color:#8e5df5;text-decoration:none;font-weight:600;}
.back-link a:hover{text-decoration:underline;}

.error{color:#ff6b6b;text-align:center;margin-bottom:10px;}
</style>
</head>
<body>
<div class="sidebar">
    <h3>YaMy Admin</h3>
    <a href="/streetsoul_store1/index.php"><i class="fa fa-home"></i> Trang chủ</a>
    <a href="/streetsoul_store1/view/admin/dashboard.php"><i class="fa fa-gauge"></i> Trang Quản Trị</a>
    <a href="/streetsoul_store1/view/admin/orders.php"><i class="fa fa-shopping-cart"></i> Quản lý đơn hàng</a>
    <a href="/streetsoul_store1/view/admin/users.php"><i class="fa fa-user"></i> Quản lý người dùng</a>
    <a href="/streetsoul_store1/view/admin/products.php"><i class="fa fa-box"></i> Quản lý sản phẩm</a>
    <a href="/streetsoul_store1/view/client/logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">
  <div class="form-card">
    <h2>Thêm sản phẩm mới</h2>
    <?php if (!empty($error)): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <label>Ảnh sản phẩm:</label>
      <input type="file" name="image" accept="image/*" required>

      <label>Tên sản phẩm:</label>
      <input type="text" name="name" placeholder="Ví dụ: Áo Thun Logo YaMy" required>

      <label>Giá (VND):</label>
      <input type="number" name="price" placeholder="Ví dụ: 350000" required>

      <fieldset>
        <legend>Tình trạng hàng tồn theo khu vực</legend>
        <label>Hà Nội:</label>
        <select name="stock_hanoi">
          <option value="con">Còn hàng</option><option value="het">Hết hàng</option>
        </select>
        <label>TP. Hồ Chí Minh:</label>
        <select name="stock_hcm">
          <option value="con">Còn hàng</option><option value="het">Hết hàng</option>
        </select>
        <label>Đà Nẵng:</label>
        <select name="stock_danang">
          <option value="con">Còn hàng</option><option value="het">Hết hàng</option>
        </select>
        <label>Cần Thơ:</label>
        <select name="stock_cantho">
          <option value="con">Còn hàng</option><option value="het">Hết hàng</option>
        </select>
      </fieldset>

      <label>Mô tả sản phẩm:</label>
      <textarea name="description" rows="4" placeholder="Nhập mô tả chi tiết" required></textarea>

      <button type="submit">Thêm sản phẩm</button>
    </form>

    <div class="back-link">
      <a href="products.php">← Quay lại danh sách sản phẩm</a>
    </div>
  </div>
</div>
</body>
</html>
