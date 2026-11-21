<?php
/**
 * Quản lý danh sách Tài khoản & Phân quyền Người dùng (Dành cho Quản trị viên)
 * Hỗ trợ xem toàn bộ tài khoản, tìm kiếm, cấp quyền Quản trị viên hoặc chuyển về Khách hàng
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$page_title = 'Quản lý Tài khoản & Phân quyền';

// Xử lý CẤP QUYỀN hoặc HẠ QUYỀN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_change_role'])) {
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);
    $new_role = $_POST['action_change_role'] === 'to_admin' ? 'admin' : 'customer';

    // Bảo mật: Không cho phép tự hạ quyền của chính tài khoản Admin bạn đang đăng nhập
    if ($target_user_id === (int)$_SESSION['user_id'] && $new_role !== 'admin') {
        set_flash('danger', 'Không thể tự hạ quyền của chính tài khoản Quản trị viên bạn đang đăng nhập!');
    } else {
        $stmtRole = $pdo->prepare("UPDATE `users` SET `role` = ? WHERE `id` = ?");
        $stmtRole->execute([$new_role, $target_user_id]);

        $role_label = ($new_role === 'admin') ? 'Quản trị viên (Admin)' : 'Khách hàng';
        set_flash('success', "Đã cập nhật quyền cho tài khoản thành công: {$role_label}!");
    }
    redirect('/admin/customers.php');
}

$search = trim($_GET['search'] ?? '');
$filter_role = trim($_GET['role'] ?? '');

// Truy vấn danh sách người dùng kèm thông tin khách hàng và số đơn đặt phòng
$sql = "
    SELECT u.id as user_id, u.email, u.role, u.created_at as registered_at,
           c.id as customer_id, c.full_name, c.phone, c.address,
           COUNT(DISTINCT b.id) as total_bookings,
           COALESCE(SUM(pay.amount), 0) as total_spent
    FROM users u
    LEFT JOIN customers c ON c.user_id = u.id
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

if (!empty($filter_role) && in_array($filter_role, ['admin', 'customer'])) {
    $sql .= " AND u.role = ? ";
    $params[] = $filter_role;
}

$sql .= " GROUP BY u.id ORDER BY u.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Quản lý Tài khoản & Phân quyền</h3>
        <p class="text-muted small mb-0">Theo dõi thông tin tài khoản, đơn đặt phòng và trực tiếp cấp / thu hồi quyền Quản trị viên</p>
    </div>
</div>

<!-- Thanh tìm kiếm & Lọc vai trò -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/customers.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Tìm theo tên, email, số điện thoại..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Tất cả vai trò --</option>
                    <option value="customer" <?= $filter_role === 'customer' ? 'selected' : '' ?>>Khách hàng (customer)</option>
                    <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Quản trị viên (admin)</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i> Lọc
                </button>
                <?php if (!empty($search) || !empty($filter_role)): ?>
                    <a href="/admin/customers.php" class="btn btn-sm btn-outline-secondary ms-1">Xóa bộ lọc</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bảng danh sách tài khoản -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tài khoản</th>
                        <th>Họ và tên</th>
                        <th>Số điện thoại</th>
                        <th class="text-center">Vai trò / Quyền</th>
                        <th class="text-center">Số đơn đặt</th>
                        <th class="text-end">Tổng chi tiêu</th>
                        <th>Ngày tạo</th>
                        <th class="text-end">Cấp quyền & Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($accounts)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                Không tìm thấy tài khoản nào phù hợp với điều kiện tìm kiếm.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($accounts as $a): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($a['email']) ?></div>
                                    <small class="text-muted">UID: #<?= $a['user_id'] ?></small>
                                </td>
                                <td>
                                    <?= !empty($a['full_name']) ? e($a['full_name']) : '<em class="text-muted">Chưa cập nhật</em>' ?>
                                </td>
                                <td>
                                    <?= !empty($a['phone']) ? e($a['phone']) : '<em class="text-muted">--</em>' ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($a['role'] === 'admin'): ?>
                                        <span class="badge bg-danger px-2 py-1"><i class="bi bi-shield-lock-fill me-1"></i>Quản trị viên</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary px-2 py-1"><i class="bi bi-person-fill me-1"></i>Khách hàng</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $a['total_bookings'] ?> đơn</span>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?= format_money($a['total_spent']) ?>
                                </td>
                                <td><small class="text-muted"><?= format_date($a['registered_at']) ?></small></td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- Form đổi quyền tài khoản -->
                                        <?php if ($a['user_id'] === (int)$_SESSION['user_id']): ?>
                                            <span class="badge bg-light text-muted border py-1 px-2" title="Tài khoản đang đăng nhập hiện tại">
                                                <i class="bi bi-person-check-fill text-success me-1"></i>Đang đăng nhập
                                            </span>
                                        <?php elseif ($a['role'] === 'customer'): ?>
                                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn CẤP QUYỀN Quản trị viên (Admin) cho tài khoản <?= e($a['email']) ?> không?')">
                                                <input type="hidden" name="target_user_id" value="<?= $a['user_id'] ?>">
                                                <button type="submit" name="action_change_role" value="to_admin" class="btn btn-sm btn-outline-danger" title="Cấp quyền Admin">
                                                    <i class="bi bi-shield-plus me-1"></i>Cấp Admin
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn HẠ QUYỀN tài khoản <?= e($a['email']) ?> về Khách hàng thường không?')">
                                                <input type="hidden" name="target_user_id" value="<?= $a['user_id'] ?>">
                                                <button type="submit" name="action_change_role" value="to_customer" class="btn btn-sm btn-outline-warning text-dark" title="Hạ quyền thành Khách hàng">
                                                    <i class="bi bi-person-dash me-1"></i>Hạ quyền
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!empty($a['customer_id'])): ?>
                                            <a href="/admin/customer_detail.php?id=<?= $a['customer_id'] ?>" class="btn btn-sm btn-outline-primary" title="Xem chi tiết đơn và lịch sử">
                                                Chi tiết
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
