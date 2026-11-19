<?php
/**
 * Danh sách và Lịch sử Đơn đặt phòng của Khách hàng hiện tại
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();

$page_title = 'Đơn đặt phòng của tôi';

// Lấy customer_id (tự động tạo hồ sơ nếu chưa có)
$stmtCust = $pdo->prepare("SELECT id FROM customers WHERE user_id = ?");
$stmtCust->execute([$user['id']]);
$customer_id = $stmtCust->fetchColumn();

if (!$customer_id) {
    $autoName = !empty($user['full_name']) && $user['full_name'] !== 'Khách' ? $user['full_name'] : 'Khách hàng';
    $stmtCreate = $pdo->prepare("INSERT INTO customers (user_id, full_name, phone, address) VALUES (?, ?, '0901234567', '')");
    $stmtCreate->execute([$user['id'], $autoName]);
    $customer_id = $pdo->lastInsertId();
    $_SESSION['customer_id'] = $customer_id;
    $_SESSION['full_name'] = $autoName;
}

// Bộ lọc trạng thái đơn
$status_filter = trim($_GET['status'] ?? '');

$sql = "
    SELECT b.*, p.name as property_name, p.type as property_type, p.city as property_city,
           r.room_number, rt.name as room_type_name
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN properties p ON r.property_id = p.id
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE b.customer_id = ?
";
$params = [$customer_id];

if (!empty($status_filter)) {
    $sql .= " AND b.status = ? ";
    $params[] = $status_filter;
}

$sql .= " ORDER BY b.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <h2 class="fw-bold mb-1"><i class="bi bi-calendar-check me-2"></i>Đơn đặt phòng của tôi</h2>
        <p class="mb-0 opacity-75">Theo dõi trạng thái và lịch sử đặt phòng của bạn tại ItachiHome</p>
    </div>
</div>

<div class="container py-2 pb-5">
    <!-- Bộ lọc trạng thái nhanh -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="fw-semibold small text-muted me-2">Lọc trạng thái:</span>
                <a href="/my_bookings.php" class="btn btn-sm <?= empty($status_filter) ? 'btn-primary' : 'btn-outline-secondary' ?>">Tất cả</a>
                <a href="/my_bookings.php?status=pending" class="btn btn-sm <?= $status_filter === 'pending' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' ?>">Chờ xác nhận</a>
                <a href="/my_bookings.php?status=confirmed" class="btn btn-sm <?= $status_filter === 'confirmed' ? 'btn-info text-dark' : 'btn-outline-info text-dark' ?>">Đã xác nhận</a>
                <a href="/my_bookings.php?status=checked_in" class="btn btn-sm <?= $status_filter === 'checked_in' ? 'btn-primary' : 'btn-outline-primary' ?>">Đang ở</a>
                <a href="/my_bookings.php?status=completed" class="btn btn-sm <?= $status_filter === 'completed' ? 'btn-success' : 'btn-outline-success' ?>">Hoàn thành</a>
                <a href="/my_bookings.php?status=cancelled" class="btn btn-sm <?= $status_filter === 'cancelled' ? 'btn-dark' : 'btn-outline-dark' ?>">Đã hủy</a>
            </div>
        </div>
    </div>

    <!-- Danh sách đơn -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã đơn</th>
                            <th>Cơ sở lưu trú</th>
                            <th>Phòng</th>
                            <th>Thời gian lưu trú</th>
                            <th>Số đêm</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                    <h6 class="fw-bold mt-2 text-muted">Bạn chưa có đơn đặt phòng nào phù hợp</h6>
                                    <a href="/properties.php" class="btn btn-primary btn-sm mt-2">
                                        <i class="bi bi-search me-1"></i> Khám phá phòng ngay
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary"><?= e($b['booking_code']) ?></span>
                                        <div class="text-muted small"><?= format_datetime($b['created_at']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= e($b['property_name']) ?></div>
                                        <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($b['property_city']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">Phòng <?= e($b['room_number']) ?></span>
                                        <div class="small text-muted"><?= e($b['room_type_name']) ?></div>
                                    </td>
                                    <td>
                                        <div><?= format_date($b['check_in']) ?> → <?= format_date($b['check_out']) ?></div>
                                        <small class="text-muted"><?= $b['guests'] ?> khách</small>
                                    </td>
                                    <td class="fw-semibold"><?= $b['nights'] ?> đêm</td>
                                    <td class="fw-bold text-danger"><?= format_money($b['total_amount']) ?></td>
                                    <td><?= get_status_badge($b['status']) ?></td>
                                    <td class="text-end">
                                        <a href="/booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-outline-primary btn-sm">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
