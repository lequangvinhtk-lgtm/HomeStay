<?php
/**
 * Trang Đăng ký tài khoản Khách hàng mới
 * Chỉ đăng ký tài khoản với vai trò 'customer'
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Nếu đã đăng nhập rồi thì chuyển về trang chủ
if (is_logged_in()) {
    redirect('/index.php');
}

$page_title = 'Đăng ký tài khoản';
$errors = [];
$old_data = [
    'full_name' => '',
    'email'     => '',
    'phone'     => '',
    'address'   => '',
];

// Xử lý khi người dùng bấm nút Đăng ký (phương thức POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Lấy và cắt tỉa khoảng trắng dữ liệu gửi lên
    $full_name        = trim($_POST['full_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $password         = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    $old_data = [
        'full_name' => $full_name,
        'email'     => $email,
        'phone'     => $phone,
        'address'   => $address,
    ];

    // 2. Kiểm tra dữ liệu hợp lệ (Validation)
    if (empty($full_name)) {
        $errors[] = 'Vui lòng nhập họ và tên.';
    }

    if (empty($email)) {
        $errors[] = 'Vui lòng nhập địa chỉ email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Định dạng email không hợp lệ.';
    }

    if (empty($phone)) {
        $errors[] = 'Vui lòng nhập số điện thoại liên hệ.';
    }

    if (empty($password)) {
        $errors[] = 'Vui lòng nhập mật khẩu.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có độ dài tối thiểu 6 ký tự.';
    }

    if ($password !== $password_confirm) {
        $errors[] = 'Mật khẩu xác nhận không khớp với mật khẩu đã nhập.';
    }

    // 3. Kiểm tra email đã tồn tại trong hệ thống hay chưa
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `email` = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Email này đã được sử dụng. Vui lòng chọn email khác hoặc đăng nhập.';
        }
    }

    // 4. Nếu không có lỗi thì thêm vào CSDL (bằng Transaction)
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Mã hóa mật khẩu bảo mật bằng bcrypt
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Thêm vào bảng users với role luôn là customer
            $stmtUser = $pdo->prepare("INSERT INTO `users` (`email`, `password_hash`, `role`, `created_at`) VALUES (?, ?, 'customer', NOW())");
            $stmtUser->execute([$email, $password_hash]);
            $new_user_id = $pdo->lastInsertId();

            // Thêm thông tin tương ứng vào bảng customers
            $stmtCustomer = $pdo->prepare("INSERT INTO `customers` (`user_id`, `full_name`, `phone`, `address`) VALUES (?, ?, ?, ?)");
            $stmtCustomer->execute([$new_user_id, $full_name, $phone, $address]);

            $pdo->commit();

            set_flash('success', 'Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay bây giờ.');
            redirect('/login.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Đã có lỗi xảy ra trong quá trình tạo tài khoản: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-plus-fill text-primary" style="font-size: 3rem;"></i>
                        <h3 class="fw-bold mt-2">Tạo tài khoản mới</h3>
                        <p class="text-muted small">Đăng ký thành viên để đặt phòng khách sạn và homestay tiện lợi</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="/register.php" method="POST" novalidate>
                        <div class="mb-3">
                            <label for="full_name" class="form-label fw-semibold">Họ và tên <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($old_data['full_name']) ?>" required placeholder="Ví dụ: Nguyễn Văn An">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Địa chỉ Email <span class="required-star">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($old_data['email']) ?>" required placeholder="name@example.com">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label fw-semibold">Số điện thoại <span class="required-star">*</span></label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= e($old_data['phone']) ?>" required placeholder="0901234567">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="address" class="form-label fw-semibold">Địa chỉ</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?= e($old_data['address']) ?>" placeholder="Quận/Huyện, Tỉnh/Thành">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Mật khẩu <span class="required-star">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="Tối thiểu 6 ký tự">
                        </div>

                        <div class="mb-4">
                            <label for="password_confirm" class="form-label fw-semibold">Xác nhận mật khẩu <span class="required-star">*</span></label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required placeholder="Nhập lại mật khẩu trên">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-check-circle me-1"></i> Hoàn tất đăng ký
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <span class="text-muted small">Đã có tài khoản?</span>
                        <a href="/login.php" class="text-decoration-none fw-semibold ms-1">Đăng nhập ngay</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
