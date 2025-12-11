<?php
session_name('admin_session');
session_start();
require_once __DIR__ . '/../config/db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Lấy id sản phẩm
if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit;
}
$productId = (int)$_GET['id'];

/* =============================
   HÀM HỖ TRỢ
============================= */
function sanitize_price_input($str) {
    $str = trim((string)$str);
    if ($str === '') return 0.0;
    $str = str_replace([' ', "\xc2\xa0", '.'], '', $str);
    $str = str_replace(',', '.', $str);
    $str = preg_replace('/[^\d\.]/', '', $str);
    if ($str === '') return 0.0;
    return (float)$str;
}

/* =============================
   LẤY DỮ LIỆU TỪ CSDL
============================= */
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    die("Không tìm thấy sản phẩm.");
}

$stmtImg = $pdo->prepare("
    SELECT id, image_url
    FROM product_images
    WHERE product_id = :pid
    ORDER BY id ASC
    LIMIT 1
");
$stmtImg->execute([':pid' => $productId]);
$productImage = $stmtImg->fetch(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")
                  ->fetchAll(PDO::FETCH_ASSOC);

$sizes = $pdo->query("SELECT id, name FROM sizes ORDER BY id ASC")
             ->fetchAll(PDO::FETCH_ASSOC);

$colors = $pdo->query("SELECT id, name FROM colors ORDER BY name ASC")
              ->fetchAll(PDO::FETCH_ASSOC);

$variantsStmt = $pdo->prepare("
    SELECT v.*, s.name AS size_name, c.name AS color_name
    FROM product_variants v
    LEFT JOIN sizes  s ON s.id = v.size_id
    LEFT JOIN colors c ON c.id = v.color_id
    WHERE v.product_id = :pid
    ORDER BY v.id ASC
");
$variantsStmt->execute([':pid' => $productId]);
$variants = $variantsStmt->fetchAll(PDO::FETCH_ASSOC);

/* =============================
   XỬ LÝ FORM
============================= */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $name             = trim($_POST['name'] ?? '');
    $category_id      = (int)($_POST['category_id'] ?? 0);
    $is_featured      = isset($_POST['is_featured']) ? (int)$_POST['is_featured'] : 0;
    $status           = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $description      = trim($_POST['description'] ?? '');
    $discount_percent = isset($_POST['discount_percent']) ? (float)$_POST['discount_percent'] : 0;

    if ($name === '') {
        $error = "Tên không được để trống.";
    } elseif ($category_id <= 0) {
        $error = "Vui lòng chọn danh mục.";
    }

    $currentImage   = $productImage['image_url'] ?? null;
    $currentImageId = $productImage['id'] ?? null;
    $newImage       = $currentImage;

    if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $basename = basename($_FILES['image']['name']);
            $ext      = pathinfo($basename, PATHINFO_EXTENSION);
            $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($basename, PATHINFO_FILENAME));
            $filename = uniqid('prod_', true) . '_' . $safeName . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $newImage = $filename;
            } else {
                $error = "Không thể tải ảnh lên.";
            }
        } else {
            $error = "Lỗi upload ảnh (mã lỗi: " . (int)$_FILES['image']['error'] . ").";
        }
    }

    if (!$error) {
        $sql = "UPDATE products SET 
                    name             = :name,
                    description      = :description,
                    category_id      = :category_id,
                    is_featured      = :is_featured,
                    discount_percent = :discount_percent,
                    status           = :status
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'             => $name,
            ':description'      => $description,
            ':category_id'      => $category_id,
            ':is_featured'      => $is_featured,
            ':discount_percent' => $discount_percent,
            ':status'           => $status,
            ':id'               => $productId
        ]);

        if ($newImage && $newImage !== $currentImage) {
            if ($currentImageId) {
                $stmtImgUpdate = $pdo->prepare("UPDATE product_images SET image_url = :img WHERE id = :id");
                $stmtImgUpdate->execute([':img' => $newImage, ':id' => $currentImageId]);
            } else {
                $stmtImgInsert = $pdo->prepare("INSERT INTO product_images (product_id, image_url) VALUES (:pid, :img)");
                $stmtImgInsert->execute([':pid' => $productId, ':img' => $newImage]);
            }
        }

        header("Location: products_edit.php?id={$productId}&success=product");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_variant']) && !isset($_POST['add_variant'])) {
    $delId = (int)$_POST['delete_variant'];
    $stmtDel = $pdo->prepare("DELETE FROM product_variants WHERE id = :id AND product_id = :pid");
    $stmtDel->execute([':id' => $delId, ':pid' => $productId]);
    header("Location: products_edit.php?id={$productId}&success=variant_deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_variants'])) {
    if (!empty($_POST['variants']) && is_array($_POST['variants'])) {
        foreach ($_POST['variants'] as $variant_id => $v) {
            $size_id_raw = $v['size_id'] ?? null;
            $color_id_raw = $v['color_id'] ?? null;
            $quantity_raw = $v['quantity'] ?? 0;
            $price_raw = $v['price'] ?? 0;
            $price_reduced_raw = $v['price_reduced'] ?? 0;

            $size_id = (int)$size_id_raw;
            $color_id = (int)$color_id_raw;
            $quantity = (int)$quantity_raw;
            $price = is_string($price_raw) ? sanitize_price_input($price_raw) : (float)$price_raw;
            $price_reduced = is_string($price_reduced_raw) ? sanitize_price_input($price_reduced_raw) : (float)$price_reduced_raw;

            $stmt = $pdo->prepare("
                UPDATE product_variants 
                SET size_id = :size_id,
                    color_id = :color_id,
                    price = :price,
                    price_reduced = :price_reduced,
                    quantity = :quantity
                WHERE id = :id AND product_id = :pid
            ");
            $stmt->execute([
                ':size_id' => $size_id,
                ':color_id' => $color_id,
                ':price' => $price,
                ':price_reduced' => $price_reduced,
                ':quantity' => $quantity,
                ':id' => (int)$variant_id,
                ':pid' => $productId
            ]);
        }
    }

    if (!empty($_POST['new_variants']) && is_array($_POST['new_variants'])) {
        $insertStmt = $pdo->prepare("
            INSERT INTO product_variants
                (product_id, size_id, color_id, price, price_reduced, quantity)
            VALUES
                (:product_id, :size_id, :color_id, :price, :price_reduced, :quantity)
        ");
        foreach ($_POST['new_variants'] as $n) {
            $size_id = (int)($n['size_id'] ?? 0);
            $color_id = (int)($n['color_id'] ?? 0);
            $quantity = (int)($n['quantity'] ?? 0);
            $price = is_string($n['price'] ?? '') ? sanitize_price_input($n['price']) : (float)($n['price'] ?? 0);
            $price_reduced = is_string($n['price_reduced'] ?? '') ? sanitize_price_input($n['price_reduced']) : (float)($n['price_reduced'] ?? 0);

            if ($size_id > 0 && $color_id > 0) {
                $insertStmt->execute([
                    ':product_id' => $productId,
                    ':size_id'    => $size_id,
                    ':color_id'   => $color_id,
                    ':price'      => $price,
                    ':price_reduced' => $price_reduced,
                    ':quantity'   => $quantity
                ]);
            }
        }
    }

    header("Location: products_edit.php?id={$productId}&success=variants");
    exit;
}

function format_vnd($num) {
    if ($num === null || $num === '') return '';
    return number_format((float)$num, 0, ',', '.');
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Sửa sản phẩm</title>
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
.page-title{font-size:26px;font-weight:700;margin-bottom:25px;text-align:center;}
.form-card{background:#fff;border-radius:16px;padding:30px;max-width:900px;margin:0 auto;box-shadow:0 4px 14px rgba(0,0,0,0.1);}

/* product meta row: danh mục, giảm giá, loại, trạng thái - trên 1 hàng */
.product-meta {
  display:flex;
  gap:12px;
  align-items:flex-start;
  flex-wrap:wrap;
  margin-top:6px;
}
.product-meta .meta-item{
  flex:1 1 180px; /* co dãn, nhưng tối thiểu 180px */
  min-width:160px;
}
.product-meta label{display:block;margin-bottom:6px;font-weight:600;color:#444;font-size:13px;}
.product-meta select, .product-meta input{width:100%;padding:10px;border-radius:8px;border:1px solid #ddd;background:#fff;font-size:14px;}

/* keep form controls usual */
form label{font-weight:600;margin-top:18px;display:block;}
form input,form textarea,form select{width:100%;padding:12px;border-radius:8px;border:1px solid #ccc;margin-top:6px;font-size:14px;}
form textarea{resize:vertical;}
button.main-btn{width:100%;padding:14px;margin-top:25px;background:#8E5DF5;border:none;border-radius:10px;color:#fff;font-weight:700;font-size:16px;cursor:pointer;transition:.3s;}
button.main-btn:hover{background:#7c47e0;}
img.product-preview{width:120px;border-radius:10px;margin-top:10px;display:block;}
.note{margin-top:10px;color:#16a34a;font-weight:600;}
.error{margin-top:10px;color:#ef4444;font-weight:600;}

/* variants table style (like image 2) */
.variants-wrapper{
  margin-top:24px;background:#fff;border-radius:12px;padding:18px;border:1px solid #eee;
  box-shadow:none;
}
.variants-title{font-size:18px;font-weight:700;color:#8E5DF5;margin-bottom:4px;}
.variants-sub{font-size:13px;color:#777;margin-bottom:12px;}

#variants-table{width:100%;border-collapse:collapse;margin-top:6px;table-layout:fixed;}
#variants-table th,#variants-table td{border:1px solid #eee;padding:12px;text-align:center;vertical-align:middle;font-size:14px;}
#variants-table th{background:#f6ecff;color:#333;font-weight:700;}
#variants-table th:nth-child(1), #variants-table td:nth-child(1){width:8%;}
#variants-table th:nth-child(2), #variants-table td:nth-child(2){width:16%;}
#variants-table th:nth-child(3), #variants-table td:nth-child(3){width:16%;}
#variants-table th:nth-child(4), #variants-table td:nth-child(4){width:18%;}
#variants-table th:nth-child(5), #variants-table td:nth-child(5){width:18%;}
#variants-table th:nth-child(6), #variants-table td:nth-child(6){width:14%;}
#variants-table th:nth-child(7), #variants-table td:nth-child(7){width:10%;}
#variants-table select,#variants-table input[type="number"],#variants-table input[type="text"]{
  width:100%;max-width:150px;padding:10px;border-radius:10px;border:1px solid #ccc;font-size:14px;text-align:center;
}
.price-input{text-align:right;}

/* controls area under table */
.variants-controls{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-top:12px;
}

/* add button on left */
#add-variant-btn{
  background:#8E5DF5;color:#fff;border:none;padding:10px 14px;border-radius:8px;font-weight:700;cursor:pointer;
}
#add-variant-btn:hover{background:#7c47e0;}

/* make all delete buttons red */
.btn-inline, .btn-remove-new {
  padding:6px 12px;background:#ff4d4d;color:#fff;border:none;border-radius:6px;cursor:pointer;
}
.btn-inline:hover, .btn-remove-new:hover{background:#cc0000;}

/* place update button on right */
.variants-actions{text-align:right;}

/* responsive tweaks */
@media (max-width:900px){
  .product-meta .meta-item { flex:1 1 48%; }
}
@media (max-width:600px){
  .product-meta .meta-item { flex:1 1 100%; }
}
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
    <a href="comments.php"><i class="fa fa-comment"></i> Quản lý bình luận</a>
    <a href="logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">
  <h1 class="page-title">Sửa sản phẩm</h1>

  <div class="form-card">
    <?php
    if (isset($_GET['success']) && $_GET['success'] === 'product') echo '<div class="note">Cập nhật sản phẩm thành công!</div>';
    if (isset($_GET['success']) && $_GET['success'] === 'variants') echo '<div class="note">Cập nhật biến thể thành công!</div>';
    if (isset($_GET['success']) && $_GET['success'] === 'variant_deleted') echo '<div class="note">Đã xóa biến thể thành công!</div>';
    if ($error) echo '<div class="error">'.htmlspecialchars($error, ENT_QUOTES, 'UTF-8').'</div>';
    ?>

    <!-- FORM SẢN PHẨM -->
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="update_product" value="1">

      <label>Ảnh sản phẩm:</label>
      <input type="file" name="image" accept="image/*">
      <?php if (!empty($productImage['image_url'])): ?>
        <img class="product-preview" src="/clothing_store/uploads/<?= htmlspecialchars($productImage['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Ảnh sản phẩm">
      <?php endif; ?>

      <label>Tên sản phẩm:</label>
      <input type="text" name="name" value="<?= htmlspecialchars($product['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <!-- product meta row: danh mục, giảm giá, loại, trạng thái -->
      <div class="product-meta">
        <div class="meta-item">
          <label>Danh mục</label>
          <select name="category_id">
            <option value="">-- Chọn danh mục --</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $c['id'] == ($product['category_id'] ?? 0) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="meta-item">
          <label>Giảm giá (%)</label>
          <input type="number" name="discount_percent" value="<?= isset($product['discount_percent']) ? (int)$product['discount_percent'] : 0 ?>" min="0" max="100" step="0.1">
        </div>

        <div class="meta-item">
          <label>Loại sản phẩm</label>
          <?php $featured = (int)($product['is_featured'] ?? 0); ?>
          <select name="is_featured">
            <option value="0" <?= $featured === 0 ? 'selected' : '' ?>>Sản phẩm thường</option>
            <option value="1" <?= $featured === 1 ? 'selected' : '' ?>>Sản phẩm nổi bật</option>
            <option value="2" <?= $featured === 2 ? 'selected' : '' ?>>Sản phẩm giảm giá</option>
            <option value="3" <?= $featured === 3 ? 'selected' : '' ?>>Sản phẩm mới</option>
          </select>
        </div>

        <div class="meta-item">
          <label>Trạng thái kho</label>
          <?php $st = (int)($product['status'] ?? 1); ?>
          <select name="status">
            <option value="1" <?= $st === 1 ? 'selected' : '' ?>>Còn hàng</option>
            <option value="0" <?= $st === 0 ? 'selected' : '' ?>>Hết hàng</option>
          </select>
        </div>
      </div>

      <label>Mô tả:</label>
      <textarea name="description" rows="4"><?= htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

      <button type="submit" class="main-btn">Cập nhật sản phẩm</button>
    </form>

    <!-- FORM BIẾN THỂ -->
    <form method="post" id="variants-form" style="margin-top:24px;">
      <input type="hidden" name="update_variants" value="1">

      <div class="variants-wrapper">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div class="variants-title">Biến thể sản phẩm</div>
            <div class="variants-sub">Chỉnh sửa size, màu sắc, giá và số lượng cho từng biến thể.</div>
          </div>
        </div>

        <table id="variants-table">
          <tr>
            <th>ID</th>
            <th>Size</th>
            <th>Màu</th>
            <th>Giá (VND)</th>
            <th>Giá đã giảm (VND)</th>
            <th>Số lượng</th>
            <th>Hành động</th>
          </tr>

          <?php foreach ($variants as $v):
    $vid = (int)$v['id'];

    // Giá gốc & giá giảm dạng số (float) lấy từ DB
    $raw_price         = (float)($v['price'] ?? 0);
    $raw_price_reduced = (float)($v['price_reduced'] ?? 0);
    $quantity_val      = (int)($v['quantity'] ?? 0);

    // % giảm của sản phẩm cha
    $discount = (float)($product['discount_percent'] ?? 0);

    // Nếu chưa có price_reduced trong DB mà có % giảm -> tự tính
    if ($raw_price_reduced <= 0 && $discount > 0) {
        $raw_price_reduced = round($raw_price * (100 - $discount) / 100);
    }

    // Chuẩn bị giá hiển thị (có . phân cách nghìn)
    $price_val         = format_vnd($raw_price);
    $price_reduced_val = format_vnd($raw_price_reduced);
?>
    <tr data-variant-id="<?= $vid ?>">
      <td><?= $vid ?></td>

      <td>
        <select name="variants[<?= $vid ?>][size_id]">
          <?php foreach ($sizes as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id'] == ($v['size_id'] ?? 0) ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </td>

      <td>
        <select name="variants[<?= $vid ?>][color_id]">
          <?php foreach ($colors as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == ($v['color_id'] ?? 0) ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </td>

      <!-- Giá gốc -->
      <td>
        <input type="text"
               class="price-input"
               name="variants[<?= $vid ?>][price]"
               value="<?= htmlspecialchars($price_val, ENT_QUOTES, 'UTF-8') ?>">
      </td>

      <!-- Giá đã giảm -->
      <td>
        <input type="text"
               class="price-input"
               name="variants[<?= $vid ?>][price_reduced]"
               value="<?= htmlspecialchars($price_reduced_val, ENT_QUOTES, 'UTF-8') ?>">
      </td>

      <td>
        <input type="number"
               name="variants[<?= $vid ?>][quantity]"
               value="<?= htmlspecialchars($quantity_val, ENT_QUOTES, 'UTF-8') ?>">
      </td>

      <td>
        <button type="submit" name="delete_variant" value="<?= $vid ?>" class="btn-inline">Xóa</button>
      </td>
    </tr>
<?php endforeach; ?>


          <tbody id="new-variants-body"></tbody>

        </table>

        <div class="variants-controls">
          <div>
            <button type="button" id="add-variant-btn">+ Thêm biến thể</button>
          </div>
          <div class="variants-actions">
            <button type="submit" class="main-btn">Cập nhật biến thể</button>
          </div>
        </div>
      </div>
    </form>

    <div class="back-link">
      <a href="products.php">← Quay lại danh sách sản phẩm</a>
    </div>
  </div>

</div>

<script>
/* nhỏ gọn: thêm dòng mới (client), xóa dòng mới, format/unformat giá trước submit */
const SIZE_OPTIONS = `<?php foreach($sizes as $s) echo '<option value="'.$s['id'].'">'.htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8').'</option>'; ?>`;
const COLOR_OPTIONS = `<?php foreach($colors as $c) echo '<option value="'.$c['id'].'">'.htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8').'</option>'; ?>`;
let newIdx = 0;
const tbodyNew = document.getElementById('new-variants-body');
const addBtn = document.getElementById('add-variant-btn');
const form = document.getElementById('variants-form');

function fmtInt(n){ n=String(n||'').replace(/\D/g,''); return n? n.replace(/\B(?=(\d{3})+(?!\d))/g,'.') : ''; }
function unfmt(s){ return String(s||'').replace(/\u00A0/g,'').replace(/[.\s]/g,'').replace(/,/g,'.').replace(/[^\d\.]/g,''); }

addBtn.addEventListener('click', ()=>{
  const i = newIdx++;
  const tr = document.createElement('tr');
  tr.dataset.new = i;
  tr.innerHTML = `
    <td>mới</td>
    <td><select name="new_variants[${i}][size_id]"><option value="">--</option>${SIZE_OPTIONS}</select></td>
    <td><select name="new_variants[${i}][color_id]"><option value="">--</option>${COLOR_OPTIONS}</select></td>
    <td><input type="text" class="price-input" name="new_variants[${i}][price]" value=""></td>
    <td><input type="text" class="price-input" name="new_variants[${i}][price_reduced]" value=""></td>
    <td><input type="number" name="new_variants[${i}][quantity]" value="0" min="0"></td>
    <td><button type="button" class="btn-remove-new">Xóa</button></td>
  `;
  tbodyNew.appendChild(tr);
});

document.addEventListener('click', (ev)=>{
  if(ev.target && ev.target.classList.contains('btn-remove-new')){
    const tr = ev.target.closest('tr');
    if(tr) tr.remove();
  }
});

document.addEventListener('focusin', (ev)=>{
  if(ev.target && ev.target.classList && ev.target.classList.contains('price-input')){
    ev.target.value = unfmt(ev.target.value);
  }
});
document.addEventListener('focusout', (ev)=>{
  if(ev.target && ev.target.classList && ev.target.classList.contains('price-input')){
    const raw = unfmt(ev.target.value);
    ev.target.value = raw ? fmtInt(raw.split('.')[0]) : '';
  }
});

form.addEventListener('submit', ()=>{
  document.querySelectorAll('#variants-form .price-input').forEach(i=>{
    i.value = unfmt(i.value);
  });
});

/* nếu server trả post new_variants (rare), prefill */
<?php if(!empty($_POST['new_variants']) && is_array($_POST['new_variants'])):
    $js = json_encode($_POST['new_variants'], JSON_UNESCAPED_UNICODE);
    echo "const PREF = $js; PREF.forEach(v=>{ addBtn.click(); const last = tbodyNew.querySelector('tr[data-new=\"'+(newIdx-1)+'\"]'); if(last){ if(v.size_id) last.querySelector('[name$=\"[size_id]\"]').value=v.size_id; if(v.color_id) last.querySelector('[name$=\"[color_id]\"]').value=v.color_id; if(v.price) last.querySelector('[name$=\"[price]\"]').value=fmtInt(v.price); if(v.price_reduced) last.querySelector('[name$=\"[price_reduced]\"]').value=fmtInt(v.price_reduced); if(v.quantity) last.querySelector('[name$=\"[quantity]\"]').value=v.quantity;} });";
endif; ?>
</script>

</body>
</html>
