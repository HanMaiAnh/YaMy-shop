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
 * Lấy thông tin biến thể theo:
 * - ưu tiên: $variant_id
 * - fallback: $product_id + $size_name + $color_name
 *
 * Trả về:
 * [
 *   variant_id, product_id, product_name,
 *   base_price, price_reduced, discount_percent,
 *   stock, size_name, color_name, image
 * ]
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
 *  - Nếu price_reduced > 0 & < base_price => dùng price_reduced
 *  - else nếu discount_percent > 0        => base * (1 - percent/100)
 *  - else                                 => base
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
   AJAX API (add / update / remove / clear / voucher list/apply)
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

        $MAX_PER_ITEM = 5;

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

                if ($stock > 0) {
                    $qty = min($qty, $stock);
                }
                $qty = min($qty, $MAX_PER_ITEM);

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
                        'size'             => $variant['size_name'],
                        'color'            => $variant['color_name'],
                        'added_at'         => time(),
                    ];
                    $res['message'] = 'Đã thêm vào giỏ hàng';
                } else {
                    $newQty = (int)$_SESSION['cart'][$key]['quantity'] + $qty;
                    if ($stock > 0) {
                        $newQty = min($newQty, $stock);
                    }
                    $newQty = min($newQty, $MAX_PER_ITEM);
                    $_SESSION['cart'][$key]['quantity'] = $newQty;

                    if ($newQty >= $MAX_PER_ITEM) {
                        $res['message'] = "Số lượng đã đạt tối đa ({$MAX_PER_ITEM}) cho biến thể này";
                    } else {
                        $res['message'] = 'Đã cập nhật số lượng trong giỏ hàng';
                    }
                }
                break;

            /* ---------- UPDATE ---------- */
            case 'update':
                $key = $_POST['key'] ?? '';
                $qty = max(1, (int)($_POST['quantity'] ?? 1));
                $qty = min($qty, $MAX_PER_ITEM);

                if ($key && isset($_SESSION['cart'][$key])) {
                    $variant_id = 0;
                    if (strpos($key, 'v_') === 0) {
                        $variant_id = (int)substr($key, 2);
                    }

                    if ($variant_id > 0) {
                        $st = $pdo->prepare("SELECT quantity FROM product_variants WHERE id = ?");
                        $st->execute([$variant_id]);
                        $stock = (int)$st->fetchColumn();
                        if ($stock > 0) {
                            $qty = min($qty, $stock, $MAX_PER_ITEM);
                        }
                    }

                    $_SESSION['cart'][$key]['quantity'] = $qty;
                    $res['message'] = 'Cập nhật thành công';
                } else {
                    $res['message'] = 'Sản phẩm không tồn tại trong giỏ';
                }
                break;

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

            /* ---------- APPLY VOUCHER (with safe decrement) ---------- */
            case 'apply_voucher':
                $code = trim($_POST['code'] ?? '');
                if ($code === '') {
                    $res['message'] = 'Vui lòng nhập mã giảm giá';
                    echo json_encode($res);
                    exit;
                }

                // Tổng tiền hiện tại của giỏ (raw)
                $cartTotal = calculate_total_raw_from_session();
                if ($cartTotal <= 0) {
                    $res['message'] = 'Giỏ hàng trống, không thể áp dụng mã';
                    echo json_encode($res);
                    exit;
                }

                try {
                    // Bắt đầu transaction để tránh race condition (SELECT ... FOR UPDATE)
                    $pdo->beginTransaction();

                    // Lấy voucher và khóa hàng để tránh nhiều request reduce cùng lúc
                    $stmt = $pdo->prepare("\n                        SELECT *\n                        FROM vouchers\n                        WHERE code = ?\n                          AND begin  <= CURDATE()\n                          AND expired >= CURDATE()\n                        LIMIT 1\n                        FOR UPDATE\n                    ");
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
                        $res['message'] = 'Giá trị đơn hàng tối thiểu để dùng mã này là ' .
                                          number_format($minOrder, 0, '', '.') . '₫';
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

                    // Giảm 1 lượt Quantity và commit (nếu bạn muốn ghi log, làm ở đây)
                    $newQty = max(0, $quantityLeft - 1);
                    $upd = $pdo->prepare("UPDATE vouchers SET quantity = ? WHERE id = ?");
                    $upd->execute([$newQty, $voucher['id']]);

                    $pdo->commit();

                    $newTotal = max(0, $cartTotal - $discountAmount);

                    // Lưu voucher vào session (như cũ)
                    $_SESSION['applied_voucher'] = [
                        'id'              => (int)$voucher['id'],
                        'code'            => $voucher['code'],
                        'discount_value'  => $discountValue,
                        'discount_amount' => $discountAmount,
                        'new_total'       => $newTotal,
                        'remaining_quantity' => $newQty,
                    ];

                    $res['success']         = true;
                    $res['message']         = 'Đã áp dụng mã "' . $voucher['code'] .
                                              '" - giảm ' . number_format($discountAmount, 0, '', '.') . '₫';
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

            /* ---------- LIST VOUCHERS (AJAX) ---------- */
            case 'list_vouchers':
                try {
                    $stmt = $pdo->prepare("SELECT id, code, value, amount_reduced, minimum_value, begin, expired, quantity FROM vouchers WHERE begin <= CURDATE() AND expired >= CURDATE() ORDER BY id DESC LIMIT 50");
                    $stmt->execute();
                    $vlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'vouchers' => $vlist]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
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
    $stmt = $pdo->prepare("\n        SELECT id, code, value, amount_reduced, minimum_value, begin, expired\n        FROM vouchers\n        WHERE begin <= CURDATE() AND expired >= CURDATE() AND quantity > 0\n        ORDER BY id DESC\n        LIMIT 20\n    ");
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
            <a href="<?= htmlspecialchars(BASE_URL) ?>" class="btn btn-danger mt-3 px-5">Mua sắm ngay</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Danh sách item -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-0" id="cart-items">
                        <?php foreach ($cart_items as $key => $item): ?>
                            <?php
                                $imgFile = $item['image'] ?? '';
                                $imgSrc  = $imgFile ? upload($imgFile) : upload('no-image.png');
                            ?>
                            <div class="cart-item border-bottom p-4 d-flex align-items-start"
                                 data-key="<?= htmlspecialchars($key) ?>"
                                 data-price="<?= (int)$item['price'] ?>">

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
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <!-- tên + size + màu -->
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
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

                                        <!-- quantity + xóa + giá -->
                                        <div class="cart-actions d-flex align-items-center gap-3">
                                            <div class="input-group input-group-sm quantity-group">
                                                <button type="button"
                                                        class="btn btn-outline-secondary qty-btn"
                                                        data-dir="-1"
                                                        aria-label="Giảm">-</button>
                                                <input type="number"
                                                       class="form-control text-center qty-input"
                                                       value="<?= (int)$item['quantity'] ?>"
                                                       min="1" max="5"
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
                        <h5 class="mb-3">Tóm tắt</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>
                                Tạm tính (<span id="selected-count"><?= count($cart_items) ?></span> sản phẩm)
                            </span>
                            <strong id="selected-total" data-total><?= number_format($total_price) ?>₫</strong>
                        </div>
                        <hr>
<div class="mb-3">
  <label for="voucher_code" class="form-label">Mã giảm giá</label>
  <div class="input-group">
    <input type="text" id="voucher_code" class="form-control" placeholder="Nhập mã giảm giá">
    <button type="button" id="applyVoucher" class="btn btn-outline-primary">Áp dụng</button>
  </div>
  <small id="voucher-msg" class="text-success d-block mt-1"></small>

  <?php if (!empty($available_vouchers)): ?>
    <div class="voucher-list mt-2">
      <div class="small text-muted mb-1">Mã giảm giá hiện có:</div>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($available_vouchers as $v): 
          $code = htmlspecialchars($v['code']);
          $val  = (float)$v['value'];
          $label = $val <= 100 ? ((int)$val . '%') : (number_format($val,0,'','.') . '₫');
          $min = (float)$v['minimum_value'];
          $disabled = ($min > $total_price) ? 'disabled' : '';
          $minLabel = $min > 0 ? 'Đơn từ ' . number_format($min,0,'','.') . '₫' : 'Không yêu cầu';
        ?>
          <button type="button"
                  class="btn btn-sm btn-outline-secondary voucher-btn <?= $disabled ? 'disabled' : '' ?>"
                  data-code="<?= $code ?>"
                  data-min="<?= (int)$min ?>"
                  title="<?= $label ?> — <?= $minLabel ?>"
                  <?= $disabled ? 'aria-disabled="true"' : '' ?>>
            <div><strong><?= $code ?></strong></div>
            <div class="small text-muted"><?= $label ?></div>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<hr>


                        <div class="d-flex justify-content-between mb-3">
                            <strong>Tổng cộng</strong>
                            <strong class="text-danger fs-5" id="selected-total-2" data-total>
                                <?= number_format($total_price) ?>₫
                            </strong>
                        </div>

                        <form id="checkoutForm" action="../view/checkout.php" method="POST">
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
    align-items: center !important;  /* căn giữa tất cả */
}

/* Cột nội dung */
.cart-item .flex-grow-1 {
    display: flex;
    flex-direction: column;
    justify-content: center; /* căn giữa dọc */
}

/* Nhóm bên phải: số lượng – xóa – giá */
.cart-actions {
    display: flex;
    align-items: center !important; /* căn giữa dọc */
}

/* Nút +/- và input số lượng */
.quantity-group {
    display: flex;
    align-items: center;
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

/* bố cục phần hành động bên phải */
.cart-actions {
    min-width: 260px;
    justify-content: flex-end;
}
.cart-actions .quantity-group {
    width: 120px;
}
.cart-actions .subtotal {
    white-space: nowrap;
    min-width: 100px;
    text-align: right;
}
.qty-btn {
    width: 36px;
    padding: .25rem .45rem;
}

/* voucher list */
.voucher-list .voucher-btn {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: .45rem .6rem;
    min-width: 105px;
    line-height: 1;
}

/* toast */
.toast-temp,
.toast-confirm {
    max-width: 360px;
}
.summary-sticky { position: sticky; top: 90px; z-index: 2; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const MAX_PER_ITEM = 5;

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

    // ---------- Tính tạm tính + tổng cộng cho item đang được tick ----------
    function updateSelectedSummary() {
        let totalRaw = 0;
        let countLines = 0;

        document.querySelectorAll('.cart-item').forEach(item => {
            const cb = item.querySelector('.select-item');
            if (!cb || !cb.checked) return;

            const qty   = parseInt(item.querySelector('.qty-input').value) || 0;
            const price = parseInt(item.dataset.price) || 0;

            totalRaw   += qty * price;
            countLines += 1;          // đếm theo dòng sản phẩm (không phải tổng qty)
        });

        const countSpan = document.getElementById('selected-count');
        if (countSpan) countSpan.textContent = countLines;

        document.querySelectorAll('[data-total]').forEach(el => {
            el.textContent =
                new Intl.NumberFormat('vi-VN').format(totalRaw) + '₫';
        });
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

    // ---------- khởi tạo listener cho mỗi item ----------
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
            if (v > MAX_PER_ITEM) {
                v = MAX_PER_ITEM;
                showToast('Số lượng tối đa mỗi sản phẩm là ' + MAX_PER_ITEM, 'warning');
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
            if (v > MAX_PER_ITEM) {
                v = MAX_PER_ITEM;
                showToast('Số lượng tối đa mỗi sản phẩm là ' + MAX_PER_ITEM, 'warning');
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

// === Voucher helper functions ===
function parseVnd(str) {
    if (!str) return 0;
    return parseInt(String(str).replace(/[^\d]/g, ''), 10) || 0;
}

// Tính tổng các item đang được tick (same logic as updateSelectedSummary but returns number)
function getSelectedTotalNumber() {
    let total = 0;
    document.querySelectorAll('.cart-item').forEach(item => {
        const cb = item.querySelector('.select-item');
        if (!cb || !cb.checked) return;
        const qty   = parseInt(item.querySelector('.qty-input').value) || 0;
        const price = parseInt(item.dataset.price) || 0;
        total += qty * price;
    });
    return total;
}

// Cập nhật trạng thái (enabled/disabled) của các voucher button dựa trên tổng hiện tại
function refreshVoucherButtons() {
    const total = getSelectedTotalNumber();
    document.querySelectorAll('.voucher-btn').forEach(btn => {
        const min = parseInt(btn.dataset.min || '0', 10) || 0;
        const qty = parseInt(btn.dataset.qty || '0', 10) || 0;
        if (min > total || qty <= 0) {
            btn.classList.add('disabled');
            btn.setAttribute('aria-disabled', 'true');
            btn.title = `Yêu cầu đơn tối thiểu ${new Intl.NumberFormat('vi-VN').format(min)}₫`;
        } else {
            btn.classList.remove('disabled');
            btn.removeAttribute('aria-disabled');
        }
    });

    // Nếu voucher đã active nhưng giờ không hợp lệ thì bỏ active + clear message
    const active = document.querySelector('.voucher-btn.active');
    if (active && active.classList.contains('disabled')) {
        active.classList.remove('active');
        const vm = document.getElementById('voucher-msg');
        if (vm) { vm.textContent = ''; vm.className = ''; }
    }
}

// ---------------- applyVoucherCode (mới) ----------------
async function applyVoucherCode(code) {
    const voucherMsg = document.getElementById('voucher-msg');
    if (!code) {
        if (voucherMsg) { voucherMsg.textContent = 'Vui lòng nhập mã giảm giá'; voucherMsg.className = 'text-danger'; }
        return;
    }

    try {
        const resp = await postAjax({ action: 'apply_voucher', code });
        if (resp.success) {
            if (voucherMsg) { voucherMsg.textContent = resp.message; voucherMsg.className = 'text-success'; }

            // Cập nhật các hiển thị tổng tiền
            document.querySelectorAll('[data-total]').forEach(el => el.textContent = resp.total_formatted);

            // Thêm/ cập nhật hidden input để gửi lên checkout
            let existing = document.querySelector('input[name="applied_voucher_code"]');
            if (!existing) {
                existing = document.createElement('input');
                existing.type = 'hidden';
                existing.name = 'applied_voucher_code';
                document.getElementById('checkoutForm').appendChild(existing);
            }
            existing.value = code;

            // highlight matching voucher button (nếu có)
            document.querySelectorAll('.voucher-btn').forEach(b => {
                if ((b.dataset.code || '').toLowerCase() === code.toLowerCase()) b.classList.add('active');
                else b.classList.remove('active');
            }

            );

            // If server returned remaining_quantity, update the button's data-qty
            if (typeof resp.remaining_quantity !== 'undefined') {
                document.querySelectorAll('.voucher-btn').forEach(b => {
                    if ((b.dataset.code||'').toLowerCase() === code.toLowerCase()) {
                        b.dataset.qty = resp.remaining_quantity;
                        if (parseInt(b.dataset.qty||'0',10) <= 0) {
                            b.classList.add('disabled');
                            b.setAttribute('aria-disabled','true');
                            b.title = 'Mã đã hết lượt sử dụng';
                        }
                    }
                });
            }

            // reload the list from server to be absolutely in-sync (optional)
            setTimeout(() => loadVouchers(), 200);

        } else {
            // show error message
            if (voucherMsg) { voucherMsg.textContent = resp.message || 'Mã không hợp lệ'; voucherMsg.className = 'text-danger'; }

            // If server says voucher exhausted or not found, disable client button
            const lower = (resp.message || '').toLowerCase();
            if (lower.includes('hết lượt') || lower.includes('hết hạn') || lower.includes('không tồn tại')) {
                document.querySelectorAll('.voucher-btn').forEach(b => {
                    if ((b.dataset.code || '').toLowerCase() === code.toLowerCase()) {
                        b.classList.add('disabled');
                        b.setAttribute('aria-disabled','true');
                        b.title = resp.message || 'Mã không còn hiệu lực';
                    }
                });
                // optionally reload server list
                setTimeout(() => loadVouchers(), 200);
            }
        }
    } catch (err) {
        if (voucherMsg) { voucherMsg.textContent = err.message || 'Lỗi kết nối'; voucherMsg.className = 'text-danger'; }
    }
}

// ---------------- loadVouchers() ----------------
async function loadVouchers() {
    const container = document.querySelector('.voucher-list');
    if (!container) return;

    try {
        const resp = await postAjax({ action: 'list_vouchers' });
        if (!resp.success) return;

        const vouchers = resp.vouchers || [];
        // rebuild HTML
        let html = '<div class="small text-muted mb-1">Mã giảm giá hiện có:</div><div class="d-flex flex-wrap gap-2">';
        vouchers.forEach(v => {
            const code = (v.code||'').replace(/</g,'&lt;');
            const val = parseFloat(v.value);
            const label = val <= 100 ? (parseInt(val)+'%') : (new Intl.NumberFormat('vi-VN').format(parseInt(val)) + '₫');
            const min = parseInt(v.minimum_value || 0);
            const qty = parseInt(v.quantity || 0);
            const disabled = (min > getSelectedTotalNumber() || qty <= 0) ? 'disabled' : '';
            const ariaDisabled = (qty <= 0) ? 'aria-disabled="true"' : '';
            const title = `${label} — ${min>0 ? 'Đơn từ ' + new Intl.NumberFormat('vi-VN').format(min) + '₫' : 'Không yêu cầu'}${qty<=0 ? ' — Đã hết lượt' : ''}`;
            html += `<button type="button" class="btn btn-sm btn-outline-secondary voucher-btn ${disabled}" data-code="${code}" data-min="${min}" data-qty="${qty}" title="${title}" ${ariaDisabled}>
                        <div><strong>${code}</strong></div>
                        <div class="small text-muted">${label}${qty<=0 ? ' • Hết lượt' : ''}</div>
                     </button>`;
        });
        html += '</div>';
        container.innerHTML = html;

        // wire events
        document.querySelectorAll('.voucher-btn').forEach(btn => {
            btn.addEventListener('click', async (ev) => {
                ev.preventDefault();
                const code = (btn.dataset.code || '').trim();
                const min  = parseInt(btn.dataset.min || '0', 10) || 0;
                const total = getSelectedTotalNumber();
                const qty = parseInt(btn.dataset.qty || '0', 10) || 0;

                if (btn.classList.contains('disabled') || min > total || qty <= 0) {
                    const voucherMsg = document.getElementById('voucher-msg');
                    if (voucherMsg) {
                        const msg = qty <= 0 ? 'Mã đã hết lượt sử dụng' : `Mã yêu cầu đơn tối thiểu ${new Intl.NumberFormat('vi-VN').format(min)}₫`;
                        voucherMsg.textContent = msg;
                        voucherMsg.className = 'text-danger';
                    }
                    return;
                }

                // điền mã vào ô
                const voucherInput = document.getElementById('voucher_code');
                if (voucherInput) voucherInput.value = code;

                // highlight UI
                document.querySelectorAll('.voucher-btn.active').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                // auto-apply
                await applyVoucherCode(code);
            });
        });

        // ensure buttons reflect current selection total
        refreshVoucherButtons();

    } catch (err) {
        console.error('loadVouchers error', err);
    }
}

// Hook into init handlers and call loadVouchers
(function initVoucherHandlers() {
    const btnApplyVoucher = document.getElementById('applyVoucher');
    const voucherInput    = document.getElementById('voucher_code');
    const voucherMsg      = document.getElementById('voucher-msg');

    if (btnApplyVoucher) {
        btnApplyVoucher.addEventListener('click', async () => {
            if (!voucherInput) return;
            const code = voucherInput.value.trim();
            if (!code) {
                if (voucherMsg) { voucherMsg.textContent = 'Vui lòng nhập mã giảm giá'; voucherMsg.className = 'text-danger'; }
                return;
            }
            await applyVoucherCode(code);
        });
    }

    // Click chọn voucher từ list
    document.querySelectorAll('.voucher-btn').forEach(btn => {
        btn.addEventListener('click', async (ev) => {
            ev.preventDefault();
            const code = (btn.dataset.code || '').trim();
            const min  = parseInt(btn.dataset.min || '0', 10) || 0;
            const total = getSelectedTotalNumber();

            // nếu disabled theo dữ liệu -> show lý do, không apply
            if (btn.classList.contains('disabled') || min > total) {
                if (voucherMsg) {
                    const msg = min > 0
                        ? `Mã yêu cầu đơn tối thiểu ${new Intl.NumberFormat('vi-VN').format(min)}₫`
                        : 'Mã tạm thời không thể áp dụng';
                    voucherMsg.textContent = msg;
                    voucherMsg.className = 'text-danger';
                }
                return;
            }

            // điền mã vào ô
            if (voucherInput) voucherInput.value = code;

            // highlight UI
            document.querySelectorAll('.voucher-btn.active').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // auto-apply
            await applyVoucherCode(code);
        });
    });

    // Khi khởi tạo/ DOM ready -> set trạng thái voucher lần đầu
    refreshVoucherButtons();

    // load actual vouchers from server (to keep qty up-to-date)
    loadVouchers();

    // Khi user thay đổi selection / số lượng, gọi refresh để cập nhật voucher states.
    document.addEventListener('change', (e) => {
        if (e.target && (e.target.classList.contains('select-item') || e.target.id === 'select-all' || e.target.classList.contains('qty-input'))) {
            // nhỏ delay để các cập nhật subtotal hoàn tất
            clearTimeout(window._voucherRefreshTimer2);
            window._voucherRefreshTimer2 = setTimeout(() => {
                refreshVoucherButtons();
                // cũng refresh buttons list so qty/min states are correct
                loadVouchers();
            }, 80);
        }
    });
})();

    // ---------- checkout: chỉ gửi selected_keys[] ----------
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', e => {
            e.preventDefault();
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

            checkoutForm.submit();
        });
    }
});

</script>



<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
