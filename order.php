<?php
// order.php (checkout đặt hàng)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
session_start();

// Nếu project của bạn có header riêng, chỉnh lại đường dẫn cho đúng
include_once __DIR__ . "/../layout/header.php";

// Kết nối DB (PDO) – giống các file trong /view/
require_once __DIR__ . "/../../config/db.php";
// Nếu có file cấu hình VNPay
$configPath = __DIR__ . "/../../config/config.php";
if (file_exists($configPath)) {
    include_once $configPath;
}

// Bắt buộc đăng nhập (nếu hệ thống bạn yêu cầu)
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Giỏ hàng trống → quay lại cart
if (empty($_SESSION['cart'])) {
    header('Location: ../cart.php');
    exit;
}

// Tính tổng tiền hàng
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $price = isset($item['price']) ? (float)$item['price'] : 0;
    $qty   = isset($item['quantity']) ? (int)$item['quantity'] : 1;
    $total += $price * $qty;
}
$shipping_fee = 0;
$grandTotal   = $total + $shipping_fee;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pttt    = $_POST['pttt'] ?? 'cod';  // cod / vnpay / momo...

    if ($name === '' || $phone === '' || $address === '') {
        echo "<div style='color:red;padding:10px;border:1px solid #f00;margin:10px 0;'>Vui lòng nhập đầy đủ Họ tên, SĐT, Địa chỉ.</div>";
    } else {
        try {
            // Bắt đầu transaction để an toàn
            $pdo->beginTransaction();

            // Lưu đơn hàng vào bảng orders
            // Giả sử bảng orders có các cột:
            // id, user_id, recipient_name, recipient_phone, recipient_address,
            // total, payment_method, status, note, created_at
            $sqlOrder = "
                INSERT INTO orders 
                    (user_id, recipient_name, recipient_phone, recipient_address, total, payment_method, status, created_at)
                VALUES 
                    (:user_id, :rname, :rphone, :raddress, :total, :pmethod, :status, NOW())
            ";
            $stmtOrder = $pdo->prepare($sqlOrder);
            $stmtOrder->execute([
                ':user_id'   => (int)$_SESSION['user_id'],
                ':rname'     => $name,
                ':rphone'    => $phone,
                ':raddress'  => $address,
                ':total'     => $grandTotal,
                ':pmethod'   => $pttt,
                ':status'    => 'pending', // hoặc 'Đang xử lý'
            ]);

            $order_id = (int)$pdo->lastInsertId();

            // Lưu chi tiết đơn hàng
            $sqlItem = "
                INSERT INTO order_details (order_id, product_id, quantity, price)
                VALUES (:order_id, :product_id, :quantity, :price)
            ";
            $stmtItem = $pdo->prepare($sqlItem);

            foreach ($_SESSION['cart'] as $item) {
                $product_id = (int)($item['id'] ?? 0);
                $quantity   = (int)($item['quantity'] ?? 1);
                $price_item = (float)($item['price'] ?? 0);

                if ($product_id <= 0) {
                    continue;
                }

                $stmtItem->execute([
                    ':order_id'   => $order_id,
                    ':product_id' => $product_id,
                    ':quantity'   => $quantity,
                    ':price'      => $price_item,
                ]);
            }

            // Commit transaction
            $pdo->commit();

            // Nếu chọn VNPay → chuyển hướng
            if (strtolower($pttt) === 'vnpay') {
                if (
                    empty($vnp_Url) || 
                    empty($vnp_TmnCode) || 
                    empty($vnp_HashSecret) || 
                    empty($vnp_Returnurl)
                ) {
                    die("<div style='color:red;padding:10px;border:1px solid #f00;'>Thiếu cấu hình VNPay. Vui lòng kiểm tra file config/config.php.</div>");
                }

                $vnp_TxnRef    = $order_id;
                $vnp_Amount    = (int)($grandTotal * 100);
                $vnp_Locale    = 'vn';
                $vnp_BankCode  = '';
                $vnp_IpAddr    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $vnp_ExpireDate= date('YmdHis', strtotime('+15 minutes'));

                $inputData = [
                    "vnp_Version"   => "2.1.0",
                    "vnp_TmnCode"   => $vnp_TmnCode,
                    "vnp_Amount"    => $vnp_Amount,
                    "vnp_Command"   => "pay",
                    "vnp_CreateDate"=> date('YmdHis'),
                    "vnp_CurrCode"  => "VND",
                    "vnp_IpAddr"    => $vnp_IpAddr,
                    "vnp_Locale"    => $vnp_Locale,
                    "vnp_OrderInfo" => "Thanh toán đơn hàng #" . $order_id,
                    "vnp_OrderType" => "other",
                    "vnp_ReturnUrl" => $vnp_Returnurl,
                    "vnp_TxnRef"    => $vnp_TxnRef,
                    "vnp_ExpireDate"=> $vnp_ExpireDate
                ];

                if (!empty($vnp_BankCode)) {
                    $inputData['vnp_BankCode'] = $vnp_BankCode;
                }

                ksort($inputData);
                $hashdata = "";
                $query    = "";
                $i = 0;
                foreach ($inputData as $key => $value) {
                    if ($i == 1) {
                        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
                    } else {
                        $hashdata .= urlencode($key) . "=" . urlencode($value);
                        $i = 1;
                    }
                    $query .= urlencode($key) . "=" . urlencode($value) . '&';
                }

                $vnp_Url_full = $vnp_Url . "?" . $query;
                if (!empty($vnp_HashSecret)) {
                    $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
                    $vnp_Url_full .= 'vnp_SecureHash=' . $vnpSecureHash;
                }

                unset($_SESSION['cart']); // clear giỏ trước khi đi thanh toán
                header('Location: ' . $vnp_Url_full);
                ob_end_flush();
                exit;
            } else {
                // Thanh toán COD
                unset($_SESSION['cart']);
                header("Location: order-success.php?order_id=" . urlencode($order_id));
                exit;
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "<div style='color:red;padding:10px;border:1px solid #f00;margin:10px 0;'>
                    Lỗi khi lưu đơn hàng: " . htmlspecialchars($e->getMessage()) . "
                  </div>";
        }
    }
}
?>

<!-- HTML giao diện giống file cũ của bạn -->
<style>
.checkout-container {
    display: flex;
    justify-content: center;
    gap: 30px;
    padding: 40px;
    background: #fafafa;
    font-family: 'Inter', sans-serif;
}
.checkout-left, .checkout-right {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.checkout-left { flex: 2; }
.checkout-right { flex: 1; }
.checkout-left h3, .checkout-right h3 { margin-bottom: 20px; font-weight: 600; }
.checkout-left input {  padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; width: 100%; }
.summary { font-size: 15px; line-height: 1.8; }
.summary p strong { font-size: 16px; }
.btn-submit { width: 100%; background: #fb923c; color: white; border: none; border-radius: 8px; padding: 14px 0; font-weight: 600; cursor: pointer; }
.btn-submit:hover { background: #f97316; }
.order-item { display: flex; align-items: center; margin-bottom: 15px; }
.order-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin-right: 10px; }
.order-item-info { flex: 1; }
.coupon-box { margin-top: 15px; display: flex; gap: 8px; }
.coupon-box input { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 8px; }
.coupon-box button { background: #fb923c; color: #fff; border: none; border-radius: 8px; padding: 8px 16px; }
</style>

<div class="checkout-container">
    <div class="checkout-left">
        <h3>Thông tin giao hàng</h3>
        <form method="POST">
            <input type="text" name="name" placeholder="Nhập họ và tên" required>
            <input type="text" name="phone" placeholder="Nhập số điện thoại" required>
            <input type="text" name="address" placeholder="Địa chỉ, tên đường" required>

            <p><strong>Phương thức thanh toán</strong></p>
            <label><input type="radio" name="pttt" value="cod" checked> Thanh toán khi nhận hàng</label><br>
            <label><input type="radio" name="pttt" value="vnpay"> Thanh toán VNPay</label><br><br>

            <button type="submit" class="btn-submit">Đặt hàng</button>
        </form>
    </div>

    <div class="checkout-right">
        <h3>Giỏ hàng</h3>
        <?php foreach ($_SESSION['cart'] as $item): ?>
            <div class="order-item">
                <img src="../<?= htmlspecialchars($item['image'] ?? 'no-image.jpg'); ?>" alt="">
                <div class="order-item-info">
                    <p><?= htmlspecialchars($item['name'] ?? 'Sản phẩm'); ?></p>
                    <p><?= number_format($item['price'] ?? 0); ?>₫ × <?= (int)($item['quantity'] ?? 1); ?></p>
                </div>
            </div>
        <?php endforeach; ?>

        <hr>
        <div class="summary">
            <div class="coupon-box">
                <input type="text" placeholder="Nhập mã khuyến mãi">
                <button>Áp dụng</button>
            </div>
            <p>Tổng tiền hàng: <strong><?= number_format($total); ?>₫</strong></p>
            <p>Phí vận chuyển: <strong><?= number_format($shipping_fee); ?>₫</strong></p>
            <p><strong>Tổng thanh toán: <?= number_format($grandTotal); ?>₫</strong></p>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../layout/footer.php"; ?>
