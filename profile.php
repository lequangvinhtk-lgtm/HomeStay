<?php
/**
 * Trang Quản lý Hồ sơ cá nhân và Đổi mật khẩu của Khách hàng
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Yêu cầu bắt buộc phải đăng nhập
require_login();

$user = current_user();
$page_title = 'Hồ sơ cá nhân';

$profile_error = '';
$profile_success = '';
$password_error = '';
$password_success = '';

// Lấy thông tin hiện tại của khách hàng từ CSDL
$stmt = $pdo->prepare("
    SELECT u.id as user_id, u.email, c.id as customer_id, c.full_name, c.phone, c.address 
    FROM users u
    LEFT JOIN customers c ON c.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user['id']]);
$customer_data = $stmt->fetch();

// 1. Xử lý CẬP NHẬT THÔNG TIN CÁ NHÂN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    if (empty($full_name)) {
        $profile_error = 'Họ và tên không được để trống.';
    } elseif (empty($phone)) {
        $profile_error = 'Số điện thoại không được để trống.';
    } else {
        // Cập nhật bảng customers (nếu chưa có thì thêm mới)
        $chkCust = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE user_id = ?");
        $chkCust->execute([$user['id']]);
        if ($chkCust->fetchColumn() > 0) {
            $updateStmt = $pdo->prepare("UPDATE customers SET full_name = ?, phone = ?, address = ? WHERE user_id = ?");
            $updateStmt->execute([$full_name, $phone, $address, $user['id']]);
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO customers (user_id, full_name, phone, address) VALUES (?, ?, ?, ?)");
            $insertStmt->execute([$user['id'], $full_name, $phone, $address]);
        }

        // Cập nhật lại session
        $_SESSION['full_name'] = $full_name;
        $customer_data['full_name'] = $full_name;
        $customer_data['phone']     = $phone;
        $customer_data['address']   = $address;

        set_flash('success', 'Cập nhật thông tin cá nhân thành công!');
        redirect('/profile.php');
    }
}

// 2. Xử lý ĐỔI MẬT KHẨU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_change_password'])) {
    $old_password        = $_POST['old_password'] ?? '';
    $new_password        = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($old_password) || empty($new_password) || empty($confirm_new_password)) {
        $password_error = 'Vui lòng điền đầy đủ các trường mật khẩu.';
    } elseif (strlen($new_password) < 6) {
        $password_error = 'Mật khẩu mới phải có tối thiểu 6 ký tự.';
    } elseif ($new_password !== $confirm_new_password) {
        $password_error = 'Mật khẩu xác nhận không khớp.';
    } else {
        // Kiểm tra mật khẩu cũ từ CSDL
        $pwdStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $pwdStmt->execute([$user['id']]);
        $current_hash = $pwdStmt->fetchColumn();

        if (!password_verify($old_password, $current_hash)) {
            $password_error = 'Mật khẩu hiện tại không đúng.';
        } else {
            // Cập nhật mật khẩu mới đã hash
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $updatePwd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $updatePwd->execute([$new_hash, $user['id']]);

            set_flash('success', 'Đổi mật khẩu thành công!');
            redirect('/profile.php');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <!-- Cột menu thông tin bên trái -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 text-center p-4">
                <div class="mb-3">
                    <i class="bi bi-person-circle text-primary" style="font-size: 4rem;"></i>
                </div>
                <h5 class="fw-bold mb-1"><?= e($customer_data['full_name'] ?? 'Khách hàng') ?></h5>
                <p class="text-muted small mb-3"><?= e($customer_data['email']) ?></p>
                <div class="d-grid gap-2">
                    <a href="/my_bookings.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-calendar-check me-1"></i> Xem lịch sử đặt phòng
                    </a>
                </div>
            </div>
        </div>

        <!-- Cột chỉnh sửa thông tin bên phải -->
        <div class="col-md-8">
            <!-- Form thông tin cá nhân -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title fw-bold mb-0">
                        <i class="bi bi-person-lines-fill text-primary me-2"></i>Thông tin tài khoản
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($profile_error)): ?>
                        <div class="alert alert-danger"><?= e($profile_error) ?></div>
                    <?php endif; ?>

                    <form action="/profile.php" method="POST">
                        <input type="hidden" name="action_update_profile" value="1">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Địa chỉ Email</label>
                            <input type="email" class="form-control bg-light" value="<?= e($customer_data['email']) ?>" readonly>
                            <div class="form-text">Email dùng để đăng nhập và không thể thay đổi.</div>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label fw-semibold">Họ và tên <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($customer_data['full_name'] ?? '') ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label fw-semibold">Số điện thoại <span class="required-star">*</span></label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= e($customer_data['phone'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="address" class="form-label fw-semibold">Địa chỉ</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?= e($customer_data['address'] ?? '') ?>" placeholder="Tỉnh / Thành phố">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Cập nhật thông tin
                        </button>
                    </form>
                </div>
            </div>

            <!-- Form đổi mật khẩu -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title fw-bold mb-0">
                        <i class="bi bi-key-fill text-primary me-2"></i>Đổi mật khẩu
                    </h5>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($password_error)): ?>
                        <div class="alert alert-danger"><?= e($password_error) ?></div>
                    <?php endif; ?>

                    <form action="/profile.php" method="POST">
                        <input type="hidden" name="action_change_password" value="1">

                        <div class="mb-3">
                            <label for="old_password" class="form-label fw-semibold">Mật khẩu hiện tại <span class="required-star">*</span></label>
                            <input type="password" class="form-control" id="old_password" name="old_password" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label fw-semibold">Mật khẩu mới <span class="required-star">*</span></label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required placeholder="Tối thiểu 6 ký tự">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_new_password" class="form-label fw-semibold">Xác nhận mật khẩu mới <span class="required-star">*</span></label>
                                <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-warning text-dark fw-semibold">
                            <i class="bi bi-shield-check me-1"></i> Đổi mật khẩu
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
