<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// === HÀM CHUYỂN HƯỚNG (phòng khi chưa có trong functions.php) ===
if (!function_exists('redirect')) {
    function redirect($url)
    {
        header("Location: $url");
        exit;
    }
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('index.php');

// === LẤY SẢN PHẨM ===
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name,
           COALESCE(p.discounted_price, p.price * (1 - COALESCE(p.discount_percent, 0)/100)) as final_price
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ?
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['error'] = "Sản phẩm không tồn tại!";
    redirect('index.php');
}

// === TÍNH GIÁ & GIẢM GIÁ ===
$price = (float)($product['price'] ?? 0);
$discount_percent = (float)($product['discount_percent'] ?? 0);
$final_price = $discount_percent > 0 ? round($price * (1 - $discount_percent / 100)) : $price;

// === ĐÁNH GIÁ TRUNG BÌNH ===
$avg_stmt = $pdo->prepare("SELECT AVG(rating) as avg FROM reviews WHERE product_id = ?");
$avg_stmt->execute([$id]);
$avg_rating = round((float)$avg_stmt->fetchColumn(), 1);

// === DANH SÁCH ĐÁNH GIÁ ===
$reviews_stmt = $pdo->prepare("
    SELECT r.*, u.username 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = ? 
    ORDER BY r.created_at DESC
");
$reviews_stmt->execute([$id]);
$reviews = $reviews_stmt->fetchAll();

// === WISHLIST ===
$is_wishlisted = false;
if (isset($_SESSION['user_id'])) {
    $wish_stmt = $pdo->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
    $wish_stmt->execute([$_SESSION['user_id'], $id]);
    $is_wishlisted = (bool)$wish_stmt->fetch();
}

// === XỬ LÝ ĐÁNH GIÁ ===
if ($_POST['submit_review'] ?? false) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = "Vui lòng đăng nhập để đánh giá!";
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $_SESSION['error'] = "Đánh giá phải từ 1 đến 5 sao!";
        } elseif (empty($comment)) {
            $_SESSION['error'] = "Vui lòng nhập nội dung đánh giá!";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO reviews (product_id, user_id, rating, comment) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE rating = ?, comment = ?, created_at = NOW()
                ");
                $stmt->execute([$id, $_SESSION['user_id'], $rating, $comment, $rating, $comment]);
                $_SESSION['success'] = "Cảm ơn bạn đã đánh giá!";
            } catch (Exception $e) {
                $_SESSION['error'] = "Lỗi: " . $e->getMessage();
            }
        }
    }
    redirect("product.php?id=$id");
}

// === XỬ LÝ WISHLIST ===
if (isset($_GET['wishlist'])) {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
    $action = $_GET['wishlist'];
    if ($action === 'add') {
        $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)")
            ->execute([$_SESSION['user_id'], $id]);
    } elseif ($action === 'remove') {
        $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")
            ->execute([$_SESSION['user_id'], $id]);
    }
    redirect("product.php?id=$id");
}
?>

<?php include '../includes/header.php'; ?>

<?php
// Dữ liệu mẫu cho size & màu (bạn sẽ thay bằng dữ liệu thực khi có)
$sizes = ['S', 'M', 'L', 'XL'];
$colors = [
    ['name' => 'Be', 'hex' => '#f5e6ca'],
    ['name' => 'Trắng', 'hex' => '#ffffff'],
    ['name' => 'Đen', 'hex' => '#000000'],
];
?>

<div class="container product-page my-5">
    <!-- Thông báo -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row g-5">
        <!-- Ảnh sản phẩm -->
        <div class="col-lg-6">
            <div class="product-gallery position-relative">
                <div class="main-image mb-3 position-relative">
                    <img id="mainImage" src="<?= upload($product['image']) ?>"
                         alt="<?= htmlspecialchars($product['name']) ?>"
                         class="img-fluid rounded-4 shadow main-img">
                    <?php if ($discount_percent > 0): ?>
                        <span class="discount-badge">-<?= $discount_percent ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="thumb-list d-flex gap-2">
                    <!-- Dùng cùng ảnh làm mẫu (nếu bạn có nhiều ảnh, loop ở đây) -->
                    <img src="<?= upload($product['image']) ?>" class="thumb-img active" data-src="<?= upload($product['image']) ?>">
                    <img src="<?= upload($product['image']) ?>" class="thumb-img" data-src="<?= upload($product['image']) ?>">
                    <img src="<?= upload($product['image']) ?>" class="thumb-img" data-src="<?= upload($product['image']) ?>">
                </div>
            </div>
        </div>

        <!-- Thông tin sản phẩm -->
        <div class="col-lg-6">
            <h1 class="product-title mb-2"><?= htmlspecialchars($product['name']) ?></h1>
            <div class="product-meta text-muted mb-3">
                <span><i class="fa-regular fa-folder"></i> <?= htmlspecialchars($product['cat_name'] ?? 'Chưa phân loại') ?></span>
            </div>

            <!-- Sao đánh giá -->
            <div class="product-rating mb-3 d-flex align-items-center">
                <div class="me-3">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star <?= $i <= $avg_rating ? 'text-warning' : 'text-muted' ?>"></i>
                    <?php endfor; ?>
                </div>
                <div class="small text-muted">(<?= $avg_rating ?>/5 - <?= count($reviews) ?> đánh giá)</div>
            </div>

            <!-- Giá -->
            <div class="product-price mb-4">
                <?php if ($discount_percent > 0): ?>
                    <div class="d-flex align-items-baseline gap-3">
                        <span class="price-final"><?= number_format($final_price) ?>₫</span>
                        <span class="price-old"><?= number_format($price) ?>₫</span>
                    </div>
                <?php else: ?>
                    <span class="price-final"><?= number_format($price) ?>₫</span>
                <?php endif; ?>
            </div>

            <!-- Mô tả ngắn -->
            <div class="product-short-desc mb-4">
                <p class="lead text-dark"><?= nl2br(htmlspecialchars($product['description'] ?? 'Không có mô tả.')) ?></p>
            </div>

            <!-- Chọn size -->
            <div class="product-option mb-3">
                <label class="fw-semibold mb-2 d-block">Kích thước:</label>
                <div class="d-flex flex-wrap gap-2 sizes-wrap" role="radiogroup" aria-label="Kích thước">
                    <?php foreach ($sizes as $s): ?>
                        <button type="button" class="btn btn-outline-dark btn-size" data-size="<?= $s ?>"><?= $s ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Chọn màu -->
            <div class="product-option mb-4">
                <label class="fw-semibold mb-2 d-block">Màu sắc:</label>
                <div class="d-flex flex-wrap gap-2 colors-wrap" role="radiogroup" aria-label="Màu sắc">
                    <?php foreach ($colors as $c): ?>
                        <button type="button" class="color-circle" title="<?= htmlspecialchars($c['name']) ?>"
                                data-color="<?= htmlspecialchars($c['name']) ?>"
                                style="background: <?= $c['hex'] ?>; border: 1px solid rgba(0,0,0,0.1)"></button>
                    <?php endforeach; ?>
                </div>
            </div>
<!-- Chọn số lượng -->
<div class="product-quantity mb-3 d-flex align-items-center gap-2">
    <label class="fw-semibold mb-0">Số lượng:</label>
    <input type="number" name="quantity" class="form-control text-center" value="1" min="1" style="width:80px;">
</div>

            <!-- Nút hành động (form thêm vào giỏ) -->
            <div class="d-flex flex-wrap gap-3 mb-4">
                <form id="addToCartForm" method="POST" action="cart.php" class="d-inline">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $id ?>">
                    <input type="hidden" name="selected_size" id="selected_size" value="">
                    <input type="hidden" name="selected_color" id="selected_color" value="">
                    <button type="submit" class="btn btn-dark btn-lg rounded-pill px-5">
                        <i class="fa-solid fa-cart-plus me-2"></i> Thêm vào giỏ hàng
                    </button>
                </form>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="?id=<?= $id ?>&wishlist=<?= $is_wishlisted ? 'remove' : 'add' ?>#wishlist"
                       class="btn btn-outline-danger btn-lg rounded-pill px-5">
                        <i class="fa-regular fa-heart me-2"></i>
                        <?= $is_wishlisted ? 'Bỏ yêu thích' : 'Yêu thích' ?>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-danger btn-lg rounded-pill px-5">
                        <i class="fa-regular fa-heart me-2"></i> Yêu thích
                    </a>
                <?php endif; ?>
            </div>

            <!-- Chính sách -->
            <div class="border-top pt-3 small text-muted">
                <p class="mb-1"><i class="fa-solid fa-truck-fast text-success"></i> Giao hàng toàn quốc - Miễn phí từ 500k</p>
                <p class="mb-0"><i class="fa-solid fa-rotate-left text-primary"></i> Đổi trả trong 7 ngày</p>
            </div>
        </div>
    </div>

    <!-- Tabs mô tả và đánh giá -->
    <div class="product-tabs mt-5">
        <ul class="nav nav-tabs" id="productTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="desc-tab" data-bs-toggle="tab" data-bs-target="#desc" type="button">Mô tả chi tiết</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="review-tab" data-bs-toggle="tab" data-bs-target="#review" type="button">Đánh giá (<?= count($reviews) ?>)</button>
            </li>
        </ul>
        <div class="tab-content p-4 border border-top-0 bg-white rounded-bottom">
            <div class="tab-pane fade show active" id="desc" role="tabpanel">
                <?= nl2br(htmlspecialchars($product['description'] ?? 'Không có mô tả chi tiết.')) ?>
            </div>

            <div class="tab-pane fade" id="review" role="tabpanel">
                <div class="mt-3">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Chọn số sao:</label>
                                <div class="star-rating d-flex gap-2">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" class="d-none">
                                        <label for="star<?= $i ?>" class="star-label">★</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <textarea name="comment" class="form-control" rows="3" placeholder="Chia sẻ cảm nhận..." required></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn btn-dark">
                                <i class="fa-solid fa-paper-plane"></i> Gửi đánh giá
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted">Vui lòng <a href="login.php">đăng nhập</a> để đánh giá sản phẩm.</p>
                    <?php endif; ?>

                    <div class="review-list mt-4">
                        <?php if (empty($reviews)): ?>
                            <p class="text-muted">Chưa có đánh giá nào.</p>
                        <?php else: foreach ($reviews as $r): ?>
                            <div class="border-bottom py-3">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($r['username']) ?></strong>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></small>
                                </div>
                                <div class="text-warning mb-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-solid fa-star<?= $i <= $r['rating'] ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($r['comment'])) ?></p>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles (giao diện giống Rubies) -->
<style>
.product-title { font-size: 2.2rem; font-weight: 600; }
.price-final { color: #d62b70; font-size: 2rem; font-weight: 700; }
.price-old { color: #d62b70; text-decoration: line-through; margin-left: 8px; font-size: 1rem; }
.product-gallery .main-img { width: 100%; height: 520px; object-fit: cover; border-radius: 1rem; }
.thumb-img { width: 90px; height: 90px; border-radius: 0.6rem; object-fit: cover; cursor: pointer; border: 2px solid transparent; }
.thumb-img.active, .thumb-img:hover { border-color: #d62b70; }
.discount-badge { position: absolute; top: 18px; left: 18px; background: #d62b70; color: white; padding: .35rem .7rem; border-radius: .5rem; font-weight: 700; }
.btn-size { border-radius: 50px; min-width: 55px; padding: .4rem .9rem; }
.color-circle { width: 36px; height: 36px; border-radius: 50%; border: 1px solid #ddd; cursor: pointer; display: inline-block; }
.color-circle.active { box-shadow: 0 0 0 3px #d62b70; transform: translateY(-2px); }
.star-label { font-size: 1.6rem; color: #ddd; cursor: pointer; }
.star-rating input:checked ~ label, .star-label:hover, .star-label:hover ~ .star-label { color: #f7c000; }
.nav-tabs .nav-link { border: none; color: #d62b70; font-weight: 600; }
.nav-tabs .nav-link.active { border-bottom: 3px solid #d62b70; color: #d62b70; }
@media (max-width: 991px) {
    .product-gallery .main-img { height: 380px; }
    .product-title { font-size: 1.6rem; }
    .price-final { font-size: 1.6rem; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const decreaseBtn = document.querySelector('.btn-decrease');
    const increaseBtn = document.querySelector('.btn-increase');
    const quantityInput = document.querySelector('input[name="quantity"]');

    decreaseBtn.addEventListener('click', function () {
        let val = parseInt(quantityInput.value) || 1;
        if(val > 1) quantityInput.value = val - 1;
    });

    increaseBtn.addEventListener('click', function () {
        let val = parseInt(quantityInput.value) || 1;
        quantityInput.value = val + 1;
    });
});
</script>


<!-- JS: thumbnail click, chọn size/color -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Thumbnail -> thay ảnh chính
    document.querySelectorAll('.thumb-img').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            document.querySelectorAll('.thumb-img').forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
            const src = thumb.getAttribute('data-src') || thumb.src;
            const main = document.getElementById('mainImage');
            if (main) main.src = src;
        });
    });

    // Size selection (toggle active, set hidden input)
    const sizeButtons = document.querySelectorAll('.btn-size');
    const sizeInput = document.getElementById('selected_size');
    sizeButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            sizeButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (sizeInput) sizeInput.value = btn.getAttribute('data-size') || btn.textContent.trim();
        });
    });

    // Color selection
    const colorButtons = document.querySelectorAll('.color-circle');
    const colorInput = document.getElementById('selected_color');
    colorButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            colorButtons.forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            if (colorInput) colorInput.value = btn.getAttribute('data-color') || btn.title;
        });
    });

    // simple star rating UX: clicking label checks radio
    document.querySelectorAll('.star-label').forEach(function(lbl) {
        lbl.addEventListener('click', function() {
            const forId = lbl.getAttribute('for');
            const el = document.getElementById(forId);
            if (el) el.checked = true;
            // color labels appropriately
            document.querySelectorAll('.star-label').forEach(l => l.style.color = '#ddd');
            lbl.style.color = '#f7c000';
            // color previous labels too
            let prev = lbl.previousElementSibling;
            while(prev) {
                if (prev.classList.contains('star-label')) prev.style.color = '#f7c000';
                prev = prev.previousElementSibling;
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
