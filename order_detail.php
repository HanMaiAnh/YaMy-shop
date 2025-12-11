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

/* ============================
   XỬ LÝ TRẠNG THÁI
============================ */
$status_raw  = (string)($order['status'] ?? '');
$status_norm = mb_strtolower(trim($status_raw), 'UTF-8');

// Các trạng thái được xem là "ĐÃ GIAO HÀNG" => cho phép đánh giá + bình luận
$delivered_statuses = [
    'completed',
    'đã giao hàng',
    'da giao hang',
    'đã giao',
    'da giao',
    'delivered',
    'shipped'
];

$can_comment = in_array($status_norm, $delivered_statuses, true);

// Các trạng thái CHO PHÉP HỦY (chưa giao cho đơn vị vận chuyển)
$cancelable_statuses = [
    'pending',
    'chờ xác nhận',
    'cho xac nhan',
    'processing',
    'đang xử lý',
    'dang xu ly',
    'confirmed',
    'đã xác nhận',
    'da xac nhan',
    'chuẩn bị giao',
    'chuan bi giao'
];

// Có thể hủy theo trạng thái hiện tại hay không (để hiển thị nút)
$can_cancel = in_array($status_norm, $cancelable_statuses, true);

/* ============================
   XỬ LÝ SUBMIT HỦY ĐƠN
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'cancel_order'
) {
    // Kiểm tra lại trong DB xem đơn còn trạng thái cho phép hủy không
    $stmtCheck = $pdo->prepare("
        SELECT status
        FROM orders
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmtCheck->execute([$order_id, (int)$_SESSION['user_id']]);
    $rowOrder = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$rowOrder) {
        $_SESSION['error'] = 'Không tìm thấy đơn hàng hoặc không thuộc về bạn.';
        header('Location: order_history.php');
        exit;
    }

    $statusDbNorm = mb_strtolower(trim($rowOrder['status'] ?? ''), 'UTF-8');

    if (!in_array($statusDbNorm, $cancelable_statuses, true)) {
        $_SESSION['error'] = 'Đơn hàng đã được chuyển sang trạng thái đang giao / đã giao nên không thể hủy.';
        header('Location: order_detail.php?id=' . $order_id);
        exit;
    }

    // Cập nhật trạng thái sang "Đã hủy"
    $stmtUpdate = $pdo->prepare("
        UPDATE orders
        SET status = 'Đã hủy'
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmtUpdate->execute([$order_id, (int)$_SESSION['user_id']]);

    $_SESSION['success'] = 'Hủy đơn hàng thành công.';
    header('Location: order_detail.php?id=' . $order_id);
    exit;
}

/* ============================
   XỬ LÝ SUBMIT ĐÁNH GIÁ + BÌNH LUẬN
   -> LƯU VÀO BẢNG `reviews`
============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_product_id'])) {
    $product_id = (int)($_POST['comment_product_id'] ?? 0);
    $comment    = trim($_POST['comment'] ?? '');
    $rating     = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;

    if (!$can_comment) {
        $_SESSION['error'] = 'Bạn chỉ có thể đánh giá/bình luận khi đơn hàng đã được giao.';
        header('Location: order_detail.php?id=' . $order_id);
        exit;
    }

    if ($product_id <= 0 || $comment === '') {
        $_SESSION['error'] = 'Nội dung bình luận không hợp lệ.';
        header('Location: order_detail.php?id=' . $order_id);
        exit;
    }

    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = 'Số sao không hợp lệ.';
        header('Location: order_detail.php?id=' . $order_id);
        exit;
    }

    // Kiểm tra sản phẩm có thuộc đơn hàng này không
    $stmtCheck = $pdo->prepare("
        SELECT 1 FROM order_details
        WHERE order_id = ? AND product_id = ?
        LIMIT 1
    ");
    $stmtCheck->execute([$order_id, $product_id]);
    if (!$stmtCheck->fetchColumn()) {
        $_SESSION['error'] = 'Sản phẩm không thuộc đơn hàng này, không thể đánh giá.';
        header('Location: order_detail.php?id=' . $order_id);
        exit;
    }

    // Kiểm tra user đã có review cho sản phẩm này chưa (bảng reviews)
    $stmtExist = $pdo->prepare("
        SELECT id FROM reviews
        WHERE user_id = ? AND product_id = ?
        LIMIT 1
    ");
    $stmtExist->execute([(int)$_SESSION['user_id'], $product_id]);
    $review_id = $stmtExist->fetchColumn();

    if ($review_id) {
        // Cập nhật review
        $stmtUpdate = $pdo->prepare("
            UPDATE reviews
            SET content = ?, rating = ?, created_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$comment, $rating, $review_id]);
        $_SESSION['success'] = 'Cập nhật đánh giá thành công.';
    } else {
        // Thêm review mới
        $stmtInsert = $pdo->prepare("
            INSERT INTO reviews (product_id, user_id, rating, content, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmtInsert->execute([
            $product_id,
            (int)$_SESSION['user_id'],
            $rating,
            $comment
        ]);
        $_SESSION['success'] = 'Gửi đánh giá thành công.';
    }

    header('Location: order_detail.php?id=' . $order_id);
    exit;
}

/* ============================
   LẤY CHI TIẾT SẢN PHẨM TRONG ĐƠN
============================ */
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

/* ============================
   LẤY REVIEW CỦA USER (NẾU CÓ) TỪ BẢNG `reviews`
============================ */
$user_comments = [];
if (!empty($items)) {
    $product_ids   = array_column($items, 'product_id');
    $placeholders  = implode(',', array_fill(0, count($product_ids), '?'));

    $sqlComments = "
        SELECT product_id, rating, content AS comment
        FROM reviews
        WHERE user_id = ?
          AND product_id IN ($placeholders)
    ";
    $params  = array_merge([(int)$_SESSION['user_id']], $product_ids);
    $stmtCmt = $pdo->prepare($sqlComments);
    $stmtCmt->execute($params);

    while ($row = $stmtCmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$row['product_id'];
        $user_comments[$pid] = [
            'rating'  => (int)($row['rating'] ?? 0),
            'comment' => $row['comment'] ?? ''
        ];
    }
}

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

// Flash message
$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
.review-box {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.star-rating {
    direction: rtl;
    display: inline-flex;
    font-size: 20px;
    cursor: pointer;
}

.star-rating input {
    display: none;
}

.star-rating label {
    color: #ccc;
    margin: 0 2px;
    transition: color 0.2s ease;
}

.star-rating input:checked ~ label {
    color: #ffca08;
}

.star-rating label:hover,
.star-rating label:hover ~ label {
    color: #ffdd55;
}

.comment-area {
    border-radius: 8px;
    resize: none;
    font-size: 14px;
}

.btn-review {
    width: 100%;
    padding: 6px 10px;
    font-size: 14px;
    border-radius: 999px;
}

.review-info {
    font-size: 13px;
    color: #28a745;
    margin-top: 4px;
}
</style>

<div class="container py-5">
    <h2 class="mb-4">Chi tiết đơn hàng #<?= htmlspecialchars($order['id']) ?></h2>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

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

            <?php if ($can_cancel): ?>
                <form method="post" class="mt-2"
                      onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn hàng này?');">
                    <input type="hidden" name="action" value="cancel_order">
                    <button type="submit" class="btn btn-outline-danger">
                        Hủy đơn hàng
                    </button>
                </form>
                <small class="text-muted d-block mt-1">
                    Bạn chỉ có thể hủy khi đơn chưa được giao cho đơn vị vận chuyển.
                </small>
            <?php endif; ?>
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
        <?php if ($can_comment): ?>
            <p class="text-success mb-3">
                Đơn hàng đã được giao, bạn có thể đánh giá (chọn sao) và bình luận cho từng sản phẩm bên dưới.
            </p>
        <?php else: ?>
            <p class="text-muted mb-3">
                Bạn chỉ có thể đánh giá/bình luận khi đơn hàng ở trạng thái <strong>ĐÃ GIAO HÀNG</strong>.
            </p>
        <?php endif; ?>

        <div class="list-group">
            <?php foreach ($items as $it): ?>
                <?php
                    $imgFile = $it['product_image'] ?? '';
                    $imgPath = !empty($imgFile)
                        ? '../uploads/' . $imgFile
                        : '../assets/no-image.png';

                    $pid       = (int)($it['product_id'] ?? 0);
                    $myData    = $user_comments[$pid] ?? ['rating' => 0, 'comment' => ''];
                    $myRating  = $myData['rating'] ?: 5;
                    $myComment = $myData['comment'] ?? '';
                ?>
                <div class="list-group-item">
                    <div class="d-flex align-items-center">
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

                    <!-- Form đánh giá + bình luận (UI đẹp) -->
                    <div class="review-box">
                        <?php if ($can_comment): ?>
                            <form method="post">
                                <input type="hidden" name="comment_product_id" value="<?= $pid ?>">

                                <!-- Rating sao -->
                                <div class="mb-2">
                                    <div class="star-rating">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input
                                                type="radio"
                                                id="star<?= $i ?>-<?= $pid ?>"
                                                name="rating"
                                                value="<?= $i ?>"
                                                <?= ($myRating == $i ? 'checked' : '') ?>
                                            >
                                            <label for="star<?= $i ?>-<?= $pid ?>">★</label>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <!-- Textarea bình luận -->
                                <textarea
                                    name="comment"
                                    class="form-control comment-area mb-2"
                                    rows="2"
                                    placeholder="Viết cảm nhận của bạn về sản phẩm này..."><?= htmlspecialchars($myComment) ?></textarea>

                                <!-- Nút gửi -->
                                <button type="submit" class="btn btn-primary btn-review">
                                    <?= $myComment ? 'Cập nhật đánh giá' : 'Gửi đánh giá' ?>
                                </button>

                                <?php if ($myComment): ?>
                                    <div class="review-info">
                                        Bạn đã đánh giá sản phẩm này <?= $myRating ?> ★ – có thể chỉnh sửa nếu muốn.
                                    </div>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <small class="text-muted">
                                Đơn hàng chưa được giao, bạn chưa thể đánh giá/bình luận sản phẩm này.
                            </small>
                        <?php endif; ?>
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
