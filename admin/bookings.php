<?php
/**
 * Quản lý danh sách toàn bộ Đơn đặt phòng trong hệ thống (Dành cho Admin)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$page_title = 'Quản lý Đơn đặt phòng';

// Các bộ lọc
$status_filter = trim($_GET['status'] ?? '');
$search        = trim($_GET['search'] ?? '');
$property_id   = (int)($_GET['property_id'] ?? 0);

// Lấy danh sách cơ sở lưu trú để lọc
$properties_list = $pdo->query("SELECT id, name FROM properties ORDER BY name ASC")->fetchAll();

$sql = "
    SELECT b.*, 
           c.full_name as customer_name, c.phone as customer_phone,
           p.name as property_name, p.type as property_type, p.city as property_city,
           r.room_number, rt.name as room_type_name,
           (SELECT COUNT(*) FROM payments pay WHERE pay.booking_id = b.id) as is_paid
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN rooms r ON b.room_id = r.id
    JOIN properties p ON r.property_id = p.id
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE 1=1
";
$params = [];

if (!empty($status_filter)) {
    $sql .= " AND b.status = ? ";
    $params[] = $status_filter;
}

if ($property_id > 0) {
    $sql .= " AND r.property_id = ? ";
    $params[] = $property_id;
}

if (!empty($search)) {
    $sql .= " AND (b.booking_code LIKE ? OR b.contact_name LIKE ? OR b.contact_phone LIKE ? OR c.full_name LIKE ?) ";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY b.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-calendar2-check text-primary me-2"></i>Đơn đặt phòng</h3>
        <p class="text-muted small mb-0">Theo dõi, duyệt phòng, check-in, check-out và quản lý thanh toán</p>
    </div>
</div>

<!-- Thanh công cụ tìm kiếm và lọc -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/bookings.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Mã đơn, tên khách, số ĐT..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Chờ xác nhận (pending)</option>
                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận (confirmed)</option>
                    <option value="checked_in" <?= $status_filter === 'checked_in' ? 'selected' : '' ?>>Đã nhận phòng (checked_in)</option>
                    <option value="checked_out" <?= $status_filter === 'checked_out' ? 'selected' : '' ?>>Đã trả phòng (checked_out)</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Hoàn thành (completed)</option>
                    <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Bị từ chối (rejected)</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Đã hủy (cancelled)</option>
                </select>
            </div>

            <div class="col-md-3">
                <select name="property_id" class="form-select form-select-sm">
                    <option value="0">-- Tất cả cơ sở lưu trú --</option>
                    <?php foreach ($properties_list as $prop): ?>
                        <option value="<?= $prop['id'] ?>" <?= $property_id === (int)$prop['id'] ? 'selected' : '' ?>>
                            <?= e($prop['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i> Lọc
                </button>
                <?php if (!empty($status_filter) || !empty($search) || $property_id > 0): ?>
                    <a href="/admin/bookings.php" class="btn btn-sm btn-outline-secondary" title="Xóa lọc">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bảng hiển thị danh sách đơn -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã đơn</th>
                        <th>Khách hàng</th>
                        <th>Cơ sở & Phòng</th>
                        <th>Thời gian lưu trú</th>
                        <th>Tổng tiền</th>
                        <th>Thanh toán</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                Không tìm thấy đơn đặt phòng nào phù hợp điều kiện tìm kiếm.
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
                                    <div class="fw-semibold"><?= e($b['contact_name']) ?></div>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($b['contact_phone']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($b['property_name']) ?></div>
                                    <small class="text-muted">Phòng: <strong><?= e($b['room_number']) ?></strong> (<?= e($b['room_type_name']) ?>)</small>
                                </td>
                                <td>
                                    <div><?= format_date($b['check_in']) ?> → <?= format_date($b['check_out']) ?></div>
                                    <small class="text-muted"><?= $b['nights'] ?> đêm (<?= $b['guests'] ?> khách)</small>
                                </td>
                                <td class="fw-bold text-danger">
                                    <?= format_money($b['total_amount']) ?>
                                </td>
                                <td>
                                    <?php if ($b['is_paid'] > 0): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                            <i class="bi bi-check-circle me-1"></i>Đã thanh toán
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning">
                                            Chưa thanh toán
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= get_status_badge($b['status']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="/admin/booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-primary">
                                        Chi tiết & Xử lý
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

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
