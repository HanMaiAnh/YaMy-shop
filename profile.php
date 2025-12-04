<?php
// view/profile.php — giao diện profile đẹp hơn
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Kiểm tra cột tồn tại
$existingCols = [];
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (is_array($cols)) $existingCols = $cols;
} catch (Exception $e) {
    error_log("Cannot show columns users: " . $e->getMessage());
}

$hasPhone = in_array('phone', $existingCols, true);
$hasAddress = in_array('address', $existingCols, true);

// Lấy user (chỉ các cột tồn tại)
$selectCols = ['id','username','email'];
if ($hasPhone) $selectCols[] = 'phone';
if ($hasAddress) $selectCols[] = 'address';
$selectSql = implode(', ', $selectCols);

try {
    $stmt = $pdo->prepare("SELECT {$selectSql} FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
    $user = false;
}

if (!$user) {
    include '../includes/header.php';
    echo '<div class="container py-5"><div class="alert alert-warning">Không tìm thấy thông tin người dùng. Vui lòng đăng nhập lại.</div></div>';
    include '../includes/footer.php';
    exit;
}

$username = $user['username'] ?? '';
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';
$address = $user['address'] ?? '';

include '../includes/header.php';
?>

<style>
/* Small extra styles to make profile look nicer */
.profile-wrapper { padding: 60px 0; }
.profile-card { border-radius: 12px; box-shadow: 0 6px 30px rgba(20,20,50,0.06); overflow: hidden; }
.avatar-circle {
    width: 120px; height: 120px; border-radius: 50%;
    display:flex; align-items:center; justify-content:center;
    font-weight:700; color:#fff; font-size:36px;
    background: linear-gradient(135deg,#667eea,#764ba2);
    box-shadow: 0 8px 24px rgba(118,75,162,0.18);
}
.info-label { font-size: .9rem; color: #6c757d; }
.section-title { font-weight:700; font-size:1.25rem; }
.btn-ghost { background: transparent; border: 1px solid rgba(0,0,0,0.06); }
@media (max-width: 767px) {
    .avatar-circle { width: 100px; height: 100px; font-size:28px; }
}
</style>

<div class="container profile-wrapper">
    <div class="section-header mb-4">
        <h1 class="fw-bold">Thông tin hồ sơ</h1>
        <?php if (!empty($_GET['updated'])): ?>
            <div class="alert alert-success">Cập nhật thông tin thành công!</div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>
    </div>

    <div class="card profile-card">
        <div class="row g-0">
            <!-- Left: Avatar & quick actions -->
            <div class="col-lg-4 p-4 d-flex align-items-center" style="background: linear-gradient(180deg,#fafafa,#ffffff);">
                <div class="w-100 text-center">
                    <?php
                        // Avatar as initials
                        $initials = '';
                        if ($username) {
                            $parts = preg_split('/\s+/', $username);
                            $initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
                        }
                    ?>
                    <div class="avatar-circle mb-3 mx-auto"><?= $initials ?: 'U' ?></div>
                    <h5 class="mb-0"><?= htmlspecialchars($username) ?></h5>
                    <p class="text-muted mb-3"><?= htmlspecialchars($email) ?></p>

                    <div class="d-grid gap-2">
                        <a href="order_history.php" class="btn btn-outline-primary btn-sm">Xem đơn hàng</a>
                        <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Đổi mật khẩu</button>
                    </div>
                </div>
            </div>

            <!-- Right: Form -->
            <div class="col-lg-8 p-5">
                <form method="post" action="update_profile.php" class="row g-3">
                    <div class="col-12">
                        <label class="form-label info-label">Tên đăng nhập</label>
                        <input class="form-control" value="<?= htmlspecialchars($username) ?>" disabled>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label info-label">Email</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email) ?>">
                    </div>

                    <?php if ($hasPhone): ?>
                    <div class="col-md-6">
                        <label class="form-label info-label">Số điện thoại</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>">
                    </div>
                    <?php endif; ?>

                    <?php if ($hasAddress): ?>
                    <div class="col-12">
                        <label class="form-label info-label">Địa chỉ</label>
                        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($address) ?>">
                    </div>
                    <?php endif; ?>

                    <div class="col-12 d-flex justify-content-end">
                        <a href="<?= BASE_URL ?>/view/index.php" class="btn btn-ghost me-2">Hủy</a>
                        <button type="submit" class="btn btn-primary">Cập nhật hồ sơ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal đổi mật khẩu (front-end); nếu muốn mình sẽ thêm backend change_password.php) -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="changePasswordForm" method="post" action="change_password.php">
      <div class="modal-header">
        <h5 class="modal-title">Đổi mật khẩu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
      </div>
      <div class="modal-body">
          <div class="mb-3">
              <label class="form-label">Mật khẩu hiện tại</label>
              <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
              <label class="form-label">Mật khẩu mới</label>
              <input type="password" name="new_password" class="form-control" minlength="6" required>
          </div>
          <div class="mb-3">
              <label class="form-label">Nhập lại mật khẩu mới</label>
              <input type="password" name="confirm_password" class="form-control" minlength="6" required>
          </div>
          <small class="text-muted">Mật khẩu tối thiểu 6 ký tự.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Đóng</button>
        <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
      </div>
    </form>
  </div>
</div>

<script>
/* (tối giản) kiểm tra client để tránh submit nhầm */
document.addEventListener('DOMContentLoaded', function(){
    const form = document.getElementById('changePasswordForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const np = form.querySelector('input[name="new_password"]').value;
            const cp = form.querySelector('input[name="confirm_password"]').value;
            if (np !== cp) {
                e.preventDefault();
                alert('Mật khẩu mới và xác nhận không khớp!');
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
