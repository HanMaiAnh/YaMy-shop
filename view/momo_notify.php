<?php
// Nhận callback từ MoMo (IPN)
require_once dirname(__DIR__) . '/config/db.php';

// Lấy dữ liệu POST từ MoMo
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Debug (bỏ comment khi test)
// file_put_contents('momo_notify.log', $input.PHP_EOL, FILE_APPEND);

if (!$data) {
    http_response_code(400);
    exit("No data received");
}

// --- Lấy các trường quan trọng ---
$orderId = $data['orderId'] ?? '';
$amount = $data['amount'] ?? 0;
$resultCode = $data['resultCode'] ?? 1; // 0 = thành công
$signature = $data['signature'] ?? '';
$extraData = $data['extraData'] ?? '';

// --- Kiểm tra signature ---
$partnerCode = "MOMO12345678";
$accessKey = "F8BBA842ECF85";
$secretKey = "K951B6PE1waDMi640xX08PD3vg6EkVlz";

$rawHash = "partnerCode=$partnerCode&accessKey=$accessKey&orderId=$orderId&amount=$amount&resultCode=$resultCode&extraData=$extraData";
$checkSignature = hash_hmac("sha256", $rawHash, $secretKey);

if ($checkSignature !== $signature) {
    http_response_code(400);
    exit("Invalid signature");
}

// --- Cập nhật trạng thái đơn hàng ---
$status = ($resultCode == 0) ? 'paid' : 'failed';
$stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->execute([$status, $orderId]);

// --- Trả về MoMo ---
echo json_encode([
    "status" => "ok",
    "message" => "IPN received"
]);
