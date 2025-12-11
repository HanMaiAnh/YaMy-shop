<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/header.php';

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
    include dirname(__DIR__) . '/includes/footer.php';
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
    include dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// --- Lấy chi tiết sản phẩm + 1 ảnh đại diện cho mỗi sản phẩm ---
// Ở đây CHỈ dùng bảng product_images, vì bảng products KHÔNG có cột image
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
    ) AS pi ON pi.product_id = p.id
    WHERE od.order_id = ?
    ORDER BY od.id ASC
";

$stmt = $pdo->prepare($sql_items);
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- An toàn cho các trường có thể không tồn tại ---
$total_amount = isset($order['total']) ? (float)$order['total'] : 0.0;

// payment_method có thể không tồn tại — dùng mặc định 'cod'
$payment_method = 'cod';
if (isset($order['payment_method']) && $order['payment_method'] !== null && $order['payment_method'] !== '') {
    $payment_method = $order['payment_method'];
} elseif (isset($order['payment_method']) === false) {
    $payment_method = 'cod';
}
$payment_label = strtoupper(htmlspecialchars($payment_method));
?>

<style>
body { background: #fafafa; font-family: Arial, sans-serif; }
.success-container { max-width: 900px; margin: 50px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
.success-container h2 { color: #4caf50; text-align: center; margin-bottom: 20px; }
.success-container p { text-align: center; font-size: 1.1rem; }
.order-item { display: flex; gap: 15px; margin-bottom: 15px; align-items: center; }
.order-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; flex-shrink: 0; }
.order-item div { flex: 1; }
.order-total { text-align: right; font-weight: bold; font-size: 1.2rem; color: #c00; margin-top: 10px; }
.btn-home { display: block; text-align: center; margin-top: 20px; padding: 12px; background: #d32f4f; color: #fff; border-radius: 6px; text-decoration: none; }
.btn-home:hover { background: #b71c1c; }
</style>

<div class="success-container">
    <h2>Đặt hàng thành công!</h2>
    <p>Mã đơn hàng của bạn: <strong>#<?= htmlspecialchars($order['id']) ?></strong></p>
    <p>Tổng tiền: <strong><?= number_format($total_amount,0,'','.') ?>₫</strong></p>
    <p>Phương thức thanh toán: <strong><?= $payment_label ?></strong></p>

    <hr>

    <h3>Chi tiết đơn hàng</h3>
    <?php if (!empty($order_items)): ?>
        <?php foreach ($order_items as $item): ?>
            <?php
            // Xử lý đường dẫn ảnh
            $rawImg = $item['image'] ?? '';
            if ($rawImg === '' || $rawImg === null) {
                $imgSrc = '../uploads/no-image.png';
            } else {
                if (preg_match('~^https?://~', $rawImg)) {
                    // Nếu là URL đầy đủ http/https
                    $imgSrc = $rawImg;
                } else {
                    // Nếu trong DB lưu 'uploads/ten-anh.jpg' hoặc '/clothing_store/uploads/ten-anh.jpg'
                    if (strpos($rawImg, '/') !== false) {
                        $imgSrc = '../' . ltrim($rawImg, '/');
                    } else {
                        // Chỉ là tên file
                        $imgSrc = '../uploads/' . $rawImg;
                    }
                }
            }
            ?>
            <div class="order-item">
                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($item['name'] ?? 'Sản phẩm') ?>">
                <div>
                    <div><?= htmlspecialchars($item['name'] ?? 'Sản phẩm') ?></div>
                    <div>x<?= (int)($item['quantity'] ?? 0) ?></div>
                </div>
                <div><?= number_format(((float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 0)),0,'','.') ?>₫</div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-center text-muted">Không có sản phẩm trong đơn (order details trống).</p>
    <?php endif; ?>

    <div class="order-total">Tổng cộng: <?= number_format($total_amount,0,'','.') ?>₫</div>

    <a href="index.php" class="btn-home">← Tiếp tục mua sắm</a>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
