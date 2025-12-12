<?php 
session_name('admin_session');
session_start();

// --- Kết nối DB PDO ---
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

// --- Kiểm tra quyền admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
$username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

/* Lọc danh mục + tìm theo tên*/
$filter    = $_GET['filter'] ?? 'all';
$keyword   = trim((string)($_GET['keyword'] ?? ''));

$where_sql = "";
// chỉ lưu giá trị keyword để bind nếu cần
$kw_value = null;

if ($filter === 'root') {
    $where_sql = "WHERE (c.parent_id IS NULL OR c.parent_id = 0)";
} elseif ($filter === 'child') {
    $where_sql = "WHERE (c.parent_id IS NOT NULL AND c.parent_id <> 0)";
}

// Nếu có từ khóa, hãy thêm điều kiện và chuẩn bị tham số (có tên là :kw)
if ($keyword !== '') {
    if ($where_sql === '') {
        $where_sql = "WHERE c.name LIKE :kw";
    } else {
        $where_sql .= " AND c.name LIKE :kw";
    }
    $kw_value = '%' . $keyword . '%';
}

/*Phân trang*/
$limit  = 10;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

/*Tổng số danh mục (bind :kw trực tiếp nếu cần)*/
$total_sql = "SELECT COUNT(*) FROM categories c $where_sql";
$total_stmt = $pdo->prepare($total_sql);

// Chỉ liên kết :kw nếu tồn tại
if ($kw_value !== null) {
    $total_stmt->bindValue(':kw', $kw_value, PDO::PARAM_STR);
}
$total_stmt->execute();
$total_categories = (int)$total_stmt->fetchColumn();
$total_pages      = max(1, ceil($total_categories / $limit));

/*Lấy danh sách (main query), dùng named param :kw nếu cần, offset/limit gán trực tiếp bằng bindValue (int)*/
$sql = "SELECT 
            c.id,
            c.name,
            c.parent_id,
            c.sort_order,
            p.name AS parent_name
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        $where_sql
        ORDER BY c.sort_order ASC, c.id ASC
        LIMIT :offset, :limit";

$stmt = $pdo->prepare($sql);

// Liên kết offset/limit an toàn dưới dạng số nguyên
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);

// liên kết :kw nếu có
if ($kw_value !== null) {
    $stmt->bindValue(':kw', $kw_value, PDO::PARAM_STR);
}

$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*trợ giúp hiển thị*/
function build_qs(array $extra = []): string {
    $base = [
        'filter'  => $_GET['filter'] ?? 'all',
        'keyword' => $_GET['keyword'] ?? '',
    ];
    $merged = array_merge($base, $extra);
    $pairs = [];
    foreach ($merged as $k => $v) {
        if ($v === '' || $v === null) continue;
        $pairs[] = urlencode($k) . '=' . urlencode($v);
    }
    return $pairs ? implode('&', $pairs) : '';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản lý danh mục</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {margin:0; padding:0; box-sizing:border-box; font-family: 'Montserrat', sans-serif;}
body {display:flex; background:#f5f6fa; color:#111;}
.sidebar{
    width:260px;
    background:#fff;
    height:100vh;
    padding:30px 20px;
    position:fixed;
    border-right:1px solid #ddd;
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
.content {margin-left:280px;padding:30px;width:100%;}
.page-header {display:flex;justify-content:space-between;align-items:center;}
.page-title {font-size:26px;font-weight:700;}
.add-btn {
    background:#E91E63;
    color:#fff;
    padding:10px 14px;
    border-radius:8px;
    text-decoration:none;
    font-weight:600;
    white-space:nowrap;
    margin-left: auto; 
}


/*  thanh lọc */
.filter-bar {
    margin-top:20px;
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    justify-content:flex-start;
}

.filter-select {
    min-width:220px;
    padding:10px 12px;
    border-radius:8px;
    border:1px solid #e6e6e6;
    background:#fff;
    font-size:14px;
}
.filter-input {
    padding:10px 14px;
    border-radius:8px;
    border:1px solid #e6e6e6;
    background:#fff;
    min-width:320px;
    font-size:14px;
}
.filter-actions {
    display:flex;
    gap:8px;
    align-items:center;
}
.btn-filter {
    background:#6C4CF0;
    color:#fff;
    padding:10px 14px;
    border-radius:8px;
    border:none;
    font-weight:700;
    cursor:pointer;
}
.btn-reset {
    background:#f24545;
    color:#fff;
    padding:10px 14px;
    border-radius:8px;
    border:none;
    font-weight:700;
    cursor:pointer;
}
table {width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;margin-top:20px;}
th {background:#8E5DF5;padding:14px;text-align:center;font-weight:600;color:#fff;font-size:14px;}
td {padding:14px;text-align:center;border-bottom:1px solid #ddd;font-size:14px;}
tr:hover {background:#f9f9f9;}
.parent-root {font-weight:600;color:#4CAF50;}
.btn-edit {background:#03A9F4;padding:6px 12px;border-radius:6px;color:#fff;text-decoration:none;font-size:14px;}
.btn-edit:hover {background:#0288D1;}
.btn-delete {background:#E53935;padding:6px 12px;border-radius:6px;color:#fff;text-decoration:none;font-size:14px;margin-left:4px;}
.btn-delete:hover {background:#C62828;}
.pagination {text-align:center;margin-top:18px;}
.pagination a {color:#fff;padding:8px 12px;border-radius:6px;background:#1a1a1a;margin:3px;text-decoration:none;font-size:13px;}
.pagination a.active {background:#E91E63;}

@media (max-width:900px){
    .filter-bar{flex-direction:column;align-items:flex-start;}
    .filter-input{min-width:100%;}
    .filter-select{min-width:100%;}
    .filter-actions{width:100%;justify-content:flex-start}
    .add-btn{width:100%;text-align:center}
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
    <a href="comments.php"><i class="fa fa-comment"></i> Quản lý bình luận</a>
    <a href="logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">Quản lý danh mục</h1>
       
    </div>

    <!-- Filter + Keyword -->
    <form method="get" class="filter-bar" action="categories.php" id="filterForm">
         <input type="text" name="keyword" class="filter-input" placeholder="Tìm danh mục" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" aria-label="Tìm kiếm theo tên">
        <select name="filter" class="filter-select" aria-label="Bộ lọc danh mục">
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tất cả</option>
            <option value="root" <?= $filter === 'root' ? 'selected' : '' ?>>Danh mục gốc</option>
            <option value="child" <?= $filter === 'child' ? 'selected' : '' ?>>Danh mục con</option>
        </select>

       

        <div class="filter-actions">
            <button type="submit" class="btn-filter">Lọc</button>
            <button type="button" id="btnReset" class="btn-reset">Reset</button>
        </div>
         <a href="categories_add.php" class="add-btn">+ Thêm danh mục</a>
    </form>

    <table>
        <tr>
            <th>ID</th>
            <th>Tên danh mục</th>
            <th>Danh mục cha</th>
            <th>Thứ tự sắp xếp</th>
            <th>Hành động</th>
        </tr>

        <?php if ($categories): ?>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if (empty($c['parent_id'])): ?>
                            <span class="parent-root">Danh mục gốc</span>
                        <?php else: ?>
                            <?= htmlspecialchars($c['parent_name'] ?? ('ID: ' . $c['parent_id']), ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$c['sort_order'] ?></td>
                    <td>
                        <a href="categories_edit.php?id=<?= (int)$c['id'] ?>" class="btn-edit">Sửa</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5">Không có danh mục nào.</td></tr>
        <?php endif; ?>
    </table>

    <div class="pagination">
        <?php
        // giữ lại filter & keyword khi chuyển trang
        for ($i = 1; $i <= $total_pages; $i++):
            $qs = ['filter'=>$filter, 'page'=>$i];
            if ($keyword !== '') $qs['keyword'] = $keyword;
            $query = http_build_query($qs);
        ?>
            <a href="?<?= htmlspecialchars($query) ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>

<script>
// Lệnh Reset xóa và gửi (áp dụng cho tất cả)
document.getElementById('btnReset').addEventListener('click', function(){
    const form = document.getElementById('filterForm');
    if (!form) return;
    form.querySelector('select[name="filter"]').value = 'all';
    form.querySelector('input[name="keyword"]').value = '';
    form.submit();
});
</script>
</body>
</html>
