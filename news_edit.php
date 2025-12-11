<?php
session_name('admin_session');
session_start();

// --- Kết nối DB PDO ---
require_once __DIR__ . '/../config/db.php'; // $pdo

// --- Kiểm tra quyền admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Lấy id tin tức
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: news.php");
    exit;
}

$errors = [];

// ====== Nếu submit form (POST) ====== //
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $image   = trim($_POST['image'] ?? '');      // bạn đang lưu full URL trong DB
    $content = trim($_POST['content'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $infor   = trim($_POST['infor'] ?? '');

    if ($title === '') {
        $errors[] = "Vui lòng nhập tiêu đề.";
    }

    // Có thể thêm validate khác nếu muốn

    if (empty($errors)) {
        $sql = "UPDATE news 
                SET title = :title,
                    image = :image,
                    content = :content,
                    address = :address,
                    infor = :infor
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title'   => $title,
            ':image'   => $image,
            ':content' => $content,
            ':address' => $address,
            ':infor'   => $infor,
            ':id'      => $id,
        ]);

        header("Location: news.php?msg=updated");
        exit;
    }
}

// ====== Lấy dữ liệu tin tức hiện tại ====== //
$sql = "SELECT * FROM news WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$news = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$news) {
    header("Location: news.php");
    exit;
}

// --- Escape dữ liệu để hiển thị --- //
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa tin tức</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:'Montserrat',sans-serif;
        }
        body {
            display:flex;
            background:#f9f9f9; 
            color:#333;
        }
        /* SIDEBAR */
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
        .content {
            margin-left:280px;
            padding:40px;
            width:calc(100% - 280px);
            min-height:100vh;
        }
        .page-header {
            margin-bottom:10px;
        }
        .page-title {
            font-size:28px;
            font-weight:700;
            margin-bottom:6px;
        }
        .subtitle {
            color:#aaa;
            margin-bottom:20px;
        }
        .form-card {
            background:#fff;
            border-radius:14px;
            padding:30px 32px 26px;
            max-width:900px;
            margin:auto;
            border:1px solid #e5e5e5;
            box-shadow:0 4px 12px rgba(0,0,0,0.08);
        }
        .form-card h2 {
            text-align:center;
            color:#8E5DF5;
            margin-bottom:18px;
            font-size:22px;
            font-weight:700;
        }

        .meta {
            font-size:13px;
            color:#777;
            margin-bottom:12px;
            text-align:center;
        }

        .form-group {margin-bottom:16px;}
        .form-group label {
            display:block;
            font-weight:600;
            margin-bottom:6px;
            font-size:14px;
        }
        .form-group input[type="text"],
        .form-group textarea {
            width:100%;
            padding:10px 12px;
            border-radius:8px;
            border:1px solid #dcdcdc;
            font-size:14px;
            background:#fff;
            transition:border-color .3s;
        }
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            border-color:#8E5DF5;
            outline:none;
        }
        .form-group textarea {
            min-height:110px;
            resize:vertical;
        }

        .current-img {margin-top:8px;}
        .current-img img {
            width:150px;
            height:110px;
            object-fit:cover;
            border-radius:10px;
            border:1px solid #ddd;
        }

        .errors {
            background:#ffe8e8;
            color:#d40000;
            border:1px solid #ffb3b3;
            border-radius:8px;
            padding:10px 14px;
            margin-bottom:16px;
            font-size:14px;
        }
        .errors ul {margin-left:18px;}
        .form-actions {
            margin-top:10px;
            display:flex;
            flex-direction:column;
            align-items:flex-start;
            gap:10px;
        }
        .submit-btn {
            background:#8E5DF5;
            color:#fff;
            padding:14px;
            border-radius:10px;
            border:none;
            font-weight:600;
            cursor:pointer;
            font-size:15px;
            width:100%;         
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            transition:.3s;
        }
        .submit-btn:hover {background:#a57bff;}

        .back-link {
            font-size:16px;
        }
        .back-link a {
            color:#8E5DF5;
            font-weight:600;
            text-decoration:none;
        }
        .back-link a:hover {text-decoration:underline;color:#E91E63;}

        @media (max-width:768px) {
            .sidebar {width:100%;height:auto;position:relative;}
            .content {margin-left:0;width:100%;padding:20px;}
            .form-card {padding:22px;}
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
    <div class="page-header">
        <h1 class="page-title">Sửa tin tức #<?= (int)$news['id'] ?></h1>
        <p class="subtitle">Chỉnh sửa nội dung bài viết tin tức.</p>
    </div>
    <div class="form-card">
        <h2>Thông tin tin tức</h2>
        <p class="meta">Ngày tạo: <?= e($news['created_at']) ?></p>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <strong>Có lỗi xảy ra:</strong>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="title">Tiêu đề *</label>
                <input type="text" id="title" name="title"
                       value="<?= e($_POST['title'] ?? $news['title']) ?>" required>
            </div>

            <div class="form-group">
                <label for="image">Link ảnh (URL đầy đủ)</label>
                <input type="text" id="image" name="image"
                       value="<?= e($_POST['image'] ?? $news['image']) ?>">
                <?php if (!empty($news['image'])): ?>
                    <div class="current-img">
                        <span>Ảnh hiện tại:</span><br>
                        <img src="<?= e($news['image']) ?>" alt="current image">
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="address">Địa chỉ / mô tả ngắn</label>
                <textarea id="address" name="address"><?= e($_POST['address'] ?? $news['address']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="infor">Thông tin thêm (infor)</label>
                <textarea id="infor" name="infor"><?= e($_POST['infor'] ?? $news['infor']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="content">Nội dung chi tiết</label>
                <textarea id="content" name="content"><?= e($_POST['content'] ?? $news['content']) ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="submit-btn">
                    <i class="fa fa-save"></i> Lưu thay đổi
                </button>

                <div class="back-link">
                    <a href="news.php">← Quay lại danh sách tin tức</a>
                </div>
            </div>

        </form>
    </div>
</div>
</body>
</html>
