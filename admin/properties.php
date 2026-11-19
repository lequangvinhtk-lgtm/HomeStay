<?php
/**
 * Quản lý danh sách Cơ sở lưu trú (Khách sạn / Homestay)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$page_title = 'Quản lý Cơ sở lưu trú';

// Xử lý XÓA AN TOÀN hoặc ĐỔI TRẠNG THÁI qua POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $property_id = (int)($_POST['property_id'] ?? 0);

    if ($property_id > 0) {
        if ($_POST['action'] === 'delete') {
            // Kiểm tra xem cơ sở này đã có dữ liệu liên quan (loại phòng, phòng) hay chưa
            $chkTypes = $pdo->prepare("SELECT COUNT(*) FROM room_types WHERE property_id = ?");
            $chkTypes->execute([$property_id]);
            $hasTypes = $chkTypes->fetchColumn() > 0;

            $chkRooms = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE property_id = ?");
            $chkRooms->execute([$property_id]);
            $hasRooms = $chkRooms->fetchColumn() > 0;

            if ($hasTypes || $hasRooms) {
                // ĐÃ CÓ LIÊN QUAN: Chuyển sang inactive, không xóa vật lý
                $stmt = $pdo->prepare("UPDATE properties SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$property_id]);
                set_flash('warning', 'Cơ sở này đã có dữ liệu phòng/loại phòng liên quan nên được chuyển sang trạng thái "Ngừng hoạt động" để đảm bảo toàn vẹn dữ liệu.');
            } else {
                // CHƯA CÓ LIÊN QUAN: Cho phép xóa hoàn toàn
                $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ?");
                $stmt->execute([$property_id]);
                set_flash('success', 'Đã xóa hoàn toàn cơ sở lưu trú.');
            }
            redirect('/admin/properties.php');
        }

        if ($_POST['action'] === 'toggle_status') {
            $current_status = $_POST['current_status'] ?? 'active';
            $new_status = ($current_status === 'active') ? 'inactive' : 'active';

            $stmt = $pdo->prepare("UPDATE properties SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $property_id]);
            set_flash('success', 'Đã cập nhật trạng thái cơ sở lưu trú thành công.');
            redirect('/admin/properties.php');
        }
    }
}

// Lấy danh sách cơ sở lưu trú kèm số lượng phòng hiện có
$stmt = $pdo->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.id) as total_rooms,
           (SELECT COUNT(*) FROM room_types rt WHERE rt.property_id = p.id) as total_room_types
    FROM properties p
    ORDER BY p.id DESC
");
$properties = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-buildings text-primary me-2"></i>Cơ sở lưu trú</h3>
        <p class="text-muted small mb-0">Quản lý các khách sạn và homestay trong hệ thống</p>
    </div>
    <a href="/admin/property_form.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Thêm cơ sở mới
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">Hình ảnh</th>
                        <th>Tên cơ sở</th>
                        <th>Loại hình</th>
                        <th>Thành phố</th>
                        <th>Địa chỉ</th>
                        <th class="text-center">Số phòng</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($properties)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Chưa có cơ sở lưu trú nào.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($properties as $p): ?>
                            <tr>
                                <td>
                                    <?php $img_src = !empty($p['image']) ? '/assets/images/' . e($p['image']) : '/assets/images/no_image.jpg'; ?>
                                    <img src="<?= $img_src ?>" alt="<?= e($p['name']) ?>" class="rounded" style="width: 70px; height: 50px; object-fit: cover;">
                                </td>
                                <td>
                                    <div class="fw-bold"><?= e($p['name']) ?></div>
                                    <small class="text-muted"><?= $p['total_room_types'] ?> loại phòng</small>
                                </td>
                                <td>
                                    <?php if ($p['type'] === 'homestay'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">Homestay</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Khách sạn</span>
                                    <?php endif; ?>
                                </td>
                                <td><i class="bi bi-geo-alt text-danger me-1"></i><?= e($p['city']) ?></td>
                                <td><small class="text-muted"><?= e($p['address']) ?></small></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $p['total_rooms'] ?> phòng</span>
                                </td>
                                <td>
                                    <?php if ($p['status'] === 'active'): ?>
                                        <span class="badge bg-success">Đang hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ngừng hoạt động</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/admin/property_form.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary" title="Sửa">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Đổi trạng thái active / inactive -->
                                        <form action="/admin/properties.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $p['status'] ?>">
                                            <button type="submit" class="btn btn-outline-<?= $p['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $p['status'] === 'active' ? 'Ngừng hoạt động' : 'Kích hoạt lại' ?>">
                                                <i class="bi bi-power"></i>
                                            </button>
                                        </form>

                                        <!-- Xóa an toàn -->
                                        <form action="/admin/properties.php" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa cơ sở này? (Nếu đã có dữ liệu liên quan hệ thống sẽ chuyển sang ngừng hoạt động).')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
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
