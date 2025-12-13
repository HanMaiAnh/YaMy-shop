<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

/*
 * NEW ARRIVALS: 8 sản phẩm mới nhất
 * - chỉ lấy sản phẩm có status = 1 (hiển thị)
 * - chỉ lấy sản phẩm có tổng quantity > 0 (còn hàng)
 */
$sqlFeatured = "
    SELECT 
        p.id,
        p.name,
        p.discount_percent,
        p.created_at,
        c.name AS cat_name,
        MIN(pi.image_url) AS image_url,
        MIN(v.price) AS price,
        MIN(NULLIF(v.price_reduced,0)) AS reduced_price,
        COALESCE(SUM(v.quantity),0) AS total_qty
    FROM products p
    LEFT JOIN categories c      ON p.category_id = c.id
    LEFT JOIN product_images pi ON pi.product_id = p.id
    LEFT JOIN product_variants v ON v.product_id = p.id
    WHERE p.status = 1
    GROUP BY p.id, p.name, p.discount_percent, p.created_at, c.name
    HAVING COALESCE(SUM(v.quantity),0) > 0
    ORDER BY p.created_at DESC
    LIMIT 8
";

try {
    $featured = $pdo->query($sqlFeatured)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $featured = [];
}

/*
 * HOT SALES: 8 sản phẩm có giảm giá
 * - status = 1
 * - còn hàng (total_qty > 0)
 * - có giảm: p.discount_percent > 0 OR (exists price_reduced > 0 in variants)
 */
$sqlDiscounted = "
    SELECT 
        p.id,
        p.name,
        p.discount_percent,
        c.name AS cat_name,
        MIN(pi.image_url) AS image_url,
        MIN(v.price) AS price,
        MIN(NULLIF(v.price_reduced,0)) AS reduced_price,
        COALESCE(SUM(v.quantity),0) AS total_qty
    FROM products p
    LEFT JOIN categories c      ON p.category_id = c.id
    LEFT JOIN product_images pi ON pi.product_id = p.id
    LEFT JOIN product_variants v ON v.product_id = p.id
    WHERE p.status = 1
    GROUP BY p.id, p.name, p.discount_percent, c.name
    HAVING COALESCE(SUM(v.quantity),0) > 0
       AND (
            COALESCE(p.discount_percent,0) > 0
            OR COALESCE(MIN(NULLIF(v.price_reduced,0)),0) > 0
       )
    ORDER BY p.discount_percent DESC, p.id DESC
    LIMIT 8
";

try {
    $discounted = $pdo->query($sqlDiscounted)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $discounted = [];
}

/*
 * Tin tức mới nhất
 */
$newsList = [];
try {
    $stmtNews = $pdo->query("
        SELECT id, title, content, image, created_at
        FROM news
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $newsList = $stmtNews->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $newsList = [];
}

include '../includes/header.php';
?>

<!-- HERO -->
<section class="hero-carousel overflow-hidden">
    <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">

        <div class="carousel-inner">

            <div class="carousel-item active">
                <img src="<?= asset('images/banner1.png') ?>" class="d-block w-100 hero-img" alt="Khuyến mãi">
            </div>

            <div class="carousel-item">
                <img src="<?= asset('images/banner2.png') ?>" class="d-block w-100 hero-img" alt="Sản phẩm mới">
                <div class="carousel-caption d-none d-md-block text-end caption-right">
                    <h1 class="display-4 fw-bold text-white text-shadow">MỚI NHẤT 2025</h1>
                    <p class="lead text-white text-shadow">Bộ sưu tập xu hướng mới nhất</p>
                    <a href="products.php" class="btn btn-primary btn-lg mt-3 shadow-sm">Khám phá</a>
                </div>
            </div>

            <div class="carousel-item">
                <img src="<?= asset('images/banner3.png') ?>" class="d-block w-100 hero-img" alt="Giảm giá">
                <div class="carousel-caption d-none d-md-block text-start caption-left">
                    <p class="lead text-white text-shadow">Giảm giá cực sốc – chỉ trong hôm nay!</p>
                    <a href="products.php" class="btn btn-warning btn-lg mt-3 shadow-sm">Xem ngay</a>
                </div>
            </div>

            <div class="carousel-item">
                <img src="<?= asset('images/banner4.png') ?>" class="d-block w-100 hero-img" alt="Thời trang">
                <div class="carousel-caption d-none d-md-block text-end caption-right">
                    <h1 class="display-4 fw-bold text-white text-shadow">FASHION WEEK</h1>
                    <p class="lead text-white text-shadow">Tham gia tuần lễ thời trang YaMy</p>
                    <a href="products.php" class="btn btn-success btn-lg mt-3 shadow-sm">Tham gia</a>
                </div>
            </div>

        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
            <span class="visually-hidden">Previous</span>
        </button>

        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
            <span class="visually-hidden">Next</span>
        </button>

        <div class="carousel-indicators">
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3"></button>
        </div>
    </div>
</section>

<!-- MAIN -->
 
<div class="container my-5">


    <!-- NEW ARRIVALS -->
    <section id="featured" class="mb-5">
        <h2 class="text-center mb-4">New Arrivals</h2>
        <div class="row g-4">
            <?php if (empty($featured)): ?>
                <p class="text-center text-muted">Chưa có sản phẩm nào.</p>
            <?php else: ?>
                <?php foreach ($featured as $p): ?>
                    <?php
                        $price    = (int)($p['price'] ?? 0);
                        $reduced  = (int)($p['reduced_price'] ?? 0);
                        $discount = (int)($p['discount_percent'] ?? 0);

                        if ($reduced > 0) {
                            $final = $reduced;
                        } elseif ($discount > 0 && $price > 0) {
                            $final = (int)round($price * (1 - $discount / 100));
                        } else {
                            $final = $price;
                        }

                        $imgSrc = !empty($p['image_url'])
                            ? '../uploads/' . $p['image_url']
                            : '../images/placeholder-product.png';
                    ?>
                    <div class="col-6 col-md-3">
                        <div class="card h-100 position-relative overflow-hidden card-clickable"
                             data-url="product-detail.php?id=<?= $p['id'] ?>"
                             style="cursor:pointer;">

                            <div class="position-absolute top-0 start-0 bg-danger text-white px-2 py-1 small">
                                New
                            </div>

                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                 class="card-img-top"
                                 style="height:220px; object-fit:cover;"
                                 alt="<?= htmlspecialchars($p['name']) ?>">

                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title mb-1">
                                    <?= htmlspecialchars($p['name']) ?>
                                </h6>

                                <div class="d-flex align-items-baseline gap-2 mt-1">
                                    <?php if ($final < $price && $price > 0): ?>
                                        <del class="text-muted small"><?= number_format($price) ?>₫</del>
                                        <span class="text-danger fw-bold fs-5"><?= number_format($final) ?>₫</span>
                                    <?php else: ?>
                                        <span class="text-danger fw-bold fs-5"><?= number_format($price) ?>₫</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- HOT SALES -->
    <section id="discounted" class="mb-5">
        <h2 class="text-center mb-4">HOT SALES</h2>
        <div class="row g-4">
            <?php if (empty($discounted)): ?>
                <p class="text-center text-muted">Chưa có sản phẩm khuyến mãi.</p>
            <?php else: ?>
                <?php foreach ($discounted as $p): ?>
                    <?php
                        $price    = (int)($p['price'] ?? 0);
                        $reduced  = (int)($p['reduced_price'] ?? 0);
                        $discount = (int)($p['discount_percent'] ?? 0);

                        if ($reduced > 0) {
                            $final = $reduced;
                        } elseif ($discount > 0 && $price > 0) {
                            $final = (int)round($price * (1 - $discount / 100));
                        } else {
                            $final = $price;
                        }

                        $imgSrc = !empty($p['image_url'])
                            ? '../uploads/' . $p['image_url']
                            : '../images/placeholder-product.png';
                    ?>
                    <div class="col-6 col-md-3">
                        <div class="card h-100 border-danger position-relative overflow-hidden card-clickable"
                             data-url="product-detail.php?id=<?= $p['id'] ?>"
                             style="cursor:pointer;">

                            <div class="position-absolute top-0 end-0 bg-danger text-white px-2 py-1 rounded-start">
                                SALE
                            </div>

                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                 class="card-img-top"
                                 style="height:220px; object-fit:cover;"
                                 alt="<?= htmlspecialchars($p['name']) ?>">

                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title mb-1">
                                    <?= htmlspecialchars($p['name']) ?>
                                </h6>
                                <p class="text-muted small mb-1">
                                    <?= htmlspecialchars($p['cat_name'] ?? '') ?>
                                </p>

                                <div class="d-flex align-items-baseline gap-2">
                                    <?php if ($final < $price && $price > 0): ?>
                                        <del class="text-muted small"><?= number_format($price) ?>₫</del>
                                        <span class="text-danger fw-bold fs-5">
                                            <?= number_format($final) ?>₫
                                        </span>
                                    <?php else: ?>
                                        <span class="text-danger fw-bold fs-5">
                                            <?= number_format($price) ?>₫
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- NEWS -->
    <section id="home-news" class="mb-5">
        <h2 class="text-center mb-4">📰 Tin tức mới nhất</h2>

        <?php if (!empty($newsList)): ?>
            <div class="row g-4">
                <?php foreach ($newsList as $n): ?>
                    <div class="col-sm-6 col-md-4">
                        <div class="card h-100 shadow-sm">
                            <?php if (!empty($n['image'])): ?>
                                <img src="<?= htmlspecialchars($n['image']) ?>"
                                     class="card-img-top"
                                     style="height:200px;object-fit:cover;"
                                     alt="<?= htmlspecialchars($n['title']) ?>">
                            <?php endif; ?>

                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title mb-2">
                                    <?= htmlspecialchars($n['title']) ?>
                                </h5>
                                <p class="card-text small text-muted mb-2">
                                    <?= date('d/m/Y', strtotime($n['created_at'])) ?>
                                </p>
                                <p class="card-text flex-grow-1">
                                    <?= mb_substr(strip_tags($n['content']), 0, 120) . '...' ?>
                                </p>
                                <a href="news_detail.php?id=<?= $n['id'] ?>"
                                   class="mt-2 btn btn-outline-primary btn-sm">
                                    Xem thêm →
                                </a>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-3">
                <a href="./news.php" class="btn btn-link">Xem tất cả tin tức</a>
            </div>
        <?php else: ?>
            <p class="text-center text-muted">Hiện chưa có tin tức nào.</p>
        <?php endif; ?>
    </section>

</div><!-- /.container -->

<script>
// Click vào card => đi tới trang chi tiết
document.querySelectorAll('.card-clickable').forEach(card => {
    card.addEventListener('click', function () {
        window.location.href = this.dataset.url;
    });
});
</script>

<?php include '../includes/footer.php'; ?>
