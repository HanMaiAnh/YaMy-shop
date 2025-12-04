<?php
ob_start();
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/config/config_vnpay.php';

// --- Kiểm tra đăng nhập ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// --- Lấy các sản phẩm được chọn từ cart ---
$selected_keys = $_POST['selected_keys'] ?? [];
if (empty($selected_keys)) {
    echo '<div class="container py-5 text-center">
            <h3>Không có sản phẩm nào được chọn để thanh toán!</h3>
            <a href="cart.php" class="btn btn-danger mt-3">Quay về giỏ hàng</a>
          </div>';
    include dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// Chuẩn bị danh sách sản phẩm theo keys
$cart_items = [];
$total = 0;
foreach ($selected_keys as $key) {
    if (!isset($_SESSION['cart'][$key])) continue;
    $cart_items[$key] = $_SESSION['cart'][$key];
}

// Nếu rỗng sau lọc
if (empty($cart_items)) {
    echo '<div class="container py-5 text-center">
            <h3>Sản phẩm trong giỏ hàng không hợp lệ!</h3>
            <a href="cart.php" class="btn btn-danger mt-3">Quay về giỏ hàng</a>
          </div>';
    include dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// --- Lấy thông tin từ DB (nếu muốn cập nhật stock / price chính xác) ---
$variant_ids = array_column($cart_items, 'variant_id');
$variants_map = [];
if ($variant_ids) {
    $placeholders = implode(',', array_fill(0, count($variant_ids), '?'));
    $sql = "
        SELECT 
            v.id AS variant_id,
            v.product_id,
            v.price AS base_price,
            v.price_reduced,
            v.quantity AS stock,
            p.name AS product_name,
            (
                SELECT image_url
                FROM product_images
                WHERE product_id = v.product_id
                ORDER BY id ASC
                LIMIT 1
            ) AS image,
            s.name AS size_name,
            c.name AS color_name
        FROM product_variants v
        JOIN products p ON p.id = v.product_id
        LEFT JOIN sizes s ON s.id = v.size_id
        LEFT JOIN colors c ON c.id = v.color_id
        WHERE v.id IN ($placeholders)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($variant_ids);
    $db_variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($db_variants as $v) {
        $variants_map[$v['variant_id']] = $v;
    }
}

// --- Cập nhật giá và tính tổng ---
foreach ($cart_items as $key => &$item) {
    $vid = $item['variant_id'] ?? null;
    if ($vid !== null && isset($variants_map[$vid])) {
        $v = $variants_map[$vid];
        $final_price = ($v['price_reduced'] > 0 && $v['price_reduced'] < $v['base_price'])
            ? $v['price_reduced']
            : $v['base_price'];

        $item['final_price'] = (int)$final_price;
        $item['image']       = $v['image'] ?: 'no-image.png';
        $item['stock']       = (int)$v['stock'];
        $total += $item['final_price'] * $item['quantity'];
    } else {
        $item['final_price'] = (int)($item['price'] ?? 0);
        $total += $item['final_price'] * $item['quantity'];
    }
}
unset($item);

// ---- ÁP DỤNG VOUCHER ----
$discountAmount = 0;
if (!empty($_SESSION['applied_voucher'])) {
    $discountAmount = (int)($_SESSION['applied_voucher']['discount_amount'] ?? 0);
    $total = max(0, $total - $discountAmount);
}

// --- Xử lý đặt hàng ---
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_submit'])) {
    // khi submit lại form, selected_keys vẫn được gửi bằng hidden
    $posted_selected = $_POST['selected_keys'] ?? $selected_keys;

    $recipient_name    = trim((string)($_POST['recipient_name'] ?? ''));
    $recipient_phone   = trim((string)($_POST['recipient_phone'] ?? ''));
    $recipient_address = trim((string)($_POST['recipient_address'] ?? ''));
    $recipient_email   = trim((string)($_POST['recipient_email'] ?? ''));
    $note              = trim((string)($_POST['note'] ?? ''));
    $payment_method    = trim((string)($_POST['payment_method'] ?? 'cod'));

    if ($recipient_name === '')    $errors[] = 'Vui lòng nhập họ và tên.';
    if ($recipient_phone === '')   $errors[] = 'Vui lòng nhập số điện thoại.';
    if ($recipient_address === '') $errors[] = 'Vui lòng nhập địa chỉ nhận hàng.';
    if ($recipient_email === '') {
        $errors[] = 'Vui lòng nhập email.';
    } elseif (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Thêm đơn hàng
            $stmt = $pdo->prepare("
                INSERT INTO orders
                (user_id, recipient_name, recipient_phone, recipient_address, recipient_email, note, total, payment_method, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $recipient_name,
                $recipient_phone,
                $recipient_address,
                $recipient_email,
                $note,
                $total,
                $payment_method
            ]);
            $order_id = $pdo->lastInsertId();

            // Thêm chi tiết đơn hàng
            $stmtDetail = $pdo->prepare("
                INSERT INTO order_details (order_id, product_id, variant_id, quantity, price)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtUpd = $pdo->prepare("
                UPDATE product_variants SET quantity = quantity - ? WHERE id = ?
            ");

            foreach ($cart_items as $item) {
                $product_id_safe = $item['product_id'] ?? $item['id'] ?? 0;
                $variant_id_safe = $item['variant_id'] ?? null;
                $quantity_safe   = (int)($item['quantity'] ?? 0);
                $price_safe      = (int)($item['final_price'] ?? ($item['price'] ?? 0));

                $stmtDetail->execute([
                    $order_id,
                    $product_id_safe,
                    $variant_id_safe,
                    $quantity_safe,
                    $price_safe
                ]);

                if ($variant_id_safe) {
                    $stmtUpd->execute([$quantity_safe, $variant_id_safe]);
                }
            }

            $pdo->commit();

            // Xóa khỏi session (chỉ xoá những sản phẩm đã chọn)
            foreach ($posted_selected as $k) {
                unset($_SESSION['cart'][$k]);
            }

            // Nếu có voucher, tùy logic:
            // unset($_SESSION['applied_voucher']);

            // =========================
            //   XỬ LÝ VNPAY
            // =========================
           if ($payment_method === 'vnpay') {
    $vnp_TxnRef    = $order_id;              // Mã đơn hàng
    $vnp_Amount    = $total * 100;           // Nhân 100 theo chuẩn VNPay
    $vnp_OrderInfo = 'Thanh toan don hang #' . $order_id;
    $vnp_OrderType = 'billpayment';
    $vnp_Locale    = 'vn';
    $vnp_BankCode  = '';                     // Để trống cho khách chọn
    $vnp_IpAddr    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $expire = date('YmdHis', strtotime('+15 minutes'));

    $inputData = array(
        "vnp_Version"    => "2.1.0",
        "vnp_TmnCode"    => $vnp_TmnCode,
        "vnp_Amount"     => $vnp_Amount,
        "vnp_Command"    => "pay",
        "vnp_CreateDate" => date('YmdHis'),
        "vnp_CurrCode"   => "VND",
        "vnp_IpAddr"     => $vnp_IpAddr,
        "vnp_Locale"     => $vnp_Locale,
        "vnp_OrderInfo"  => $vnp_OrderInfo,
        "vnp_OrderType"  => $vnp_OrderType,
        "vnp_ReturnUrl"  => $vnp_Returnurl, // NHỚ TÊN BIẾN ĐÚNG
        "vnp_TxnRef"     => $vnp_TxnRef,
        "vnp_ExpireDate" => $expire
    );

    if (!empty($vnp_BankCode)) {
        $inputData['vnp_BankCode'] = $vnp_BankCode;
    }

    ksort($inputData);

    $query    = "";
    $hashdata = "";

    foreach ($inputData as $key => $value) {
        if ($query !== "") {
            $query    .= '&';
            $hashdata .= '&';
        }
        $query    .= urlencode($key) . "=" . urlencode($value);
        $hashdata .= urlencode($key) . "=" . urlencode($value);
    }

    $vnp_UrlWithParams = $vnp_Url . "?" . $query;

    if (!empty($vnp_HashSecret)) {
        $vnpSecureHash     = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        $vnp_UrlWithParams .= '&vnp_SecureHash=' . $vnpSecureHash;
    }

    header('Location: ' . $vnp_UrlWithParams);
    exit;
}


            // =========================
            //   Thanh toán COD / MoMo
            // =========================
            header("Location: order_success.php?order_id=" . $order_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Lỗi khi lưu đơn hàng: ' . $e->getMessage();
        }
    }
}
?>

<style>
body { background: #fafafa; font-family: Arial, sans-serif; }
.checkout-container {
    display: flex; gap: 30px; justify-content: center;
    align-items: flex-start; padding: 40px;
}
.checkout-left, .checkout-right {
    background: #fff; border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.05); padding: 30px;
}
.checkout-left {
    flex: 2; position: sticky; top: 30px;
    align-self: flex-start; height: fit-content;
}
.checkout-right {
    flex: 1; top: 30px;
    align-self: flex-start; height: fit-content;
}
h2 { font-size: 1.5rem; margin-bottom: 20px; text-align: center; }
label { font-weight: bold; }
.form-control, textarea, select {
    width: 100%; padding: 10px; margin-bottom: 15px;
    border: 1px solid #ddd; border-radius: 5px;
}
.btn-danger {
    background: #d32f4f; color: #fff; border: none;
    padding: 12px; font-weight: bold; width: 100%; border-radius: 6px;
}
.btn-danger:hover { background: #d32f2f; }
.summary-item {
    display: flex; align-items: center; gap: 10px; margin-bottom: 10px;
}
.summary-item img {
    width: 60px; height: 60px; border-radius: 5px; object-fit: cover;
}
.summary-total {
    font-weight: bold; font-size: 1.2rem; color: #c00;
    text-align: right; margin-top: 10px;
}
.summary-label { color: #666; }
.alert { margin-bottom: 1rem; }
@media (max-width: 991px) {
    .checkout-container { flex-direction: column; }
    .checkout-left, .checkout-right { position: static; width: 100%; }
}
</style>

<div class="container">
    <div class="checkout-container">
        <!-- LEFT -->
        <div class="checkout-left">
            <h2>Thông tin mua hàng</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $err): ?>
                        <div><?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <!-- Gửi lại selected_keys khi submit -->
                <?php foreach ($selected_keys as $k): ?>
                    <input type="hidden" name="selected_keys[]" value="<?= htmlspecialchars($k) ?>">
                <?php endforeach; ?>

                <div class="mb-2"><small class="text-muted">*Email</small></div>
                <input type="email" name="recipient_email" class="form-control" placeholder="Email" required
                       value="<?= htmlspecialchars($_POST['recipient_email'] ?? '') ?>">

                <div class="mb-2"><small class="text-muted">*Họ và tên</small></div>
                <input type="text" name="recipient_name" class="form-control" placeholder="Họ và tên" required
                       value="<?= htmlspecialchars($_POST['recipient_name'] ?? '') ?>">

                <div class="mb-2"><small class="text-muted">*Số điện thoại</small></div>
                <input type="text" name="recipient_phone" class="form-control" placeholder="Số điện thoại" required
                       value="<?= htmlspecialchars($_POST['recipient_phone'] ?? '') ?>">

                <div class="mb-2"><small class="text-muted">*Địa chỉ nhận hàng</small></div>
                <textarea name="recipient_address" class="form-control" placeholder="Địa chỉ nhận hàng" rows="2" required><?= htmlspecialchars($_POST['recipient_address'] ?? '') ?></textarea>

                <div class="mb-2"><small class="text-muted">Ghi chú (nếu có)</small></div>
                <textarea name="note" class="form-control" placeholder="Ghi chú" rows="2"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>

                <h4>Phương thức thanh toán</h4>
                <label>
                    <input type="radio" name="payment_method" value="cod"
                        <?= (($_POST['payment_method'] ?? '') === 'cod'
                            ? 'checked'
                            : (!isset($_POST['payment_method']) ? 'checked' : '')) ?>>
                    Thanh toán khi nhận hàng (COD)
                </label><br>
                <label>
                    <input type="radio" name="payment_method" value="momo"
                        <?= (($_POST['payment_method'] ?? '') === 'momo' ? 'checked' : '') ?>>
                    Thanh toán qua MoMo
                </label><br>
                <label>
                    <input type="radio" name="payment_method" value="vnpay"
                        <?= (($_POST['payment_method'] ?? '') === 'vnpay' ? 'checked' : '') ?>>
                    Thanh toán qua VNPay
                </label>

                <button type="submit" name="checkout_submit" class="btn-danger mt-4">ĐẶT HÀNG</button>
            </form>
        </div>

        <!-- RIGHT -->
        <div class="checkout-right">
            <h4>Đơn hàng (<?= count($cart_items) ?> sản phẩm)</h4>
            <?php foreach ($cart_items as $item): ?>
                <div class="summary-item">
                    <img src="<?= htmlspecialchars('../uploads/' . ($item['image'] ?: 'no-image.png')) ?>"
                         alt="<?= htmlspecialchars($item['name']) ?>">
                    <div>
                        <div><?= htmlspecialchars($item['name']) ?></div>
                        <div class="summary-label">
                            <?= !empty($item['size']) ? 'Size: ' . htmlspecialchars($item['size']) : '' ?>
                            <?= !empty($item['color']) ? ' | Màu: ' . htmlspecialchars($item['color']) : '' ?>
                            | x<?= (int)$item['quantity'] ?>
                        </div>
                    </div>
                    <div class="ms-auto">
                        <?= number_format($item['final_price'] * $item['quantity'], 0, '', '.') ?>₫
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($discountAmount) && $discountAmount > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">
                        Giảm giá (<?= htmlspecialchars($_SESSION['applied_voucher']['code'] ?? '') ?>)
                    </span>
                    <strong class="text-success">
                        -<?= number_format($discountAmount, 0, '', '.') ?>₫
                    </strong>
                </div>
            <?php endif; ?>

            <hr>
            <div class="summary-total">
                Tổng cộng: <?= number_format($total, 0, '', '.') ?>₫
            </div>
            <a href="cart.php" class="d-block mt-3 text-danger">← Quay về giỏ hàng</a>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
