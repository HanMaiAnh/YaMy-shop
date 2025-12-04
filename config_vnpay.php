<?php
// config_vnpay.php
date_default_timezone_set('Asia/Ho_Chi_Minh');

// LẤY THEO THÔNG TIN BẠN ĐƯA
$vnp_TmnCode    = "NQK8WMLY"; // Terminal ID
$vnp_HashSecret = "XPKMZ460VIKIPG22U3LBTCT68AYB7ZMK"; // Secret Key

// URL THANH TOÁN TEST (SANDBOX)
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";

// URL NHẬN KẾT QUẢ TỪ VNPAY (RETURN URL)
$vnp_Returnurl = "http://localhost/clothing_store/view/vnpay_return.php";
// NHỚ sửa "ten_project_cua_ban" đúng với folder của bạn, ví dụ:
// "http://localhost/clothing_store/view/vnpay_return.php"

// MÃ NGÂN HÀNG (NẾU MUỐN CỐ ĐỊNH 1 NGÂN HÀNG, CÒN KHÔNG THÌ ĐỂ TRỐNG)
$vnp_BankCode = "";
