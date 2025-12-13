<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

/* ================== END FUNCTIONS INSERT ================== */

// === GIỎ HÀNG ===
$cart_count = !empty($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// === USER ===
$username = $_SESSION['username'] ?? 'Khách';
$user_logged_in = isset($_SESSION['user_id']);

// === LẤY DANH MỤC TỪ DATABASE ===
$sql = "SELECT * FROM categories ORDER BY parent_id ASC, sort_order ASC, name ASC";
$stmt = $pdo->query($sql);
$categories = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// === HÀM TẠO DANH MỤC CÂY (NHIỀU CẤP) ===
function buildCategoryTree($categories, $parent_id = 0) {
    $branch = [];
    foreach ($categories as $category) {
        if ((int)$category['parent_id'] === (int)$parent_id) {
            $children = buildCategoryTree($categories, $category['id']);
            if ($children) {
                $category['children'] = $children;
            }
            $branch[] = $category;
        }
    }
    return $branch;
}

$category_tree = buildCategoryTree($categories);

function renderCategoryMenu($categories) {
    $html = '';
    foreach ($categories as $cat) {
        if (!empty($cat['children'])) {
            $html .= '<li class="dropdown-submenu">
                        <a class="dropdown-item dropdown-toggle" href="#">' . htmlspecialchars($cat['name']) . '</a>
                        <ul class="dropdown-menu">';
            $html .= renderCategoryMenu($cat['children']);
            $html .= '</ul></li>';
        } else {
            $html .= '<li><a class="dropdown-item" href="' . BASE_URL . '/view/products.php?category=' . $cat['id'] . '">' 
                  . htmlspecialchars($cat['name']) . '</a></li>';
        }
    }
    return $html;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?? 'YaMy Shop - Cửa hàng thời trang' ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">

<style>
:root {
    --primary: #e91e63;
    --dark: #1a1a1a;
}
.navbar {
    background: #fff !important;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    padding: 0.75rem 0;
}
.navbar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 800;
    font-size: 1.5rem;
    color: var(--dark) !important;
    transition: 0.3s;
}
.logo-img {
    width: 48px; height: 48px; border-radius: 10px; object-fit: contain;
}

/* ---------- NAV LINKS ---------- */
.nav-link { 
    color:#555 !important; 
    font-weight:500; 
    border-radius:8px; 
    padding:0.5rem 1rem !important; 
    transition:0.3s; 
}
.nav-link:hover, .nav-link.active { 
    color: var(--primary) !important; 
    background: rgba(233,30,99,0.08); 
}

/* ---------- DROPDOWN ---------- */
.dropdown-menu {
    border-radius: 8px;
    border: none;
    box-shadow: 0 3px 12px rgba(0,0,0,0.15);
}
.dropdown-submenu {
    position: relative;
}
.dropdown-submenu .dropdown-menu {
    top: 0;
    left: 100%;
    margin-left: 0.1rem;
    margin-top: -0.25rem;
}

/* ---------- SEARCH BAR CĂN ĐỀU VỚI MENU ---------- */
.navbar-search {
    flex: 1;
    max-width: 420px;
    margin: 0 1.5rem;
}
.navbar-search .input-group {
    width: 100%;
}
.navbar-search .form-control {
    border-radius: 999px 0 0 999px;
    height: 40px;
    font-size: 0.95rem;
}
.navbar-search .btn {
    border-radius: 0 999px 999px 0;
    height: 40px;
    padding: 0 16px;
}

/* ---------- CART BADGE & USER ---------- */
.badge-cart {
    position: absolute;
    top: -6px;
    right: -8px;
    background: var(--primary);
    color: #fff;
    font-size: 0.65rem;
    border-radius: 50%;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.user-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg,#667eea,#764ba2);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: bold;
}

/* ---------- RESPONSIVE ---------- */
@media (max-width: 991.98px) {
    .navbar-search {
        order: 2;               /* đưa search xuống dưới menu khi mobile */
        width: 100%;
        max-width: 100%;
        margin: 0.75rem 0 0.5rem;
    }
    .navbar-nav {
        margin-top: 0.5rem;
    }
}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light sticky-top">
  <div class="container">
    <a class="navbar-brand" href="<?= BASE_URL ?>/view/index.php">
        <img src="<?= BASE_URL ?>/uploads/logoyamy.png" alt="YaMy Shop" class="logo-img">
        YaMy Shop
    </a>

    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <!-- THANH TÌM KIẾM (ở giữa, flex:1) -->
      <form class="d-flex align-items-center navbar-search my-2 my-lg-0" 
            role="search" 
            method="get" 
            action="<?= BASE_URL ?>/view/products.php">
          <div class="input-group">
              <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Tìm sản phẩm..."
                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
              >
              <button class="btn btn-outline-secondary" type="submit">
                  <i class="fas fa-search"></i>
              </button>
          </div>
      </form>

      <ul class="navbar-nav ms-auto align-items-center">

        <!-- MENU DANH MỤC
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= ($current_page ?? '') === 'category' ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-list"></i> Danh mục
          </a>
          <ul class="dropdown-menu">
              <?= renderCategoryMenu($category_tree) ?>
          </ul>
        </li> -->

        <li class="nav-item">
          <a class="nav-link <?= ($current_page ?? '') === 'products' ? 'active' : '' ?>" href="<?= BASE_URL ?>/view/products.php">
            <i class="fas fa-shirt"></i> Sản phẩm
          </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?= ($current_page ?? '') === 'contact' ? 'active' : '' ?>" 
                href="<?= BASE_URL ?>/view/contact.php">
                <i class="fas fa-headset"></i> Liên hệ
            </a>
        </li>

        <li class="nav-item">
          <a class="nav-link text-danger" href="<?= BASE_URL ?>/view/withlist.php">
            <i class="fas fa-heart"></i> Yêu thích
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link position-relative <?= ($current_page ?? '') === 'cart' ? 'active' : '' ?>" href="<?= BASE_URL ?>/view/cart.php">
            <i class="fas fa-shopping-cart"></i> Giỏ hàng
            <?php if ($cart_count > 0): ?>
                <span class="badge-cart"><?= $cart_count ?></span>
            <?php endif; ?>
          </a>
        </li>

        <!-- USER -->
        <li class="nav-item dropdown">
            <?php if ($user_logged_in): ?>
                <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                    <div class="user-avatar"><?= strtoupper(substr($username, 0, 2)) ?></div>
                    <span class="d-none d-md-inline"><?= htmlspecialchars($username) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm">
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/view/profile.php"><i class="fas fa-user me-2"></i> Hồ sơ</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/view/order_history.php"><i class="fas fa-box me-2"></i> Đơn hàng</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Đăng xuất</a></li>
                </ul>
            <?php else: ?>
                <a class="nav-link" href="<?= BASE_URL ?>/view/login.php">
                    <i class="fas fa-sign-in-alt"></i> Đăng nhập
                </a>
            <?php endif; ?>
        </li>

      </ul>
    </div>
  </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.dropdown-submenu').forEach(function (menu) {
    const submenu = menu.querySelector('.dropdown-menu');

    menu.addEventListener('mouseenter', function() {
        submenu.classList.add('show');
    });

    menu.addEventListener('mouseleave', function() {
        submenu.classList.remove('show');
    });
});

 // Hàm toast dùng chung toàn trang
window.showToast = function (message, type = 'success', timeout = 2500) {
    document.querySelectorAll('.custom-toast').forEach(t => t.remove());

    const wrap = document.createElement('div');
    wrap.className = 'custom-toast position-fixed top-0 start-50 translate-middle-x p-3';
    wrap.style.zIndex = '9999';

    const kind = type === 'warning' ? 'warning' : (type === 'danger' ? 'danger' : 'success');

    wrap.innerHTML = `
        <div class="alert alert-${kind} alert-dismissible fade show mb-0">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
        </div>
    `;
    document.body.appendChild(wrap);

    setTimeout(() => {
        wrap.style.transition = 'opacity .3s';
        wrap.style.opacity = '0';
        setTimeout(() => wrap.remove(), 300);
    }, timeout);
};
</script>

</body>
</html>
