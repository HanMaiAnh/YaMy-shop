<?php
session_name('admin_session');
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';

$sizeError = $sizeSuccess = '';
$colorError = $colorSuccess = '';

// ================== XỬ LÝ FORM ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';   // size | color
    $mode = $_POST['mode'] ?? '';   // add  | edit
    $name = trim($_POST['name'] ?? '');
    $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($type === 'size') {
        if ($name === '') {
            $sizeError = 'Tên size không được để trống.';
        } else {
            try {
                if ($mode === 'add') {
                    // kiểm tra trùng
                    $check = $pdo->prepare("SELECT COUNT(*) FROM sizes WHERE name = :name");
                    $check->execute([':name' => $name]);
                    if ($check->fetchColumn() > 0) {
                        $sizeError = 'Size này đã tồn tại.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO sizes (name) VALUES (:name)");
                        $stmt->execute([':name' => $name]);
                        $sizeSuccess = 'Thêm size thành công.';
                    }
                } elseif ($mode === 'edit' && $id > 0) {
                    // kiểm tra trùng (trừ chính nó)
                    $check = $pdo->prepare("SELECT COUNT(*) FROM sizes WHERE name = :name AND id <> :id");
                    $check->execute([':name' => $name, ':id' => $id]);
                    if ($check->fetchColumn() > 0) {
                        $sizeError = 'Size này đã tồn tại.';
                    } else {
                        $stmt = $pdo->prepare("UPDATE sizes SET name = :name WHERE id = :id");
                        $stmt->execute([':name' => $name, ':id' => $id]);
                        $sizeSuccess = 'Cập nhật size thành công.';
                    }
                }
            } catch (PDOException $e) {
                $sizeError = 'Lỗi DB: ' . $e->getMessage();
            }
        }
    } elseif ($type === 'color') {
        if ($name === '') {
            $colorError = 'Tên màu không được để trống.';
        } else {
            try {
                if ($mode === 'add') {
                    // không bắt buộc unique, nhưng check cho đẹp
                    $check = $pdo->prepare("SELECT COUNT(*) FROM colors WHERE name = :name");
                    $check->execute([':name' => $name]);
                    if ($check->fetchColumn() > 0) {
                        $colorError = 'Màu này đã tồn tại.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO colors (name) VALUES (:name)");
                        $stmt->execute([':name' => $name]);
                        $colorSuccess = 'Thêm màu thành công.';
                    }
                } elseif ($mode === 'edit' && $id > 0) {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM colors WHERE name = :name AND id <> :id");
                    $check->execute([':name' => $name, ':id' => $id]);
                    if ($check->fetchColumn() > 0) {
                        $colorError = 'Màu này đã tồn tại.';
                    } else {
                        $stmt = $pdo->prepare("UPDATE colors SET name = :name WHERE id = :id");
                        $stmt->execute([':name' => $name, ':id' => $id]);
                        $colorSuccess = 'Cập nhật màu thành công.';
                    }
                }
            } catch (PDOException $e) {
                $colorError = 'Lỗi DB: ' . $e->getMessage();
            }
        }
    }
}

/* ================== PHÂN TRANG SIZE & COLOR ================== */
$sizeLimit  = 10;
$colorLimit = 10;

$sizePage  = isset($_GET['size_page'])  ? max(1, (int)$_GET['size_page'])  :1;
$colorPage = isset($_GET['color_page']) ? max(1, (int)$_GET['color_page']) : 1;

// tổng bản ghi
$totalSizes  = (int)$pdo->query("SELECT COUNT(*) FROM sizes")->fetchColumn();
$totalColors = (int)$pdo->query("SELECT COUNT(*) FROM colors")->fetchColumn();

// tổng số trang
$sizeTotalPages  = max(1, ceil($totalSizes  / $sizeLimit));
$colorTotalPages = max(1, ceil($totalColors / $colorLimit));

// nếu page > tổng thì đẩy về trang cuối
if ($sizePage > $sizeTotalPages)  $sizePage  = $sizeTotalPages;
if ($colorPage > $colorTotalPages) $colorPage = $colorTotalPages;

$sizeOffset  = ($sizePage  - 1) * $sizeLimit;
$colorOffset = ($colorPage - 1) * $colorLimit;

// ================== LẤY DỮ LIỆU HIỂN THỊ (CÓ LIMIT) ==================
// Sizes
$stSize = $pdo->prepare("SELECT id, name FROM sizes ORDER BY id ASC LIMIT :limit OFFSET :offset");
$stSize->bindValue(':limit',  $sizeLimit,  PDO::PARAM_INT);
$stSize->bindValue(':offset', $sizeOffset, PDO::PARAM_INT);
$stSize->execute();
$sizes = $stSize->fetchAll(PDO::FETCH_ASSOC);

// Colors
$stColor = $pdo->prepare("SELECT id, name FROM colors ORDER BY id ASC LIMIT :limit OFFSET :offset");
$stColor->bindValue(':limit',  $colorLimit,  PDO::PARAM_INT);
$stColor->bindValue(':offset', $colorOffset, PDO::PARAM_INT);
$stColor->execute();
$colors = $stColor->fetchAll(PDO::FETCH_ASSOC);

// id đang được sửa (nếu có)
$editSizeId  = isset($_GET['edit_size'])  ? (int)$_GET['edit_size']  : 0;
$editColorId = isset($_GET['edit_color']) ? (int)$_GET['edit_color'] : 0;

$editSize  = null;
$editColor = null;

if ($editSizeId > 0) {
    $st = $pdo->prepare("SELECT * FROM sizes WHERE id = :id");
    $st->execute([':id' => $editSizeId]);
    $editSize = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($editColorId > 0) {
    $st = $pdo->prepare("SELECT * FROM colors WHERE id = :id");
    $st->execute([':id' => $editColorId]);
    $editColor = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<title>Quản lý size & màu - YaMy Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{display:flex;background:#f5f6fa;color:#111;}
a{text-decoration:none;}

/* SIDEBAR giống các trang khác */
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

/* CONTENT */
.content{margin-left:280px;padding:40px;width:100%;}
.page-title{font-size:26px;font-weight:700;margin-bottom:25px;}

/* 2 PANEL TRÁI / PHẢI */
.panels{
    display:flex;
    gap:24px;
    flex-wrap:wrap;
}
.panel{
    flex:1 1 340px;
    background:#fff;
    border-radius:16px;
    padding:20px 22px;
    box-shadow:0 4px 14px rgba(0,0,0,0.06);
}
.panel-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:12px;
}
.panel-title{
    font-size:18px;
    font-weight:700;
}
.badge-panel{
    padding:4px 10px;
    border-radius:999px;
    background:#f3e6ff;
    color:#8E5DF5;
    font-size:12px;
    font-weight:600;
}

/* form add / edit */
.panel form{
    margin-bottom:14px;
}
.panel label{
    font-size:13px;
    font-weight:600;
    margin-bottom:4px;
    display:block;
}
.panel input[type="text"], .panel input[type="number"]{
    width:100%;
    padding:9px 10px;
    border-radius:10px;
    border:1px solid #ddd;
    font-size:14px;
    margin-bottom:8px;
}
.panel input:focus{
    border-color:#8E5DF5;
    outline:none;
}
.btn-main{
    background:#8E5DF5;
    color:#fff;
    border:none;
    padding:8px 14px;
    border-radius:999px;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
}
.btn-main:hover{background:#E91E63;}

.btn-edit{
    background:#03A9F4;
    color:#fff;
    padding:5px 10px;
    border-radius:999px;
    font-size:12px;
}
.btn-edit:hover{background:#0288D1;}

.table-list{
    width:100%;
    border-collapse:collapse;
    margin-top:6px;
}
.table-list th,
.table-list td{
    padding:8px 6px;
    border-bottom:1px solid #eee;
    font-size:14px;
    text-align:left;
}
.table-list th{
    color:#666;
    font-weight:600;
}
.msg{font-size:13px;margin-bottom:6px;}
.msg.error{color:#e53935;}
.msg.success{color:#2e7d32;}

/* pagination riêng cho từng panel */
.pagination{
    margin-top:10px;
    text-align:right;
}
.pagination a{
    display:inline-block;
    padding:6px 10px;
    border-radius:8px;
    background:#1a1a1a;
    color:#fff;
    font-size:12px;
    margin-left:4px;
}
.pagination a.active{background:#E91E63;}
.pagination a:hover{background:#8E5DF5;}

@media(max-width:800px){
    .content{padding:20px;}
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
    <h1 class="page-title">Quản lý size & màu</h1>

    <div class="panels">
        <!-- PANEL SIZE BÊN TRÁI -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">Size</div>
                <span class="badge-panel">Tổng: <?= $totalSizes ?></span>
            </div>

            <?php if ($sizeError): ?>
                <div class="msg error"><?= htmlspecialchars($sizeError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($sizeSuccess): ?>
                <div class="msg success"><?= htmlspecialchars($sizeSuccess, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($editSize): ?>
                <form method="post">
                    <input type="hidden" name="type" value="size">
                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="id" value="<?= (int)$editSize['id'] ?>">
                    <label>Sửa size:</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editSize['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                    <button type="submit" class="btn-main">Cập nhật size</button>
                    <a href="sizes_colors.php?size_page=<?= $sizePage ?>&color_page=<?= $colorPage ?>" style="margin-left:8px;font-size:13px;">Hủy</a>
                </form>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="type" value="size">
                    <input type="hidden" name="mode" value="add">
                    <label>Thêm size mới:</label>
                    <input type="text" name="name" placeholder="VD: S, M, L, XL" required>
                    <button type="submit" class="btn-main">Thêm size</button>
                </form>
            <?php endif; ?>

            <table class="table-list">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên size</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($sizes): ?>
                    <?php foreach ($sizes as $s): ?>
                        <tr>
                            <td><?= (int)$s['id'] ?></td>
                            <td><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <a href="sizes_colors.php?edit_size=<?= (int)$s['id'] ?>&size_page=<?= $sizePage ?>&color_page=<?= $colorPage ?>" class="btn-edit">Sửa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">Chưa có size nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- PHÂN TRANG SIZE -->
            <?php if ($sizeTotalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $sizeTotalPages; $i++): ?>
                        <a href="sizes_colors.php?size_page=<?= $i ?>&color_page=<?= $colorPage ?>"
                           class="<?= $i == $sizePage ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- PANEL COLOR BÊN PHẢI -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">Màu sắc</div>
                <span class="badge-panel">Tổng: <?= $totalColors ?></span>
            </div>

            <?php if ($colorError): ?>
                <div class="msg error"><?= htmlspecialchars($colorError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($colorSuccess): ?>
                <div class="msg success"><?= htmlspecialchars($colorSuccess, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($editColor): ?>
                <form method="post">
                    <input type="hidden" name="type" value="color">
                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="id" value="<?= (int)$editColor['id'] ?>">
                    <label>Sửa màu:</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editColor['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                    <button type="submit" class="btn-main">Cập nhật màu</button>
                    <a href="sizes_colors.php?size_page=<?= $sizePage ?>&color_page=<?= $colorPage ?>" style="margin-left:8px;font-size:13px;">Hủy</a>
                </form>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="type" value="color">
                    <input type="hidden" name="mode" value="add">
                    <label>Thêm màu mới:</label>
                    <input type="text" name="name" placeholder="VD: Black, White, Red..." required>
                    <button type="submit" class="btn-main">Thêm màu</button>
                </form>
            <?php endif; ?>

            <table class="table-list">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên màu</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($colors): ?>
                    <?php foreach ($colors as $c): ?>
                        <tr>
                            <td><?= (int)$c['id'] ?></td>
                            <td><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <a href="sizes_colors.php?edit_color=<?= (int)$c['id'] ?>&size_page=<?= $sizePage ?>&color_page=<?= $colorPage ?>" class="btn-edit">Sửa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">Chưa có màu nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- PHÂN TRANG COLOR -->
            <?php if ($colorTotalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $colorTotalPages; $i++): ?>
                        <a href="sizes_colors.php?size_page=<?= $sizePage ?>&color_page=<?= $i ?>"
                           class="<?= $i == $colorPage ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
