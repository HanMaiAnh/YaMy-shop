<?php
if (isset($_POST['tendangnhap']) == true) {
    $tendangnhap = $_POST['tendangnhap'];
    $matkhau = $_POST['matkhau'];
    $hoten = $_POST['hoten'];
    $email = $_POST['email'];
    $ngaysinh = $_POST['ngaysinh'];
    $phai = $_POST['phai'];

    $conn = new PDO("mysql:host=localhost;dbname=streetsoul_store999;charset=utf8","root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql ="INSERT INTO users SET tendangnhap =?, matkhau =?, hoten =?, email =?, ngaysinh =?, phai =?";
    $smmt = $conn->prepare($sql);
    $smmt->execute([$tendangnhap, $matkhau, $hoten, $email, $ngaysinh, $phai]);
    header("Location: /yamy_shop/index.php");
exit;

exit;

}
?>


<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">

<form style="width: 50%; margin: auto; margin-top: 50px;" class="border border-primary border-2 rounded p-2" method="POST">
  <div class="mb-3">
    <label for="tendangnhap" class="form-label">Tên đăng nhập</label>
    <input type="text" class="form-control" id="tendangnhap" name="tendangnhap" placeholder="Nhập tên đăng nhập từ 6-20 ký tự">
    <div id="LoiTenDangNhap" class="form-text">Lỗi tên đăng nhập</div>
  </div>
  <div class="mb-3">
    <label for="matkhau" class="form-label">Mật khẩu</label>
    <input type="password" class="form-control" id="matkhau" name="matkhau" placeholder="Nhập mật khẩu từ 6-20 ký tự">
  </div>
  <div class="mb-3">
    <label for="hoten" class="form-label">Họ tên</label>
    <input type="text" class="form-control" id="hoten" name="hoten" placeholder="Nhập họ tên của bạn">
    <div id="LoiHoTen" class="form-text">Lỗi họ tên</div>
  </div>
  <div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input type="email" class="form-control" id="email" name="email" placeholder="Nhập email của bạn">
    <div id="LoiEmail" class="form-text">Lỗi email</div>
  </div>
  <div class="mb-3">
    <label for="ngaysinh" class="form-label">Ngày sinh</label>
    <input type="date" class="form-control" id="ngaysinh" name="ngaysinh" placeholder="Nhập ngày sinh của bạn">
    <div id="LoiNgaySinh" class="form-text">Lỗi khi nhập ngày sinh</div>
  </div>
  <label class="form-label">Giới tính</label>
  <div class="form-check">
  <input class="form-check-input" type="radio" name="phai" value="" id="nam" value="1">
  <label class="form-check-label" for="nam">
    Nam
  </label>
</div>
<div class="form-check">
  <input class="form-check-input" type="radio" name="phai" value="" id="nu" value="0" checked>
  <label class="form-check-label" for="nu">
    Nữ
  </label>
</div>
  <button type="submit" class="btn btn-primary">Đăng ký</button>
</form>