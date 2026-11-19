<?php
/**
 * Kết nối Cơ sở dữ liệu MySQL bằng PDO
 * Đơn giản, rõ ràng, chuẩn đồ án sinh viên năm 2
 */

$db_host = '127.0.0.1';
$db_name = 'hotel_booking';
$db_user = 'vinh';
$db_pass = 'Vinh@2007';
$db_port = 3306;

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

$options = [
    // Bật chế độ ném ngoại lệ khi có lỗi SQL để dễ bắt và xử lý
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Trả về dữ liệu dạng mảng kết hợp (associative array) theo tên cột
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Tắt giả lập prepared statement để MySQL thực thi trực tiếp prepared statements thật (chống SQL Injection tốt nhất)
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Thông báo lỗi thân thiện nếu sai thông tin đăng nhập hoặc chưa mở MySQL
    die('<div style="font-family: Arial, sans-serif; padding: 20px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 6px; margin: 20px;">'
        . '<h4>Lỗi kết nối Cơ sở dữ liệu!</h4>'
        . '<p>Vui lòng kiểm tra file <code>config/db.php</code> và đảm bảo MySQL đang chạy, CSDL <code>hotel_booking</code> đã được nạp.</p>'
        . '<p><small>Chi tiết lỗi kỹ thuật: ' . htmlspecialchars($e->getMessage()) . '</small></p>'
        . '</div>');
}
