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

// TAB SẢN PHẨM (fav_tab): featured (mặc định) | favorites | instock
$favTab = (string)($_GET['fav_tab'] ?? 'featured');
if (!in_array($favTab, ['featured','favorites','instock'], true)) $favTab = 'featured';

// Phân trang sản phẩm trong box (dùng same perPage cho tất cả tab)
$perPageFav = 5;
$favPage = isset($_GET['fav_page']) ? max(1, (int)$_GET['fav_page']) : 1;

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

    // Chuẩn bị 12 tháng
    $monthsLabels = [];
    for ($m = 1; $m <= 12; $m++) $monthsLabels[] = str_pad((string)$m, 2, '0', STR_PAD_LEFT);

    // Doanh thu theo tháng (Đã giao hàng)
    $stmtRev = $conn->prepare("
        SELECT DATE_FORMAT(created_at, '%m') AS month, SUM(total) AS total
        FROM orders
        WHERE status = 'Đã giao hàng'
        GROUP BY month
        ORDER BY month
    ");
    $stmtRev->execute();
    $rawRev = $stmtRev->fetchAll(PDO::FETCH_ASSOC);
    $revenueByMonth = array_fill(0, 12, 0);
    foreach ($rawRev as $r) {
        $idx = (int)$r['month'] - 1;
        if ($idx >= 0 && $idx < 12) $revenueByMonth[$idx] = (float)$r['total'];
    }

    // Số đơn theo tháng (mọi trạng thái)
    $stmtOrd = $conn->prepare("
        SELECT DATE_FORMAT(created_at, '%m') AS month, COUNT(*) AS cnt
        FROM orders
        GROUP BY month
        ORDER BY month
    ");
    $stmtOrd->execute();
    $rawOrd = $stmtOrd->fetchAll(PDO::FETCH_ASSOC);
    $ordersByMonth = array_fill(0, 12, 0);
    foreach ($rawOrd as $r) {
        $idx = (int)$r['month'] - 1;
        if ($idx >= 0 && $idx < 12) $ordersByMonth[$idx] = (int)$r['cnt'];
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

    // --- Orders by status per month (dữ liệu cho chart 'Đơn hàng') ---
    $statusOrder = [
        'Chờ xác nhận',
        'Đang xử lý',
        'Đơn hàng đang được giao',
        'Đã giao hàng',
        'Hủy đơn hàng'
    ];

    // Lấy distinct status từ DB (trim)
    $statusListStmt = $conn->query("SELECT DISTINCT TRIM(status) AS status FROM orders");
    $dbStatuses = $statusListStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $dbStatuses = array_unique(array_map('trim', $dbStatuses));

    // Sắp xếp theo $statusOrder nếu có, sau đó thêm các status khác (nếu có lỗi tên sẽ vẫn xuất)
    $orderedStatuses = [];
    foreach ($statusOrder as $s) {
        if (in_array($s, $dbStatuses, true)) $orderedStatuses[] = $s;
    }
    foreach ($dbStatuses as $s) {
        if (!in_array($s, $orderedStatuses, true)) $orderedStatuses[] = $s;
    }

    // Query counts grouped by month and status
    $stmtStatusMonths = $conn->prepare("
        SELECT DATE_FORMAT(created_at, '%m') AS month, TRIM(status) AS status, COUNT(*) AS cnt
        FROM orders
        GROUP BY month, status
        ORDER BY month
    ");
    $stmtStatusMonths->execute();
    $rawStatusMonths = $stmtStatusMonths->fetchAll(PDO::FETCH_ASSOC);

    // Initialize structure
    $statusMonthCounts = [];
    foreach ($orderedStatuses as $st) {
        $statusMonthCounts[$st] = array_fill(0, 12, 0);
    }

    foreach ($rawStatusMonths as $r) {
        $midx = (int)$r['month'] - 1;
        $st = trim($r['status']);
        if (!isset($statusMonthCounts[$st])) {
            $statusMonthCounts[$st] = array_fill(0, 12, 0);
            $orderedStatuses[] = $st;
        }
        if ($midx >= 0 && $midx < 12) $statusMonthCounts[$st][$midx] = (int)$r['cnt'];
    }

    // --- SẢN PHẨM (box right) ---
    $dbNameStmt = $conn->query("SELECT DATABASE() AS dbname");
    $dbName = $dbNameStmt->fetchColumn();

    $colCheckStmt = $conn->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = 'products' AND COLUMN_NAME = :col
    ");

    $hasFeatured = false;
    $hasQuantity = false;

    $colCheckStmt->execute([':schema' => $dbName, ':col' => 'is_featured']);
    if ($colCheckStmt->fetchColumn() > 0) $hasFeatured = true;

    $colCheckStmt->execute([':schema' => $dbName, ':col' => 'quantity']);
    if ($colCheckStmt->fetchColumn() > 0) $hasQuantity = true;

    // Count total items for selected tab
    if ($favTab === 'favorites') {
        $favCountStmt = $conn->query("
            SELECT COUNT(DISTINCT product_id) AS total_fav
            FROM wishlist
        ");
        $favTotal = (int)($favCountStmt->fetch(PDO::FETCH_ASSOC)['total_fav'] ?? 0);
    } elseif ($favTab === 'instock') {
        if ($hasQuantity) {
            $cntStmt = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE quantity > 0");
            $favTotal = (int)($cntStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        } else {
            $cntStmt = $conn->query("SELECT COUNT(*) AS cnt FROM products");
            $favTotal = (int)($cntStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        }
    } else { // featured -> count only products that have sold > 0 (exclude canceled orders)
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
        $favTotal = (int)($cntStmt2->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    }

    $favTotalPages = $favTotal > 0 ? (int)ceil($favTotal / $perPageFav) : 1;
    if ($favPage > $favTotalPages) $favPage = $favTotalPages;
    $offsetFav = ($favPage - 1) * $perPageFav;

    // Lấy danh sách sản phẩm theo tab (favorite/instock/featured-as-top-sold-with-sales-only)
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
            $instockQuery->bindValue(':offset', $offsetFav, PDO::PARAM_INT);
            $instockQuery->execute();
            $favoriteProducts = $instockQuery->fetchAll(PDO::FETCH_ASSOC);
        }
    } else { // featured -> top-selling products by SUM(order_details.quantity), but only those with SUM > 0
        $featQuery = $conn->prepare("
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
            ORDER BY sold_count DESC, p.created_at DESC
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

function paginationSequence($current, $total, $edgeCount = 2, $adjacent = 2) {
    $pages = [];
    if ($total <= (2*$edgeCount + 2*$adjacent + 1)) {
        for ($i=1;$i<=$total;$i++) $pages[] = $i;
        return $pages;
    }
    $left = max(1, $current - $adjacent);
    $right = min($total, $current + $adjacent);

    for ($i=1; $i <= $edgeCount; $i++) $pages[] = $i;

    if ($left > $edgeCount + 1) $pages[] = '...';
    for ($i = max($edgeCount+1,$left); $i <= min($right, $total - $edgeCount); $i++) $pages[] = $i;
    if ($right < $total - $edgeCount) $pages[] = '...';

    for ($i = max($total - $edgeCount + 1, $edgeCount+1); $i <= $total; $i++) $pages[] = $i;

    $out = [];
    foreach ($pages as $p) {
        if (empty($out) || end($out) !== $p) $out[] = $p;
    }
    return $out;
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
.select-range, select#orderStatusSel, select#categorySel { padding:7px 10px; border-radius:10px; border:1px solid #eee; font-weight:600; background:#fff; }

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
                <a href="<?= htmlspecialchars(buildLink(['chart'=>'orders']), ENT_QUOTES) ?>" class="tab-btn <?= $chartDefault === 'orders' ? 'active' : '' ?>">Đơn hàng</a>

                <div style="margin-left:auto; display:flex; align-items:center; gap:8px;">
                    <label for="monthRange" class="small-note" id="monthLabel">Hiển thị:</label>
                    <select id="monthRange" class="select-range" title="Chọn số tháng hiển thị">
                        <option value="3">3 tháng</option>
                        <option value="6">6 tháng</option>
                        <option value="12" selected>12 tháng</option>
                    </select>
                    <small id="monthNote" style="color:#999; margin-left:8px;"></small>
                </div>
            </div>

            <!-- controls area -->
            <div style="display:flex; gap:12px; align-items:center; margin-top:6px; margin-bottom:6px;">
                <div style="display:flex; flex-direction:column; font-size:13px; color:#666;">
                    <span>Chọn trạng thái:</span>
                </div>
                <select id="orderStatusSel" aria-label="Chọn trạng thái để hiển thị">
                    <option value="__all">Tất cả trạng thái</option>
                    <?php foreach ($orderedStatuses as $st): ?>
                        <option value="<?= htmlspecialchars($st, ENT_QUOTES) ?>"><?= htmlspecialchars($st, ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>

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

        <!-- FAVORITES / FEATURED / INSTOCK BOX -->
        <div class="fav-box">
            <h2>Sản phẩm</h2>

            <div class="product-tabs" role="tablist" aria-label="Product tabs">
                <?php
                    $tabs = [
                        'featured' => 'Nổi bật',
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
                            // meta có thể là wish_count, quantity (instock) hoặc sold_count (featured)
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
                                    <?php else: // featured -> show sold count ?>
                                        <i class="fa fa-chart-line"></i> Bán: <?= $meta ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($favTotalPages > 1): ?>
                        <div class="fav-pagination" aria-label="Phân trang sản phẩm">
                            <?php
                                $pages = paginationSequence($favPage, $favTotalPages, 2, 2);
                                foreach ($pages as $p):
                                    if ($p === '...'):
                            ?>
                                <span class="page-ellipsis">…</span>
                            <?php
                                    else:
                                        $qs = $_GET;
                                        $qs['fav_page'] = $p;
                                        $qs['fav_tab'] = $favTab;
                                        $qs['chart'] = $currentChartParam;
                                        $link = $_SERVER['PHP_SELF'] . '?' . http_build_query($qs);
                                        if ($p == $favPage):
                            ?>
                                    <span class="page-current"><?= $p ?></span>
                                <?php else: ?>
                                    <a class="page-link" href="<?= htmlspecialchars($link, ENT_QUOTES) ?>"><?= $p ?></a>
                                <?php endif; ?>
                            <?php
                                    endif;
                                endforeach;
                            ?>
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
// PHP -> JS data
const monthsDataRaw   = <?= json_encode($monthsLabels) ?>; // ['01','02',...]
const monthsLabelsReadable = monthsDataRaw.map(m => 'Tháng ' + parseInt(m,10));
const revenueDataFull = <?= json_encode($revenueByMonth) ?>;
const ordersDataFull  = <?= json_encode($ordersByMonth) ?>;
const catNamesData    = <?= json_encode($categoryNames) ?>;
const catCountsData   = <?= json_encode($productCounts) ?>;
const orderedStatuses = <?= json_encode(array_values($orderedStatuses)) ?>;
const statusMonthCounts = <?= json_encode($statusMonthCounts) ?>;
const initialChart = <?= json_encode($chartDefault) ?>;

// color map for statuses (adjust if needed)
const statusColorMap = {
    'Chờ xác nhận': '#9e7cf7',
    'Đang xử lý': '#f6c84c',
    'Đơn hàng đang được giao': '#ff8a4c',
    'Đã giao hàng': '#2ecc71',
    'Hủy đơn hàng': '#f44336'
};

function randomColor(i){
    const palette = ['#8E5DF5','#A64FF2','#F6C84C','#FF8A4C','#2ECC71','#F44336','#00A3FF'];
    return palette[i % palette.length];
}

function formatVND(value){
    if (value === null || value === undefined) return value;
    const v = Math.round(Number(value));
    return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " ₫";
}

/* ---------- REVENUE CHART ---------- */
const revCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revCtx, {
    type: 'bar',
    data: {
        labels: monthsLabelsReadable,
        datasets: [
            { label: 'Doanh thu', data: revenueDataFull, backgroundColor: '#8E5DF5', borderRadius:8, maxBarThickness:44, yAxisID:'y_left' },
            { label: 'Tổng đơn', data: ordersDataFull, backgroundColor: '#2ECC71', borderRadius:8, maxBarThickness:28, yAxisID:'y_right' }
        ]
    },
    options: {
        responsive:true,
        maintainAspectRatio:false,
        interaction:{ mode:'index', intersect:false },
        plugins:{
            legend:{ position:'top', labels:{ usePointStyle:true, pointStyle:'rectRounded', padding:12, font:{ size:12 } } },
            tooltip:{ callbacks:{ label:function(ctx){
                const lab = ctx.dataset.label||'';
                const val = ctx.parsed.y ?? ctx.raw ?? 0;
                return lab === 'Doanh thu' ? lab + ': ' + formatVND(val) : lab + ': ' + val;
            } } }
        },
        scales:{
            x:{ stacked:false, grid:{ display:false }, ticks:{ autoSkip:true, maxRotation:0, minRotation:0 } },
            y_left:{ type:'linear', display:true, position:'left', grid:{ color:'rgba(0,0,0,0.06)', drawBorder:false }, ticks:{ callback:function(v){ return formatVND(v);} }, beginAtZero:true },
            y_right:{ type:'linear', display:true, position:'right', grid:{ display:false }, ticks:{ callback:function(v){ return v; } }, beginAtZero:true }
        },
        layout:{ padding:{ top:6, bottom:6 } }
    }
});

/* ---------- PRODUCT CATEGORY CHART (no months) ---------- */
const productCtx = document.getElementById('productChart').getContext('2d');
let productChart = new Chart(productCtx, {
    type: 'bar',
    data: {
        labels: catNamesData,
        datasets: [{ label:'Số lượng sản phẩm', data: catCountsData, backgroundColor: '#8E5DF5', borderRadius:10, maxBarThickness:60 }]
    },
    options: {
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:false } },
        scales:{ x:{ grid:{ display:false }, ticks:{ autoSkip:false, maxRotation:45, minRotation:30 } }, y:{ beginAtZero:true, grid:{ color:'rgba(0,0,0,0.03)' }, ticks:{ precision:0 } } }
    }
});

/* ---------- ORDERS CHART (status per month) ---------- */
const ordersCtx = document.getElementById('ordersChart').getContext('2d');

function buildStatusDatasets(statuses, monthCounts) {
    const datasets = [];
    statuses.forEach((st, i) => {
        const col = statusColorMap[st] || randomColor(i);
        datasets.push({
            label: st,
            data: monthCounts[st] || new Array(monthsLabelsReadable.length).fill(0),
            backgroundColor: col,
            borderRadius:8,
            maxBarThickness:28
        });
    });
    return datasets;
}

let ordersChart = new Chart(ordersCtx, {
    type: 'bar',
    data: {
        labels: monthsLabelsReadable,
        datasets: buildStatusDatasets(orderedStatuses, statusMonthCounts)
    },
    options: {
        responsive:true, maintainAspectRatio:false,
        interaction:{ mode:'index', intersect:false },
        plugins:{ legend:{ position:'top', labels:{ boxWidth:12 } }, tooltip:{ callbacks:{ label:function(ctx){ return ctx.dataset.label + ': ' + (ctx.parsed.y || 0); } } } },
        scales:{ x:{ stacked:false, grid:{ display:false }, ticks:{ autoSkip:true } }, y:{ beginAtZero:true, ticks:{ stepSize:1, precision:0 } } }
    }
});

// adjust orders chart Y max so bars are not too stretched
function adjustOrdersYAxis() {
    const dataSets = ordersChart.data.datasets || [];
    let maxVal = 0;
    dataSets.forEach(ds => {
        if (Array.isArray(ds.data)) {
            ds.data.forEach(v => { if (v > maxVal) maxVal = v; });
        }
    });
    const suggested = Math.max(1, Math.ceil((maxVal + 1) / 1) ); // integer upper bound
    ordersChart.options.scales.y.suggestedMax = suggested + 1;
    ordersChart.update();
}

/* ---------- HELPERS ---------- */
function takeLastMonths(arr, n) {
    if (!Array.isArray(arr)) return [];
    if (n >= arr.length) return arr.slice();
    return arr.slice(arr.length - n);
}

/* ---------- UPDATE FUNCTIONS ---------- */
function updateRevenueChartDisplay(monthsCount) {
    monthsCount = parseInt(monthsCount) || 12;
    const labels = takeLastMonths(monthsLabelsReadable, monthsCount);
    const rev = takeLastMonths(revenueDataFull, monthsCount);
    const ord = takeLastMonths(ordersDataFull, monthsCount);

    revenueChart.data.labels = labels;
    revenueChart.data.datasets[0].data = rev;
    revenueChart.data.datasets[1].data = ord;
    revenueChart.update();
}

function updateProductChart(categoryName) {
    if (!categoryName || categoryName === '__all') {
        productChart.data.labels = catNamesData;
        productChart.data.datasets = [{ label:'Số lượng sản phẩm', data: catCountsData, backgroundColor: '#8E5DF5', borderRadius:10, maxBarThickness:60 }];
    } else {
        const idx = catNamesData.indexOf(categoryName);
        if (idx === -1) {
            productChart.data.labels = [categoryName];
            productChart.data.datasets = [{ label:'Số lượng sản phẩm', data: [0], backgroundColor: '#8E5DF5', borderRadius:10, maxBarThickness:80 }];
        } else {
            productChart.data.labels = [catNamesData[idx]];
            productChart.data.datasets = [{ label:'Số lượng sản phẩm', data: [catCountsData[idx]], backgroundColor: '#8E5DF5', borderRadius:10, maxBarThickness:80 }];
        }
    }
    productChart.update();
}

function updateOrdersChartDisplay(monthsCount, statusFilter) {
    monthsCount = parseInt(monthsCount) || 12;
    const labels = takeLastMonths(monthsLabelsReadable, monthsCount);
    ordersChart.data.labels = labels;

    if (statusFilter && statusFilter !== '__all') {
        const ds = [{
            label: statusFilter,
            data: takeLastMonths(statusMonthCounts[statusFilter] || new Array(12).fill(0), monthsCount),
            backgroundColor: statusColorMap[statusFilter] || '#8E5DF5',
            borderRadius:8,
            maxBarThickness:50
        }];
        ordersChart.data.datasets = ds;
    } else {
        const ds = [];
        orderedStatuses.forEach((st, i) => {
            const dataAll = statusMonthCounts[st] || new Array(12).fill(0);
            ds.push({
                label: st,
                data: takeLastMonths(dataAll, monthsCount),
                backgroundColor: statusColorMap[st] || randomColor(i),
                borderRadius:8,
                maxBarThickness:28
            });
        });
        ordersChart.data.datasets = ds;
    }
    ordersChart.update();
    // adjust Y
    setTimeout(adjustOrdersYAxis, 50);
}

/* ---------- DOM & UI ---------- */
const revenueWrapper  = document.getElementById('revenueChartWrapper');
const productWrapper  = document.getElementById('productChartWrapper');
const ordersWrapper   = document.getElementById('ordersChartWrapper');
const monthRangeSel   = document.getElementById('monthRange');
const orderStatusSel  = document.getElementById('orderStatusSel');
const categorySel     = document.getElementById('categorySel');
const monthLabel      = document.getElementById('monthLabel');
const monthNote       = document.getElementById('monthNote');
const catLabelWrap    = document.getElementById('catLabelWrap');

function showOnly(which){
    revenueWrapper.style.display = which === 'revenue' ? 'block' : 'none';
    productWrapper.style.display = which === 'product' ? 'block' : 'none';
    ordersWrapper.style.display  = which === 'orders'  ? 'block' : 'none';

    // month selector visible for revenue & orders, hidden for product
    if (which === 'product') {
        monthRangeSel.style.display = 'none';
        monthLabel.style.display = 'none';
        monthNote.style.display = 'none';
        orderStatusSel.style.display = 'none';
        catLabelWrap.style.display = ''; // may hide later if no categories
        categorySel.style.display = '';
    } else if (which === 'orders') {
        monthRangeSel.style.display = '';
        monthLabel.style.display = '';
        monthNote.style.display = '';
        orderStatusSel.style.display = '';
        catLabelWrap.style.display = 'none';
        categorySel.style.display = 'none';
    } else { // revenue
        monthRangeSel.style.display = '';
        monthLabel.style.display = '';
        monthNote.style.display = '';
        orderStatusSel.style.display = 'none';
        catLabelWrap.style.display = 'none';
        categorySel.style.display = 'none';
    }

    // if category list is empty hide its label & select entirely
    if (!Array.isArray(catNamesData) || catNamesData.length === 0) {
        catLabelWrap.style.display = 'none';
        categorySel.style.display = 'none';
    }

    // ensure charts resized
    setTimeout(() => {
        try { revenueChart.resize(); } catch(e){}
        try { productChart.resize(); } catch(e){}
        try { ordersChart.resize(); } catch(e){}
    }, 120);
}

/* ---------- EVENTS ---------- */
monthRangeSel.addEventListener('change', function(){
    const m = this.value;
    updateRevenueChartDisplay(m);
    updateOrdersChartDisplay(m, orderStatusSel.value);
});

orderStatusSel.addEventListener('change', function(){
    const m = monthRangeSel.value;
    updateOrdersChartDisplay(m, this.value);
});

categorySel.addEventListener('change', function(){
    updateProductChart(this.value);
});

/* ---------- INIT ---------- */
window.addEventListener('load', function(){
    const selVal = parseInt(monthRangeSel.value, 10) || 12;
    updateRevenueChartDisplay(selVal);
    updateOrdersChartDisplay(selVal, orderStatusSel.value);
    updateProductChart(categorySel.value);

    if (initialChart === 'product') showOnly('product');
    else if (initialChart === 'orders') showOnly('orders');
    else showOnly('revenue');
});
</script>
</body>
</html>
