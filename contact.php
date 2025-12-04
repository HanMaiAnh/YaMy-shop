<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Liên Hệ - YaMyShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <style>
        /* Style riêng cho contact */
        .contact-container {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            margin: 40px auto;
        }
        .contact-container h2 { text-align: center; margin-bottom: 25px; color: #333; font-size: 28px; }
        .contact-container input, 
        .contact-container textarea {
            width: 100%;
            padding: 12px 15px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .contact-container input:focus, 
        .contact-container textarea:focus { border-color: #ff7f50; outline: none; }
        .contact-container button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, #ff7f50, #ff4500);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        .contact-container button:hover { background: linear-gradient(90deg, #ff6347, #ff0000); }
        .contact-container .message {
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 16px;
            display: none;
            opacity: 0;
            transition: opacity 0.5s;
        }
        .contact-container .success { background: #d4edda; color: #155724; }
        .contact-container .error   { background: #f8d7da; color: #721c24; }

        @media (max-width: 600px) {
            .contact-container { padding: 20px; }
            .contact-container h2 { font-size: 24px; }
        }
    </style>
</head>
<body>

<?php include dirname(__DIR__) . '/includes/header.php'; ?>

<div class="contact-container">
    <h2>Liên hệ với chúng tôi</h2>

    <div id="responseMessage" class="message"></div>

    <form id="contactForm">
        <input type="text" name="name" placeholder="Tên của bạn" required>
        <input type="email" name="email" placeholder="Email của bạn" required>
        <input type="text" name="subject" placeholder="Tiêu đề" required>
        <textarea name="message" rows="5" placeholder="Nội dung" required></textarea>
        <button type="submit" id="submitBtn">Gửi Thư</button>
    </form>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
const form = document.getElementById('contactForm');
const btn  = document.getElementById('submitBtn');
const resp = document.getElementById('responseMessage');

function showMessage(message, status) {
    resp.textContent = message;
    resp.className = 'message ' + status;
    resp.style.display = 'block';
    resp.style.opacity = 0;
    setTimeout(() => { resp.style.opacity = 1; }, 50);
    // Ẩn dần sau 4 giây
    setTimeout(() => {
        resp.style.opacity = 0;
        setTimeout(() => { resp.style.display = 'none'; }, 500);
    }, 4000);
}

form.addEventListener('submit', function(e){
    e.preventDefault();
    btn.disabled = true;
    btn.textContent = 'Đang gửi...';

    const formData = new FormData(form);

    fetch('send_mail.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            showMessage(data.message, data.status === 'success' ? 'success' : 'error');
            btn.disabled = false;
            btn.textContent = 'Gửi Thư';
            if(data.status === 'success') form.reset();
        }).catch(err => {
            showMessage('Có lỗi xảy ra, vui lòng thử lại.', 'error');
            btn.disabled = false;
            btn.textContent = 'Gửi Thư';
        });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
