<?php
session_start();

// --- Kết nối DB PDO ---
require_once __DIR__ . '/../config/db.php'; // $pdo từ đây

// --- Kiểm tra quyền admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// --- Lấy tên người dùng hiện tại ---
$username = $_SESSION['username'] ?? 'Admin';
$username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

// ====== Phân trang ====== //
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// --- Tổng số tin tức --- //
$total_sql = "SELECT COUNT(*) FROM news";
$stmt = $pdo->query($total_sql);
$total_news = $stmt->fetchColumn();
$total_pages = ceil($total_news / $limit);

// --- Lấy danh sách tin tức --- //
$sql = "SELECT * FROM news ORDER BY id DESC LIMIT :offset, :limit";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý tin tức</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {margin:0; padding:0; box-sizing:border-box; font-family: 'Montserrat', sans-serif;}
        body {display:flex; background:#f5f6fa; color:#111;}
        .sidebar {width:260px;height:100vh;background:#fff;padding:30px 20px;position:fixed;border-right:1px solid #ddd;}
        .sidebar h3 {color:#111;margin-bottom:25px;font-weight:700;font-size:20px;text-transform:uppercase;letter-spacing:2px;}
        .sidebar a {display:flex;align-items:center;gap:10px;color:#111;padding:12px;border-radius:8px;text-decoration:none;font-size:15px;margin-bottom:5px;transition:0.25s;}
        .sidebar a:hover {color:#8E5DF5;background:#f0e8ff;transform:translateX(5px);}
        .content {margin-left:280px;padding:30px;width:100%;}
        .page-header {display:flex;justify-content:space-between;align-items:center;}
        .page-title {font-size:26px;font-weight:700;}
        .add-btn {background:#E91E63;color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:600;}
        .add-btn:hover {background:#ff4081;}
        table {width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;margin-top:20px;}
        th {background:#8E5DF5;padding:14px;text-align:center;font-weight:600;color:#fff;}
        td {padding:14px;text-align:center;border-bottom:1px solid #ddd;vertical-align:middle;}
        tr:hover {background:#f9f9f9;}
        .btn-edit {background:#03A9F4;padding:6px 12px;border-radius:6px;color:#fff;text-decoration:none;font-size:14px;margin-right:6px;display:inline-block;}
        .btn-edit:hover {background:#0288D1;}
        .btn-delete {background:#ff4d4d;padding:6px 12px;border-radius:6px;color:#fff;text-decoration:none;font-size:14px;display:inline-block;}
        .btn-delete:hover {background:#e60000;}
        .pagination {text-align:center;margin-top:18px;}
        .pagination a {color:#fff;padding:8px 12px;border-radius:6px;background:#1a1a1a;margin:3px;text-decoration:none;}
        .pagination a.active {background:#E91E63;}
        .thumb-img {width:80px;height:60px;object-fit:cover;border-radius:8px;}
        .short-text {max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    </style>
</head>
<body>
<div class="sidebar">
    <h3>YAMY ADMIN</h3>
    <a href="/clothing_store/admin/dashboard.php"><i class="fa fa-gauge"></i> Trang Quản Trị</a>
    <a href="/clothing_store/admin/orders.php"><i class="fa fa-shopping-cart"></i> Quản lý đơn hàng</a>
    <a href="/clothing_store/admin/users.php"><i class="fa fa-user"></i> Quản lý người dùng</a>
    <a href="/clothing_store/admin/products.php"><i class="fa fa-box"></i> Quản lý sản phẩm</a>
    <a href="/clothing_store/admin/news.php"><i class="fa fa-newspaper"></i> Quản lý tin tức</a>
    <a href="/clothing_store/view/logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">Quản lý tin tức</h1>
        <a href="news_add.php" class="add-btn">+ Thêm tin tức</a>
    </div>

    <table>
        <tr>
            <th>ID</th>
            <th>Tiêu đề</th>
            <th>Ảnh</th>
            <th>Địa chỉ / nội dung ngắn</th>
            <th>Ngày tạo</th>
            <th>Hành động</th>
        </tr>
        <?php if (empty($newsList)): ?>
            <tr>
                <td colspan="6">Chưa có tin tức nào.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($newsList as $n): ?>
                <tr>
                    <td><?= (int)$n['id'] ?></td>

                    <td class="short-text">
                        <?= htmlspecialchars($n['title'] ?? '--', ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <td>
                        <?php if (!empty($n['image'])): ?>
                            <img class="thumb-img"
                            src="<?= htmlspecialchars($n['image'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="thumb">
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>

                    <td class="short-text">
                        <?php
                        // ưu tiên cột address, nếu rỗng thì dùng infor
                        $addr = $n['address'] ?? '';
                        $info = $n['infor'] ?? '';
                        $text = $addr !== '' ? $addr : $info;
                        echo htmlspecialchars($text ?: '--', ENT_QUOTES, 'UTF-8');
                        ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($n['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <td>
                        <a href="news_edit.php?id=<?= (int)$n['id'] ?>" class="btn-edit">
                            <i class="fa fa-pen"></i> Sửa
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?= $i ?>" class="<?= ($i == $page) ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
</div>
</body>
</html>
