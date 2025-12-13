<?php
// view/product-detail.php
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Helper redirect nếu chưa có
// =============================
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

// =============================
// Lấy ID sản phẩm từ GET
// =============================
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($id <= 0) {
    $_SESSION['error'] = "Sản phẩm không hợp lệ.";
    redirect('index.php');
}

// =============================
// Lấy thông tin sản phẩm và category
// =============================
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS cat_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['error'] = "Sản phẩm không tồn tại!";
    redirect('index.php');
}

// =============================
// KHÔNG CHO GỬI ĐÁNH GIÁ NỮA (CHỈ XEM)
// =============================

// =============================
// Xử lý Wishlist (GET: add/remove)
// =============================
if (isset($_GET['wishlist'])) {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
    $action = $_GET['wishlist'];
    if ($action === 'add') {
        $stmtW = $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmtW->execute([$_SESSION['user_id'], $id]);
    } elseif ($action === 'remove') {
        $stmtW = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmtW->execute([$_SESSION['user_id'], $id]);
    }
    redirect("product-detail.php?id={$id}");
}

// =============================
// Lấy các biến thể sản phẩm (size/color)
// =============================
$stmtVar = $pdo->prepare("
    SELECT 
        v.*,
        s.name  AS size_name,
        col.name AS color_name
    FROM product_variants v
    LEFT JOIN sizes  s   ON v.size_id  = s.id
    LEFT JOIN colors col ON v.color_id = col.id
    WHERE v.product_id = ?
    ORDER BY s.id ASC, v.id ASC
");
$stmtVar->execute([$id]);
$variants = $stmtVar->fetchAll(PDO::FETCH_ASSOC);

// fallback nếu không có variant
if (empty($variants)) {
    $variants = [[
        'size_id'       => 0,
        'size_name'     => '',
        'color_name'    => '',
        'price'         => (float)($product['price'] ?? 0),
        'price_reduced' => 0,
        'quantity'      => 0,
    ]];
}

// build maps cho JS và hiển thị
$sizeMap   = [];   // size => [base_price, final_price, discount_pct, quantity]
$sizesInfo = [];   // size => ['has_stock'=>bool]
$defaultSize       = null;
$currentColorName  = 'Không xác định';

$discount_percent_product = (float)($product['discount_percent'] ?? 0);

// loop variants -> tính giá final, discount, quantity
foreach ($variants as $v) {
    $sizeName = trim((string)($v['size_name'] ?? ''));
    $base     = (float)($v['price'] ?? 0);
    $reduced  = (float)($v['price_reduced'] ?? 0);
    $qty      = (int)($v['quantity'] ?? 0);
    $color    = trim((string)($v['color_name'] ?? ''));

    if ($currentColorName === 'Không xác định' && $color !== '') {
        $currentColorName = $color;
    }

    if ($reduced > 0 && $reduced < $base) {
        $final   = $reduced;
        $discPct = (int)round(100 - ($final / max($base, 1) * 100));
    } elseif ($discount_percent_product > 0 && $base > 0) {
        $final   = round($base * (1 - $discount_percent_product / 100));
        $discPct = (int)$discount_percent_product;
    } else {
        $final   = $base;
        $discPct = 0;
    }

    if ($sizeName !== '') {
        $sizeMap[$sizeName] = [
            'base_price'   => (int)$base,
            'final_price'  => (int)$final,
            'discount_pct' => (int)$discPct,
            'quantity'     => $qty,
        ];

        $sizesInfo[$sizeName] = ['has_stock' => $qty > 0];

        if ($defaultSize === null) {
            $defaultSize = $sizeName;
        } elseif ($qty > 0 && (!isset($sizeMap[$defaultSize]) || $sizeMap[$defaultSize]['quantity'] <= 0)) {
            $defaultSize = $sizeName;
        }
    }
}

// nếu chưa có defaultSize nhưng vẫn có sizeMap
if ($defaultSize === null && !empty($sizeMap)) {
    $keys        = array_keys($sizeMap);
    $defaultSize = $keys[0];
}

if (!empty($sizeMap)) {
    $defaultVariant = $sizeMap[$defaultSize] ?? [
        'base_price'    => 0,
        'final_price'   => 0,
        'discount_pct'  => 0,
        'quantity'      => 0,
    ];

    $price            = $defaultVariant['base_price'];
    $final_price      = $defaultVariant['final_price'];
    $discount_percent = $defaultVariant['discount_pct'];
    $has_discount     = $final_price < $price && $discount_percent > 0;
    $defaultStock     = (int)($defaultVariant['quantity'] ?? 0);
} else {
    $firstVar = $variants[0] ?? [];
    $base     = (float)($firstVar['price'] ?? ($product['price'] ?? 0));
    $reduced  = (float)($firstVar['price_reduced'] ?? 0);

    if ($reduced > 0 && $reduced < $base) {
        $final   = $reduced;
        $discPct = (int)round(100 - ($final / max($base, 1) * 100));
    } elseif ($discount_percent_product > 0 && $base > 0) {
        $final   = round($base * (1 - $discount_percent_product / 100));
        $discPct = (int)$discount_percent_product;
    } else {
        $final   = $base;
        $discPct = 0;
    }

    $price            = $base;
    $final_price      = $final;
    $discount_percent = $discPct;
    $has_discount     = $final_price < $price && $discount_percent > 0;
    $defaultStock     = (int)($firstVar['quantity'] ?? 0);
    $defaultSize      = '';
}

// =============================
// LẤY DANH SÁCH MÀU CỦA RIÊNG SẢN PHẨM (từ product_variants)
// =============================
$productColors = [];   // mảng [color_name => true]
$colorOptions  = [];   // mảng list color name hiển thị

foreach ($variants as $v) {
    $cname = trim((string)($v['color_name'] ?? ''));
    if ($cname !== '' && !isset($productColors[$cname])) {
        $productColors[$cname] = true;
        $colorOptions[] = $cname;
    }
}

// =============================
// Ảnh sản phẩm
// =============================
$stmtImg = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY id ASC");
$stmtImg->execute([$id]);
$imageRows = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

$images = [];
foreach ($imageRows as $row) {
    if (!empty($row['image_url'])) $images[] = $row['image_url'];
}
if (empty($images)) $images[] = 'placeholder-product.png';

// =============================
// Đánh giá
// =============================
$avg_stmt = $pdo->prepare("SELECT AVG(rating) as avg FROM comments WHERE product_id = ?");
$avg_stmt->execute([$id]);
$avg_rating = round((float)$avg_stmt->fetchColumn(), 1);

$comments_stmt = $pdo->prepare("
    SELECT c.*, u.username
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.product_id = ?
    ORDER BY c.date_comment DESC
    LIMIT 10
");
$comments_stmt->execute([$id]);
$comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================
// Kiểm tra wishlist của user
// =============================
$is_wishlisted = false;
if (isset($_SESSION['user_id'])) {
    $wish_stmt = $pdo->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1");
    $wish_stmt->execute([$_SESSION['user_id'], $id]);
    $is_wishlisted = (bool)$wish_stmt->fetchColumn();
}

// =============================
// Sản phẩm liên quan (cùng category)
// =============================
$related_products = [];
if (!empty($product['category_id'])) {
    $relStmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            MIN(CASE WHEN v.price_reduced > 0 AND v.price_reduced < v.price THEN v.price_reduced ELSE v.price END) AS min_price,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) AS image_url
        FROM products p
        JOIN product_variants v ON v.product_id = p.id
        WHERE p.category_id = ? AND p.id <> ?
        GROUP BY p.id, p.name
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $relStmt->execute([$product['category_id'], $id]);
    $related_products = $relStmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =============================
   MAP SIZE -> JS
============================= */
$sizeMapForJs = $sizeMap;

/* =============================
   INCLUDE HEADER + RENDER PAGE
============================= */
include '../includes/header.php';
?>

<script>
const SIZE_VARIANTS = <?= json_encode($sizeMapForJs, JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="container product-page my-5">
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row g-5">
        <!-- Images -->
        <div class="col-lg-6">
            <div class="product-gallery position-relative">
                <div class="main-image mb-3 position-relative">
                    <?php $mainImg = $images[0]; $mainUrl = upload($mainImg); ?>
                    <img id="mainImage" src="<?= htmlspecialchars($mainUrl) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="img-fluid rounded-4 shadow main-img">
                    <?php if ($has_discount && $discount_percent > 0): ?>
                        <span class="discount-badge" id="discount-badge">-<?= (int)$discount_percent ?>%</span>
                    <?php else: ?>
                        <span class="discount-badge d-none" id="discount-badge"></span>
                    <?php endif; ?>
                </div>

                <div class="thumb-list d-flex gap-2 justify-content-center" role="list">
                    <?php foreach ($images as $i => $img): $imgUrl = upload($img); ?>
                        <img src="<?= htmlspecialchars($imgUrl) ?>" class="thumb-img <?= $i === 0 ? 'active' : '' ?>" data-src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($product['name']) ?> - <?= $i+1 ?>" role="listitem" tabindex="0">
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Info -->
        <div class="col-lg-6">
            <div class="product-info-box p-3 p-md-4 rounded-3 shadow-sm bg-white">
                <h1 class="product-title mb-2 text-truncate">
                    <?= htmlspecialchars($product['name']) ?>
                </h1>

                <div class="d-flex flex-wrap align-items-center gap-3 mb-3 small text-muted">
                    <div class="product-meta d-flex align-items-center">
                        <i class="fa-regular fa-folder me-1"></i>
                        <span><?= htmlspecialchars($product['cat_name'] ?? 'Chưa phân loại') ?></span>
                    </div>

                    <div class="product-rating d-flex align-items-center">
                        <div class="me-2" aria-hidden="true">
                            <?php for ($i=1;$i<=5;$i++): ?>
                                <i class="fas fa-star <?= $i <= $avg_rating ? 'text-warning' : 'text-muted' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span>(<?= $avg_rating ?>/5 - <?= count($comments) ?> đánh giá)</span>
                    </div>
                </div>

                <!-- price -->
                <div class="product-price mb-3">
                    <?php if ($has_discount): ?>
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="price-final" id="price-final"><?= number_format($final_price) ?>₫</span>
                            <span class="price-old" id="price-old"><?= number_format($price) ?>₫</span>
                        </div>
                    <?php else: ?>
                        <span class="price-final" id="price-final"><?= number_format($final_price) ?>₫</span>
                        <span class="price-old d-none" id="price-old"></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($sizesInfo)): ?>
                <!-- sizes -->
                <div class="product-option mb-2">
                    <label class="fw-semibold mb-1 d-block small">Kích thước</label>
                    <div class="d-flex flex-wrap gap-2 sizes-wrap" role="radiogroup" aria-label="Chọn kích thước">
                        <?php foreach ($sizesInfo as $sname => $info):
                            $hasStock  = !empty($info['has_stock']);
                            $isDefault = ($sname === $defaultSize);
                            $cls = 'btn btn-outline-dark btn-size btn-sm' . ($isDefault ? ' active' : '') . ($hasStock ? '' : ' opacity-50');
                        ?>
                            <button type="button"
                                    class="<?= $cls ?>"
                                    data-size="<?= htmlspecialchars($sname) ?>"
                                    data-stock="<?= $hasStock ? '1' : '0' ?>"
                                    aria-pressed="<?= $isDefault ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($sname) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

               <!-- colors: chỉ hiển thị màu thật sự có trong product_variants -->
<?php if (!empty($colorOptions)): ?>
<div class="product-option mb-2">
    <label class="fw-semibold mb-1 d-block small">Màu sắc</label>
    <div class="d-flex flex-wrap gap-2 colors-wrap" role="radiogroup" aria-label="Chọn màu">
        <?php foreach ($colorOptions as $colorName):
            $isCurrent = (mb_strtolower($colorName, 'UTF-8') === mb_strtolower($currentColorName, 'UTF-8'));
            $cls       = 'btn btn-outline-dark btn-sm btn-color' . ($isCurrent ? ' active' : '');
        ?>
            <button type="button"
                    class="<?= $cls ?>"
                    data-color="<?= htmlspecialchars($colorName) ?>"
                    aria-pressed="<?= $isCurrent ? 'true' : 'false' ?>">
                <?= htmlspecialchars($colorName) ?>
            </button>
        <?php endforeach; ?>
    </div>
    <small class="text-muted d-block mt-1">
        Màu hiện tại: <strong id="current-color-label"><?= htmlspecialchars($currentColorName) ?></strong>
    </small>
</div>
<?php endif; ?>


                <!-- quantity + đã chọn -->
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <div class="product-quantity d-flex align-items-center gap-2">
                        <label for="quantityInput" class="fw-semibold mb-0 small">SL:</label>
                        <div class="input-group input-group-sm" style="width:120px;">
                            <button type="button" class="btn btn-outline-secondary btn-decrease">-</button>
                            <input id="quantityInput" type="number" name="quantity" class="form-control text-center" value="1" min="1" readonly>
                            <button type="button" class="btn btn-outline-secondary btn-increase">+</button>
                        </div>
                    </div>

                    <div class="selected-info">
                        <small class="text-muted">Đã chọn:
                            <span id="selected-info" class="text-danger fw-semibold">
                                <?= $defaultSize ? htmlspecialchars($defaultSize) . ' / ' : '' ?>
                                <?= htmlspecialchars($currentColorName) ?> - 1 cái
                            </span>
                        </small>
                    </div>

                    <div class="stock-info">
                        <small class="text-muted">Tồn kho:
                            <span id="stock-info"
                                  class="fw-semibold <?= $defaultStock > 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $defaultStock > 0
                                    ? ('Còn ' . $defaultStock . ' sản phẩm')
                                    : 'Hết hàng' ?>
                            </span>
                        </small>
                    </div>
                </div>

                <!-- buttons -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button id="addToCartBtn" type="button" class="btn btn-dark btn-sm rounded-pill px-4 position-relative">
                        <span class="btn-text"><i class="fa-solid fa-cart-plus me-2"></i> Thêm vào giỏ</span>
                        <span class="btn-loading d-none"><i class="fa-solid fa-spinner fa-spin me-2"></i> Đang thêm...</span>
                    </button>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="?id=<?= (int)$id ?>&wishlist=<?= $is_wishlisted ? 'remove' : 'add' ?>"
                           class="btn btn-outline-danger btn-sm rounded-pill px-4 wishlist-btn"
                           aria-pressed="<?= $is_wishlisted ? 'true' : 'false' ?>">
                            <i class="fa-<?= $is_wishlisted ? 'solid' : 'regular' ?> fa-heart me-1"></i>
                            <?= $is_wishlisted ? 'Bỏ yêu thích' : 'Yêu thích' ?>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-danger btn-sm rounded-pill px-4">
                            <i class="fa-regular fa-heart me-1"></i> Yêu thích
                        </a>
                    <?php endif; ?>
                </div>

                <!-- shipping -->
                <div class="border-top pt-2 small text-muted">
                    <p class="mb-1"><i class="fa-solid fa-truck-fast text-success me-1"></i> Giao hàng toàn quốc - Miễn phí từ 500k</p>
                    <p class="mb-0"><i class="fa-solid fa-rotate-left text-primary me-1"></i> Đổi trả trong 7 ngày</p>
                </div>

                <!-- hidden inputs -->
                <div id="cartData"
                     style="display:none"
                     data-product-id="<?= (int)$id ?>"
                     data-stock="<?= (int)$defaultStock ?>"></div>
                <input type="hidden" id="selected_size" value="<?= htmlspecialchars($defaultSize) ?>">
                <input type="hidden" id="selected_color" value="<?= htmlspecialchars($currentColorName) ?>">
            </div>
        </div>
    </div>
</div>

<!-- Tabs: mô tả, đánh giá -->
<div class="container mb-5">
    <ul class="nav nav-tabs" id="productTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-desc" data-bs-toggle="tab" data-bs-target="#pane-desc" type="button" role="tab">
                Mô tả chi tiết
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-review" data-bs-toggle="tab" data-bs-target="#pane-review" type="button" role="tab">
                Đánh giá (<?= count($comments) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content border-bottom border-start border-end p-4 bg-white rounded-bottom shadow-sm" id="productTabsContent">
        <!-- description -->
        <div class="tab-pane fade show active" id="pane-desc" role="tabpanel">
            <h5 class="mb-3">Thông tin chi tiết</h5>
            <p class="mb-0"><?= nl2br(htmlspecialchars($product['description'] ?? 'Không có mô tả chi tiết.')) ?></p>
        </div>

        <!-- comments: CHỈ XEM, KHÔNG FORM -->
        <div class="tab-pane fade" id="pane-review" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="border rounded p-3 h-100">
                        <h5 class="mb-3">Đánh giá trung bình</h5>
                        <div class="d-flex align-items-center mb-2">
                            <span class="display-6 fw-bold me-2"><?= $avg_rating ?></span>
                            <div>
                                <?php for ($i=1;$i<=5;$i++): ?>
                                    <i class="fas fa-star <?= $i <= $avg_rating ? 'text-warning' : 'text-muted' ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p class="text-muted mb-0"><?= count($comments) ?> đánh giá cho sản phẩm này.</p>
                    </div>
                </div>

                <div class="col-lg-7">
                    <h5 class="mb-3">Danh sách đánh giá</h5>
                    <div class="review-list mt-2">
                        <?php if (empty($comments)): ?>
                            <p class="text-muted">Chưa có đánh giá nào.</p>
                        <?php else: foreach ($comments as $r): ?>
                            <div class="border-bottom py-3">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($r['username'] ?? 'Khách') ?></strong>
                                    <small class="text-muted">
                                        <?= date('d/m/Y H:i', strtotime($r['date_comment'] ?? 'now')) ?>
                                    </small>
                                </div>
                                <div class="text-warning mb-1" aria-hidden="true">
                                    <?php for ($i=1;$i<=5;$i++): ?>
                                        <i class="fas fa-star <?= $i <= (int)($r['rating'] ?? 0) ? '' : 'text-muted' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($r['content'] ?? '')) ?></p>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sản phẩm liên quan (hiển thị luôn, không nằm trong tab) -->
<div class="container mb-5">
    <h4 class="mb-3">Sản phẩm liên quan</h4>

    <?php if (empty($related_products)): ?>
        <p class="text-muted mb-0">Chưa có sản phẩm liên quan.</p>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($related_products as $rp):
                $img       = $rp['image_url'] ?: 'placeholder-product.png';
                $imgUrl    = upload($img);
                $priceText = $rp['min_price'] ? number_format((int)$rp['min_price']) . '₫' : '';
            ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="product-detail.php?id=<?= (int)$rp['id'] ?>" class="text-decoration-none text-dark">
                        <div class="card h-100 border-0 shadow-sm related-card">
                            <img src="<?= htmlspecialchars($imgUrl) ?>"
                                 class="card-img-top related-img"
                                 alt="<?= htmlspecialchars($rp['name']) ?>">
                            <div class="card-body p-2">
                                <h6 class="card-title mb-1 text-truncate">
                                    <?= htmlspecialchars($rp['name']) ?>
                                </h6>
                                <?php if ($priceText): ?>
                                    <div class="text-danger fw-semibold small"><?= $priceText ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.product-title { font-size: 2.2rem; font-weight: 600; }

.product-page {
    margin-top: 2rem;
    margin-bottom: 2rem;
}

.product-info-box {
    border-radius: 0.9rem;
}

.product-title {
    font-size: 1.5rem;
    font-weight: 600;
}

/* giá */
.price-final  {
    color: #d62b70;
    font-size: 1.6rem;
    font-weight: 700;
}
.price-old {
    color: #999;
    text-decoration: line-through;
    font-size: 0.9rem;
}

/* ảnh */
.main-img {
    width: 100%;
    height: 420px;
    object-fit: cover;
    border-radius: 0.9rem;
}
.thumb-img {
    width: 70px;
    height: 70px;
    border-radius: 0.5rem;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid transparent;
}
.thumb-img.active,
.thumb-img:hover {
    border-color: #d62b70;
}

/* badge giảm giá */
.discount-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: #d62b70;
    color: #fff;
    padding: .25rem .6rem;
    border-radius: .5rem;
    font-weight: 700;
    font-size: 0.8rem;
}

/* size + button */
.btn-size {
    border-radius: 999px;
    min-width: 48px;
    padding: .25rem .6rem;
    font-size: 0.85rem;
}
.btn-size.active {
    background: #d62b70;
    color: #fff;
    border-color: #d62b70;
}

/* card liên quan */
.related-card {
    border-radius: 0.8rem;
}
.related-img {
    height: 170px;
    object-fit: cover;
    border-top-left-radius: 0.8rem;
    border-top-right-radius: 0.8rem;
}

/* mô tả ngắn 3 dòng */
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@media (max-width: 991px) {
    .main-img { height: 320px; }
    .product-title { font-size: 1.3rem; }
    .price-final { font-size: 1.4rem; }
}
</style>

<!-- Client JS -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const quantityInput    = document.querySelector('#quantityInput');
    const sizeInputHidden  = document.getElementById('selected_size');
    const colorInputHidden = document.getElementById('selected_color');
    const selectedInfo     = document.getElementById('selected-info');
    const addToCartBtn     = document.getElementById('addToCartBtn');
    const btnText          = addToCartBtn?.querySelector('.btn-text');
    const btnLoading       = addToCartBtn?.querySelector('.btn-loading');
    const cartData         = document.getElementById('cartData');
    const productId        = cartData?.dataset.productId;

    const priceFinalEl   = document.getElementById('price-final');
    const priceOldEl     = document.getElementById('price-old');
    const discountBadge  = document.getElementById('discount-badge');
    const stockInfoEl    = document.getElementById('stock-info');
    const hasSizeButtons = document.querySelectorAll('.btn-size').length > 0;
    const productStock   = parseInt(cartData?.dataset.stock || '0', 10);
    const currentColorLabel = document.getElementById('current-color-label');

    if (!quantityInput || !sizeInputHidden || !selectedInfo || !addToCartBtn || !cartData) {
        console.error('Thiếu phần tử DOM cần thiết cho product-detail!');
        return;
    }

    function getVariant(size) {
        return (typeof SIZE_VARIANTS !== 'undefined' && SIZE_VARIANTS[size])
            ? SIZE_VARIANTS[size]
            : null;
    }

    function getCurrentStockLimit() {
        if (hasSizeButtons) {
            const size = sizeInputHidden.value;
            const v    = getVariant(size);
            return v ? parseInt(v.quantity || 0, 10) : 0;
        }
        return productStock || 0;
    }

    function formatPrice(num) {
        return new Intl.NumberFormat('vi-VN').format(num) + '₫';
    }

    function showToast(message, type = 'success', timeout = 3000) {
        document.querySelectorAll('.custom-toast').forEach(t => t.remove());
        const toast = document.createElement('div');
        toast.className = 'custom-toast position-fixed top-0 start-50 translate-middle-x p-3';
        toast.style.zIndex = '9999';
        const bsType = type === 'warning' ? 'warning' : (type === 'danger' ? 'danger' : 'success');
        toast.innerHTML = `
            <div class="alert alert-${bsType} alert-dismissible fade show mb-0">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
            </div>`;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.transition = 'opacity 0.3s';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, timeout);
    }

    function updateSelectedInfo() {
        const size  = sizeInputHidden.value;
        const color = colorInputHidden.value;
        const qty   = quantityInput.value;
        if (size || color) {
            const parts = [];
            if (size)  parts.push(size);
            if (color) parts.push(color);
            selectedInfo.innerHTML = `<span class="text-success fw-bold">${parts.join(' / ')} - ${qty} cái</span>`;
        } else {
            selectedInfo.innerHTML = `<span class="text-muted">${qty} cái</span>`;
        }
    }

    function updatePrice() {
        const size = sizeInputHidden.value;
        const v    = getVariant(size);
        if (!v) return;
        const base   = v.base_price || 0;
        const final  = v.final_price || 0;
        const discPt = v.discount_pct || 0;

        if (priceFinalEl) priceFinalEl.textContent = formatPrice(final);
        if (discPt > 0 && final < base) {
            if (priceOldEl) {
                priceOldEl.classList.remove('d-none');
                priceOldEl.textContent = formatPrice(base);
            }
            if (discountBadge) {
                discountBadge.textContent = '-' + discPt + '%';
                discountBadge.classList.remove('d-none');
            }
        } else {
            if (priceOldEl) {
                priceOldEl.textContent = '';
                priceOldEl.classList.add('d-none');
            }
            if (discountBadge) discountBadge.classList.add('d-none');
        }
    }

    function updateStockInfo() {
        if (!stockInfoEl || !hasSizeButtons) return;

        const size = sizeInputHidden.value;
        const v    = getVariant(size);
        if (!v) {
            stockInfoEl.textContent = '';
            return;
        }

        const qty = parseInt(v.quantity || 0, 10);

        if (qty > 0) {
            stockInfoEl.textContent = 'Còn ' + qty + ' sản phẩm';
            stockInfoEl.classList.remove('text-danger');
            stockInfoEl.classList.add('text-success');
        } else {
            stockInfoEl.textContent = 'Hết hàng';
            stockInfoEl.classList.remove('text-success');
            stockInfoEl.classList.add('text-danger');
        }
    }

    // quantity buttons
    document.querySelectorAll('.btn-increase, .btn-decrease').forEach(btn => {
        btn.addEventListener('click', function () {
            let val = parseInt(quantityInput.value) || 1;

            if (this.classList.contains('btn-increase')) {
                val++;
            }
            if (this.classList.contains('btn-decrease') && val > 1) {
                val--;
            }

            const stockLimit = getCurrentStockLimit();

            if (stockLimit > 0 && val > stockLimit) {
                val = stockLimit;
                showToast('Chỉ còn ' + stockLimit + ' sản phẩm cho lựa chọn này', 'warning');
            }

            quantityInput.value = val;
            updateSelectedInfo();
        });
    });

    // size select
    document.querySelectorAll('.btn-size').forEach(btn => {
        btn.addEventListener('click', function () {
            if (this.dataset.stock === '0') {
                showToast('Size này tạm hết hàng', 'warning');
                return;
            }
            document.querySelectorAll('.btn-size').forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-pressed','false');
            });
            this.classList.add('active');
            this.setAttribute('aria-pressed','true');
            sizeInputHidden.value = this.getAttribute('data-size');

            const stockLimit = getCurrentStockLimit();
            let currentQty   = parseInt(quantityInput.value) || 1;
            if (stockLimit > 0 && currentQty > stockLimit) {
                currentQty = stockLimit;
                quantityInput.value = currentQty;
            }

            updateSelectedInfo();
            updatePrice();
            updateStockInfo();
        });
    });

    // color select (dùng btn-color, KHÔNG chuyển trang)
    document.querySelectorAll('.btn-color').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.btn-color').forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-pressed','false');
            });
            this.classList.add('active');
            this.setAttribute('aria-pressed','true');

            const chosenColor = this.getAttribute('data-color') || '';
            colorInputHidden.value = chosenColor;
            if (currentColorLabel) currentColorLabel.textContent = chosenColor;

            updateSelectedInfo();
        });
    });

    // thumbnail click
    document.querySelectorAll('.thumb-img').forEach(thumb => {
        thumb.addEventListener('click', function () {
            document.querySelectorAll('.thumb-img').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('mainImage').src = this.getAttribute('data-src');
        });
    });

    // add to cart
    addToCartBtn.addEventListener('click', function () {
        const stockLimit = getCurrentStockLimit();
        let currentQty   = parseInt(quantityInput.value) || 1;

        if (stockLimit > 0 && currentQty > stockLimit) {
            currentQty = stockLimit;
            quantityInput.value = currentQty;
            updateSelectedInfo();
            showToast('Chỉ còn ' + stockLimit + ' sản phẩm cho lựa chọn này', 'warning');
        }

        if (stockLimit === 0) {
            showToast('Sản phẩm hiện đã hết hàng cho lựa chọn này', 'warning');
            return;
        }

        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        addToCartBtn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('selected_size', sizeInputHidden.value);
        formData.append('selected_color', colorInputHidden.value);
        formData.append('quantity', quantityInput.value);

        fetch('cart.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async (response) => {
            const ct = response.headers.get('content-type') || '';
            if (!response.ok) {
                let msg = 'Lỗi server';
                if (ct.includes('application/json')) {
                    const j = await response.json().catch(()=>null);
                    if (j && j.message) msg = j.message;
                } else {
                    const t = await response.text().catch(()=>null);
                    if (t) msg = t;
                }
                throw new Error(msg);
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                const badge = document.querySelector('.badge-cart');
                if (badge && typeof data.count !== 'undefined') badge.textContent = data.count;
                showToast(data.message || 'Đã thêm vào giỏ hàng!', 'success');
            } else {
                showToast((data && data.message) || 'Không thể thêm vào giỏ', 'danger');
            }
        })
        .catch(err => {
            console.error(err);
            showToast(err.message || 'Lỗi kết nối!', 'danger');
        })
        .finally(() => {
            btnText.classList.remove('d-none');
            btnLoading.classList.add('d-none');
            addToCartBtn.disabled = false;
        });
    });

    // init
    updateSelectedInfo();
    updatePrice();
    if (hasSizeButtons) updateStockInfo();
});
</script>

<?php include '../includes/footer.php'; ?>
