<?php
// view/checkout.php
ob_start();
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/header.php';
// BẮT BUỘC: load cấu hình VNPay để dùng khi redirect
require_once dirname(__DIR__) . '/config/config_vnpay.php';

// Bật exception mode PDO (nếu chưa)
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// -----------------------
// HANDLE VNPAY RETURN / CALLBACK (nếu vnp gửi về đây)
// -----------------------
if (!empty($_GET['vnp_TxnRef']) || !empty($_GET['vnp_ResponseCode']) || !empty($_GET['vnp_SecureHash'])) {
    // Kiểm tra secure hash
    $vnp_Params = $_GET;
    $vnp_SecureHash = isset($vnp_Params['vnp_SecureHash']) ? $vnp_Params['vnp_SecureHash'] : '';
    // Remove secure hash params for build hash data
    unset($vnp_Params['vnp_SecureHash']);
    unset($vnp_Params['vnp_SecureHashType']);

    ksort($vnp_Params);
    $hashdata = [];
    foreach ($vnp_Params as $key => $value) {
        if (substr($key, 0, 4) === "vnp_") {
            $hashdata[] = $key . '=' . $value;
        }
    }
    $hashdata = implode('&', $hashdata);

    $calcHash = '';
    if (!empty($vnp_HashSecret)) {
        $calcHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
    }

    $vnp_TxnRef = isset($_GET['vnp_TxnRef']) ? $_GET['vnp_TxnRef'] : null;
    $vnp_ResponseCode = isset($_GET['vnp_ResponseCode']) ? $_GET['vnp_ResponseCode'] : null;

    // Nếu hash hợp lệ và response code = 00 => payment success
    if ($calcHash && hash_equals($calcHash, $vnp_SecureHash)) {
        // thành công khi response code == '00'
        if ($vnp_ResponseCode === '00') {
            try {
                $pdo->beginTransaction();

                // Cập nhật đơn hàng: đánh dấu paid
                $orderId = (int)$vnp_TxnRef;
                if ($orderId > 0) {
                    // Nếu bảng orders có cột status/paid_at, cập nhật
                    $updateSql = "UPDATE orders SET status = ?, paid_at = NOW() WHERE id = ?";
                    $stmtUpd = $pdo->prepare($updateSql);
                    $stmtUpd->execute(['Đã giao hàng', $orderId]);
                }

                // Xóa các selected_keys đã lưu trong pending_order session (nếu có)
                if (!empty($_SESSION['pending_order']) && is_array($_SESSION['pending_order'])) {
                    $pending = $_SESSION['pending_order'];
                    $selKeys = $pending['selected_keys'] ?? [];
                    if (!empty($selKeys) && isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                        foreach ($selKeys as $k) {
                            if (isset($_SESSION['cart'][$k])) {
                                unset($_SESSION['cart'][$k]);
                            }
                        }
                        if (empty($_SESSION['cart'])) {
                            unset($_SESSION['cart']);
                        }
                    }
                    // xóa voucher đã dùng
                    if (isset($_SESSION['applied_voucher'])) {
                        unset($_SESSION['applied_voucher']);
                    }
                    // cleanup pending_order
                    unset($_SESSION['pending_order']);
                }

                $pdo->commit();

                // redirect tới trang thành công
                header('Location: order_success.php?order_id=' . urlencode($orderId));
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('VNPAY finalize error: ' . $e->getMessage());
                // Nếu có lỗi, redirect về cart với message
                $_SESSION['flash_error'] = 'Xử lý thanh toán gặp lỗi. Vui lòng liên hệ hỗ trợ.';
                header('Location: cart.php');
                exit;
            }
        } else {
            // payment failed or cancelled
            try {
                $pdo->beginTransaction();
                $orderId = (int)$vnp_TxnRef;
                if ($orderId > 0) {
                    $stmtFail = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmtFail->execute(['Hủy đơn hàng', $orderId]);
                }

                // Nếu bạn đã giảm stock khi tạo order, cần logic trả lại stock ở đây (nếu muốn).
                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('VNPAY failure handling error: ' . $e->getMessage());
            }

            $_SESSION['flash_error'] = 'Thanh toán không thành công hoặc đã bị hủy.';
            header('Location: cart.php');
            exit;
        }
    } else {
        // Secure hash không hợp lệ
        error_log('VNPAY verify failed. calcHash=' . $calcHash . ' | received=' . $vnp_SecureHash);
        $_SESSION['flash_error'] = 'Không thể xác thực phản hồi thanh toán (invalid signature).';
        header('Location: cart.php');
        exit;
    }
}

// --- Kiểm tra đăng nhập ---
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ================== LẤY THÔNG TIN HỒ SƠ USER ==================
$profile_email   = '';
$profile_name    = '';
$profile_phone   = '';
$profile_address = '';
$userCols        = [];

try {
    // Lấy cấu trúc bảng users
    $userCols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN, 0);
    $userCols = is_array($userCols) ? $userCols : [];

    // Cột nào tồn tại thì select cột đó
    $selectUserCols = ['id', 'email'];
    if (in_array('full_name', $userCols, true)) $selectUserCols[] = 'full_name';
    if (in_array('username',  $userCols, true)) $selectUserCols[] = 'username';
    if (in_array('phone',     $userCols, true)) $selectUserCols[] = 'phone';
    if (in_array('address',   $userCols, true)) $selectUserCols[] = 'address';

    $sqlUser = "SELECT " . implode(', ', $selectUserCols) . " FROM users WHERE id = ?";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([$user_id]);
    if ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $profile_email   = $u['email'] ?? '';
        // Ưu tiên full_name, nếu không có thì dùng username
        $profile_name    = $u['full_name'] ?? ($u['username'] ?? '');
        $profile_phone   = $u['phone'] ?? '';
        $profile_address = $u['address'] ?? '';
    }
} catch (Exception $e) {
    error_log("Fetch profile for checkout error: " . $e->getMessage());
}

// ================== LẤY DANH SÁCH SẢN PHẨM ĐƯỢC CHỌN ==================
// session cart: key: 'v_123' => [ product_id, variant_id, name, price, quantity, image, size, color ... ]
$session_cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

// Lấy selected_keys từ POST (lần đầu đi từ cart) hoặc từ session (khi submit form đặt hàng)
$selected_keys = [];
if (!empty($_POST['selected_keys']) && is_array($_POST['selected_keys'])) {
    $selected_keys = array_values(array_unique(array_map('strval', $_POST['selected_keys'])));
    $_SESSION['checkout_selected_keys'] = $selected_keys;
} elseif (!empty($_SESSION['checkout_selected_keys']) && is_array($_SESSION['checkout_selected_keys'])) {
    $selected_keys = $_SESSION['checkout_selected_keys'];
}

// Lọc cart theo selected_keys; nếu không có selected_keys thì lấy toàn bộ giỏ
$cart_items = [];
if (!empty($selected_keys)) {
    foreach ($selected_keys as $k) {
        if (isset($session_cart[$k]) && is_array($session_cart[$k])) {
            $item = $session_cart[$k];
            $qty  = (int)($item['quantity'] ?? 0);
            if ($qty > 0) {
                $cart_items[$k] = $item;
            }
        }
    }
} else {
    // fallback nếu người dùng vào trực tiếp checkout không qua cart
    foreach ($session_cart as $k => $item) {
        if (!is_array($item)) continue;
        $qty = (int)($item['quantity'] ?? 0);
        if ($qty > 0) {
            $cart_items[$k] = $item;
        }
    }
}

// nếu giỏ hàng rỗng -> thông báo
if (empty($cart_items)) {
    echo '<div class="container py-5 text-center">'
       . '<h3>Không có sản phẩm nào để thanh toán!</h3>'
       . '<a href="cart.php" class="btn btn-danger mt-3">Quay lại giỏ hàng</a>'
       . '</div>';
    include dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// ================== TÍNH TỔNG TIỀN + VOUCHER ==================
$cart_subtotal = 0;
// ================== CONFIG SHIPPING ==================
define('SHIP_THRESHOLD', 500000); // 500k
define('SHIP_FEE', 30000);        // 30k

foreach ($cart_items as $k => &$it) {
    $price = (float)($it['price'] ?? 0);
    $qty   = (int)($it['quantity'] ?? 0);
    $subtotal = $price * $qty;
    $it['subtotal'] = $subtotal;
    $cart_subtotal += $subtotal;
}
unset($it);

// --- XỬ LÝ remove voucher (nếu user click nút xóa) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['remove_voucher'])) {
    unset($_SESSION['applied_voucher']);
    // redirect để tránh resubmit
    header('Location: checkout.php');
    exit;
}

// --- ÁP DỤNG voucher nếu có trong session (không cần checkbox) ---
$voucher_info    = $_SESSION['applied_voucher'] ?? null;
$discount_amount = 0;
$voucher_code    = '';
if (is_array($voucher_info)) {
    $discount_amount = (float)($voucher_info['discount_amount'] ?? 0);
    $voucher_code    = (string)($voucher_info['code'] ?? '');
    if ($discount_amount > $cart_subtotal) {
        $discount_amount = $cart_subtotal;
    }
}

// ================== TÍNH PHÍ SHIP ==================
$shipping_fee = 0;
if ($cart_subtotal > 0 && $cart_subtotal < SHIP_THRESHOLD) {
    $shipping_fee = SHIP_FEE;
}

// Tổng cuối = tiền hàng - voucher + ship
$grand_total = max(0, $cart_subtotal - $discount_amount + $shipping_fee);


// ================== CẤU TRÚC BẢNG ORDERS ==================
$orderCols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `orders`")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (is_array($cols)) $orderCols = $cols;
} catch (Exception $e) {
    error_log("Không thể lấy cấu trúc orders: " . $e->getMessage());
}

// ================== XỬ LÝ ĐẶT HÀNG ==================
// Chỉ xử lý đặt hàng khi POST mà KHÔNG có from_cart (tức là submit form ở checkout, không phải POST từ cart.php)
$isSubmitOrder = ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['from_cart']) && empty($_POST['remove_voucher']));

$error_msg = '';
if ($isSubmitOrder) {
    $recipient_name    = trim($_POST['recipient_name']    ?? '');
    $recipient_phone   = trim($_POST['recipient_phone']   ?? '');
    $recipient_address = trim($_POST['recipient_address'] ?? '');
    $recipient_email   = trim($_POST['recipient_email']   ?? '');
    $payment_method    = trim($_POST['payment_method']    ?? 'cod');

    // Validate cơ bản
    if (!$recipient_name || !$recipient_phone || !$recipient_address || !$recipient_email) {
        $error_msg = "Vui lòng nhập đầy đủ thông tin!";
    } elseif ($grand_total <= 0) {
        $error_msg = "Giá trị đơn hàng không hợp lệ!";
    } else {
        try {
            $pdo->beginTransaction();

            // Build dynamic insert based on existing columns
            $insertCols   = [];
            $values       = [];

            // user_id
            if (in_array('user_id', $orderCols, true)) {
                $insertCols[] = 'user_id';
                $values[]     = $user_id;
            }

            // optional recipient columns
            if (in_array('recipient_name', $orderCols, true)) {
                $insertCols[] = 'recipient_name';
                $values[]     = $recipient_name;
            }
            if (in_array('recipient_phone', $orderCols, true)) {
                $insertCols[] = 'recipient_phone';
                $values[]     = $recipient_phone;
            }
            if (in_array('recipient_address', $orderCols, true)) {
                $insertCols[] = 'recipient_address';
                $values[]     = $recipient_address;
            }
            if (in_array('recipient_email', $orderCols, true)) {
                $insertCols[] = 'recipient_email';
                $values[]     = $recipient_email;
            }

            // subtotal nếu có cột
            if (in_array('subtotal', $orderCols, true)) {
                $insertCols[] = 'subtotal';
                $values[]     = $cart_subtotal;
            }
            // shipping_fee nếu có
if (in_array('shipping_fee', $orderCols, true)) {
    $insertCols[] = 'shipping_fee';
    $values[]     = $shipping_fee;
}

            // discount_amount nếu có
            if (in_array('discount_amount', $orderCols, true)) {
                $insertCols[] = 'discount_amount';
                $values[]     = $discount_amount;
            }

            // voucher_code nếu có
            if (in_array('voucher_code', $orderCols, true) && $voucher_code !== '') {
                $insertCols[] = 'voucher_code';
                $values[]     = $voucher_code;
            }

            // total (final)
            if (in_array('total', $orderCols, true)) {
                $insertCols[] = 'total';
                $values[]     = $grand_total;
            }

            // Nếu có cột final_total riêng
            if (in_array('final_total', $orderCols, true)) {
                $insertCols[] = 'final_total';
                $values[]     = $grand_total;
            }

            // payment_method optional
            if (in_array('payment_method', $orderCols, true)) {
                $insertCols[] = 'payment_method';
                $values[]     = $payment_method;
            }

            // status (if exists) set to pending
            if (in_array('status', $orderCols, true)) {
                $insertCols[] = 'status';
                $values[] = ($payment_method === 'vnpay')
                    ? 'Chờ thanh toán'
                    : 'Chờ xác nhận';
            }

            // created_at nếu tồn tại -> NOW()
            $cols_sql  = implode(', ', $insertCols);
            $ph_parts  = array_fill(0, count($insertCols), '?');

            if (in_array('created_at', $orderCols, true)) {
                $cols_sql  .= ', created_at';
                $ph_parts[] = 'NOW()';
            }

            $placeholders_sql = implode(', ', $ph_parts);

            $sql = "INSERT INTO `orders` ({$cols_sql}) VALUES ({$placeholders_sql})";
            $stmtIns = $pdo->prepare($sql);
            $stmtIns->execute($values);
            $order_id = $pdo->lastInsertId();
            if (!$order_id) {
                throw new Exception('Không lấy được order_id sau khi insert orders.');
            }

            // Insert order_details
            $stmtDet = $pdo->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            foreach ($cart_items as $k => $it) {
                $qty       = (int)($it['quantity'] ?? 0);
                $price     = (float)($it['price'] ?? 0);
                $productId = (int)($it['product_id'] ?? 0);
                if ($qty <= 0 || $productId <= 0) continue;

                $stmtDet->execute([$order_id, $productId, $qty, $price]);
            }

            // ============= CẬP NHẬT LẠI HỒ SƠ USER =============
            try {
                if (!empty($userCols)) {
                    $uUpdates = [];
                    $uParams  = [];

                    // email
                    if ($recipient_email !== '' && in_array('email', $userCols, true)) {
                        $uUpdates[] = 'email = ?';
                        $uParams[]  = $recipient_email;
                    }

                    // full_name hoặc username
                    if ($recipient_name !== '') {
                        if (in_array('full_name', $userCols, true)) {
                            $uUpdates[] = 'full_name = ?';
                            $uParams[]  = $recipient_name;
                        } elseif (in_array('username', $userCols, true)) {
                            $uUpdates[] = 'username = ?';
                            $uParams[]  = $recipient_name;
                        }
                    }

                    // phone
                    if (in_array('phone', $userCols, true)) {
                        $uUpdates[] = 'phone = ?';
                        $uParams[]  = $recipient_phone;
                    }

                    // address
                    if (in_array('address', $userCols, true)) {
                        $uUpdates[] = 'address = ?';
                        $uParams[]  = $recipient_address;
                    }

                    if ($uUpdates) {
                        $uParams[] = $user_id;
                        $sqlU = "UPDATE users SET " . implode(', ', $uUpdates) . " WHERE id = ?";
                        $stmtU = $pdo->prepare($sqlU);
                        $stmtU->execute($uParams);
                    }
                }
            } catch (Exception $e) {
                // Không rollback vì đơn hàng vẫn nên thành công, chỉ log lỗi
                error_log("Update user from checkout error: " . $e->getMessage());
            }

            $pdo->commit();

            // --- Thay đổi: KHÔNG xóa toàn bộ $_SESSION['cart'] ngay lập tức ---
            // Lấy danh sách selected keys hiện tại (những item đã chèn vào order)
            $selected_keys_for_order = array_keys($cart_items);

            // Lưu pending_order vào session (dùng khi redirect sang gateway)
            $_SESSION['pending_order'] = [
                'order_id'      => (int)$order_id,
                'selected_keys' => $selected_keys_for_order,
                'total'         => $grand_total,
                'created_at'    => time(),
            ];

            // Xóa checkout_selected_keys vì đã được lưu vào pending_order
            unset($_SESSION['checkout_selected_keys']);

            // Nếu phương thức là COD => an toàn xóa những item đã chọn ngay
            if ($payment_method === 'cod') {
                foreach ($selected_keys_for_order as $k) {
                    if (isset($_SESSION['cart'][$k])) {
                        unset($_SESSION['cart'][$k]);
                    }
                }
                // nếu cart rỗng thì remove luôn key cart
                if (empty($_SESSION['cart'])) {
                    unset($_SESSION['cart']);
                }
                // voucher đã dùng => xóa (COD đã hoàn tất)
                unset($_SESSION['applied_voucher']);

                // Redirect về trang thành công COD
                header("Location: order_success.php?order_id=" . urlencode($order_id));
                exit;
            }

            // Nếu phương thức khác (ví dụ vnpay) => giữ cart nguyên, redirect sang gateway
            // pending_order đã lưu, sẽ xử lý xóa sau khi nhận callback/return và xác thực thanh toán.

            // ================== REDIRECT THEO PHƯƠNG THỨC THANH TOÁN ==================
            if ($payment_method === 'vnpay') {
                // TẠO LINK THANH TOÁN VNPAY DÙNG CONFIG
                $vnp_Version   = "2.1.0";
                $vnp_TxnRef    = (string)$order_id; // Mã đơn hàng
                $vnp_OrderInfo = "Thanh toan don hang #" . $order_id;
                $vnp_OrderType = "billpayment";
                $vnp_Amount    = $grand_total * 100; // Nhân 100 theo quy định VNPay
                $vnp_Locale    = "vn";
                $vnp_IpAddr    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

                $inputData = [
                    "vnp_Version"   => $vnp_Version,
                    "vnp_TmnCode"   => $vnp_TmnCode,      // từ config_vnpay.php
                    "vnp_Amount"    => $vnp_Amount,
                    "vnp_Command"   => "pay",
                    "vnp_CreateDate"=> date('YmdHis'),
                    "vnp_CurrCode"  => "VND",
                    "vnp_IpAddr"    => $vnp_IpAddr,
                    "vnp_Locale"    => $vnp_Locale,
                    "vnp_OrderInfo" => $vnp_OrderInfo,
                    "vnp_OrderType" => $vnp_OrderType,
                    "vnp_ReturnUrl" => $vnp_Returnurl,   // từ config_vnpay.php
                    "vnp_TxnRef"    => $vnp_TxnRef,
                ];

                // Nếu bạn cấu hình cố định BankCode thì gửi kèm
                if (!empty($vnp_BankCode)) {
                    $inputData['vnp_BankCode'] = $vnp_BankCode;
                }

                ksort($inputData);

                $query    = "";
                $hashdata = "";
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

                $vnp_UrlFull = $vnp_Url . "?" . $query;
                if (!empty($vnp_HashSecret)) {
                    $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
                    $vnp_UrlFull  .= 'vnp_SecureHash=' . $vnpSecureHash;
                }

                header('Location: ' . $vnp_UrlFull);
                exit;
            } else {
                // COD handled above already
                header("Location: order_success.php?order_id=" . urlencode($order_id));
                exit;
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Checkout error: " . $e->getMessage());
            $error_msg = "Đặt hàng thất bại: " . htmlspecialchars($e->getMessage());
        }
    }
}

// ================== GIÁ TRỊ MẶC ĐỊNH CHO FORM (PREFILL) ==================
$val_recipient_email   = $_POST['recipient_email']   ?? $profile_email;
$val_recipient_name    = $_POST['recipient_name']    ?? $profile_name;
$val_recipient_phone   = $_POST['recipient_phone']   ?? $profile_phone;
$val_recipient_address = $_POST['recipient_address'] ?? $profile_address;

// ================== HTML ==================
?>
<style>
body { background: #fafafa; font-family: Arial, sans-serif; }
.checkout-container { display: flex; gap: 30px; justify-content: center; align-items: flex-start; padding: 40px; }
.checkout-left, .checkout-right { background: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); padding: 30px; }
.checkout-left { flex: 2; position: sticky; top: 100px; align-self: flex-start; height: fit-content; }
.checkout-right { flex: 1; position: sticky; top: 100px; align-self: flex-start; height: fit-content; }
h2 { font-size: 1.5rem; margin-bottom: 20px; text-align: center; }
label { font-weight: bold; display: block; margin-bottom: 5px; color: #333; }
.form-control, textarea, select { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; }
.btn-danger { background: #d32f4f; color: #fff; border: none; padding: 12px; font-weight: bold; width: 100%; border-radius: 6px; cursor: pointer; }
.btn-danger:hover { background: #b71c1c; }
.summary-item { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.summary-item img { width: 60px; height: 60px; border-radius: 5px; object-fit: cover; }
.summary-total { font-weight: bold; font-size: 1.2rem; color: #c00; text-align: right; margin-top: 10px; }
.summary-label { color: #666; }
.alert { padding: 12px; margin-bottom: 15px; border-radius: 5px; }
.alert-danger { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

.summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
.summary-row span { font-size: 0.95rem; }
.summary-row .label { color: #666; }
.summary-row .value { font-weight: 600; }

@media (max-width: 991px) {
    .checkout-container { flex-direction: column; padding: 20px 10px; }
    .checkout-left, .checkout-right { position: static; width: 100%; }
}
</style>

<form method="POST">
    <div class="checkout-container">

<!-- LEFT -->
<div class="checkout-left">
    <h2>Thông tin mua hàng</h2>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= $error_msg ?></div>
    <?php endif; ?>

    <!-- EMAIL -->
    <div>
        <label for="recipient_email">
            Email <small>(Nhập email để nhận thông tin đơn hàng)</small>
        </label>
        <input
            type="email"
            name="recipient_email"
            id="recipient_email"
            class="form-control"
            required
            title="Email không được để trống và phải đúng định dạng"
            value="<?= htmlspecialchars($val_recipient_email) ?>"
        >
    </div>

    <!-- HỌ TÊN -->
    <div>
        <label for="recipient_name">
            Họ và tên <small>(Người nhận hàng)</small>
        </label>
        <input
            type="text"
            name="recipient_name"
            id="recipient_name"
            class="form-control"
            required
            pattern="^[A-Za-zÀ-ỹ\s]{3,50}$"
            title="Họ tên không được để trống, chỉ chứa chữ, tối thiểu 3 ký tự"
            value="<?= htmlspecialchars($val_recipient_name) ?>"
        >
    </div>

    <!-- SỐ ĐIỆN THOẠI -->
    <div>
        <label for="recipient_phone">
            Số điện thoại <small>(Liên hệ khi giao hàng)</small>
        </label>
        <input
            type="tel"
            name="recipient_phone"
            id="recipient_phone"
            class="form-control"
            required
            pattern="^(0[3|5|7|8|9])[0-9]{8}$"
            title="Số điện thoại Việt Nam hợp lệ (VD: 0912345678)"
            value="<?= htmlspecialchars($val_recipient_phone) ?>"
        >
    </div>

    <!-- ĐỊA CHỈ -->
    <div>
        <label for="recipient_address">
            Địa chỉ nhận hàng
            <small>(Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành)</small>
        </label>
        <textarea
            name="recipient_address"
            id="recipient_address"
            class="form-control"
            rows="2"
            required
            minlength="5"
            title="Địa chỉ không được để trống và phải đủ chi tiết (ít nhất 5 ký tự)"
        ><?= htmlspecialchars($val_recipient_address) ?></textarea>
    </div>

    <h4>Phương thức thanh toán</h4>
    <?php $pm = $_POST['payment_method'] ?? 'cod'; ?>
    <label>
        <input type="radio" name="payment_method" value="cod" <?= $pm === 'cod' ? 'checked' : '' ?>>
        Thanh toán khi nhận hàng (COD)
    </label><br>
    <label>
        <input type="radio" name="payment_method" value="vnpay" <?= $pm === 'vnpay' ? 'checked' : '' ?>>
        Thanh toán qua VNPay
    </label><br>
</div>


        <!-- RIGHT -->
        <div class="checkout-right">
            <h4>Đơn hàng (<?= count($cart_items) ?> sản phẩm)</h4>
            <?php if (!empty($cart_items)): ?>
                <?php foreach ($cart_items as $k => $it):
                    $qty      = (int)($it['quantity'] ?? 0);
                    $price    = (float)($it['price'] ?? 0);
                    $subtotal = $it['subtotal'] ?? ($price * $qty);
                    $imgFile  = $it['image'] ?? '';
                    // Nếu bạn có hàm upload() giống cart.php thì dùng:
                    if (function_exists('upload')) {
                        $imgSrc = $imgFile ? upload($imgFile) : upload('no-image.png');
                    } else {
                        $imgSrc = '../uploads/' . htmlspecialchars($imgFile);
                    }
                ?>
                    <div class="summary-item">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="">
                        <div>
                            <div><?= htmlspecialchars($it['name'] ?? '') ?></div>
                            <div class="summary-label">
                                x<?= $qty ?>
                                <?php if (!empty($it['size'])): ?>
                                    • Size: <?= htmlspecialchars($it['size']) ?>
                                <?php endif; ?>
                                <?php if (!empty($it['color'])): ?>
                                    • Màu: <?= htmlspecialchars($it['color']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ms-auto"><?= number_format($subtotal,0,'','.') ?>₫</div>
                    </div>
                <?php endforeach; ?>
                <hr>

                <div class="summary-row">
                    <span class="label">Tạm tính</span>
                    <span class="value"><?= number_format($cart_subtotal,0,'','.') ?>₫</span>
                </div>

                <?php if (!empty($_SESSION['applied_voucher'])): ?>
                    <div style="margin:10px 0;">
                        <div style="font-weight:600;">
                            Mã giảm giá: <?= htmlspecialchars($_SESSION['applied_voucher']['code'] ?? '') ?>
                            ( -<?= number_format((float)($_SESSION['applied_voucher']['discount_amount'] ?? 0),0,'','.') ?>₫ )
                        </div>
                        <div style="margin-top:6px;">
                            <button type="submit" name="remove_voucher" value="1" style="background:none;border:0;color:#d32f4f;cursor:pointer;padding:0;font-size:0.95rem;">Xóa mã giảm giá</button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($discount_amount > 0): ?>
                    <div class="summary-row">
                        <span class="label">
                            Giảm giá <?= $voucher_code ? '(' . htmlspecialchars($voucher_code) . ')' : '' ?>
                        </span>
                        <span class="value">-<?= number_format($discount_amount,0,'','.') ?>₫</span>
                    </div>
                <?php endif; ?>

                <div class="summary-row">
    <span class="label">Phí vận chuyển</span>
    <span class="value">
        <?= $shipping_fee > 0
            ? number_format($shipping_fee,0,'','.') . '₫'
            : 'Miễn phí'
        ?>
    </span>
</div>


                <hr>
                <div class="summary-total">Tổng cộng: <?= number_format($grand_total,0,'','.') ?>₫</div>

                <!-- NÚT ĐẶT HÀNG CHUYỂN SANG Ô ĐƠN HÀNG -->
                <button type="submit" class="btn-danger mt-4">ĐẶT HÀNG</button>
            <?php else: ?>
                <p class="text-center text-muted">Giỏ hàng trống hoặc sản phẩm không còn tồn tại.</p>
            <?php endif; ?>
            <a href="cart.php" class="d-block mt-3 text-danger">← Quay về giỏ hàng</a>
        </div>
    </div>
</form>

<script>
document.getElementById('recipient_phone')?.addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
