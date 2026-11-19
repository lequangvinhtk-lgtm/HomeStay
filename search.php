<?php
/**
 * Trang Tìm kiếm phòng nghỉ theo Địa điểm, Thời gian lưu trú, Số khách và Khoảng giá
 * Tích hợp thuật toán CHỐNG TRÙNG LỊCH (5.3) theo tiêu chuẩn
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Tìm kiếm phòng khả dụng';

// 1. Nhận các tham số tìm kiếm từ URL (GET)
$city      = trim($_GET['city'] ?? '');
$type      = trim($_GET['type'] ?? '');
$check_in  = trim($_GET['check_in'] ?? '');
$check_out = trim($_GET['check_out'] ?? '');
$guests    = (int)($_GET['guests'] ?? 0);
$max_price = (float)($_GET['max_price'] ?? 0);

$errors = [];

// 2. Kiểm tra tính hợp lệ của ngày tháng (nếu người dùng có nhập)
$has_dates = (!empty($check_in) && !empty($check_out));
if ($has_dates) {
    $today = date('Y-m-d');
    if ($check_in < $today) {
        $errors[] = 'Ngày nhận phòng không thể ở trong quá khứ.';
    }
    if ($check_out <= $check_in) {
        $errors[] = 'Ngày trả phòng phải sau ngày nhận phòng ít nhất 1 đêm.';
    }
}

// Lấy danh sách thành phố active
$stmtCities = $pdo->query("SELECT DISTINCT city FROM properties WHERE status = 'active' ORDER BY city ASC");
$cities = $stmtCities->fetchAll(PDO::FETCH_COLUMN);

$rooms = [];

// 3. Xây dựng câu truy vấn tìm phòng
if (empty($errors)) {
    $sql = "
        SELECT r.*, p.name as property_name, p.city as property_city, p.type as property_type, p.address as property_address,
               rt.name as room_type_name
        FROM rooms r
        JOIN properties p ON r.property_id = p.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE r.status = 'active' 
          AND p.status = 'active'
          AND rt.status = 'active'
    ";
    $params = [];

    // Lọc theo thành phố (dùng LIKE để bắt chính xác kể cả khi gõ không dấu hoặc có dấu)
    if (!empty($city)) {
        $sql .= " AND p.city LIKE ? ";
        $params[] = "%" . trim($city) . "%";
    }

    // Lọc theo loại hình khách sạn / homestay
    if (!empty($type) && in_array($type, ['hotel', 'homestay'])) {
        $sql .= " AND p.type = ? ";
        $params[] = $type;
    }

    // Lọc theo số lượng khách
    if ($guests > 0) {
        $sql .= " AND r.capacity >= ? ";
        $params[] = $guests;
    }

    // Lọc theo mức giá tối đa
    if ($max_price > 0) {
        $sql .= " AND r.price_per_night <= ? ";
        $params[] = $max_price;
    }

    // THUẬT TOÁN CHỐNG TRÙNG LỊCH PHÒNG (Mục 5.3):
    // Chỉ loại bỏ phòng khi đã có đơn 'confirmed' hoặc 'checked_in' bị trùng thời gian:
    // Điều kiện trùng: (ngày_nhận_mới < ngày_trả_cũ AND ngày_trả_mới > ngày_nhận_cũ)
    if ($has_dates) {
        $sql .= "
            AND NOT EXISTS (
                SELECT 1 FROM bookings b
                WHERE b.room_id = r.id
                  AND b.status IN ('confirmed', 'checked_in')
                  AND (? < b.check_out AND ? > b.check_in)
            )
        ";
        $params[] = $check_in;
        $params[] = $check_out;
    }

    $sql .= " ORDER BY r.price_per_night ASC ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <h2 class="fw-bold mb-1"><i class="bi bi-search me-2"></i>Tìm kiếm phòng nghỉ</h2>
        <p class="mb-0 opacity-75">Hệ thống tự động lọc phòng trống, kiểm tra lịch và hiển thị giá tốt nhất</p>
    </div>
</div>

<div class="container py-2">
    <!-- Form điều kiện tìm kiếm -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form action="/search.php" method="GET">
                <div class="row g-2 align-items-end text-start">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-semibold small mb-1 text-nowrap"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Điểm đến</label>
                        <select name="city" class="form-select" style="height: 42px;">
                            <option value="">-- Tất cả điểm đến --</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= e($c) ?>" <?= $city === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label fw-semibold small mb-1 text-nowrap"><i class="bi bi-building me-1 text-primary"></i> Loại hình</label>
                        <select name="type" class="form-select" style="height: 42px;">
                            <option value="">Tất cả</option>
                            <option value="hotel" <?= $type === 'hotel' ? 'selected' : '' ?>>Khách sạn</option>
                            <option value="homestay" <?= $type === 'homestay' ? 'selected' : '' ?>>Homestay</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-semibold small mb-1 text-nowrap"><i class="bi bi-calendar-event me-1 text-primary"></i> Ngày nhận</label>
                        <input type="date" name="check_in" class="form-control" style="height: 42px;" value="<?= e($check_in) ?>" min="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-semibold small mb-1 text-nowrap"><i class="bi bi-calendar-check me-1 text-primary"></i> Ngày trả</label>
                        <input type="date" name="check_out" class="form-control" style="height: 42px;" value="<?= e($check_out) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    </div>

                    <div class="col-lg-1 col-md-4 col-4">
                        <label class="form-label fw-semibold small mb-1 text-nowrap"><i class="bi bi-people me-1"></i> Khách</label>
                        <select name="guests" class="form-select" style="height: 42px;">
                            <option value="0">Tất cả</option>
                            <option value="1" <?= $guests === 1 ? 'selected' : '' ?>>1</option>
                            <option value="2" <?= $guests === 2 ? 'selected' : '' ?>>2</option>
                            <option value="3" <?= $guests === 3 ? 'selected' : '' ?>>3</option>
                            <option value="4" <?= $guests === 4 ? 'selected' : '' ?>>4+</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-12 col-8">
                        <button type="submit" class="btn btn-warning w-100 fw-bold text-dark d-flex align-items-center justify-content-center text-nowrap" style="height: 42px;">
                            <i class="bi bi-search me-1"></i><span>Tìm phòng</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Thông báo lỗi nếu có ngày không hợp lệ -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Kết quả tìm kiếm -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">
            Kết quả tìm kiếm: <span class="text-primary"><?= count($rooms) ?></span> phòng khả dụng
        </h5>
        <?php if ($has_dates): ?>
            <span class="badge bg-light text-dark border">
                Thời gian: <strong><?= format_date($check_in) ?></strong> → <strong><?= format_date($check_out) ?></strong>
            </span>
        <?php endif; ?>
    </div>

    <?php if (empty($rooms)): ?>
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <i class="bi bi-calendar-x text-muted" style="font-size: 3.5rem;"></i>
                <h5 class="fw-bold mt-3 text-muted">Không tìm thấy phòng phù hợp trong khoảng thời gian này</h5>
                <p class="text-muted small">Các phòng có thể đã có khách đặt hoặc chưa thỏa mãn số lượng người. Bạn hãy thử chọn ngày khác hoặc điểm đến khác nhé!</p>
                <a href="/search.php" class="btn btn-outline-primary btn-sm">Xóa bộ lọc tìm kiếm</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($rooms as $r): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 card-hover overflow-hidden">
                        <div class="position-relative">
                            <?php $img_src = !empty($r['image']) ? '/assets/images/' . e($r['image']) : '/assets/images/no_image.jpg'; ?>
                            <img src="<?= $img_src ?>" class="room-card-img" alt="Phòng <?= e($r['room_number']) ?>">
                            <span class="position-absolute top-0 start-0 m-2 badge <?= $r['property_type'] === 'homestay' ? 'bg-success' : 'bg-primary' ?>">
                                <?= get_property_type_text($r['property_type']) ?>
                            </span>
                            <span class="position-absolute bottom-0 end-0 m-2 badge bg-dark bg-opacity-75">
                                <i class="bi bi-people-fill me-1"></i><?= $r['capacity'] ?> khách
                            </span>
                        </div>
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-light text-primary border"><?= e($r['room_type_name']) ?></span>
                                <small class="text-muted"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($r['property_city']) ?></small>
                            </div>

                            <h5 class="fw-bold fs-6 my-2">
                                Phòng <?= e($r['room_number']) ?> - <?= e($r['property_name']) ?>
                            </h5>

                            <p class="text-muted small mb-2"><i class="bi bi-pin-map me-1"></i><?= e($r['property_address']) ?></p>

                            <?php if (!empty($r['amenities'])): ?>
                                <p class="text-muted small text-truncate mb-3" title="<?= e($r['amenities']) ?>">
                                    <i class="bi bi-check2 text-success me-1"></i><?= e($r['amenities']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fs-5 fw-bold text-danger"><?= format_money($r['price_per_night']) ?></span>
                                    <small class="text-muted">/đêm</small>
                                </div>
                                <?php
                                $book_url = '/booking_create.php?room_id=' . $r['id'];
                                if ($has_dates) {
                                    $book_url .= '&check_in=' . urlencode($check_in) . '&check_out=' . urlencode($check_out);
                                }
                                if ($guests > 0) {
                                    $book_url .= '&guests=' . $guests;
                                }
                                ?>
                                <a href="<?= $book_url ?>" class="btn btn-primary btn-sm fw-semibold">
                                    <i class="bi bi-calendar-plus me-1"></i> Đặt ngay
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
