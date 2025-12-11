<?php
// includes/header.php (sửa theo yêu cầu)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// === GIỎ HÀNG ===
$cart_count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        if (is_array($item) && isset($item['quantity'])) {
            $cart_count += (int)$item['quantity'];
        } elseif (is_numeric($item)) {
            $cart_count += (int)$item;
        }
    }
}

// === USER ===
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Khách';
$user_logged_in = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($page_title ?? 'YaMy Shop - Cửa hàng thời trang') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<style>
:root { --primary: #e91e63; --dark: #1a1a1a; }

.navbar { box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 0.75rem 0; background: white !important; }
.navbar-brand { display: flex; align-items: center; gap: 12px; font-weight: 800; font-size: 1.5rem; color: var(--dark) !important; text-decoration: none; transition: all 0.3s; }
.navbar-brand:hover { color: var(--primary) !important; transform: scale(1.02); }
.logo-img { width: 50px; height: 50px; object-fit: contain; border-radius: 8px; box-shadow: 0 2px 8px rgba(233,30,99,0.2); transition: transform 0.3s; }
.logo-img:hover { transform: rotate(5deg) scale(1.1); }

.nav-link { color: #555 !important; font-weight: 500; padding: 0.5rem 1rem !important; border-radius: 8px; transition: all 0.3s; position: relative; }
.nav-link:hover, .nav-link.active { color: var(--primary) !important; background: rgba(233,30,99,0.08); }
.nav-link i { font-size: 1.1rem; }

.badge-cart { position: absolute; top: -8px; right: -8px; font-size: 0.65rem; padding: 0.25em 0.5em; background: var(--primary); color: white; border-radius: 50%; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; animation: pulse 2s infinite; }
@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(233,30,99,0.7);} 70% { box-shadow:0 0 0 8px rgba(233,30,99,0);} 100% { box-shadow:0 0 0 0 rgba(233,30,99,0);} }

.user-menu .dropdown-toggle { display: flex; align-items: center; gap: 8px; color: #555; font-weight: 500; }
.user-menu .dropdown-toggle::after { display: none; }
.user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg,#667eea,#764ba2); display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:0.9rem; }

.search-input { width:180px; }
@media (max-width:768px) {
    .navbar-brand { font-size: 1.3rem; }
    .logo-img { width: 40px; height: 40px; }
    .search-input { width:120px; }
}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
<div class="container">
    <!-- Logo -->
    <a class="navbar-brand" href="<?= BASE_URL ?>/view/index.php">
        <img src="<?= upload('logoyamy.png') ?>" alt="YaMy Shop" class="logo-img">
        YaMy Shop
    </a>

    <!-- Toggle mobile -->
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
        <!-- Thanh tìm kiếm -->
        <form action="<?= BASE_URL ?>/view/products.php" method="get" class="d-flex me-3">
            <input type="text" name="search" class="form-control search-input" placeholder="Tìm sản phẩm..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <button class="btn btn-primary ms-2" type="submit"><i class="fas fa-search"></i></button>
        </form>

        <!-- Menu chính -->
        <ul class="navbar-nav ms-auto align-items-center">

            <!-- DANH MỤC SẢN PHẨM (các link ví dụ, bạn có thể đổi category param tuỳ ý) -->
            <li class="nav-item"><a class="nav-link <?= ($current_page ?? '')==='ao-thun'?'active':'' ?>" href="<?= BASE_URL ?>/view/products.php?category=ao-thun">Áo Thun</a></li>
            <li class="nav-item"><a class="nav-link <?= ($current_page ?? '')==='ao-khoac'?'active':'' ?>" href="<?= BASE_URL ?>/view/products.php?category=ao-khoac">Áo Khoác</a></li>
            <li class="nav-item"><a class="nav-link <?= ($current_page ?? '')==='ao-so-mi'?'active':'' ?>" href="<?= BASE_URL ?>/view/products.php?category=ao-so-mi">Áo Sơ Mi</a></li>
            <li class="nav-item"><a class="nav-link <?= ($current_page ?? '')==='quan-jeans'?'active':'' ?>" href="<?= BASE_URL ?>/view/products.php?category=quan-jeans">Quần Jeans</a></li>
            <li class="nav-item"><a class="nav-link <?= ($current_page ?? '')==='quan-short'?'active':'' ?>" href="<?= BASE_URL ?>/view/products.php?category=quan-short">Quần Short</a></li>

            <!-- Trang chủ -->
            <li class="nav-item"><a class="nav-link <?= ($current_page ?? '')==='home'?'active':'' ?>" href="<?= BASE_URL ?>/view/index.php"><i class="fas fa-home"></i></a></li>

            <!-- Yêu thích -->
            <li class="nav-item"><a class="nav-link text-danger" href="<?= BASE_URL ?>/view/withlist.php"><i class="fas fa-heart"></i></a></li>

            <!-- Giỏ hàng -->
            <li class="nav-item">
                <a class="nav-link position-relative" href="<?= BASE_URL ?>/view/cart.php">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if($cart_count>0): ?>
                        <span class="badge-cart"><?= (int)$cart_count ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <!-- Tài khoản -->
            <li class="nav-item dropdown user-menu">
                <?php if($user_logged_in): ?>
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar"><?= strtoupper(substr(htmlspecialchars($username),0,2)) ?></div>
                        <span class="d-none d-md-inline ms-1"><?= htmlspecialchars($username) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/view/profile.php"><i class="fas fa-user me-2"></i> Hồ sơ</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/view/order_history.php"><i class="fas fa-box me-2"></i> Đơn hàng</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Đăng xuất</a></li>
                    </ul>
                <?php else: ?>
                    <a class="nav-link" href="<?= BASE_URL ?>/view/login.php"><i class="fas fa-sign-in-alt"></i> Đăng nhập</a>
                <?php endif; ?>
            </li>

        </ul>
    </div>
</div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
