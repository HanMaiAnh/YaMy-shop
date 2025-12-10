<?php
session_name('admin_session');
session_start();
require_once __DIR__ . '/../config/db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$error = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code          = trim($_POST['code'] ?? '');
    $value         = isset($_POST['value']) ? (float)$_POST['value'] : 0;
    $amountReduced = isset($_POST['amount_reduced']) ? (float)$_POST['amount_reduced'] : 0;
    $minValue      = isset($_POST['minimum_value']) ? (float)$_POST['minimum_value'] : 0;
    $qty           = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $begin         = trim($_POST['begin'] ?? '');
    $expired       = trim($_POST['expired'] ?? '');

    if ($code === '') {
        $error = "Mã voucher không được để trống!";
    } elseif ($qty < 1) {
        $error = "Số lượng phải lớn hơn 0";
    } elseif ($begin === '' || $expired === '') {
        $error = "Ngày bắt đầu và ngày kết thúc không được bỏ trống!";
    }

    if (!$error) {
        $stmt = $pdo->prepare("
            INSERT INTO vouchers (code, value, amount_reduced, minimum_value, quantity, begin, expired)
            VALUES (:code, :value, :amount_reduced, :minimum_value, :quantity, :begin, :expired)
        ");
        $stmt->execute([
            ':code'          => $code,
            ':value'         => $value,
            ':amount_reduced'=> $amountReduced,
            ':minimum_value' => $minValue,
            ':quantity'      => $qty,
            ':begin'         => $begin,
            ':expired'       => $expired
        ]);

        header("Location: vouchers.php?success=added");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Thêm Voucher</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{display:flex;background:#f5f6fa;color:#111;}
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
.content{margin-left:280px;padding:40px;width:100%;}
.page-title{text-align:center;font-size:26px;font-weight:700;margin-bottom:20px;}

.form-card{
  background:#fff;border-radius:16px;padding:30px;max-width:650px;width:100%;
  margin:0 auto;box-shadow:0 4px 14px rgba(0,0,0,0.1);
}
label{font-weight:600;margin-top:15px;display:block;}
input,select{
  width:100%;padding:12px;border-radius:8px;border:1px solid #ccc;margin-top:6px;font-size:15px;
}
button{
  width:100%;padding:14px;background:#8E5DF5;color:#fff;border:none;border-radius:10px;
  font-size:16px;font-weight:700;margin-top:25px;cursor:pointer;transition:.3s;
}
button:hover{background:#E91E63;}
.error{margin-top:10px;color:#ef4444;font-weight:600;}
.back-link{text-align:center;margin-top:20px;}
.back-link a{color:#8E5DF5;text-decoration:none;font-weight:600;}
.back-link a:hover{text-decoration:underline;}
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
    <h1 class="page-title">Thêm Voucher mới</h1>

    <div class="form-card">
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">

            <label>Mã voucher</label>
            <input name="code" placeholder="VD: YAMY10">

            <label>Giảm theo %</label>
            <input type="number" name="value" min="0" max="100" step="0.1" placeholder="VD: 10">

            <label>Giảm theo tiền cố định (VNĐ)</label>
            <input type="number" name="amount_reduced" placeholder="VD: 50000">

            <label>Giá trị đơn tối thiểu</label>
            <input type="number" name="minimum_value" placeholder="VD: 200000">

            <label>Số lượng</label>
            <input type="number" name="quantity" placeholder="VD: 100" min="1">

            <label>Ngày bắt đầu</label>
            <input type="date" name="begin">

            <label>Ngày kết thúc</label>
            <input type="date" name="expired">

            <button type="submit"> Thêm Voucher</button>
        </form>

        <div class="back-link">
            <a href="vouchers.php">← Quay lại danh sách vouchers</a>
        </div>
    </div>
</div>

</body>
</html>
