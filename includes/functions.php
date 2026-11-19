<?php
/**
 * Các hàm tiện ích dùng chung cho toàn bộ dự án
 * Viết theo kiểu PHP thủ tục (procedural), ngắn gọn, dễ giải thích khi bảo vệ
 */

// Luôn khởi động session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Chống lỗi XSS: mã hóa các ký tự đặc biệt khi in ra HTML
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Định dạng số tiền sang định dạng Việt Nam Đồng (VD: 550.000 đ)
 */
function format_money($amount) {
    return number_format((float)$amount, 0, ',', '.') . ' đ';
}

/**
 * Định dạng ngày sang kiểu d/m/Y (VD: 25/11/2026)
 */
function format_date($date_str) {
    if (!$date_str) return '';
    $timestamp = strtotime($date_str);
    return date('d/m/Y', $timestamp);
}

/**
 * Định dạng ngày giờ sang kiểu d/m/Y H:i
 */
function format_datetime($datetime_str) {
    if (!$datetime_str) return '';
    $timestamp = strtotime($datetime_str);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * Chuyển hướng trang và dừng thực thi
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Lưu thông báo nhanh (Flash message) vào session
 * $type: success, danger, warning, info
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Lấy thông báo Flash và hiển thị ra HTML alert của Bootstrap rồi xóa khỏi session
 */
function display_flash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show my-3" role="alert">';
        echo e($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

/**
 * Kiểm tra xem người dùng đã đăng nhập chưa
 */
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

/**
 * Kiểm tra xem người dùng hiện tại có phải là Quản trị viên (admin) hay không
 */
function is_admin() {
    return is_logged_in() && !empty($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Lấy thông tin người dùng đang đăng nhập trong Session
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'          => $_SESSION['user_id'],
        'email'       => $_SESSION['email'] ?? '',
        'role'        => $_SESSION['role'] ?? 'customer',
        'customer_id' => $_SESSION['customer_id'] ?? null,
        'full_name'   => $_SESSION['full_name'] ?? 'Khách',
    ];
}

/**
 * Bắt buộc phải đăng nhập (dành cho các trang của khách hàng cần tài khoản)
 */
function require_login($redirect_to = '/login.php') {
    if (!is_logged_in()) {
        set_flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        redirect($redirect_to);
    }
}

/**
 * Bắt buộc quyền Quản trị viên (dành cho các trang trong /admin)
 */
function require_admin($redirect_to = '/admin/login.php') {
    if (!is_admin()) {
        set_flash('danger', 'Khu vực quản trị! Vui lòng đăng nhập bằng tài khoản Quản trị viên.');
        redirect($redirect_to);
    }
}

/**
 * Trả về mã HTML badge màu cho từng trạng thái của đơn đặt phòng
 */
function get_status_badge($status) {
    $badges = [
        'pending'     => '<span class="badge bg-warning text-dark">Chờ xác nhận</span>',
        'confirmed'   => '<span class="badge bg-info text-dark">Đã xác nhận</span>',
        'checked_in'  => '<span class="badge bg-primary">Đã nhận phòng</span>',
        'checked_out' => '<span class="badge bg-secondary">Đã trả phòng</span>',
        'completed'   => '<span class="badge bg-success">Hoàn thành</span>',
        'rejected'    => '<span class="badge bg-danger">Bị từ chối</span>',
        'cancelled'   => '<span class="badge bg-dark">Đã hủy</span>',
    ];
    return $badges[$status] ?? '<span class="badge bg-light text-dark">' . e($status) . '</span>';
}

/**
 * Dịch tên trạng thái đơn sang tiếng Việt thuần
 */
function get_status_text($status) {
    $texts = [
        'pending'     => 'Chờ xác nhận',
        'confirmed'   => 'Đã xác nhận',
        'checked_in'  => 'Đã nhận phòng',
        'checked_out' => 'Đã trả phòng',
        'completed'   => 'Hoàn thành',
        'rejected'    => 'Bị từ chối',
        'cancelled'   => 'Đã hủy',
    ];
    return $texts[$status] ?? $status;
}

/**
 * Dịch loại hình cơ sở lưu trú
 */
function get_property_type_text($type) {
    return $type === 'homestay' ? 'Homestay' : 'Khách sạn';
}

/**
 * Lấy cấu hình thông tin ngân hàng & thanh toán
 */
function get_payment_config() {
    $config_file = __DIR__ . '/../config/payment.php';
    if (file_exists($config_file)) {
        return require $config_file;
    }
    return [
        'bank_id'         => 'MB',
        'bank_name'       => 'MBBank (Ngân hàng TMCP Quân Đội)',
        'account_no'      => '0901234567',
        'account_name'    => 'NGUYEN QUANG VINH',
        'transfer_prefix' => 'ITACHIHOME',
        'custom_qr_image' => '',
    ];
}

/**
 * Tạo link ảnh mã QR thanh toán theo chuẩn VietQR tự động
 */
function get_vietqr_url($amount, $booking_code) {
    $config = get_payment_config();

    // Nếu có cài đặt ảnh QR tùy chỉnh thì ưu tiên hiển thị ảnh đó
    if (!empty($config['custom_qr_image']) && file_exists(__DIR__ . '/../assets/images/' . $config['custom_qr_image'])) {
        return '/assets/images/' . $config['custom_qr_image'];
    }

    $bank_id      = urlencode($config['bank_id'] ?? 'MB');
    $account_no   = urlencode($config['account_no'] ?? '0901234567');
    $account_name = urlencode($config['account_name'] ?? 'NGUYEN QUANG VINH');
    $prefix       = $config['transfer_prefix'] ?? 'ITACHIHOME';
    $add_info     = urlencode($prefix . ' ' . $booking_code);
    $amount_val   = (int)$amount;

    return "https://img.vietqr.io/image/{$bank_id}-{$account_no}-compact2.png?amount={$amount_val}&addInfo={$add_info}&accountName={$account_name}";
}
