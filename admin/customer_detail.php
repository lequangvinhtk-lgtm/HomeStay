<?php
/**
 * Xem Chi tiết Hồ sơ & Lịch sử Đặt phòng của Khách hàng (Dành cho Admin)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($customer_id <= 0) {
    set_flash('danger', 'Khách hàng không hợp lệ.');
    redirect('/admin/customers.php');
}

// Lấy thông tin khách hàng
$stmt = $pdo->prepare("
    SELECT c.*, u.id as user_id, u.email, u.role, u.created_at as registered_at
    FROM customers c
    JOIN users u ON c.user_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash('danger', 'Không tìm thấy thông tin khách hàng yêu cầu.');
    redirect('/admin/customers.php');
}

// Xử lý CẤP QUYỀN hoặc HẠ QUYỀN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_change_role'])) {
    $new_role = $_POST['action_change_role'] === 'to_admin' ? 'admin' : 'customer';

    if ($customer['user_id'] === (int)$_SESSION['user_id'] && $new_role !== 'admin') {
        set_flash('danger', 'Không thể tự hạ quyền của chính tài khoản Quản trị viên bạn đang đăng nhập!');
    } else {
        $stmtRole = $pdo->prepare("UPDATE `users` SET `role` = ? WHERE `id` = ?");
        $stmtRole->execute([$new_role, $customer['user_id']]);
        $role_text = ($new_role === 'admin') ? 'Quản trị viên (Admin)' : 'Khách hàng';
        set_flash('success', "Đã cập nhật quyền tài khoản thành công: {$role_text}!");
    }
    redirect('/admin/customer_detail.php?id=' . $customer_id);
}

$page_title = 'Chi tiết khách hàng - ' . $customer['full_name'];

// Lấy lịch sử tất cả các đơn đặt phòng của khách hàng này
$stmtBookings = $pdo->prepare("
    SELECT b.*, p.name as property_name, p.city as property_city, r.room_number, rt.name as room_type_name,
           (SELECT COUNT(*) FROM payments pay WHERE pay.booking_id = b.id) as is_paid
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN properties p ON r.property_id = p.id
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE b.customer_id = ?
    ORDER BY b.id DESC
");
$stmtBookings->execute([$customer_id]);
$bookings = $stmtBookings->fetchAll();

// Thống kê tổng chi tiêu
$total_spent = 0;
$stmtSpent = $pdo->prepare("
    SELECT COALESCE(SUM(pay.amount), 0)
    FROM payments pay
    JOIN bookings b ON pay.booking_id = b.id
    WHERE b.customer_id = ?
");
$stmtSpent->execute([$customer_id]);
$total_spent = $stmtSpent->fetchColumn();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <div class="small mb-1">
            <a href="/admin/customers.php" class="text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Quay lại danh sách khách hàng
            </a>
        </div>
        <h3 class="fw-bold mb-0">Hồ sơ khách: <?= e($customer['full_name']) ?></h3>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Cột thông tin cá nhân khách -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <i class="bi bi-person-circle text-primary" style="font-size: 4rem;"></i>
                </div>
                <h5 class="fw-bold mb-1"><?= e($customer['full_name']) ?></h5>
                <p class="text-muted small mb-2"><?= e($customer['email']) ?></p>

                <div class="mb-3">
                    <?php if ($customer['role'] === 'admin'): ?>
                        <span class="badge bg-danger px-3 py-2"><i class="bi bi-shield-lock-fill me-1"></i>Quản trị viên (Admin)</span>
                    <?php else: ?>
                        <span class="badge bg-primary px-3 py-2"><i class="bi bi-person-fill me-1"></i>Khách hàng</span>
                    <?php endif; ?>
                </div>

                <div class="text-start bg-light p-3 rounded-3 small">
                    <div class="mb-2">
                        <span class="text-muted"><i class="bi bi-telephone me-1"></i> Số điện thoại:</span>
                        <div class="fw-bold"><?= e($customer['phone']) ?></div>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted"><i class="bi bi-geo-alt me-1"></i> Địa chỉ:</span>
                        <div class="fw-semibold"><?= e($customer['address'] ?? 'Chưa cập nhật') ?></div>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted"><i class="bi bi-shield me-1"></i> Quyền truy cập:</span>
                        <div class="fw-bold <?= $customer['role'] === 'admin' ? 'text-danger' : 'text-primary' ?>">
                            <?= $customer['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng' ?>
                        </div>
                    </div>
                    <div>
                        <span class="text-muted"><i class="bi bi-calendar-event me-1"></i> Ngày đăng ký:</span>
                        <div><?= format_datetime($customer['registered_at']) ?></div>
                    </div>
                </div>

                <!-- Thao tác phân quyền -->
                <div class="mt-3 pt-3 border-top">
                    <?php if ($customer['user_id'] === (int)$_SESSION['user_id']): ?>
                        <span class="badge bg-light text-muted border py-2 w-100">
                            <i class="bi bi-person-check-fill text-success me-1"></i>Tài khoản đang đăng nhập hiện tại
                        </span>
                    <?php elseif ($customer['role'] === 'customer'): ?>
                        <form action="" method="POST" onsubmit="return confirm('Bạn có chắc muốn CẤP QUYỀN Quản trị viên (Admin) cho tài khoản này?')">
                            <button type="submit" name="action_change_role" value="to_admin" class="btn btn-outline-danger w-100 fw-semibold btn-sm">
                                <i class="bi bi-shield-plus me-1"></i> Cấp quyền Quản trị viên
                            </button>
                        </form>
                    <?php else: ?>
                        <form action="" method="POST" onsubmit="return confirm('Bạn có chắc muốn HẠ QUYỀN tài khoản này về Khách hàng thường?')">
                            <button type="submit" name="action_change_role" value="to_customer" class="btn btn-outline-warning text-dark w-100 fw-semibold btn-sm">
                                <i class="bi bi-person-dash me-1"></i> Hạ quyền thành Khách hàng
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="row g-2 mt-3 text-center">
                    <div class="col-6">
                        <div class="border rounded p-2">
                            <small class="text-muted d-block">Tổng số đơn</small>
                            <span class="fs-5 fw-bold text-primary"><?= count($bookings) ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2">
                            <small class="text-muted d-block">Tổng chi tiêu</small>
                            <span class="fs-6 fw-bold text-success"><?= format_money($total_spent) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cột lịch sử đặt phòng của khách này -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-primary">
                    <i class="bi bi-clock-history me-2"></i>Lịch sử các đơn đặt phòng
                </h6>
                <span class="badge bg-secondary"><?= count($bookings) ?> đơn</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mã đơn</th>
                                <th>Cơ sở & Phòng</th>
                                <th>Thời gian</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th class="text-end">Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        Khách hàng này chưa có đơn đặt phòng nào.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-primary"><?= e($b['booking_code']) ?></span>
                                            <div class="text-muted small"><?= format_date($b['created_at']) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= e($b['property_name']) ?></div>
                                            <small class="text-muted">Phòng: <?= e($b['room_number']) ?></small>
                                        </td>
                                        <td>
                                            <small><?= format_date($b['check_in']) ?> → <?= format_date($b['check_out']) ?></small>
                                            <div class="text-muted small"><?= $b['nights'] ?> đêm</div>
                                        </td>
                                        <td class="fw-bold"><?= format_money($b['total_amount']) ?></td>
                                        <td><?= get_status_badge($b['status']) ?></td>
                                        <td class="text-end">
                                            <a href="/admin/booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                Xử lý
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
