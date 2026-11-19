<?php
/**
 * Trang Bảng điều khiển Quản trị viên (Dashboard)
 * Hiển thị số liệu tổng quan: số đơn chờ duyệt, tổng doanh thu tháng, số cơ sở, số phòng, số khách
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Kiểm tra quyền admin
require_admin();

$page_title = 'Bảng điều khiển';

// 1. Thống kê số đơn chờ duyệt (pending)
$stmtPending = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$total_pending_bookings = $stmtPending->fetchColumn();

// 2. Thống kê tổng số khách hàng
$stmtCustomers = $pdo->query("SELECT COUNT(*) FROM customers");
$total_customers = $stmtCustomers->fetchColumn();

// 3. Thống kê tổng số cơ sở lưu trú và số phòng
$stmtProperties = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'active'");
$total_properties = $stmtProperties->fetchColumn();

$stmtRooms = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'active'");
$total_rooms = $stmtRooms->fetchColumn();

// 4. Doanh thu thực thu trong tháng hiện tại (tính từ bảng payments theo paid_at)
$current_month = date('m');
$current_year  = date('Y');
$stmtRevenue = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) 
    FROM payments 
    WHERE MONTH(paid_at) = ? AND YEAR(paid_at) = ?
");
$stmtRevenue->execute([$current_month, $current_year]);
$monthly_revenue = $stmtRevenue->fetchColumn();

// 5. Danh sách 5 đơn đặt phòng mới nhất
$stmtLatestBookings = $pdo->query("
    SELECT b.*, c.full_name as customer_name, p.name as property_name, r.room_number
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN rooms r ON b.room_id = r.id
    JOIN properties p ON r.property_id = p.id
    ORDER BY b.created_at DESC
    LIMIT 5
");
$latest_bookings = $stmtLatestBookings->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-speedometer2 text-primary me-2"></i>Tổng quan hệ thống</h3>
        <p class="text-muted small mb-0">Theo dõi trạng thái hoạt động của các cơ sở lưu trú và đơn đặt phòng</p>
    </div>
    <div>
        <span class="badge bg-primary fs-6 px-3 py-2">
            <i class="bi bi-calendar3 me-1"></i> Hôm nay: <?= date('d/m/Y') ?>
        </span>
    </div>
</div>

<!-- 4 Thẻ chỉ số tổng quan (KPIs) -->
<div class="row g-3 mb-4">
    <!-- Đơn chờ duyệt -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm border-start border-warning border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Đơn chờ duyệt</div>
                        <h3 class="fw-bold my-1 text-warning"><?= $total_pending_bookings ?></h3>
                        <a href="/admin/bookings.php?status=pending" class="text-decoration-none small text-muted">
                            Xem danh sách <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                        <i class="bi bi-hourglass-split fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Doanh thu tháng này -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Doanh thu tháng <?= date('m/Y') ?></div>
                        <h4 class="fw-bold my-1 text-success"><?= format_money($monthly_revenue) ?></h4>
                        <a href="/admin/reports.php" class="text-decoration-none small text-muted">
                            Báo cáo chi tiết <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                        <i class="bi bi-cash-coin fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tổng số khách hàng -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Khách hàng</div>
                        <h3 class="fw-bold my-1 text-info"><?= $total_customers ?></h3>
                        <a href="/admin/customers.php" class="text-decoration-none small text-muted">
                            Quản lý khách <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                        <i class="bi bi-people-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cơ sở & Phòng hoạt động -->
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Cơ sở / Phòng</div>
                        <h4 class="fw-bold my-1 text-primary"><?= $total_properties ?> / <?= $total_rooms ?></h4>
                        <a href="/admin/rooms.php" class="text-decoration-none small text-muted">
                            Xem phòng <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                        <i class="bi bi-buildings-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lối tắt nhanh và Bảng đơn mới nhất -->
<div class="row g-4">
    <!-- Cột danh sách đơn mới nhất -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Đơn đặt phòng gần đây</h6>
                <a href="/admin/bookings.php" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Cơ sở & Phòng</th>
                                <th>Lưu trú</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($latest_bookings)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Chưa có đơn đặt phòng nào.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($latest_bookings as $b): ?>
                                    <tr>
                                        <td><span class="fw-bold text-primary"><?= e($b['booking_code']) ?></span></td>
                                        <td>
                                            <div class="fw-semibold"><?= e($b['contact_name']) ?></div>
                                            <small class="text-muted"><?= e($b['contact_phone']) ?></small>
                                        </td>
                                        <td>
                                            <div><?= e($b['property_name']) ?></div>
                                            <small class="text-muted">Phòng: <?= e($b['room_number']) ?></small>
                                        </td>
                                        <td>
                                            <small><?= format_date($b['check_in']) ?> → <?= format_date($b['check_out']) ?></small>
                                            <div class="text-muted small"><?= $b['nights'] ?> đêm</div>
                                        </td>
                                        <td class="fw-bold"><?= format_money($b['total_amount']) ?></td>
                                        <td><?= get_status_badge($b['status']) ?></td>
                                        <td>
                                            <a href="/admin/booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-primary">
                                                Chi tiết
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

    <!-- Cột phím tắt chức năng -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-lightning-charge text-warning me-2"></i>Thao tác nhanh</h6>
            </div>
            <div class="card-body d-grid gap-2">
                <a href="/admin/property_form.php" class="btn btn-outline-primary text-start">
                    <i class="bi bi-plus-circle me-2"></i> Thêm Khách sạn / Homestay mới
                </a>
                <a href="/admin/room_form.php" class="btn btn-outline-success text-start">
                    <i class="bi bi-door-open me-2"></i> Thêm phòng mới
                </a>
                <a href="/admin/bookings.php?status=pending" class="btn btn-outline-warning text-dark text-start">
                    <i class="bi bi-hourglass-split me-2"></i> Xử lý đơn đang chờ duyệt
                </a>
                <a href="/admin/reports.php" class="btn btn-outline-info text-start">
                    <i class="bi bi-bar-chart-line me-2"></i> Xem báo cáo doanh thu
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body p-4">
                <h6 class="fw-bold"><i class="bi bi-shield-check me-2"></i>Quy tắc vận hành phòng</h6>
                <p class="small mb-0 opacity-75">
                    Hệ thống tự động kiểm tra chống trùng lịch khi duyệt đơn (confirmed). Đơn hoàn tất cần được ghi nhận đủ thanh toán và chuyển trạng thái trả phòng đúng quy trình.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
