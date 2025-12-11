<?php
// view/checkout.php
ob_start();
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/header.php';

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

// ================== CHUẨN HÓA GIỎ HÀNG ==================
$raw_cart   = $_SESSION['cart'] ?? [];
$cart_items = []; // mỗi phần tử: ['id'=>int, 'quantity'=>int]

foreach ($raw_cart as $key => $val) {
    // Trường hợp key là "id|size|color" hoặc chỉ "id"
    if (is_string($key) && strpos($key, '|') !== false) {
        $parts = explode('|', $key);
        $pid   = (int)($parts[0] ?? 0);
        $qty   = is_array($val) ? (int)($val['quantity'] ?? 1) : (int)$val;
    } else {
        // key có thể là id (số) hoặc $val là mảng chứa id
        if (is_array($val) && isset($val['id'])) {
            $pid = (int)$val['id'];
            $qty = (int)($val['quantity'] ?? 1);
        } else {
            $pid = (int)$key;
            $qty = is_array($val) ? (int)($val['quantity'] ?? 1) : (int)$val;
        }
    }
    if ($pid > 0 && $qty > 0) {
        if (isset($cart_items[$pid])) $cart_items[$pid]['quantity'] += $qty;
        else $cart_items[$pid] = ['id' => $pid, 'quantity' => $qty];
    }
}

// nếu giỏ hàng rỗng -> thông báo
if (empty($cart_items)) {
    echo '<div class="container py-5 text-center">
            <h3>Giỏ hàng trống!</h3>
            <a href="index.php" class="btn btn-danger mt-3">Tiếp tục mua sắm</a>
          </div>';
    include dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// ================== LẤY THÔNG TIN PRODUCT ==================
$ids = array_keys($cart_items);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT id, name, price, image FROM products WHERE id IN ($placeholders)");
$stmt->execute($ids);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$products = [];
foreach ($rows as $r) $products[$r['id']] = $r;

// Loại bỏ item không tồn tại
foreach ($cart_items as $pid => $it) {
    if (!isset($products[$pid])) {
        unset($cart_items[$pid]);
    }
}
if (empty($cart_items)) {
    echo '<div class="container py-5 text-center">
            <h3>Giỏ hàng rỗng hoặc sản phẩm không tồn tại!</h3>
            <a href="index.php" class="btn btn-danger mt-3">Tiếp tục mua sắm</a>
          </div>';
    include dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// ================== TÍNH TỔNG TIỀN ==================
$total = 0;
foreach ($cart_items as $pid => $it) {
    $price = isset($products[$pid]) ? (float)$products[$pid]['price'] : 0;
    $qty   = (int)$it['quantity'];
    $total += $price * $qty;
}

// ================== CẤU TRÚC BẢNG ORDERS ==================
$orderCols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `orders`")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (is_array($cols)) $orderCols = $cols;
} catch (Exception $e) {
    error_log("Không thể lấy cấu trúc orders: " . $e->getMessage());
}

// ================== XỬ LÝ ĐẶT HÀNG ==================
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_name    = trim($_POST['recipient_name']    ?? '');
    $recipient_phone   = trim($_POST['recipient_phone']   ?? '');
    $recipient_address = trim($_POST['recipient_address'] ?? '');
    $recipient_email   = trim($_POST['recipient_email']   ?? '');
    $payment_method    = trim($_POST['payment_method']    ?? 'cod');

    // Validate cơ bản
    if (!$recipient_name || !$recipient_phone || !$recipient_address || !$recipient_email) {
        $error_msg = "Vui lòng nhập đầy đủ thông tin!";
    } else {
        try {
            $pdo->beginTransaction();

            // Build dynamic insert based on existing columns
            $insertCols   = [];
            $values       = [];

            // If orders table has user_id column, include it
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

            // total
            if (in_array('total', $orderCols, true)) {
                $insertCols[] = 'total';
                $values[]     = $total;
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

            // created_at if exists
            $cols_sql = implode(', ', $insertCols);
            $ph_parts = [];
            foreach ($insertCols as $c) {
                if ($c === 'created_at') {
                    $ph_parts[] = 'NOW()';
                } else {
                    $ph_parts[] = '?';
                }
            }
            // Nếu bảng orders có created_at mà bạn muốn dùng NOW():
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
            foreach ($cart_items as $pid => $it) {
                $qty   = (int)$it['quantity'];
                $price = isset($products[$pid]) ? (float)$products[$pid]['price'] : 0;
                $stmtDet->execute([$order_id, $pid, $qty, $price]);
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

            // Clear cart
            unset($_SESSION['cart']);

            // Redirect logic
            if ($payment_method === 'vnpay') {
                header("Location: vnpay_payment.php?order_id=$order_id");
                exit;
            } elseif ($payment_method === 'momo') {
                header("Location: momo_payment.php?order_id=$order_id");
                exit;
            } else {
                header("Location: order_success.php?order_id=$order_id");
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

?>
<style>
body { background: #fafafa; font-family: Arial, sans-serif; }
.checkout-container { display: flex; gap: 30px; justify-content: center; align-items: flex-start; padding: 40px; }
.checkout-left, .checkout-right { background: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); padding: 30px; }
.checkout-left { flex: 2; position: sticky; top: 30px; align-self: flex-start; height: fit-content; }
.checkout-right { flex: 1; top: 30px; align-self: flex-start; height: fit-content; }
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
@media (max-width: 991px) {
    .checkout-container { flex-direction: column; }
    .checkout-left, .checkout-right { position: static; width: 100%; }
}
</style>

<div class="checkout-container">

    <!-- LEFT -->
    <div class="checkout-left">
        <h2>Thông tin mua hàng</h2>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger"><?= $error_msg ?></div>
        <?php endif; ?>

        <form method="POST">
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
            <label><input type="radio" name="payment_method" value="cod" <?= (!isset($_POST['payment_method']) || $_POST['payment_method'] === 'cod') ? 'checked' : '' ?>> Thanh toán khi nhận hàng (COD)</label><br>
            <label><input type="radio" name="payment_method" value="momo" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'momo') ? 'checked' : '' ?>> Thanh toán qua MoMo</label><br>

            <button type="submit" class="btn-danger mt-4">ĐẶT HÀNG</button>
        </form>
    </div>

    <!-- RIGHT -->
    <div class="checkout-right">
        <h4>Đơn hàng (<?= count($products) ?> sản phẩm)</h4>
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $p):
                $qty      = $cart_items[$p['id']]['quantity'] ?? 0;
                $subtotal = $p['price'] * $qty;
            ?>
                <div class="summary-item">
                    <img src="../uploads/<?= htmlspecialchars($p['image']) ?>" alt="">
                    <div>
                        <div><?= htmlspecialchars($p['name']) ?></div>
                        <div class="summary-label">x<?= $qty ?></div>
                    </div>
                    <div class="ms-auto"><?= number_format($subtotal,0,'','.') ?>₫</div>
                </div>
            <?php endforeach; ?>
            <hr>
            <div class="summary-total">Tổng cộng: <?= number_format($total,0,'','.') ?>₫</div>
        <?php else: ?>
            <p class="text-center text-muted">Giỏ hàng trống hoặc sản phẩm không còn tồn tại.</p>
        <?php endif; ?>
        <a href="cart.php" class="d-block mt-3 text-danger">← Quay về giỏ hàng</a>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
