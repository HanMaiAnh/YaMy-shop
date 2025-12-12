<?php
session_name('admin_session');
session_start();

// --- Kết nối DB PDO ---
require_once __DIR__ . '/../config/db.php'; // $pdo từ đây

// --- Kiểm tra quyền admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// ====== Lấy dữ liệu lọc ======
$qRaw = trim($_GET['q'] ?? '');
$roleFilter = trim($_GET['role'] ?? 'all');

// ====== Build WHERE POSitional ======
$whereParts = [];
$params = [];

// Lọc theo vai trò
if ($roleFilter !== 'all') {
    $whereParts[] = "role = ?";
    $params[] = $roleFilter;
}

// Tìm kiếm toàn diện
if ($qRaw !== '') {
    $qLike = '%' . $qRaw . '%';
    $or = [];

    // username, email, phone LIKE
    $or[] = "username LIKE ?";
    $params[] = $qLike;

    $or[] = "email LIKE ?";
    $params[] = $qLike;

    $or[] = "phone LIKE ?";
    $params[] = $qLike;

    // Giới tính hỗ trợ: Nam / Nữ
    if (mb_strtolower($qRaw, 'UTF-8') === 'nam') {
        $or[] = "sex = ?";
        $params[] = 'male';
    } elseif (mb_strtolower($qRaw, 'UTF-8') === 'nữ' || mb_strtolower($qRaw, 'UTF-8') === 'nu') {
        $or[] = "sex = ?";
        $params[] = 'female';
    }

    // Tìm theo ID
    if (ctype_digit($qRaw)) {
        $or[] = "id = ?";
        $params[] = (int)$qRaw;
    }

    $whereParts[] = "(" . implode(" OR ", $or) . ")";
}

$whereSql = empty($whereParts) ? "" : "WHERE " . implode(" AND ", $whereParts);

// ====== Phân trang ======
$limit = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Tổng số user
$countSql = "SELECT COUNT(*) FROM users $whereSql";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalUsers = (int)$stmt->fetchColumn();
$totalPages = max(1, ceil($totalUsers / $limit));

// Lấy danh sách user
$sql = "SELECT * FROM users $whereSql ORDER BY id DESC LIMIT ? OFFSET ?";
$dataParams = array_merge($params, [$limit, $offset]);

$stmt = $pdo->prepare($sql);
$stmt->execute($dataParams);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản lý người dùng</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* GIỮ NGUYÊN PHONG CÁCH NHƯ BẠN ĐANG DÙNG */
* {margin:0; padding:0; box-sizing:border-box; font-family: 'Montserrat', sans-serif;}
body {display:flex; background:#f5f6fa; color:#111;}
.sidebar{
    width:260px; background:#fff; height:100vh;
    padding:30px 20px; position:fixed; border-right:1px solid #ddd;
}
.sidebar h3{ font-size:22px; font-weight:700; margin-bottom:25px; }
.sidebar a{
    display:flex; align-items:center; gap:10px;
    padding:12px; color:#333; text-decoration:none;
    border-radius:8px; margin-bottom:8px; transition:.25s;
    font-weight:500; font-size:15px;
}
.sidebar a:hover{
    background:#f2e8ff; color:#8E5DF5; transform:translateX(4px);
}
.sidebar .logout{color:#e53935;margin-top:20px;}
.content{margin-left:280px;padding:30px;width:100%;}
.page-title{font-size:26px;font-weight:700;margin-bottom:20px;}

/* ROW chứa filter + nút thêm */
.header-row{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    margin-bottom:18px;
}
.filter-row{
    display:flex; gap:12px; flex-wrap:wrap; align-items:center;
}
.input, .select{
    padding:10px 14px; border-radius:8px; border:1px solid #ddd;
    background:#fff; font-size:14px;
}
.btn-primary{
    padding:10px 14px; background:#6C4CF0;color:#fff;
    border:none;border-radius:8px;font-weight:700;cursor:pointer;
}
.btn-danger{
    padding:10px 14px; background:#f24545;color:#fff;
    border:none;border-radius:8px;font-weight:700;cursor:pointer;
}

/* nút thêm mới (màu hồng giống trang khác) */
.btn-add{
    display:inline-block;
    padding:10px 16px;
    background:#E91E63;
    color:#fff;
    border-radius:12px;
    font-weight:700;
    text-decoration:none;
    box-shadow:0 6px 18px rgba(255,47,132,0.12);
    transition:transform .15s ease, box-shadow .15s ease;
}
.btn-add:hover{ transform:translateY(-2px); box-shadow:0 10px 22px rgba(255,47,132,0.16); }

table {
    width:100%; border-collapse:collapse; background:#fff;
    border-radius:14px; overflow:hidden;
}
th {
    background:#8E5DF5; color:#fff; padding:14px; text-align:center;
}
td {
    padding:14px; text-align:center; border-bottom:1px solid #ddd;
}
tr:hover { background:#f9f9f9; }

.status-active {color:#4CAF50;font-weight:600;}
.status-locked {color:#ff4d4d;font-weight:600;}

.btn-edit{ background:#03A9F4;padding:6px 12px;border-radius:6px;color:#fff;text-decoration:none; }
.btn-edit:hover{ background:#0288D1; }

.pagination{text-align:center;margin-top:18px;}
.pagination a{
    color:#fff;padding:8px 12px;border-radius:6px;background:#1a1a1a;
    margin:3px;text-decoration:none;
}
.pagination a.active{background:#E91E63;}
</style>
</head>
<body>

<!-- SIDEBAR -->
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

<!-- CONTENT -->
<div class="content">

    <h1 class="page-title">Quản lý người dùng</h1>

    <!-- HEADER: bao gồm thanh tìm + dropdown (bên trái) và Nút Thêm (bên phải) -->
    <div class="header-row">

        <!-- LEFT: FORM LỌC -->
        <form method="get" style="flex:1;">
            <div class="filter-row" style="align-items:center;">
                <input type="text" name="q" class="input"
                    placeholder="Tìm người dùng"
                    value="<?= htmlspecialchars($qRaw, ENT_QUOTES, 'UTF-8') ?>">

                <select name="role" class="select">
                    <option value="all" <?= $roleFilter==='all'?'selected':'' ?>>Tất cả vai trò</option>
                    <option value="User" <?= $roleFilter==='User'?'selected':'' ?>>User</option>
                    <option value="Admin" <?= $roleFilter==='Admin'?'selected':'' ?>>Admin</option>
                </select>

                <button class="btn-primary" type="submit">Lọc</button>
                <button type="button" id="btnReset" class="btn-danger">Reset</button>
            </div>
        </form>

        <!-- RIGHT: NÚT THÊM (chỉ hiển thị cho admin) -->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div>
                <a href="user_add.php" class="btn-add">+ Thêm người dùng</a>
            </div>
        <?php endif; ?>

    </div>

    <!-- TABLE -->
    <table>
        <tr>
            <th>ID</th>
            <th>Tên đăng nhập</th>
            <th>Email</th>
            <th>SĐT</th>
            <th>Giới tính</th>
            <th>Vai trò</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
        </tr>

        <?php if (!empty($users)): ?>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($u['phone'], ENT_QUOTES, 'UTF-8') ?></td>

                <td>
                    <?= ($u['sex']=='male'?'Nam':($u['sex']=='female'?'Nữ':'--')) ?>
                </td>

                <td><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></td>

                <td>
                    <?= ($u['active']==1
                        ? '<span class="status-active">Hoạt động</span>'
                        : '<span class="status-locked">Khoá</span>'
                    ) ?>
                </td>

                <td><a class="btn-edit" href="user_detail.php?id=<?= urlencode($u['id']) ?>">Chi tiết</a></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8">Không có người dùng nào.</td>
            </tr>
        <?php endif; ?>

    </table>

    <!-- PAGINATION -->
    <div class="pagination">
        <?php for($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?q=<?= urlencode($qRaw) ?>&role=<?= urlencode($roleFilter) ?>&page=<?= $i ?>"
                class="<?= ($i==$page)?'active':'' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>

</div>

<script>
// RESET FILTER
document.getElementById('btnReset').addEventListener('click', () => {
    window.location = "users.php";
});
</script>

</body>
</html>
