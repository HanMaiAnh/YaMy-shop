<?php
ini_set('session.cookie_path', '/');
session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ====== CHECK ADMIN ======
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../view/login.php");
    exit;
}

try {
    require_once __DIR__ . '/../config/db.php';
    $conn = $pdo; // $pdo từ db.php

    // ====== CÁC TRẠNG THÁI HỆ THỐNG ======
    $ALL_STATUSES = [
        'Chờ xác nhận',
        'Đang xử lý',
        'Đơn hàng đang được giao',
        'Đã giao hàng',
        'Hủy đơn hàng'
    ];
    $finalStatuses = ['Đã giao hàng', 'Hủy đơn hàng', 'Đã hủy'];

    // ====== LẤY INPUT SEARCH & FILTER ======
    $ordersPerPage = 10;
    $page = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;

    $qRaw = trim((string)($_GET['q'] ?? ''));
    $statusFilter = trim((string)($_GET['status'] ?? 'all'));

    // sanitize statusFilter (only allow known statuses or 'all')
    if ($statusFilter !== 'all' && !in_array($statusFilter, $ALL_STATUSES, true)) {
        $statusFilter = 'all';
    }

    /* =====================
       Build WHERE clauses using positional placeholders (?)
       so we can safely append LIMIT/OFFSET later.
       paramsOrdered is an array in the exact order of placeholders.
    ====================== */
    $whereParts = [];
    $paramsOrdered = [];

    if ($statusFilter !== 'all') {
        $whereParts[] = "status = ?";
        $paramsOrdered[] = $statusFilter;
    }

    if ($qRaw !== '') {
        // We'll search by recipient_name LIKE, by id if q is numeric, and by status LIKE (so searching "Đang xử lý" in q works)
        // Use OR group and append corresponding params in the right order
        $qLike = '%' . $qRaw . '%';
        $orParts = [];

        // recipient_name LIKE ?
        $orParts[] = "recipient_name LIKE ?";
        $paramsOrdered[] = $qLike;

        // status LIKE ?
        $orParts[] = "status LIKE ?";
        $paramsOrdered[] = $qLike;

        // id exact match (if user typed a number) - to support search by order id
        if (ctype_digit($qRaw)) {
            $orParts[] = "id = ?";
            $paramsOrdered[] = (int)$qRaw;
        }

        $whereParts[] = '(' . implode(' OR ', $orParts) . ')';
    }

    $whereSql = '';
    if (!empty($whereParts)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereParts);
    }

    // ====== COUNT TOTAL ======
    $countSql = "SELECT COUNT(*) FROM orders $whereSql";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($paramsOrdered);
    $totalOrders = (int)$countStmt->fetchColumn();
    $totalPages = $totalOrders > 0 ? (int)ceil($totalOrders / $ordersPerPage) : 1;
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $ordersPerPage;

    // ====== SELECT ORDERS (với LIMIT/OFFSET positional) ======
    $sql = "
        SELECT *
        FROM orders
        $whereSql
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    // copy params then append limit and offset (both integers)
    $paramsForSelect = $paramsOrdered;
    $paramsForSelect[] = (int)$ordersPerPage;
    $paramsForSelect[] = (int)$offset;

    $stmt = $conn->prepare($sql);
    $stmt->execute($paramsForSelect);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    // hiển thị lỗi nhẹ nhàng
    echo "<p style='color: red;'>Lỗi kết nối: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    $orders = [];
    $totalPages = 1;
    $page = 1;
    $totalOrders = 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Quản lý đơn hàng</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* (giữ nguyên CSS bạn đã dùng, rút gọn ở đây) */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
:root{--bg-main:#f7f5ff;--accent:#8E5DF5;--border-color:#e6dcf9;}
body{display:flex;background:var(--bg-main);color:#222;min-height:100vh;}
.sidebar{width:260px;background:#fff;height:100vh;padding:30px 20px;position:fixed;border-right:1px solid #ddd;}
.sidebar h3{font-size:22px;font-weight:700;margin-bottom:25px;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:12px;color:#333;text-decoration:none;border-radius:8px;margin-bottom:8px;transition:.25s;font-weight:500;font-size:15px;}
.sidebar a:hover{background:#f2e8ff;color:var(--accent);transform:translateX(4px);}
.sidebar .logout{color:#e53935;margin-top:20px;}
.content{margin-left:280px;padding:30px;width:100%;}
.page-title{font-size:26px;font-weight:700;margin-bottom:18px;color:#2b2b2b;}
.card{background:#fff;padding:18px;border-radius:12px;box-shadow:0 6px 18px rgba(110,100,120,0.06);}

/* FILTER BAR */
.filter-row{display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap;}
.input {padding:10px 14px;border-radius:8px;border:1px solid #e6e6e6;background:#fff;font-size:14px;min-width:320px;}
.select {padding:10px 14px;border-radius:8px;border:1px solid #e6e6e6;background:#fff;font-size:14px;min-width:220px;}
.btn-primary{background:#6C4CF0;color:#fff;padding:10px 14px;border-radius:8px;border:none;font-weight:700;cursor:pointer;}
.btn-danger{background:#f24545;color:#fff;padding:10px 14px;border-radius:8px;border:none;font-weight:700;cursor:pointer;}

/* TABLE */
.table-wrap{margin-top:12px;}
table{width:100%;border-collapse:collapse;background:transparent;border-radius:12px;overflow:hidden;margin-top:6px;}
thead th{background:var(--accent);color:#fff;padding:16px;text-align:left;font-weight:600;font-size:15px;}
tbody td{padding:14px;border-bottom:1px solid var(--border-color);vertical-align:middle;font-size:15px;color:#333;}
tbody tr:nth-child(even){background: #fbf7ff;}
tr:hover{background:#f7f2ff;}
.btn-edit {background:#03A9F4;padding:8px 12px;border-radius:8px;color:#fff;text-decoration:none;font-size:14px;display:inline-block;border:0;}
.btn-edit:hover {background:#0288D1;}

/* status select styles (same as you used) */
.order-status {
    display: block; width: 100%; max-width: 320px; height: 46px; line-height: 46px;
    padding: 0 44px 0 14px; box-sizing: border-box; font-size: 14px; color: #2a2a2a;
    background: #fff; border: 1.6px solid #e9defc; border-radius: 10px; cursor: pointer;
    -webkit-appearance: none; appearance: none; transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
    background-image: url("data:image/svg+xml;utf8,<svg fill='%238E5DF5' height='24' width='24' viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/></svg>");
    background-repeat: no-repeat; background-position: right 12px center; background-size: 18px;
}
.order-status:hover { border-color: #d0b6ff; background-color: #fbf7ff; }
.order-status:disabled { background: #f0f0f0; color: #8a8a8a; border-color: #e0e0e0; cursor: not-allowed; opacity: 0.95; }
.order-status.status-wait { border-left:6px solid #f2c249; }
.order-status.status-processing { border-left:6px solid #3fa2ff; }
.order-status.status-shipping { border-left:6px solid #8E5DF5; }
.order-status.status-done { border-left:6px solid #2ecc71; }
.order-status.status-cancel { border-left:6px solid #ff5b5b; }

/* Pagination */
.pagination{text-align:center;margin-top:18px;}
.pagination a{color:#fff;padding:8px 12px;border-radius:6px;background:#1a1a1a;margin:3px;transition:.3s;}
.pagination a:hover{background:#8E5DF5;}
.pagination a.active{background:#E91E63;}
@media (max-width:900px){ .sidebar{display:none;} .content{margin-left:20px;padding:20px;} .order-status{min-width:160px;} }
</style>
</head>

<body>
<div class="sidebar">
    <h3>YAMY ADMIN</h3>
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
  <h1 class="page-title">Quản lý đơn hàng</h1>

  <div class="card">
    <form method="get" action="orders.php" style="margin-bottom:12px;">
      <div class="filter-row">
        <input type="text" name="q" class="input" placeholder="Tìm đơn hàng" value="<?= htmlspecialchars($qRaw, ENT_QUOTES, 'UTF-8') ?>">
        <select name="status" class="select">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tất cả trạng thái</option>
          <?php foreach ($ALL_STATUSES as $st): ?>
            <option value="<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
              <?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-primary">Lọc</button>
        <button type="button" id="btnReset" class="btn-danger">Reset</button>
      </div>
    </form>

    <div class="table-wrap">
    <?php if (!empty($orders)): ?>
      <table>
        <thead>
        <tr>
          <th style="width:6%;">ID</th>
          <th style="width:18%;">Ngày đặt</th>
          <th style="width:14%;">Mã đơn hàng</th>
          <th style="width:22%;">Tên khách hàng</th>
          <th style="width:24%;">Trạng thái</th>
          <th style="width:12%;">Hành động</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order): 
            $statusDb = $order['status'] ?? 'Chờ xác nhận';
            if ($statusDb === 'Đã hủy' || $statusDb === 'đã hủy') {
                $status = 'Hủy đơn hàng';
            } else {
                $status = $statusDb;
            }
            $isFinal = in_array($status, $finalStatuses, true);
            $orderCode = 'DH' . str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT);
            $receiverName = $order['recipient_name'] ?? '--';
            // build allowed options same logic as before
            if ($status === 'Chờ xác nhận') {
                $allowed = ['Chờ xác nhận', 'Đang xử lý', 'Hủy đơn hàng'];
            } elseif ($status === 'Đang xử lý') {
                $allowed = ['Đang xử lý', 'Đơn hàng đang được giao', 'Hủy đơn hàng'];
            } elseif ($status === 'Đơn hàng đang được giao') {
                $allowed = ['Đơn hàng đang được giao', 'Đã giao hàng'];
            } elseif (in_array($status, ['Đã giao hàng','Hủy đơn hàng'], true)) {
                $allowed = [$status];
            } else {
                $allowed = ['Chờ xác nhận','Đang xử lý','Đơn hàng đang được giao','Đã giao hàng','Hủy đơn hàng'];
            }
        ?>
        <tr data-id="<?= htmlspecialchars((string)$order['id'], ENT_QUOTES, 'UTF-8') ?>">
          <td><?= htmlspecialchars((string)$order['id'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($order['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($receiverName, ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <select class="order-status <?= 'status-'.(preg_replace('/\s+|[^a-zA-Z0-9\-]/','', mb_strtolower($status))) ?>"
                    data-id="<?= htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8') ?>"
                    <?= $isFinal ? 'disabled' : '' ?>>
              <?php foreach ($allowed as $opt): ?>
                <option value="<?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>" <?= $opt === $status ? 'selected' : '' ?>>
                  <?= htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <a href="order_detail.php?id=<?= htmlspecialchars((string)$order['id'], ENT_QUOTES, 'UTF-8') ?>" class="btn-edit">Chi tiết</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="pagination" style="margin-top:14px;">
        <?php
        // preserve query params except page
        $qs = $_GET;
        unset($qs['page']);
        $base = $_SERVER['PHP_SELF'] . '?' . http_build_query($qs);
        if ($totalPages > 1) {
            if ($page > 2) {
                echo '<a href="' . htmlspecialchars($base . '&page=1') . '">1</a>';
                if ($page > 3) echo '<span style="display:inline-block;padding:8px 12px;color:#666;">...</span>';
            }
            $start = max(1, $page - 1);
            $end = min($totalPages, $page + 1);
            for ($i = $start; $i <= $end; $i++) {
                $cls = ($i == $page) ? 'active' : '';
                echo '<a href="' . htmlspecialchars($base . '&page=' . $i) . '" class="' . $cls . '">' . $i . '</a>';
            }
            if ($page < $totalPages - 2) {
                echo '<span style="display:inline-block;padding:8px 12px;color:#666;">...</span>';
            }
            if ($page < $totalPages - 1) {
                echo '<a href="' . htmlspecialchars($base . '&page=' . $totalPages) . '">' . $totalPages . '</a>';
            }
        }
        ?>
      </div>

    <?php else: ?>
      <p style="text-align:center;color:#888;margin-top:20px;">Không có đơn hàng nào.</p>
    <?php endif; ?>
    </div>
  </div>
</div>

<script>
/* Reset button */
document.getElementById('btnReset').addEventListener('click', function(){
    const form = this.closest('form');
    if (!form) return;
    form.querySelector('input[name="q"]').value = '';
    form.querySelector('select[name="status"]').value = 'all';
    form.submit();
});

/* Client-side: rebuild select & handle change (AJAX) */
const finalStatuses = ["Đã giao hàng", "Hủy đơn hàng", "Đã hủy"];

function computeAllowedOptions(currentStatus) {
  if (currentStatus === 'Chờ xác nhận') {
    return ['Chờ xác nhận', 'Đang xử lý', 'Hủy đơn hàng'];
  } else if (currentStatus === 'Đang xử lý') {
    return ['Đang xử lý', 'Đơn hàng đang được giao', 'Hủy đơn hàng'];
  } else if (currentStatus === 'Đơn hàng đang được giao') {
    return ['Đơn hàng đang được giao', 'Đã giao hàng'];
  } else if (currentStatus === 'Đã giao hàng' || currentStatus === 'Hủy đơn hàng' || currentStatus === 'Đã hủy') {
    return [currentStatus];
  } else {
    return ['Chờ xác nhận', 'Đang xử lý', 'Đơn hàng đang được giao', 'Đã giao hàng', 'Hủy đơn hàng'];
  }
}

function applyStatusClass(selectEl, status) {
  selectEl.classList.remove('status-wait','status-processing','status-shipping','status-done','status-cancel');
  if (status === 'Chờ xác nhận') selectEl.classList.add('status-wait');
  else if (status === 'Đang xử lý') selectEl.classList.add('status-processing');
  else if (status === 'Đơn hàng đang được giao') selectEl.classList.add('status-shipping');
  else if (status === 'Đã giao hàng') selectEl.classList.add('status-done');
  else if (status === 'Hủy đơn hàng' || status === 'Đã hủy') selectEl.classList.add('status-cancel');
}

function rebuildSelect(selectEl, currentStatus) {
  const allowed = computeAllowedOptions(currentStatus);
  selectEl.innerHTML = '';
  allowed.forEach(opt => {
    const o = document.createElement('option');
    o.value = opt;
    o.textContent = opt;
    if (opt === currentStatus) o.selected = true;
    selectEl.appendChild(o);
  });
  applyStatusClass(selectEl, currentStatus);
}

document.querySelectorAll('.order-status').forEach(select => {
  // ensure initial state matches allowed options from server
  rebuildSelect(select, select.value);

  select.addEventListener('change', function() {
    const selectEl = this;
    const id = selectEl.dataset.id;
    const newStatus = selectEl.value;
    const prevStatus = selectEl._prev || selectEl.getAttribute('data-prev') || null;
    selectEl._prev = newStatus;
    selectEl.disabled = true;

    fetch('/clothing_store/admin/update_order_status.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `id=${encodeURIComponent(id)}&status=${encodeURIComponent(newStatus)}`
    })
    .then(r => r.json().catch(()=>null))
    .then(data => {
      if (!data || !data.success) {
        const rollbackTo = data && data.currentStatus ? data.currentStatus : (prevStatus || selectEl.value);
        alert(data && data.message ? data.message : 'Cập nhật thất bại — rollback.');
        rebuildSelect(selectEl, rollbackTo);
        selectEl.disabled = finalStatuses.includes(rollbackTo);
        selectEl._prev = rollbackTo;
        return;
      }

      // success
      rebuildSelect(selectEl, newStatus);
      selectEl._prev = newStatus;
      const prevBg = selectEl.style.backgroundColor;
      selectEl.style.backgroundColor = '#dfffe0';
      setTimeout(()=> selectEl.style.backgroundColor = prevBg, 600);
      selectEl.disabled = finalStatuses.includes(newStatus);
    })
    .catch(err => {
      console.error(err);
      alert('Lỗi kết nối — đã rollback.');
      const rollbackTo = prevStatus || selectEl.value;
      rebuildSelect(selectEl, rollbackTo);
      selectEl.disabled = finalStatuses.includes(rollbackTo);
      selectEl._prev = rollbackTo;
    });
  });

  // store initial prev for safety
  select._prev = select.value;
});
</script>

</body>
</html>
