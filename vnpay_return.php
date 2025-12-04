<?php
session_start();
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/config_vnpay.php';

// LẤY TẤT CẢ THAM SỐ TRẢ VỀ TỪ VNPAY (GET)
$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';

$inputData = array();
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

$orderId    = $_GET['vnp_TxnRef'] ?? 0;        // Mã đơn hàng mình đã gửi
$vnp_Amount = ($_GET['vnp_Amount'] ?? 0) / 100; // VNPay trả về *100

// Mặc định
$paymentStatus = 'failed';
$message       = 'Thanh toán thất bại.';

// Kiểm tra checksum
if ($secureHash === $vnp_SecureHash) {
    // Checksum đúng
    if ($_GET['vnp_ResponseCode'] == '00' && $_GET['vnp_TransactionStatus'] == '00') {
        $paymentStatus = 'success';
        $message       = 'Thanh toán VNPay thành công.';

        // Cập nhật trạng thái đơn hàng trong DB: từ 'pending' -> 'paid'
        if ($orderId) {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', payment_method='vnpay' WHERE id = ?");
            $stmt->execute([$orderId]);
        }
    } else {
        $message = 'Thanh toán không thành công. Mã lỗi: ' . ($_GET['vnp_ResponseCode'] ?? 'unknown');
    }
} else {
    $message = 'Chữ ký không hợp lệ (Sai HashSecret / dữ liệu bị thay đổi).';
}
?>
<!-- CHỈ THÊM STYLE & HTML NỘI DUNG, KHÔNG MỞ LẠI <html>, <body> -->
<style>
    .vnpay-wrapper {
        padding: 40px 0 50px;
    }

    .vnpay-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 0 16px;
        box-sizing: border-box;
    }

    .vnpay-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 32px 36px;
        text-align: center;

    }

    .vnpay-card.failed {
        border-top-color: #ef4444;
    }

    .vnpay-icon {
        font-size: 56px;
        margin-bottom: 12px;
    }

    .vnpay-status-success {
        color: #16a34a;
        font-size: 22px;
        margin: 0 0 6px;
        font-weight: 700;
    }

    .vnpay-status-failed {
        color: #b91c1c;
        font-size: 22px;
        margin: 0 0 6px;
        font-weight: 700;
    }

    .vnpay-subtext {
        margin: 0 0 14px;
        color: #6b7280;
        font-size: 14px;
    }

    .vnpay-text {
        margin: 4px 0;
        color: #374151;
        font-size: 15px;
    }

    .vnpay-order-id {
        font-weight: 600;
        color: #111827;
    }

    .vnpay-amount {
        margin-top: 12px;
        font-size: 20px;
        font-weight: 700;
        color: #d62b70; /* số tiền theo màu brand */
    }

    .vnpay-btn-group {
        margin-top: 24px;
        display: flex;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .vnpay-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 190px;
        height: 46px;
        padding: 0 26px;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: transform 0.1s ease, box-shadow 0.18s ease, background 0.18s ease;
        box-sizing: border-box;
    }

    .vnpay-btn-primary {
        background: #d62b70; /* nút chính màu brand */
        color: #ffffff;
        box-shadow: 0 8px 20px rgba(214, 43, 112, 0.55);
    }

    .vnpay-btn-primary:hover {
        background: #b6245c;
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(214, 43, 112, 0.7);
    }

    .vnpay-btn-secondary {
        background: #020617; /* nút đen như hình demo */
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.55);
    }

    .vnpay-btn-secondary:hover {
        background: #000000;
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.7);
    }

    @media (max-width: 576px) {
        .vnpay-card {
            padding: 24px 18px;
            border-radius: 16px;
        }

        .vnpay-btn {
            width: 100%;
        }
    }
</style>

<div class="vnpay-wrapper">
    <div class="vnpay-container">
        <div class="vnpay-card <?= $paymentStatus === 'success' ? '' : 'failed' ?>">
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
            </p>

            <div class="vnpay-btn-group">
                <a class="vnpay-btn vnpay-btn-primary"
                   href="order_success.php?order_id=<?= urlencode($orderId) ?>">
                    Xem chi tiết đơn hàng
                </a>
                <a class="vnpay-btn vnpay-btn-secondary"
                   href="./index.php">
                    Về trang chủ
                </a>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
