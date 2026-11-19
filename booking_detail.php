<?php
/**
 * Chi tiết Đơn đặt phòng dành cho Khách hàng
 * Kèm chức năng HỦY ĐƠN (chỉ cho phép khi status = 'pending' và hôm nay < ngày nhận phòng)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($booking_id <= 0) {
    set_flash('danger', 'Đơn đặt phòng không hợp lệ.');
    redirect('/my_bookings.php');
}

// Lấy customer_id của người dùng hiện tại
$stmtCust = $pdo->prepare("SELECT id FROM customers WHERE user_id = ?");
$stmtCust->execute([$user['id']]);
$customer_id = $stmtCust->fetchColumn();

// BẢO MẬT: Khách hàng chỉ xem được đơn của chính mình; Quản trị viên có thể xem chi tiết
if (is_admin()) {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               r.room_number, r.capacity, r.amenities as room_amenities, r.image as room_image,
               p.name as property_name, p.type as property_type, p.address as property_address, p.city as property_city,
               rt.name as room_type_name
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN properties p ON r.property_id = p.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               r.room_number, r.capacity, r.amenities as room_amenities, r.image as room_image,
               p.name as property_name, p.type as property_type, p.address as property_address, p.city as property_city,
               rt.name as room_type_name
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN properties p ON r.property_id = p.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.id = ? AND b.customer_id = ?
    ");
    $stmt->execute([$booking_id, $customer_id]);
}
$booking = $stmt->fetch();

if (!$booking) {
    set_flash('danger', 'Không tìm thấy đơn đặt phòng hoặc bạn không có quyền xem đơn này!');
    redirect('/my_bookings.php');
}

$page_title = 'Đơn ' . $booking['booking_code'];

// Lấy thông tin thanh toán (nếu đã có)
$stmtPayment = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ?");
$stmtPayment->execute([$booking['id']]);
$payment = $stmtPayment->fetch();

// Xử lý HỦY ĐƠN (POST) theo quy tắc nghiệp vụ 5.1
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_cancel'])) {
    $today = date('Y-m-d');

    // Kiểm tra 2 điều kiện bắt buộc:
    // 1. Trạng thái phải là 'pending'
    // 2. Ngày hiện tại còn trước ngày nhận phòng (today < check_in)
    if ($booking['status'] !== 'pending') {
        set_flash('danger', 'Không thể hủy! Chỉ những đơn ở trạng thái "Chờ xác nhận" mới có thể hủy.');
    } elseif ($today >= $booking['check_in']) {
        set_flash('danger', 'Không thể hủy! Đã đến hoặc quá ngày nhận phòng (' . format_date($booking['check_in']) . ').');
    } else {
        $stmtCancel = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND customer_id = ?");
        $stmtCancel->execute([$booking['id'], $customer_id]);
        
        set_flash('success', 'Bạn đã hủy đơn đặt phòng thành công.');
        redirect('/booking_detail.php?id=' . $booking['id']);
    }
}

// Kiểm tra xem đơn hiện tại có đủ điều kiện để hủy hay không
$today = date('Y-m-d');
$can_cancel = ($booking['status'] === 'pending' && $today < $booking['check_in']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="small opacity-75 mb-1"><a href="/my_bookings.php" class="text-white text-decoration-none"><i class="bi bi-arrow-left me-1"></i> Quay lại danh sách đơn</a></div>
            <h2 class="fw-bold mb-0">Mã đơn: <?= e($booking['booking_code']) ?></h2>
        </div>
        <div>
            <span class="fs-5"><?= get_status_badge($booking['status']) ?></span>
        </div>
    </div>
</div>

<div class="container py-2 pb-5">
    <div class="row g-4">
        <!-- Cột trái: Chi tiết thông tin lưu trú & phòng -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-door-open me-2"></i>Thông tin phòng & Cơ sở lưu trú
                    </h5>
                    <span class="badge <?= $booking['property_type'] === 'homestay' ? 'bg-success' : 'bg-primary' ?>">
                        <?= get_property_type_text($booking['property_type']) ?>
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="row align-items-center mb-4 pb-3 border-bottom">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <?php $img_src = !empty($booking['room_image']) ? '/assets/images/' . e($booking['room_image']) : '/assets/images/no_image.jpg'; ?>
                            <img src="<?= $img_src ?>" alt="Phòng" class="rounded w-100" style="height: 140px; object-fit: cover;">
                        </div>
                        <div class="col-md-8">
                            <h4 class="fw-bold mb-1"><?= e($booking['property_name']) ?></h4>
                            <p class="text-muted small mb-2"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($booking['property_address']) ?>, <?= e($booking['property_city']) ?></p>
                            <div class="fw-semibold text-primary fs-5 mb-1">
                                Phòng: <?= e($booking['room_number']) ?> <small class="text-muted fs-6">(<?= e($booking['room_type_name']) ?>)</small>
                            </div>
                            <?php if (!empty($booking['room_amenities'])): ?>
                                <small class="text-muted"><i class="bi bi-stars text-warning me-1"></i><?= e($booking['room_amenities']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Thời gian & Chi tiết đặt -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="text-muted small">Thời gian nhận phòng (Check-in)</div>
                                <div class="fs-5 fw-bold text-dark mt-1"><?= format_date($booking['check_in']) ?></div>
                                <small class="text-muted">Từ 14:00</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="text-muted small">Thời gian trả phòng (Check-out)</div>
                                <div class="fs-5 fw-bold text-dark mt-1"><?= format_date($booking['check_out']) ?></div>
                                <small class="text-muted">Trước 12:00 trưa</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="text-muted small">Số đêm:</div>
                            <div class="fw-bold"><?= $booking['nights'] ?> đêm</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Số khách lưu trú:</div>
                            <div class="fw-bold"><?= $booking['guests'] ?> người</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Thời gian đặt:</div>
                            <div class="fw-bold"><?= format_datetime($booking['created_at']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Thông tin liên hệ nhận phòng -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0"><i class="bi bi-person-lines-fill text-primary me-2"></i>Thông tin người nhận phòng</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small">Họ và tên:</span>
                            <div class="fw-bold fs-6"><?= e($booking['contact_name']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Số điện thoại:</span>
                            <div class="fw-bold fs-6"><?= e($booking['contact_phone']) ?></div>
                        </div>
                        <div class="col-12">
                            <span class="text-muted small">Ghi chú đặc biệt:</span>
                            <div class="text-secondary"><?= !empty($booking['note']) ? nl2br(e($booking['note'])) : '<em>Không có ghi chú</em>' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nếu bị từ chối thì hiển thị lý do -->
            <?php if ($booking['status'] === 'rejected' && !empty($booking['reject_reason'])): ?>
                <div class="alert alert-danger p-4">
                    <h6 class="fw-bold"><i class="bi bi-exclamation-octagon-fill me-2"></i>Lý do từ chối yêu cầu đặt phòng:</h6>
                    <p class="mb-0 mt-2"><?= e($booking['reject_reason']) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cột phải: Thanh toán & Nút Hủy đơn -->
        <div class="col-lg-4">
            <!-- Thẻ thanh toán -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0"><i class="bi bi-cash-stack text-success me-2"></i>Chi phí lưu trú</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-2 small">
                        <span>Đơn giá phòng:</span>
                        <span><?= format_money($booking['price_per_night']) ?>/đêm</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span>Số đêm (<?= $booking['nights'] ?> đêm):</span>
                        <span><?= format_money($booking['total_amount']) ?></span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold">Tổng thanh toán:</span>
                        <span class="fw-bold fs-4 text-danger"><?= format_money($booking['total_amount']) ?></span>
                    </div>

                    <!-- Trạng thái thanh toán thực tế -->
                    <div class="p-3 rounded-3 <?= $payment ? 'bg-success bg-opacity-10 text-success border border-success' : 'bg-warning bg-opacity-10 text-dark border border-warning' ?>">
                        <?php if ($payment): ?>
                            <div class="fw-bold"><i class="bi bi-check-circle-fill me-1"></i> ĐÃ THANH TOÁN ĐỦ</div>
                            <small class="d-block mt-1">Hình thức: <?= $payment['method'] === 'cash' ? 'Tiền mặt tại quầy' : 'Chuyển khoản ngân hàng' ?></small>
                            <small class="d-block">Thời gian: <?= format_datetime($payment['paid_at']) ?></small>
                        <?php else: ?>
                            <div class="fw-bold"><i class="bi bi-clock-history me-1"></i> CHƯA THANH TOÁN</div>
                            <small class="d-block mt-1">Thanh toán bằng tiền mặt hoặc chuyển khoản khi nhận phòng hoặc theo hướng dẫn của Quản trị viên.</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Khối hủy đơn đặt phòng -->
            <?php if ($can_cancel): ?>
                <div class="card border-0 shadow-sm border-danger border-start border-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-danger"><i class="bi bi-x-circle me-1"></i> Hủy yêu cầu đặt phòng</h6>
                        <p class="text-muted small mb-3">
                            Đơn của bạn đang ở trạng thái <em>Chờ xác nhận</em> và hôm nay còn trước ngày nhận phòng, bạn có thể tự do hủy đơn này.
                        </p>
                        <form action="" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn hủy đơn đặt phòng này không? Hành động này không thể hoàn tác.')">
                            <input type="hidden" name="action_cancel" value="1">
                            <button type="submit" class="btn btn-outline-danger w-100 fw-semibold">
                                <i class="bi bi-trash3 me-1"></i> Xác nhận Hủy đơn
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
