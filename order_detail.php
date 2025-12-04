<?php
// view/order_detail.php

ob_start();
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Kết nối DB & helper
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Header chung
include_once dirname(__DIR__) . '/includes/header.php';

// Kiểm tra login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Lấy order_id từ URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    echo '<div class="container py-5"><div class="alert alert-warning">Không tìm thấy đơn hàng.</div></div>';
    include dirname(__DIR__) . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// Lấy thông tin đơn hàng (chỉ lấy đơn của user hiện tại)
$stmt = $pdo->prepare("
    SELECT *
    FROM orders
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$order_id, (int)$_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo '<div class="container py-5"><div class="alert alert-warning">Đơn hàng không tồn tại hoặc không thuộc về bạn.</div></div>';
    include dirname(__DIR__) . '/includes/footer.php';
    ob_end_flush();
    exit;
}

// Lấy chi tiết sản phẩm trong đơn
//  - Mỗi dòng trong order_details -> 1 dòng hiển thị
//  - Lấy 1 ảnh đại diện từ product_images (image_url)
$stmt = $pdo->prepare("
    SELECT 
        od.*, 
        p.name AS product_name,
        (
            SELECT pi.image_url
            FROM product_images pi
            WHERE pi.product_id = p.id
            ORDER BY pi.id ASC
            LIMIT 1
        ) AS product_image
    FROM order_details od
    LEFT JOIN products p ON od.product_id = p.id
    WHERE od.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// mapping phương thức thanh toán
$payment_map = [
    'cod'  => 'Thanh toán khi nhận hàng (COD)',
    'momo' => 'MoMo',
    'vnpay'=> 'VNPay',
    'bank' => 'Chuyển khoản ngân hàng'
];

$payment_code  = $order['payment_method'] ?? 'cod';
$payment_label = $payment_map[$payment_code] ?? strtoupper($payment_code);

// Tổng tiền (dùng cột total trong orders nếu đã lưu)
$order_total = isset($order['total']) ? (float)$order['total'] : 0;

?>
<div class="container py-5">
    <h2 class="mb-4">Chi tiết đơn hàng #<?= htmlspecialchars($order['id']) ?></h2>

    <div class="row">
        <div class="col-md-6">
            <p><strong>Ngày đặt:</strong>
                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at'] ?? 'now'))) ?>
            </p>
            <p><strong>Trạng thái:</strong>
                <?= htmlspecialchars(ucfirst($order['status'] ?? 'pending')) ?>
            </p>
            <p><strong>Phương thức thanh toán:</strong>
                <?= htmlspecialchars($payment_label) ?>
            </p>
        </div>

        <div class="col-md-6">
            <p><strong>Người nhận:</strong>
                <?= htmlspecialchars($order['recipient_name'] ?? ($order['name'] ?? '-')) ?>
            </p>
            <p><strong>Địa chỉ:</strong>
                <?= htmlspecialchars($order['recipient_address'] ?? ($order['address'] ?? '-')) ?>
            </p>
            <p><strong>Điện thoại:</strong>
                <?= htmlspecialchars($order['recipient_phone'] ?? ($order['phone'] ?? '-')) ?>
            </p>
            <p>
                <strong>Tổng tiền:</strong>
                <span class="text-danger fw-bold">
                    <?= number_format($order_total, 0, '', '.') ?>₫
                </span>
            </p>
        </div>
    </div>

    <hr>

    <h4 class="mb-3">Sản phẩm trong đơn</h4>

    <?php if (!empty($items)): ?>
        <div class="list-group">
            <?php foreach ($items as $it): ?>
                <?php
                    $imgFile = $it['product_image'] ?? '';
                    // Nếu image_url trong DB chỉ lưu tên file: "ao-thun-1.jpg"
                    // và file nằm trong thư mục /uploads
                    $imgPath = !empty($imgFile)
                        ? '../uploads/' . $imgFile
                        : '../assets/no-image.png'; // Đổi lại đúng đường dẫn ảnh mặc định của bạn
                ?>
                <div class="list-group-item d-flex align-items-center">
                    <img src="<?= htmlspecialchars($imgPath) ?>"
                         alt=""
                         style="width:64px;height:64px;object-fit:cover;margin-right:12px;">

                    <div>
                        <div class="fw-semibold">
                            <?= htmlspecialchars($it['product_name'] ?? 'Sản phẩm') ?>
                        </div>
                        <small class="text-muted">
                            Số lượng:
                            <?= (int)($it['quantity'] ?? 0) ?>
                            • Giá:
                            <?= number_format((float)($it['price'] ?? 0), 0, '', '.') ?>₫
                        </small>
                    </div>

                    <div class="ms-auto fw-semibold">
                        <?php
                            $lineTotal = ((float)($it['price'] ?? 0)) * ((int)($it['quantity'] ?? 0));
                            echo number_format($lineTotal, 0, '', '.') . '₫';
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Không có sản phẩm nào trong đơn hàng này.</p>
    <?php endif; ?>

    <a href="order_history.php" class="btn btn-secondary mt-4">
        ← Quay lại lịch sử đơn hàng
    </a>
</div>

<?php
include_once dirname(__DIR__) . '/includes/footer.php';
ob_end_flush();
