<?php
session_name('admin_session');
session_start();

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Lấy id user từ GET (an toàn)
if (!isset($_GET['id']) || !intval($_GET['id'])) {
    header("Location: users.php");
    exit;
}
$userId = (int) $_GET['id'];

try {
    // Lấy thông tin user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Không tìm thấy người dùng.");
    }

    // Lấy danh sách đơn hàng của user này
    $sqlOrders = "
        SELECT 
            id,
            total,
            status,
            payment_method,
            created_at,
            recipient_name,
            recipient_phone,
            recipient_address,
            recipient_email
        FROM orders
        WHERE user_id = :uid
        ORDER BY created_at DESC
    ";
    $stmt2 = $pdo->prepare($sqlOrders);
    $stmt2->execute([':uid' => $userId]);
    $orders = $stmt2->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Lỗi khi lấy dữ liệu: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// Xử lý hiển thị (dùng null-coalesce để tránh truyền null vào htmlspecialchars)
$username   = isset($user['username']) ? (string)$user['username'] : '';
$email      = isset($user['email']) ? (string)$user['email'] : '';
$role       = isset($user['role']) ? (string)$user['role'] : 'user';
$created_at = isset($user['created_at']) ? (string)$user['created_at'] : '';

$phone = $user['phone'] ?? null;
$sex   = $user['sex']   ?? null;
$active = $user['active'] ?? null;

// NEW: địa chỉ và ngày sinh
$address = isset($user['address']) ? (string)$user['address'] : null;
$birthday = isset($user['birthday']) ? (string)$user['birthday'] : null;
function formatDateVN($dateString) {
    if (!$dateString) return '--';
    $ts = strtotime($dateString);
    if (!$ts) return '--';
    return date('d/m/Y H:i', $ts); // Hiển thị: 03/12/2025 14:28
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Chi tiết người dùng</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body{display:flex;background:#f5f6fa;color:#111;}
a{text-decoration:none;}

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
/* content */
.content{
    margin-left:280px;padding:30px;width:calc(100% - 280px);min-height:100vh;
}
h1{font-size:24px;margin-bottom:10px;}
.sub{color:#777;margin-bottom:20px;}

.card{
    background:#fff;border-radius:14px;padding:20px;border:1px solid #ddd;
    margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.03);
}
.card h2{font-size:18px;margin-bottom:12px;}

.info-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:10px 20px;
    margin-top:10px;
}
.info-item span.label{
    font-size:13px;color:#777;display:block;margin-bottom:2px;
}
.info-item span.value{
    font-size:15px;font-weight:600;
}

.badge{
    display:inline-block;padding:4px 8px;border-radius:999px;
    font-size:12px;font-weight:600;
}
.badge-role-admin{background:#ffe0e0;color:#c62828;}
.badge-role-user{background:#e0f7fa;color:#006064;}
.badge-active{background:#e0f8e9;color:#2e7d32;}
.badge-locked{background:#ffe0e0;color:#c62828;}

table{
    width:100%;border-collapse:collapse;background:#fff;border-radius:14px;
    overflow:hidden;margin-top:10px;
}
th{background:#8E5DF5;padding:12px;text-align:left;font-weight:600;color:#fff;font-size:14px;}
td{padding:12px;border-bottom:1px solid #eee;font-size:14px;vertical-align:middle;}
tr:hover{background:#fafafa;}
.breadcrumb{font-size:13px;color:#777;margin-bottom:20px;}
.breadcrumb a{color:#8E5DF5;text-decoration:none;}
.order-code{font-weight:600;}
.text-center{text-align:center;}
.btn-back{
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:#f0e8ff;
    padding:8px 14px;
    border-radius:999px;
    font-size:13px;
    text-decoration:none;
    color:#5a3ec8;
    margin-bottom:10px;
}
.btn-back i{font-size:12px;}
.back-link{margin-top:20px; }
.back-link a{color:#8E5DF5;font-weight:600;font-size: 16px;}
.back-link a:hover{text-decoration:underline;color:#E91E63;}

/* small button */
.btn-detail{
    display:inline-block;padding:8px 12px;background:#03A9F4;color:#fff;border-radius:8px;text-decoration:none;
}
.btn-detail:hover{background:#0288D1}
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
    <a href="users.php" class="btn-back"><i class="fa fa-arrow-left"></i> Quay lại danh sách</a>
    <h1 class="page-title">Chi tiết người dùng</h1>
    <div class="breadcrumb">
        <a href="users.php">Quản lý người dùng</a> &raquo;
        Stt: <strong><?= htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8') ?></strong>
    </div>

    <!-- Thông tin tài khoản -->
    <div class="card">
        <h2>Thông tin tài khoản</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="label">Username</span>
                <span class="value"><?= htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Email</span>
                <span class="value"><?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="info-item">
                <span class="label">Số điện thoại</span>
                <span class="value">
                    <?= $phone ? htmlspecialchars((string)$phone, ENT_QUOTES, 'UTF-8') : '--' ?>
                </span>
            </div>

            <!-- NEW: Địa chỉ -->
            <div class="info-item">
                <span class="label">Địa chỉ</span>
                <span class="value">
                    <?= $address ? nl2br(htmlspecialchars($address, ENT_QUOTES, 'UTF-8')) : '--' ?>
                </span>
            </div>

            <!-- NEW: Ngày sinh -->
            <div class="info-item">
                <span class="label">Ngày sinh</span>
                <span class="value">
                    <?php
                    if ($birthday === null || $birthday === '') {
                        echo '--';
                    } else {
                        // nếu bạn muốn định dạng khác (ví dụ d/m/Y) thay 'Y-m-d' bằng format mong muốn
                        $dt = date_create($birthday);
                        echo $dt ? htmlspecialchars($dt->format('d/m/Y'), ENT_QUOTES, 'UTF-8') : htmlspecialchars((string)$birthday, ENT_QUOTES, 'UTF-8');
                    }
                    ?>
                </span>
            </div>

            <div class="info-item">
                <span class="label">Giới tính</span>
                <span class="value">
                    <?php
                    if ($sex === null || $sex === '') {
                        echo '--';
                    } elseif ((string)$sex === 'male') {
                        echo 'Nam';
                    } elseif ((string)$sex === 'female') {
                        echo 'Nữ';
                    } else {
                        echo htmlspecialchars((string)$sex, ENT_QUOTES, 'UTF-8');
                    }
                    ?>
                </span>
            </div>
            <div class="info-item">
                <span class="label">Vai trò</span>
                <span class="value">
                    <?php if ($role === 'admin'): ?>
                        <span class="badge badge-role-admin">Admin</span>
                    <?php else: ?>
                        <span class="badge badge-role-user">User</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="label">Trạng thái</span>
                <span class="value">
                    <?php if ($active === null): ?>
                        --
                    <?php elseif ((int)$active === 1): ?>
                        <span class="badge badge-active">Hoạt động</span>
                    <?php else: ?>
                        <span class="badge badge-locked">Khoá</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="label">Ngày tạo tài khoản</span>
                <span class="value"><?= formatDateVN($created_at) ?></span>

            </div>
        </div>
    </div>

    <!-- Danh sách đơn hàng -->
    <div class="card">
        <h2>Đơn hàng của người dùng</h2>

        <?php if (empty($orders)): ?>
            <p class="text-center" style="margin-top:10px;color:#777;">Người dùng này chưa có đơn hàng nào.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Mã đơn</th>
                    <th>Ngày đặt</th>
                    <th>Tên người nhận</th>
                    <th>Tổng tiền</th>
                    <th>Thanh toán</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
                <?php foreach ($orders as $o): ?>
                    <?php
                        $code = 'DH' . str_pad((string)$o['id'], 5, '0', STR_PAD_LEFT);
                        $total = isset($o['total']) ? (float)$o['total'] : 0.0;
                        $recipientName = $o['recipient_name'] ?? '--';
                        $orderId = (int)$o['id'];
                    ?>
                    <tr>
                        <td class="order-code"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($o['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format($total, 0, ',', '.') ?> ₫</td>
                        <td><?= htmlspecialchars($o['payment_method'] ?? '--', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($o['status'] ?? '--', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a class="btn-detail" href="order_detail.php?id=<?= $orderId ?>">Chi tiết</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="back-link">
        <a href="users.php">← Quay lại danh sách người dùng</a>
    </div>
</div>
</body>
</html>
