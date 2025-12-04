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

// Bảng map trạng thái (tiếng Việt) và class màu cho badge
$status_labels = [
    'pending'   => 'Chờ xử lý',
    'completed' => 'Hoàn thành',
    'paid'      => 'Đã thanh toán',
    'cancelled' => 'Đã hủy'
];
$status_badge = [
    'pending'   => 'warning',
    'completed' => 'success',
    'paid'      => 'primary',
    'cancelled' => 'danger'
];
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
                        <th>Trạng thái</th>
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

                        // trạng thái và class badge
                        $status = strtolower($o['status'] ?? 'pending');
                        $label = $status_labels[$status] ?? 'Không xác định';
                        $badgeClass = $status_badge[$status] ?? 'secondary';

                        // ngày đặt: xử lý an toàn
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
                                <!-- trỏ tới trang chi tiết đang có (order_detail.php) -->
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
