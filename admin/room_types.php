<?php
/**
 * Quản lý danh sách Loại phòng theo từng Cơ sở lưu trú
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$page_title = 'Quản lý Loại phòng';

// Lọc theo cơ sở nếu có
$property_filter = isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0;

// Xử lý XÓA AN TOÀN hoặc ĐỔI TRẠNG THÁI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $room_type_id = (int)($_POST['room_type_id'] ?? 0);

    if ($room_type_id > 0) {
        if ($_POST['action'] === 'delete') {
            // Kiểm tra xem loại phòng này đã có phòng cụ thể nào chưa
            $chkRooms = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE room_type_id = ?");
            $chkRooms->execute([$room_type_id]);
            $hasRooms = $chkRooms->fetchColumn() > 0;

            if ($hasRooms) {
                // Đã có phòng liên quan -> Chuyển inactive, không xóa vật lý
                $stmt = $pdo->prepare("UPDATE room_types SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$room_type_id]);
                set_flash('warning', 'Loại phòng này đã có phòng cụ thể liên quan nên được chuyển sang trạng thái "Ngừng hoạt động" để đảm bảo toàn vẹn dữ liệu.');
            } else {
                // Chưa có phòng liên quan -> Cho phép xóa
                $stmt = $pdo->prepare("DELETE FROM room_types WHERE id = ?");
                $stmt->execute([$room_type_id]);
                set_flash('success', 'Đã xóa hoàn toàn loại phòng.');
            }
            redirect('/admin/room_types.php' . ($property_filter > 0 ? '?property_id=' . $property_filter : ''));
        }

        if ($_POST['action'] === 'toggle_status') {
            $current_status = $_POST['current_status'] ?? 'active';
            $new_status = ($current_status === 'active') ? 'inactive' : 'active';

            $stmt = $pdo->prepare("UPDATE room_types SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $room_type_id]);
            set_flash('success', 'Đã cập nhật trạng thái loại phòng.');
            redirect('/admin/room_types.php' . ($property_filter > 0 ? '?property_id=' . $property_filter : ''));
        }
    }
}

// Lấy danh sách các cơ sở để làm bộ lọc
$properties_list = $pdo->query("SELECT id, name FROM properties ORDER BY name ASC")->fetchAll();

// Truy vấn danh sách loại phòng kèm tên cơ sở và số lượng phòng
$sql = "
    SELECT rt.*, p.name as property_name, p.type as property_type,
           (SELECT COUNT(*) FROM rooms r WHERE r.room_type_id = rt.id) as total_rooms
    FROM room_types rt
    JOIN properties p ON rt.property_id = p.id
";
$params = [];

if ($property_filter > 0) {
    $sql .= " WHERE rt.property_id = ? ";
    $params[] = $property_filter;
}

$sql .= " ORDER BY p.name ASC, rt.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$room_types = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-tags text-primary me-2"></i>Loại phòng</h3>
        <p class="text-muted small mb-0">Quản lý các phân loại phòng (Standard, Deluxe, Bungalow...)</p>
    </div>
    <a href="/admin/room_type_form.php<?= $property_filter > 0 ? '?property_id=' . $property_filter : '' ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Thêm loại phòng mới
    </a>
</div>

<!-- Bộ lọc theo cơ sở -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/room_types.php" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="col-form-label fw-semibold small">Lọc theo Cơ sở:</label>
            </div>
            <div class="col-md-4">
                <select name="property_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">-- Tất cả cơ sở lưu trú --</option>
                    <?php foreach ($properties_list as $prop): ?>
                        <option value="<?= $prop['id'] ?>" <?= $property_filter === (int)$prop['id'] ? 'selected' : '' ?>>
                            <?= e($prop['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($property_filter > 0): ?>
                <div class="col-auto">
                    <a href="/admin/room_types.php" class="btn btn-sm btn-outline-secondary">Xóa lọc</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tên loại phòng</th>
                        <th>Thuộc cơ sở lưu trú</th>
                        <th>Mô tả tóm tắt</th>
                        <th class="text-center">Số phòng con</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($room_types)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Chưa có loại phòng nào phù hợp.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($room_types as $rt): ?>
                            <tr>
                                <td><span class="fw-bold"><?= e($rt['name']) ?></span></td>
                                <td>
                                    <div class="fw-semibold text-primary"><?= e($rt['property_name']) ?></div>
                                    <small class="text-muted"><?= get_property_type_text($rt['property_type']) ?></small>
                                </td>
                                <td><small class="text-muted"><?= e($rt['description'] ?? 'Không có') ?></small></td>
                                <td class="text-center">
                                    <a href="/admin/rooms.php?room_type_id=<?= $rt['id'] ?>" class="badge bg-secondary text-decoration-none">
                                        <?= $rt['total_rooms'] ?> phòng
                                    </a>
                                </td>
                                <td>
                                    <?php if ($rt['status'] === 'active'): ?>
                                        <span class="badge bg-success">Đang hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ngừng hoạt động</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/admin/room_type_form.php?id=<?= $rt['id'] ?>" class="btn btn-outline-secondary" title="Sửa">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <form action="/admin/room_types.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="room_type_id" value="<?= $rt['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $rt['status'] ?>">
                                            <button type="submit" class="btn btn-outline-<?= $rt['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $rt['status'] === 'active' ? 'Ngừng hoạt động' : 'Kích hoạt lại' ?>">
                                                <i class="bi bi-power"></i>
                                            </button>
                                        </form>

                                        <form action="/admin/room_types.php" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa loại phòng này? (Nếu đã có phòng cụ thể hệ thống sẽ chuyển sang ngừng hoạt động).')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="room_type_id" value="<?= $rt['id'] ?>">
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
