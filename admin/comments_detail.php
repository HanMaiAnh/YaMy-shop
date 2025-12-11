<?php
session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) session_start();

// check tk admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../view/login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Chấp nhận ID sản phẩm (id) hoặc ID đánh giá và chuyển đổi thành ID sản phẩm
$productId = null;
if (isset($_GET['review_id']) && is_numeric($_GET['review_id'])) {
    $rid = (int)$_GET['review_id'];
    $stmt = $pdo->prepare("SELECT product_id FROM comments WHERE id = :rid LIMIT 1");
    $stmt->execute([':rid' => $rid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $productId = (int)$row['product_id'];
}
if ($productId === null) {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header("Location: comments.php");
        exit;
    }
    $productId = (int)$_GET['id'];
}

try {
    // Nhận thông tin sản phẩm + một hình ảnh
    $stmtP = $pdo->prepare("
        SELECT p.id, p.name, p.description, c.name AS category_name,
               (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_url
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.id = :id
        LIMIT 1
    ");
    $stmtP->execute([':id' => $productId]);
    $product = $stmtP->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        echo "<p style='color:red;text-align:center;margin-top:50px;'>Không tìm thấy sản phẩm.</p>";
        exit;
    }

    // lấy bảng comments
    $stmtR = $pdo->prepare("
        SELECT r.id, r.user_id, r.rating, r.comment, r.date_comment,
               u.username, u.email
        FROM comments r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.product_id = :pid
          AND COALESCE(r.is_hidden, 0) = 0
        ORDER BY r.date_comment DESC
        LIMIT 1000
    ");
    $stmtR->execute([':pid' => $productId]);
    $comments = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    // aggregates
    $stmtAgg = $pdo->prepare("
        SELECT COUNT(*) AS cnt, ROUND(AVG(rating),2) AS avg_rating
        FROM comments
        WHERE product_id = :pid
          AND COALESCE(is_hidden,0) = 0
    ");
    $stmtAgg->execute([':pid' => $productId]);
    $agg = $stmtAgg->fetch(PDO::FETCH_ASSOC);
    $totalComments = (int)($agg['cnt'] ?? 0);
    $avgRating = $agg['avg_rating'] !== null ? (float)$agg['avg_rating'] : 0.0;

} catch (PDOException $e) {
    echo "<p style='color:red;text-align:center;margin-top:50px;'>Lỗi DB: " . h($e->getMessage()) . "</p>";
    exit;
}

// image path normalization
$imgFile = trim((string)($product['image_url'] ?? ''));
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
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Chi tiết bình luận sản phẩm: <?= h($product['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
:root{--bg-main:#f7f5ff;--card-bg:#fff;--text:#222;--muted:#777;--accent:#8E5DF5;}
body{display:flex;background:var(--bg-main);color:var(--text);}
/* sidebar */
.sidebar{width:260px;background:#fff;height:100vh;padding:30px 20px;position:fixed;border-right:1px solid #ddd;}
.sidebar h3{font-size:22px;font-weight:700;margin-bottom:25px;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:12px;color:#333;text-decoration:none;border-radius:8px;margin-bottom:8px;transition:.25s;font-weight:500;font-size:15px;}
.sidebar a:hover{background:#f2e8ff;color:var(--accent);transform:translateX(4px);}
.content{margin-left:280px;padding:30px;width:100%;}
.page-title{font-size:26px;font-weight:700;margin-bottom:10px;}
.card{background:var(--card-bg);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 6px 18px rgba(0,0,0,0.04);}
.product-top{display:flex;gap:18px;align-items:center;}
.product-img{width:120px;height:120px;object-fit:cover;border-radius:12px;border:1px solid #eee;}
.product-info h2{font-size:18px;margin-bottom:6px;}
.small-muted{font-size:13px;color:var(--muted);margin-top:6px;}
.meta-row{display:flex;gap:12px;align-items:center;margin-top:8px;}
.meta-badge {
    background: #f0e8ff;
    color: #f5b301; /* vàng */
    padding: 6px 10px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 13px;
}


/* review list */
.review-list{margin-top:12px;}
.review-item{display:flex;gap:12px;padding:12px;border-bottom:1px solid #f1f1f1;align-items:flex-start;}
.reviewer-avatar{width:44px;height:44px;border-radius:8px;background:#f3f3f3;display:inline-flex;align-items:center;justify-content:center;font-weight:700;color:#666;}
.review-body{flex:1;}
.review-header{display:flex;justify-content:space-between;align-items:center;gap:12px;}
.review-user{font-weight:700;}
.review-meta{font-size:13px;color:var(--muted);}
.stars{color:#f5b301;font-weight:700;margin-right:8px;}
.review-text{margin-top:8px;white-space:pre-wrap;color:#333;}
.actions{display:flex;gap:8px;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;border:none;cursor:pointer;font-weight:700;}
.btn-back{background:#f0e8ff;color:var(--accent);text-decoration:none;padding:8px 12px;border-radius:999px;display:inline-flex;align-items:center;gap:8px;margin-bottom:12px;}
.btn-hide{background:#ffb020;color:#111;}
.empty{padding:18px;text-align:center;color:var(--muted);font-size:15px;}
</style>
</head>
<body>
    <div class="sidebar">
        <h3>YaMy Admin</h3>
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
        <a href="comments.php" class="btn-back"><i class="fa fa-arrow-left"></i> Quay lại</a>
        <h1 class="page-title">Chi tiết bình luận — sản phẩm</h1>

        <div class="card">
            <div class="product-top">
                <img src="<?= h($imgSrc) ?>" alt="Ảnh" class="product-img" onerror="this.onerror=null;this.src='../uploads/no-image.png'">
                <div class="product-info">
                    <h2><?= h($product['name']) ?></h2>
                    <div class="small-muted">ID sản phẩm: <?= (int)$product['id'] ?><?= isset($product['category_name']) ? ' • ' . h($product['category_name']) : '' ?></div>
                    <div class="meta-row">
                        <div class="meta-badge"><?= number_format($avgRating, 2) ?> ★ (<?= $totalComments ?>)</div>
                        <div class="small-muted"><?= ($product['description']) ? h(mb_substr($product['description'],0,180)) : '' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Tất cả bình luận (<?= $totalComments ?>)</h2></div>

            <?php if (empty($comments)): ?>
                <div class="empty">Chưa có bình luận / đánh giá nào cho sản phẩm này.</div>
            <?php else: ?>
                <div class="review-list">
                    <?php foreach ($comments as $rv): ?>
                        <div class="review-item">
                            <div class="reviewer-avatar"><?= h(mb_substr($rv['username'] ?: 'U', 0, 1)) ?></div>
                            <div class="review-body">
                                <div class="review-header">
                                    <div>
                                        <div class="review-user"><?= h($rv['username'] ?? '[Người dùng đã xóa]') ?></div>
                                        <div class="review-meta"><?= h($rv['user_email'] ?? '') ?> • ID: <?= (int)$rv['user_id'] ?></div>
                                    </div>
                                    <div class="review-right">
                                        <div class="stars"><?= str_repeat('★', max(0,(int)$rv['rating'])) . str_repeat('☆', max(0,5-(int)$rv['rating'])) ?></div>
                                        <div class="review-meta"><?= date('Y-m-d H:i:s', strtotime($rv['date_comment'])) ?></div>
                                    </div>
                                </div>

                                <div class="review-text"><?= nl2br(h($rv['comment'])) ?></div>

                                <div style="margin-top:8px;">
                                    <form method="post" action="comments.php" style="display:inline;">
                                        <!-- ĐỔI TÊN INPUT: hide_review -> hide_comments -->
                                        <input type="hidden" name="hide_comments" value="<?= (int)$rv['id'] ?>">
                                        <button type="submit" class="btn btn-hide" onclick="return confirm('Bạn có chắc muốn ẩn bình luận này?')">
                                            <i class="fa fa-eye-slash"></i> Ẩn
                                        </button>
                                    </form>
                                </div>
                                
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
