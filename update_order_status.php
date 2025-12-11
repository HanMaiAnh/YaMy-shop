<?php
// update_order_status.php
session_name('admin_session');
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// === Kiểm tra quyền admin ===
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Không có quyền thực hiện"]);
    exit;
}

// === Method & input ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Phương thức không hợp lệ"]);
    exit;
}

// Lấy dữ liệu an toàn
$idRaw = $_POST['id'] ?? '';
$statusRaw = $_POST['status'] ?? '';

$id = filter_var($idRaw, FILTER_VALIDATE_INT);
$newStatus = is_string($statusRaw) ? trim($statusRaw) : '';

if ($id === false || $id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ID không hợp lệ"]);
    exit;
}
if ($newStatus === '') {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Trạng thái không hợp lệ"]);
    exit;
}

try {
    require_once __DIR__ . '/../config/db.php'; // mong là file này tạo $pdo (PDO instance)
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Không tìm thấy kết nối CSDL (pdo).');
    }

    // Lấy trạng thái hiện tại
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = :id FOR UPDATE");
    // NOTE: FOR UPDATE works only inside a transaction for some DB engines — we'll start a transaction
    $pdo->beginTransaction();
    $stmt->execute([':id' => $id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Không tìm thấy đơn hàng", "currentStatus" => null]);
        exit;
    }

    $currentStatus = (string)$order['status'];

    // Danh sách trạng thái hợp lệ (flow)
    $statusFlow = [
        'Chờ xác nhận',
        'Đang xử lý',
        'Đơn hàng đang được giao',
        'Đã giao hàng',
        'Hủy đơn hàng'
    ];

    // Nếu hiện tại là final state thì không cho sửa
    if (in_array($currentStatus, ['Đã giao hàng', 'Hủy đơn hàng'], true)) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Đơn hàng đã ở trạng thái kết thúc, không thể thay đổi",
            "currentStatus" => $currentStatus
        ]);
        exit;
    }

    // Kiểm tra newStatus thuộc flow hay không
    if (!in_array($newStatus, $statusFlow, true)) {
        // Có thể cho phép trạng thái ngoài flow? Hiện tại block lại
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Trạng thái mới không hợp lệ",
            "currentStatus" => $currentStatus
        ]);
        exit;
    }

    // Nếu both in flow -> kiểm tra bước
    $inCurrent = in_array($currentStatus, $statusFlow, true);
    $inNew     = in_array($newStatus, $statusFlow, true);

    if ($inCurrent && $inNew) {
        $currentIndex = array_search($currentStatus, $statusFlow, true);
        $newIndex     = array_search($newStatus, $statusFlow, true);

        // cho phép cập nhật nếu:
        // - newStatus == currentStatus (no-op)
        // - OR newStatus == 'Hủy đơn hàng'
        // - OR newIndex == currentIndex + 1 (sang bước kế tiếp)
        if ($newStatus !== $currentStatus) {
            $allowed = false;
            if ($newStatus === 'Hủy đơn hàng') {
                $allowed = true;
            } elseif ($newIndex === $currentIndex + 1) {
                $allowed = true;
            }

            if (!$allowed) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Chỉ được chuyển sang bước kế tiếp hoặc hủy đơn hàng",
                    "currentStatus" => $currentStatus
                ]);
                exit;
            }
        } // else no-op allowed
    }

    // Sử dụng optimistic update: chỉ cập nhật khi status hiện tại khớp (tránh race)
    $update = $pdo->prepare("UPDATE orders SET status = :newStatus WHERE id = :id AND status = :currentStatus");
    $update->execute([
        ':newStatus' => $newStatus,
        ':id' => $id,
        ':currentStatus' => $currentStatus
    ]);

    if ($update->rowCount() === 0) {
        // có thể là vì trạng thái đã thay đổi song song -> lấy trạng thái hiện tại mới nhất
        $stmt2 = $pdo->prepare("SELECT status FROM orders WHERE id = :id");
        $stmt2->execute([':id' => $id]);
        $fresh = $stmt2->fetch(PDO::FETCH_ASSOC);
        $pdo->rollBack();
        $freshStatus = $fresh ? (string)$fresh['status'] : null;
        http_response_code(409); // conflict
        echo json_encode([
            "success" => false,
            "message" => "Không thể cập nhật (xung đột trạng thái). Vui lòng làm mới trang.",
            "currentStatus" => $freshStatus
        ]);
        exit;
    }

    // commit
    $pdo->commit();

    // Trả về thành công
    echo json_encode([
        "success" => true,
        "message" => "Cập nhật thành công",
        "oldStatus" => $currentStatus,
        "newStatus" => $newStatus
    ]);
    exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    // Không lộ quá nhiều chi tiết ra production; nhưng trả message ngắn để debug dev
    echo json_encode(["success" => false, "message" => "Lỗi CSDL. Vui lòng thử lại sau."]);
    exit;
} catch (Exception $ex) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $ex->getMessage()]);
    exit;
}
