<?php
session_name('admin_session');
session_start();

require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
$username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

/* -----------------------
   Helpers
   ----------------------- */

// kiểm tra column tồn tại (safe)
function columnExists(PDO $pdo, string $table, string $col): bool {
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :db AND table_name = :table AND column_name = :col");
        $s->execute([':db' => $db, ':table' => $table, ':col' => $col]);
        return ((int)$s->fetchColumn() > 0);
    } catch (Exception $e) {
        return false;
    }
}

/* -----------------------
   Read inputs
   ----------------------- */
$filter    = $_GET['filter'] ?? 'all';
$q         = trim((string)($_GET['q'] ?? ''));
$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to   = trim((string)($_GET['date_to'] ?? ''));

$allowedFilters = ['all','active','expired','upcoming','out'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'all';

/* -----------------------
   Build WHERE using positional placeholders ( ? )
   order in $params must match SQL
   ----------------------- */
$conds = [];
$params = [];
$today = date('Y-m-d');

// Dropdown filter explicit (if user selected one)
if ($filter === 'active') {
    $conds[] = "begin <= ? AND expired >= ? AND quantity > 0";
    $params[] = $today;
    $params[] = $today;
} elseif ($filter === 'expired') {
    $conds[] = "expired < ?";
    $params[] = $today;
} elseif ($filter === 'upcoming') {
    $conds[] = "begin > ?";
    $params[] = $today;
} elseif ($filter === 'out') {
    $conds[] = "quantity <= 0";
}

// Build search condition for q
if ($q !== '') {
    $qLower = mb_strtolower($q, 'UTF-8');

    // prepare OR parts for search: status OR code OR name (if exists)
    $searchParts = [];
    $searchParams = [];

    // detect status keyword only when filter == all (avoid contradiction)
    if ($filter === 'all') {
        if (mb_strpos($qLower, 'đang') !== false || mb_strpos($qLower, 'dang') !== false || mb_strpos($qLower, 'active') !== false) {
            // status = active
            $searchParts[] = "(begin <= ? AND expired >= ? AND quantity > 0)";
            $searchParams[] = $today;
            $searchParams[] = $today;
        } elseif (mb_strpos($qLower, 'hết lượt') !== false || mb_strpos($qLower, 'het luot') !== false || mb_strpos($qLower, 'out') !== false) {
            $searchParts[] = "(quantity <= 0)";
            // no params
        } elseif (mb_strpos($qLower, 'hết') !== false || mb_strpos($qLower, 'het') !== false || mb_strpos($qLower, 'expired') !== false) {
            $searchParts[] = "(expired < ?)";
            $searchParams[] = $today;
        } elseif (mb_strpos($qLower, 'chưa') !== false || mb_strpos($qLower, 'chua') !== false || mb_strpos($qLower, 'upcoming') !== false) {
            $searchParts[] = "(begin > ?)";
            $searchParams[] = $today;
        }
    }

    // search in code
    if (columnExists($pdo, 'vouchers', 'code')) {
        $searchParts[] = "code LIKE ?";
        $searchParams[] = '%' . $q . '%';
    }
    // search in name if exists
    if (columnExists($pdo, 'vouchers', 'name')) {
        $searchParts[] = "name LIKE ?";
        $searchParams[] = '%' . $q . '%';
    }

    // if we have any search parts, combine them with OR and add to main conds
    if (!empty($searchParts)) {
        $conds[] = '(' . implode(' OR ', $searchParts) . ')';
        // append searchParams in same order
        foreach ($searchParams as $p) $params[] = $p;
    }
}

// Date filters (straightforward)
if ($date_from !== '') {
    $conds[] = "begin >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $conds[] = "expired <= ?";
    $params[] = $date_to;
}

// Build WHERE SQL
$where_sql = '';
if (!empty($conds)) {
    $where_sql = 'WHERE ' . implode(' AND ', $conds);
}

/* -----------------------
   Pagination
   ----------------------- */
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

/* -----------------------
   Count total (bind params in same order)
   ----------------------- */
$total_sql = "SELECT COUNT(*) FROM vouchers $where_sql";
$total_stmt = $pdo->prepare($total_sql);
$total_stmt->execute($params);
$total_vouchers = (int)$total_stmt->fetchColumn();
$total_pages = max(1, ceil($total_vouchers / $limit));

/* -----------------------
   Main select (LIMIT directly)
   ----------------------- */
$sql = "SELECT * FROM vouchers $where_sql ORDER BY id DESC LIMIT {$offset}, {$limit}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------
   Helpers display
   ----------------------- */
function getVoucherStatus(array $v): string {
    $today = date('Y-m-d');
    $begin = $v['begin'] ?? null;
    $expired = $v['expired'] ?? null;
    $qty = (int)($v['quantity'] ?? 0);
    if ($expired && $expired < $today) return 'expired';
    if ($qty <= 0) return 'out';
    if ($begin && $begin > $today) return 'upcoming';
    return 'active';
}

function build_qs(array $extra = []): string {
    $base = [
        'filter'    => $_GET['filter'] ?? 'all',
        'q'         => $_GET['q'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to'] ?? '',
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
<meta charset="utf-8">
<title>Quản lý vouchers</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ===== full CSS (keeps your desired look) ===== */
* {margin:0; padding:0; box-sizing:border-box; font-family: 'Montserrat', sans-serif;}
body {display:flex; background:#f5f6fa; color:#111;}
.sidebar{ width:260px; background:#fff; height:100vh; padding:30px 20px; position:fixed; border-right:1px solid #ddd; }
.sidebar h3{ font-size:22px; margin-bottom:25px; }
.sidebar a{ display:flex; align-items:center; gap:10px; padding:12px; color:#333; text-decoration:none; border-radius:8px; margin-bottom:8px; transition:.25s; font-weight:500; font-size:15px;}
.sidebar a:hover{ background:#f2e8ff; color:#8E5DF5; transform:translateX(4px); }
.content {margin-left:280px;padding:30px;width:100%;}
.page-header {display:flex;justify-content:space-between;align-items:center;}
.page-title {font-size:26px;font-weight:700;}
.add-btn {background:#E91E63;color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:600;}
.add-btn:hover {background:#ff4081;}
/* Search bar */
.search-bar { margin-top:20px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; justify-content:flex-start; }
.select-filter { min-width:140px; padding:10px 12px; border-radius:8px; border:1px solid #eee; background:#fff; font-weight:600; }
.input { padding:10px 14px; border-radius:8px; border:1px solid #eee; background:#fff; min-width:240px; font-size:14px; }
.input.date { min-width:160px; }
.search-group { display:flex; gap:12px; align-items:center; }
.controls { display:flex; gap:8px; align-items:center; }
.btn-search { background:#6C4CF0; color:#fff; padding:10px 14px; border-radius:8px; border:none; font-weight:700; cursor:pointer; }
.btn-reset  { background:#f24545; color:#fff; padding:10px 14px; border-radius:8px; border:none; font-weight:700; cursor:pointer; text-decoration:none; }
/* Table */
table {width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;margin-top:20px;}
th {background:#8E5DF5;padding:14px;text-align:center;font-weight:600;color:#fff;}
td {padding:14px;text-align:center;border-bottom:1px solid #eee;font-size:14px;}
tr:hover {background:#faf8ff;}
.status-active   {color:#4CAF50;font-weight:600;}
.status-expired  {color:#ff4d4d;font-weight:600;}
.status-upcoming {color:#ff9800;font-weight:600;}
.status-out      {color:#9e9e9e;font-weight:600;}
.btn-edit {background:#03A9F4;padding:6px 12px;border-radius:6px;color:#fff;text-decoration:none;font-size:14px;}
.pagination {text-align:center;margin-top:18px;}
.pagination a {color:#fff;padding:8px 12px;border-radius:6px;background:#1a1a1a;margin:3px;text-decoration:none;}
.pagination a.active {background:#E91E63;}
@media (max-width:900px){
    .search-bar{flex-direction:column;align-items:flex-start;}
    .search-group{flex-direction:column;align-items:stretch;}
    .search-group .input{min-width:100%;}
    .controls{width:100%;justify-content:flex-start}
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
        <h1 class="page-title">Quản lý vouchers</h1>
        <a href="vouchers_add.php" class="add-btn">+ Thêm voucher</a>
    </div>

    <form method="get" class="search-bar" action="vouchers.php" novalidate>
        <div class="search-group">
            <input type="text" name="q" class="input" placeholder="Tìm vouchers" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <select name="filter" class="select-filter" onchange="this.form.submit()">
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tất cả</option>
            <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Đang diễn ra</option>
            <option value="upcoming" <?= $filter === 'upcoming' ? 'selected' : '' ?>>Chưa bắt đầu</option>
            <option value="expired" <?= $filter === 'expired' ? 'selected' : '' ?>>Đã hết hạn</option>
            <option value="out" <?= $filter === 'out' ? 'selected' : '' ?>>Hết lượt</option>
        </select>

        <div class="search-group">
            <input type="date" name="date_from" class="input date" value="<?= htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8') ?>">
            <input type="date" name="date_to" class="input date" value="<?= htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="controls">
            <button type="submit" class="btn-search">Lọc</button>
            <button type="button" id="btnReset" class="btn-reset">Reset</button>
        </div>
    </form>

    <table>
        <tr>
            <th>ID</th>
            <th>Mã voucher</th>
            <th>Giá trị (%)</th>
            <th>Giảm tiền cố định</th>
            <th>Đơn tối thiểu</th>
            <th>Số lượng</th>
            <th>Bắt đầu</th>
            <th>Kết thúc</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
        </tr>

        <?php if ($vouchers): foreach ($vouchers as $v):
            $status = getVoucherStatus($v);
            $statusLabel = [
                'active'   => '<span class="status-active">Đang diễn ra</span>',
                'expired'  => '<span class="status-expired">Đã hết hạn</span>',
                'upcoming' => '<span class="status-upcoming">Chưa bắt đầu</span>',
                'out'      => '<span class="status-out">Hết lượt</span>',
            ][$status];
        ?>
            <tr>
                <td><?= (int)$v['id'] ?></td>
                <td><?= htmlspecialchars($v['code'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= number_format((float)$v['value'], 0, '', '.') ?></td>
                <td><?= $v['amount_reduced'] !== null ? number_format((float)$v['amount_reduced'], 0, '', '.') : '-' ?></td>
                <td><?= number_format((float)$v['minimum_value'], 0, '', '.') ?></td>
                <td><?= (int)$v['quantity'] ?></td>
                <td><?= htmlspecialchars($v['begin']) ?></td>
                <td><?= htmlspecialchars($v['expired']) ?></td>
                <td><?= $statusLabel ?></td>
                <td><a href="vouchers_edit.php?id=<?= (int)$v['id'] ?>" class="btn-edit">Sửa</a></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="10">Không có voucher nào.</td></tr>
        <?php endif; ?>
    </table>

    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): $qs = build_qs(['page' => $i]); ?>
            <a href="?<?= $qs ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>

<script>
// Reset clears the inputs and submits
document.getElementById('btnReset').addEventListener('click', function(){
    const form = this.closest('form');
    if (!form) return;
    form.querySelectorAll('input[type="text"], input[type="date"]').forEach(i=>i.value='');
    form.querySelectorAll('select').forEach(s=>s.value='all');
    form.submit();
});
</script>
</body>
</html>
