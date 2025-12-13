<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require dirname(__DIR__) . '/vendor/autoload.php'; // Composer autoload

function sendOtpMail($toEmail, $otp, $username)
{
    $mail = new PHPMailer(true);

    try {
        // Cấu hình SMTP Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // 🔥 THAY BẰNG GMAIL & APP PASSWORD CỦA BẠN
        $mail->Username   = 'yamyshop2323@gmail.com';      // Gmail shop
        $mail->Password   = 'kcgw aozk glrh rixd';         // App password Gmail

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Người GỬI (shop)
        $mail->setFrom('yamyshop2323@gmail.com', 'Website Contact');

        // Người NHẬN (user đăng ký)
        $mail->addAddress($toEmail);  // <-- chính là email user

        // Nội dung email
        $mail->isHTML(true);
        $mail->Subject = 'Mã OTP xác thực tài khoản - Website Contact';
        $mail->Body    = "
            <h2>Xin chào, <strong>{$username}</strong></h2>
            <p>Mã OTP của bạn là:</p>
            <h1 style='color:#667eea'>{$otp}</h1>
            <p>Mã có hiệu lực trong <strong>10 phút</strong>.</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return 'Không gửi được email. Lỗi: ' . $mail->ErrorInfo;
    }
}
