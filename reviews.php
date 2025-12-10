<?php
// admin/reviews.php
session_name('admin_session');
session_start();
require_once __DIR__ . '/../config/db.php'; // tạo ra $pdo

// kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// đảm bảo cột is_hidden tồn tại (nếu có quyền sẽ thêm)
try {
    $check = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'reviews'
          AND COLUMN_NAME = 'is_hidden'
        LIMIT 1
    ");
    $check->execute();
    $colExists = (bool)$check->fetchColumn();
    if (!$colExists) {
        $pdo->exec("ALTER TABLE reviews ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (PDOException $e) {
    // Nếu không có quyền ALTER, tiếp tục (COALESCE sẽ xử lý)
    $colExists = false;
}

// xử lý hide (soft-hide)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hide_review'])) {
    $hideId = (int)$_POST['hide_review'];
    if ($hideId > 0) {
        $stmt = $pdo->prepare("UPDATE reviews SET is_hidden = 1 WHERE id = ?");
        $stmt->execute([$hideId]);
        $message = "Đã ẩn bình luận #{$hideId}.";
    }
    header("Location: reviews.php?msg=" . urlencode($message));
    exit;
}

// params lọc / phân trang
$perPage = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$productFilter = isset($_GET['product']) ? (int)$_GET['product'] : 0;
$ratingFilter  = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$qSearch       = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// danh sách products cho select filter
$products = $pdo->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Build WHERE dùng positional placeholders (?)
$whereParts = [];
$params = []; // positional array in correct order

// luôn loại các review đã ẩn
$whereParts[] = "COALESCE(r.is_hidden,0) = 0";

if ($productFilter > 0) {
    $whereParts[] = "r.product_id = ?";
    $params[] = $productFilter;
}
if ($ratingFilter > 0) {
    $whereParts[] = "r.rating = ?";
    $params[] = $ratingFilter;
}
if ($qSearch !== '') {
    $whereParts[] = "(r.comment LIKE ? OR u.username LIKE ? OR p.name LIKE ?)";
    $like = '%' . $qSearch . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// COUNT total
$countSql = "
    SELECT COUNT(*) 
    FROM reviews r
    LEFT JOIN users u ON u.id = r.user_id
    LEFT JOIN products p ON p.id = r.product_id
    $whereSQL
";
$stmtCount = $pdo->prepare($countSql);

// bind positional params for count
if (!empty($params)) {
    for ($i = 0; $i < count($params); $i++) {
        $val = $params[$i];
        $stmtCount->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}
$stmtCount->execute();
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page-1)*$perPage; }

// SELECT dữ liệu (kèm ảnh đại diện sản phẩm)
$sql = "
    SELECT r.id, r.product_id, r.user_id, r.rating, r.comment, r.created_at,
           p.name AS product_name,
           u.username AS username, u.email AS user_email,
           (
             SELECT pi.image_url
             FROM product_images pi
             WHERE pi.product_id = p.id
             ORDER BY pi.id ASC
             LIMIT 1
           ) AS product_image
    FROM reviews r
    LEFT JOIN products p ON p.id = r.product_id
    LEFT JOIN users u ON u.id = r.user_id
    $whereSQL
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);

// bind filter params first (positional)
$bindIndex = 1;
if (!empty($params)) {
    foreach ($params as $val) {
        $stmt->bindValue($bindIndex, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $bindIndex++;
    }
}
// bind limit and offset as integers
$stmt->bindValue($bindIndex++, (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue($bindIndex++, (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// message từ GET (sau redirect)
if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message = (string)$_GET['msg'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Quản lý bình luận & đánh giá</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* CSS giữ nguyên giao diện bạn muốn + thumbnail */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{display:flex;background:#f5f6fa;color:#111;min-height:100vh;}
a{text-decoration:none;color:inherit;}
.sidebar{width:260px;background:#fff;height:100vh;padding:30px 20px;position:fixed;border-right:1px solid #ddd;}
.sidebar h3{font-size:22px;font-weight:700;margin-bottom:25px;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:12px;color:#333;text-decoration:none;border-radius:8px;margin-bottom:8px;transition:.25s;font-weight:500;font-size:15px;}
.sidebar a:hover{background:#f2e8ff;color:#8E5DF5;transform:translateX(4px);}
.sidebar .logout{color:#e53935;margin-top:20px;}
.content{margin-left:280px;padding:30px;width:100%;}
.page-title{font-size:26px;font-weight:700;margin-bottom:18px;}
.filter-bar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;}
.filter-bar select, .filter-bar input[type="text"]{padding:10px;border-radius:8px;border:1px solid #ddd;background:#fff;font-size:14px;}
.filter-bar button{padding:10px 14px;border-radius:8px;border:none;background:#8E5DF5;color:#fff;font-weight:700;cursor:pointer;}
.filter-bar .reset{background:#ef4444;margin-left:6px;}
.note{padding:10px 14px;background:#e6ffed;color:#2e7d32;border-radius:8px;margin-bottom:12px;font-weight:600;}
.error{padding:10px 14px;background:#fff1f0;color:#c62828;border-radius:8px;margin-bottom:12px;font-weight:600;}
.table-box{background:#fff;border-radius:12px;padding:12px;border:1px solid #eee;}
table{width:100%;border-collapse:collapse;}
th,td{padding:12px;border-bottom:1px solid #f1f1f1;text-align:left;vertical-align:top;}
th{background:#faf8ff;font-weight:700;color:#333;}
.user-meta{font-size:13px;color:#666;}
.star{color:#fbbf24;font-weight:700;}
/* thumbnail */
.thumb{
    width:64px;
    height:64px;
    object-fit:cover;
    border-radius:8px;
    border:1px solid #eee;
    display:inline-block;
}
/* nút nhỏ: inline-flex + không wrap */
.btn-small{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:8px;
    border:none;
    cursor:pointer;
    font-weight:700;
    white-space:nowrap;
    color:#fff;
    text-decoration:none;
    font-size:14px;
}
.btn-small i{font-size:16px;}
.btn-view{background:#03A9F4;}
.btn-hide{background:#ffb020;color:#111;}
.pager{margin-top:12px;text-align:center;}
.pager a{display:inline-block;padding:8px 10px;margin:2px;border-radius:6px;background:#111;color:#fff;text-decoration:none;}
.pager a.active{background:#8E5DF5;}
.empty{padding:18px;text-align:center;color:#777;}
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
        <h1 class="page-title">Quản lý bình luận & đánh giá</h1>

        <?php if ($message): ?>
            <div class="note"><?= h($message) ?></div>
        <?php endif; ?>

        <div class="filter-bar">
            <form method="get" style="display:flex;gap:8px;align-items:center;">
                <label>
                    <select name="product">
                        <option value="0">-- Tất cả sản phẩm --</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $productFilter === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= h($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <select name="rating">
                        <option value="0">-- Tất cả đánh giá --</option>
                        <option value="5" <?= $ratingFilter===5 ? 'selected' : '' ?>>5 sao</option>
                        <option value="4" <?= $ratingFilter===4 ? 'selected' : '' ?>>4 sao</option>
                        <option value="3" <?= $ratingFilter===3 ? 'selected' : '' ?>>3 sao</option>
                        <option value="2" <?= $ratingFilter===2 ? 'selected' : '' ?>>2 sao</option>
                        <option value="1" <?= $ratingFilter===1 ? 'selected' : '' ?>>1 sao</option>
                    </select>
                </label>

                <label>
                    <input type="text" name="q" placeholder="Tìm kiếm nội dung, user, sản phẩm..." value="<?= h($qSearch) ?>">
                </label>

                <button type="submit">Lọc</button>
                <a class="reset" href="reviews.php" style="display:inline-block;padding:10px 12px;border-radius:8px;background:#ef4444;color:#fff;font-weight:700;text-decoration:none;">Reset</a>
            </form>
        </div>

        <div class="table-box">
            <?php if (empty($reviews)): ?>
                <div class="empty">Chưa có bình luận / đánh giá nào khớp.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:6%;">ID</th>
                            <th style="width:8%;">Ảnh</th>
                            <th style="width:20%;">Sản phẩm</th>
                            <th style="width:18%;">Người dùng</th>
                            <th style="width:10%;">Đánh giá</th>
                            <th>Bình luận</th>
                            <th style="width:12%;">Ngày</th>
                            <th style="width:10%;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reviews as $r): 
                            // chuẩn hóa ảnh sản phẩm
                            $imgFile = trim((string)($r['product_image'] ?? ''));
                            if ($imgFile === '') {
                                $imgSrc = '../uploads/no-image.png';
                            } else {
                                if (strpos($imgFile, 'uploads/') !== false || strpos($imgFile, '/uploads/') !== false) {
                                    $imgSrc = (strpos($imgFile, '../') === 0) ? $imgFile : ('../' . ltrim($imgFile, '/'));
                                } else {
                                    $imgSrc = '../uploads/' . str_replace(' ', '%20', $imgFile);
                                }
                            }
                        ?>
                            <tr>
                                <td><?= (int)$r['id'] ?></td>
                                <td>
                                    <img src="<?= h($imgSrc) ?>" alt="thumb" class="thumb" onerror="this.onerror=null;this.src='../uploads/no-image.png'">
                                </td>
                                <td>
                                    <div style="font-weight:700;"><?= h($r['product_name'] ?? '[Sản phẩm đã xóa]') ?></div>
                                    <div class="user-meta">ID sản phẩm: <?= (int)$r['product_id'] ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:700;"><?= h($r['username'] ?? '[Người dùng đã xóa]') ?></div>
                                    <div class="user-meta"><?= h($r['user_email'] ?? '') ?> • ID: <?= (int)$r['user_id'] ?></div>
                                </td>
                                <td>
                                    <div class="star"><?= str_repeat('★', max(0,(int)$r['rating'])) . str_repeat('☆', max(0,5-(int)$r['rating'])) ?></div>
                                    <div class="user-meta"><?= (int)$r['rating'] ?> / 5</div>
                                </td>
                                <td style="white-space:pre-wrap;"><?= h($r['comment']) ?></td>
                                <td><?= h($r['created_at']) ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <!-- link tới trang chi tiết review (dùng id review để xem) -->
                                        <a class="btn-small btn-view" href="review_detail.php?id=<?= (int)$r['product_id'] ?>" title="Chi tiết"><i class="fa fa-eye"></i><span>Chi tiết</span></a>

                                        <!-- form ẩn có class hide-form để JS bắt -->
                                        <form method="post" class="hide-form" style="display:inline;">
                                            <input type="hidden" name="hide_review" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="btn-small btn-hide" title="Ẩn"><i class="fa fa-eye-slash"></i><span>Ẩn</span></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- pagination -->
                <div class="pager" style="margin-top:12px;">
                    <?php
                    $baseUrl = 'reviews.php?';
                    $keep = [];
                    if ($productFilter) $keep[] = 'product=' . (int)$productFilter;
                    if ($ratingFilter)  $keep[] = 'rating=' . (int)$ratingFilter;
                    if ($qSearch !== '') $keep[] = 'q=' . urlencode($qSearch);
                    $baseKeep = $keep ? implode('&', $keep) . '&' : '';

                    if ($totalPages > 1) {
                        for ($i = 1; $i <= $totalPages; $i++) {
                            $cls = $i === $page ? 'active' : '';
                            echo '<a class="'.$cls.'" href="'.$baseUrl.$baseKeep.'page='.$i.'">'.$i.'</a> ';
                        }
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<script>
// SweetAlert2 confirmation for hiding (intercepts forms with class "hide-form")
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('form.hide-form').forEach(function(form){
        form.addEventListener('submit', function(e){
            e.preventDefault();
            const idInput = form.querySelector('input[name="hide_review"]');
            const id = idInput ? idInput.value : '';
            Swal.fire({
                title: 'Ẩn bình luận?',
                html: id ? 'Bạn có chắc muốn ẩn bình luận #' + id + '?<br><small>(Bạn có thể hiện lại trực tiếp trong DB)</small>' : 'Bạn có chắc muốn ẩn bình luận này?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'OK, ẩn',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>
</body>
</html>
