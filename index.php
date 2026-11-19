<?php
/**
 * Trang chủ Website Đặt phòng Khách sạn & Homestay (StayEasy)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Trang chủ - Đặt phòng Khách sạn & Homestay giá tốt';

// 1. Lấy danh sách thành phố có cơ sở lưu trú active để hiển thị trong select
$stmtCities = $pdo->query("SELECT DISTINCT city FROM properties WHERE status = 'active' ORDER BY city ASC");
$cities = $stmtCities->fetchAll(PDO::FETCH_COLUMN);

// 2. Lấy 4 cơ sở lưu trú nổi bật đang hoạt động
$stmtFeaturedProps = $pdo->query("
    SELECT p.*, 
           (SELECT MIN(price_per_night) FROM rooms WHERE property_id = p.id AND status = 'active') as min_price,
           (SELECT COUNT(*) FROM rooms WHERE property_id = p.id AND status = 'active') as total_rooms
    FROM properties p
    WHERE p.status = 'active'
    ORDER BY p.id ASC
    LIMIT 4
");
$featured_properties = $stmtFeaturedProps->fetchAll();

// 3. Lấy 6 phòng gợi ý giá tốt đang hoạt động
$stmtFeaturedRooms = $pdo->query("
    SELECT r.*, p.name as property_name, p.city as property_city, p.type as property_type, rt.name as room_type_name
    FROM rooms r
    JOIN properties p ON r.property_id = p.id
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE r.status = 'active' AND p.status = 'active'
    ORDER BY r.price_per_night ASC
    LIMIT 6
");
$featured_rooms = $stmtFeaturedRooms->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner & Form Tìm Kiếm Nhanh -->
<section class="hero-banner text-center position-relative">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-3">
                    <i class="bi bi-star-fill me-1"></i> Trải nghiệm lưu trú tuyệt vời nhất
                </span>
                <h1 class="display-5 fw-bold mb-3">Tìm & Đặt Khách Sạn, Homestay Ưng Ý</h1>
                <p class="lead mb-4 opacity-90">
                    Khám phá hàng ngàn phòng nghỉ tiện nghi tại Đà Nẵng, Hội An, Huế với mức giá ưu đãi và dịch vụ chất lượng.
                </p>

                <!-- Box tìm kiếm phòng nhanh -->
                <div class="card shadow-lg border-0 text-dark rounded-4">
                    <div class="card-body p-3 p-md-4">
                        <form action="/search.php" method="GET">
                            <div class="row g-3 align-items-end text-start">
                                <!-- Điểm đến -->
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label small fw-bold text-muted mb-1 text-nowrap">
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> Điểm đến / Thành phố
                                    </label>
                                    <select name="city" class="form-select py-2 shadow-sm" style="height: 44px;">
                                        <option value="">Tất cả địa điểm</option>
                                        <?php foreach ($cities as $c): ?>
                                            <option value="<?= e($c) ?>"><?= e($c) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Ngày nhận phòng -->
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label small fw-bold text-muted mb-1 text-nowrap">
                                        <i class="bi bi-calendar-event text-primary me-1"></i> Ngày nhận phòng
                                    </label>
                                    <input type="date" name="check_in" class="form-control py-2 shadow-sm" style="height: 44px;" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
                                </div>

                                <!-- Ngày trả phòng -->
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label small fw-bold text-muted mb-1 text-nowrap">
                                        <i class="bi bi-calendar-check text-primary me-1"></i> Ngày trả phòng
                                    </label>
                                    <input type="date" name="check_out" class="form-control py-2 shadow-sm" style="height: 44px;" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                                </div>

                                <!-- Số khách -->
                                <div class="col-lg-1 col-md-3 col-4">
                                    <label class="form-label small fw-bold text-muted mb-1 text-nowrap">
                                        <i class="bi bi-people-fill text-secondary me-1"></i> Khách
                                    </label>
                                    <select name="guests" class="form-select py-2 shadow-sm" style="height: 44px;">
                                        <option value="1">1</option>
                                        <option value="2" selected>2</option>
                                        <option value="3">3</option>
                                        <option value="4">4+</option>
                                    </select>
                                </div>

                                <!-- Nút tìm kiếm -->
                                <div class="col-lg-2 col-md-3 col-8">
                                    <button type="submit" class="btn btn-warning w-100 fw-bold shadow-sm text-dark d-flex align-items-center justify-content-center text-nowrap" style="height: 44px;">
                                        <i class="bi bi-search me-2"></i><span>Tìm phòng</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- Các ưu điểm nổi bật của hệ thống -->
<section class="py-5 bg-white border-bottom">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="p-3">
                    <div class="text-primary mb-3">
                        <i class="bi bi-shield-check" style="font-size: 2.5rem;"></i>
                    </div>
                    <h5 class="fw-bold">Xác nhận nhanh chóng</h5>
                    <p class="text-muted small mb-0">Hệ thống xử lý đơn tức thì, quản lý lịch phòng thông minh, đảm bảo không bị trùng phòng.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3">
                    <div class="text-primary mb-3">
                        <i class="bi bi-tag" style="font-size: 2.5rem;"></i>
                    </div>
                    <h5 class="fw-bold">Giá cả minh bạch</h5>
                    <p class="text-muted small mb-0">Đơn giá niêm yết rõ ràng theo từng đêm, không phát sinh phụ phí ẩn khi nhận phòng.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3">
                    <div class="text-primary mb-3">
                        <i class="bi bi-headset" style="font-size: 2.5rem;"></i>
                    </div>
                    <h5 class="fw-bold">Hỗ trợ tận tâm</h5>
                    <p class="text-muted small mb-0">Đội ngũ hỗ trợ nhiệt tình, giải đáp mọi thắc mắc và hỗ trợ nhận - trả phòng linh hoạt.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Điểm đến thịnh hành với hình ảnh phong cảnh -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-4">
            <span class="badge bg-primary bg-opacity-10 text-primary fw-bold mb-2">KHÁM PHÁ VIỆT NAM</span>
            <h3 class="fw-bold mb-1">Các điểm đến du lịch hàng đầu</h3>
            <p class="text-muted small">Lựa chọn những thành phố tuyệt đẹp cho kỳ nghỉ đáng nhớ của bạn</p>
        </div>

        <div class="row g-4">
            <!-- Đà Nẵng -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm overflow-hidden card-hover position-relative h-100 rounded-4">
                    <img src="/assets/images/danang.jpg" alt="Đà Nẵng" style="height: 260px; object-fit: cover;">
                    <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-end p-4" style="background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 60%, transparent 100%);">
                        <h4 class="text-white fw-bold mb-1">Đà Nẵng</h4>
                        <p class="text-white-50 small mb-3">Thành phố đáng sống với biển Mỹ Khê và cầu Rồng rực rỡ</p>
                        <a href="/search.php?city=Đà+Nẵng" class="btn btn-light btn-sm fw-semibold w-fit align-self-start">
                            Khám phá phòng <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Hội An / Itachi Home -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm overflow-hidden card-hover position-relative h-100 rounded-4">
                    <img src="/assets/images/hoian.jpg" alt="Hội An" style="height: 260px; object-fit: cover;">
                    <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-end p-4" style="background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 60%, transparent 100%);">
                        <div class="badge bg-success mb-2 align-self-start">Nổi bật: Itachi Home</div>
                        <h4 class="text-white fw-bold mb-1">Hội An</h4>
                        <p class="text-white-50 small mb-3">Phố cổ lung linh đèn lồng & homestay biển An Bàng thơ mộng</p>
                        <a href="/search.php?city=Hội+An" class="btn btn-light btn-sm fw-semibold w-fit align-self-start">
                            Khám phá phòng <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Huế -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm overflow-hidden card-hover position-relative h-100 rounded-4">
                    <img src="/assets/images/hue.jpg" alt="Huế" style="height: 260px; object-fit: cover;">
                    <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-end p-4" style="background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 60%, transparent 100%);">
                        <h4 class="text-white fw-bold mb-1">Huế</h4>
                        <p class="text-white-50 small mb-3">Cố đô trầm mặc, di sản cổ kính bên dòng sông Hương</p>
                        <a href="/search.php?city=Huế" class="btn btn-light btn-sm fw-semibold w-fit align-self-start">
                            Khám phá phòng <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Danh sách Cơ sở lưu trú nổi bật -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="badge bg-primary bg-opacity-10 text-primary fw-bold mb-2">ĐIỂM ĐẾN NỔI BẬT</span>
                <h3 class="fw-bold mb-0">Khách sạn & Homestay được yêu thích</h3>
            </div>
            <a href="/properties.php" class="btn btn-outline-primary btn-sm">
                Xem tất cả <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($featured_properties as $p): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm card-hover overflow-hidden">
                        <div class="position-relative">
                            <?php $img_src = !empty($p['image']) ? '/assets/images/' . e($p['image']) : '/assets/images/no_image.jpg'; ?>
                            <img src="<?= $img_src ?>" class="property-card-img" alt="<?= e($p['name']) ?>">
                            <span class="position-absolute top-0 start-0 m-2 badge <?= $p['type'] === 'homestay' ? 'bg-success' : 'bg-primary' ?>">
                                <?= get_property_type_text($p['type']) ?>
                            </span>
                        </div>
                        <div class="card-body d-flex flex-column p-3">
                            <div class="text-muted small mb-1">
                                <i class="bi bi-geo-alt text-danger me-1"></i><?= e($p['city']) ?>
                            </div>
                            <h5 class="card-title fw-bold fs-6 mb-2 text-truncate" title="<?= e($p['name']) ?>">
                                <a href="/property_detail.php?id=<?= $p['id'] ?>" class="text-dark text-decoration-none">
                                    <?= e($p['name']) ?>
                                </a>
                            </h5>
                            <p class="card-text small text-muted text-truncate mb-3"><?= e($p['address']) ?></p>

                            <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block">Giá chỉ từ</small>
                                    <span class="fw-bold text-primary">
                                        <?= $p['min_price'] ? format_money($p['min_price']) : 'Liên hệ' ?>
                                    </span>
                                </div>
                                <a href="/property_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    Chi tiết
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Danh sách Phòng gợi ý giá tốt -->
<section class="py-5 bg-white border-top">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="badge bg-success bg-opacity-10 text-success fw-bold mb-2">GỢI Ý PHÒNG TỐT</span>
                <h3 class="fw-bold mb-0">Phòng nghỉ tiện nghi, giá hấp dẫn</h3>
            </div>
            <a href="/search.php" class="btn btn-outline-success btn-sm">
                Tìm phòng khác <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($featured_rooms as $r): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm card-hover overflow-hidden">
                        <div class="position-relative">
                            <?php $img_src = !empty($r['image']) ? '/assets/images/' . e($r['image']) : '/assets/images/no_image.jpg'; ?>
                            <img src="<?= $img_src ?>" class="room-card-img" alt="Phòng <?= e($r['room_number']) ?>">
                            <span class="position-absolute bottom-0 end-0 m-2 badge bg-dark bg-opacity-75">
                                <i class="bi bi-people-fill me-1"></i>Tối đa <?= $r['capacity'] ?> khách
                            </span>
                        </div>
                        <div class="card-body d-flex flex-column p-3">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="badge bg-light text-primary border"><?= e($r['room_type_name']) ?></span>
                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($r['property_city']) ?></small>
                            </div>

                            <h5 class="fw-bold fs-6 my-2">
                                Phòng <?= e($r['room_number']) ?> - <?= e($r['property_name']) ?>
                            </h5>

                            <?php if (!empty($r['amenities'])): ?>
                                <p class="text-muted small text-truncate mb-3" title="<?= e($r['amenities']) ?>">
                                    <i class="bi bi-check2-circle text-success me-1"></i><?= e($r['amenities']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fs-5 fw-bold text-danger"><?= format_money($r['price_per_night']) ?></span>
                                    <small class="text-muted">/đêm</small>
                                </div>
                                <a href="/booking_create.php?room_id=<?= $r['id'] ?>" class="btn btn-primary btn-sm fw-semibold">
                                    <i class="bi bi-calendar-plus me-1"></i> Đặt ngay
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Banner giới thiệu dịch vụ nghỉ dưỡng -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="card border-0 rounded-4 overflow-hidden shadow-sm" style="background: linear-gradient(rgba(10, 30, 60, 0.65), rgba(10, 30, 60, 0.75)), url('/assets/images/promo_banner.jpg') center/cover no-repeat;">
            <div class="card-body p-5 text-white text-center">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-3">
                    <i class="bi bi-heart-fill text-danger me-1"></i> ƯU ĐÃI ĐẶC BIỆT
                </span>
                <h2 class="display-6 fw-bold mb-3">Nghỉ dưỡng tuyệt vời tại Itachi Home & Các Cơ sở hàng đầu</h2>
                <p class="lead opacity-90 mx-auto mb-4" style="max-width: 700px;">
                    Không gian xanh mát, phòng ốc hiện đại, dịch vụ tận tâm. Đặt phòng hôm nay để nhận mức giá ưu đãi và giữ phòng ngay lập tức.
                </p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="/property_detail.php?id=2" class="btn btn-warning btn-lg fw-bold text-dark px-4 shadow-sm">
                        <i class="bi bi-house-door-fill me-1"></i> Khám phá Itachi Home
                    </a>
                    <a href="/properties.php" class="btn btn-outline-light btn-lg fw-semibold px-4">
                        Xem tất cả phòng
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
