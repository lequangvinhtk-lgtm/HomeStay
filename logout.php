<?php
/**
 * Đăng xuất tài khoản khách hàng
 */

require_once __DIR__ . '/includes/functions.php';

// Hủy bỏ toàn bộ session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Khởi tạo session mới chỉ để lưu thông báo flash
session_start();
set_flash('info', 'Bạn đã đăng xuất tài khoản thành công.');
redirect('/login.php');
