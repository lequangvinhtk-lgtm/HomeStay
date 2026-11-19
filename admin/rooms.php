<?php
/**
 * Quản lý danh sách Phòng cụ thể trong hệ thống
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$page_title = 'Quản lý Phòng';

// Các bộ lọc
$property_filter  = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;
$room_type_filter = isset($_GET['room_type_id']) ? (int)$_GET['room_type_id'] : 0;
$status_filter    = isset($_GET['status']) ? trim($_GET['status']) : '';

// Xử lý XÓA AN TOÀN hoặc ĐỔI TRẠNG THÁI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $room_id = (int)($_POST['room_id'] ?? 0);

    if ($room_id > 0) {
        if ($_POST['action'] === 'delete') {
            // Kiểm tra xem phòng này đã từng có đơn đặt phòng nào chưa
            $chkBookings = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE room_id = ?");
            $chkBookings->execute([$room_id]);
            $hasBookings = $chkBookings->fetchColumn() > 0;

            if ($hasBookings) {
                // ĐÃ CÓ ĐƠN LIÊN QUAN: Chuyển sang inactive, KHÔNG xóa vật lý
                $stmt = $pdo->prepare("UPDATE rooms SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$room_id]);
                set_flash('warning', 'Phòng này đã có đơn đặt phòng liên quan trong lịch sử nên được chuyển sang trạng thái "Ngừng hoạt động" để đảm bảo toàn vẹn dữ liệu.');
            } else {
                // CHƯA CÓ ĐƠN: Cho phép xóa hoàn toàn
                $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
                $stmt->execute([$room_id]);
                set_flash('success', 'Đã xóa hoàn toàn phòng khỏi hệ thống.');
            }
            redirect('/admin/rooms.php');
        }

        if ($_POST['action'] === 'toggle_status') {
            $current_status = $_POST['current_status'] ?? 'active';
            $new_status = ($current_status === 'active') ? 'inactive' : 'active';

            $stmt = $pdo->prepare("UPDATE rooms SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $room_id]);
            set_flash('success', 'Đã cập nhật trạng thái phòng.');
            redirect('/admin/rooms.php');
        }
    }
}

// Lấy danh mục cơ sở phục vụ bộ lọc
$properties_list = $pdo->query("SELECT id, name FROM properties ORDER BY name ASC")->fetchAll();

// Truy vấn danh sách phòng
$sql = "
    SELECT r.*, p.name as property_name, p.type as property_type, rt.name as room_type_name,
           (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id) as total_bookings
    FROM rooms r
    JOIN properties p ON r.property_id = p.id
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE 1=1
";
$params = [];

if ($property_filter > 0) {
    $sql .= " AND r.property_id = ? ";
    $params[] = $property_filter;
}

if ($room_type_filter > 0) {
    $sql .= " AND r.room_type_id = ? ";
    $params[] = $room_type_filter;
}

if (!empty($status_filter) && in_array($status_filter, ['active', 'inactive'])) {
    $sql .= " AND r.status = ? ";
    $params[] = $status_filter;
}

$sql .= " ORDER BY p.name ASC, r.room_number ASC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-door-open text-primary me-2"></i>Danh sách Phòng</h3>
        <p class="text-muted small mb-0">Quản lý số phòng, sức chứa, giá niêm yết và trạng thái</p>
    </div>
    <a href="/admin/room_form.php<?= $property_filter > 0 ? '?property_id=' . $property_filter : '' ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Thêm phòng mới
    </a>
</div>

<!-- Bộ lọc tìm kiếm nhanh -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/rooms.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="property_id" class="form-select form-select-sm">
                    <option value="0">-- Lọc theo Cơ sở lưu trú --</option>
                    <?php foreach ($properties_list as $prop): ?>
                        <option value="<?= $prop['id'] ?>" <?= $property_filter === (int)$prop['id'] ? 'selected' : '' ?>>
                            <?= e($prop['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Tất cả trạng thái --</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Ngừng hoạt động</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i> Lọc
                </button>
                <a href="/admin/rooms.php" class="btn btn-sm btn-outline-secondary">Xóa lọc</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">Hình ảnh</th>
                        <th>Số phòng</th>
                        <th>Cơ sở lưu trú</th>
                        <th>Loại phòng</th>
                        <th class="text-center">Sức chứa</th>
                        <th>Giá mỗi đêm</th>
                        <th class="text-center">Lượt đặt</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rooms)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">Chưa có phòng nào phù hợp điều kiện lọc.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rooms as $r): ?>
                            <tr>
                                <td>
                                    <?php $img_src = !empty($r['image']) ? '/assets/images/' . e($r['image']) : '/assets/images/no_image.jpg'; ?>
                                    <img src="<?= $img_src ?>" alt="Phòng <?= e($r['room_number']) ?>" class="rounded" style="width: 70px; height: 50px; object-fit: cover;">
                                </td>
                                <td>
                                    <span class="fw-bold fs-6 text-primary">Phòng <?= e($r['room_number']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($r['property_name']) ?></div>
                                    <small class="text-muted"><?= get_property_type_text($r['property_type']) ?></small>
                                </td>
                                <td><?= e($r['room_type_name']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-people-fill me-1"></i><?= $r['capacity'] ?> người
                                    </span>
                                </td>
                                <td class="fw-bold text-success">
                                    <?= format_money($r['price_per_night']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $r['total_bookings'] ?> đơn</span>
                                </td>
                                <td>
                                    <?php if ($r['status'] === 'active'): ?>
                                        <span class="badge bg-success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ngừng hoạt động</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/admin/room_form.php?id=<?= $r['id'] ?>" class="btn btn-outline-secondary" title="Sửa">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <form action="/admin/rooms.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $r['status'] ?>">
                                            <button type="submit" class="btn btn-outline-<?= $r['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $r['status'] === 'active' ? 'Ngừng hoạt động' : 'Kích hoạt lại' ?>">
                                                <i class="bi bi-power"></i>
                                            </button>
                                        </form>

                                        <form action="/admin/rooms.php" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa phòng này? (Nếu đã có đơn đặt phòng trong lịch sử, hệ thống sẽ chuyển sang trạng thái ngừng hoạt động).')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Xóa">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
