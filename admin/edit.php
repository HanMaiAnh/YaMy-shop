<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['vaitro'] !== 1) {
    header("Location: /streetsoul_store1/view/client/login.php");
    exit;
}

require_once __DIR__ . '/../../config/db.php';
$db = new Database();
$conn = $db->conn;

$id = $_GET['id'];
$result = $conn->query("SELECT * FROM products WHERE id=$id");
$product = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'];
    $price = $_POST['price'];
    $imagePath = $product['image'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $imageName = $_FILES['image']['name'];
        $imageTmp  = $_FILES['image']['tmp_name'];
        $targetDir = __DIR__ . '/../../public/images/';
        $newImagePath = uniqid() . '_' . basename($imageName);

        if (move_uploaded_file($imageTmp, $targetDir . $newImagePath)) {
            $imagePath = $newImagePath;

            if (!empty($product['image']) && file_exists($targetDir . $product['image'])) {
                unlink($targetDir . $product['image']);
            }
        } else {
            echo "<p style='color:red; text-align:center;'>Không thể tải ảnh mới.</p>";
        }
    }

    $stmt = $conn->prepare("UPDATE products SET name = ?, price = ?, image = ? WHERE id = ?");
    $stmt->bind_param("sdsi", $name, $price, $imagePath, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: products.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa sản phẩm</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { display: flex; background: #f8f9fa; }

        .sidebar { width: 250px; height: 100vh; background: #8c123d; color: white; padding: 20px; position: fixed; }
        .sidebar a { display: block; color: white; padding: 10px; text-decoration: none; margin: 10px 0; transition: 0.3s; }
        .sidebar a:hover { background: #ffe0e9; }

        .content { margin-left: 270px; padding: 20px; width: 100%; }

        .dashboard-card {
            background: white; padding: 20px; border-radius: 10px;
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 20px;
            max-width: 600px; margin-left: auto; margin-right: auto;
        }

        h2 { text-align: center; color: #333; margin-bottom: 20px; }

        form label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }

        input[type="text"], input[type="number"], input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            box-sizing: border-box;
        }

        button {
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #8c123d;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            display: block;
            width: 100%;
        }

        button:hover { background-color: #8c123d; }

        img {
            max-width: 150px;
            margin-top: 10px;
            border-radius: 4px;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            text-decoration: none;
            color: #007bff;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
        .dashboard-card form textarea {
  width: 100%;
  box-sizing: border-box;
}

.dashboard-card form button {
  display: block;
  margin-top: 10px;
  width: 100%;
  padding: 10px;
  font-size: 16px;
}
.content {
  display: flex;
  justify-content: center;
  padding: 20px;
}

.dashboard-card {
  background: #fff;
  border-radius: 10px;
  padding: 30px;
  max-width: 500px;
  width: 100%;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}



.dashboard-card form label {
  display: block;
  margin-top: 15px;
  font-weight: bold;
  font-size: 14px;
}

.dashboard-card form input[type="text"],
.dashboard-card form input[type="number"],
.dashboard-card form input[type="file"],
.dashboard-card form select,
.dashboard-card form textarea {
  width: 100%;
  margin-top: 5px;
  padding: 10px;
  font-size: 14px;
  border: 1px solid #ddd;
  border-radius: 5px;
  box-sizing: border-box;
}

.dashboard-card form textarea {
  resize: vertical;
}

.dashboard-card form fieldset {
  border: 1px solid #ddd;
  border-radius: 5px;
  padding: 15px;
  margin-top: 15px;
}

.dashboard-card form legend {
  font-weight: bold;
  padding: 0 5px;
  font-size: 14px;
}

.dashboard-card form button {
  display: block;
  margin-top: 20px;
  width: 100%;
  padding: 12px;
  font-size: 16px;
  background-color: #8c123d;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}

.dashboard-card form button:hover {
  background-color: #ff6b1a;
}

.back-link {
  margin-top: 15px;
  text-align: center;
}

.back-link a {
  text-decoration: none;
  color: #8c123d;
}

.back-link a:hover {
  text-decoration: underline;
}

    </style>
</head>
<body>
    <div class="sidebar">
        <h3>Quản trị</h3>
        <a href="/streetsoul_store1/index.php"><i class="fa fa-home"></i> Trang chủ</a>
        <a href="/streetsoul_store1/view/admin/orders.php"><i class="fa fa-shopping-cart"></i> Quản lý đơn hàng</a>
        <a href="/streetsoul_store1/view/admin/users.php"><i class="fa fa-user"></i> Quản lý người dùng</a>
        <a href="/streetsoul_store1/view/admin/products.php"><i class="fa fa-box"></i> Quản lý sản phẩm</a>
        <a href="/streetsoul_store1/view/client/logout.php"><i class="fa fa-sign-out"></i> Đăng xuất</a>
    </div>

    <div class="content">
    <div class="dashboard-card">
        <h2>Sửa sản phẩm</h2>
        <form method="post" enctype="multipart/form-data">
            <!-- 1. Ảnh sản phẩm -->
            <label for="image">Ảnh sản phẩm:</label>
            <input type="file" name="image" accept="image/*">
            <?php if (!empty($product['image'])): ?>
                    <img src="/streetsoul_store1/public/images/<?php echo htmlspecialchars($product['image']); ?>" alt="Ảnh sản phẩm">
                <?php endif; ?>
            <!-- 2. Tên sản phẩm -->
            <label for="name">Tên sản phẩm:</label>
            <input type="text" name="name" value="Áo Thun Mẫu Chữ - Trắng" required>

            <!-- 3. Giá -->
            <label for="price">Giá (VND):</label>
            <input type="number" name="price" value="400000" required>

            <!-- 4. Khu vực giao hàng -->
            <fieldset>
                <legend>Khu vực giao hàng:</legend>
                
                <label>Hà Nội:</label>
                <select name="stock_hanoi" required>
                    <option value="con" selected>Còn hàng</option>
                    <option value="het">Hết hàng</option>
                </select>

                <label>TP. Hồ Chí Minh:</label>
                <select name="stock_hcm" required>
                    <option value="con">Còn hàng</option>
                    <option value="het" selected>Hết hàng</option>
                </select>

                <label>Đà Nẵng:</label>
                <select name="stock_danang" required>
                    <option value="con">Còn hàng</option>
                    <option value="het" selected>Hết hàng</option>
                </select>

                <label>Cần Thơ:</label>
                <select name="stock_cantho" required>
                    <option value="con" selected>Còn hàng</option>
                    <option value="het">Hết hàng</option>
                </select>
            </fieldset>

            <!-- 5. Mô tả sản phẩm -->
            <label for="description">Mô tả sản phẩm:</label>
            <textarea name="description" rows="4" required>Đây là một chiếc áo thun cotton cao cấp, thoáng mát, thích hợp cho mọi thời tiết...</textarea>

            <button type="submit">Cập nhật sản phẩm</button>
        </form>

        <div class="back-link">
            <a href="products.php">← Quay lại danh sách sản phẩm</a>
        </div>
    </div>
</div>

    </div>
</body>
</html>
