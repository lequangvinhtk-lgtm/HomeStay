<?php
/**
 * Form Thêm mới hoặc Chỉnh sửa Cơ sở lưu trú (Khách sạn / Homestay)
 * Kèm tính năng upload ảnh đơn giản bằng move_uploaded_file
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = ($id > 0);
$page_title = $is_edit ? 'Chỉnh sửa Cơ sở lưu trú' : 'Thêm Cơ sở lưu trú mới';

$errors = [];
$property = [
    'name'        => '',
    'type'        => 'hotel',
    'address'     => '',
    'city'        => '',
    'description' => '',
    'amenities'   => '',
    'image'       => '',
    'status'      => 'active',
];

// Nếu là chế độ sửa, lấy dữ liệu cũ từ CSDL
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Không tìm thấy cơ sở lưu trú yêu cầu.');
        redirect('/admin/properties.php');
    }
    $property = $existing;
}

// Xử lý khi nhấn Lưu form (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $type        = in_array($_POST['type'] ?? '', ['hotel', 'homestay']) ? $_POST['type'] : 'hotel';
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amenities   = trim($_POST['amenities'] ?? '');
    $status      = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    $image_name  = $property['image']; // Giữ ảnh cũ mặc định

    // Kiểm tra tính hợp lệ
    if (empty($name)) {
        $errors[] = 'Tên cơ sở lưu trú không được để trống.';
    }
    if (empty($address)) {
        $errors[] = 'Địa chỉ không được để trống.';
    }
    if (empty($city)) {
        $errors[] = 'Thành phố không được để trống.';
    }

    // Xử lý upload ảnh (nếu có chọn file)
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp   = $_FILES['image']['tmp_name'];
        $file_name  = $_FILES['image']['name'];
        $file_ext   = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = 'Chỉ chấp nhận các định dạng ảnh: jpg, jpeg, png, webp.';
        } else {
            // Đặt tên file duy nhất tránh trùng lặp
            $new_image_name = 'prop_' . time() . '_' . rand(100, 999) . '.' . $file_ext;
            $upload_path = __DIR__ . '/../assets/images/' . $new_image_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $image_name = $new_image_name;
            } else {
                $errors[] = 'Lỗi không thể tải ảnh lên thư mục assets/images/. Vui lòng kiểm tra quyền ghi thư mục.';
            }
        }
    }

    // Nếu không có lỗi, tiến hành thêm hoặc cập nhật CSDL
    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $pdo->prepare("
                UPDATE properties 
                SET name = ?, type = ?, address = ?, city = ?, description = ?, amenities = ?, image = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $type, $address, $city, $description, $amenities, $image_name, $status, $id]);
            set_flash('success', 'Cập nhật cơ sở lưu trú thành công!');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO properties (name, type, address, city, description, amenities, image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $type, $address, $city, $description, $amenities, $image_name, $status]);
            set_flash('success', 'Thêm mới cơ sở lưu trú thành công!');
        }
        redirect('/admin/properties.php');
    } else {
        // Giữ lại dữ liệu vừa nhập để người dùng không phải gõ lại
        $property = [
            'name'        => $name,
            'type'        => $type,
            'address'     => $address,
            'city'        => $city,
            'description' => $description,
            'amenities'   => $amenities,
            'image'       => $image_name,
            'status'      => $status,
        ];
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">
                    <i class="bi <?= $is_edit ? 'bi-pencil-square' : 'bi-plus-circle' ?> text-primary me-2"></i>
                    <?= e($page_title) ?>
                </h3>
                <p class="text-muted small mb-0">Điền các thông tin của khách sạn hoặc homestay</p>
            </div>
            <a href="/admin/properties.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Quay lại danh sách
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
                        <div class="col-md-8">
                            <label for="name" class="form-label fw-semibold">Tên cơ sở lưu trú <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= e($property['name']) ?>" required placeholder="VD: Danang Riverside Hotel">
                        </div>

                        <div class="col-md-4">
                            <label for="type" class="form-label fw-semibold">Loại hình <span class="required-star">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="hotel" <?= $property['type'] === 'hotel' ? 'selected' : '' ?>>Khách sạn (Hotel)</option>
                                <option value="homestay" <?= $property['type'] === 'homestay' ? 'selected' : '' ?>>Homestay</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label for="address" class="form-label fw-semibold">Địa chỉ chi tiết <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="address" name="address" value="<?= e($property['address']) ?>" required placeholder="VD: 258 Trần Hưng Đạo, Sơn Trà">
                        </div>

                        <div class="col-md-4">
                            <label for="city" class="form-label fw-semibold">Thành phố <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="city" name="city" value="<?= e($property['city']) ?>" required placeholder="VD: Đà Nẵng, Hội An, Huế...">
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">Mô tả giới thiệu</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Giới thiệu vị trí, không gian, điểm nổi bật..."><?= e($property['description']) ?></textarea>
                        </div>

                        <div class="col-12">
                            <label for="amenities" class="form-label fw-semibold">Tiện nghi cơ sở (phân cách bằng dấu phẩy)</label>
                            <input type="text" class="form-control" id="amenities" name="amenities" value="<?= e($property['amenities']) ?>" placeholder="VD: Wifi miễn phí, Bể bơi vô cực, Thang máy, Lễ tân 24/7">
                        </div>

                        <div class="col-md-8">
                            <label for="image" class="form-label fw-semibold">Hình ảnh đại diện</label>
                            <input type="file" class="form-control" id="image" name="image" accept=".jpg,.jpeg,.png,.webp">
                            <div class="form-text">Hỗ trợ JPG, PNG, WEBP. Để trống nếu giữ ảnh hiện tại.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label fw-semibold">Trạng thái hoạt động</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= $property['status'] === 'active' ? 'selected' : '' ?>>Đang hoạt động (active)</option>
                                <option value="inactive" <?= $property['status'] === 'inactive' ? 'selected' : '' ?>>Ngừng hoạt động (inactive)</option>
                            </select>
                        </div>

                        <?php if (!empty($property['image'])): ?>
                            <div class="col-12">
                                <label class="form-label fw-semibold text-muted">Hình ảnh hiện tại:</label>
                                <div>
                                    <img src="/assets/images/<?= e($property['image']) ?>" alt="Ảnh cơ sở" class="rounded border" style="max-height: 120px;">
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 pt-3 border-top mt-4">
                            <button type="submit" class="btn btn-primary px-4 py-2">
                                <i class="bi bi-save me-1"></i> <?= $is_edit ? 'Lưu thay đổi' : 'Thêm mới cơ sở' ?>
                            </button>
                            <a href="/admin/properties.php" class="btn btn-light px-4 py-2 ms-2">Hủy bỏ</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
