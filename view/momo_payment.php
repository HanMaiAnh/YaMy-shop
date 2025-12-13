<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

if (!isset($_SESSION['order_id']) || !isset($_SESSION['order_total'])) {
    header("Location: checkout.php");
    exit;
}

// ===== 1. Lấy ID đơn hàng nội bộ & tạo orderId riêng cho MoMo =====
$internalOrderId = (string)$_SESSION['order_id'];  // VD: 36
$amount          = (string)$_SESSION['order_total'];

// orderId gửi lên MoMo: UNIQUE
$momoOrderId = $internalOrderId . '_' . time();    // VD: "36_1732169999"

// requestId cũng nên unique, có thể dùng luôn momoOrderId
$requestId   = $momoOrderId;

// Lưu orderId gốc vào extraData để lúc MoMo trả về mình biết đơn nào
$extraData = base64_encode(json_encode([
    'order_id' => $internalOrderId
]));

$orderInfo = "Thanh toán đơn hàng #{$internalOrderId} qua MoMo";


// ===== 2. Cấu hình MoMo sandbox =====
$endpoint    = "https://test-payment.momo.vn/v2/gateway/api/create";
$partnerCode = "MOMO";
$accessKey   = "F8BBA842ECF85";
$secretKey   = "K951B6PE1waDMi640xX08PD3vg6EkVlz";

$redirectUrl = "http://localhost/momo_return.php";
$ipnUrl      = "http://localhost/momo_ipn_fake.php";
$requestType = "payWithATM";


// ===== 3. Tạo rawHash & signature =====
$rawHash = "accessKey={$accessKey}"
         . "&amount={$amount}"
         . "&extraData={$extraData}"
         . "&ipnUrl={$ipnUrl}"
         . "&orderId={$momoOrderId}"
         . "&orderInfo={$orderInfo}"
         . "&partnerCode={$partnerCode}"
         . "&redirectUrl={$redirectUrl}"
         . "&requestId={$requestId}"
         . "&requestType={$requestType}";

$signature = hash_hmac("sha256", $rawHash, $secretKey);


// ===== 4. Data gửi MoMo =====
$data = [
    "partnerCode" => $partnerCode,
    "partnerName" => "Test Store",
    "storeId"     => "MomoTest",
    "accessKey"   => $accessKey,
    "requestId"   => $requestId,
    "amount"      => $amount,
    "orderId"     => $momoOrderId,   // <- dùng momoOrderId
    "orderInfo"   => $orderInfo,
    "redirectUrl" => $redirectUrl,
    "ipnUrl"      => $ipnUrl,
    "lang"        => "vi",
    "extraData"   => $extraData,
    "requestType" => $requestType,
    "signature"   => $signature
];

$payload = json_encode($data, JSON_UNESCAPED_UNICODE);


// ===== 5. Gửi cURL =====
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$result = curl_exec($ch);

if ($result === false) {
    echo "<h3>Lỗi kết nối đến MoMo:</h3>";
    echo curl_error($ch);
    curl_close($ch);
    exit;
}

curl_close($ch);
$response = json_decode($result, true);


// ===== 6. Xử lý kết quả =====
if (isset($response['payUrl']) && !empty($response['payUrl'])) {
    header("Location: " . $response['payUrl']);
    exit;
} else {
    echo "<h3>Lỗi thanh toán MoMo:</h3>";
    echo "<pre>";
    var_dump($response);
    echo "</pre>";
}



// 1	NGUYEN VAN A	9704 0000 0000 0018	03/07	OTP	Thành công
// 2	NGUYEN VAN A	9704 0000 0000 0026	03/07	OTP	Thẻ bị khóa
// 3	NGUYEN VAN A	9704 0000 0000 0034	03/07	OTP	Nguồn tiền không đủ
// 4	NGUYEN VAN A	9704 0000 0000 0042	03/07	OTP	Hạn mức thẻ