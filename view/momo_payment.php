<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

if (!isset($_SESSION['order_id']) || !isset($_SESSION['order_total'])) {
    header("Location: checkout.php");
    exit;
}

// --- Config MoMo sandbox ---
$endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";
$partnerCode = "MOMO12345678"; // sandbox
$accessKey = "F8BBA842ECF85";
$secretKey = "K951B6PE1waDMi640xX08PD3vg6EkVlz";

// --- Dùng localhost cho test, MoMo không gọi notifyUrl ---
$returnUrl = "http://localhost/momo_return.php";
$notifyUrl = "http://localhost/fake_notify.php"; // chỉ để tránh blank

$orderId = $_SESSION['order_id'];
$requestId = time();
$amount = (string)$_SESSION['order_total'];
$orderInfo = "Thanh toán đơn hàng #$orderId qua MoMo";
$extraData = "";

// --- Tạo signature ---
$rawHash = "accessKey=$accessKey&amount=$amount&extraData=$extraData&orderId=$orderId&orderInfo=$orderInfo&partnerCode=$partnerCode&requestId=$requestId&returnUrl=$returnUrl&notifyUrl=$notifyUrl";
$signature = hash_hmac("sha256", $rawHash, $secretKey);

// --- Dữ liệu gửi MoMo ---
$data = [
    "partnerCode" => $partnerCode,
    "accessKey" => $accessKey,
    "requestId" => $requestId,
    "amount" => $amount,
    "orderId" => $orderId,
    "orderInfo" => $orderInfo,
    "returnUrl" => $returnUrl,
    "notifyUrl" => $notifyUrl,
    "extraData" => $extraData,
    "requestType" => "captureWallet",
    "signature" => $signature
];

$payload = json_encode($data, JSON_UNESCAPED_UNICODE);

// --- Gửi cURL ---
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$result = curl_exec($ch);
curl_close($ch);

$response = json_decode($result, true);

if (isset($response['payUrl']) && $response['payUrl'] != "") {
    header("Location: " . $response['payUrl']);
    exit();
} else {
    echo "<h3>Lỗi thanh toán MoMo:</h3>";
    echo "<pre>"; print_r($response); echo "</pre>";
}
