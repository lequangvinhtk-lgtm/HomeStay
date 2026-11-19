<?php
/**
 * Danh sách toàn bộ Khách sạn và Homestay dành cho Khách hàng
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Danh sách Khách sạn & Homestay';

// Lọc theo loại và thành phố
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$city_filter = isset($_GET['city']) ? trim($_GET['city']) : '';
$keyword     = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// Lấy danh sách thành phố active
$stmtCities = $pdo->query("SELECT DISTINCT city FROM properties WHERE status = 'active' ORDER BY city ASC");
$cities = $stmtCities->fetchAll(PDO::FETCH_COLUMN);

// Truy vấn danh sách cơ sở lưu trú active
$sql = "
    SELECT p.*,
           (SELECT MIN(price_per_night) FROM rooms WHERE property_id = p.id AND status = 'active') as min_price,
           (SELECT COUNT(*) FROM rooms WHERE property_id = p.id AND status = 'active') as total_rooms
    FROM properties p
    WHERE p.status = 'active'
";
$params = [];

if (!empty($type_filter) && in_array($type_filter, ['hotel', 'homestay'])) {
    $sql .= " AND p.type = ? ";
    $params[] = $type_filter;
}

if (!empty($city_filter)) {
    $sql .= " AND p.city = ? ";
    $params[] = $city_filter;
}

if (!empty($keyword)) {
    $sql .= " AND (p.name LIKE ? OR p.address LIKE ? OR p.description LIKE ?) ";
    $kw = "%{$keyword}%";
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

$sql .= " ORDER BY p.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <h2 class="fw-bold mb-1">Khách sạn & Homestay nổi bật</h2>
        <p class="mb-0 opacity-75">Khám phá các điểm dừng chân lý tưởng cho kỳ nghỉ của bạn</p>
    </div>
</div>

<div class="container py-3">
    <!-- Thanh lọc & tìm kiếm -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="/properties.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="keyword" class="form-control" placeholder="Tìm theo tên hoặc địa chỉ..." value="<?= e($keyword) ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <select name="type" class="form-select">
                        <option value="">-- Tất cả loại hình --</option>
                        <option value="hotel" <?= $type_filter === 'hotel' ? 'selected' : '' ?>>Khách sạn (Hotel)</option>
                        <option value="homestay" <?= $type_filter === 'homestay' ? 'selected' : '' ?>>Homestay</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <select name="city" class="form-select">
                        <option value="">-- Tất cả thành phố --</option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?= e($c) ?>" <?= $city_filter === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-funnel me-1"></i> Lọc
                    </button>
                    <?php if (!empty($type_filter) || !empty($city_filter) || !empty($keyword)): ?>
                        <a href="/properties.php" class="btn btn-outline-secondary" title="Xóa lọc">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Danh sách kết quả -->
    <div class="row g-4">
        <?php if (empty($properties)): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-buildings text-muted" style="font-size: 3rem;"></i>
                <h5 class="fw-bold mt-3 text-muted">Không tìm thấy cơ sở lưu trú nào phù hợp</h5>
                <p class="text-muted small">Vui lòng thử thay đổi điều kiện tìm kiếm hoặc chọn thành phố khác.</p>
                <a href="/properties.php" class="btn btn-outline-primary btn-sm">Xem tất cả cơ sở</a>
            </div>
        <?php else: ?>
            <?php foreach ($properties as $p): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm card-hover overflow-hidden">
                        <div class="position-relative">
                            <?php $img_src = !empty($p['image']) ? '/assets/images/' . e($p['image']) : '/assets/images/no_image.jpg'; ?>
                            <img src="<?= $img_src ?>" class="property-card-img" alt="<?= e($p['name']) ?>">
                            <span class="position-absolute top-0 start-0 m-2 badge <?= $p['type'] === 'homestay' ? 'bg-success' : 'bg-primary' ?>">
                                <?= get_property_type_text($p['type']) ?>
                            </span>
                            <span class="position-absolute bottom-0 end-0 m-2 badge bg-dark bg-opacity-75">
                                <?= $p['total_rooms'] ?> phòng khả dụng
                            </span>
                        </div>
                        <div class="card-body d-flex flex-column p-4">
                            <div class="text-muted small mb-1">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($p['city']) ?>
                            </div>
                            <h5 class="card-title fw-bold fs-5 mb-2">
                                <a href="/property_detail.php?id=<?= $p['id'] ?>" class="text-dark text-decoration-none">
                                    <?= e($p['name']) ?>
                                </a>
                            </h5>
                            <p class="text-muted small mb-2"><i class="bi bi-pin-map me-1"></i><?= e($p['address']) ?></p>

                            <?php if (!empty($p['description'])): ?>
                                <p class="card-text small text-muted mb-3 line-clamp-2">
                                    <?= e(mb_strimwidth($p['description'], 0, 110, '...')) ?>
                                </p>
                            <?php endif; ?>

                            <!-- Tiện nghi dạng tag -->
                            <?php if (!empty($p['amenities'])): ?>
                                <div class="mb-3">
                                    <?php 
                                    $tags = array_map('trim', explode(',', $p['amenities']));
                                    $show_tags = array_slice($tags, 0, 3);
                                    foreach ($show_tags as $t): ?>
                                        <span class="badge-amenity"><i class="bi bi-check2 text-success me-1"></i><?= e($t) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($tags) > 3): ?>
                                        <span class="badge-amenity text-muted">+<?= count($tags) - 3 ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block">Giá chỉ từ</small>
                                    <span class="fs-5 fw-bold text-primary">
                                        <?= $p['min_price'] ? format_money($p['min_price']) : 'Liên hệ' ?>
                                    </span>
                                </div>
                                <a href="/property_detail.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm px-3 fw-semibold">
                                    Xem phòng <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
