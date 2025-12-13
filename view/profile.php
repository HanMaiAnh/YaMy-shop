<?php
// view/profile.php — giao diện hồ sơ kiểu Shopee
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

$hasPhone    = in_array('phone', $existingCols, true);
$hasAddress  = in_array('address', $existingCols, true);
$hasFullName = in_array('fullname', $existingCols, true);
$hasGender   = in_array('gender', $existingCols, true);
$hasBirthday = in_array('birthday', $existingCols, true); // dạng DATE hoặc VARCHAR

// Lấy user (chỉ các cột tồn tại)
$selectCols = ['id', 'username', 'email'];
if ($hasPhone)    $selectCols[] = 'phone';
if ($hasAddress)  $selectCols[] = 'address';
if ($hasFullName) $selectCols[] = 'fullname';
if ($hasGender)   $selectCols[] = 'gender';
if ($hasBirthday) $selectCols[] = 'birthday';
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

$username  = $user['username']   ?? '';
$email     = $user['email']      ?? '';
$phone     = $user['phone']      ?? '';
$address   = $user['address']    ?? '';
$fullname = $user['fullname']  ?? '';
$gender    = $user['gender']     ?? '';
$birthday  = $user['birthday']   ?? ''; // giả sử yyyy-mm-dd

// Tách ngày sinh ra ngày/tháng/năm nếu có
$dobDay = $dobMonth = $dobYear = '';
if ($birthday && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $birthday, $m)) {
    $dobYear  = $m[1];
    $dobMonth = $m[2];
    $dobDay   = $m[3];
}

include '../includes/header.php';
?>

<style>
.profile-wrapper {
    padding: 40px 0 60px;
}
.profile-box {
    background: #fff;
    border-radius: 4px;
    border: 1px solid #f0f0f0;
}
.profile-header {
    border-bottom: 1px solid #f5f5f5;
    padding: 20px 24px;
}
.profile-header h2 {
    font-size: 20px;
    margin: 0;
}
.profile-header p {
    margin: 4px 0 0;
    color: #999;
    font-size: 13px;
}
.profile-body {
    padding: 24px;
}
.profile-row {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
}
.profile-row-label {
    flex: 0 0 160px;
    color: #757575;
    font-size: 14px;
}
.profile-row-control {
    flex: 1;
}
.profile-row-control input[type="text"],
.profile-row-control input[type="email"],
.profile-row-control select {
    max-width: 400px;
}
.profile-avatar-block {
    border-right: 1px solid #f5f5f5;
    padding: 24px;
    text-align: center;
}
.profile-avatar-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #ee4d2d;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 28px;
    margin: 0 auto 12px;
}
@media (max-width: 767px) {
    .profile-row {
        flex-direction: column;
        align-items: flex-start;
    }
    .profile-row-label {
        margin-bottom: 4px;
    }
    .profile-avatar-block {
        border-right: none;
        border-top: 1px solid #f5f5f5;
    }
}

/* ========= NÚT MÀU CHUNG ========== */

/* Tất cả btn-primary (Lưu, Đổi mật khẩu) */
.btn-primary {
    background-color: #d62b70 !important;
    border-color: #d62b70 !important;
    color: #fff !important;
}
.btn-primary:hover,
.btn-primary:focus {
    background-color: #bf255f !important;
    border-color: #bf255f !important;
    color: #fff !important;
}

/* Tất cả btn-outline-secondary (Hủy, Đổi mật khẩu, Đóng) */
.btn-outline-secondary {
    color: #d62b70 !important;
    border-color: #d62b70 !important;
    background-color: transparent !important;
}
.btn-outline-secondary:hover,
.btn-outline-secondary:focus {
    background-color: #d62b70 !important;
    border-color: #d62b70 !important;
    color: #fff !important;
}
</style>

<div class="container profile-wrapper">
    <!-- CHỈ GIỮ LẠI THÔNG BÁO SESSION -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="profile-box">
        <div class="row g-0">

            <!-- CỘT AVATAR & ĐỔI MẬT KHẨU – BÊN TRÁI -->
            <div class="col-lg-3">
                <div class="profile-avatar-block h-100 d-flex flex-column justify-content-center">
                    <?php
                        $initials = '';
                        if ($username) {
                            $parts = preg_split('/\s+/', $username);
                            $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                        }
                    ?>
                    <div class="profile-avatar-circle"><?= $initials ?: 'U' ?></div>
                    <div class="mt-2 mb-3"><?= htmlspecialchars($username) ?></div>
                    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                        Đổi mật khẩu
                    </button>
                </div>
            </div>

            <!-- CỘT FORM – BÊN PHẢI -->
            <div class="col-lg-9">
                <div class="profile-header">
                    <h2>Hồ Sơ Của Tôi</h2>
                    <p>Quản lý thông tin hồ sơ để bảo mật tài khoản</p>
                </div>
                <div class="profile-body">
                    <form method="post" action="update_profile.php">
                        <!-- Tên đăng nhập -->
                        <div class="profile-row">
                            <div class="profile-row-label">Tên đăng nhập</div>
                            <div class="profile-row-control">
                                <input class="form-control" value="<?= htmlspecialchars($username) ?>" disabled>
                                <small class="text-muted">Tên Đăng nhập chỉ có thể thay đổi một lần.</small>
                            </div>
                        </div>

                        <!-- Tên hiển thị -->
                        <div class="profile-row">
                            <div class="profile-row-label">Họ và tên</div>
                            <div class="profile-row-control">
                                <input type="text" name="fullname" class="form-control"
                                       value="<?= htmlspecialchars($fullname ?: $username) ?>">
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="profile-row">
                            <div class="profile-row-label">Email</div>
                            <div class="profile-row-control d-flex align-items-center gap-2">
                                <input type="email" name="email" class="form-control"
                                       style="max-width: 260px"
                                       value="<?= htmlspecialchars($email) ?>" required>
                            </div>
                        </div>

                        <!-- Số điện thoại -->
                        <?php if ($hasPhone): ?>
                        <div class="profile-row">
                            <div class="profile-row-label">Số điện thoại</div>
                            <div class="profile-row-control d-flex align-items-center gap-2">
                                <input type="text" name="phone" class="form-control"
                                       style="max-width: 260px"
                                       value="<?= htmlspecialchars($phone) ?>">
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Giới tính -->
                        <div class="profile-row">
                            <div class="profile-row-label">Giới tính</div>
                            <div class="profile-row-control">
                                <?php $g = strtolower($gender); ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" id="gender_male" value="male"
                                        <?= ($g === 'male' || $g === 'nam') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="gender_male">Nam</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" id="gender_female" value="female"
                                        <?= ($g === 'female' || $g === 'nữ' || $g === 'nu') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="gender_female">Nữ</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="gender" id="gender_other" value="other"
                                        <?= ($g === 'other' || $g === 'khác' || $g === 'khac') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="gender_other">Khác</label>
                                </div>
                            </div>
                        </div>

                        <!-- Ngày sinh -->
                        <div class="profile-row">
                            <div class="profile-row-label">Ngày sinh</div>
                            <div class="profile-row-control">
                                <div class="d-flex gap-2">
                                    <select class="form-select" name="dob_day" style="max-width: 120px">
                                        <option value="">Ngày</option>
                                        <?php for ($d = 1; $d <= 31; $d++): ?>
                                            <option value="<?= $d ?>" <?= ((int)$dobDay === $d) ? 'selected' : '' ?>>
                                                <?= $d ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <select class="form-select" name="dob_month" style="max-width: 120px">
                                        <option value="">Tháng</option>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?= $m ?>" <?= ((int)$dobMonth === $m) ? 'selected' : '' ?>>
                                                <?= $m ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <select class="form-select" name="dob_year" style="max-width: 140px">
                                        <option value="">Năm</option>
                                        <?php
                                        $currentYear = (int)date('Y');
                                        for ($y = $currentYear; $y >= 1900; $y--): ?>
                                            <option value="<?= $y ?>" <?= ((int)$dobYear === $y) ? 'selected' : '' ?>>
                                                <?= $y ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Địa chỉ -->
                        <?php if ($hasAddress): ?>
                        <div class="profile-row">
                            <div class="profile-row-label">Địa chỉ</div>
                            <div class="profile-row-control">
                                <input type="text" name="address" class="form-control"
                                       value="<?= htmlspecialchars($address) ?>">
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Nút -->
                        <div class="profile-row">
                            <div class="profile-row-label"></div>
                            <div class="profile-row-control">
                                <button type="submit" class="btn btn-primary">Lưu</button>
                                <a href="<?= BASE_URL ?>/view/index.php" class="btn btn-outline-secondary ms-2">Hủy</a>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal đổi mật khẩu -->
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
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
        <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
      </div>
    </form>
  </div>
</div>

<script>
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
