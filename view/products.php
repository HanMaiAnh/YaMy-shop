<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$page_title = "Tất cả sản phẩm";

// === LỌC INPUT ===
$search     = trim($_GET['search'] ?? '');
$category   = (int)($_GET['category'] ?? 0);
$color      = trim($_GET['color'] ?? '');
$min_price  = (int)($_GET['min_price'] ?? 0);
$max_price  = (int)($_GET['max_price'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 20;

// === LẤY DANH SÁCH MÀU TỪ BẢNG colors ===
$colors = [];
$colors_query = $pdo->query("SELECT DISTINCT name FROM colors ORDER BY name");
while ($row = $colors_query->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($row['name'])) {
        $colors[] = $row['name'];
    }
}

// === XÂY WHERE CHO CÁC BỘ LỌC CƠ BẢN (KHÔNG TÍNH GIÁ) ===
$where = [];
$params = [];

// always require product is active (status = 1)
$where[] = "p.status = 1";

if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category;
}

if ($color !== '') {
    // lọc theo tên màu trong bảng colors
    $where[] = "col.name = ?";
    $params[] = $color;
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// === PHẦN HAVING CHO LỌC GIÁ (DÙNG GIÁ SAU KHI GIẢM) ===
$having = [];
$havingParams = [];

if ($min_price > 0) {
    $having[] = "final_price >= ?";
    $havingParams[] = $min_price;
}
if ($max_price > 0) {
    $having[] = "final_price <= ?";
    $havingParams[] = $max_price;
}
$havingSQL = $having ? "HAVING " . implode(" AND ", $having) : "";

// ---------------------------------------------------------------------
// 1) ĐẾM TỔNG SẢN PHẨM (CÓ ÁP DỤNG LỌC GIÁ) ĐỂ PHÂN TRANG
// ---------------------------------------------------------------------
$countSql = "
    SELECT COUNT(*) FROM (
        SELECT 
            p.id,
            COALESCE(
                MIN(NULLIF(v.price_reduced,0)),
                MIN(v.price) * (1 - COALESCE(p.discount_percent,0)/100),
                MIN(v.price)
            ) AS final_price
        FROM products p
        LEFT JOIN product_variants v ON v.product_id = p.id
        LEFT JOIN colors col ON col.id = v.color_id
        $whereSQL
        GROUP BY p.id
        $havingSQL
    ) AS sub
";

try {
    $stmt = $pdo->prepare($countSql);
    $stmt->execute(array_merge($params, $havingParams));
    $total = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    // nếu lỗi, đặt total = 0 để tránh lỗi hiển thị
    $total = 0;
}

$pages  = max(1, ceil($total / $per_page));
$offset = ($page - 1) * $per_page;

// ---------------------------------------------------------------------
// 2) LẤY DANH SÁCH SẢN PHẨM
// ---------------------------------------------------------------------
$sql = "
    SELECT 
        p.id,
        p.name,
        p.description,
        p.category_id,
        COALESCE(p.discount_percent,0) AS discount_percent,
        c.name AS cat_name,
        MIN(v.price) AS base_price,
        MIN(NULLIF(v.price_reduced,0)) AS reduced_price,
        MIN(pi.image_url) AS image_url,
        COALESCE(
            MIN(NULLIF(v.price_reduced,0)),
            MIN(v.price) * (1 - COALESCE(p.discount_percent,0)/100),
            MIN(v.price)
        ) AS final_price
    FROM products p
    LEFT JOIN categories c      ON p.category_id = c.id
    LEFT JOIN product_variants v ON v.product_id = p.id
    LEFT JOIN product_images pi ON pi.product_id = p.id
    LEFT JOIN colors col        ON col.id = v.color_id
    $whereSQL
    GROUP BY p.id, p.name, p.description, p.category_id, p.discount_percent, c.name
    $havingSQL
    ORDER BY p.id DESC
    LIMIT ? OFFSET ?
";

try {
    $stmt = $pdo->prepare($sql);
    // bind params in order: $params (WHERE) then $havingParams then limit/offset
    $executeParams = array_merge($params, $havingParams, [$per_page, $offset]);
    $stmt->execute($executeParams);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // cho an toàn
    $products = [];
}

// === LẤY CATEGORY CHO FILTER ===
$cats = $pdo->query("SELECT id,name FROM categories ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);

include "../includes/header.php";
?>

<div class="container my-5">
    <div class="row">

        <!-- FILTER -->
        <div class="col-lg-3">
            <div class="card shadow-sm sticky-top filter-sidebar">
                <div class="card-header bg-danger text-white fw-bold">
                    <i class="fa-solid fa-filter"></i> Bộ lọc
                </div>

                <div class="card-body">
                    <form method="GET">

                        <!-- ĐÃ BỎ Ô TÌM KIẾM Ở ĐÂY -->

                        <select name="category" class="form-select form-select-sm mb-2">
                            <option value="">Tất cả danh mục</option>
                            <?php foreach ($cats as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="color" class="form-select form-select-sm mb-2">
                            <option value="">Tất cả màu</option>
                            <?php foreach ($colors as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $c == $color ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input type="number" name="min_price"
                               value="<?= $min_price ?: '' ?>"
                               placeholder="Giá từ" class="form-control form-control-sm mb-2">
                        <input type="number" name="max_price"
                               value="<?= $max_price ?: '' ?>"
                               placeholder="Giá đến" class="form-control form-control-sm mb-3">

                        <button class="btn btn-danger w-100">Lọc</button>
                        <a href="products.php" class="btn btn-secondary w-100 mt-2">Xóa lọc</a>

                    </form>
                </div>
            </div>
        </div>

        <!-- PRODUCT LIST -->
        <div class="col-lg-9">

            <h4 class="mb-4"><?= $total ?> sản phẩm</h4>

            <?php if ($search !== ''): ?>
                <div class="alert alert-info shadow-sm">
                    Kết quả cho từ khóa: <strong><?= htmlspecialchars($search) ?></strong>
                </div>
            <?php endif; ?>

            <?php if (empty($products)): ?>
                <div class="text-center py-5 bg-light rounded">
                    <i class="fa-solid fa-box-open fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Không tìm thấy sản phẩm.</p>
                    <a href="products.php" class="btn btn-outline-danger">Xem tất cả</a>
                </div>

            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($products as $p): ?>
                        <?php
                            $basePrice   = (float)($p['base_price'] ?? 0);
                            $final       = (float)($p['final_price'] ?? 0);
                            $discountPct = (int)($p['discount_percent'] ?? 0);

                            // % giảm thực tế (so sánh giá gốc vs giá cuối)
                            $discount = ($basePrice > 0 && $final < $basePrice)
                                        ? round((1 - $final / $basePrice) * 100)
                                        : 0;

                            $imgSrc = !empty($p['image_url'])
                                ? "../uploads/" . $p['image_url']
                                : "../images/placeholder-product.png";
                        ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card border-0 shadow-sm h-100 card-click"
                                 data-url="product-detail.php?id=<?= $p['id'] ?>">

                                <div class="position-relative">
                                    <img src="<?= htmlspecialchars($imgSrc) ?>"
                                         class="w-100"
                                         style="height:220px;object-fit:cover;"
                                         alt="<?= htmlspecialchars($p['name']) ?>">

                                    <?php if ($discount > 0): ?>
                                        <span class="badge bg-danger position-absolute top-0 start-0">
                                            -<?= $discount ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="card-body">
                                    <h6 class="text-truncate mb-1">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </h6>
                                    <small class="text-muted d-block mb-1">
                                        <?= htmlspecialchars($p['cat_name'] ?? '') ?>
                                    </small>

                                    <div class="mt-2">
                                        <?php if ($discount > 0 && $basePrice > 0): ?>
                                            <small class="text-muted text-decoration-line-through">
                                                <?= number_format($basePrice) ?>₫
                                            </small>
                                            <div class="text-danger fw-bold">
                                                <?= number_format($final) ?>₫
                                            </div>
                                        <?php else: ?>
                                            <div class="text-danger fw-bold">
                                                <?= number_format($final) ?>₫
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link"
                                       href="products.php?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>

<script>
document.querySelectorAll('.card-click').forEach(card => {
    card.addEventListener('click', () => {
        window.location.href = card.dataset.url;
    });
});
</script>

<style>
.card-click {
    cursor: pointer;
    transition: .3s;
}
.card-click:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,.15);
}
</style>

<?php include "../includes/footer.php"; ?>
