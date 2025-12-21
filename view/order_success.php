<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

// --- Kiểm tra đăng nhập ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- Lấy order_id từ GET ---
$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    echo '<div class="container py-5 text-center">
            <h3>Không tìm thấy đơn hàng!</h3>
            <a href="index.php" class="btn btn-danger mt-3">Về trang chủ</a>
          </div>';
    exit;
}

// --- Lấy thông tin đơn hàng ---
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo '<div class="container py-5 text-center">
            <h3>Đơn hàng không tồn tại hoặc không thuộc về bạn!</h3>
            <a href="index.php" class="btn btn-danger mt-3">Về trang chủ</a>
          </div>';
    exit;
}

// --- Lấy chi tiết sản phẩm ---
$sql_items = "
    SELECT 
        od.*,
        p.name,
        COALESCE(pi.image_url, '') AS image
    FROM order_details od
    JOIN products p ON od.product_id = p.id
    LEFT JOIN (
        SELECT product_id, MIN(image_url) AS image_url
        FROM product_images
        GROUP BY product_id
    ) pi ON pi.product_id = p.id
    WHERE od.order_id = ?
    ORDER BY od.id ASC
";
$stmt = $pdo->prepare($sql_items);
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Giá trị an toàn ---
$total_amount = (float)($order['total'] ?? 0);
$payment_method = $order['payment_method'] ?? 'cod';
$status = $order['status'] ?? '';
$isPaid = ($payment_method === 'cod') || ($status === 'Đã thanh toán');

require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
body { background:#fafafa; font-family:Arial,sans-serif; }
.success-container {
    max-width:900px;
    margin:50px auto;
    background:#fff;
    padding:30px;
    border-radius:8px;
    box-shadow:0 0 10px rgba(0,0,0,.05);
}
.success-container h2 { text-align:center; margin-bottom:20px; }
.success { color:#4caf50; }
.fail { color:#e53935; }
.order-item { display:flex; gap:15px; margin-bottom:15px; align-items:center; }
.order-item img { width:60px; height:60px; object-fit:cover; border-radius:5px; }
.order-total { text-align:right; font-weight:bold; font-size:1.2rem; color:#c00; }
.btn-home {
    display:block;
    text-align:center;
    margin-top:20px;
    padding:12px;
    background:#d32f4f;
    color:#fff;
    border-radius:6px;
    text-decoration:none;
}
</style>

<div class="success-container">

    <?php if ($isPaid): ?>
        <h2 class="success">Đặt hàng thành công!</h2>
    <?php else: ?>
        <h2 class="fail">Thanh toán không thành công</h2>
        <p style="text-align:center;color:#666;">
            Giao dịch VNPay không hoàn tất hoặc đã bị hủy.
        </p>
    <?php endif; ?>

    <p style="text-align:center;">Mã đơn hàng: <strong>#<?= (int)$order['id'] ?></strong></p>
    <p style="text-align:center;">Tổng tiền: <strong><?= number_format($total_amount,0,'','.') ?>₫</strong></p>
    <p style="text-align:center;">Phương thức thanh toán:
        <strong><?= strtoupper(htmlspecialchars($payment_method)) ?></strong>
    </p>

    <hr>

    <h3>Chi tiết đơn hàng</h3>

    <?php foreach ($order_items as $item): ?>
        <?php
        $rawImg = trim((string)($item['image'] ?? ''));

if ($rawImg === '') {
    $imgSrc = '../uploads/no-image.png';
} else {
    // DB chỉ lưu tên file → ảnh nằm trong /uploads
    $imgSrc = '../uploads/' . rawurlencode($rawImg);
}


        ?>
        <div class="order-item">
<img
    src="<?= $imgSrc ?>"
    alt="<?= htmlspecialchars($item['name'] ?? '') ?>"
    onerror="this.onerror=null;this.src='../uploads/no-image.png';"
/>
            <div>
                <div><?= htmlspecialchars($item['name']) ?></div>
                <div>x<?= (int)$item['quantity'] ?></div>
            </div>
            <div><?= number_format($item['price'] * $item['quantity'],0,'','.') ?>₫</div>
        </div>
    <?php endforeach; ?>

    <div class="order-total">Tổng cộng: <?= number_format($total_amount,0,'','.') ?>₫</div>

    <a href="index.php" class="btn-home">← Tiếp tục mua sắm</a>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
