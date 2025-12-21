<?php
// view/order_history.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

// Nếu chưa đăng nhập thì chuyển tới trang login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Lấy đơn hàng của user cùng:
// - số sản phẩm (COUNT)
// - tổng tiền (SUM price * quantity) từ order_details (fallback nếu orders.total không tồn tại)
$sql = "
    SELECT
        o.*,
        COUNT(od.id) AS items,
        COALESCE(SUM(od.price * od.quantity), 0) AS calc_total
    FROM orders o
    LEFT JOIN order_details od ON od.order_id = o.id
    WHERE o.user_id = :user_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => (int)$_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <h2 class="mb-4">Lịch sử đơn hàng</h2>

    <?php if (empty($orders)): ?>
        <div class="alert alert-info">Bạn chưa có đơn hàng nào.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Ngày đặt</th>
                        <th>Số sản phẩm</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái đơn</th>
                        <th>Thanh toán</th>

                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <?php
                        // Lấy tổng tiền: ưu tiên cột orders.total nếu có, nếu không dùng calc_total
                        $total_amount = 0.0;
                        if (isset($o['total']) && $o['total'] !== null && $o['total'] !== '') {
                            $total_amount = (float)$o['total'];
                        } else {
                            $total_amount = (float)($o['calc_total'] ?? 0);
                        }

                        // ===== XỬ LÝ TRẠNG THÁI ĐƠN HÀNG =====
                        $status_raw = trim((string)($o['status'] ?? ''));   // giá trị lấy từ DB
                        $status_norm = mb_strtolower($status_raw, 'UTF-8'); // chuẩn hóa về thường

                        $label = 'Không xác định';
                        $badgeClass = 'secondary';


                        // ===== XỬ LÝ TRẠNG THÁI THANH TOÁN =====
                        $payment_label = 'Chưa thanh toán';
                        $payment_badge = 'warning';

                        $payment_method = $o['payment_method'] ?? 'cod';

                        // Nếu COD
                        if ($payment_method === 'cod') {
                            if (in_array($status_norm, ['completed', 'đã hoàn thành'], true)) {
                                $payment_label = 'Đã thanh toán';
                                $payment_badge = 'success';
                            } else {
                                $payment_label = 'Thanh toán khi nhận hàng';
                                $payment_badge = 'secondary';
                            }
                        }
                        // Nếu online
                        else {
                            if (in_array($status_norm, ['paid', 'completed', 'đã hoàn thành'], true)) {
                                $payment_label = 'Đã thanh toán';
                                $payment_badge = 'success';
                            } else {
                                $payment_label = 'Chưa thanh toán';
                                $payment_badge = 'warning';
                            }
                        }

                        // Nhóm các dạng status khác nhau về chung 1 loại

                        // 1. Chờ xử lý
                        $pending_values = [
                            'pending',
                            'chờ xử lý',
                            'cho xu ly',
                            'chờ xác nhận',
                            'cho xac nhan'
                        ];

                        // 2. Đang xử lý
                        $processing_values = [
                            'processing',
                            'đang xử lý',
                            'đang xử lí',
                            'dang xu ly',
                            'dang xu li',
                            'đang xử lý đơn',
                            'dang xu ly don'
                        ];

                        // 3. Hoàn thành
                        $completed_values = [
                            'completed',
                            'hoàn thành',
                            'da hoan thanh',
                            'đã hoàn thành'
                        ];

                        // 5. Đã hủy
                        $cancelled_values = [
                            'cancelled',
                            'canceled',
                            'đã hủy',
                            'da huy',
                            'đã huỷ',
                            'hủy',
                            'huy'
                        ];

                        // ===== MAP TRẠNG THÁI ĐƠN HÀNG (CHUẨN CUỐI) =====
if (in_array($status_norm, ['paid', 'pending', 'payment_success', 'success', 'chờ xác nhận', 'cho xac nhan'], true)) {
    $label = 'Chờ xác nhận';
    $badgeClass = 'warning';
}
elseif (in_array($status_norm, $processing_values, true)) {
    $label = 'Đang xử lý';
    $badgeClass = 'info';
}
elseif (in_array($status_norm, $completed_values, true)) {
    $label = 'Hoàn thành';
    $badgeClass = 'success';
}
elseif (in_array($status_norm, $cancelled_values, true)) {
    $label = 'Đã hủy';
    $badgeClass = 'danger';
}
else {
    if ($status_raw !== '') {
        $label = $status_raw;
        $badgeClass = 'secondary';
    }
}



                        // ===== XỬ LÝ NGÀY ĐẶT =====
                        $created = $o['created_at'] ?? null;
                        $date_display = '-';
                        if ($created) {
                            try {
                                $date_display = date('d/m/Y H:i', strtotime($created));
                            } catch (\Exception $e) {
                                $date_display = '-';
                            }
                        }
                        ?>
                        <tr>
                            <td>#<?= htmlspecialchars(str_pad($o['id'], 5, '0', STR_PAD_LEFT)) ?></td>
                            <td><?= htmlspecialchars($date_display) ?></td>
                            <td><?= (int)($o['items'] ?? 0) ?></td>
                            <td class="text-danger fw-bold"><?= number_format($total_amount) ?>₫</td>
                            <td>
                                <span class="badge bg-<?= htmlspecialchars($badgeClass) ?>">
                                    <?= htmlspecialchars($label) ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge bg-<?= htmlspecialchars($payment_badge) ?>">
                                    <?= htmlspecialchars($payment_label) ?>
                                </span>
                            </td>

                            <td>
                                <a href="order_detail.php?id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-info">Xem</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
