<?php
// view/checkout.php
ob_start();
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/config_vnpay.php';

// Nạp header (giao diện, nav, các hàm chung khác)
require_once dirname(__DIR__) . '/includes/header.php';

// --------------------- đảm bảo nạp includes/stock.php (nơi chứa reduceStockAfterPayment) ---------------------
// Nếu hàm chưa tồn tại thì require file stock.php; nếu đã tồn tại (ví dụ header tự định nghĩa) thì không require.
$stock_file = dirname(__DIR__) . '/includes/stock.php';
if (!function_exists('reduceStockAfterPayment')) {
    if (file_exists($stock_file)) {
        require_once $stock_file;
    } else {
        error_log("includes/stock.php not found at: " . $stock_file);
        // Không throw để tránh crash giao diện; khi gọi hàm sẽ báo lỗi rõ hơn
    }
}

// Bật exception mode PDO (nếu chưa)
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
    if (in_array('fullname', $userCols, true)) $selectUserCols[] = 'fullname';
    if (in_array('username',  $userCols, true)) $selectUserCols[] = 'username';
    if (in_array('phone',     $userCols, true)) $selectUserCols[] = 'phone';
    if (in_array('address',   $userCols, true)) $selectUserCols[] = 'address';

    $sqlUser = "SELECT " . implode(', ', $selectUserCols) . " FROM users WHERE id = ?";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([$user_id]);
    if ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $profile_email   = $u['email'] ?? '';
        // Ưu tiên fullname, nếu không có thì dùng username
        $profile_name    = $u['fullname'] ?? ($u['username'] ?? '');
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
    echo '<div class="container py-5 text-center">
            <h3>Không có sản phẩm nào để thanh toán!</h3>
            <a href="cart.php" class="btn btn-danger mt-3">Quay lại giỏ hàng</a>
          </div>';
    include dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// ================== TÍNH TỔNG TIỀN (GIÁ CUỐI) ==================
$cart_subtotal = 0;

foreach ($cart_items as $k => &$it) {
    // LUÔN dùng giá cuối
    $price = (float)($it['price'] ?? 0);
    $qty   = (int)($it['quantity'] ?? 0);

    $subtotal = $price * $qty;
    $it['subtotal'] = $subtotal;
    $cart_subtotal += $subtotal;
}
unset($it);

// ================== VOUCHER ==================
$voucher_info    = $_SESSION['applied_voucher'] ?? null;
$discount_amount = 0;
$voucher_code    = '';

if (is_array($voucher_info)) {
    $voucher_code = (string)($voucher_info['code'] ?? '');
    $discount_amount = (float)($voucher_info['discount_amount'] ?? 0);

    // Chặn giảm quá mức
    if ($discount_amount > $cart_subtotal) {
        $discount_amount = $cart_subtotal;
    }
}

// ================== TỔNG TIỀN CUỐI ==================
$grand_total = max(0, $cart_subtotal - $discount_amount);




// ================== CẤU TRÚC BẢNG ORDERS ==================
$orderCols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `orders`")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (is_array($cols)) $orderCols = $cols;
} catch (Exception $e) {
    error_log("Không thể lấy cấu trúc orders: " . $e->getMessage());
}

/**
 * Hàm helper: tạo URL VNPay (trả về string)
 * Tái sử dụng thay vì duplicate code
 */
function createVnpayUrl(PDO $pdo, int $orderId, string $vnp_Url, string $vnp_Returnurl, string $vnp_TmnCode, string $vnp_HashSecret, string $vnp_BankCode = ''): string
{
    // Lấy thông tin đơn (dùng total từ orders)
    $stmt = $pdo->prepare("SELECT total FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng #' . $orderId);
    }

    $amount = (float)($order['total'] ?? 0);
    if ($amount <= 0) {
        throw new Exception('Tổng tiền đơn hàng không hợp lệ.');
    }

    $vnp_Amount = $amount * 100;
    $vnp_TxnRef    = (string)$orderId; // Mã đơn hàng
    $vnp_OrderInfo = "Thanh toan don hang #{$orderId}";
    $vnp_OrderType = "billpayment";
    $vnp_Locale    = "vn";
    $vnp_IpAddr    = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $inputData = [
        "vnp_Version"   => "2.1.0",
        "vnp_TmnCode"   => $vnp_TmnCode,
        "vnp_Amount"    => $vnp_Amount,
        "vnp_Command"   => "pay",
        "vnp_CreateDate"=> date('YmdHis'),
        "vnp_CurrCode"  => "VND",
        "vnp_IpAddr"    => $vnp_IpAddr,
        "vnp_Locale"    => $vnp_Locale,
        "vnp_OrderInfo" => $vnp_OrderInfo,
        "vnp_OrderType" => $vnp_OrderType,
        "vnp_ReturnUrl" => $vnp_Returnurl,
        "vnp_TxnRef"    => $vnp_TxnRef,
    ];

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
        // đảm bảo có dấu & trước param vnp_SecureHash nếu cần
        $vnp_UrlFull .= 'vnp_SecureHash=' . $vnpSecureHash;
    }

    return $vnp_UrlFull;
}

// ================== XỬ LÝ ĐẶT HÀNG ==================
// Chỉ xử lý đặt hàng khi POST mà KHÔNG có from_cart (tức là submit form ở checkout, không phải POST từ cart.php)
$isSubmitOrder = ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['from_cart']));

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
                $values[]     = 'pending';
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

            // ------------------ Insert order_details ------------------
            $stmtDet = $pdo->prepare("INSERT INTO order_details (order_id, product_id, variant_id, quantity, price) VALUES (?, ?, ?, ?, ?)");

            // kiểm tra products có cột quantity hay không (fallback nếu không có variant)
            $hasProductsQuantity = false;
            try {
                $colCheck = $pdo->query("SHOW COLUMNS FROM products LIKE 'quantity'")->fetch(PDO::FETCH_ASSOC);
                if ($colCheck) $hasProductsQuantity = true;
            } catch (Exception $e) {
                // ignore
            }

            foreach ($cart_items as $k => $it) {
                $qty       = (int)($it['quantity'] ?? 0);
                $price     = (float)($it['price'] ?? 0);
                $productId = (int)($it['product_id'] ?? 0);
                if ($qty <= 0 || $productId <= 0) continue;

                // try to get variant_id from cart first
                $variantId = isset($it['variant_id']) ? (int)$it['variant_id'] : 0;

                // if not present, try map from size/color
                if ($variantId === 0) {
                    $sizeId  = $it['size_id'] ?? ($it['size'] ?? null);
                    $colorId = $it['color_id'] ?? ($it['color'] ?? null);
                    if ($sizeId || $colorId) {
                        // findVariantId might be in header or in stock.php
                        if (function_exists('findVariantId')) {
                            $variantId = findVariantId($pdo, $productId, $sizeId, $colorId);
                        } else {
                            // fallback: leave 0
                            $variantId = 0;
                        }
                    }
                }

                // if still 0: allow only if products.quantity exists (we can deduct from products)
                if ($variantId === 0 && !$hasProductsQuantity) {
                    // không có variant và products không có cột quantity -> block để tránh order thiếu biến thể
                    throw new Exception("Vui lòng chọn biến thể cho sản phẩm #{$productId} trước khi đặt hàng.");
                }

                // execute insert (variant_id may be 0 if allowed)
                $stmtDet->execute([$order_id, $productId, $variantId, $qty, $price]);
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

                    // fullname hoặc username
                    if ($recipient_name !== '') {
                        if (in_array('fullname', $userCols, true)) {
                            $uUpdates[] = 'fullname = ?';
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

        // ===== TRỪ LƯỢT VOUCHER (COD – SAU KHI ORDER OK) =====
if (!empty($_SESSION['applied_voucher']['id'])) {
    $vid = (int)$_SESSION['applied_voucher']['id'];

    $stmt = $pdo->prepare("
        UPDATE vouchers 
        SET quantity = quantity - 1 
        WHERE id = ? AND quantity > 0
    ");
    $stmt->execute([$vid]);
}
    

            // === Sau khi commit thành công ===
            // Không xóa toàn bộ cart ngay lập tức.
            // Thay vào đó: chỉ remove những item đã tạo order (những key trong $cart_items)

            // Lấy danh sách keys đã xử lý (những item đã được insert vào order_details)
            $processed_keys = array_keys($cart_items);

            // Nếu phương thức thanh toán là vnpay -> order chờ thanh toán (pending)
            // Không xóa session cart ngay; lưu order pending để callback có thể xác nhận
            if ($payment_method === 'vnpay') {
                // Lưu order pending để callback biết order nào đang chờ
                $_SESSION['pending_order_id'] = $order_id;
                // Lưu các key liên quan (callback hoặc user cancel có thể cần)
                $_SESSION['pending_order_keys'] = $processed_keys;
                // Không unset cart để tránh mất giỏ hàng khi user chưa thanh toán
            } else {
                // ====== COD: trừ tồn kho ngay (markPaid = false) ======
                if (!function_exists('reduceStockAfterPayment')) {
                    // Nếu hàm không tồn tại => không thể trừ kho tự động
                    $resReduce = ['success' => false, 'message' => 'Hàm trừ tồn kho không tồn tại trên server.'];
                } else {
                    try {
                        $resReduce = reduceStockAfterPayment($pdo, (int)$order_id, false);
                    } catch (Exception $e) {
                        $resReduce = ['success' => false, 'message' => 'Lỗi khi gọi hàm trừ tồn kho: ' . $e->getMessage()];
                    }
                }

                if (!empty($resReduce) && $resReduce['success']) {
                    // Nếu trừ kho thành công -> xóa các key trong cart tương ứng
                    foreach ($processed_keys as $k) {
                        if (isset($_SESSION['cart'][$k])) {
                            unset($_SESSION['cart'][$k]);
                        }
                    }
                    if (empty($_SESSION['cart'])) {
                        unset($_SESSION['cart']);
                    }
                    // Xóa voucher / selected_keys vì đã hoàn tất
                    unset($_SESSION['applied_voucher']);
                    unset($_SESSION['checkout_selected_keys']);

                    // Redirect về trang chi tiết đơn hàng
                    header("Location: order_success.php?order_id=" . urlencode($order_id));
                    exit;
                } else {
                    // Trừ kho thất bại -> log và set order status để admin xử lý
                    $errMsg = $resReduce['message'] ?? 'Không xác định';
                    error_log("Reduce stock failed for order {$order_id}: " . $errMsg);

                    // Lưu thông báo lỗi vào session để hiển thị tạm thời
                    $_SESSION['order_processing_error_' . $order_id] = $errMsg;

                    // Cập nhật trạng thái đơn để admin xử lý (ví dụ: stock_failed)
                    try {
                        $stmtErr = $pdo->prepare("UPDATE orders SET status = 'stock_failed' WHERE id = ?");
                        $stmtErr->execute([$order_id]);
                    } catch (Exception $e) {
                        error_log("Failed to update orders.status for order {$order_id}: " . $e->getMessage());
                    }

                    // Redirect vẫn về trang chi tiết đơn để bạn kiểm tra trạng thái/thiếu hàng
                    header("Location: order_success.php?order_id=" . urlencode($order_id));
                    exit;
                }
            }

            // === REDIRECT THEO PHƯƠNG THỨC THANH TOÁN ===
            if ($payment_method === 'vnpay') {
                // Tạo URL VNPay bằng helper để đảm bảo $vnp_UrlFull luôn tồn tại
                try {
                    $vnp_UrlFull = createVnpayUrl($pdo, (int)$order_id, $vnp_Url, $vnp_Returnurl, $vnp_TmnCode, $vnp_HashSecret, $vnp_BankCode ?? '');
                    header('Location: ' . $vnp_UrlFull);
                    exit;
                } catch (Exception $e) {
                    // Nếu tạo URL thất bại -> log và thông báo user (không redirect)
                    error_log("Create VNPay URL failed for order {$order_id}: " . $e->getMessage());
                    $error_msg = "Không thể khởi tạo thanh toán VNPay: " . htmlspecialchars($e->getMessage());
                }
            } else {
                // NOTE: COD flow already redirected above after stock processing
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
/* ... giữ nguyên CSS như trước ... */
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

            <div>
                <label for="recipient_email">Email <small>(Nhập email để nhận thông tin đơn hàng)</small></label>
                <input type="email" name="recipient_email" id="recipient_email" class="form-control" required
                       value="<?= htmlspecialchars($val_recipient_email) ?>">
            </div>

            <div>
                <label for="recipient_name">Họ và tên <small>(Người nhận hàng)</small></label>
                <input type="text" name="recipient_name" id="recipient_name" class="form-control" required
                       value="<?= htmlspecialchars($val_recipient_name) ?>">
            </div>

            <div>
                <label for="recipient_phone">Số điện thoại <small>(Liên hệ khi giao hàng)</small></label>
                <input type="text" name="recipient_phone" id="recipient_phone" class="form-control" required
                       value="<?= htmlspecialchars($val_recipient_phone) ?>">
            </div>

            <div>
                <label for="recipient_address">Địa chỉ nhận hàng <small>(Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành)</small></label>
                <textarea name="recipient_address" id="recipient_address" class="form-control" rows="2" required><?= htmlspecialchars($val_recipient_address) ?></textarea>
            </div>

            <h4>Phương thức thanh toán</h4>
            <?php
            $pm = $_POST['payment_method'] ?? 'cod';
            ?>
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
                    <span class="value">0₫</span>
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

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
