<?php
// view/cart.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Link quay về trang chủ / trang sản phẩm
if (!defined('BASE_URL')) {
    define('BASE_URL', '/clothing_store/view/index.php');
}

/* =========================================================
   Helpers
========================================================= */

function is_ajax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Tính tổng tiền thô từ session cart (mọi item, không cần selected)
 */
function calculate_total_raw_from_session() {
    $total = 0;
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $it) {
            $price = isset($it['price']) ? (int)$it['price'] : 0;
            $qty   = isset($it['quantity']) ? (int)$it['quantity'] : 0;
            $total += $price * $qty;
        }
    }
    return (int)$total;
}

/**
 * Lấy thông tin biến thể (dùng khi add/update) - giữ nguyên logic nếu cần
 */
function fetch_variant_info(PDO $pdo, int $variant_id = 0, int $product_id = 0, string $size = '', string $color = '') {
    $baseSelect = "
        SELECT 
            v.id   AS variant_id,
            v.product_id,
            p.name AS product_name,
            v.price AS base_price,
            v.price_reduced,
            COALESCE(p.discount_percent,0) AS discount_percent,
            v.quantity AS stock,
            s.name AS size_name,
            c.name AS color_name,
            (
                SELECT image_url 
                FROM product_images 
                WHERE product_id = v.product_id 
                ORDER BY id ASC 
                LIMIT 1
            ) AS image
        FROM product_variants v
        JOIN products p ON p.id = v.product_id
        LEFT JOIN sizes  s ON s.id = v.size_id
        LEFT JOIN colors c ON c.id = v.color_id
    ";

    // 1. Theo variant_id
    if ($variant_id > 0) {
        $sql  = $baseSelect . " WHERE v.id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$variant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // 2. Theo product + size + color
    if ($product_id > 0 && $size !== '' && $color !== '') {
        $sql = $baseSelect . "
            WHERE v.product_id = ?
              AND s.name = ?
              AND c.name = ?
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$product_id, $size, $color]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // 3. Không có màu (hiếm khi dùng)
    if ($product_id > 0 && $size !== '') {
        $sql = $baseSelect . "
            WHERE v.product_id = ?
              AND s.name = ?
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$product_id, $size]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    return null;
}

/**
 * Tính giá cuối:
 */
function calc_final_price(array $v): array {
    $base  = (float)($v['base_price'] ?? 0);
    $discV = (float)($v['price_reduced'] ?? 0);
    $discP = (float)($v['discount_percent'] ?? 0);

    if ($discV > 0 && $discV < $base) {
        $final   = $discV;
        $discPct = round(100 - $final / max($base, 1) * 100);
    } elseif ($discP > 0 && $base > 0) {
        $final   = round($base * (1 - $discP / 100));
        $discPct = $discP;
    } else {
        $final   = $base;
        $discPct = 0;
    }

    return [
        'base_price'       => (int)$base,
        'final_price'      => (int)$final,
        'discount_percent' => (int)$discPct,
    ];
}

/* =========================================================
   AJAX API (add / update / remove / clear / apply_voucher)
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json; charset=utf-8');

    $res = [
        'success'    => false,
        'message'    => 'Unknown error',
        'count'      => 0,
        'total_raw'  => 0,
        'total'      => '0₫',
        'cart_empty' => true,
    ];

    try {
        $action = $_POST['action'] ?? '';
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        switch ($action) {
            /* ---------- ADD ---------- */
            case 'add':
                $product_id = (int)($_POST['product_id'] ?? 0);
                $variant_id = (int)($_POST['variant_id'] ?? 0);
                $qty        = max(1, (int)($_POST['quantity'] ?? 1));
                $size       = trim($_POST['selected_size'] ?? '');
                $color      = trim($_POST['selected_color'] ?? '');

                if ($product_id <= 0 && $variant_id <= 0) {
                    $res['message'] = 'Sản phẩm không hợp lệ';
                    echo json_encode($res);
                    exit;
                }

                $variant = fetch_variant_info($pdo, $variant_id, $product_id, $size, $color);
                if (!$variant) {
                    $res['message'] = 'Không tìm thấy biến thể sản phẩm';
                    echo json_encode($res);
                    exit;
                }

                $priceInfo  = calc_final_price($variant);
                $finalPrice = $priceInfo['final_price'];
                $stock      = (int)($variant['stock'] ?? 0);

                if ($stock > 0 && $qty > $stock) {
                    $qty = $stock;
                }

                $key = 'v_' . (int)$variant['variant_id'];

                if (!isset($_SESSION['cart'][$key])) {
                    $_SESSION['cart'][$key] = [
                        'product_id'       => (int)$variant['product_id'],
                        'variant_id'       => (int)$variant['variant_id'],
                        'name'             => $variant['product_name'],
                        'price'            => $finalPrice,
                        'base_price'       => $priceInfo['base_price'],
                        'discount_percent' => $priceInfo['discount_percent'],
                        'image'            => $variant['image'],
                        'quantity'         => $qty,
                        'stock'            => $stock,               // lưu tồn kho để JS dùng
                        'size'             => $variant['size_name'],
                        'color'            => $variant['color_name'],
                        'added_at'         => time(),
                    ];
                    $res['message'] = 'Đã thêm vào giỏ hàng';
                } else {
                    $currentQty = (int)$_SESSION['cart'][$key]['quantity'];
                    $newQty     = $currentQty + $qty;

                    if ($stock > 0 && $newQty > $stock) {
                        $newQty = $stock;
                    }

                    $_SESSION['cart'][$key]['quantity'] = $newQty;
                    $_SESSION['cart'][$key]['stock']    = $stock;

                    if ($stock > 0 && $newQty >= $stock) {
                        $res['message'] = "Bạn đã chọn tối đa {$stock} sản phẩm cho biến thể này";
                    } else {
                        $res['message'] = 'Đã cập nhật số lượng trong giỏ hàng';
                    }
                }
                break;

            /* ---------- UPDATE ---------- */
            case 'update':
                $key = $_POST['key'] ?? '';
                $qty = max(1, (int)($_POST['quantity'] ?? 1));

                if ($key && isset($_SESSION['cart'][$key])) {
                    $stock = isset($_SESSION['cart'][$key]['stock'])
                           ? (int)$_SESSION['cart'][$key]['stock']
                           : 0;

                    if ($stock <= 0) {
                        $variant_id = 0;
                        if (strpos($key, 'v_') === 0) {
                            $variant_id = (int)substr($key, 2);
                        }
                        if ($variant_id > 0) {
                            $st = $pdo->prepare("SELECT quantity FROM product_variants WHERE id = ?");
                            $st->execute([$variant_id]);
                            $stock = (int)$st->fetchColumn();
                            $_SESSION['cart'][$key]['stock'] = $stock;
                        }
                    }

                    if ($stock > 0 && $qty > $stock) {
                        $qty = $stock;
                    }

                    $_SESSION['cart'][$key]['quantity'] = $qty;
                    $res['message'] = 'Cập nhật thành công';
                } else {
                    $res['message'] = 'Sản phẩm không tồn tại trong giỏ';
                }
                break;
/* ---------- REMOVE VOUCHER ---------- */
case 'remove_voucher':
    unset($_SESSION['applied_voucher']);
    $cartTotal = 0;
    if (!empty($_POST['selected_keys']) && is_array($_POST['selected_keys'])) {
        foreach ($_POST['selected_keys'] as $k) {
            if (!isset($_SESSION['cart'][$k])) continue;
            $cartTotal += (int)$_SESSION['cart'][$k]['price']
                        * (int)$_SESSION['cart'][$k]['quantity'];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Đã hủy mã giảm giá',
        'total_raw' => $cartTotal,
        'total_formatted' => number_format($cartTotal,0,'','.').'₫'
    ]);
    exit;
    
            /* ---------- REMOVE ---------- */
            case 'remove':
                $key = $_POST['key'] ?? '';
                if ($key && isset($_SESSION['cart'][$key])) {
                    unset($_SESSION['cart'][$key]);
                    $res['message'] = 'Đã xóa sản phẩm';
                } else {
                    $res['message'] = 'Sản phẩm không có trong giỏ';
                }
                break;

            /* ---------- CLEAR ---------- */
            case 'clear':
                $_SESSION['cart'] = [];
                $res['message']   = 'Đã xóa toàn bộ giỏ hàng';
                break;

            /* ---------- APPLY VOUCHER (safe) ---------- */
case 'apply_voucher':

    $code = trim($_POST['code'] ?? '');
    if ($code === '') {
        echo json_encode(['success'=>false,'message'=>'Vui lòng chọn mã giảm giá']);
        exit;
    }

    // ✅ tính tổng theo sản phẩm được tick
    $cartTotal = 0;
    $selected_keys = [];

    if (!empty($_POST['selected_keys']) && is_array($_POST['selected_keys'])) {
        $selected_keys = array_map('trim', $_POST['selected_keys']);
        foreach ($selected_keys as $k) {
            if (!isset($_SESSION['cart'][$k])) continue;
            $cartTotal += (int)$_SESSION['cart'][$k]['price']
                        * (int)$_SESSION['cart'][$k]['quantity'];
        }
    }

    if ($cartTotal <= 0) {
        echo json_encode(['success'=>false,'message'=>'Giỏ hàng không hợp lệ']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM vouchers
        WHERE code = ?
          AND begin <= CURDATE()
          AND expired >= CURDATE()
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        echo json_encode(['success'=>false,'message'=>'Mã không tồn tại']);
        exit;
    }

    if ((int)$voucher['quantity'] <= 0) {
        echo json_encode(['success'=>false,'message'=>'Mã đã hết lượt sử dụng']);
        exit;
    }

    if ($cartTotal < (float)$voucher['minimum_value']) {
        echo json_encode([
            'success'=>false,
            'message'=>'Đơn tối thiểu '
                . number_format($voucher['minimum_value'],0,'','.') . '₫'
        ]);
        exit;
    }

    $value = (float)$voucher['value'];
    $max   = (float)$voucher['amount_reduced'];

    $discount = ($value <= 100)
        ? round($cartTotal * $value / 100)
        : $value;

    if ($max > 0 && $discount > $max) {
        $discount = $max;
    }

    $_SESSION['applied_voucher'] = [
        'id' => (int)$voucher['id'],
        'code' => $voucher['code'],
        'discount_amount' => $discount,
        'applied_on_keys' => $selected_keys
    ];

    $newTotal = max(0, $cartTotal - $discount);

    echo json_encode([
        'success' => true,
        'message' => 'Áp dụng mã '.$voucher['code'].' thành công',
        'total_raw' => $newTotal,
        'total_formatted' => number_format($newTotal,0,'','.').'₫'
    ]);
    exit;





                // --- START: CHỈ TÍNH TỔNG CHO CÁC selected_keys[] NẾU CLIENT GỬI ---
                $cartTotal = 0;
                $selected_keys = [];

                if (!empty($_POST['selected_keys']) && is_array($_POST['selected_keys'])) {
                    $selected_keys = array_map('trim', $_POST['selected_keys']);
                    foreach ($selected_keys as $k) {
        if (isset($_SESSION['cart'][$k])) {
            $price = (int)$_SESSION['cart'][$k]['price']; // ✅ GIÁ HIỂN THỊ
            $qty   = (int)$_SESSION['cart'][$k]['quantity'];
            $cartTotal += $price * $qty;
        }
    }
} else {
    foreach ($_SESSION['cart'] as $it) {
        $cartTotal += (int)$it['price'] * (int)$it['quantity'];
    }
}

                // --- END ---

                if ($cartTotal <= 0) {
                    $res['message'] = 'Giỏ hàng trống, không thể áp dụng mã';
                    echo json_encode($res);
                    exit;
                }

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("
                        SELECT *
                        FROM vouchers
                        WHERE code = ?
                          AND begin <= CURDATE()
                          AND expired >= CURDATE()
                        LIMIT 1
                        FOR UPDATE
                    ");
                    $stmt->execute([$code]);
                    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$voucher) {
                        $pdo->rollBack();
                        $res['message'] = 'Mã giảm giá không tồn tại hoặc không hợp lệ';
                        echo json_encode($res);
                        exit;
                    }

                    $quantityLeft = (int)($voucher['quantity'] ?? 0);
                    if ($quantityLeft <= 0) {
                        $pdo->rollBack();
                        $res['message'] = 'Mã giảm giá đã hết lượt sử dụng';
                        echo json_encode($res);
                        exit;
                    }

                    $minOrder      = (float)($voucher['minimum_value'] ?? 0);
                    $discountValue = (float)($voucher['value']);                // % hoặc VNĐ
                    $maxReduced    = (float)($voucher['amount_reduced'] ?? 0); // giới hạn giảm tối đa (nếu có)

                    if ($cartTotal < $minOrder) {
                        $pdo->rollBack();
                        $res['message'] = 'Giá trị đơn hàng tối thiểu để dùng mã này là ' . number_format($minOrder, 0, '', '.') . '₫';
                        echo json_encode($res);
                        exit;
                    }

                    if ($discountValue <= 0) {
                        $pdo->rollBack();
                        $res['message'] = 'Mã giảm giá không hợp lệ (giá trị = 0)';
                        echo json_encode($res);
                        exit;
                    }

                    // Tính số tiền được giảm:
                    if ($discountValue <= 100) {
                        $discountAmount = round($cartTotal * $discountValue / 100);
                    } else {
                        $discountAmount = $discountValue;
                    }

                    // Trần giảm tối đa
                    if ($maxReduced > 0 && $discountAmount > $maxReduced) {
                        $discountAmount = $maxReduced;
                    }

                    if ($discountAmount <= 0) {
                        $pdo->rollBack();
                        $res['message'] = 'Mã giảm giá không mang lại giảm giá nào';
                        echo json_encode($res);
                        exit;
                    }

                    // Giảm 1 lượt Quantity và commit
                    $newQty = max(0, $quantityLeft - 1);
                    $upd = $pdo->prepare("UPDATE vouchers SET quantity = ? WHERE id = ?");
                    $upd->execute([$newQty, $voucher['id']]);

                    $pdo->commit();

                    $newTotal = max(0, $cartTotal - $discountAmount);

                    // Lưu voucher vào session (tuỳ chọn)
                    $_SESSION['applied_voucher'] = [
                        'id'              => (int)$voucher['id'],
                        'code'            => $voucher['code'],
                        'discount_value'  => $discountValue,
                        'discount_amount' => $discountAmount,
                        'new_total'       => $newTotal,
                        'remaining_quantity' => $newQty,
                        'applied_on_keys' => $selected_keys, // lưu để tham khảo (tùy dùng)
                    ];

                    $res['success']         = true;
                    $res['message']         = 'Đã áp dụng mã "' . $voucher['code'] . '" - giảm ' . number_format($discountAmount, 0, '', '.') . '₫';
                    $res['total_raw']       = $newTotal;
                    $res['total']           = number_format($newTotal, 0, '', '.') . '₫';
                    $res['total_formatted'] = number_format($newTotal, 0, '', '.') . '₫';
                    $res['cart_empty']      = false;
                    $res['remaining_quantity'] = $newQty;

                    echo json_encode($res);
                    exit;

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
                    exit;
                }
                break;

            default:
                $res['message'] = 'Hành động không hợp lệ';
                echo json_encode($res);
                exit;
        }

        // Các action add/update/remove/clear sẽ chạy xuống đây
        $count     = !empty($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
        $total_raw = calculate_total_raw_from_session();

        $res['success']    = true;
        $res['count']      = $count;
        $res['total_raw']  = $total_raw;
        $res['total']      = number_format($total_raw, 0, '', '.') . '₫';
        $res['cart_empty'] = empty($_SESSION['cart']);

        echo json_encode($res);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
/* =========================================================
   Render trang giỏ hàng (HTML)
========================================================= */

$cart_items  = [];
$total_price = 0;

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
        if (!is_array($item)) continue;
        $qty      = (int)($item['quantity'] ?? 0);
        $price    = (int)($item['price'] ?? 0);
        $subtotal = $price * $qty;
        $total_price += $subtotal;

        $ci             = $item;
        $ci['subtotal'] = $subtotal;
        $cart_items[$key] = $ci;
    }
}

// === LẤY CÁC VOUCHER HIỆN CÒN HỢP LỆ ĐỂ HIỂN THỊ CHO NGƯỜI DÙNG ===
try {
    $stmt = $pdo->prepare("
        SELECT id, code, value, amount_reduced, minimum_value, begin, expired, quantity
        FROM vouchers
        WHERE begin <= CURDATE() AND expired >= CURDATE()
        ORDER BY id DESC
        LIMIT 50
    ");
    $stmt->execute();
    $available_vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $available_vouchers = [];
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="container py-5">

    <!-- Tiêu đề + chọn tất cả / xóa tất cả -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <h2 class="mb-0">Giỏ hàng của bạn</h2>
            <?php if (!empty($cart_items)): ?>
                <div class="d-flex align-items-center gap-3">
                    <label class="mb-0 d-flex align-items-center gap-1">
                        <input id="select-all" type="checkbox" checked>
                        <span>Chọn tất cả</span>
                    </label>
                    <button id="clear-cart" class="btn btn-outline-danger btn-sm">Xóa tất cả</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($cart_items)): ?>
        <div class="text-center py-5 bg-light rounded">
            <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">Giỏ hàng trống</h4>
            <a href="/clothing_store/view/products.php" class="btn btn-danger mt-3 px-5">Mua sắm ngay</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Danh sách item -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-0" id="cart-items">
                        <?php foreach ($cart_items as $key => $item): ?>
                            <?php
                                $imgFile   = $item['image'] ?? '';
                                $imgSrc    = $imgFile ? upload($imgFile) : upload('no-image.png');
                                $stock     = isset($item['stock']) ? (int)$item['stock'] : 0;
                                $unitPrice = isset($item['price']) ? (int)$item['price'] : 0;
                            ?>
                            <div class="cart-item border-bottom p-4 d-flex align-items-start"
                                 data-key="<?= htmlspecialchars($key) ?>"
                                 data-price="<?= (int)$item['price'] ?>"
                                 data-stock="<?= $stock ?>">

                                <!-- checkbox -->
                                <div class="me-3 pt-1">
                                    <input type="checkbox"
                                           class="select-item"
                                           data-key="<?= htmlspecialchars($key) ?>"
                                           checked
                                           aria-label="Chọn sản phẩm">
                                </div>

                                <!-- ảnh -->
                                <div class="flex-shrink-0" style="width:80px;">
                                    <img src="<?= htmlspecialchars($imgSrc) ?>"
                                         class="img-fluid rounded"
                                         style="height:90px;object-fit:cover;">
                                </div>

                                <!-- Nội dung -->
                                <div class="flex-grow-1 ms-3">
                                    <div class="d-flex justify-content-between align-items-center gap-3">
                                        <!-- Tên + size + màu (bên trái) -->
                                        <div class="cart-info">
                                            <h6 class="mb-1 product-name">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php if (!empty($item['size'])): ?>
                                                    Size: <?= htmlspecialchars($item['size']) ?>
                                                <?php endif; ?>
                                                <?php if (!empty($item['color'])): ?>
                                                    <?= !empty($item['size']) ? ' | ' : '' ?>
                                                    Màu: <?= htmlspecialchars($item['color']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>

                                        <!-- ĐƠN GIÁ (ở giữa) -->
                                        <div class="cart-price text-center">
                                            <div class="unit-value">
                                                <?= number_format($unitPrice) ?>₫
                                            </div>
                                        </div>

                                        <!-- Số lượng + xóa + tổng tiền (bên phải) -->
                                        <div class="cart-actions d-flex align-items-center gap-3">
                                            <div class="input-group input-group-sm quantity-group">
                                                <button type="button"
                                                        class="btn btn-outline-secondary qty-btn"
                                                        data-dir="-1"
                                                        aria-label="Giảm">-</button>
                                                <input type="number"
                                                       class="form-control text-center qty-input"
                                                       value="<?= (int)$item['quantity'] ?>"
                                                       min="1"
                                                       aria-label="Số lượng">
                                                <button type="button"
                                                        class="btn btn-outline-secondary qty-btn"
                                                        data-dir="1"
                                                        aria-label="Tăng">+</button>
                                            </div>

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger remove-item">
                                                Xóa
                                            </button>

                                            <strong class="subtotal text-danger">
                                                <?= number_format($item['subtotal']) ?>₫
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Tóm tắt -->
            <div class="col-lg-4">
                <div class="card shadow-sm sticky-top summary-sticky">
                    <div class="card-body">
                    <div class="mb-3"> 
                        <label for="voucher_select" class="form-label">Chọn mã giảm giá</label>

                            <div class="input-group">
                                <select id="voucher_select" class="form-select" aria-label="Chọn mã giảm giá">
                                    <option value="">Không dùng mã</option>
                                    <?php if (!empty($available_vouchers)): ?>
                                        <?php foreach ($available_vouchers as $v):
                                            $code = htmlspecialchars($v['code']);
                                            $val  = (float)$v['value'];
                                            $label = $val <= 100 ? ((int)$val . '%') : (number_format($val,0,'','.') . '₫');
                                            $min = (float)$v['minimum_value'];
                                            $minAttr = (int)$min;
                                            $remaining = isset($v['quantity']) ? (int)$v['quantity'] : 0;
                                            // disabled if min > current total OR remaining <= 0
                                            $disabledAttr = ($remaining <= 0) ? 'disabled data-disabled="1"' : '';
                                        ?>
                                        <option value="<?= $code ?>"
                                                data-label="<?= $label ?>"
                                                data-min="<?= $minAttr ?>"
                                                data-remaining="<?= $remaining ?>"
                                                <?= $disabledAttr ? 'disabled data-disabled="1" aria-disabled="true"' : '' ?>>
                                            <?= $code ?> — <?= $label ?> <?= $min > 0 ? ' (Đơn từ ' . number_format($min,0,'','.') . '₫)' : '' ?>
                                        </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?> 
                                </select>

                                <button type="button" id="applyVoucher" class="btn btn-outline-primary">Áp dụng</button>
                            </div>
                    </div>
                        <h5 class="mb-3">Tóm tắt</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>
                                Tạm tính (<span id="selected-count"><?= count($cart_items) ?></span> sản phẩm)
                            </span>
                            <strong id="selected-total" data-total><?= number_format($total_price) ?>₫</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-success" id="voucher-row" style="display:none">
                            <span>Giảm giá</span>
                            <strong id="voucher-discount">-0₫</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Phí vận chuyển</span>
                            <!-- shipping fee shown here; default 0₫ -->
                            <strong id="shipping-fee" data-shipping>0₫</strong>
                        </div>
                        <!-- CHỈ HIỂN THỊ COMBOBOX MÃ GIẢM GIÁ (KHÔNG CÓ Ô NHẬP) -->
                        <div class="mb-3">
                            

                            <small id="voucher-msg" class="text-success d-block mt-1"></small>
                                        <hr>
                        </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                            <strong class="me-2">Tổng cộng</strong>
                            <strong class="text-danger fs-5 ms-auto" id="selected-total-2" data-total>
                                <?= number_format($total_price) ?>₫
                            </strong>
                        </div>
                        <form id="checkoutForm" action="./checkout.php" method="POST">
                            <input type="hidden" name="from_cart" value="1">
                            <button id="checkoutBtn"
                                    type="submit"
                                    class="btn btn-danger w-100 py-3">
                                THANH TOÁN
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Căn giữa theo chiều dọc cho từng item */
.cart-item {
    display: flex;
    align-items: center !important;
}

/* Cột nội dung */
.cart-item .flex-grow-1 {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* Tên sản phẩm: cho phép xuống 2 dòng, dư thì "..." */
.product-name {
    max-width: 260px;
    overflow: hidden;
    white-space: normal;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

/* Khối chứa thông tin bên trái */
.cart-info {
    flex: 1 1 auto;
    min-width: 0;
}

/* Ô đơn giá ở giữa: cố định chiều rộng, không co lại */
.cart-price {
    flex: 0 0 130px;
    text-align: center;
}
.cart-price .unit-value {
    font-weight: 600;
    white-space: nowrap;
}

/* Nhóm bên phải: số lượng – xóa – tổng tiền */
.cart-actions {
    display: flex;
    align-items: center !important;
    flex: 0 0 260px;
    justify-content: flex-end;
}

/* Nút +/- và input số lượng */
.quantity-group {
    display: flex;
    align-items: center;
}
.cart-actions .quantity-group {
    width: 120px;
}

/* Tổng tiền */
.cart-actions .subtotal {
    white-space: nowrap;
    min-width: 110px;
    text-align: right;
}

.qty-btn {
    width: 36px;
    padding: .25rem .45rem;
}

/* Ảnh sản phẩm cũng căn giữa */
.cart-item .flex-shrink-0 {
    display: flex;
    align-items: center;
}

/* Ẩn spinner của input number */
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type=number] { -moz-appearance: textfield; }

/* checkbox nhỏ */
.cart-item .select-item {
    width: 18px;
    height: 18px;
}

.summary-sticky .card-body {
    padding: 1.25rem;
}

.summary-sticky h5 {
    margin-bottom: .75rem;
    font-weight: 700;
}

/* Make total more prominent */
.summary-sticky .text-danger.fs-5 {
    font-size: 1.35rem;
    font-weight: 700;
}

/* toast */
.toast-temp,
.toast-confirm {
    max-width: 360px;
}

.summary-sticky {
    position: sticky;
    top: 90px;
    z-index: 2;
}

/* Responsive adjustments */
@media (max-width: 576px) {
    .summary-sticky .input-group { flex-direction: column; gap: .5rem; }
}

/* -------- Voucher disabled styling -------- */
/* Make disabled voucher options appear faded/greyed */
#voucher_select option[disabled] {
    color: #6c757d;      /* gray text */
    opacity: 0.6;
}

/* Extra class for custom styling if needed */
.voucher-option-disabled {
    opacity: 0.6;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // ===============================
// GIỮ TỔNG TIỀN SẢN PHẨM GỐC (KHÔNG BAO GIỜ BỊ VOUCHER ẢNH HƯỞNG)
// ===============================
let BASE_SELECTED_TOTAL = 0;

    // ---------- AJAX helper ----------
    async function postAjax(data) {
        const fd = new FormData();
        for (const k in data) fd.append(k, data[k]);

        const resp = await fetch('cart.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const ct = resp.headers.get('content-type') || '';
        if (!resp.ok) {
            if (ct.includes('application/json')) {
                const j = await resp.json().catch(() => null);
                throw new Error(j && j.message ? j.message : 'Server error');
            }
            const t = await resp.text().catch(() => null);
            throw new Error(t || 'Network error');
        }
        if (!ct.includes('application/json')) {
            const t = await resp.text();
            throw new Error('Phản hồi không phải JSON: ' + t);
        }
        return resp.json();
    }

    // ---------- Toast ----------
    function showToast(msg, type = 'success', timeout = 2200) {
        document.querySelectorAll('.toast-temp').forEach(t => t.remove());
        const wrap = document.createElement('div');
        wrap.className = 'toast-temp position-fixed top-0 start-50 translate-middle-x p-3';
        wrap.style.zIndex = 9999;
        const kind = type === 'warning' ? 'warning'
                    : type === 'danger' ? 'danger'
                    : 'success';
        wrap.innerHTML = `<div class="alert alert-${kind} mb-0">${msg}</div>`;
        document.body.appendChild(wrap);
        setTimeout(() => wrap.remove(), timeout);
    }

    function showConfirmToast(message, onConfirm) {
        document.querySelectorAll('.toast-confirm').forEach(t => t.remove());
        const div = document.createElement('div');
        div.className = 'toast-confirm position-fixed top-0 start-50 translate-middle-x p-3';
        div.style.zIndex = 10001;
        div.innerHTML = `
            <div class="alert alert-warning d-flex flex-column gap-2 mb-0">
                <div>${message}</div>
                <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-sm btn-secondary btn-cancel">Hủy</button>
                    <button class="btn btn-sm btn-danger btn-ok">Đồng ý</button>
                </div>
            </div>`;
        document.body.appendChild(div);
        div.querySelector('.btn-cancel').onclick = () => div.remove();
        div.querySelector('.btn-ok').onclick = () => {
            div.remove();
            if (typeof onConfirm === 'function') onConfirm();
        };
    }

    // ---------- Lấy tồn kho 1 item ----------
    function getItemStockLimit(item) {
        const stock = parseInt(item.dataset.stock || '0', 10);
        return stock > 0 ? stock : 999999;
    }

    // ---------- Utility: parse currency string to number ----------
    function parseNumberFromCurrency(str) {
        if (!str) return 0;
        const n = String(str).replace(/[^\d]/g, '');
        return n ? parseInt(n, 10) : 0;
    }

    // ---------- Voucher options refresh ----------
    function refreshVoucherOptions() {
        const sel = document.getElementById('voucher_select');
        if (!sel) return;

        // Lấy tổng hiện tại từ 1 trong các element data-total (ưu tiên selected-total)
        const totalEl = document.getElementById('selected-total');
        let currentTotal = 0;
        if (totalEl) {
            currentTotal = parseNumberFromCurrency(totalEl.textContent);
        } else {
            const any = document.querySelector('[data-total]');
            currentTotal = any ? parseNumberFromCurrency(any.textContent) : 0;
        }

        Array.from(sel.options).forEach(opt => {
            // option "Không dùng mã" không có data-min
            const min = parseInt(opt.dataset.min || '0', 10) || 0;
            const remaining = parseInt(opt.dataset.remaining || '0', 10);
// ✅ LUÔN CHO PHÉP "KHÔNG DÙNG MÃ"
if (opt.value === '') {
    opt.disabled = false;
    opt.dataset.disabled = '0';
    opt.classList.remove('voucher-option-disabled');
    return;
}

            // nếu min > currentTotal => disable
            if (min > 0 && currentTotal < min) {
                if (!opt.disabled) opt.disabled = true;
                opt.dataset.disabled = '1';
                opt.classList.add('voucher-option-disabled');
            } else {
                // nếu option bị vô hiệu do hết lượt (remaining <= 0), giữ disabled
                if (remaining <= 0) {
                    opt.disabled = true;
                    opt.dataset.disabled = '1';
                    opt.classList.add('voucher-option-disabled');
                } else {
                    // enable nếu trước đó bị disable vì min chưa đạt
                    if (opt.disabled && opt.dataset && opt.dataset.disabled === '1' && min > 0) {
                        // trường hợp dataset.disabled=1 vì min chưa đạt
                        // enable lại
                        opt.disabled = false;
                        opt.dataset.disabled = '0';
                        opt.classList.remove('voucher-option-disabled');
                    } else {
                        // nếu không có cờ disabled thì đảm bảo class cleared
                        if (!opt.disabled) {
                            opt.classList.remove('voucher-option-disabled');
                            opt.dataset.disabled = '0';
                        }
                    }
                }
            }
        });

        const anyDisabled = Array.from(sel.options).some(o => o.disabled);
        if (anyDisabled) {
            sel.classList.add('voucher-has-disabled');
        } else {
            sel.classList.remove('voucher-has-disabled');
        }
    }
// ---------- SHIPPING FEE ----------
function calculateShippingFee(total) {
    if (total >= 500000) return 0;
    return total > 0 ? 30000 : 0;
}
function updateSelectedSummary() {
    let totalRaw   = 0;
    let countLines = 0;

    document.querySelectorAll('.cart-item').forEach(item => {
        const cb = item.querySelector('.select-item');
        if (!cb || !cb.checked) return;

        const qty   = parseInt(item.querySelector('.qty-input').value) || 0;
        const price = parseInt(item.dataset.price) || 0;

        totalRaw   += qty * price;
        countLines += 1;
    });

    // ---------- SHIPPING ----------
    const shippingFee = calculateShippingFee(totalRaw);
    const finalTotal  = totalRaw + shippingFee;

    // số lượng sản phẩm
    const countSpan = document.getElementById('selected-count');
    if (countSpan) countSpan.textContent = countLines;

    BASE_SELECTED_TOTAL = totalRaw; // ✅ lưu tổng gốc
    // ✅ cập nhật TẠM TÍNH (theo checkbox)
const selectedTotalEl = document.getElementById('selected-total');
if (selectedTotalEl) {
    selectedTotalEl.textContent =
        new Intl.NumberFormat('vi-VN').format(totalRaw) + '₫';
}

    // phí ship
    const shipEl = document.getElementById('shipping-fee');
    if (shipEl) {
        shipEl.textContent =
            shippingFee === 0 ? 'Miễn phí' :
            new Intl.NumberFormat('vi-VN').format(shippingFee) + '₫';
    }

    // tổng cộng
    document.getElementById('selected-total-2').textContent =
        new Intl.NumberFormat('vi-VN').format(finalTotal) + '₫';

    // refresh voucher theo tổng sản phẩm (không tính ship)
    refreshVoucherOptions();
}

    function syncSelectAll() {
        const selAll = document.getElementById('select-all');
        if (!selAll) return;
        const items = Array.from(document.querySelectorAll('.select-item'));
        if (!items.length) {
            selAll.checked = false;
            return;
        }
        selAll.checked = items.every(cb => cb.checked);
    }

    // ---------- Khởi tạo item ----------
    document.querySelectorAll('.cart-item').forEach(item => {
        const cb  = item.querySelector('.select-item');
        const qty = item.querySelector('.qty-input');

        cb && cb.addEventListener('change', () => {
            updateSelectedSummary();
            syncSelectAll();
        });

        qty && qty.addEventListener('change', () => {
            let v = parseInt(qty.value) || 1;
            if (v < 1) v = 1;

            const stockLimit = getItemStockLimit(item);
            if (v > stockLimit) {
                v = stockLimit;
                showToast('Chỉ còn ' + stockLimit + ' sản phẩm cho mặt hàng này', 'warning');
            }

            qty.value = v;

            postAjax({ action: 'update', key: item.dataset.key, quantity: v })
                .then(res => {
                    if (!res.success) {
                        showToast(res.message || 'Lỗi', 'danger');
                        return;
                    }
                    const unit  = parseInt(item.dataset.price) || 0;
                    const subEl = item.querySelector('.subtotal');
                    if (subEl) {
                        subEl.textContent =
                            new Intl.NumberFormat('vi-VN').format(unit * v) + '₫';
                    }
                    updateSelectedSummary();
                })
                .catch(err => {
                    console.error(err);
                    showToast(err.message || 'Lỗi kết nối', 'danger');
                });
        });
    });

    // ---------- nút + / - , remove, clear ----------
    document.addEventListener('click', e => {
        const btnQty = e.target.closest('.qty-btn');
        if (btnQty) {
            const item  = btnQty.closest('.cart-item');
            const input = item.querySelector('.qty-input');
            let v = parseInt(input.value) || 1;
            v += parseInt(btnQty.dataset.dir);

            if (v < 1) v = 1;

            const stockLimit = getItemStockLimit(item);
            if (v > stockLimit) {
                v = stockLimit;
                showToast('Chỉ còn ' + stockLimit + ' sản phẩm cho mặt hàng này', 'warning');
            }

            input.value = v;

            postAjax({ action: 'update', key: item.dataset.key, quantity: v })
                .then(res => {
                    if (!res.success) {
                        showToast(res.message || 'Lỗi', 'danger');
                        return;
                    }
                    const unit  = parseInt(item.dataset.price) || 0;
                    const subEl = item.querySelector('.subtotal');
                    if (subEl) {
                        subEl.textContent =
                            new Intl.NumberFormat('vi-VN').format(unit * v) + '₫';
                    }
                    updateSelectedSummary();
                })
                .catch(err => {
                    console.error(err);
                    showToast(err.message || 'Lỗi kết nối', 'danger');
                });

            return;
        }

        const btnRemove = e.target.closest('.remove-item');
        if (btnRemove) {
            const item = btnRemove.closest('.cart-item');
            const key  = item.dataset.key;

            showConfirmToast('Bạn có chắc muốn xóa sản phẩm này?', () => {
                postAjax({ action: 'remove', key })
                    .then(res => {
                        if (!res.success) {
                            showToast(res.message || 'Lỗi', 'danger');
                            return;
                        }
                        item.remove();
                        updateSelectedSummary();
                        syncSelectAll();
                        showToast('Đã xóa sản phẩm', 'success');
                        if (res.cart_empty) {
                            location.reload();
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showToast(err.message || 'Lỗi kết nối', 'danger');
                    });
            });
            return;
        }

        if (e.target.closest('#clear-cart')) {
            showConfirmToast('Bạn có muốn xóa toàn bộ giỏ hàng?', () => {
                postAjax({ action: 'clear' })
                    .then(res => {
                        if (!res.success) {
                            showToast(res.message || 'Lỗi', 'danger');
                            return;
                        }
                        showToast('Đã xóa toàn bộ giỏ hàng', 'success');
                        location.reload();
                    })
                    .catch(err => {
                        console.error(err);
                        showToast(err.message || 'Lỗi kết nối', 'danger');
                    });
            });
            return;
        }
    });

    // ---------- chọn tất cả ----------
    const selAll = document.getElementById('select-all');
    if (selAll) {
        selAll.addEventListener('change', () => {
            const checked = selAll.checked;
            document.querySelectorAll('.select-item').forEach(cb => {
                cb.checked = checked;
            });
            updateSelectedSummary();
        });
    }

    updateSelectedSummary();
    syncSelectAll();
    // ensure voucher options init correctly
    refreshVoucherOptions();

    // ---------- APPLY VOUCHER (tách hàm để reuse) ----------
    const voucherSelect = document.getElementById('voucher_select');
    const applyVoucherBtn = document.getElementById('applyVoucher');
    const voucherMsg = document.getElementById('voucher-msg');

    function setVoucherMessage(msg, ok = true) {
        if (!voucherMsg) return;
        voucherMsg.textContent = msg;
        voucherMsg.classList.remove('text-success','text-danger');
        voucherMsg.classList.add(ok ? 'text-success' : 'text-danger');
    }

    // Hàm áp mã (gọi từ nút hoặc khi chọn)
    async function applyVoucherCode(code, optionEl = null) {
        if (!code) {
            setVoucherMessage('Vui lòng chọn mã giảm giá', false);
            return;
        }

        // nếu option bị disabled (do minimum chưa đạt hoặc hết lượt), báo và không gửi request
        if (optionEl && optionEl.dataset && optionEl.dataset.disabled === '1') {
            // nếu còn remaining = 0 thì thông báo hết lượt, nếu min chưa đủ thì thông báo điều kiện
            const remaining = parseInt(optionEl.dataset.remaining || '0', 10);
            if (remaining <= 0) {
                setVoucherMessage('Mã này đã hết lượt sử dụng', false);
            } else {
                setVoucherMessage('Mã này hiện chưa đạt điều kiện đơn hàng', false);
            }
            return;
        }

        applyVoucherBtn && (applyVoucherBtn.disabled = true);

        // Lấy các key sản phẩm đang được chọn (checked)
        const selectedKeys = Array.from(document.querySelectorAll('.select-item'))
                                  .filter(cb => cb.checked)
                                  .map(cb => cb.dataset.key);

        if (!selectedKeys.length) {
            setVoucherMessage('Vui lòng chọn ít nhất một sản phẩm để áp mã', false);
            applyVoucherBtn && (applyVoucherBtn.disabled = false);
            return;
        }

        try {
            // Tạo FormData manual để gửi mảng selected_keys[] chính xác
            const fd = new FormData();
            fd.append('action', 'apply_voucher');
            fd.append('code', code);
            selectedKeys.forEach(k => fd.append('selected_keys[]', k));

            const resp = await fetch('cart.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const ct = resp.headers.get('content-type') || '';
            if (!resp.ok) {
                if (ct.includes('application/json')) {
                    const j = await resp.json().catch(() => null);
                    throw new Error(j && j.message ? j.message : 'Server error');
                }
                const t = await resp.text().catch(() => null);
                throw new Error(t || 'Network error');
            }
            if (!ct.includes('application/json')) {
                const t = await resp.text();
                throw new Error('Phản hồi không phải JSON: ' + t);
            }

            const res = await resp.json();

            if (!res.success) {
                setVoucherMessage(res.message || 'Áp dụng mã thất bại', false);
                showToast(res.message || 'Áp dụng mã thất bại', 'danger');
            } else {
                setVoucherMessage(res.message || 'Áp dụng mã thành công', true);
                showToast(res.message || 'Áp dụng mã thành công', 'success');

                // cập nhật tổng tiền hiển thị
if (typeof res.total_raw === 'number') {
    const selectedTotal = parseNumberFromCurrency(
        document.getElementById('selected-total').textContent
    );

    const discount = selectedTotal - res.total_raw;

    // hiển thị dòng giảm giá
    const row = document.getElementById('voucher-row');
    const val = document.getElementById('voucher-discount');

    if (row && val && discount > 0) {
        row.style.display = 'flex';
        val.textContent =
            '-' + new Intl.NumberFormat('vi-VN').format(discount) + '₫';
    }

    const shippingFee = calculateShippingFee(res.total_raw);
    const finalTotal  = res.total_raw + shippingFee;
    document.getElementById('shipping-fee').textContent =
        shippingFee === 0 ? 'Miễn phí'
        : new Intl.NumberFormat('vi-VN').format(shippingFee) + '₫';

    document.getElementById('selected-total-2').textContent =
        new Intl.NumberFormat('vi-VN').format(finalTotal) + '₫';
}
                // nếu server trả remaining_quantity, cập nhật option.dataset và disable khi =0
                if (typeof res.remaining_quantity !== 'undefined' && optionEl) {
                    optionEl.dataset.remaining = String(res.remaining_quantity);
                    if (res.remaining_quantity <= 0) {
                        optionEl.disabled = true;
                        optionEl.dataset.disabled = '1';
                        optionEl.classList.add('voucher-option-disabled');
                    } else {
                        // cập nhật trạng thái (có thể vẫn disabled do min)
                        optionEl.dataset.disabled = optionEl.disabled ? '1' : '0';
                    }
                }

                // Sau khi cập nhật tổng/remaining, refresh toàn bộ options để consistent
                refreshVoucherOptions();
            }
        } catch (err) {
            console.error(err);
            setVoucherMessage(err.message || 'Lỗi kết nối server', false);
            showToast(err.message || 'Lỗi kết nối', 'danger');
        } finally {
            applyVoucherBtn && (applyVoucherBtn.disabled = false);
        }
    }

    // gợi ý: khi nhấn nút
    if (applyVoucherBtn && voucherSelect) {
        applyVoucherBtn.addEventListener('click', () => {
            const code = (voucherSelect.value || '').trim();
            const opt = voucherSelect.selectedOptions && voucherSelect.selectedOptions[0];
            applyVoucherCode(code, opt);
        });
    }

    // --- TỰ ĐỘNG ÁP KHI CHỌN (auto-apply) ---
    if (voucherSelect) {
        voucherSelect.addEventListener('change', (e) => {
            const code = (e.target.value || '').trim();
           if (!code) {
    setVoucherMessage('');

    // lấy các sản phẩm đang chọn
    const selectedKeys = Array.from(document.querySelectorAll('.select-item'))
                              .filter(cb => cb.checked)
                              .map(cb => cb.dataset.key);

    const fd = new FormData();
    fd.append('action', 'remove_voucher');
    selectedKeys.forEach(k => fd.append('selected_keys[]', k));

    fetch('cart.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) return;
        // ✅ Ẩn dòng giảm giá voucher
const row = document.getElementById('voucher-row');
const val = document.getElementById('voucher-discount');
if (row && val) {
    row.style.display = 'none';
    val.textContent = '-0₫';
}


        // cập nhật lại tổng + ship
        const raw = res.total_raw || 0;
        const shippingFee = calculateShippingFee(raw);
        const finalTotal  = raw + shippingFee;

        document.getElementById('selected-total').textContent =
            new Intl.NumberFormat('vi-VN').format(raw) + '₫';

        document.getElementById('shipping-fee').textContent =
            shippingFee === 0 ? 'Miễn phí' :
            new Intl.NumberFormat('vi-VN').format(shippingFee) + '₫';

        document.getElementById('selected-total-2').textContent =
            new Intl.NumberFormat('vi-VN').format(finalTotal) + '₫';

        refreshVoucherOptions();
        showToast('Đã hủy mã giảm giá', 'success');
    });

    return;
}

            const opt = e.target.selectedOptions && e.target.selectedOptions[0];

            // nhỏ delay để UX mượt hơn (vừa đủ để người dùng thấy chọn)
            setTimeout(() => applyVoucherCode(code, opt), 120);
        });
    }

    // ---------- checkout: chỉ gửi selected_keys[] ----------
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', e => {
            e.preventDefault();

            // Xóa input cũ nếu có
            checkoutForm.querySelectorAll('input[name="selected_keys[]"]').forEach(i => i.remove());

            const selected = Array.from(document.querySelectorAll('.select-item'))
                                  .filter(cb => cb.checked)
                                  .map(cb => cb.dataset.key);

            if (!selected.length) {
                showToast('Vui lòng chọn ít nhất một sản phẩm để thanh toán', 'warning');
                return;
            }

            selected.forEach(k => {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'selected_keys[]';
                inp.value = k;
                checkoutForm.appendChild(inp);
            });

            // Submit thật sự
            checkoutForm.submit();
        });
    }
});
</script>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
