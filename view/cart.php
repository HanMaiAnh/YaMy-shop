<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

function is_ajax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

$MAX_QUANTITY = 5;

/**
 * XỬ LÝ ACTIONS: chỉ chạy khi:
 * - method POST AND (có field 'action' OR là AJAX)
 * - NOTE: nếu POST với 'apply_voucher' thì KHÔNG chạy block này (để phần voucher xử lý sau)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) || is_ajax())) {

    ob_start();

    try {
        $isAjax = is_ajax();
        $action = $_POST['action'] ?? '';
        $product_id = (int)($_POST['product_id'] ?? 0);
        $message = '';

        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];

        // basic validation: nếu action rỗng thì lỗi
        if ($action === '') {
            throw new Exception('Thiếu action.');
        }

        // product_id chỉ cần có với các action ngoại trừ clear/remove (remove có key)
        if ($product_id <= 0 && !in_array($action, ['clear', 'remove', 'update'])) {
            // note: update/remove dùng 'key' thay vì product_id
            if (!in_array($action, ['clear', 'remove'])) {
                // allow update/remove to proceed (they'll use 'key')
                // but if action is add and no product id => error
                if ($action === 'add') {
                    throw new Exception('Sản phẩm không hợp lệ');
                }
            }
        }

        switch ($action) {

            case 'add':
                $size = trim($_POST['selected_size'] ?? '');
                $color = trim($_POST['selected_color'] ?? '');
                $qty = (int)($_POST['quantity'] ?? 1);
                if ($qty < 1) $qty = 1;

                if ($size === '' || $color === '') {
                    throw new Exception('Vui lòng chọn size và màu!');
                }

                $stmt = $pdo->prepare("
                    SELECT id, name, image, stock,
                        COALESCE(discounted_price, price * (1 - COALESCE(discount_percent, 0)/100)) as final_price
                    FROM products WHERE id = ?
                ");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) throw new Exception('Sản phẩm không tồn tại!');
                if ((int)$product['stock'] <= 0) throw new Exception('Hết hàng!');

                $limit = min((int)$product['stock'], $MAX_QUANTITY);
                if ($qty > $limit) {
                    $qty = $limit;
                    $message = "Chỉ còn $limit sản phẩm!";
                }

                $key = "$product_id|$size|$color";

                if (isset($_SESSION['cart'][$key])) {
                    $_SESSION['cart'][$key]['quantity'] += $qty;
                    if ($_SESSION['cart'][$key]['quantity'] > $limit) {
                        $_SESSION['cart'][$key]['quantity'] = $limit;
                    }
                } else {
                    $_SESSION['cart'][$key] = [
                        'id' => (int)$product['id'],
                        'name' => $product['name'],
                        'image' => $product['image'],
                        'price' => (int)round((float)$product['final_price']),
                        'size' => $size,
                        'color' => $color,
                        'quantity' => $qty
                    ];
                }
                break;

            case 'update':
                $key = trim($_POST['key'] ?? '');
                $qty = (int)($_POST['quantity'] ?? 1);
                if ($qty < 1) $qty = 1;
                if ($key === '' || !isset($_SESSION['cart'][$key])) {
                    // nothing to update
                    break;
                }

                $pid = (int)explode('|', $key)[0];
                $stock = (int)$pdo->query("SELECT stock FROM products WHERE id = " . intval($pid))->fetchColumn();
                $limit = min($stock, $MAX_QUANTITY);

                if ($qty > $limit) {
                    $qty = $limit;
                    $message = "Chỉ còn $limit sản phẩm!";
                }

                $_SESSION['cart'][$key]['quantity'] = $qty;
                break;

            case 'remove':
                $key = trim($_POST['key'] ?? '');
                if ($key !== '' && isset($_SESSION['cart'][$key])) {
                    unset($_SESSION['cart'][$key]);
                    $message = "Đã xóa sản phẩm!";
                }
                break;

            case 'clear':
                $_SESSION['cart'] = [];
                unset($_SESSION['voucher']);
                $message = "Đã xóa toàn bộ giỏ hàng!";
                break;

            default:
                throw new Exception('Hành động không hợp lệ');
        }

        if (empty($_SESSION['cart'])) unset($_SESSION['cart']);

        // tính số lượng
        $count = 0;
        if (!empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $it) {
                $count += (int)$it['quantity'];
            }
        }

        // tính tổng - đảm bảo calculate_cart_total xử lý khi cart rỗng
        $total = calculate_cart_total($pdo, $_SESSION['cart'] ?? []);

        // trả JSON nếu AJAX
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'count' => $count,
                'total' => number_format($total, 0, '', '.') . '₫',
                'cart_empty' => empty($_SESSION['cart']),
                'message' => $message
            ]);
            ob_end_flush();
            exit;
        }

        // nếu form submit thông thường -> redirect về cart và lưu flash message
        if ($message !== '') $_SESSION['flash_message'] = $message;
        header('Location: cart.php');
        exit;

    } catch (Exception $e) {

        if (is_ajax()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            ob_end_flush();
            exit;
        }

        // non-AJAX: lưu lỗi vào flash và redirect
        $_SESSION['flash_error'] = $e->getMessage();
        header('Location: cart.php');
        exit;
    }
}

// ------------------- hiển thị trang giỏ hàng -------------------
require_once __DIR__ . '/../includes/header.php';

$cart_items = [];
$total_price = 0;

// build cart items từ session
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
        $parts = explode('|', $key);
        if (count($parts) !== 3) continue;

        [$pid, $size, $color] = $parts;
        $pid = (int)$pid;
        $qty = (int)$item['quantity'];
        if ($qty < 1) continue;

        $stmt = $pdo->prepare("
            SELECT id, name, image,
                   COALESCE(discounted_price, price * (1 - COALESCE(discount_percent, 0)/100)) as final_price
            FROM products WHERE id = ?
        ");
        $stmt->execute([$pid]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($p) {
            $price = (int)round((float)$p['final_price']);
            $subtotal = $price * $qty;
            $total_price += $subtotal;

            $cart_items[] = [
                'key' => $key,
                'id' => $pid,
                'name' => $p['name'],
                'image' => $p['image'],
                'price' => $price,
                'size' => $size,
                'color' => $color,
                'quantity' => $qty,
                'subtotal' => $subtotal
            ];
        }
    }
}

// === Áp dụng Voucher ===
$voucher_message = '';
$discount = 0;
$voucher_code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_voucher'])) {
    $voucher_code = trim($_POST['voucher_code']);
    if ($voucher_code) {
        $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE code = ? AND status = 'active'");
        $stmt->execute([$voucher_code]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($voucher) {
            $today = date('Y-m-d');
            if ($today >= $voucher['begin'] && $today <= $voucher['expired']) {
                if ($total_price >= $voucher['minimum_value']) {
                    if ($voucher['quantity'] > 0) {
                        $discount = $voucher['amount_reduced'] === 'percent'
                            ? $total_price * ($voucher['value'] / 100)
                            : $voucher['value'];
                        if ($discount > $total_price) $discount = $total_price;

                        $_SESSION['voucher'] = [
                            'code' => $voucher_code,
                            'discount' => $discount
                        ];
                        $voucher_message = "✅ Áp dụng mã '$voucher_code' thành công! Giảm " . number_format($discount, 0, ',', '.') . "₫";
                    } else {
                        $voucher_message = "❌ Mã '$voucher_code' đã hết lượt sử dụng!";
                    }
                } else {
                    $voucher_message = "❌ Đơn hàng chưa đạt giá trị tối thiểu " . number_format($voucher['minimum_value'], 0, ',', '.') . "₫.";
                }
            } else {
                $voucher_message = "❌ Mã '$voucher_code' đã hết hạn!";
            }
        } else {
            $voucher_message = "❌ Mã voucher không hợp lệ!";
        }
    }
}

if (isset($_SESSION['voucher'])) {
    $discount = $_SESSION['voucher']['discount'];
    $voucher_code = $_SESSION['voucher']['code'];
}

$grand_total = max(0, $total_price - $discount);
?>

<div class="container py-5">
    <?php if (!empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_message']); ?></div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Giỏ hàng của bạn</h2>
        <?php if (!empty($cart_items)): ?>
            <button type="button" class="btn btn-outline-danger btn-sm" id="clear-cart">Xóa tất cả</button>
        <?php endif; ?>
    </div>

    <?php if (empty($cart_items)): ?>
        <div class="text-center py-5 bg-light rounded">
            <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">Giỏ hàng trống</h4>
            <a href="<?= BASE_URL ?>" class="btn btn-danger mt-3 px-5">Mua sắm ngay</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-0" id="cart-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="p-4 border-bottom cart-item" data-key="<?= htmlspecialchars($item['key']) ?>">
                                <div class="row align-items-center">
                                    <div class="col-3 col-md-2">
                                        <img src="<?= upload($item['image']) ?>" class="img-fluid rounded" style="height: 90px; object-fit: cover;">
                                    </div>
                                    <div class="col-9 col-md-10">
                                        <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($item['size']) ?> | <?= htmlspecialchars($item['color']) ?></small>
                                        <div class="d-flex align-items-center gap-2 mt-2">
                                            <div class="input-group input-group-sm" style="width: 110px;">
                                                <button type="button" class="btn btn-outline-secondary qty-btn" data-dir="-1">-</button>
                                                <input type="number" class="form-control text-center qty-input" value="<?= (int)$item['quantity'] ?>" min="1">
                                                <button type="button" class="btn btn-outline-secondary qty-btn" data-dir="1">+</button>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-item">Xóa</button>
                                            <strong class="ms-auto text-danger"><?= number_format($item['subtotal']) ?>₫</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm sticky-top" style="top: 1rem;">
                    <div class="card-body">
                        <h5 class="mb-3">Tóm tắt</h5>

                        <form method="POST" class="mb-3">
                            <div class="input-group">
                                <input type="text" class="form-control" name="voucher_code" placeholder="Nhập mã giảm giá" value="<?= htmlspecialchars($voucher_code) ?>">
                                <button type="submit" name="apply_voucher" class="btn btn-success">Áp dụng</button>
                            </div>
                            <?php if ($voucher_message): ?>
                                <div class="alert alert-info mt-2 py-2 text-center"><?= $voucher_message ?></div>
                            <?php endif; ?>
                        </form>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Tạm tính (<?= count($cart_items) ?> sản phẩm)</span>
                            <strong><?= number_format($total_price) ?>₫</strong>
                        </div>

                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Giảm giá</span>
                            <strong>-<?= number_format($discount) ?>₫</strong>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between mb-3">
                            <strong>Tổng cộng</strong>
                            <strong class="text-success fs-5"><?= number_format($grand_total) ?>₫</strong>
                        </div>

                        <a href="checkout.php" class="btn btn-danger w-100 py-3">THANH TOÁN</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const send = (data, cb) => {
        const fd = new FormData();
        for (const k in data) fd.append(k, data[k]);
        fetch('cart.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => {
            if (!r.ok) throw new Error('Network error');
            return r.json();
        })
        .then(d => {
            if (d.success) {
                document.querySelectorAll('.badge-cart').forEach(b => b.textContent = d.count);
                if (d.message) showToast(d.message, 'success');
                if (cb) cb();
            } else {
                showToast(d.message || 'Lỗi!', 'danger');
            }
        })
        .catch(() => showToast('Lỗi kết nối!', 'danger'));
    };

    document.querySelectorAll('.qty-btn').forEach(b => {
        b.addEventListener('click', () => {
            const item = b.closest('.cart-item');
            const input = item.querySelector('.qty-input');
            let v = parseInt(input.value) || 1;
            v += parseInt(b.dataset.dir);
            if (v < 1) v = 1;
            input.value = v;
            send({ action: 'update', key: item.dataset.key, quantity: v });
        });
    });

    document.querySelectorAll('.remove-item').forEach(b => {
        b.addEventListener('click', () => {
            if (!confirm('Xóa sản phẩm này?')) return;
            const key = b.closest('.cart-item').dataset.key;
            send({ action: 'remove', key }, () => {
                b.closest('.cart-item').remove();
            });
        });
    });

    document.getElementById('clear-cart')?.addEventListener('click', () => {
        if (!confirm('Xóa toàn bộ?')) return;
        send({ action: 'clear' }, () => location.reload());
    });

    function showToast(msg, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `position-fixed top-0 start-50 translate-middle-x p-3`;
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show mb-0">
                ${msg}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
