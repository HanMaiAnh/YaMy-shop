<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS cat_name
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$wishlist = $stmt->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <h2 class="text-center mb-4"><i class="fas fa-heart text-danger"></i> Danh sách yêu thích</h2>
    <div class="row g-4">
        <?php if (empty($wishlist)): ?>
            <p class="text-center text-muted">Bạn chưa yêu thích sản phẩm nào.</p>
        <?php else: ?>
            <?php foreach ($wishlist as $p): ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm position-relative">
                        <a href="product.php?id=<?= $p['id'] ?>">
                            <img src="<?= upload($p['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['name']) ?>" style="height:220px;object-fit:cover;">
                        </a>
                        <a href="product.php?id=<?= $p['id'] ?>&wishlist=remove" 
                           class="position-absolute top-0 end-0 m-2 text-danger fs-4">
                            <i class="fas fa-heart"></i>
                        </a>
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title"><?= htmlspecialchars($p['name']) ?></h6>
                            <p class="text-muted small mb-1"><?= htmlspecialchars($p['cat_name']) ?></p>
                            <div class="mt-auto">
                                <span class="fw-bold text-danger"><?= number_format($p['final_price']) ?>₫</span>
                                <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-outline-danger btn-sm w-100 mt-2">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.card {
    transition: transform .2s ease;
}
.card:hover {
    transform: translateY(-5px);
}
</style>

<?php include '../includes/footer.php'; ?>
