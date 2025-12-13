<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

// Bắt buộc đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* ============================
   XÓA KHỎI YÊU THÍCH
============================ */
if (isset($_GET['remove']) && ctype_digit($_GET['remove'])) {
    $productId = (int)$_GET['remove'];

    $del = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $del->execute([$_SESSION['user_id'], $productId]);

    // Lưu thông báo toast
    $_SESSION['toast'] = [
        'msg'  => 'Đã xóa khỏi danh sách yêu thích!',
        'type' => 'success'
    ];

    header("Location: withlist.php");
    exit;
}

/* ============================
   LẤY DANH SÁCH YÊU THÍCH
   - Lấy thêm ảnh (product_images)
   - Lấy thêm giá (product_variants)
============================ */
$sql = "
    SELECT 
        p.*,
        c.name AS cat_name,
        (
            SELECT pi.image_url
            FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.id ASC
            LIMIT 1
        ) AS image,
        (
            SELECT MIN(v.price)
            FROM product_variants v
            WHERE v.product_id = p.id
        ) AS base_price,
        (
            SELECT MIN(v.price_reduced)
            FROM product_variants v
            WHERE v.product_id = p.id
              AND v.price_reduced IS NOT NULL
              AND v.price_reduced > 0
        ) AS reduced_price
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<?php
// Hiển thị toast nếu có
if (!empty($_SESSION['toast'])):
    $t = $_SESSION['toast'];
    unset($_SESSION['toast']);
?>
<script>
    if (typeof showToast === 'function') {
        showToast(<?= json_encode($t['msg']) ?>, <?= json_encode($t['type']) ?>);
    } else {
        alert(<?= json_encode($t['msg']) ?>);
    }
</script>
<?php endif; ?>

<div class="container my-5">
    <h2 class="text-center mb-4">
        <i class="fas fa-heart text-danger"></i> Danh sách yêu thích
    </h2>

    <div class="row g-4">
        <?php if (empty($wishlist)): ?>
            <div class="col-12">
                <p class="text-center text-muted mb-0">
                    Bạn chưa yêu thích sản phẩm nào.
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($wishlist as $p): ?>
                <?php
                    // ===== TÍNH GIÁ CUỐI =====
                    $basePriceFromVariant    = (int)($p['base_price'] ?? 0);
                    $reducedPriceFromVariant = (int)($p['reduced_price'] ?? 0);
                    $discountPct             = (float)($p['discount_percent'] ?? 0);
                    $hasDiscount             = false;

                    if ($reducedPriceFromVariant > 0 && $reducedPriceFromVariant < $basePriceFromVariant) {
                        // Có price_reduced trong variants
                        $finalPrice = $reducedPriceFromVariant;
                        $basePrice  = $basePriceFromVariant;
                        $hasDiscount = true;
                    } elseif ($discountPct > 0 && $basePriceFromVariant > 0) {
                        // Giảm theo % từ bảng products
                        $basePrice  = $basePriceFromVariant;
                        $finalPrice = (int)round($basePrice * (1 - $discountPct / 100));
                        $hasDiscount = true;
                    } else {
                        // Không khuyến mãi
                        $basePrice  = $basePriceFromVariant;
                        $finalPrice = $basePriceFromVariant;
                    }

                    if ($finalPrice < 0) {
                        $finalPrice = 0;
                    }

                    // ===== ẢNH SẢN PHẨM =====
                    $imgFile = !empty($p['image']) ? $p['image'] : 'no-image.png';
                    $imgSrc  = upload($imgFile);
                ?>

                <div class="col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm position-relative">
                        <!-- Link tới trang chi tiết sản phẩm -->
                        <a href="product-detail.php?id=<?= (int)$p['id'] ?>">
                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                 class="card-img-top"
                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                 style="height:220px;object-fit:cover;">
                        </a>

                        <!-- Nút bỏ yêu thích (xóa khỏi wishlist) -->
                       <!-- Nút bỏ yêu thích (xóa khỏi wishlist, KHÔNG hiện popup localhost nữa) -->
<a href="withlist.php?remove=<?= (int)$p['id'] ?>"
   class="wishlist-remove">
    <i class="fas fa-heart"></i>
</a>


                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title mb-1">
                                <?= htmlspecialchars($p['name']) ?>
                            </h6>
                            <p class="text-muted small mb-2">
                                <?= htmlspecialchars($p['cat_name'] ?? 'Danh mục khác') ?>
                            </p>

                            <div class="mt-auto">
                                <div class="mb-2">
                                    <span class="fw-bold text-danger">
                                        <?= number_format($finalPrice, 0, '', '.') ?>₫
                                    </span>

                                    <?php if ($hasDiscount && $basePrice > 0 && $basePrice > $finalPrice): ?>
                                        <span class="text-muted text-decoration-line-through small ms-1">
                                            <?= number_format($basePrice, 0, '', '.') ?>₫
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Xem chi tiết -->
                                <a href="product-detail.php?id=<?= (int)$p['id'] ?>"
                                   class="btn btn-outline-danger btn-sm w-100">
                                    Xem chi tiết
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.card {
    transition: transform .2s ease, box-shadow .2s ease;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}

/* Nút trái tim xóa wishlist */
.wishlist-remove {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid #ff4d4d;
    color: #ff4d4d;
    transition: all 0.25s ease;
    cursor: pointer;
    text-decoration: none;
}
.wishlist-remove i {
    font-size: 20px;
}
.wishlist-remove:hover {
    background: #ff4d4d;
    color: #fff;
    transform: scale(1.1);
    box-shadow: 0 0 12px rgba(255,0,0,0.4);
}
</style>

<?php include '../includes/footer.php'; ?>
