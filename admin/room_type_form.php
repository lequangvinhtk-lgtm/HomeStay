<?php
/**
 * Form Thêm mới hoặc Chỉnh sửa Loại phòng
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = ($id > 0);
$page_title = $is_edit ? 'Chỉnh sửa Loại phòng' : 'Thêm Loại phòng mới';

$errors = [];
$room_type = [
    'property_id' => isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0,
    'name'        => '',
    'description' => '',
    'status'      => 'active',
];

// Danh sách cơ sở lưu trú để đưa vào select
$properties = $pdo->query("SELECT id, name FROM properties ORDER BY name ASC")->fetchAll();

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM room_types WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Không tìm thấy loại phòng yêu cầu.');
        redirect('/admin/room_types.php');
    }
    $room_type = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_id = (int)($_POST['property_id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status      = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if ($property_id <= 0) {
        $errors[] = 'Vui lòng chọn cơ sở lưu trú.';
    }
    if (empty($name)) {
        $errors[] = 'Tên loại phòng không được để trống.';
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $pdo->prepare("
                UPDATE room_types 
                SET property_id = ?, name = ?, description = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$property_id, $name, $description, $status, $id]);
            set_flash('success', 'Cập nhật loại phòng thành công!');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO room_types (property_id, name, description, status)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$property_id, $name, $description, $status]);
            set_flash('success', 'Thêm mới loại phòng thành công!');
        }
        redirect('/admin/room_types.php?property_id=' . $property_id);
    } else {
        $room_type = [
            'property_id' => $property_id,
            'name'        => $name,
            'description' => $description,
            'status'      => $status,
        ];
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">
                    <i class="bi <?= $is_edit ? 'bi-pencil-square' : 'bi-plus-circle' ?> text-primary me-2"></i>
                    <?= e($page_title) ?>
                </h3>
                <p class="text-muted small mb-0">Quản lý định nghĩa phân loại phòng cho cơ sở lưu trú</p>
            </div>
            <a href="/admin/room_types.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Quay lại
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="" method="POST">
                    <div class="mb-3">
                        <label for="property_id" class="form-label fw-semibold">Thuộc Cơ sở lưu trú <span class="required-star">*</span></label>
                        <select class="form-select" id="property_id" name="property_id" required>
                            <option value="">-- Chọn khách sạn hoặc homestay --</option>
                            <?php foreach ($properties as $prop): ?>
                                <option value="<?= $prop['id'] ?>" <?= (int)$room_type['property_id'] === (int)$prop['id'] ? 'selected' : '' ?>>
                                    <?= e($prop['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Tên loại phòng <span class="required-star">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= e($room_type['name']) ?>" required placeholder="VD: Deluxe River View, Superior...">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Mô tả đặc điểm loại phòng</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Đặc điểm vị trí, hướng nhìn, không gian..."><?= e($room_type['description']) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="status" class="form-label fw-semibold">Trạng thái</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?= $room_type['status'] === 'active' ? 'selected' : '' ?>>Đang hoạt động (active)</option>
                            <option value="inactive" <?= $room_type['status'] === 'inactive' ? 'selected' : '' ?>>Ngừng hoạt động (inactive)</option>
                        </select>
                    </div>

                    <div class="pt-3 border-top">
                        <button type="submit" class="btn btn-primary px-4 py-2">
                            <i class="bi bi-save me-1"></i> <?= $is_edit ? 'Lưu thay đổi' : 'Thêm loại phòng' ?>
                        </button>
                        <a href="/admin/room_types.php" class="btn btn-light px-4 py-2 ms-2">Hủy bỏ</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
