<?php
// includes/stock.php
if (!function_exists('reduceStockAfterPayment')) {

    /**
     * Giảm tồn kho an toàn cho order.
     *
     * @param PDO $pdo
     * @param int $order_id
     * @param bool $markPaid  Nếu true => đánh dấu paid; nếu false => đánh dấu 'processing'
     * @return array ['success'=>bool, 'message'=>string]
     */
    function reduceStockAfterPayment(PDO $pdo, int $order_id, bool $markPaid = true)
    {
        try {
            // Lấy danh sách sản phẩm trong đơn hàng
            $stmt = $pdo->prepare("SELECT id, product_id, variant_id, quantity FROM order_details WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$items) {
                return ['success' => false, 'message' => 'Đơn hàng không có sản phẩm.'];
            }

            // Bắt đầu transaction
            $pdo->beginTransaction();

            foreach ($items as $item) {

                $qty        = (int)($item['quantity'] ?? 0);
                $product_id = (int)($item['product_id'] ?? 0);
                $variant_id = (int)($item['variant_id'] ?? 0);

                if ($qty <= 0 || $product_id <= 0) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Dữ liệu sản phẩm trong order không hợp lệ.'];
                }

                // Nếu có variant → trừ trong bảng product_variants
                if ($variant_id > 0) {

                    $stmtV = $pdo->prepare("SELECT quantity FROM product_variants WHERE id = ? FOR UPDATE");
                    $stmtV->execute([$variant_id]);

                    $rowV = $stmtV->fetch(PDO::FETCH_ASSOC);
                    if (!$rowV) {
                        $pdo->rollBack();
                        return ['success' => false, 'message' => "Biến thể ID {$variant_id} không tồn tại."];
                    }

                    $current_stock = (int)$rowV['quantity'];
                    if ($current_stock < $qty) {
                        $pdo->rollBack();
                        return ['success' => false, 'message' => "Biến thể ID {$variant_id} chỉ còn {$current_stock}, không đủ tồn kho."];
                    }

                    // Trừ tồn kho variant
                    $stmtUpdV = $pdo->prepare("UPDATE product_variants SET quantity = quantity - ? WHERE id = ?");
                    $stmtUpdV->execute([$qty, $variant_id]);

                } else {
                    // Không có variant -> cần bảng products có cột quantity để trừ
                    $colCheck = $pdo->query("SHOW COLUMNS FROM products LIKE 'quantity'")->fetch(PDO::FETCH_ASSOC);
                    if (!$colCheck) {
                        $pdo->rollBack();
                        return [
                            'success' => false,
                            'message' => "Sản phẩm ID {$product_id} không có biến thể và bảng products không có cột tồn kho."
                        ];
                    }

                    // Lấy và FOR UPDATE trên products
                    $stmtP = $pdo->prepare("SELECT quantity FROM products WHERE id = ? FOR UPDATE");
                    $stmtP->execute([$product_id]);
                    $rowP = $stmtP->fetch(PDO::FETCH_ASSOC);

                    if (!$rowP) {
                        $pdo->rollBack();
                        return ['success' => false, 'message' => "Sản phẩm ID {$product_id} không tồn tại."];
                    }

                    $current = (int)$rowP['quantity'];
                    if ($current < $qty) {
                        $pdo->rollBack();
                        return ['success' => false, 'message' => "Sản phẩm ID {$product_id} chỉ còn {$current}, không đủ tồn kho."];
                    }

                    // Trừ products.quantity
                    $stmtUpdP = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                    $stmtUpdP->execute([$qty, $product_id]);
                }
            }

            // Cập nhật trạng thái đơn (ghi chú: bảng orders có thể không có cột paid_at)
            if ($markPaid) {
                // nếu muốn lưu paid_at thì thêm cột vào DB; ở đây chỉ cập nhật status để tránh lỗi schema
                $stmtOrder = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
                $stmtOrder->execute([$order_id]);
            } else {
                $stmtOrder = $pdo->prepare("UPDATE orders SET status = 'processing' WHERE id = ?");
                $stmtOrder->execute([$order_id]);
            }

            $pdo->commit();

            return ['success' => true, 'message' => 'Đã trừ tồn kho thành công.'];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => 'Lỗi khi trừ tồn kho: ' . $e->getMessage()];
        }
    }

} // end if function_exists
