<?php
ob_start();
session_start();
require_once dirname(__DIR__) . "/config/db.php";
require_once dirname(__DIR__) . "/includes/header.php";

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---------------------- 1. BẮT BUỘC LOGIN ----------------------
if (empty($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// ---------------------- 2. LẤY SELECTED KEYS ----------------------
$selected_keys = $_POST["selected_keys"] ?? [];

if (is_string($selected_keys)) {
    $selected_keys = json_decode($selected_keys, true);
}

if (!is_array($selected_keys) || empty($selected_keys)) {
    echo '<div class="container py-5 text-center">
            <h3>Không có sản phẩm nào được chọn để thanh toán!</h3>
            <a href="cart.php" class="btn btn-danger mt-3">Quay lại giỏ hàng</a>
          </div>';
    include dirname(__DIR__) . "/includes/footer.php";
    exit;
}

// ---------------------- 3. LẤY SẢN PHẨM TRONG CART ----------------------
$cart = $_SESSION["cart"] ?? [];
$items = [];

foreach ($selected_keys as $k) {
    if (isset($cart[$k])) {
        $items[$k] = $cart[$k];
    }
}

if (empty($items)) {
    echo '<div class="container py-5 text-center">
            <h3>Giỏ hàng trống hoặc sản phẩm không còn tồn tại.</h3>
            <a href="cart.php" class="btn btn-danger mt-3">Quay lại giỏ hàng</a>
          </div>';
    include dirname(__DIR__) . "/includes/footer.php";
    exit;
}

// ---------------------- 4. TÍNH TỔNG TIỀN ----------------------
$total = 0;
foreach ($items as $it) {
    $total += $it["price"] * $it["quantity"];
}

// ---------------------- 5. LẤY HỒ SƠ USER ----------------------
$userInfo = ["email" => "", "name" => "", "phone" => "", "address" => ""];

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $userInfo["email"]   = $u["email"] ?? "";
        $userInfo["name"]    = $u["full_name"] ?? ($u["username"] ?? "");
        $userInfo["phone"]   = $u["phone"] ?? "";
        $userInfo["address"] = $u["address"] ?? "";
    }
} catch (Exception $e) {}

// ---------------------- 6. ĐẶT HÀNG ----------------------
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["place_order"])) {

    $rec_email   = trim($_POST["recipient_email"]);
    $rec_name    = trim($_POST["recipient_name"]);
    $rec_phone   = trim($_POST["recipient_phone"]);
    $rec_address = trim($_POST["recipient_address"]);
    $payment     = $_POST["payment_method"] ?? "cod";

    if (!$rec_email || !$rec_name || !$rec_phone || !$rec_address) {
        $error_msg = "Vui lòng nhập đầy đủ thông tin!";
    } else {

        try {
            $pdo->beginTransaction();

            // ------- Insert vào orders -------
            $stmt = $pdo->prepare("
                INSERT INTO orders 
                (user_id, total, payment_method, status, recipient_name, recipient_phone, recipient_email, recipient_address, created_at)
                VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $user_id, $total, $payment,
                $rec_name, $rec_phone, $rec_email, $rec_address
            ]);

            $order_id = $pdo->lastInsertId();

            if (!$order_id) throw new Exception("Không thể tạo đơn hàng.");

            // ------- Insert vào order_details -------
            $cols = $pdo->query("SHOW COLUMNS FROM order_details")->fetchAll(PDO::FETCH_COLUMN, 0);
            $insertCols = array_diff($cols, ["id"]); 
            $sql_cols = implode(",", $insertCols);
            $placeholders = implode(",", array_fill(0, count($insertCols), "?"));
            $stmtDet = $pdo->prepare("INSERT INTO order_details ($sql_cols) VALUES ($placeholders)");

            foreach ($items as $it) {
                $row = [];
                foreach ($insertCols as $c) {
                    switch ($c) {
                        case "order_id":    $row[] = $order_id; break;
                        case "product_id":  $row[] = $it["product_id"]; break;
                        case "quantity":    $row[] = $it["quantity"]; break;
                        case "price":       $row[] = $it["price"]; break;
                        default:            $row[] = null;
                    }
                }
                $stmtDet->execute($row);
            }

            // Xóa các món đã mua khỏi giỏ
            foreach ($selected_keys as $k) unset($_SESSION["cart"][$k]);

            $pdo->commit();

            // ---------------------- REDIRECT PAYMENT ----------------------

            // ===== VNPay =====
            if ($payment === "vnpay") {

                require_once dirname(__DIR__) . "/config/config_vnpay.php";

                $amount = intval($total);
                if ($amount <= 0) die("Tổng tiền không hợp lệ.");

                $vnp_Amount = $amount * 100;
                $vnp_TxnRef = $order_id;

                $inputData = [
                    "vnp_Version"    => "2.1.0",
                    "vnp_Command"    => "pay",
                    "vnp_TmnCode"    => $vnp_TmnCode,
                    "vnp_Amount"     => $vnp_Amount,
                    "vnp_CurrCode"   => "VND",
                    "vnp_TxnRef"     => $vnp_TxnRef,
                    "vnp_OrderInfo"  => "Thanh toán đơn hàng #" . $order_id,
                    "vnp_OrderType"  => "billpayment",
                    "vnp_ReturnUrl"  => $vnp_Returnurl,
                    "vnp_Locale"     => "vn",
                    "vnp_IpAddr"     => $_SERVER['REMOTE_ADDR'],
                    "vnp_CreateDate" => date("YmdHis"),
                    "vnp_ExpireDate" => date("YmdHis", strtotime("+15 minutes"))
                ];

                ksort($inputData);
                $hashData = urldecode(http_build_query($inputData));
                $vnp_SecureHash = hash_hmac("sha512", $hashData, $vnp_HashSecret);

                $redirectUrl = $vnp_Url . "?" . http_build_query($inputData) . "&vnp_SecureHash=" . $vnp_SecureHash;

                header("Location: $redirectUrl");
                exit;
            }

            // ===== MOMO =====
            if ($payment === "momo") {
                header("Location: momo_payment.php?order_id=$order_id");
                exit;
            }

            // ===== COD =====
            header("Location: order_success.php?order_id=$order_id");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = "Lỗi đặt hàng: " . $e->getMessage();
        }
    }
}
?>
<style>
body{background:#fafafa;font-family:Arial;}
.checkout{max-width:1200px;margin:auto;padding:30px;display:flex;gap:25px;}
.box{background:#fff;padding:25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
.left{flex:2;}
.right{flex:1;}
.item{display:flex;gap:12px;margin-bottom:12px;}
.item img{width:60px;height:60px;border-radius:6px;object-fit:cover;}
</style>

<div class="checkout">

    <div class="box left">
        <h3>Thông tin giao hàng</h3>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?= $error_msg ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php foreach ($selected_keys as $k): ?>
                <input type="hidden" name="selected_keys[]" value="<?= htmlspecialchars($k) ?>">
            <?php endforeach; ?>

            <label>Email</label>
            <input class="form-control" name="recipient_email" required value="<?= htmlspecialchars($userInfo["email"]) ?>">

            <label>Họ và tên</label>
            <input class="form-control" name="recipient_name" required value="<?= htmlspecialchars($userInfo["name"]) ?>">

            <label>Số điện thoại</label>
            <input class="form-control" name="recipient_phone" required value="<?= htmlspecialchars($userInfo["phone"]) ?>">

            <label>Địa chỉ</label>
            <textarea class="form-control" name="recipient_address" required><?= htmlspecialchars($userInfo["address"]) ?></textarea>

            <h5 class="mt-3">Phương thức thanh toán</h5>
            <label><input type="radio" name="payment_method" value="cod" checked> COD</label><br>
            <label><input type="radio" name="payment_method" value="vnpay"> VNPay</label><br>
            <label><input type="radio" name="payment_method" value="momo"> MoMo</label><br>

            <button name="place_order" class="btn btn-danger w-100 mt-3">ĐẶT HÀNG</button>
        </form>
    </div>

    <div class="box right">
        <h4>Đơn hàng (<?= count($items) ?> sản phẩm)</h4>

        <?php foreach ($items as $it): ?>
            <div class="item">
                <img src="<?= htmlspecialchars($it["image"]) ?>">
                <div>
                    <div><?= htmlspecialchars($it["name"]) ?></div>
                    <small class="text-muted">
                        x<?= $it["quantity"] ?>
                    </small>
                </div>
                <div class="ms-auto"><?= number_format($it["price"] * $it["quantity"]) ?>₫</div>
            </div>
        <?php endforeach; ?>

        <hr>
        <h5 class="text-end text-danger">Tổng: <?= number_format($total) ?>₫</h5>

        <a href="cart.php" class="d-block mt-3 text-danger">← Quay về giỏ hàng</a>
    </div>

</div>

<?php include dirname(__DIR__) . "/includes/footer.php"; ?>
