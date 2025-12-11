<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';


$current_page = 'products';  // Đánh dấu trang hiện tại
$page_title = 'Tất cả sản phẩm - YaMy Shop';

// === LẤY DỮ LIỆU LỌC ===
$search = trim($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$min_price = (int)($_GET['min_price'] ?? 0);
$max_price = (int)($_GET['max_price'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;

// === XÂY DỰNG CÂU LỆNH SQL ===
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category;
}

if ($min_price > 0) {
    $where[] = "COALESCE(p.discounted_price, p.price * (1 - COALESCE(p.discount_percent, 0)/100)) >= ?";
    $params[] = $min_price;
}

if ($max_price > 0) {
    $where[] = "COALESCE(p.discounted_price, p.price * (1 - COALESCE(p.discount_percent, 0)/100)) <= ?";
    $params[] = $max_price;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// === TÍNH TỔNG SỐ SẢN PHẨM ===
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $where_clause
");
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$pages = ceil($total_products / $per_page);
$offset = ($page - 1) * $per_page;

// === LẤY DANH SÁCH SẢN PHẨM ===
$products_stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name,
           COALESCE(p.discounted_price, p.price * (1 - COALESCE(p.discount_percent, 0)/100)) as final_price
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $where_clause
    ORDER BY p.id DESC 
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$products_stmt->execute($params);
$products = $products_stmt->fetchAll();

// === LẤY DANH MỤC CHO BỘ LỌC ===
$cat_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $cat_stmt->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <div class="row">
        <!-- BỘ LỌC -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm sticky-top" style="top: 80px;">
                <div class="card-header bg-danger text-white">
                    <strong><i class="fa-solid fa-filter me-2"></i> Bộ lọc</strong>
                </div>
                <div class="card-body">
                    <form method="GET" id="filter-form">
                        <div class="mb-3">
                            <input type="text" name="search" class="form-control form-control-sm" 
                                   placeholder="Tìm kiếm..." value="<?= htmlspecialchars($search) ?>">
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
                            <input type="number" name="min_price" class="form-control form-control-sm" 
                                   placeholder="Giá từ (₫)" value="<?= $min_price ?: '' ?>" min="0">
                        </div>
                        <div class="mb-3">
                            <input type="number" name="max_price" class="form-control form-control-sm" 
                                   placeholder="Giá đến (₫)" value="<?= $max_price ?: '' ?>" min="0">
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="fa-solid fa-magnifying-glass"></i> Lọc
                        </button>
                        <a href="?" class="btn btn-outline-secondary w-100 mt-2">Xóa lọc</a>
                    </form>
                </div>
            </div>
        </div>

        <!-- DANH SÁCH SẢN PHẨM -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <?= $total_products ?> sản phẩm
                    <?php if ($search || $category || $min_price || $max_price): ?>
                        <small class="text-muted">(đã lọc)</small>
                    <?php endif; ?>
                </h4>
            </div>

            <?php if (empty($products)): ?>
                <div class="text-center py-5 bg-light rounded">
                    <i class="fa-solid fa-box-open fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Không tìm thấy sản phẩm nào.</p>
                    <a href="?" class="btn btn-outline-danger">Xem tất cả</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($products as $p): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm hover-shadow position-relative overflow-hidden">
                                <a href="product-detail.php?id=<?= $p['id'] ?>" class="text-decoration-none">
                                    <img src="<?= upload($p['image']) ?>" 
                                         class="card-img-top" 
                                         alt="<?= htmlspecialchars($p['name']) ?>" 
                                         style="height: 220px; object-fit: cover;" 
                                         loading="lazy">
                                    
                                    <?php if ($p['discount_percent'] > 0): ?>
                                        <span class="position-absolute top-0 start-0 m-2 badge bg-danger">
                                            -<?= $p['discount_percent'] ?>%
                                        </span>
                                    <?php endif; ?>

                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title text-dark mb-1">
                                            <?= htmlspecialchars(mb_substr($p['name'], 0, 50)) ?>
                                            <?= strlen($p['name']) > 50 ? '...' : '' ?>
                                        </h6>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars($p['cat_name']) ?></p>
                                        
                                        <div class="mt-auto">
                                            <?php if ($p['discount_percent'] > 0): ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <del class="text-muted small"><?= number_format($p['price']) ?>₫</del>
                                                    <span class="text-danger fw-bold fs-5"><?= number_format($p['final_price']) ?>₫</span>
                                                </div>
                                            <?php else: ?>
                                                <span class="fw-bold fs-5 text-dark"><?= number_format($p['final_price']) ?>₫</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                                
                                <div class="card-footer bg-white border-0 pt-0">
                                    <a href="product-detail.php?id=<?= $p['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger w-100">
                                        <i class="fa-solid fa-eye me-1"></i> Xem chi tiết
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- PHÂN TRANG -->
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
                            $prev = $page > 1 ? $page - 1 : 1;
                            $next = $page < $pages ? $page + 1 : $pages;

                            // Nút Prev
                            $prevParams = $baseParams + ['page' => $prev];
                            $prevUrl = '?' . http_build_query($prevParams);
                            ?>
                            <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $prevUrl ?>">«</a>
                            </li>

                            <?php for ($i = 1; $i <= $pages; $i++): 
                                if ($i > 3 && $i < $pages - 2 && abs($i - $page) > 2) {
                                    if ($i == 4 || $i == $pages - 3) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    continue;
                                }
                                $linkParams = $baseParams + ['page' => $i];
                                $url = '?' . http_build_query($linkParams);
                            ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $url ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php
                            $nextParams = $baseParams + ['page' => $next];
                            $nextUrl = '?' . http_build_query($nextParams);
                            ?>
                            <li class="page-item <?= $page == $pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $nextUrl ?>">»</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.hover-shadow {
    transition: all 0.3s ease;
}
.hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
.card-img-top {
    transition: transform 0.3s ease;
}
.card:hover .card-img-top {
    transform: scale(1.05);
}
.badge {
    font-size: 0.7rem;
    padding: 0.35em 0.65em;
}
</style>

<?php include '../includes/footer.php'; ?>