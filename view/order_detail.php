<?php
// view/order_detail.php
session_start();

// load DB và helper giống các file khác trong project
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// include header (đường dẫn tới includes/header.php nằm ở parent của view)
include_once dirname(__DIR__) . '/includes/header.php';

// kiểm tra login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    echo '<div class="container py-5"><div class="alert alert-warning">Không tìm thấy đơn hàng.</div></div>';
    include dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// Lấy order (chỉ lấy order của user hiện tại)
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, (int)$_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo '<div class="container py-5"><div class="alert alert-warning">Đơn hàng không tồn tại hoặc không thuộc về bạn.</div></div>';
    include dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// Lấy chi tiết
$stmt = $pdo->prepare("
    SELECT od.*, p.name AS product_name, p.image AS product_image
    FROM order_details od
    LEFT JOIN products p ON od.product_id = p.id
    WHERE od.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// mapping phương thức thanh toán (nếu cần)
$payment_map = [
    'cod' => 'Thanh toán khi nhận hàng (COD)',
    'momo' => 'MoMo',
    'vnpay' => 'VNPay',
    'bank' => 'Chuyển khoản'
];
$payment_label = $payment_map[$order['payment_method'] ?? 'cod'] ?? strtoupper($order['payment_method'] ?? 'COD');

?>

<div class="container py-5">
    <h2>Chi tiết đơn hàng #<?= htmlspecialchars($order['id']) ?></h2>

    <div class="row mt-4">
        <div class="col-md-6">
            <p><strong>Ngày đặt:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at'] ?? 'now'))) ?></p>
            <p><strong>Trạng thái:</strong> <?= htmlspecialchars(ucfirst($order['status'] ?? 'pending')) ?></p>
            <p><strong>Phương thức thanh toán:</strong> <?= htmlspecialchars($payment_label) ?></p>
            <p><strong>Người nhận:</strong> <?= htmlspecialchars($order['recipient_name'] ?? ($order['name'] ?? '-')) ?></p>
            <p><strong>Địa chỉ:</strong> <?= htmlspecialchars($order['recipient_address'] ?? ($order['address'] ?? '-')) ?></p>
            <p><strong>Điện thoại:</strong> <?= htmlspecialchars($order['recipient_phone'] ?? ($order['phone'] ?? '-')) ?></p>
        </div>

        <div class="col-md-6">
            <p><strong>Tổng tiền:</strong> <span class="text-danger fw-bold"><?= number_format((float)($order['total'] ?? 0),0,'','.') ?>₫</span></p>
        </div>
    </div>

    <hr>

    <h4>Sản phẩm</h4>
    <?php if ($items): ?>
        <div class="list-group">
            <?php foreach ($items as $it): ?>
                <div class="list-group-item d-flex align-items-center">
                    <img src="<?= htmlspecialchars('../uploads/' . ($it['product_image'] ?? '')) ?>" alt="" style="width:64px;height:64px;object-fit:cover;margin-right:12px;">
                    <div>
                        <div><?= htmlspecialchars($it['product_name'] ?? 'Sản phẩm') ?></div>
                        <small>Số lượng: <?= (int)($it['quantity'] ?? 0) ?> • Giá: <?= number_format((float)($it['price'] ?? 0),0,'','.') ?>₫</small>
                    </div>
                    <div class="ms-auto"><?= number_format(((float)($it['price'] ?? 0) * (int)($it['quantity'] ?? 0)),0,'','.') ?>₫</div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Không có sản phẩm trong đơn.</p>
    <?php endif; ?>

    <a href="order_history.php" class="btn btn-secondary mt-3">← Quay lại Lịch sử đơn hàng</a>
</div>

<?php
// include footer (đường dẫn theo project của bạn)
include_once dirname(__DIR__) . '/includes/footer.php';
