<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

// =====================
// 1. Lấy dữ liệu MoMo trả về
// =====================
$partnerCode  = $_GET['partnerCode']  ?? '';
$orderIdMoMo  = $_GET['orderId']      ?? ''; // VD: 36_1732169999
$requestId    = $_GET['requestId']    ?? '';
$amount       = $_GET['amount']       ?? '';
$orderInfo    = $_GET['orderInfo']    ?? '';
$orderType    = $_GET['orderType']    ?? '';
$transId      = $_GET['transId']      ?? '';
$resultCode   = $_GET['resultCode']   ?? '';
$message      = $_GET['message']      ?? '';
$payType      = $_GET['payType']      ?? '';
$responseTime = $_GET['responseTime'] ?? '';
$extraData    = $_GET['extraData']    ?? '';
$signature    = $_GET['signature']    ?? '';

// =====================
// 2. Xác định ID đơn hàng nội bộ (trong DB của bạn)
// =====================
$internalOrderId = null;

// Ưu tiên đọc từ extraData (do lúc tạo yêu cầu mình đã base64 + json_encode)
if (!empty($extraData)) {
    $decoded = json_decode(base64_decode($extraData), true);
    if (is_array($decoded) && isset($decoded['order_id'])) {
        $internalOrderId = (int)$decoded['order_id'];
    }
}

// Nếu extraData không có thì fallback: tách từ orderIdMoMo "36_1732..."
if (!$internalOrderId && !empty($orderIdMoMo)) {
    $parts = explode('_', $orderIdMoMo);
    if (!empty($parts[0])) {
        $internalOrderId = (int)$parts[0];
    }
}

// =====================
// 3. Xử lý kết quả thanh toán
// =====================
// MoMo: resultCode = "0" là thành công
$isPaid = false;
$error  = '';

if ($resultCode === '0') {
    if ($internalOrderId) {
        // Cập nhật trạng thái đơn hàng trong DB
        $stmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
        $stmt->execute([$internalOrderId]);

        $isPaid = true;

        // Xóa giỏ hàng & tổng tiền trong session (nếu còn)
        unset($_SESSION['cart']);
        unset($_SESSION['order_id']);
        unset($_SESSION['order_total']);
    } else {
        $error = 'Không xác định được mã đơn hàng nội bộ!';
    }
} else {
    // Thanh toán thất bại, MoMo trả về message + resultCode
    $error = "Thanh toán thất bại. Mã lỗi: {$resultCode} - {$message}";
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kết quả thanh toán MoMo</title>
    <style>
        body { font-family: Arial, sans-serif; background: #fafafa; padding: 50px; text-align: center; }
        .success { color: green; }
        .error { color: red; }
        a { display: inline-block; margin-top: 20px; color: #d32f2f; text-decoration: none; font-weight: bold; }
        .box {
            background: #fff;
            display: inline-block;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .info { margin-top: 15px; font-size: 14px; color: #555; text-align: left; }
        .info p { margin: 4px 0; }
    </style>
</head>
<body>
    <div class="box">
        <?php if ($isPaid): ?>
            <h2 class="success">Thanh toán MoMo thành công!</h2>
            <p>Đơn hàng #<?= htmlspecialchars($internalOrderId) ?> đã được ghi nhận và cập nhật trạng thái <b>paid</b>.</p>
        <?php else: ?>
            <h2 class="error">Thanh toán MoMo không thành công</h2>
            <p><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <div class="info">
            <p><b>Mã giao dịch MoMo:</b> <?= htmlspecialchars($transId) ?></p>
            <p><b>Mã đơn MoMo (orderId):</b> <?= htmlspecialchars($orderIdMoMo) ?></p>
            <p><b>Số tiền:</b> <?= htmlspecialchars($amount) ?> VND</p>
            <p><b>Thông điệp:</b> <?= htmlspecialchars($message) ?></p>
        </div>

        <a href="/index.php">← Quay về trang chủ</a>
    </div>
</body>
</html>
