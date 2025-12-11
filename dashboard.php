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

// Đọc tham số biểu đồ doanh thu, sản phẩm để duy trì lựa chọn tab khi phân trang
$chartDefault = (string)($_GET['chart'] ?? 'revenue');
$chartDefault = ($chartDefault === 'product') ? 'product' : 'revenue';

//Phân trang sản phẩm yêu thích
$perPageFav = 5;
$favPage = isset($_GET['fav_page']) ? (int)$_GET['fav_page'] : 1;
if ($favPage < 1) $favPage = 1;

try {
    // Tổng doanh thu (đã giao hàng)
    $revenueQuery = $conn->query("
        SELECT SUM(total) AS total_revenue
        FROM orders
        WHERE status = 'Đã giao hàng'
    ");
    $revenue = $revenueQuery->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
    // Tổng đơn (đã giao hàng)
    $orderQuery = $conn->query("
        SELECT COUNT(*) AS total_orders
        FROM orders
        WHERE status = 'Đã giao hàng'
    ");
    $totalOrders = $orderQuery->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;
    // Tổng khách hàng
    $userQuery = $conn->query("SELECT COUNT(*) AS total_users FROM users WHERE role = 'user'");
    $totalUsers = $userQuery->fetch(PDO::FETCH_ASSOC)['total_users'] ?? 0;
    // Tổng sản phẩm
    $productQuery = $conn->query("SELECT COUNT(*) AS total_products FROM products");
    $totalProducts = $productQuery->fetch(PDO::FETCH_ASSOC)['total_products'] ?? 0;
    // Chuẩn bị template 12 tháng để luôn trả về đủ 12 label
    $monthsLabels = [];
    for ($m = 1; $m <= 12; $m++) {
        $monthsLabels[] = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
    }
    // Doanh thu theo tháng (đã giao hàng)
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
    //Số đơn theo tháng (đã giao hàng)
    $stmtOrd = $conn->prepare("
        SELECT DATE_FORMAT(created_at, '%m') AS month, COUNT(*) AS cnt
        FROM orders
        WHERE status = 'Đã giao hàng'
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
    //Biểu đồ sản phẩm theo danh mục
    $categoryQuery = $conn->query("
        SELECT categories.name, COUNT(products.id) AS total
        FROM categories
        LEFT JOIN products ON categories.id = products.category_id
        GROUP BY categories.id, categories.name
    ");

    $categoryNames = [];
    $productCounts = [];
    while ($row = $categoryQuery->fetch(PDO::FETCH_ASSOC)) {
        $categoryNames[] = $row['name'];
        $productCounts[] = (int)$row['total'];
    }
    // Tổng số sản phẩm khác nhau trong wishlist
    $favCountStmt = $conn->query("
        SELECT COUNT(DISTINCT product_id) AS total_fav
        FROM wishlist
    ");
    $favTotal = (int)($favCountStmt->fetch(PDO::FETCH_ASSOC)['total_fav'] ?? 0);
    $favTotalPages = $favTotal > 0 ? (int)ceil($favTotal / $perPageFav) : 1;
    if ($favPage > $favTotalPages) $favPage = $favTotalPages;

    $offsetFav = ($favPage - 1) * $perPageFav;
    // Lấy danh sách theo trang
    $favQuery = $conn->prepare("
        SELECT 
            p.id,
            p.name,
            COUNT(w.id) AS wish_count,
            (
                SELECT image_url 
                FROM product_images 
                WHERE product_id = p.id 
                ORDER BY id ASC 
                LIMIT 1
            ) AS image_url
        FROM wishlist w
        JOIN products p ON p.id = w.product_id
        GROUP BY p.id, p.name
        ORDER BY wish_count DESC
        LIMIT :limit OFFSET :offset
    ");
    $favQuery->bindValue(':limit',  $perPageFav, PDO::PARAM_INT);
    $favQuery->bindValue(':offset', $offsetFav, PDO::PARAM_INT);
    $favQuery->execute();
    $favoriteProducts = $favQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Lỗi truy vấn: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
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
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{
    display:flex; 
    background:#f5f5f5; 
    color:#333; 
    min-height:100vh;
}
.sidebar{
    width:260px;
    background:#fff;
    height:100vh;
    padding:30px 20px;
    position:fixed;
    border-right:1px solid #ddd;
    overflow:auto;
}
.sidebar h3{
    font-size:22px;
    font-weight:700;
    margin-bottom:25px;
}
.sidebar a{
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px;
    color:#333;
    text-decoration:none;
    border-radius:8px;
    margin-bottom:8px;
    transition:.25s;
    font-weight:500;
    font-size:15px;
}
.sidebar a:hover{
    background:#f2e8ff;
    color:#8E5DF5;
    transform:translateX(4px);
}
.sidebar .logout{
    color:#e53935;
    margin-top:20px;
}
.content{
    flex:1;
    padding:35px 40px;
    margin-left:260px;
}
.stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-top:25px;
}
.card{
    background:#fff;
    padding:22px;
    border-radius:14px;
    border:1px solid #ddd;
    text-align:center;
    box-shadow:0 4px 12px rgba(0,0,0,0.04);
}
.card h4{
    font-size:15px;
    color:#666;
    margin-bottom:10px;
}
.card p{
    font-size:28px;
    font-weight:700;
    margin-top:5px;
}
.bottom-row{
    display:grid;
    grid-template-columns: 2fr 1.2fr;
    gap:20px;
    margin-top:30px;
}
.chart-box,.fav-box{
    background:#fff;
    padding:22px;
    border-radius:14px;
    border:1px solid #ddd;
    box-shadow:0 4px 12px rgba(0,0,0,0.03);
}
.chart-box h2,.fav-box h2{
    font-size:18px;
    margin-bottom:10px;
}
.switch{
    display:flex;
    align-items:center;
}
.switch button{
    padding:8px 14px;
    border:none;
    border-radius:14px;
    margin-right:10px;
    font-weight:700;
    color:#fff;
    cursor:pointer;
    font-size:13px;
    box-shadow:0 6px 18px rgba(14,14,14,0.06);
}
.switch button:focus{
    outline:none;
}
#btnRevenue{ 
    background:#8E5DF5; 
}
#btnProduct{ 
    background:#E91E63;
 }
.chart-wrapper{
    margin-top:15px;
    height:340px;
    position:relative;
}
.chart-box .chartjs-legend {
    display:flex;
    gap:12px;
    align-items:center;
    margin-top:8px;
    flex-wrap:wrap;
}
.fav-inner{
    min-height:350px;
    display:flex;
    flex-direction:column;
}
.fav-list{
    list-style:none;
    margin-top:12px;
    padding:0;
    flex:1;
}
.fav-item{
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 0;
    border-bottom:1px solid #f1f1f1;
}
.fav-item:last-child{
    border-bottom:none;
}
.favorite-thumb{
    width:50px;
    height:50px;
    border-radius:8px;
    object-fit:cover;
    background:#f3f3f3;
}
.fav-name{
    flex:1;
    font-size:14px;
    font-weight:600;
    color:#333;
}
.fav-count{
    font-size:13px;
    color:#E91E63;
    font-weight:600;
}
.fav-pagination{
    margin-top:10px;
    display:flex;
    justify-content:center;
    gap:6px;
    font-size:13px;
}
.fav-pagination a,.fav-pagination span{
    padding:4px 8px;
    border-radius:6px;
    border:1px solid #eee;
    text-decoration:none;
    color:#555;
}
.fav-pagination a:hover{
    background:#f5ecff;
    border-color:#8E5DF5;
    color:#8E5DF5;
}
.fav-pagination .active{
    background:#8E5DF5;
    border-color:#8E5DF5;
    color:#fff;
}
@media(max-width: 992px){
    .bottom-row{
        grid-template-columns:1fr;
    }
    .chart-wrapper{ height:300px; }
}
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
    <a href="reviews.php"><i class="fa fa-comment"></i> Quản lý bình luận</a>
    <a href="logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>
<div class="content">
    <h1>Xin chào, <?= $username ?> <i  style="color:#FF9800"class="fa-solid fa-hand"></i></h1>
    <p>Chúc bạn một ngày làm việc hiệu quả!</p>

    <div class="stats">
        <div class="card"><h4>Doanh thu</h4><p style="color:#4CAF50"><?= number_format($revenue,0,',','.') ?> ₫</p></div>
        <div class="card"><h4>Đơn hàng</h4><p style="color:#03A9F4"><?= $totalOrders ?></p></div>
        <div class="card"><h4>Khách hàng</h4><p style="color:#FF9800"><?= $totalUsers ?></p></div>
        <div class="card"><h4>Sản phẩm</h4><p style="color:#E91E63"><?= $totalProducts ?></p></div>
    </div>

    <div class="bottom-row">
        <!-- BOX BIỂU ĐỒ -->
        <div class="chart-box">
            <h2>Biểu đồ thống kê</h2>
            <div class="switch">
                <button id="btnRevenue" type="button">Doanh thu / Đơn hàng</button>
                <button id="btnProduct" type="button">Danh mục sản phẩm</button>
            </div>

            <div class="chart-wrapper" id="revenueChartWrapper">
                <canvas id="revenueChart"></canvas>
            </div>
            <div class="chart-wrapper" style="display:none;" id="productChartWrapper">
                <canvas id="productChart"></canvas>
            </div>
        </div>
        <!-- BOX SẢN PHẨM YÊU THÍCH -->
        <div class="fav-box">
            <h2>Sản phẩm được yêu thích nhiều</h2>

            <?php if (empty($favoriteProducts)): ?>
                <p class="text-muted" style="font-size:14px;">Chưa có sản phẩm nào trong wishlist.</p>
            <?php else: ?>
                <div class="fav-inner">
                    <ul class="fav-list">
                        <?php foreach ($favoriteProducts as $fp):
                            $img    = $fp['image_url'] ?: 'placeholder-product.png';
                            $imgUrl = '/clothing_store/uploads/' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
                        ?>
                            <li class="fav-item">
                                <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($fp['name'], ENT_QUOTES, 'UTF-8') ?>" class="favorite-thumb">
                                <div class="fav-name">
                                    <?= htmlspecialchars($fp['name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="fav-count">
                                    <i class="fa-regular fa-heart"></i>
                                    <?= (int)$fp['wish_count'] ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($favTotalPages > 1): ?>
                        <div class="fav-pagination">
                            <?php for ($i = 1; $i <= $favTotalPages; $i++): ?>
                                <?php
                                    // build link and ensure chart param persists
                                    $qs = $_GET;
                                    $qs['fav_page'] = $i;
                                    $qs['chart'] = $chartDefault; // Đảm bảo biểu đồ vẫn hiển thị xuyên suốt các liên kết phân trang.
                                    $link = $_SERVER['PHP_SELF'] . '?' . http_build_query($qs);
                                ?>
                                <?php if ($i == $favPage): ?>
                                    <span class="active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// dữ liệu từ PHP
const monthsData   = <?= json_encode($monthsLabels) ?>;       
const revenueData  = <?= json_encode($revenueByMonth) ?>;     
const ordersData   = <?= json_encode($ordersByMonth) ?>;     
const catNamesData = <?= json_encode($categoryNames) ?>;
const catCountsData= <?= json_encode($productCounts) ?>;

// tab biểu đồ ban đầu từ máy chủ
const initialChart = <?= json_encode($chartDefault) ?>; // 'doanh thu' hoặc 'sản phẩm'

// format tiền VND
function formatVND(value){
    if (value === null || value === undefined) return value;
    const v = Math.round(Number(value));
    return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " ₫";
}

/* --Biểu đồ tổng (grouped bars)-- */
const revCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revCtx, {
    type: 'bar',
    data: {
        labels: monthsData,
        datasets: [
            {
                label: 'Doanh thu',
                data: revenueData,
                backgroundColor: '#8E5DF5',
                borderRadius: 8,
                barThickness: 18,
                yAxisID: 'y'
            },
            {
                label: 'Đơn hàng',
                data: ordersData,
                backgroundColor: '#4CAF50',
                borderRadius: 8,
                barThickness: 12,
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
                labels: { usePointStyle: true, pointStyle: 'rectRounded', padding: 12, font: { size: 12 } }
            },
            tooltip: {
                callbacks: {
                    label: function(context){
                        const label = context.dataset.label || '';
                        const val = context.parsed.y ?? context.raw ?? 0;
                        if (label === 'Doanh thu') {
                            return label + ': ' + formatVND(val);
                        } else {
                            return label + ': ' + val;
                        }
                    }
                }
            }
        },
        scales: {
            x: { stacked: false, grid: { display: false }, ticks: { font: { size: 12, weight: '600' } } },
            y: {
                type: 'linear', display: true, position: 'left',
                grid: { color: 'rgba(0,0,0,0.06)', drawBorder: false },
                ticks: { callback: function(v) { return formatVND(v); } },
                beginAtZero: true
            },
            y_right: {
                type: 'linear', display: true, position: 'right', grid: { display: false },
                ticks: { callback: function(v) { return v; } }, beginAtZero: true, offset: true
            }
        },
        layout: { padding: { top: 6, bottom: 6 } }
    }
});
/* --Biểu đồ danh mục sản phẩm-- */
const productCtx = document.getElementById('productChart').getContext('2d');
const productChart = new Chart(productCtx, {
    type: 'bar',
    data: {
        labels: catNamesData,
        datasets: [
            {
                label: 'Số lượng sản phẩm',
                data: catCountsData,
                backgroundColor: '#E91E63',
                barThickness: 28,
                borderRadius: 10,
                categoryPercentage: 0.9,
                barPercentage: 0.6
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: { usePointStyle: true, pointStyle: 'rectRounded', font: { size: 12 } }
            },
            tooltip: {
                callbacks: {
                    label: function(ctx){
                        return ctx.dataset.label + ': ' + ctx.parsed.y;
                    }
                }
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 12 }, maxRotation: 0, minRotation: 0 } },
            y: { grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false }, ticks: { beginAtZero: true } }
        }
    }
});

/* -- Chuyển đổi tab + lưu tham số biểu đồ trong URL -- */
const revenueWrapper  = document.getElementById('revenueChartWrapper');
const productWrapper  = document.getElementById('productChartWrapper');
const btnRevenue      = document.getElementById('btnRevenue');
const btnProduct      = document.getElementById('btnProduct');

function showRevenue() {
    revenueWrapper.style.display = 'block';
    productWrapper.style.display = 'none';
    if (typeof revenueChart !== 'undefined' && revenueChart) revenueChart.resize();
    updateUrlParam('chart', 'revenue');
    // hoạt động trực quan
    btnRevenue.style.opacity = '1';
    btnProduct.style.opacity = '0.85';
}

function showProduct() {
    revenueWrapper.style.display = 'none';
    productWrapper.style.display = 'block';
    if (typeof productChart !== 'undefined' && productChart) productChart.resize();
    updateUrlParam('chart', 'product');
    // các hoạt động trực quan
    btnProduct.style.opacity = '1';
    btnRevenue.style.opacity = '0.85';
}

btnRevenue.addEventListener('click', showRevenue);
btnProduct.addEventListener('click', showProduct);

// Cập nhật tham số truy vấn URL (biểu đồ) bằng cách sử dụng replaceState để các liên kết phân trang có thể bao gồm nó.
function updateUrlParam(key, value) {
    try {
        const url = new URL(window.location.href);
        url.searchParams.set(key, value);
        window.history.replaceState({}, '', url.toString());
    } catch (e) {
        console.warn('Cannot update URL param', e);
    }
}

// Khi tải trang: hiển thị biểu đồ ban đầu theo initialChart do máy chủ cung cấp.
// Điều này đảm bảo nếu các liên kết bao gồm chart=product, biểu đồ sản phẩm sẽ được hiển thị sau khi tải lại trang.
window.addEventListener('load', function() {
    if (initialChart === 'product') {
        showProduct();
    } else {
        showRevenue();
    }
});
</script>

</body>
</html>
