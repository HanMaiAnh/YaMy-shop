<?php
// admin/news.php
session_name('admin_session');
session_start();

// --- Kết nối DB PDO ---
require_once __DIR__ . '/../config/db.php'; // $pdo từ đây

// --- Kiểm tra quyền admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// helper an toàn
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ====== Params lọc / paging ====== //
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$q         = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$date_from = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
$date_to   = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';

$whereParts = [];
$params = [];

if ($q !== '') {
    $whereParts[] = "(title LIKE ? OR address LIKE ? OR infor LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($date_from !== '') {
    $whereParts[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $whereParts[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}
$whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

try {
    $countSql = "SELECT COUNT(*) FROM news $whereSQL";
    $stmtCount = $pdo->prepare($countSql);
    if (!empty($params)) {
        for ($i = 0; $i < count($params); $i++) {
            
            $stmtCount->bindValue($i + 1, $params[$i], PDO::PARAM_STR);
        }
    }
    $stmtCount->execute();
    $total_news = (int)$stmtCount->fetchColumn();
} catch (PDOException $e) {
    // fallback: nếu lỗi thì coi như 0
    $total_news = 0;
}

$total_pages = max(1, (int)ceil($total_news / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

// Chọn các hàng (liên kết vị trí + giới hạn bù)
$sql = "SELECT * FROM news $whereSQL ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);

// liên kết các tham số bộ lọc
$bindIndex = 1;
if (!empty($params)) {
    foreach ($params as $val) {
        $stmt->bindValue($bindIndex, $val, PDO::PARAM_STR);
        $bindIndex++;
    }
}
// ràng buộc giới hạn & bù trừ
$stmt->bindValue($bindIndex++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($bindIndex++, (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xây dựng chuỗi truy vấn và giữ lại để sử dụng cho các liên kết phân trang.
$keep = [];
if ($q !== '') $keep[] = 'q=' . urlencode($q);
if ($date_from !== '') $keep[] = 'date_from=' . urlencode($date_from);
if ($date_to !== '') $keep[] = 'date_to=' . urlencode($date_to);
$baseKeep = $keep ? implode('&', $keep) . '&' : '';

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý tin tức</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {margin:0; padding:0; box-sizing:border-box; font-family: 'Montserrat', sans-serif;}
        body {display:flex; background:#f5f6fa; color:#111; min-height:100vh;}
        .sidebar{
            width:260px;
            background:#fff;
            height:100vh;
            padding:30px 20px;
            position:fixed;
            border-right:1px solid #ddd;
        }
        .sidebar h3{font-size:22px; font-weight:700; margin-bottom:25px;}
        .sidebar a{
            display:flex; align-items:center; gap:10px; padding:12px; color:#333; text-decoration:none;
            border-radius:8px; margin-bottom:8px; transition:.25s; font-weight:500; font-size:15px;
        }
        .sidebar a:hover{ background:#f2e8ff; color:#8E5DF5; transform:translateX(4px); }
        .sidebar .logout{ color:#e53935; margin-top:20px; }
        .content {margin-left:280px; padding:30px; width:100%;}
        .page-header {display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;}
        .page-title {font-size:26px; font-weight:700; margin-bottom:8px;}
        .add-btn {background:#E91E63; color:#fff; padding:10px 14px; border-radius:8px; text-decoration:none; font-weight:600;}
        .add-btn:hover {background:#ff4081;}
        .filter-bar { display:flex; margin-top:12px; margin-bottom:18px; width:100%; }
        .filter-bar form { width:100%; display:flex; align-items:center; gap:12px; }
        .filter-input {
    padding:12px 14px;
    border:1px solid #ddd;
    border-radius:8px;
    font-size:15px;
    background:#fff;
    flex:0 0 auto;          
    width:350px;            
    max-width:100%;        
}
        .date-input {
            padding:12px;
            border:1px solid #ddd;
            border-radius:8px;
            font-size:14px;
            width:160px;
        }

        .btn-filter {
            padding:12px 16px;
            background:#8E5DF5;
            border:none;
            color:#fff;
            border-radius:8px;
            font-weight:700;
            cursor:pointer;
        }

        .btn-reset {
            padding:12px 16px;
            background:#EF4444;
            color:#fff;
            border-radius:8px;
            font-weight:700;
            text-decoration:none;
        }

        .table-card { background:#fff; border-radius:14px; padding:14px; margin-top:18px; border:1px solid #eee; }
        table { width:100%; border-collapse:collapse; }
        thead th { background:#faf8ff; padding:14px; text-align:left; font-weight:700; color:#333; }
        td { padding:12px; border-bottom:1px solid #f1f1f1; vertical-align:middle; }
        tr:hover { background:#f9f9f9; }
        .thumb-img { width:80px; height:60px; object-fit:cover; border-radius:8px; }
        .short-text { max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#333; }
        .meta-small { font-size:13px; color:#666; margin-top:6px; }
        .actions { display:flex; gap:8px; align-items:center; }
        .btn-edit { background:#03A9F4; padding:8px 12px; border-radius:8px; color:#fff; text-decoration:none; }
        .pagination { text-align:center; margin-top:14px; }
        .pagination a { display:inline-block; padding:8px 12px; margin:3px; border-radius:6px; background:#111; color:#fff; text-decoration:none; }
        .pagination a.active { background:#8E5DF5; }

        @media (max-width:900px){
            .filter-bar form { flex-direction:column; align-items:stretch; }
            .date-input { width:100%; }
            .filter-input { width:100%; }
            .page-header { flex-direction:column; align-items:flex-start; gap:10px; }
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
            <div>
                <div class="page-title">Quản lý tin tức</div>
            </div>
            <div>
                <a href="news_add.php" class="add-btn">+ Thêm tin tức</a>
            </div>
        </div>

        <div class="filter-bar">
            <form method="get" action="news.php">
                <input type="text" name="q" class="filter-input" placeholder="Tìm tin tức"
                       value="<?= h($q) ?>">

                <input type="date" name="date_from" class="date-input" value="<?= h($date_from) ?>">
                <input type="date" name="date_to" class="date-input" value="<?= h($date_to) ?>">

                <button type="submit" class="btn-filter">Lọc</button>
                <a href="news.php" class="btn-reset">Reset</a>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th style="width:6%;">ID</th>
                        <th style="width:12%;">Ảnh</th>
                        <th>Tiêu đề</th>
                        <th>Địa chỉ / infor</th>
                        <th style="width:14%;">Ngày tạo</th>
                        <th style="width:12%;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($newsList)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:20px; color:#666;">Không tìm thấy tin tức phù hợp.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($newsList as $n): ?>
                            <tr>
                                <td><?= (int)$n['id'] ?></td>
                                <td>
                                    <?php if (!empty($n['image'])): $img = h($n['image']); ?>
                                        <img class="thumb-img" src="<?= $img ?>" alt="thumb" onerror="this.onerror=null;this.src='../uploads/no-image.png'">
                                    <?php else: ?>
                                        --
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:700;"><?= h($n['title'] ?? '--') ?></div>
                                    <div class="meta-small">Người đăng: <?= h($n['author'] ?? '-') ?></div>
                                </td>
                                <td class="short-text"><?= h($n['address'] ?: $n['infor'] ?: '--') ?></td>
                                <td><?= h($n['created_at']) ?></td>
                                <td>
                                    <div class="actions">
                                        <a class="btn-edit" href="news_edit.php?id=<?= (int)$n['id'] ?>"><i class="fa fa-pen"></i> Sửa</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination" style="margin-top:16px;">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?<?= $baseKeep ?>page=<?= $i ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</body>
</html>
