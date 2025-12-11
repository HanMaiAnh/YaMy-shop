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

// Lấy danh mục, size, color
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $sizes = $pdo->query("SELECT id, name FROM sizes ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $colors = $pdo->query("SELECT id, name FROM colors ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Lỗi khi lấy dữ liệu: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // product fields
    $name             = trim($_POST['name'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $category_id      = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $discount_percent = isset($_POST['discount_percent']) ? (float)$_POST['discount_percent'] : 0.0;
    $is_featured      = isset($_POST['is_featured']) ? (int)$_POST['is_featured'] : 0;
    $status           = isset($_POST['status']) ? (int)$_POST['status'] : 1;

    $variants = $_POST['variants'] ?? [];

    // kiểm tra biến thể có size_id & color_id hợp lệ không
    $hasValidVariant = false;
    foreach ($variants as $v) {
        $size_id  = (int)($v['size_id'] ?? 0);
        $color_id = (int)($v['color_id'] ?? 0);
        if ($size_id > 0 && $color_id > 0) {
            $hasValidVariant = true;
            break;
        }
    }

    if ($name === '' || $description === '' || $category_id <= 0 || !$hasValidVariant) {
        $error = "Vui lòng điền đầy đủ thông tin sản phẩm, chọn danh mục và ít nhất 1 biến thể hợp lệ.";
    }

    // xử lý ảnh
    $imagePath = '';
    if (!$error) {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = "Vui lòng chọn ảnh sản phẩm.";
        } else {
            $imageName = $_FILES['image']['name'];
            $imageTmp  = $_FILES['image']['tmp_name'];

            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $ext = pathinfo($imageName, PATHINFO_EXTENSION);
            $safeName  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($imageName, PATHINFO_FILENAME));
            $imagePath = uniqid('', true) . '_' . $safeName . '.' . $ext;

            $destination = $uploadDir . $imagePath;
            if (!move_uploaded_file($imageTmp, $destination)) {
                $error = "Không thể lưu ảnh lên máy chủ.";
            }
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // Thêm sản phẩm (thêm các trường discount_percent, is_featured, status)
            $stmt = $pdo->prepare("
                INSERT INTO products
                  (name, description, category_id, discount_percent, is_featured, status)
                VALUES
                  (:name, :description, :category_id, :discount_percent, :is_featured, :status)
            ");
            $stmt->execute([
                ':name'             => $name,
                ':description'      => $description,
                ':category_id'      => $category_id,
                ':discount_percent' => $discount_percent,
                ':is_featured'      => $is_featured,
                ':status'           => $status,
            ]);
            $productId = (int)$pdo->lastInsertId();

            // Lưu ảnh chính vào product_images
            $stmtImg = $pdo->prepare("
                INSERT INTO product_images (product_id, image_url)
                VALUES (:product_id, :image_url)
            ");
            $stmtImg->execute([
                ':product_id' => $productId,
                ':image_url'  => $imagePath,
            ]);

            // Thêm biến thể
            $stmtVar = $pdo->prepare("
                INSERT INTO product_variants
                    (product_id, size_id, color_id, quantity, price, price_reduced)
                VALUES
                    (:product_id, :size_id, :color_id, :quantity, :price, :price_reduced)
            ");

            foreach ($variants as $v) {
                $size_id       = (int)($v['size_id'] ?? 0);
                $color_id      = (int)($v['color_id'] ?? 0);
                $quantity      = (int)($v['quantity'] ?? 0);
                $price         = (float)($v['price'] ?? 0);
                $price_reduced = (float)($v['price_reduced'] ?? 0);

                if ($size_id > 0 && $color_id > 0) {
                    $stmtVar->execute([
                        ':product_id'     => $productId,
                        ':size_id'        => $size_id,
                        ':color_id'       => $color_id,
                        ':quantity'       => $quantity,
                        ':price'          => $price,
                        ':price_reduced'  => $price_reduced,
                    ]);
                }
            }

            $pdo->commit();
            header("Location: products.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Lỗi khi lưu sản phẩm: " . $e->getMessage();
        }
    }
}

// build options size & color để dùng cho JS
$sizeOptionsHtml = '';
foreach ($sizes as $s) {
    $sizeOptionsHtml .= '<option value="'.$s['id'].'">'.htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8').'</option>';
}
$colorOptionsHtml = '';
foreach ($colors as $c) {
    $colorOptionsHtml .= '<option value="'.$c['id'].'">'.htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8').'</option>';
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<title>Thêm sản phẩm - YaMy Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
/* shared layout */
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
.page-title{font-size:26px;font-weight:700;margin-bottom:25px;text-align:center;}
.form-card{background:#fff;border-radius:16px;padding:30px;max-width:1000px;margin:0 auto;box-shadow:0 4px 14px rgba(0,0,0,0.08);}

/* inputs */
label{display:block;margin-top:12px;font-weight:600;color:#444;}
input[type="text"],input[type="number"],textarea,select,input[type="file"]{
  width:100%;margin-top:8px;padding:12px 14px;border:1px solid #ddd;border-radius:10px;background:#fff;font-size:14px;transition:.18s;
}
input:focus,textarea:focus,select:focus{border-color:#8E5DF5;outline:none;}
textarea{resize:vertical;min-height:90px;}

/* product meta row */
.product-meta{display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-top:6px;}
.meta-item{flex:1 1 200px;min-width:160px;position:relative;overflow:visible;}
.product-meta label{display:block;margin-bottom:6px;font-weight:600;color:#444;font-size:13px;}
.product-meta select, .product-meta input{width:100%;padding:10px;border-radius:8px;border:1px solid #ddd;background:#fff;font-size:14px;}

/* variants appearance */
.variants-wrapper{margin-top:18px;background:#fff;border-radius:12px;padding:18px;border:1px solid #eee;}
.variants-title{font-size:18px;font-weight:700;color:#8E5DF5;margin-bottom:6px;}
.variants-sub{font-size:13px;color:#777;margin-bottom:12px;}

#variants-table{width:100%;border-collapse:collapse;margin-top:6px;table-layout:fixed;}
#variants-table th,#variants-table td{border:1px solid #eee;padding:10px;text-align:center;vertical-align:middle;font-size:14px;}
#variants-table th{background:#f6ecff;color:#333;font-weight:700;}
#variants-table select,#variants-table input[type="number"],#variants-table input[type="text"]{
  width:100%;max-width:160px;padding:8px;border-radius:8px;border:1px solid #ccc;font-size:14px;text-align:center;
}
.price-input{text-align:right;}

/* controls */
.variants-controls{display:flex;justify-content:space-between;align-items:center;margin-top:12px;}
#add-variant-btn{background:#8E5DF5;color:#fff;border:none;padding:10px 14px;border-radius:8px;font-weight:700;cursor:pointer;}
#add-variant-btn:hover{background:#7c47e0;}

/* delete buttons red */
.btn-xoa, .btn-inline, .btn-remove-new{
  padding:6px 12px;background:#ff4d4d;color:#fff;border:none;border-radius:6px;cursor:pointer;
}
.btn-xoa:hover, .btn-inline:hover, .btn-remove-new:hover{background:#cc0000;}

/* main submit purple full-width with white text, hover to pink */
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
@media (max-width:900px){
  .product-meta .meta-item { flex:1 1 48%; }
}
@media (max-width:600px){
  .product-meta .meta-item { flex:1 1 100%; }
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
  <h1 class="page-title">Thêm sản phẩm mới</h1>

  <div class="form-card">
    <?php if (!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- SINGLE FORM chứa toàn bộ product + variants -->
    <form method="post" enctype="multipart/form-data" id="product-add-form">
      <label>Ảnh sản phẩm:</label>
      <input type="file" name="image" accept="image/*" required>

      <label>Tên sản phẩm:</label>
      <input type="text" name="name" placeholder="Ví dụ: Áo Thun Logo YaMy" required>

      <!-- product-meta row -->
      <div class="product-meta" style="margin-top:6px;">
        <div class="meta-item">
          <label>Danh mục</label>
          <select name="category_id" required>
            <option value="">-- Chọn danh mục --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="meta-item">
          <label>Giảm giá (%)</label>
          <input type="number" name="discount_percent" value="0" min="0" max="100" step="0.1">
        </div>

        <div class="meta-item">
          <label>Loại sản phẩm</label>
          <select name="is_featured">
            <option value="0">Sản phẩm thường</option>
            <option value="1">Sản phẩm nổi bật</option>
            <option value="2">Sản phẩm giảm giá</option>
            <option value="3">Sản phẩm mới</option>
          </select>
        </div>

        <div class="meta-item">
          <label>Trạng thái kho</label>
          <select name="status">
            <option value="1">Còn hàng</option>
            <option value="0">Hết hàng</option>
          </select>
        </div>
      </div>

      <label>Mô tả sản phẩm:</label>
      <textarea name="description" rows="4" placeholder="Nhập mô tả chi tiết" required></textarea>

      <!-- VARIANTS -->
      <fieldset class="variants-wrapper">
        <legend style="border:none;padding:0 8px;">Biến thể (Size, Màu, Tồn kho & Giá)</legend>
        <div class="variants-sub">Thêm các biến thể cho sản phẩm. (Size, Màu, Tồn kho, Giá)</div>

        <table id="variants-table">
          <thead>
            <tr>
              <th>Size</th>
              <th>Màu</th>
              <th>Tồn kho</th>
              <th>Giá (VND)</th>
              <th>Giá đã giảm (VND)</th>
              <th>Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <select name="variants[0][size_id]" required>
                  <option value="">-- Size --</option>
                  <?php foreach ($sizes as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <select name="variants[0][color_id]" required>
                  <option value="">-- Màu --</option>
                  <?php foreach ($colors as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" name="variants[0][quantity]" value="0" min="0" required></td>
              <td><input type="number" name="variants[0][price]" value="0" min="0" step="0.01" required></td>
              <td><input type="number" name="variants[0][price_reduced]" value="0" min="0" step="0.01" required></td>
              <td><button type="button" class="btn-xoa" onclick="this.closest('tr').remove()">Xóa</button></td>
            </tr>
          </tbody>
        </table>

        <div class="variants-controls">
          <div>
            <button type="button" id="add-variant-btn">+ Thêm biến thể</button>
          </div>
          <div style="text-align:right;">
            <!-- reserved area (nút cập nhật biến thể không cần vì toàn bộ gửi kèm) -->
          </div>
        </div>
      </fieldset>

      <!-- nút submit chính (bên trong form) -->
      <button type="submit" class="button-submit-main">Thêm sản phẩm</button>
    </form>

    <div class="back-link">
      <a href="products.php">← Quay lại danh sách sản phẩm</a>
    </div>
  </div>
</div>

<script>
/* JS: thêm hàng biến thể động, xóa hàng mới. Dùng options sinh từ PHP */
const SIZE_OPTIONS = `<?= str_replace("\n",'', $sizeOptionsHtml) ?>`;
const COLOR_OPTIONS = `<?= str_replace("\n",'', $colorOptionsHtml) ?>`;

document.getElementById('add-variant-btn').addEventListener('click', function(){
  const tbody = document.querySelector('#variants-table tbody');
  const idx = tbody.querySelectorAll('tr').length;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select name="variants[${idx}][size_id]" required>
        <option value="">-- Size --</option>
        ${SIZE_OPTIONS}
      </select>
    </td>
    <td>
      <select name="variants[${idx}][color_id]" required>
        <option value="">-- Màu --</option>
        ${COLOR_OPTIONS}
      </select>
    </td>
    <td><input type="number" name="variants[${idx}][quantity]" value="0" min="0" required></td>
    <td><input type="number" name="variants[${idx}][price]" value="0" min="0" step="0.01" required></td>
    <td><input type="number" name="variants[${idx}][price_reduced]" value="0" min="0" step="0.01" required></td>
    <td><button type="button" class="btn-remove-new">Xóa</button></td>
  `;
  tbody.appendChild(tr);
});

document.addEventListener('click', function(e){
  if (e.target && (e.target.classList.contains('btn-remove-new') || e.target.classList.contains('btn-xoa'))) {
    const r = e.target.closest('tr');
    if (r) r.remove();
  }
});
</script>
</body>
</html>
