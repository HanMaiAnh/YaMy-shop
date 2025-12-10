<?php
ini_set('session.cookie_path', '/');
session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ====== CHECK ADMIN ====== */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../view/login.php");
    exit;
}

try {
    require_once __DIR__ . '/../config/db.php';
    $conn = $pdo; // từ db.php

    // Lấy id đơn hàng (validate)
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header("Location: orders.php");
        exit;
    }
    $orderId = (int)$_GET['id'];

    // ====== THÔNG TIN ĐƠN HÀNG ======
    $sqlOrder = "SELECT * FROM orders WHERE id = :id LIMIT 1";
    $stmt = $conn->prepare($sqlOrder);
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo "<p style='color:red;text-align:center;margin-top:50px;'>Không tìm thấy đơn hàng.</p>";
        exit;
    }

    // Mã đơn dạng DH00001
    $orderCode = 'DH' . str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT);

    /*
     * Lấy các item trong đơn
     * - LEFT JOIN product_variants (variant có thể null)
     * - Lấy product bằng COALESCE(pv.product_id, od.product_id)
     * - Lấy size & color từ product_variants (pv.size_id, pv.color_id)
     * - Lấy 1 ảnh đại diện từ product_images.image_url
     */
    $sqlItems = "
        SELECT 
            od.*,
            p.id                      AS product_id,
            p.name                    AS product_name,
            pv.id                     AS variant_id,
            pv.price                  AS variant_price,
            pv.price_reduced          AS variant_price_reduced,
            s.name                    AS size_name,
            c.name                    AS color_name,
            pi.image_url              AS product_image
        FROM order_details od
        LEFT JOIN product_variants pv ON od.variant_id = pv.id
        LEFT JOIN products p ON p.id = COALESCE(pv.product_id, od.product_id)
        LEFT JOIN sizes s ON pv.size_id = s.id
        LEFT JOIN colors c ON pv.color_id = c.id
        LEFT JOIN (
            SELECT product_id, MIN(image_url) AS image_url
            FROM product_images
            GROUP BY product_id
        ) pi ON pi.product_id = p.id
        WHERE od.order_id = :order_id
        ORDER BY od.id ASC
    ";

    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->execute([':order_id' => $orderId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Tổng tiền từ cột total (đã lưu trong orders) - dùng để hiển thị "tổng trong đơn"
    $orderTotal = (float)($order['total'] ?? 0);

    // --- Chuẩn bị statement tìm variant fallback ---
    $findVariantStmt = $conn->prepare("
        SELECT id, product_id, size_id, color_id, price, price_reduced
        FROM product_variants
        WHERE product_id = :product_id
        ORDER BY (price_reduced IS NOT NULL) DESC, ABS(COALESCE(price_reduced, price) - :order_price) ASC
        LIMIT 1
    ");

    // Chuẩn hóa items: tính line total, chuẩn hóa img src, tìm fallback variant nếu cần
    $calcTotal = 0.0;
    foreach ($items as $k => $it) {
        $qty = (int)($it['quantity'] ?? 0);
        // Giá lưu trong order_details là giá khách trả (đơn giá thực tế)
        $unitPrice = (float)($it['price'] ?? 0);
        $lineTotal = $qty * $unitPrice;
        $calcTotal += $lineTotal;

        // Nếu pv không join (variant_id null), cố gắng tìm variant fallback theo product_id + giá
        $variant_id = isset($it['variant_id']) && $it['variant_id'] ? $it['variant_id'] : null;

        if (empty($variant_id)) {
            $productId = $it['product_id'] ?? null;
            if (!empty($productId)) {
                $findVariantStmt->execute([
                    ':product_id' => $productId,
                    ':order_price' => $unitPrice
                ]);
                $pv = $findVariantStmt->fetch(PDO::FETCH_ASSOC);
                if ($pv) {
                    $variant_id = $pv['id'];
                    // lấy tên size & color nếu có
                    $sizeName = '-';
                    $colorName = '-';
                    if (!empty($pv['size_id'])) {
                        $sstmt = $conn->prepare("SELECT name FROM sizes WHERE id = :id LIMIT 1");
                        $sstmt->execute([':id' => $pv['size_id']]);
                        $sr = $sstmt->fetch(PDO::FETCH_ASSOC);
                        $sizeName = $sr['name'] ?? '-';
                    }
                    if (!empty($pv['color_id'])) {
                        $cstmt = $conn->prepare("SELECT name FROM colors WHERE id = :id LIMIT 1");
                        $cstmt->execute([':id' => $pv['color_id']]);
                        $cr = $cstmt->fetch(PDO::FETCH_ASSOC);
                        $colorName = $cr['name'] ?? '-';
                    }
                    // gán fallback values
                    $items[$k]['variant_id'] = $pv['id'];
                    $items[$k]['_variant_price'] = (float)$pv['price'];
                    $items[$k]['_variant_price_reduced'] = (float)$pv['price_reduced'];
                    $items[$k]['_size_name'] = $sizeName;
                    $items[$k]['_color_name'] = $colorName;
                } else {
                    // no variant found -> fallback from joined fields or '-'
                    $items[$k]['_size_name'] = $it['size_name'] ?? '-';
                    $items[$k]['_color_name'] = $it['color_name'] ?? '-';
                    $items[$k]['_variant_price'] = $items[$k]['_variant_price_reduced'] = 0.0;
                }
            } else {
                $items[$k]['_size_name'] = $it['size_name'] ?? '-';
                $items[$k]['_color_name'] = $it['color_name'] ?? '-';
                $items[$k]['_variant_price'] = $items[$k]['_variant_price_reduced'] = 0.0;
            }
        } else {
            // variant existed in join: copy variant fields if any
            $items[$k]['_variant_price'] = isset($it['variant_price']) ? (float)$it['variant_price'] : 0.0;
            $items[$k]['_variant_price_reduced'] = isset($it['variant_price_reduced']) ? (float)$it['variant_price_reduced'] : 0.0;
            $items[$k]['_size_name'] = $it['size_name'] ?? '-';
            $items[$k]['_color_name'] = $it['color_name'] ?? '-';
        }

        // Ảnh: xử lý đường dẫn (nếu product_image empty => no-image.png)
        $imgFile = trim((string)($it['product_image'] ?? ''));
        if ($imgFile === '') {
            $imgSrc = '../uploads/no-image.png';
        } else {
            // Nếu đã lưu 'uploads/...' hoặc '/uploads/...' thì chuẩn hóa
            if (strpos($imgFile, 'uploads/') !== false || strpos($imgFile, '/uploads/') !== false) {
                $imgSrc = (strpos($imgFile, '../') === 0) ? $imgFile : ('../' . ltrim($imgFile, '/'));
            } else {
                // nếu chỉ tên file -> ghép vào ../uploads/
                $imgSrc = '../uploads/' . str_replace(' ', '%20', $imgFile);
            }
        }

        // lưu các trường tiện dùng trong template
        $items[$k]['_img_src'] = $imgSrc;
        $items[$k]['_qty'] = $qty;
        $items[$k]['_unit_price'] = $unitPrice;
        $items[$k]['_line_total'] = $lineTotal;
    }

    // ====== TRẠNG THÁI ĐƠN HÀNG (map sang tiếng Việt + badge) ======
    $statusRaw   = $order['status'] ?? 'pending';
    $statusText  = $statusRaw;
    $badgeClass  = 'badge-pending';

    switch (mb_strtolower($statusRaw)) {
        case 'pending':
        case 'chờ xác nhận':
            $statusText = 'Chờ xác nhận';
            $badgeClass = 'badge-pending';
            break;
        case 'processing':
        case 'đang xử lý':
        case 'dang xu ly':
            $statusText = 'Đang xử lý';
            $badgeClass = 'badge-processing';
            break;
        case 'shipping':
        case 'đơn hàng đang được giao':
            $statusText = 'Đơn hàng đang được giao';
            $badgeClass = 'badge-shipping';
            break;
        case 'completed':
        case 'đã giao hàng':
            $statusText = 'Đã giao hàng';
            $badgeClass = 'badge-completed';
            break;
        case 'cancelled':
        case 'hủy đơn hàng':
        case 'đã hủy':
            $statusText = 'Đã hủy đơn hàng';
            $badgeClass = 'badge-cancel';
            break;
        default:
            $statusText = $statusRaw;
            $badgeClass = 'badge-pending';
            break;
    }

    // ====== PHƯƠNG THỨC THANH TOÁN ======
    $paymentRaw = $order['payment_method'] ?? 'cod';
    $paymentText = $paymentRaw;

    switch (mb_strtolower($paymentRaw)) {
        case 'cod':
            $paymentText = 'Thanh toán khi nhận hàng (COD)';
            break;
        case 'vnpay':
            $paymentText = 'Thanh toán qua VNPay';
            break;
        default:
            $paymentText = $paymentRaw;
            break;
    }

} catch (PDOException $e) {
    echo "<p style='color:red;'>Lỗi kết nối: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Chi tiết đơn hàng <?= htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* CSS giữ nguyên phong cách trước đó */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
:root{
    --bg-main:#f7f5ff;
    --bg-sidebar:#ffffff;
    --card-bg:#ffffff;
    --text-color:#222;
    --border-color:#e0d7ff;
    --hover-color:#f5f0ff;
}
body{display:flex;background:var(--bg-main);color:var(--text-color);}

/* SIDEBAR */
.sidebar{
    width:260px;
    background:#fff;
    height:100vh;
    padding:30px 20px;
    position:fixed;
    border-right:1px solid #ddd;
}
.sidebar h3{
    font-size:22px;
    font-weight:700;
    margin-bottom:25px;
}
.sidebar a{
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px;
    color:#333;
    text-decoration:none;
    border-radius:8px;
    margin-bottom:8px;
    transition:.25s;
    font-weight:500;
    font-size:15px;
}
.sidebar a:hover{
    background:#f2e8ff;
    color:#8E5DF5;
    transform:translateX(4px);
}
.sidebar .logout{
    color:#e53935;
    margin-top:20px;
}

.content{margin-left:280px;padding:30px;width:100%;}
.page-title{font-size:26px;font-weight:700;margin-bottom:10px;}
.breadcrumb{font-size:13px;color:#777;margin-bottom:20px;}
.breadcrumb a{color:#8E5DF5;text-decoration:none;}

.card{
    background:var(--card-bg);
    border-radius:16px;
    padding:20px 22px;
    margin-bottom:20px;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
}
.card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:16px;
}
.card-header h2{
    font-size:18px;
    font-weight:600;
}
.badge-status{
    padding:6px 12px;
    border-radius:999px;
    font-size:13px;
}
.badge-pending{background:#fff5d7;color:#b38300;}
.badge-processing{background:#e5f3ff;color:#005c99;}
.badge-shipping{background:#e3ffe7;color:#1b8b42;}
.badge-completed{background:#e0ffec;color:#1b8b42;}
.badge-cancel{background:#ffe6e6;color:#b3261e;}

.info-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:12px 20px;
}
.info-item-title{
    font-size:12px;
    text-transform:uppercase;
    color:#888;
    margin-bottom:4px;
}
.info-item-value{
    font-size:14px;
    font-weight:500;
}

.table-wrapper{
    margin-top:10px;
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
}
table{width:100%;border-collapse:collapse;background:var(--card-bg);}
th{background:#8E5DF5;padding:12px;text-align:left;font-weight:600;color:#fff;font-size:14px;}
td{padding:12px;border-bottom:1px solid var(--border-color);font-size:14px;}
tr:hover{background:var(--hover-color);}
.text-right{text-align:right;}
.total-row td{font-weight:600;font-size:15px;}

/* ô sản phẩm */
.product-cell{
    display:flex;
    align-items:center;
    gap:10px;
}
.product-img{
    width:50px;
    height:50px;
    border-radius:8px;
    object-fit:cover;
    border:1px solid var(--border-color);
}

.btn-back{
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:#f0e8ff;
    padding:8px 14px;
    border-radius:999px;
    font-size:13px;
    text-decoration:none;
    color:#5a3ec8;
    margin-bottom:10px;
}
.btn-back i{font-size:12px;}
.back-link{margin-top:20px;}
.back-link a{
    color:#8E5DF5;
    font-weight:600;
    font-size:16px;
    text-decoration:none;
}

.back-link a:hover{
    text-decoration:underline;
    color:#E91E63;
}

.small-muted { font-size:12px; color:#777; }
.badge-mini { padding:6px 10px; border-radius:999px; font-size:12px; display:inline-block; }
.badge-km { background:#ffeedd; color:#b26b00; margin-left:6px; border-radius:8px; padding:4px 8px; }

</style>
</head>

<body>
<div class="sidebar">
    <h3>YaMy Admin</h3>
   <a href="dashboard.php"><i class="fa fa-gauge"></i> Trang Quản Trị</a>
    <a href="orders.php"><i class="fa fa-shopping-cart"></i> Quản lý đơn hàng</a>
    <a href="users.php"><i class="fa fa-user"></i> Quản lý người dùng</a>
    <a href="products.php"><i class="fa fa-box"></i> Quản lý sản phẩm</a>
    <a href="categories.php"><i class="fa fa-list"></i> Quản lý danh mục</a>
    <a href="sizes_colors.php"><i class="fa fa-ruler-combined"></i> Size & Màu</a>
    <a href="vouchers.php"><i class="fa-solid fa-tags"></i> Quản lý vouchers</a>
    <a href="news.php"><i class="fa fa-newspaper"></i> Quản lý tin tức</a>
    <a href="reviews.php"><i class="fa fa-comment"></i> Quản lý bình luận</a>
    <a href="logout.php" class="logout"><i class="fa fa-sign-out"></i> Đăng xuất</a>
</div>

<div class="content">
    <a href="orders.php" class="btn-back"><i class="fa fa-arrow-left"></i> Quay lại danh sách</a>
    <h1 class="page-title">Chi tiết đơn hàng</h1>
    <div class="breadcrumb">
        <a href="orders.php">Quản lý đơn hàng</a> &raquo;
        Đơn: <strong><?= htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') ?></strong>
    </div>

    <!-- THÔNG TIN ĐƠN HÀNG -->
    <div class="card">
        <div class="card-header">
            <h2>Thông tin đơn hàng</h2>
            <span class="badge-status <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <div class="info-grid">
            <div>
                <div class="info-item-title">Mã đơn hàng</div>
                <div class="info-item-value"><?= htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div>
                <div class="info-item-title">Tên người nhận</div>
                <div class="info-item-value">
                    <?= htmlspecialchars($order['recipient_name'] ?? '--', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div>
                <div class="info-item-title">Số điện thoại</div>
                <div class="info-item-value">
                    <?= htmlspecialchars($order['recipient_phone'] ?? '--', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div>
                <div class="info-item-title">Địa chỉ nhận hàng</div>
                <div class="info-item-value">
                    <?= nl2br(htmlspecialchars($order['recipient_address'] ?? '--', ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </div>
            <div>
                <div class="info-item-title">Phương thức thanh toán</div>
                <div class="info-item-value">
                    <?= htmlspecialchars($paymentText, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div>
                <div class="info-item-title">Tổng tiền (ghi trong đơn)</div>
                <div class="info-item-value">
                    <?= number_format($orderTotal, 0, ',', '.') ?> đ
                </div>
            </div>
            <div>
                <div class="info-item-title">Ghi chú</div>
                <div class="info-item-value">
                    <?= nl2br(htmlspecialchars($order['note'] ?? 'Không có', ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </div>
            <div>
                <div class="info-item-title">Ngày đặt</div>
                <div class="info-item-value">
                    <?= htmlspecialchars($order['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- DANH SÁCH SẢN PHẨM -->
    <div class="card">
        <div class="card-header">
            <h2>Sản phẩm trong đơn</h2>
        </div>

        <?php if (!empty($items)): ?>
            <div class="table-wrapper">
                <table>
                    <tr>
                        <th>Stt</th>
                        <th>Sản phẩm</th>
                        <th>Size</th>
                        <th>Màu</th>
                        <th>Số lượng</th>
                        <th>Giá gốc</th>
                        <th>Giá KM</th>
                        <th>Đơn giá</th>
                        <th class="text-right">Thành tiền</th>
                    </tr>
                    <?php $i = 1; ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td>
                                <div class="product-cell">
                                    <img src="<?= htmlspecialchars($item['_img_src'], ENT_QUOTES, 'UTF-8') ?>" alt="Ảnh" class="product-img" onerror="this.onerror=null;this.src='../uploads/no-image.png'">
                                    <div>
                                        <div><?= htmlspecialchars($item['product_name'] ?? ('Sản phẩm #' . ($item['product_id'] ?? '')), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($item['variant_id'])): ?>
                                            <div class="small-muted">Variant ID: <?= htmlspecialchars($item['variant_id'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($item['_size_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($item['_color_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)($item['_qty'] ?? 0); ?></td>
                            <td>
                                <?= (!empty($item['_variant_price']) && $item['_variant_price'] > 0) ? number_format($item['_variant_price'], 0, ',', '.') . ' đ' : '-' ?>
                            </td>
                            <td>
                                <?= (!empty($item['_variant_price_reduced']) && $item['_variant_price_reduced'] > 0) ? number_format($item['_variant_price_reduced'], 0, ',', '.') . ' đ' : '-' ?>
                            </td>
                            <td>
                                <?= number_format($item['_unit_price'], 0, ',', '.'); ?> đ
                            </td>
                            <td class="text-right"><?= number_format($item['_line_total'], 0, ',', '.'); ?> đ</td>
                        </tr>
                    <?php endforeach; ?>

                    <tr class="total-row">
                        <td colspan="8">Tổng tiền</td>
                        <td class="text-right"><?= number_format($calcTotal, 0, ',', '.'); ?> đ</td>
                    </tr>
                </table>
            </div>
        <?php else: ?>
            <p style="margin-top:10px;color:#777;">Đơn hàng không có sản phẩm nào.</p>
        <?php endif; ?>
    </div>

    <div class="back-link">
        <a href="orders.php">← Quay lại danh sách đơn hàng</a>
    </div>
</div>
</body>
</html>
