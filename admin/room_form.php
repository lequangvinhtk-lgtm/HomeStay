<?php
/**
 * Form Thêm mới hoặc Chỉnh sửa Phòng cụ thể
 * Kèm upload ảnh phòng
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = ($id > 0);
$page_title = $is_edit ? 'Chỉnh sửa Phòng' : 'Thêm Phòng mới';

$errors = [];
$room = [
    'property_id'     => isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0,
    'room_type_id'    => 0,
    'room_number'     => '',
    'capacity'        => 2,
    'price_per_night' => 500000,
    'amenities'       => '',
    'image'           => '',
    'status'          => 'active',
];

// Danh sách các cơ sở lưu trú
$properties = $pdo->query("SELECT id, name FROM properties ORDER BY name ASC")->fetchAll();

// Danh sách toàn bộ loại phòng (kèm property_id để hiển thị hoặc lọc)
$room_types = $pdo->query("SELECT id, property_id, name FROM room_types ORDER BY name ASC")->fetchAll();

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Không tìm thấy phòng yêu cầu.');
        redirect('/admin/rooms.php');
    }
    $room = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_id     = (int)($_POST['property_id'] ?? 0);
    $room_type_id    = (int)($_POST['room_type_id'] ?? 0);
    $room_number     = trim($_POST['room_number'] ?? '');
    $capacity        = (int)($_POST['capacity'] ?? 2);
    $price_per_night = (float)($_POST['price_per_night'] ?? 0);
    $amenities       = trim($_POST['amenities'] ?? '');
    $status          = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $image_name      = $room['image']; // Mặc định giữ ảnh cũ

    if ($property_id <= 0) {
        $errors[] = 'Vui lòng chọn cơ sở lưu trú.';
    }
    if ($room_type_id <= 0) {
        $errors[] = 'Vui lòng chọn loại phòng.';
    }
    if (empty($room_number)) {
        $errors[] = 'Vui lòng nhập số phòng (hoặc mã phòng).';
    }
    if ($capacity < 1) {
        $errors[] = 'Sức chứa của phòng tối thiểu phải từ 1 người trở lên.';
    }
    if ($price_per_night <= 0) {
        $errors[] = 'Giá mỗi đêm phải lớn hơn 0 VNĐ.';
    }

    // Xử lý upload ảnh phòng (nếu có chọn file)
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp   = $_FILES['image']['tmp_name'];
        $file_name  = $_FILES['image']['name'];
        $file_ext   = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = 'Chỉ chấp nhận file ảnh định dạng: jpg, jpeg, png, webp.';
        } else {
            $new_image_name = 'room_' . time() . '_' . rand(100, 999) . '.' . $file_ext;
            $upload_path = __DIR__ . '/../assets/images/' . $new_image_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $image_name = $new_image_name;
            } else {
                $errors[] = 'Không thể lưu file ảnh vào thư mục assets/images/.';
            }
        }
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $pdo->prepare("
                UPDATE rooms 
                SET property_id = ?, room_type_id = ?, room_number = ?, capacity = ?, 
                    price_per_night = ?, amenities = ?, image = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$property_id, $room_type_id, $room_number, $capacity, $price_per_night, $amenities, $image_name, $status, $id]);
            set_flash('success', 'Cập nhật thông tin phòng thành công!');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO rooms (property_id, room_type_id, room_number, capacity, price_per_night, amenities, image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$property_id, $room_type_id, $room_number, $capacity, $price_per_night, $amenities, $image_name, $status]);
            set_flash('success', 'Thêm mới phòng thành công!');
        }
        redirect('/admin/rooms.php?property_id=' . $property_id);
    } else {
        $room = [
            'property_id'     => $property_id,
            'room_type_id'    => $room_type_id,
            'room_number'     => $room_number,
            'capacity'        => $capacity,
            'price_per_night' => $price_per_night,
            'amenities'       => $amenities,
            'image'           => $image_name,
            'status'          => $status,
        ];
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">
                    <i class="bi <?= $is_edit ? 'bi-pencil-square' : 'bi-plus-circle' ?> text-primary me-2"></i>
                    <?= e($page_title) ?>
                </h3>
                <p class="text-muted small mb-0">Cấu hình thông tin chi tiết của phòng nghỉ</p>
            </div>
            <a href="/admin/rooms.php" class="btn btn-outline-secondary">
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
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="property_id" class="form-label fw-semibold">Cơ sở lưu trú <span class="required-star">*</span></label>
                            <select class="form-select" id="property_id" name="property_id" required>
                                <option value="">-- Chọn khách sạn/homestay --</option>
                                <?php foreach ($properties as $prop): ?>
                                    <option value="<?= $prop['id'] ?>" <?= (int)$room['property_id'] === (int)$prop['id'] ? 'selected' : '' ?>>
                                        <?= e($prop['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="room_type_id" class="form-label fw-semibold">Loại phòng <span class="required-star">*</span></label>
                            <select class="form-select" id="room_type_id" name="room_type_id" required>
                                <option value="">-- Chọn loại phòng --</option>
                                <?php foreach ($room_types as $rt): ?>
                                    <option value="<?= $rt['id'] ?>" data-property="<?= $rt['property_id'] ?>" <?= (int)$room['room_type_id'] === (int)$rt['id'] ? 'selected' : '' ?>>
                                        <?= e($rt['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="room_number" class="form-label fw-semibold">Số phòng / Mã phòng <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="room_number" name="room_number" value="<?= e($room['room_number']) ?>" required placeholder="VD: 101, 102, H1">
                        </div>

                        <div class="col-md-4">
                            <label for="capacity" class="form-label fw-semibold">Sức chứa tối đa (người) <span class="required-star">*</span></label>
                            <input type="number" class="form-control" id="capacity" name="capacity" value="<?= (int)$room['capacity'] ?>" min="1" max="20" required>
                        </div>

                        <div class="col-md-4">
                            <label for="price_per_night" class="form-label fw-semibold">Giá mỗi đêm (VNĐ) <span class="required-star">*</span></label>
                            <input type="number" class="form-control" id="price_per_night" name="price_per_night" value="<?= (float)$room['price_per_night'] ?>" step="10000" min="50000" required>
                        </div>

                        <div class="col-12">
                            <label for="amenities" class="form-label fw-semibold">Tiện nghi trong phòng</label>
                            <input type="text" class="form-control" id="amenities" name="amenities" value="<?= e($room['amenities']) ?>" placeholder="VD: Điều hòa, Tủ lạnh mini, TV 43 inch, Nước nóng, Ban công">
                        </div>

                        <div class="col-md-8">
                            <label for="image" class="form-label fw-semibold">Hình ảnh phòng</label>
                            <input type="file" class="form-control" id="image" name="image" accept=".jpg,.jpeg,.png,.webp">
                            <div class="form-text">Hỗ trợ JPG, PNG, WEBP. Để trống nếu giữ nguyên ảnh hiện tại.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label fw-semibold">Trạng thái phòng</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= $room['status'] === 'active' ? 'selected' : '' ?>>Đang hoạt động (active)</option>
                                <option value="inactive" <?= $room['status'] === 'inactive' ? 'selected' : '' ?>>Ngừng hoạt động (inactive)</option>
                            </select>
                        </div>

                        <?php if (!empty($room['image'])): ?>
                            <div class="col-12">
                                <label class="form-label fw-semibold text-muted">Hình ảnh phòng hiện tại:</label>
                                <div>
                                    <img src="/assets/images/<?= e($room['image']) ?>" alt="Ảnh phòng" class="rounded border" style="max-height: 120px;">
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 pt-3 border-top mt-4">
                            <button type="submit" class="btn btn-primary px-4 py-2">
                                <i class="bi bi-save me-1"></i> <?= $is_edit ? 'Lưu thay đổi' : 'Thêm mới phòng' ?>
                            </button>
                            <a href="/admin/rooms.php" class="btn btn-light px-4 py-2 ms-2">Hủy bỏ</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
