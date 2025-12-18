<?php
session_name('admin_session');
session_start();
require_once __DIR__ . '/../config/db.php'; // tạo ra $pdo

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

/* =============================
    LẤY DANH MỤC (dùng cho dropdown)
============================= */
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")
                  ->fetchAll(PDO::FETCH_ASSOC);

/* =============================
    NHẬN INPUTS LỌC + PHÂN TRANG
============================= */
$limit  = 10;
$page   = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page   = max(1, $page);
$offset = ($page - 1) * $limit;

$categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$featuredFilter = isset($_GET['featured']) ? (string)$_GET['featured'] : 'all';
$q = trim((string)($_GET['q'] ?? ''));

// xác thực featuredFilter
$allowedFeatured = ['all','0','1','2','3'];
if (!in_array($featuredFilter, $allowedFeatured, true)) $featuredFilter = 'all';

/* =============================
    XÂY DỰNG WHERE sử dụng positional placeholders (?)
    và $paramsOrdered là mảng tham số theo thứ tự
============================= */
$whereParts = [];
$paramsOrdered = [];

if ($categoryFilter > 0) {
    $whereParts[] = "p.category_id = ?";
    $paramsOrdered[] = $categoryFilter;
}

if ($featuredFilter !== 'all') {
    $whereParts[] = "p.is_featured = ?";
    $paramsOrdered[] = (int)$featuredFilter;
}

if ($q !== '') {
    // tìm trong name hoặc description - dùng 2 placeholder (OR)
    $whereParts[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $paramsOrdered[] = '%' . $q . '%';
    $paramsOrdered[] = '%' . $q . '%';
}

$whereSql = '';
if (!empty($whereParts)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereParts);
}

/* =============================
    ĐẾM TỔNG với positional params
============================= */
$total_sql = "SELECT COUNT(DISTINCT p.id) FROM products p $whereSql";
$total_stmt = $pdo->prepare($total_sql);
$total_stmt->execute($paramsOrdered);
$totalProducts = (int)$total_stmt->fetchColumn();

$totalPages = max(1, ceil($totalProducts / $limit));
if ($page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $limit;
}

/* =============================
    LẤY DANH SÁCH SẢN PHẨM
    - Sử dụng positional placeholders hoàn toàn
    - Thêm LIMIT ? OFFSET ? và append $limit, $offset vào mảng params
    - NOTE: đã loại bỏ cột products.stock, dùng products.status để admin chọn
============================= */
$sql = "
    SELECT
        p.id,
        p.name,
        p.description,

        MIN(pv.price)         AS price,
        MIN(pv.price_reduced) AS price_reduced,
        IFNULL(SUM(pv.quantity),0) AS stock_quantity,
        GROUP_CONCAT(DISTINCT siz.name ORDER BY siz.name SEPARATOR ', ') AS sizes,
        GROUP_CONCAT(DISTINCT col.name ORDER BY col.name SEPARATOR ', ') AS colors,
        p.discount_percent,
        p.is_featured,
        p.status,
        p.created_at,
        c.name AS category_name,
        MIN(pi.image_url)     AS image_url
    FROM products p
    LEFT JOIN categories      c   ON c.id = p.category_id
    LEFT JOIN product_variants pv ON pv.product_id = p.id
    LEFT JOIN sizes           siz ON siz.id = pv.size_id
    LEFT JOIN colors          col ON col.id = pv.color_id
    LEFT JOIN product_images  pi  ON pi.product_id = p.id
    $whereSql
    GROUP BY 
        p.id, p.name, p.description,
        p.discount_percent, p.is_featured, p.status, p.created_at, c.name
    ORDER BY p.id DESC
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);

// chuẩn bị params cho truy vấn select: copy paramsOrdered rồi append limit/offset (int)
$paramsForSelect = $paramsOrdered;
$paramsForSelect[] = (int)$limit;
$paramsForSelect[] = (int)$offset;

$stmt->execute($paramsForSelect);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8" />
<title>Quản lý sản phẩm</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
/* CSS giữ nguyên như trước, style badge + responsive */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{display:flex;background:#f5f6fa;color:#111;}
a{text-decoration:none;}
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
.content{margin-left:280px;padding:30px;width:100%;}
.page-title{font-size:26px;font-weight:700;margin-bottom:15px}
.top-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
    margin-bottom:15px;
}
.filter-bar{
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
    flex:1;
}
.select, .input {
    padding:10px 14px;
    border-radius:8px;
    border:1px solid #e6e6e6;
    background:#fff;
    font-size:14px;
}
.select { min-width:220px; }
.input  { min-width:320px; }
.btn-primary{
    background:#6C4CF0;color:#fff;padding:10px 14px;border-radius:8px;border:none;font-weight:700;cursor:pointer;
}
.btn-danger{
    background:#f24545;color:#fff;padding:10px 14px;border-radius:8px;border:none;font-weight:700;cursor:pointer;
}
.add-btn { background:#E91E63;color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:600;white-space:nowrap; }
table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    border-radius:14px;
    overflow:hidden;
    margin-top:10px;
}
th{
    background:#8E5DF5;
    padding:14px;
    text-align:left;
    font-weight:600;
    color:#fff;
}
td{
    padding:14px;
    border-bottom:1px solid #ddd;
    vertical-align:middle;
}
tr:hover{background:#f9f9f9;}
img.product-img{
    width:55px;height:55px;border-radius:6px;object-fit:cover;max-width:100px;
}
.price-text{white-space:nowrap;}
.badge{display:inline-block;padding:6px 10px;border-radius:10px;font-size:13px;font-weight:600;}
.badge-type-normal{background:#e0e0e0;color:#333;}
.badge-type-featured{background:#ffe0f0;color:#e91e63;}
.badge-type-sale{background:#ffe7d9;color:#e65100;}
.badge-type-new{background:#e3f2fd;color:#1565c0;}
.badge-instock{background:#e0f8e9;color:#2e7d32;}
.badge-outstock{background:#ffe0e0;color:#c62828;}
.btn-edit{background:#03A9F4;padding:6px 12px;border-radius:6px;color:#fff;font-size:14px;}
.btn-edit:hover{background:#0288D1;}
.pagination{text-align:center;margin-top:18px;}
.pagination a{color:#fff;padding:8px 12px;border-radius:6px;background:#1a1a1a;margin:3px;text-decoration:none;}
.pagination a:hover{background:#8E5DF5;}
.pagination a.active{background:#E91E63;}
@media(max-width:900px){
    .top-bar{flex-direction:column;align-items:flex-start;}
    .filter-bar{width:100%}
    .select, .input{min-width:100%}
    .add-btn{width:100%;text-align:center}
}
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
    <h1 class="page-title">Quản lý sản phẩm</h1>

    <div class="top-bar">
        <!-- form GET cho lọc giống các trang khác -->
        <form method="get" action="products.php" class="filter-bar" id="filterForm">
            <input type="text" name="q" class="input" placeholder="Tìm sản phẩm " value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
            <select name="category" class="select" aria-label="Chọn danh mục">
                <option value="0" <?= $categoryFilter==0 ? 'selected' : '' ?>>Tất cả sản phẩm</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $categoryFilter==(int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="featured" class="select" aria-label="Lọc loại sản phẩm">
                <option value="all" <?= $featuredFilter==='all' ? 'selected' : '' ?>>Tất cả loại</option>
                <option value="0" <?= $featuredFilter==='0' ? 'selected' : '' ?>>Bình thường</option>
                <option value="1" <?= $featuredFilter==='1' ? 'selected' : '' ?>>Nổi bật</option>
                <option value="2" <?= $featuredFilter==='2' ? 'selected' : '' ?>>Giảm giá</option>
                <option value="3" <?= $featuredFilter==='3' ? 'selected' : '' ?>>Mới</option>
            </select>

            <button type="submit" class="btn-primary">Lọc</button>
            <button type="button" id="btnReset" class="btn-danger">Reset</button>
        </form>

        <a href="products_add.php" class="add-btn">+ Thêm sản phẩm</a>
    </div>

    <table>
        <tr>
            <th>Ảnh</th>
            <th>ID</th>
            <th>Tên</th>
            <th>Danh mục</th>
            <th>Size</th>
            <th>Màu sắc</th>
            <th>Giá gốc</th>
            <th>Giảm (%)</th>
            <th>Giá cuối</th>
            <th>Tồn kho</th>
            <th>Loại</th>
            <th>Ngày tạo</th>
            <th>Hành động</th>
        </tr>

        <?php foreach($products as $row): ?>
            <?php
                $price          = (float)($row['price'] ?? 0);
                $priceReduced   = (float)($row['price_reduced'] ?? 0);
                $discountPercent= (float)($row['discount_percent'] ?? 0);

                if ($priceReduced > 0) {
                    $finalPrice = $priceReduced;
                } elseif ($price > 0 && $discountPercent > 0) {
                    $finalPrice = $price * (1 - $discountPercent/100);
                } else {
                    $finalPrice = $price;
                }

                // status: 1 = Hiển thị, 0 = Ẩn/hết hàng
                $status = (int)($row['status'] ?? 0);
                $stockQty  = (int)($row['stock_quantity'] ?? 0);

                $imgSrc = '';
                if (!empty($row['image_url'])) {
                    $imgSrc = '/clothing_store/uploads/' . ltrim($row['image_url'], '/');
                }

                $typeValue = (int)($row['is_featured'] ?? 0);
                switch ($typeValue) {
                    case 1:
                        $typeLabel = 'Sản phẩm nổi bật';
                        $typeClass = 'badge-type-featured';
                        break;
                    case 2:
                        $typeLabel = 'Sản phẩm giảm giá';
                        $typeClass = 'badge-type-sale';
                        break;
                    case 3:
                        $typeLabel = 'Sản phẩm mới';
                        $typeClass = 'badge-type-new';
                        break;
                    default:
                        $typeLabel = 'Sản phẩm thường';
                        $typeClass = 'badge-type-normal';
                        break;
                }

                // LOGIC HIỂN THỊ TỒN KHO:
                // - Nếu admin set status = 0 => show "Hết hàng" (ẩn/không public)
                // - Nếu status = 1:
                //      - Nếu tổng quantity > 0 => "Còn hàng (n)"
                //      - Nếu tổng quantity = 0 => "Hết hàng (0)" (admin bật hiển thị nhưng thực tế hết)
                if ($status === 0) {
                    $stockLabel = 'Hết hàng';
                    $stockClass = 'badge-outstock';
                } else {
                    if ($stockQty > 0) {
                        $stockLabel = 'Còn hàng ('.$stockQty.')';
                        $stockClass = 'badge-instock';
                    } else {
                        $stockLabel = 'Hết hàng (0)';
                        $stockClass = 'badge-outstock';
                    }
                }
            ?>

        <tr>
            <td>
                <?php if ($imgSrc): ?>
                    <img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" class="product-img" alt="">
                <?php else: ?>
                    <span>Chưa có ảnh</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['category_name'] ?? 'Chưa gán', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['sizes'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['colors'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>

            <td class="price-text">
                <?php
                    $style = ($discountPercent > 0 || $priceReduced > 0) 
                        ? "text-decoration:line-through;color:#888;" 
                        : "";
                    echo "<span style='{$style}white-space:nowrap;'>" .
                         number_format($price, 0, ',', '.') . " ₫</span>";
                ?>
            </td>

            <td>
                <?= $discountPercent > 0 ? htmlspecialchars($discountPercent, ENT_QUOTES, 'UTF-8') . '%' : '-' ?>
            </td>

            <td>
                <?php
                    $showPrice = $finalPrice > 0 ? $finalPrice : $price;
                    echo "<span class='price-text' style='color:#E91E63;font-weight:600;white-space:nowrap;'>" .
                         number_format($showPrice, 0, ',', '.') . " ₫</span>";
                ?>
            </td>

            <td>
                <span class="badge <?= $stockClass; ?>">
                    <?= htmlspecialchars($stockLabel, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </td>

            <td>
                <span class="badge <?= $typeClass; ?>">
                    <?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </td>

            <td><?= htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>

            <td>
                <a href="products_edit.php?id=<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>" class="btn-edit">
                    Sửa
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="pagination">
        <?php
        if ($totalPages > 1) {
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            if ($start > 1) {
                $qs = http_build_query(['page'=>1,'category'=>$categoryFilter,'featured'=>$featuredFilter,'q'=>$q]);
                echo '<a href="?'.$qs.'">1</a>';
                if ($start > 2) echo '<span>...</span>';
            }
            for ($i = $start; $i <= $end; $i++) {
                $qs = http_build_query(['page'=>$i,'category'=>$categoryFilter,'featured'=>$featuredFilter,'q'=>$q]);
                echo '<a href="?'.$qs.'" class="'.($i==$page?'active':'').'">'.$i.'</a>';
            }
            if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo '<span>...</span>';
                $qs = http_build_query(['page'=>$totalPages,'category'=>$categoryFilter,'featured'=>$featuredFilter,'q'=>$q]);
                echo '<a href="?'.$qs.'">'.$totalPages.'</a>';
            }
        }
        ?>
    </div>
</div>

<script>
// Reset: clear inputs and submit (go to default all)
document.getElementById('btnReset').addEventListener('click', function(){
    const form = document.getElementById('filterForm');
    if (!form) return;
    form.querySelector('select[name="category"]').value = '0';
    form.querySelector('select[name="featured"]').value = 'all';
    form.querySelector('input[name="q"]').value = '';
    form.submit();
});
</script>
</body>
</html>
