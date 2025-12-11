<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

function sendOtpMail($toEmail, $otp, $username) {
    $mail = new PHPMailer(true);

    try {
        // Cấu hình server Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // *** Đổi thành email thật của bạn ***
        $mail->Username   = 'NGUYENHUUTIN251105@gmail.com';     // gmail của bạn
        $mail->Password   = 'icvmanpzlrxhsyog';                 // MẬT KHẨU ỨNG DỤNG 16 KÝ TỰ (không có khoảng trắng)

        // Bảo mật + cổng
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;     // hoặc: 'tls'
        $mail->Port       = 587;

        $mail->CharSet    = 'UTF-8';
        $mail->isHTML(true);
        // $mail->SMTPDebug = 2; // Bật nếu muốn xem log chi tiết

        // From & To
        $mail->setFrom($mail->Username, 'clothing_store');
        $mail->addAddress($toEmail, $username);

        // Nội dung mail
        $mail->Subject = 'Ma OTP xac thuc tai khoan - clothing_store';
        $mail->Body    = "
            Xin chào <b>{$username}</b>,<br><br>
            Mã OTP xác thực tài khoản của bạn là: <b>{$otp}</b><br>
            Mã có hiệu lực trong 10 phút.<br><br>
            Cảm ơn bạn đã đăng ký tài khoản tại clothing_store.
        ";

        $mail->AltBody = "Xin chao {$username}. Ma OTP cua ban la: {$otp}. Ma co hieu luc trong 10 phut.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return $mail->ErrorInfo; // trả về lỗi để hiển thị
    }
}
