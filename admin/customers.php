<?php
/**
 * Quản lý danh sách Khách hàng trong hệ thống (Dành cho Quản trị viên)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$page_title = 'Quản lý Khách hàng';

$search = trim($_GET['search'] ?? '');

// Truy vấn danh sách khách hàng kèm số đơn đã đặt và tổng số tiền đã thanh toán thực tế
$sql = "
    SELECT c.*, u.email, u.created_at as registered_at,
           COUNT(DISTINCT b.id) as total_bookings,
           COALESCE(SUM(pay.amount), 0) as total_spent
    FROM customers c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN bookings b ON b.customer_id = c.id
    LEFT JOIN payments pay ON pay.booking_id = b.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.full_name LIKE ? OR u.email LIKE ? OR c.phone LIKE ?) ";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " GROUP BY c.id ORDER BY c.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Quản lý Khách hàng</h3>
        <p class="text-muted small mb-0">Theo dõi thông tin và lịch sử sử dụng dịch vụ của từng khách hàng</p>
    </div>
</div>

<!-- Thanh tìm kiếm -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/customers.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Tìm theo tên khách, email, số điện thoại..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-search me-1"></i> Tìm kiếm
                </button>
                <?php if (!empty($search)): ?>
                    <a href="/admin/customers.php" class="btn btn-sm btn-outline-secondary">Xóa tìm kiếm</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bảng danh sách khách hàng -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Khách hàng</th>
                        <th>Email liên hệ</th>
                        <th>Số điện thoại</th>
                        <th>Địa chỉ</th>
                        <th class="text-center">Số đơn đã đặt</th>
                        <th class="text-end">Tổng tiền đã chi</th>
                        <th>Ngày đăng ký</th>
                        <th class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                Không tìm thấy khách hàng nào phù hợp.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= e($c['full_name']) ?></div>
                                    <small class="text-muted">ID: #<?= $c['id'] ?></small>
                                </td>
                                <td><?= e($c['email']) ?></td>
                                <td><?= e($c['phone']) ?></td>
                                <td><small class="text-muted"><?= e($c['address'] ?? 'Chưa có') ?></small></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $c['total_bookings'] ?> đơn</span>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?= format_money($c['total_spent']) ?>
                                </td>
                                <td><small class="text-muted"><?= format_date($c['registered_at']) ?></small></td>
                                <td class="text-end">
                                    <a href="/admin/customer_detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Xem lịch sử
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
