<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

// === PHÂN TRANG & BỘ LỌC ===
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 9;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';

$where = [];
$params = [];

if ($search) {
    $where[] = "p.name LIKE :search";
    $params[':search'] = "%$search%";
}
if ($category) {
    $where[] = "p.category_id = :category";
    $params[':category'] = $category;
}
if ($min_price !== '') {
    $where[] = "p.final_price >= :min_price";
    $params[':min_price'] = $min_price;
}
if ($max_price !== '') {
    $where[] = "p.final_price <= :max_price";
    $params[':max_price'] = $max_price;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Tổng sản phẩm
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$pages = ceil($total / $limit);

// Lấy sản phẩm
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $whereSql 
    ORDER BY p.created_at DESC 
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Danh mục
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Sản phẩm nổi bật
$featured = $pdo->query("
    SELECT p.*, c.name as cat_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.is_featured = 1 
    ORDER BY p.created_at DESC 
    LIMIT 6
")->fetchAll();

// Sản phẩm giảm giá
$discounted = $pdo->query("
    SELECT p.*, c.name as cat_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.discount_percent > 0 
    ORDER BY p.discount_percent DESC 
    LIMIT 6
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<!-- HERO CAROUSEL - BANNER CHUẨN -->
<section class="hero-carousel overflow-hidden">
    <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-inner">

            <!-- Slide 1 -->
            <div class="carousel-item active">
                <img src="<?= asset('images/banner1.png') ?>" class="d-block w-100 hero-img" alt="Khuyến mãi" loading="lazy">
                <div class="carousel-caption d-none d-md-block text-start caption-left">
                </div>
            </div>

            <!-- Slide 2 -->
            <div class="carousel-item">
                <img src="<?= asset('images/banner2.png') ?>" class="d-block w-100 hero-img" alt="Sản phẩm mới" loading="lazy">
                <div class="carousel-caption d-none d-md-block text-end caption-right">
                    <h1 class="display-4 fw-bold text-white text-shadow">MỚI NHẤT 2025</h1>
                    <p class="lead text-white text-shadow">Bộ sưu tập xu hướng mới nhất</p>
                    <a href="#" class="btn btn-primary btn-lg mt-3 shadow-sm">Khám phá</a>
                </div>
            </div>

            <!-- Slide 3 -->
            <div class="carousel-item">
                <img src="<?= asset('images/banner3.png') ?>" class="d-block w-100 hero-img" alt="Giảm giá" loading="lazy">
                <div class="carousel-caption d-none d-md-block text-start caption-left">
                    <p class="lead text-white text-shadow">Giảm giá cực sốc – chỉ trong hôm nay!</p>
                    <a href="#" class="btn btn-warning btn-lg mt-3 shadow-sm">Xem ngay</a>
                </div>
            </div>

            <!-- Slide 4 -->
            <div class="carousel-item">
                <img src="<?= asset('images/banner4.png') ?>" class="d-block w-100 hero-img" alt="Thời trang" loading="lazy">
                <div class="carousel-caption d-none d-md-block text-end caption-right">
                    <h1 class="display-4 fw-bold text-white text-shadow">FASHION WEEK</h1>
                    <p class="lead text-white text-shadow">Tham gia tuần lễ thời trang YaMy</p>
                    <a href="#" class="btn btn-success btn-lg mt-3 shadow-sm">Tham gia</a>
                </div>
            </div>

        </div>

        <!-- Nút điều khiển -->
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>

        <!-- Indicators (chấm tròn dưới) -->
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
        </div>
    </div>
</section>

<style>
    .hero-carousel .hero-img {
    width: 100%;
    height: 400px; /* hoặc chiều cao bạn muốn cố định */
    object-fit: contain; /* Giữ ảnh đầy đủ, không bị cắt */
    background-color: #f5f5f5; /* màu nền nếu ảnh không phủ hết */
}

</style>

<div class="container my-5">



    <!-- Sản phẩm nổi bật -->
    <section id="featured" class="mb-5">
        <h2 class="text-center mb-4">Sản phẩm nổi bật</h2>
        <div class="row">
            <?php foreach ($featured as $p): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 position-relative overflow-hidden">
                        <div class="position-absolute top-0 start-0 bg-danger text-white px-2 py-1 small">Nổi bật</div>
                        <img src="<?= upload($p['image']) ?>" class="card-img-top" style="height: 220px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title"><?= $p['name'] ?></h6>
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <div>
                                    <?php if ($p['discount_percent'] > 0): ?>
                                        <del class="text-muted small"><?= number_format($p['price']) ?>₫</del>
                                        <span class="text-danger fw-bold"><?= number_format($p['discounted_price']) ?>₫</span>
                                        <span class="badge bg-danger small">-<?= $p['discount_percent'] ?>%</span>
                                    <?php else: ?>
                                        <span class="fw-bold"><?= number_format($p['price']) ?>₫</span>
                                    <?php endif; ?>
                                </div>
                                <a href="product-detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- KHUYẾN MÃI -->
    <section id="discounted" class="mb-5">
        <h2 class="text-center mb-4">Khuyến mãi HOT</h2>
        <div class="row g-4">
            <?php foreach ($discounted as $p): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-danger position-relative overflow-hidden">
                        <div class="position-absolute top-0 end-0 bg-danger text-white px-2 py-1 rounded-start z-index-1">
                            -<?= $p['discount_percent'] ?>%
                        </div>
                        <img src="<?= upload($p['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['name']) ?>" style="height: 220px; object-fit: cover;" loading="lazy">
                        <div class="card-body">
                            <h6 class="card-title"><?= htmlspecialchars($p['name']) ?></h6>
                            <p class="text-muted small"><?= $p['cat_name'] ?></p>
                            <div class="d-flex justify-content-between align-items-end">
                                <div>
                                    <del class="text-muted"><?= number_format($p['price']) ?>₫</del><br>
                                    <span class="text-danger fw-bold fs-5"><?= number_format($p['final_price']) ?>₫</span>
                                </div>
                                <form method="POST" action="cart.php" class="d-inline">
                                    <input type="hidden" name="add_to_cart" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- BỘ LỌC + SẢN PHẨM
    <section class="mb-5">
        <div class="row">
             Bộ lọc 
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm sticky-top" style="top: 80px;">
                    <div class="card-header bg-danger text-white">
                        <strong>Bộ lọc</strong>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="mb-3">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Tìm kiếm..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="mb-3">
                                <select name="category" class="form-select form-select-sm">
                                    <option value="">Tất cả danh mục</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <input type="number" name="min_price" class="form-control form-control-sm" placeholder="Giá từ" value="<?= $min_price ?>">
                            </div>
                            <div class="mb-3">
                                <input type="number" name="max_price" class="form-control form-control-sm" placeholder="Giá đến" value="<?= $max_price ?>">
                            </div>
                            <button type="submit" class="btn btn-danger w-100">Lọc</button>
                        </form>
                    </div>
                </div>
            </div> -->
<!--  -->
            <!-- Danh sách sản phẩm 
            <div class="col-lg-9">
                <div class="row g-4">
                    <?php foreach ($products as $p): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm hover-shadow">
                                <img src="<?= upload($p['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['name']) ?>" style="height: 200px; object-fit: cover;" loading="lazy">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title"><?= htmlspecialchars($p['name']) ?></h6>
                                    <p class="text-muted small"><?= $p['cat_name'] ?></p>
                                    <div class="mt-auto">
                                        <?php if ($p['discount_percent'] > 0): ?>
                                            <del class="text-muted small"><?= number_format($p['price']) ?>₫</del>
                                            <span class="text-danger fw-bold"><?= number_format($p['final_price']) ?>₫</span>
                                            <span class="badge bg-danger small">-<?= $p['discount_percent'] ?>%</span>
                                        <?php else: ?>
                                            <span class="fw-bold"><?= number_format($p['final_price']) ?>₫</span>
                                        <?php endif; ?>
                                    </div>
                                        <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger">
                                            Xem Chi Tiết
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                 Phân trang 
                <?php if ($pages > 1): ?>
                    <nav class="mt-5">
                        <ul class="pagination justify-content-center">
                            <?php
                            $baseParams = array_filter([
                                'search' => $search,
                                'category' => $category,
                                'min_price' => $min_price,
                                'max_price' => $max_price
                            ]);
                            for ($i = 1; $i <= $pages; $i++):
                                $linkParams = $baseParams + ['page' => $i];
                                $url = '?' . http_build_query($linkParams);
                            ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $url ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div> -->

<?php include '../includes/footer.php'; ?>

