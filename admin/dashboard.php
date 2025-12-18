<?php
session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin quyền
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// DB
require_once __DIR__ . '/../config/db.php';
$conn = $pdo;

// Lấy tên người dùng từ session
$username = $_SESSION['user']['hoten']
        ?? $_SESSION['user']['name']
        ?? $_SESSION['user']['username']
        ?? 'Admin';
$username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

// BIỂU ĐỒ
$chartDefault = (string)($_GET['chart'] ?? 'revenue');
$chartDefault = in_array($chartDefault, ['revenue','product','orders'], true) ? $chartDefault : 'revenue';

// TAB SẢN PHẨM (fav_tab): bestseller (mặc định) | favorites | instock
$favTab = (string)($_GET['fav_tab'] ?? 'bestseller');
$perPageFav = 5; // mỗi trang 5 sản phẩm
$favPage = isset($_GET['fav_page']) ? max(1, (int)$_GET['fav_page']) : 1;
$favTotal = 10;
$favTotalPages = (int)ceil($favTotal / $perPageFav); // = 2 trang
$offsetFav = ($favPage - 1) * $perPageFav;
if (!in_array($favTab, ['bestseller','favorites','instock'], true)) $favTab = 'bestseller';

try {
    // Tổng số liệu
    $revenueQuery = $conn->query("
        SELECT SUM(total) AS total_revenue
        FROM orders
        WHERE status = 'Đã giao hàng'
    ");
    $revenue = $revenueQuery->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

    $orderQuery = $conn->query("
        SELECT COUNT(*) AS total_orders
        FROM orders
    ");
    $totalOrders = $orderQuery->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;

    $userQuery = $conn->query("SELECT COUNT(*) AS total_users FROM users WHERE role = 'user'");
    $totalUsers = $userQuery->fetch(PDO::FETCH_ASSOC)['total_users'] ?? 0;

    $productQuery = $conn->query("SELECT COUNT(*) AS total_products FROM products");
    $totalProducts = $productQuery->fetch(PDO::FETCH_ASSOC)['total_products'] ?? 0;
    $hasDateFilter = isset($_GET['from'], $_GET['to'])
    && $_GET['from'] !== ''
    && $_GET['to'] !== '';
if (!$hasDateFilter) {

    $chartLabels  = [];
    $chartRevenue = array_fill(0, 12, 0);
    $chartOrders  = array_fill(0, 12, 0);

    for ($m = 1; $m <= 12; $m++) {
        $chartLabels[] = 'Tháng ' . $m;
    }

    $stmt = $conn->query("
        SELECT 
            MONTH(created_at) AS m,
            SUM(CASE WHEN status = 'Đã giao hàng' THEN total ELSE 0 END) AS revenue,
            COUNT(*) AS orders
        FROM orders
        WHERE YEAR(created_at) = YEAR(CURDATE())
        GROUP BY m
        ORDER BY m
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $idx = (int)$row['m'] - 1;
        $chartRevenue[$idx] = (float)$row['revenue'];
        $chartOrders[$idx]  = (int)$row['orders'];
    }
}
else {
    // ===== THEO NGÀY (KHI CHỌN TỪ NGÀY – ĐẾN NGÀY) =====
    $fromDate = $_GET['from'];
    $toDate   = $_GET['to'];

    $chartLabels  = [];
    $chartRevenue = [];
    $chartOrders  = [];

    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) AS day,
            SUM(CASE WHEN status = 'Đã giao hàng' THEN total ELSE 0 END) AS revenue,
            COUNT(*) AS orders
        FROM orders
        WHERE DATE(created_at) BETWEEN :from AND :to
        GROUP BY day
        ORDER BY day
    ");
    $stmt->execute([
        ':from' => $fromDate,
        ':to'   => $toDate
    ]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chartLabels[]  = date('d/m/Y', strtotime($row['day']));
        $chartRevenue[] = (float)$row['revenue'];
        $chartOrders[]  = (int)$row['orders'];
    }
}



    // Danh mục sản phẩm -> product counts & list (id => name)
    $categoryQuery = $conn->query("
        SELECT categories.id, categories.name, COUNT(products.id) AS total
        FROM categories
        LEFT JOIN products ON categories.id = products.category_id
        GROUP BY categories.id, categories.name
        ORDER BY categories.name ASC
    ");
    $categoryNames = [];
    $productCounts = [];
    $categoryList = [];
    while ($row = $categoryQuery->fetch(PDO::FETCH_ASSOC)) {
        $categoryList[$row['id']] = $row['name'];
        $categoryNames[] = $row['name'];
        $productCounts[] = (int)$row['total'];
    }

    // --- SẢN PHẨM (box right) ---
    $dbNameStmt = $conn->query("SELECT DATABASE() AS dbname");
    $dbName = $dbNameStmt->fetchColumn();

    $colCheckStmt = $conn->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = 'products' AND COLUMN_NAME = :col
    ");

    $hasBestseller = false;
    $hasQuantity = false;

    $colCheckStmt->execute([':schema' => $dbName, ':col' => 'is_bestseller']);
    if ($colCheckStmt->fetchColumn() > 0) $hasBestseller = true;

    $colCheckStmt->execute([':schema' => $dbName, ':col' => 'quantity']);
    if ($colCheckStmt->fetchColumn() > 0) $hasQuantity = true;

    // Count total items for selected tab
    if ($favTab === 'favorites') {
        $favCountStmt = $conn->query("
            SELECT COUNT(DISTINCT product_id) AS total_fav
            FROM wishlist
        ");
    } elseif ($favTab === 'instock') {
        if ($hasQuantity) {
            $cntStmt = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE quantity > 0");
        } else {
            $cntStmt = $conn->query("SELECT COUNT(*) AS cnt FROM products");
        }
    } else { // bestseller -> count only products that have sold > 0 (exclude canceled orders)
        $cntStmt = $conn->prepare("
            SELECT COUNT(DISTINCT p.id) AS cnt
            FROM products p
            JOIN order_details od ON od.product_id = p.id
            JOIN orders o ON o.id = od.order_id AND TRIM(o.status) != 'Hủy đơn hàng'
            GROUP BY p.id
        ");
        // We need the count of DISTINCT p.id across the grouped results.
        // Simpler: use a subquery that finds distinct product ids with sales and count them
        $cntStmt2 = $conn->query("
            SELECT COUNT(*) AS cnt FROM (
                SELECT DISTINCT p.id
                FROM products p
                JOIN order_details od ON od.product_id = p.id
                JOIN orders o ON o.id = od.order_id AND TRIM(o.status) != 'Hủy đơn hàng'
                GROUP BY p.id
                HAVING SUM(od.quantity) > 0
            ) AS t
        ");
    }

    // Lấy danh sách sản phẩm theo tab (favorite/instock/bestseller-as-top-sold-with-sales-only)
    if ($favTab === 'favorites') {
        $favQuery = $conn->prepare("
            SELECT 
                p.id,
                p.name,
                COUNT(w.id) AS wish_count,
                (
                    SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1
                ) AS image_url
            FROM wishlist w
            JOIN products p ON p.id = w.product_id
            GROUP BY p.id, p.name
            ORDER BY wish_count DESC
            LIMIT :limit OFFSET :offset
        ");
        $favQuery->bindValue(':limit', $perPageFav, PDO::PARAM_INT);
        $favQuery->bindValue(':offset', $offsetFav, PDO::PARAM_INT);
        $favQuery->execute();

        $favoriteProducts = $favQuery->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($favTab === 'instock') {
        if ($hasQuantity) {
            $instockQuery = $conn->prepare("
                SELECT p.id, p.name, p.quantity AS wish_count, 
                (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) AS image_url
                FROM products p
                WHERE p.quantity > 0
                ORDER BY p.quantity DESC
                LIMIT :limit OFFSET :offset
            ");
            $instockQuery->bindValue(':limit', $perPageFav, PDO::PARAM_INT);
            $instockQuery->bindValue(':offset', $offsetFav, PDO::PARAM_INT);
            $instockQuery->execute();

            $favoriteProducts = $instockQuery->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $instockQuery = $conn->prepare("
                SELECT p.id, p.name, 0 AS wish_count, 
                (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) AS image_url
                FROM products p
                ORDER BY p.created_at DESC, p.id DESC
                LIMIT :limit OFFSET :offset
            ");
            $instockQuery->bindValue(':limit', $perPageFav, PDO::PARAM_INT);
            $instockQuery->execute();
            $favoriteProducts = $instockQuery->fetchAll(PDO::FETCH_ASSOC);
        }
    } else { // bestseller -> top-selling products by SUM(order_details.quantity), but only those with SUM > 0
        $featQuery = $conn->prepare("
    SELECT *
    FROM (
        SELECT 
            p.id,
            p.name,
            SUM(od.quantity) AS sold_count,
            (
                SELECT image_url
                FROM product_images
                WHERE product_id = p.id
                ORDER BY id ASC
                LIMIT 1
            ) AS image_url
        FROM products p
        JOIN order_details od ON od.product_id = p.id
        JOIN orders o ON o.id = od.order_id
            AND TRIM(o.status) != 'Hủy đơn hàng'
        GROUP BY p.id, p.name
        HAVING SUM(od.quantity) > 0
        ORDER BY sold_count DESC
        LIMIT 10
    ) AS top10
    LIMIT :limit OFFSET :offset
");

$featQuery->bindValue(':limit', $perPageFav, PDO::PARAM_INT);
$featQuery->bindValue(':offset', $offsetFav, PDO::PARAM_INT);
$featQuery->execute();

$favoriteProducts = $featQuery->fetchAll(PDO::FETCH_ASSOC);

    }

} catch (PDOException $e) {
    die("Lỗi truy vấn: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// helper params used when building links
$currentChartParam = isset($_GET['chart']) ? $_GET['chart'] : $chartDefault;
$currentFavTab = $favTab;

function buildLink($overrides = []) {
    $qs = $_GET;
    foreach ($overrides as $k => $v) $qs[$k] = $v;
    return $_SERVER['PHP_SELF'] . '?' . http_build_query($qs);
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>YAMY Admin - Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ---------- BASIC ---------- */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{ display:flex; background:#f5f5f5; color:#333; min-height:100vh; }

/* ---------- SIDEBAR ---------- */
.sidebar{ width:260px; background:#fff; height:100vh; padding:30px 20px; position:fixed; border-right:1px solid #eee; overflow:auto; }
.sidebar h3{ font-size:22px; font-weight:700; margin-bottom:25px; }
.sidebar a{ display:flex; align-items:center; gap:10px; padding:12px; color:#333; text-decoration:none; border-radius:8px; margin-bottom:8px; transition:.25s; font-weight:500; font-size:15px; }
.sidebar a:hover{ background:#f2e8ff; color:#8E5DF5; transform:translateX(4px); }
.sidebar .logout{ color:#e53935; margin-top:20px; }

/* ---------- CONTENT ---------- */
.content{ flex:1; padding:30px 40px; margin-left:260px; }
.stats{ display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; margin-top:15px; }
.card{ background:#fff; padding:22px; border-radius:14px; border:1px solid #eee; text-align:center; box-shadow:0 4px 12px rgba(0,0,0,0.03); }
.card h4{ font-size:15px; color:#666; margin-bottom:10px; } .card p{ font-size:28px; font-weight:700; margin-top:5px; }

.bottom-row{ display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:25px; align-items:stretch; }

/* equal height boxes */
.chart-box, .fav-box {
    background:#fff; padding:22px; border-radius:14px; border:1px solid #eee; box-shadow:0 6px 18px rgba(0,0,0,0.03);
    display:flex; flex-direction:column; flex:1 1 auto; min-height:0;
    min-height:520px;
}
.chart-box h2, .fav-box h2{ font-size:18px; margin-bottom:10px; }

/* ---------- TABS ---------- */
.switch{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:8px; }
.switch .tab-btn{
    padding:8px 14px;
    border-radius:14px;
    background:linear-gradient(180deg,#f3e9ff,#f9f4ff);
    color:#6b46c1;
    text-decoration:none;
    font-weight:700;
    font-size:13px;
    border:1px solid transparent;
    transition:all .18s;
    box-shadow:0 6px 18px rgba(142,93,245,0.06);
}
/* default make buttons visible background so they "pop" even when not active */
.switch .tab-btn:not(.active){ background:linear-gradient(180deg,#f6efff,#fbf7ff); }
.switch .tab-btn:hover{ transform:translateY(-2px); }
.switch .tab-btn.active{ background:linear-gradient(135deg,#8E5DF5,#A64FF2); color:#fff; border-color:rgba(142,93,245,0.18); box-shadow:0 12px 30px rgba(142,93,245,0.18); }

/* product tabs */
.product-tabs{ display:flex;  gap:10px; margin-bottom:12px; flex-wrap:wrap; align-items:center; }
.product-tabs a{ display:inline-flex; background: linear-gradient(180deg,#f3e9ff,#f9f4ff) ;align-items:center; gap:8px; padding:8px 14px; border-radius:999px;  color:#6b46c1; text-decoration:none; font-weight:600; font-size:14px; border:1px solid transparent; transition:all .12s ease; }
.product-tabs a:hover, .product-tabs a:focus{ background: rgba(142,93,245,0.06); color:#5a2fb5; outline:none; }
.product-tabs a.active{ background: linear-gradient(135deg,#8E5DF5,#A64FF2); color:#fff; box-shadow: 0 10px 25px rgba(142,93,245,0.12); border:1px solid rgba(0,0,0,0.04); }

/* controls */
.select-range, select#categorySel { padding:7px 10px; border-radius:10px; border:1px solid #eee; font-weight:600; background:#fff; }

/* chart area fixed height to avoid stretching */
.chart-wrapper{ margin-top:12px; position:relative; flex:1 1 auto; min-height:0; height:360px; }

/* fav list */
.fav-inner{ display:flex; flex-direction:column; gap:8px; flex:1 1 auto; min-height:0; }
.fav-list{ list-style:none; margin-top:6px; padding:0; flex:1 1 auto; overflow:auto; padding-right:6px; min-height:0; }
.fav-item{ display:flex; align-items:center; gap:14px; padding:14px 6px; border-bottom:1px solid #f6f6f6; }
.favorite-thumb{ width:56px; height:56px; border-radius:8px; object-fit:cover; background:#f3f3f3; }
.fav-name{ flex:1; font-size:15px; font-weight:600; color:#333; }
.fav-meta{ font-size:14px; color:#E91E63; font-weight:600; }

/* pagination */
.fav-pagination{ margin-top:12px; display:flex; justify-content:center; gap:8px; font-size:13px; align-items:center; height:56px; }
.page-link, .page-current { display:inline-block; min-width:38px; padding:8px 10px; border-radius:8px; border:1px solid #eee; text-align:center; text-decoration:none; color:#555; font-weight:600; }
.page-link:hover{ background:#f5ecff; border-color:#8E5DF5; color:#8E5DF5; }
.page-current{ background:#8E5DF5; color:#fff; border-color:#8E5DF5; }
.page-ellipsis{ display:inline-block; padding:8px 6px; color:#999; }

@media(max-width: 992px){
    .bottom-row{ grid-template-columns:1fr; }
    .chart-wrapper{ height:300px; }
    .chart-box,.fav-box{ min-height:auto; }
}
.small-note { font-size:13px;color:#666;margin-left:6px; display:block; margin-top:6px; }
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
    <h1>Xin chào, <?= $username ?> <i style="color:#FF9800" class="fa-solid fa-hand"></i></h1>
    <p>Chúc bạn một ngày làm việc hiệu quả!</p>

    <div class="stats">
        <div class="card"><h4>Doanh thu</h4><p style="color:#4CAF50"><?= number_format($revenue,0,',','.') ?> ₫</p></div>
        <div class="card"><h4>Đơn hàng</h4><p style="color:#03A9F4"><?= $totalOrders ?></p></div>
        <div class="card"><h4>Khách hàng</h4><p style="color:#FF9800"><?= $totalUsers ?></p></div>
        <div class="card"><h4>Sản phẩm</h4><p style="color:#E91E63"><?= $totalProducts ?></p></div>
    </div>

    <div class="bottom-row">
        <!-- CHART BOX -->
        <div class="chart-box">
            <h2>Biểu đồ thống kê</h2>
            <div class="switch">
                <a href="<?= htmlspecialchars(buildLink(['chart'=>'revenue']), ENT_QUOTES) ?>" class="tab-btn <?= $chartDefault === 'revenue' ? 'active' : '' ?>">Doanh thu / Đơn hàng</a>
                <a href="<?= htmlspecialchars(buildLink(['chart'=>'product']), ENT_QUOTES) ?>" class="tab-btn <?= $chartDefault === 'product' ? 'active' : '' ?>">Danh mục sản phẩm</a>

                <div id="dateFilterBox" style="margin-left:auto; display:flex; align-items:center; gap:10px;">
                    <label class="small-note">Từ ngày</label>
                    <input type="date" id="fromDate" class="select-range"
                        value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">

                    <label class="small-note">Đến ngày</label>
                    <input type="date" id="toDate" class="select-range"
                        value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">

                    <button id="applyDate" class="tab-btn">Áp dụng</button>
                </div>


            </div>

            <!-- controls area -->
            <div style="display:flex; gap:12px; align-items:center; margin-top:6px; margin-bottom:6px;">
                <div style="display:flex; flex-direction:column; font-size:13px; color:#666; margin-left:8px;" id="catLabelWrap">
                    <span>Chọn danh mục:</span>
                </div>
                <select id="categorySel" aria-label="Chọn danh mục để hiển thị">
                    <option value="__all">Tất cả danh mục</option>
                    <?php
                        if (!empty($categoryList)) {
                            foreach ($categoryList as $id => $nm) {
                                echo '<option value="'.htmlspecialchars($nm,ENT_QUOTES).'">'.htmlspecialchars($nm,ENT_QUOTES).'</option>';
                            }
                        } else {
                            foreach ($categoryNames as $nm) {
                                echo '<option value="'.htmlspecialchars($nm,ENT_QUOTES).'">'.htmlspecialchars($nm,ENT_QUOTES).'</option>';
                            }
                        }
                    ?>
                </select>
            </div>

            <div class="chart-wrapper" id="revenueChartWrapper">
                <canvas id="revenueChart"></canvas>
            </div>

            <div class="chart-wrapper" style="display:none;" id="productChartWrapper">
                <canvas id="productChart"></canvas>
            </div>

            <div class="chart-wrapper" style="display:none;" id="ordersChartWrapper">
                <canvas id="ordersChart"></canvas>
            </div>
        </div>

        <!-- FAVORITES / bestseller / INSTOCK BOX -->
        <div class="fav-box">
            <h2>Top 10 Sản phẩm</h2>

            <div class="product-tabs" role="tablist" aria-label="Product tabs">
                <?php
                    $tabs = [
                        'bestseller' => 'Bán chạy',
                        'favorites' => 'Yêu thích',
                        'instock' => 'Tồn kho nhiều nhất'
                    ];
                    foreach ($tabs as $k => $label):
                        $qs = $_GET;
                        $qs['fav_tab'] = $k;
                        $qs['fav_page'] = 1; // về trang 1 khi đổi tab
                        $link = $_SERVER['PHP_SELF'] . '?' . http_build_query($qs);
                        $activeClass = ($favTab === $k) ? 'active' : '';
                ?>
                    <a href="<?= htmlspecialchars($link, ENT_QUOTES) ?>" class="<?= $activeClass ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($favoriteProducts)): ?>
                <p class="text-muted" style="font-size:14px;">Không có sản phẩm hiển thị ở tab này.</p>
            <?php else: ?>
                <div class="fav-inner">
                    <ul class="fav-list" aria-live="polite">
                        <?php foreach ($favoriteProducts as $fp):
                            $img = $fp['image_url'] ?: 'placeholder-product.png';
                            $imgUrl = '/clothing_store/uploads/' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
                            // meta có thể là wish_count, quantity (instock) hoặc sold_count (bestseller)
                            $meta = isset($fp['wish_count']) ? (int)$fp['wish_count'] : (isset($fp['sold_count']) ? (int)$fp['sold_count'] : (isset($fp['sold_count']) ? (int)$fp['sold_count'] : (isset($fp['sold_count']) ? (int)$fp['sold_count'] : (int)($fp['sold_count'] ?? 0))));
                            // Note: above line ensures compatibility if different column name appears ('sold_count' or 'sold_count' from query)
                        ?>
                            <li class="fav-item">
                                <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($fp['name'], ENT_QUOTES, 'UTF-8') ?>" class="favorite-thumb">
                                <div class="fav-name"><?= htmlspecialchars($fp['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="fav-meta">
                                    <?php if ($favTab === 'favorites'): ?>
                                        <i class="fa-regular fa-heart"></i> <?= $meta ?>
                                    <?php elseif ($favTab === 'instock'): ?>
                                        <i class="fa fa-box"></i> <?= $meta ?>
                                    <?php else: // bestseller -> show sold count ?>
                                        <i class="fa fa-chart-line"></i> Bán: <?= $meta ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($favTotalPages > 1): ?>
                        <div class="fav-pagination">
                            <?php for ($p = 1; $p <= $favTotalPages; $p++): ?>
                                <?php
                                    $qs = $_GET;
                                    $qs['fav_page'] = $p;
                                    $link = $_SERVER['PHP_SELF'] . '?' . http_build_query($qs);
                                ?>
                                <?php if ($p == $favPage): ?>
                                    <span class="page-current"><?= $p ?></span>
                                <?php else: ?>
                                    <a class="page-link" href="<?= htmlspecialchars($link, ENT_QUOTES) ?>"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
/* ================== PHP -> JS DATA ================== */
// dữ liệu THEO NGÀY (đã query ở PHP)
const chartLabels = <?= json_encode($chartLabels) ?>;
const revenueData = <?= json_encode($chartRevenue) ?>;
const ordersData  = <?= json_encode($chartOrders) ?>;

// dữ liệu cho chart danh mục
const catNamesData  = <?= json_encode($categoryNames) ?>;
const catCountsData = <?= json_encode($productCounts) ?>;

// tab mặc định
const initialChart = <?= json_encode($chartDefault) ?>;

/* ================== HELPERS ================== */
function formatVND(value){
    if (value === null || value === undefined) return value;
    const v = Math.round(Number(value));
    return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " ₫";
}

/* ================== REVENUE CHART (THEO NGÀY) ================== */
const revCtx = document.getElementById('revenueChart').getContext('2d');

const revenueChart = new Chart(revCtx, {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [
            {
                label: 'Doanh thu',
                data: revenueData,
                backgroundColor: '#8E5DF5',
                borderRadius: 8,
                yAxisID: 'y_left'
            },
            {
                label: 'Số đơn',
                data: ordersData,
                backgroundColor: '#2ECC71',
                borderRadius: 8,
                yAxisID: 'y_right'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'rectRounded',
                    padding: 12
                }
            },
            tooltip: {
                callbacks: {
                    label: function(ctx){
                        const lab = ctx.dataset.label || '';
                        const val = ctx.parsed.y ?? 0;
                        return lab === 'Doanh thu'
                            ? lab + ': ' + formatVND(val)
                            : lab + ': ' + val;
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { display: false }
            },
            y_left: {
                type: 'linear',
                position: 'left',
                beginAtZero: true,
                ticks: {
                    callback: v => formatVND(v)
                }
            },
            y_right: {
                type: 'linear',
                position: 'right',
                beginAtZero: true,
                grid: { display: false }
            }
        }
    }
});

/* ================== PRODUCT CATEGORY CHART ================== */
const productCtx = document.getElementById('productChart').getContext('2d');

const productChart = new Chart(productCtx, {
    type: 'bar',
    data: {
        labels: catNamesData,
        datasets: [
            {
                label: 'Số lượng sản phẩm',
                data: catCountsData,
                backgroundColor: '#8E5DF5',
                borderRadius: 10,
                maxBarThickness: 60
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: {
                grid: { display: false },
                ticks: { maxRotation: 45, minRotation: 30 }
            },
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});

/* ================== PRODUCT FILTER ================== */
const categorySel  = document.getElementById('categorySel');
const catLabelWrap = document.getElementById('catLabelWrap');

function updateProductChart(categoryName) {
    if (!categoryName || categoryName === '__all') {
        productChart.data.labels = catNamesData;
        productChart.data.datasets[0].data = catCountsData;
    } else {
        const idx = catNamesData.indexOf(categoryName);
        productChart.data.labels = [categoryName];
        productChart.data.datasets[0].data = [idx >= 0 ? catCountsData[idx] : 0];
    }
    productChart.update();
}

categorySel?.addEventListener('change', function(){
    updateProductChart(this.value);
});

/* ================== TAB SHOW / HIDE ================== */
const revenueWrapper = document.getElementById('revenueChartWrapper');
const productWrapper = document.getElementById('productChartWrapper');
const ordersWrapper  = document.getElementById('ordersChartWrapper');

const dateFilterBox = document.getElementById('dateFilterBox');

function showOnly(which){
    revenueWrapper.style.display = which === 'revenue' ? 'block' : 'none';
    productWrapper.style.display = which === 'product' ? 'block' : 'none';
    ordersWrapper.style.display  = which === 'orders'  ? 'block' : 'none';

    //CHỈ HIỆN CHỌN NGÀY Ở DOANH THU
    if (which === 'revenue') {
        dateFilterBox.style.display = 'flex';
    } else {
        dateFilterBox.style.display = 'none';
    }

    //CHỈ HIỆN CHỌN DANH MỤC Ở BIỂU ĐỒ SẢN PHẨM
    if (which === 'product') {
        catLabelWrap.style.display = '';
        categorySel.style.display = '';
    } else {
        catLabelWrap.style.display = 'none';
        categorySel.style.display = 'none';
    }

    setTimeout(() => {
        revenueChart.resize();
        productChart.resize();
    }, 100);
}


/* ================== INIT ================== */
window.addEventListener('load', function(){
    updateProductChart(categorySel?.value || '__all');
    showOnly(initialChart);
});

document.getElementById('applyDate').addEventListener('click', function () {
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;

    if (!from || !to) {
        alert('Vui lòng chọn đủ Từ ngày và Đến ngày');
        return;
    }

    const params = new URLSearchParams(window.location.search);
    params.set('from', from);
    params.set('to', to);
    params.set('chart', 'revenue'); // luôn quay về chart doanh thu

    window.location.search = params.toString();
});
</script>

</body>
</html>
