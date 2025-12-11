<?php
session_name('admin_session');
session_start();
require_once __DIR__ . '/../config/db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Lấy id voucher
if (!isset($_GET['id'])) {
    header("Location: vouchers.php");
    exit;
}
$voucherId = (int)$_GET['id'];

/* =============================
   LẤY VOUCHER
============================= */
$stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = :id");
$stmt->execute([':id' => $voucherId]);
$voucher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voucher) {
    die("Không tìm thấy voucher.");
}

$error  = '';
$notice = '';

/* =============================
   XỬ LÝ FORM CẬP NHẬT VOUCHER
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code          = trim($_POST['code'] ?? '');
    $value         = trim($_POST['value'] ?? '');
    $amountReduced = trim($_POST['amount_reduced'] ?? '');
    $minimumValue  = trim($_POST['minimum_value'] ?? '');
    $quantity      = trim($_POST['quantity'] ?? '');
    $begin         = trim($_POST['begin'] ?? '');
    $expired       = trim($_POST['expired'] ?? '');

    // Validate
    if ($code === '') {
        $error = "Mã voucher không được để trống.";
    } elseif ($value === '' && $amountReduced === '') {
        $error = "Phải nhập Giá trị (%) hoặc Giảm tiền cố định (có thể dùng cả hai).";
    } elseif ($quantity === '' || !ctype_digit($quantity) || (int)$quantity < 0) {
        $error = "Số lượng phải là số nguyên không âm.";
    } elseif ($begin === '' || $expired === '') {
        $error = "Vui lòng nhập đầy đủ ngày bắt đầu và ngày kết thúc.";
    } elseif ($begin > $expired) {
        $error = "Ngày bắt đầu không được lớn hơn ngày kết thúc.";
    }

    if (!$error) {
        // Chuẩn hóa dữ liệu
        $valueFloat       = ($value === '' ? 0 : (float)$value);
        $minimumFloat     = ($minimumValue === '' ? 0 : (float)$minimumValue);
        $quantityInt      = (int)$quantity;
        $amountReducedDb  = ($amountReduced === '' ? null : (float)$amountReduced);

        $sql = "UPDATE vouchers
                SET code           = :code,
                    value          = :value,
                    amount_reduced = :amount_reduced,
                    minimum_value  = :minimum_value,
                    quantity       = :quantity,
                    begin          = :begin,
                    expired        = :expired
                WHERE id = :id";
        $params = [
            ':code'           => $code,
            ':value'          => $valueFloat,
            ':amount_reduced' => $amountReducedDb,
            ':minimum_value'  => $minimumFloat,
            ':quantity'       => $quantityInt,
            ':begin'          => $begin,
            ':expired'        => $expired,
            ':id'             => $voucherId
        ];

        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);

        header("Location: vouchers_edit.php?id={$voucherId}&success=1");
        exit;
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $notice = "Cập nhật voucher thành công!";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Sửa voucher</title>
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
.content{
  margin-left:280px;padding:40px;width:100%;
}
.page-title{
  font-size:26px;font-weight:700;margin-bottom:25px;text-align:center;
}
.form-card{
  background:#fff;border-radius:16px;padding:30px;max-width:800px;
  margin:0 auto;box-shadow:0 4px 14px rgba(0,0,0,0.1);
}
form label{
  font-weight:600;margin-top:18px;display:block;
}
form input,form textarea,form select{
  width:100%;padding:12px;border-radius:8px;border:1px solid #ccc;
  margin-top:6px;font-size:14px;
}
form textarea{resize:vertical;}
button{
  width:100%;padding:14px;margin-top:25px;background:#8E5DF5;
  border:none;border-radius:10px;color:#fff;font-weight:700;
  font-size:16px;cursor:pointer;transition:.3s;
}
button:hover{background:#E91E63;}
.note{margin-top:10px;color:#16a34a;font-weight:600;}
.error{margin-top:10px;color:#ef4444;font-weight:600;}
.back-link{text-align:center;margin-top:20px;}
.back-link a{
  color:#8E5DF5;text-decoration:none;font-weight:600;
}
.back-link a:hover{text-decoration:underline;}
.row-inline{
  display:flex;
  gap:16px;
}
.row-inline .col{
  flex:1;
}
.small-hint{
  font-size:12px;
  color:#777;
  margin-top:4px;
}
</style>
</head>
<body>

<div class="sidebar">
  <h3>YAMY ADMIN</h3>
 <a href="dashboard.php"><i class="fa fa-gauge"></i> Trang Quản Trị</a>
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
  <h1 class="page-title">Sửa voucher</h1>

  <div class="form-card">
    <?php if ($notice): ?>
      <div class="note"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
      <label>Mã voucher:</label>
      <input type="text" name="code"
             value="<?= htmlspecialchars($voucher['code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             placeholder="VD: YAMY10">

      <label>Giá trị (%) & Giảm tiền cố định:</label>
      <div class="row-inline">
        <div class="col">
          <input type="number" name="value" step="0.01" min="0"
                 value="<?= htmlspecialchars((string)($voucher['value'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="% giảm (vd: 10)">
        </div>
        <div class="col">
          <input type="number" name="amount_reduced" step="1000" min="0"
                 value="<?= htmlspecialchars($voucher['amount_reduced'] !== null ? (string)$voucher['amount_reduced'] : '', ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="Giảm tiền cố định (vd: 50000)">
        </div>
      </div>
      <div class="small-hint">
        Có thể dùng 1 trong 2 hoặc kết hợp cả 2. Để trống trường không dùng.
      </div>

      <label>Đơn tối thiểu (đ):</label>
      <input type="number" name="minimum_value" step="1000" min="0"
             value="<?= htmlspecialchars((string)($voucher['minimum_value'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
             placeholder="Giá trị đơn tối thiểu để áp dụng mã">

      <label>Số lượng (số lượt sử dụng):</label>
      <input type="number" name="quantity" min="0"
             value="<?= htmlspecialchars((string)($voucher['quantity'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">

      <label>Thời gian áp dụng:</label>
      <div class="row-inline">
        <div class="col">
          <input type="date" name="begin"
                 value="<?= htmlspecialchars($voucher['begin'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col">
          <input type="date" name="expired"
                 value="<?= htmlspecialchars($voucher['expired'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>

      <button type="submit">Cập nhật voucher</button>
    </form>

    <div class="back-link">
      <a href="vouchers.php">← Quay lại danh sách voucher</a>
    </div>
  </div>
</div>

</body>
</html>
