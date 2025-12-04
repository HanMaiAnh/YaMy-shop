<footer style="background-color: #e0e0e0;" class="text-dark">
    <div class="container py-4">
        <div class="row text-center text-md-start">
            <!-- Về chúng tôi -->
            <div class="col-md-3 mb-4">
                <h5>Về chúng tôi</h5>
                <hr class="line mx-auto mx-md-0">
                <p class="small">YAMY Shop – Tự tin thể hiện phong cách, đẹp từng khoảnh khắc.</p>
                <div class="mt-3 d-flex justify-content-center justify-content-md-start gap-3">
                    <a href="#" class="text-dark"><i class="fab fa-facebook fa-lg"></i></a>
                    <a href="#" class="text-dark"><i class="fab fa-instagram fa-lg"></i></a>
                    <a href="#" class="text-dark"><i class="fab fa-youtube fa-lg"></i></a>
                    <a href="#" class="text-dark"><i class="fab fa-tiktok fa-lg"></i></a>
                </div>
            </div>

            <!-- Thông tin -->
            <div class="col-md-3 mb-4">
                <h5>Thông tin</h5>
                <hr class="line mx-auto mx-md-0">
                <ul class="list-unstyled small">
                    <li>Tuyển dụng & làm việc</li>
                    <li>Câu hỏi thường gặp</li>
                    <li>Sự kiện</li>
                    <li>Tin tức thời trang</li> 
                    <li>Chăm sóc khách hàng</li>
                </ul>
            </div>

            <!-- Chính sách -->
            <div class="col-md-3 mb-4">
                <h5>Chính sách</h5>
                <hr class="line mx-auto mx-md-0">
                <ul class="list-unstyled small">
                    <li><a href="#" class="text-dark">Bảo hành</a></li>
                    <li><a href="#" class="text-dark">Đổi hàng</a></li>
                    <li><a href="#" class="text-dark">Bảo mật</a></li>
                    <li><a href="#" class="text-dark">Vận chuyển</a></li>
                </ul>
            </div>

            <!-- Liên hệ -->
            <div class="col-md-3 mb-4">
                <h5>Liên hệ</h5>
                <hr class="line mx-auto mx-md-0">
                <ul class="list-unstyled small">
                    <li><i class="fas fa-map-marker-alt me-2"></i> Quang Trung, Gò Vấp, HCM</li>
                    <li><i class="fas fa-building me-2"></i> Công ty thời trang YaMy</li>
                    <li><i class="fas fa-envelope me-2"></i> YaMyshop2323@gmail.com</li>
                    <li><i class="fas fa-phone me-2"></i> 0393331359</li>
                </ul>
            </div>
        </div>
</footer>

<!-- Kết thúc Footer -->



<style>
    .line {
        border-top: 2px solid #212121;
        width: 40px;
        margin-bottom: 10px;
    }

    footer a:hover i {
        color: #000000;
        transform: scale(1.1);
    }

    footer a i {
        transition: all 0.3s ease;
    }

    footer a {
        text-decoration: none;
    }

    footer ul li {
        margin-bottom: 6px;
    }
</style>


<!-- POPUP MÃ GIẢM GIÁ - YaMyShop -->
<div id="voucher-popup-overlay" class="voucher-popup-overlay">
    <div id="voucher-popup" class="voucher-popup">
        <div class="container mt-4">
            <img src="../uploads/banner_voucher.png" class="img-fluid rounded shadow" alt="Voucher YaMyShop">
        </div>

        <button type="button" class="voucher-popup-close" aria-label="Đóng">&times;</button>
        <h3>Ưu đãi hôm nay tại YaMyShop</h3>
        <p>Nhập mã dưới đây để nhận ưu đãi khi thanh toán:</p>

        <ul class="voucher-list">
            <li><strong>YAMY20</strong> – Giảm 20% (tối đa 100.000₫) cho đơn từ 300.000₫</li>
            <li><strong>YAMY50K</strong> – Giảm 50.000₫ cho đơn từ 350.000₫</li>
            <li><strong>YAMYNEW</strong> – Giảm 12% cho mọi đơn (không yêu cầu tối thiểu)</li>
            <li><strong>YAMYFREESHIP</strong> – Giảm 30.000₫ phí ship cho đơn từ 250.000₫</li>
        </ul>

        <p class="voucher-note">Nhập mã ở bước <b>Giỏ hàng / Thanh toán</b> để áp dụng.</p>
        <button type="button" class="voucher-popup-btn-close">Đã hiểu</button>
    </div>
</div>

<style>
/* Overlay mờ */
.voucher-popup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 99999;
}

/* Hộp popup */
.voucher-popup {
    background: #fff;
    border-radius: 16px;
    max-width: 420px;
    width: 90%;
    padding: 20px 22px 18px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    position: relative;
    transform: translateY(-10px);
    opacity: 0;
    transition: all 0.25s ease;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

/* Khi hiển thị */
.voucher-popup-overlay.active {
    display: flex;
}
.voucher-popup-overlay.active .voucher-popup {
    opacity: 1;
    transform: translateY(0);
}

/* Nút X */
.voucher-popup-close {
    position: absolute;
    top: 6px;
    right: 10px;
    border: none;
    background: transparent;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
}

/* Nội dung */
.voucher-popup h3 {
    font-size: 20px;
    margin-bottom: 10px;
    text-align: center;
}
.voucher-popup p {
    margin-bottom: 8px;
    font-size: 14px;
}
.voucher-list {
    padding-left: 18px;
    margin-bottom: 10px;
    font-size: 14px;
}
.voucher-list li {
    margin-bottom: 4px;
}
.voucher-note {
    font-size: 13px;
    color: #555;
}

/* Nút đóng dưới */
.voucher-popup-btn-close {
    width: 100%;
    border: none;
    padding: 10px;
    border-radius: 999px;
    background: linear-gradient(90deg, #ff7f50, #ff4500);
    color: #fff;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    margin-top: 6px;
}
.voucher-popup-btn-close:hover {
    opacity: 0.9;
}
</style>

<script>
// Hiển thị popup khi vào website
document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById('voucher-popup-overlay');
    const popup   = document.getElementById('voucher-popup');
    const btnX    = document.querySelector('.voucher-popup-close');
    const btnOk   = document.querySelector('.voucher-popup-btn-close');

    if (!overlay || !popup) return;

    // 👉 Nếu muốn hiện MỖI LẦN mở web, bỏ điều kiện sessionStorage đi
    if (sessionStorage.getItem('yamy_voucher_popup_shown') === '1') {
        return;
    }

    // Hiện popup
    function openPopup() {
        overlay.classList.add('active');
        sessionStorage.setItem('yamy_voucher_popup_shown', '1'); // chỉ hiện 1 lần / 1 tab
    }

    // Đóng popup
    function closePopup() {
        overlay.classList.remove('active');
    }

    // Tự mở sau 1s (cho web load xong)
    setTimeout(openPopup, 1000);

    // Đóng khi bấm nút
    btnX && btnX.addEventListener('click', closePopup);
    btnOk && btnOk.addEventListener('click', closePopup);

    // Bấm ra ngoài cũng đóng
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closePopup();
        }
    });
});
</script>
<!-- END POPUP MÃ GIẢM GIÁ -->