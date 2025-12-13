<?php
// view/vnpay_return.php
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/config_vnpay.php';

// ensure PDO throws exceptions
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// make reduceStockAfterPayment available
require_once dirname(__DIR__) . '/includes/stock.php';

/**
 * Tạo link thanh toán VNPay (dùng khi redirect từ checkout)
 */
function redirectToVnpay(PDO $pdo, $orderId, $vnp_Url, $vnp_Returnurl, $vnp_TmnCode, $vnp_HashSecret, $vnp_BankCode = "")
{
    $orderId = (int)$orderId;
    if ($orderId <= 0) {
        throw new Exception('Mã đơn hàng không hợp lệ.');
    }

    // Lấy thông tin đơn hàng - CHỈ DÙNG total
    $stmt = $pdo->prepare("SELECT id, total FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng.');
    }

    // Số tiền thanh toán: dùng cột total
    $amount = (float)($order['total'] ?? 0);
    if ($amount <= 0) {
        throw new Exception('Tổng tiền đơn hàng không hợp lệ.');
    }

    // VNPay yêu cầu *100
    $vnp_Amount = $amount * 100;

    // Build data gửi tới VNPay
    $vnp_TxnRef    = $orderId; // Mã đơn hàng
    $vnp_OrderInfo = "Thanh toan don hang #{$orderId}";
    $vnp_OrderType = "other";
    $vnp_Locale    = "vn";
    $vnp_IpAddr    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $vnp_CreateDate = date('YmdHis');

    $inputData = [
        "vnp_Version"    => "2.1.0",
        "vnp_TmnCode"    => $vnp_TmnCode,
        "vnp_Amount"     => $vnp_Amount,
        "vnp_Command"    => "pay",
        "vnp_CreateDate" => $vnp_CreateDate,
        "vnp_CurrCode"   => "VND",
        "vnp_IpAddr"     => $vnp_IpAddr,
        "vnp_Locale"     => $vnp_Locale,
        "vnp_OrderInfo"  => $vnp_OrderInfo,
        "vnp_OrderType"  => $vnp_OrderType,
        "vnp_ReturnUrl"  => $vnp_Returnurl,
        "vnp_TxnRef"     => $vnp_TxnRef,
    ];

    if (!empty($vnp_BankCode)) {
        $inputData['vnp_BankCode'] = $vnp_BankCode;
    }

    ksort($inputData);
    $query    = "";
    $hashData = "";
    $i        = 0;
    foreach ($inputData as $key => $value) {
        if ($i === 1) {
            $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            $hashData .= urlencode($key) . "=" . urlencode($value);
            $i = 1;
        }
        $query .= urlencode($key) . "=" . urlencode($value) . '&';
    }

    // Ký hash
    $vnp_SecureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
    $vnp_UrlPayment = $vnp_Url . "?" . $query . "vnp_SecureHash=" . $vnp_SecureHash;

    header('Location: ' . $vnp_UrlPayment);
    exit;
}

/**
 * Xử lý callback VNPay
 */
function handleVnpayReturn(PDO $pdo, $vnp_HashSecret)
{
    // Nếu header/footer/asset cần trước khi in HTML, include header (nếu cần)
    require_once dirname(__DIR__) . '/includes/header.php';

    // LẤY TẤT CẢ THAM SỐ TRẢ VỀ TỪ VNPAY (GET)
    $vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';

    $inputData = [];
    foreach ($_GET as $key => $value) {
        if (substr($key, 0, 4) == "vnp_") {
            $inputData[$key] = $value;
        }
    }

    // BỎ vnp_SecureHash ĐỂ TÍNH LẠI
    unset($inputData['vnp_SecureHash']);
    unset($inputData['vnp_SecureHashType']);
    ksort($inputData);

    $hashData = "";
    foreach ($inputData as $key => $value) {
        if ($hashData != "") {
            $hashData .= '&';
        }
        $hashData .= urlencode($key) . "=" . urlencode($value);
    }

    // TÍNH HASH ĐỂ SO SÁNH
    $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

    $orderId    = (int)($_GET['vnp_TxnRef'] ?? 0);         // Mã đơn hàng mình đã gửi
    $vnp_Amount = (float)(($_GET['vnp_Amount'] ?? 0) / 100); // VNPay trả về *100

    // Mặc định
    $paymentStatus = 'failed';
    $message       = 'Thanh toán thất bại.';

    // Kiểm tra checksum
    if ($secureHash === $vnp_SecureHash) {
        // Checksum đúng
        if (
            ($_GET['vnp_ResponseCode'] ?? '') == '00'
            && ($_GET['vnp_TransactionStatus'] ?? '') == '00'
        ) {
            // Payment success from VNPay
            $paymentStatus = 'success';
            $message       = 'Thanh toán VNPay thành công.';

            if ($orderId > 0) {
                // Lưu payment_method để tracking
                try {
                    $stmtPm = $pdo->prepare("UPDATE orders SET payment_method = 'vnpay' WHERE id = ?");
                    $stmtPm->execute([$orderId]);
                } catch (Exception $e) {
                    error_log("VNPay: cannot set payment_method for order {$orderId}: " . $e->getMessage());
                }

                // Gọi hàm trừ tồn kho (hàm đã được include ở đầu file)
                try {
                    $res = reduceStockAfterPayment($pdo, $orderId, true); // true => mark paid nếu thành công

                    if ($res['success'] === true) {
                        // ===== TRỪ LƯỢT VOUCHER (VNPAY - SAU KHI THANH TOÁN OK) =====
if (!empty($_SESSION['applied_voucher']['id'])) {
    $vid = (int)$_SESSION['applied_voucher']['id'];

    try {
        $stmt = $pdo->prepare("
            UPDATE vouchers
            SET quantity = quantity - 1
            WHERE id = ? AND quantity > 0
        ");
        $stmt->execute([$vid]);
    } catch (Exception $e) {
        error_log("VNPay voucher reduce failed: " . $e->getMessage());
    }
}

                        // ================== XOÁ GIỎ HÀNG THEO TICK SAU VNPAY ==================
                    if (
                        !empty($_SESSION['pending_order_keys']) &&
                        isset($_SESSION['cart']) &&
                        is_array($_SESSION['cart'])
                    ) {
                    foreach ($_SESSION['pending_order_keys'] as $key) {
                        if (isset($_SESSION['cart'][$key])) {
                            unset($_SESSION['cart'][$key]);
        }
    }

    // Nếu giỏ hàng trống thì xoá luôn
    if (empty($_SESSION['cart'])) {
        unset($_SESSION['cart']);
    }
}

// Clear session tạm
unset($_SESSION['pending_order_keys']);
unset($_SESSION['pending_order_id']);
unset($_SESSION['checkout_selected_keys']);
unset($_SESSION['applied_voucher']);

                        // Thành công: hàm đã commit và set orders.status = 'paid'
                        $paymentStatus = 'success';
                        // message giữ nguyên
                    } else {
                        // Trừ tồn kho thất bại: log và cập nhật order để admin xử lý
                        error_log("VNPay: reduceStockAfterPayment failed for order {$orderId}: " . $res['message']);

                        try {
                            $stmtMark = $pdo->prepare("UPDATE orders SET status = 'payment_success_stock_failed' WHERE id = ?");
                            $stmtMark->execute([$orderId]);
                        } catch (Exception $e2) {
                            error_log("VNPay: cannot mark order {$orderId} as stock_failed: " . $e2->getMessage());
                        }

                        $paymentStatus = 'failed';
                        $message = 'Thanh toán thành công nhưng trừ tồn kho thất bại: ' . $res['message'];
                    }
                } catch (Exception $e) {
                    // Nếu reduceStockAfterPayment ném exception lạ
                    error_log("VNPay: exception when reducing stock for order {$orderId}: " . $e->getMessage());

                    try {
                        $stmtMark2 = $pdo->prepare("UPDATE orders SET status = 'payment_error' WHERE id = ?");
                        $stmtMark2->execute([$orderId]);
                    } catch (Exception $e3) {
                        error_log("VNPay: cannot update order status for {$orderId}: " . $e3->getMessage());
                    }

                    $paymentStatus = 'failed';
                    $message = 'Thanh toán thành công nhưng xử lý kho xảy ra lỗi kỹ thuật. Vui lòng liên hệ hỗ trợ.';
                }
            } else {
                $message = 'Thanh toán thành công nhưng không tìm thấy mã đơn hàng.';
            }
        } else {
            $message = 'Thanh toán không thành công. Mã lỗi: ' . ($_GET['vnp_ResponseCode'] ?? 'unknown');
        }
    } else {
        $message = 'Chữ ký không hợp lệ (Sai HashSecret / dữ liệu bị thay đổi).';
    }

    // Lấy thêm thông tin đơn để hiển thị số tiền chuẩn từ DB
    $orderAmount = null;
    $orderItems  = [];

    if ($orderId) {
        // 1) Lấy tổng tiền trong đơn
        try {
            $stmt = $pdo->prepare("SELECT total FROM orders WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $orderAmount = (float)($row['total'] ?? null);
            }
        } catch (Exception $e) {
            error_log("VNPay: cannot fetch order total for {$orderId}: " . $e->getMessage());
        }

        // 2) Lấy danh sách sản phẩm trong đơn (dùng order_details)
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    od.product_id,
                    od.variant_id,
                    COALESCE(p.name, '') AS product_name,
                    od.quantity,
                    od.price,
                    COALESCE(p.image, '') AS image
                FROM order_details od
                LEFT JOIN products p ON od.product_id = p.id
                WHERE od.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("VNPay: cannot fetch order items for {$orderId}: " . $e->getMessage());
            $orderItems = [];
        }
    }

    // === HTML output (bản giống của bạn, chỉ dùng biến $paymentStatus, $message, $orderId, $vnp_Amount, $orderAmount, $orderItems) ===
    ?>
    <style>
        .vnpay-wrapper { padding: 40px 0 50px; }
        .vnpay-container { max-width: 800px; margin: 0 auto; padding: 0 16px; box-sizing: border-box; }
        .vnpay-card { background: #ffffff; border-radius: 20px; padding: 32px 36px; text-align: center; }
        .vnpay-card.failed { border-top-color: #ef4444; }
        .vnpay-icon { font-size: 56px; margin-bottom: 12px; }
        .vnpay-status-success { color: #16a34a; font-size: 22px; margin: 0 0 6px; font-weight: 700; }
        .vnpay-status-failed { color: #b91c1c; font-size: 22px; margin: 0 0 6px; font-weight: 700; }
        .vnpay-subtext { margin: 0 0 14px; color: #6b7280; font-size: 14px; }
        .vnpay-text { margin: 4px 0; color: #374151; font-size: 15px; }
        .vnpay-order-id { font-weight: 600; color: #111827; }
        .vnpay-amount { margin-top: 12px; font-size: 20px; font-weight: 700; color: #d62b70; }
        .vnpay-btn-group { margin-top: 24px; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .vnpay-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 190px; height: 46px; padding: 0 26px; border-radius: 999px; font-size: 14px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: transform 0.1s ease, box-shadow 0.18s ease, background 0.18s ease; box-sizing: border-box; }
        .vnpay-btn-primary { background: #d62b70; color: #ffffff; box-shadow: 0 8px 20px rgba(214, 43, 112, 0.55); }
        .vnpay-btn-primary:hover { background: #b6245c; transform: translateY(-1px); box-shadow: 0 10px 24px rgba(214, 43, 112, 0.7); }
        .vnpay-btn-secondary { background: #020617; color: #ffffff; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.55); }
        .vnpay-btn-secondary:hover { background: #000000; transform: translateY(-1px); box-shadow: 0 10px 24px rgba(15, 23, 42, 0.7); }
        .vnpay-items { margin-top: 24px; text-align: left; }
        .vnpay-items-title { font-size: 16px; font-weight: 600; margin-bottom: 10px; color: #111827; }
        .vnpay-items-list { display: flex; flex-direction: column; gap: 10px; }
        .vnpay-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 12px; background: #f9fafb; }
        .vnpay-item-thumb { width: 64px; height: 64px; border-radius: 10px; overflow: hidden; flex-shrink: 0; border: 1px solid #e5e7eb; }
        .vnpay-item-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .vnpay-item-info { flex: 1; min-width: 0; }
        .vnpay-item-name { font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .vnpay-item-meta { font-size: 13px; color: #4b5563; }
        @media (max-width: 576px) {
            .vnpay-card { padding: 24px 18px; border-radius: 16px; }
            .vnpay-btn { width: 100%; }
            .vnpay-item { align-items: flex-start; }
        }
    </style>

    <div class="vnpay-wrapper">
        <div class="vnpay-container">
            <div class="vnpay-card <?= ($paymentStatus === 'success') ? '' : 'failed' ?>">
                <?php if ($paymentStatus === 'success'): ?>
                    <div class="vnpay-icon">✅</div>
                    <h2 class="vnpay-status-success"><?= htmlspecialchars($message) ?></h2>
                    <p class="vnpay-subtext">Cảm ơn bạn đã thanh toán qua VNPay.</p>
                <?php else: ?>
                    <div class="vnpay-icon">❌</div>
                    <h2 class="vnpay-status-failed"><?= htmlspecialchars($message) ?></h2>
                    <p class="vnpay-subtext">Đã xảy ra lỗi trong quá trình thanh toán. Vui lòng thử lại hoặc liên hệ hỗ trợ.</p>
                <?php endif; ?>

                <p class="vnpay-text">
                    Mã đơn hàng:
                    <span class="vnpay-order-id">#<?= htmlspecialchars($orderId) ?></span>
                </p>

                <p class="vnpay-amount">
                    Số tiền: <?= number_format($vnp_Amount, 0, '', '.') ?>₫
                    <?php if ($orderAmount !== null && (int)$orderAmount !== (int)$vnp_Amount): ?>
                        <br><small>(Số tiền trong đơn: <?= number_format($orderAmount, 0, '', '.') ?>₫)</small>
                    <?php endif; ?>
                </p>

                <?php if (!empty($orderItems)): ?>
                    <div class="vnpay-items">
                        <div class="vnpay-items-title">Sản phẩm trong đơn</div>
                        <div class="vnpay-items-list">
                            <?php foreach ($orderItems as $item): ?>
                                <?php
                                $img = $item['image'] ?? '';
                                if (!empty($img)) {
                                    if (strpos($img, 'http') !== 0 && strpos($img, '/') !== 0) {
                                        $img = '/clothing_store/uploads/products/' . ltrim($img, '/');
                                    }
                                }
                                ?>
                                <div class="vnpay-item">
                                    <?php if (!empty($img)): ?>
                                        <div class="vnpay-item-thumb">
                                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                                        </div>
                                    <?php endif; ?>
                                    <div class="vnpay-item-info">
                                        <div class="vnpay-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                        <div class="vnpay-item-meta">
                                            SL: <?= (int)$item['quantity'] ?> × <?= number_format($item['price'], 0, '', '.') ?>₫
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="vnpay-btn-group">
                    <a class="vnpay-btn vnpay-btn-primary" href="order_success.php?order_id=<?= urlencode($orderId) ?>">Xem chi tiết đơn hàng</a>
                    <a class="vnpay-btn vnpay-btn-secondary" href="./index.php">Về trang chủ</a>
                </div>
            </div>
        </div>
    </div>

    <?php
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}

/* =========================================================
   PHÂN NHÁNH: ĐI TẠO THANH TOÁN HAY NHẬN KẾT QUẢ?
========================================================= */

// Nếu có vnp_SecureHash => đây là callback từ VNPay
if (isset($_GET['vnp_SecureHash'])) {
    handleVnpayReturn($pdo, $vnp_HashSecret);
}

// Ngược lại: đây là bước từ checkout sang để tạo thanh toán
$orderId = $_GET['order_id'] ?? 0;
try {
    redirectToVnpay($pdo, $orderId, $vnp_Url, $vnp_Returnurl, $vnp_TmnCode, $vnp_HashSecret, $vnp_BankCode);
} catch (Exception $e) {
    require_once dirname(__DIR__) . '/includes/header.php';
    ?>
    <div class="container py-5 text-center">
        <h3 class="text-danger">Không thể khởi tạo thanh toán VNPay</h3>
        <p><?= htmlspecialchars($e->getMessage()) ?></p>
        <a href="checkout.php" class="btn btn-danger mt-3">Quay lại thanh toán</a>
    </div>
    <?php
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}
