<?php
/**
 * Trang Tạo yêu cầu Đặt phòng (Dành cho Khách hàng & Khách vãng lai)
 * Quy tắc:
 * 1. Cho phép xem phòng, tính tiền linh hoạt
 * 2. Nếu đã đăng nhập: tự động nạp thông tin khách
 * 3. Nếu chưa đăng nhập: tự động tạo tài khoản nhanh qua email/SĐT khi gửi đơn mà không bị chặn
 * 4. Ngày trả > ngày nhận; ngày nhận không ở quá khứ
 * 5. Số người <= sức chứa của phòng
 * 6. Lưu đơn giá niêm yết tại thời điểm đặt (price_per_night)
 * 7. Trạng thái khởi tạo: 'pending' (chưa giữ phòng)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$room_id = (int)($_POST['room_id'] ?? ($_GET['room_id'] ?? 0));

// Nếu chưa chọn phòng cụ thể, chọn mặc định phòng đầu tiên đang active để không bao giờ bị lỗi trắng trang
if ($room_id <= 0) {
    $firstRoom = $pdo->query("SELECT id FROM rooms WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn();
    $room_id = (int)$firstRoom;
}

// Lấy thông tin phòng và cơ sở lưu trú (phải đang active)
$stmtRoom = $pdo->prepare("
    SELECT r.*, p.name as property_name, p.address as property_address, p.city as property_city,
           p.type as property_type, p.status as property_status, rt.name as room_type_name
    FROM rooms r
    JOIN properties p ON r.property_id = p.id
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE r.id = ? AND r.status = 'active' AND p.status = 'active'
");
$stmtRoom->execute([$room_id]);
$room = $stmtRoom->fetch();

if (!$room) {
    set_flash('danger', 'Phòng nghỉ không tồn tại hoặc cơ sở đã ngừng hoạt động.');
    redirect('/properties.php');
}

$page_title = 'Đặt phòng ' . $room['room_number'] . ' - ' . $room['property_name'];

$user = current_user();
$customer = null;

if ($user) {
    $stmtCustomer = $pdo->prepare("SELECT id, full_name, phone FROM customers WHERE user_id = ?");
    $stmtCustomer->execute([$user['id']]);
    $customer = $stmtCustomer->fetch();
}

// Thiết lập giá trị mặc định cho form
$check_in      = $_POST['check_in'] ?? ($_GET['check_in'] ?? date('Y-m-d'));
$check_out     = $_POST['check_out'] ?? ($_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day')));
$guests        = (int)($_POST['guests'] ?? ($_GET['guests'] ?? min(2, $room['capacity'])));
$contact_name  = trim($_POST['contact_name'] ?? ($customer['full_name'] ?? ''));
$contact_phone = trim($_POST['contact_phone'] ?? ($customer['phone'] ?? ''));
$contact_email = trim($_POST['contact_email'] ?? ($user['email'] ?? ''));
$note          = trim($_POST['note'] ?? '');

$errors = [];

// Xử lý gửi đơn đặt phòng (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $today = date('Y-m-d');

    // 1. Kiểm tra ngày nhận & ngày trả
    if (empty($check_in) || empty($check_out)) {
        $errors[] = 'Vui lòng chọn ngày nhận phòng và ngày trả phòng.';
    } else {
        if ($check_in < $today) {
            $errors[] = 'Ngày nhận phòng không thể ở trong quá khứ.';
        }
        if ($check_out <= $check_in) {
            $errors[] = 'Ngày trả phòng phải sau ngày nhận phòng ít nhất 1 đêm.';
        }
    }

    // 2. Kiểm tra số lượng khách
    if ($guests < 1) {
        $errors[] = 'Số người lưu trú tối thiểu là 1 người.';
    } elseif ($guests > $room['capacity']) {
        $errors[] = "Số lượng khách ({$guests} người) vượt quá sức chứa tối đa của phòng này ({$room['capacity']} người).";
    }

    // 3. Kiểm tra thông tin liên hệ
    if (empty($contact_name)) {
        $errors[] = 'Vui lòng nhập họ và tên người nhận phòng.';
    }
    if (empty($contact_phone)) {
        $errors[] = 'Vui lòng nhập số điện thoại người liên hệ.';
    }

    // 4. Xác định hoặc tạo tài khoản khách hàng
    $customer_id = $customer['id'] ?? null;

    if (!$customer_id) {
        if (empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Vui lòng nhập địa chỉ email hợp lệ để nhận thông tin đơn đặt phòng.';
        } else {
            // Kiểm tra xem email này đã có tài khoản users chưa
            $stmtChkUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtChkUser->execute([$contact_email]);
            $existing_user_id = $stmtChkUser->fetchColumn();

            if ($existing_user_id) {
                // Email đã có -> lấy customer_id
                $stmtGetCust = $pdo->prepare("SELECT id FROM customers WHERE user_id = ?");
                $stmtGetCust->execute([$existing_user_id]);
                $customer_id = $stmtGetCust->fetchColumn();

                if (!$customer_id) {
                    $stmtCreateCust = $pdo->prepare("INSERT INTO customers (user_id, full_name, phone, address) VALUES (?, ?, ?, '')");
                    $stmtCreateCust->execute([$existing_user_id, $contact_name, $contact_phone]);
                    $customer_id = $pdo->lastInsertId();
                }

                $_SESSION['user_id']     = $existing_user_id;
                $_SESSION['customer_id'] = $customer_id;
                $_SESSION['email']       = $contact_email;
                $_SESSION['full_name']   = $contact_name;
                $_SESSION['role']        = 'customer';
            } else {
                // Email mới -> tự động tạo tài khoản customer với mật khẩu mặc định 123456
                $default_hash = password_hash('123456', PASSWORD_DEFAULT);
                $stmtNewUser = $pdo->prepare("INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, 'customer', NOW())");
                $stmtNewUser->execute([$contact_email, $default_hash]);
                $new_user_id = $pdo->lastInsertId();

                $stmtNewCust = $pdo->prepare("INSERT INTO customers (user_id, full_name, phone, address) VALUES (?, ?, ?, '')");
                $stmtNewCust->execute([$new_user_id, $contact_name, $contact_phone]);
                $customer_id = $pdo->lastInsertId();

                $_SESSION['user_id']     = $new_user_id;
                $_SESSION['customer_id'] = $customer_id;
                $_SESSION['email']       = $contact_email;
                $_SESSION['full_name']   = $contact_name;
                $_SESSION['role']        = 'customer';
            }
        }
    }

    // 5. Nếu không có lỗi, tiến hành lưu đơn
    if (empty($errors) && $customer_id) {
        $date1 = new DateTime($check_in);
        $date2 = new DateTime($check_out);
        $nights = $date1->diff($date2)->days;
        if ($nights <= 0) $nights = 1;

        $price_per_night = (float)$room['price_per_night'];
        $total_amount    = $nights * $price_per_night;

        $booking_code = 'BK' . date('Ymd') . '-' . rand(1000, 9999);

        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO bookings (
                    booking_code, customer_id, room_id, check_in, check_out, 
                    nights, guests, contact_name, contact_phone, note, 
                    price_per_night, total_amount, status, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, 
                    ?, ?, 'pending', NOW()
                )
            ");
            $stmtInsert->execute([
                $booking_code,
                $customer_id,
                $room['id'],
                $check_in,
                $check_out,
                $nights,
                $guests,
                $contact_name,
                $contact_phone,
                $note,
                $price_per_night,
                $total_amount
            ]);

            $new_booking_id = $pdo->lastInsertId();

            set_flash('success', "Gửi yêu cầu đặt phòng thành công! Mã đơn của bạn là: {$booking_code}. Chúng tôi sẽ kiểm tra và xác nhận sớm nhất.");
            redirect('/booking_detail.php?id=' . $new_booking_id);
        } catch (Exception $e) {
            $errors[] = 'Đã có lỗi trong quá trình lưu đơn: ' . $e->getMessage();
        }
    }
}

// Tính toán tạm tính để hiển thị
$calc_nights = 1;
if (!empty($check_in) && !empty($check_out) && $check_out > $check_in) {
    $d1 = new DateTime($check_in);
    $d2 = new DateTime($check_out);
    $calc_nights = $d1->diff($d2)->days;
}
$calc_total = $calc_nights * (float)$room['price_per_night'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container">
        <h2 class="fw-bold mb-1"><i class="bi bi-calendar-check me-2"></i>Xác nhận thông tin đặt phòng</h2>
        <p class="mb-0 opacity-75">Vui lòng kiểm tra kỹ thời gian lưu trú và thông tin liên hệ của quý khách</p>
    </div>
</div>

<div class="container py-2 pb-5">
    <?php if (!$user): ?>
        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm border-0">
            <div>
                <i class="bi bi-info-circle-fill me-2 fs-5 text-primary"></i>
                Bạn có thể điền nhanh thông tin bên dưới để gửi đơn ngay lập tức, hoặc đăng nhập nếu đã có tài khoản.
            </div>
            <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-primary btn-sm fw-semibold mt-2 mt-sm-0">
                <i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập
            </a>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Vui lòng kiểm tra lại các thông tin:</h6>
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Cột trái: Form nhập thông tin đặt phòng -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-pencil-square me-2"></i>Thông tin đặt phòng
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="" method="POST" id="bookingForm">
                        <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                        <input type="hidden" id="room_price_per_night" value="<?= (float)$room['price_per_night'] ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="booking_check_in" class="form-label fw-semibold">Ngày nhận phòng <span class="required-star">*</span></label>
                                <input type="date" class="form-control" id="booking_check_in" name="check_in" value="<?= e($check_in) ?>" min="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="booking_check_out" class="form-label fw-semibold">Ngày trả phòng <span class="required-star">*</span></label>
                                <input type="date" class="form-control" id="booking_check_out" name="check_out" value="<?= e($check_out) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="guests" class="form-label fw-semibold">Số lượng khách <span class="required-star">*</span></label>
                                <input type="number" class="form-control" id="guests" name="guests" value="<?= (int)$guests ?>" min="1" max="<?= (int)$room['capacity'] ?>" required>
                                <div class="form-text">Sức chứa tối đa: <?= $room['capacity'] ?> người lớn.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="contact_name" class="form-label fw-semibold">Tên người nhận phòng <span class="required-star">*</span></label>
                                <input type="text" class="form-control" id="contact_name" name="contact_name" value="<?= e($contact_name) ?>" required placeholder="VD: Nguyễn Văn An">
                            </div>

                            <div class="col-md-6">
                                <label for="contact_phone" class="form-label fw-semibold">Số điện thoại liên hệ <span class="required-star">*</span></label>
                                <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?= e($contact_phone) ?>" required placeholder="VD: 0901234567">
                            </div>

                            <div class="col-md-6">
                                <label for="contact_email" class="form-label fw-semibold">Địa chỉ Email <span class="required-star">*</span></label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= e($contact_email) ?>" required placeholder="name@example.com" <?= $user ? 'readonly' : '' ?>>
                                <div class="form-text">Để nhận thông báo và mã đơn đặt phòng.</div>
                            </div>

                            <div class="col-12">
                                <label for="note" class="form-label fw-semibold">Ghi chú hoặc yêu cầu đặc biệt (nếu có)</label>
                                <textarea class="form-control" id="note" name="note" rows="3" placeholder="Ví dụ: Cần nhận phòng muộn sau 18:00, chuẩn bị thêm gối..."><?= e($note) ?></textarea>
                            </div>

                            <div class="col-12 pt-3 border-top mt-4">
                                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm">
                                    <i class="bi bi-send-check me-2"></i> Gửi yêu cầu đặt phòng
                                </button>
                                <div class="text-center mt-2 text-muted small">
                                    <i class="bi bi-shield-check text-success me-1"></i> Yêu cầu của bạn sẽ được gửi tới Admin để xác nhận phòng trống nhanh chóng.
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Cột phải: Tóm tắt thông tin phòng & Bảng giá -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm sticky-top" style="top: 80px;">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0">Tóm tắt đơn đặt</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                        <?php $img_src = !empty($room['image']) ? '/assets/images/' . e($room['image']) : '/assets/images/no_image.jpg'; ?>
                        <img src="<?= $img_src ?>" alt="Phòng" class="rounded" style="width: 100px; height: 75px; object-fit: cover;">
                        <div>
                            <span class="badge <?= $room['property_type'] === 'homestay' ? 'bg-success' : 'bg-primary' ?> mb-1">
                                <?= get_property_type_text($room['property_type']) ?>
                            </span>
                            <h6 class="fw-bold mb-1"><?= e($room['property_name']) ?></h6>
                            <div class="text-muted small">Phòng: <strong><?= e($room['room_number']) ?></strong> (<?= e($room['room_type_name']) ?>)</div>
                        </div>
                    </div>

                    <div class="small mb-3">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="bi bi-geo-alt me-1"></i> Địa điểm:</span>
                            <span class="fw-semibold text-end"><?= e($room['property_city']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="bi bi-people me-1"></i> Sức chứa phòng:</span>
                            <span class="fw-semibold"><?= $room['capacity'] ?> người</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted"><i class="bi bi-tag me-1"></i> Đơn giá mỗi đêm:</span>
                            <span class="fw-semibold text-primary"><?= format_money($room['price_per_night']) ?></span>
                        </div>
                    </div>

                    <!-- Bảng tính tiền động -->
                    <div class="bg-light p-3 rounded-3 mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Số đêm lưu trú:</span>
                            <strong id="display_nights"><?= $calc_nights ?> đêm</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Đơn giá niêm yết:</span>
                            <span><?= format_money($room['price_per_night']) ?>/đêm</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-6">Tổng tiền tạm tính:</span>
                            <span class="fw-bold fs-4 text-danger" id="display_total"><?= format_money($calc_total) ?></span>
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 small mb-0">
                        <i class="bi bi-info-circle me-1"></i> Đơn đặt phòng sau khi gửi sẽ ở trạng thái <em>Chờ xác nhận (pending)</em> và được phòng ban quản lý liên hệ xác nhận sớm nhất.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
