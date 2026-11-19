<?php
/**
 * Chi tiết Cơ sở lưu trú & Danh sách các phòng cụ thể
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    set_flash('danger', 'Cơ sở lưu trú không hợp lệ.');
    redirect('/properties.php');
}

// Lấy thông tin cơ sở lưu trú
$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ? AND status = 'active'");
$stmt->execute([$id]);
$property = $stmt->fetch();

if (!$property) {
    set_flash('danger', 'Cơ sở lưu trú không tồn tại hoặc đã ngừng hoạt động.');
    redirect('/properties.php');
}

$page_title = $property['name'];

// Lấy danh sách các phòng đang active thuộc cơ sở này
$stmtRooms = $pdo->prepare("
    SELECT r.*, rt.name as room_type_name, rt.description as room_type_desc
    FROM rooms r
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE r.property_id = ? AND r.status = 'active' AND rt.status = 'active'
    ORDER BY r.price_per_night ASC
");
$stmtRooms->execute([$id]);
$rooms = $stmtRooms->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb điều hướng -->
<div class="bg-light border-bottom py-2">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/index.php" class="text-decoration-none">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="/properties.php" class="text-decoration-none">Khách sạn & Homestay</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($property['name']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container py-4">
    <!-- Tiêu đề & Thông tin cơ bản -->
    <div class="row align-items-center mb-3">
        <div class="col-md-8">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge <?= $property['type'] === 'homestay' ? 'bg-success' : 'bg-primary' ?> fs-6">
                    <?= get_property_type_text($property['type']) ?>
                </span>
                <span class="text-muted"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($property['city']) ?></span>
            </div>
            <h2 class="fw-bold mb-1"><?= e($property['name']) ?></h2>
            <p class="text-muted mb-0"><i class="bi bi-map me-1"></i><?= e($property['address']) ?></p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="#danh-sach-phong" class="btn btn-primary btn-lg shadow-sm">
                <i class="bi bi-calendar-check me-2"></i>Chọn phòng ngay
            </a>
        </div>
    </div>

    <!-- Hình ảnh & Giới thiệu chung -->
    <div class="row g-4 mb-5">
        <div class="col-lg-7">
            <?php $img_src = !empty($property['image']) ? '/assets/images/' . e($property['image']) : '/assets/images/no_image.jpg'; ?>
            <img src="<?= $img_src ?>" alt="<?= e($property['name']) ?>" class="detail-main-img shadow-sm">
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Giới thiệu cơ sở</h5>
                    <p class="text-muted small mb-4" style="line-height: 1.7;">
                        <?= nl2br(e($property['description'] ?? 'Chưa có mô tả chi tiết cho cơ sở lưu trú này.')) ?>
                    </p>

                    <h6 class="fw-bold mb-3"><i class="bi bi-stars text-warning me-2"></i>Tiện nghi nổi bật</h6>
                    <?php if (!empty($property['amenities'])): ?>
                        <div class="mb-4">
                            <?php 
                            $tags = array_map('trim', explode(',', $property['amenities']));
                            foreach ($tags as $t): ?>
                                <span class="badge-amenity mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-1"></i><?= e($t) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small">Cơ sở cung cấp các tiện nghi nghỉ dưỡng tiêu chuẩn.</p>
                    <?php endif; ?>

                    <div class="mt-auto p-3 bg-light rounded-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Nhận phòng (Check-in):</span>
                            <span class="fw-bold">Từ 14:00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Trả phòng (Check-out):</span>
                            <span class="fw-bold">Trước 12:00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Danh sách Phòng của cơ sở -->
    <div id="danh-sach-phong" class="pt-3">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <div>
                <h3 class="fw-bold mb-0">Các phòng đang khả dụng</h3>
                <p class="text-muted small mb-0">Lựa chọn phòng phù hợp với số lượng khách và sở thích của bạn</p>
            </div>
            <span class="badge bg-secondary fs-6"><?= count($rooms) ?> phòng</span>
        </div>

        <?php if (empty($rooms)): ?>
            <div class="alert alert-info text-center py-4">
                <i class="bi bi-exclamation-circle fs-3 d-block mb-2"></i>
                Hiện tại cơ sở này chưa có phòng nào đang mở đặt. Quý khách vui lòng quay lại sau!
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($rooms as $r): ?>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100 overflow-hidden card-hover">
                            <div class="row g-0 h-100">
                                <div class="col-md-5 position-relative">
                                    <?php $room_img = !empty($r['image']) ? '/assets/images/' . e($r['image']) : '/assets/images/no_image.jpg'; ?>
                                    <img src="<?= $room_img ?>" alt="Phòng <?= e($r['room_number']) ?>" class="h-100 w-100" style="object-fit: cover; min-height: 220px;">
                                    <span class="position-absolute top-0 start-0 m-2 badge bg-dark bg-opacity-75">
                                        Phòng <?= e($r['room_number']) ?>
                                    </span>
                                </div>
                                <div class="col-md-7 d-flex flex-column p-3 p-md-4">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h5 class="fw-bold mb-0"><?= e($r['room_type_name']) ?></h5>
                                    </div>

                                    <div class="text-muted small mb-2">
                                        <i class="bi bi-people-fill text-primary me-1"></i> Sức chứa: <strong><?= $r['capacity'] ?> người lớn</strong>
                                    </div>

                                    <?php if (!empty($r['room_type_desc'])): ?>
                                        <p class="text-muted small mb-2"><?= e($r['room_type_desc']) ?></p>
                                    <?php endif; ?>

                                    <?php if (!empty($r['amenities'])): ?>
                                        <div class="small text-muted mb-3">
                                            <i class="bi bi-check2-circle text-success me-1"></i><?= e($r['amenities']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fs-5 fw-bold text-danger"><?= format_money($r['price_per_night']) ?></span>
                                            <small class="text-muted">/đêm</small>
                                        </div>
                                        <?php
                                        $book_url = '/booking_create.php?room_id=' . $r['id'];
                                        if (!empty($_GET['check_in'])) $book_url .= '&check_in=' . urlencode($_GET['check_in']);
                                        if (!empty($_GET['check_out'])) $book_url .= '&check_out=' . urlencode($_GET['check_out']);
                                        if (!empty($_GET['guests'])) $book_url .= '&guests=' . (int)$_GET['guests'];
                                        ?>
                                        <a href="<?= $book_url ?>" class="btn btn-primary fw-semibold btn-sm px-3 shadow-sm">
                                            <i class="bi bi-calendar-plus me-1"></i> Đặt phòng này
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
