<?php
/**
 * Cấu hình Thông tin Ngân hàng & Mã QR Thanh toán (VietQR)
 * Bạn có thể dễ dàng thay đổi số tài khoản, tên ngân hàng và chủ tài khoản bên dưới
 */

return [
    // Mã ngân hàng theo chuẩn VietQR (MB, VCB, TCB, ACB, TPB, VPB, BIDV, CTG, OCB, VIB...)
    'bank_id'      => 'MB', 
    
    // Tên ngân hàng hiển thị
    'bank_name'    => 'MBBank (Ngân hàng TMCP Quân Đội)',
    
    // Số tài khoản thụ hưởng
    'account_no'   => '0379542640',
    
    // Tên chủ tài khoản (Viết hoa không dấu)
    'account_name' => 'LE QUANG VINH',

    // Cú pháp nội dung chuyển khoản tiền tố (VD: ITACHIHOME BK...)
    'transfer_prefix' => 'ITACHIHOME',

    // Ảnh QR tĩnh dự phòng (nếu bạn muốn dùng ảnh chụp mã QR từ app ngân hàng)
    // Lưu file ảnh vào: assets/images/qr_custom.jpg hoặc để rỗng để dùng VietQR tự động
    'custom_qr_image' => '',
];
