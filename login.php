<?php
/**
 * Trang Đăng nhập chung cho người dùng và khách hàng
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Nếu đã đăng nhập rồi thì chuyển hướng phù hợp
if (is_logged_in()) {
    if (is_admin()) {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/index.php');
    }
}

$page_title = 'Đăng nhập';
$error = '';
$email = '';
$redirect_url = $_GET['redirect'] ?? '';

// Xử lý khi gửi form Đăng nhập (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $redirect_url = trim($_POST['redirect'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ Email và Mật khẩu.';
    } else {
        // Tìm tài khoản theo email
        $stmt = $pdo->prepare("SELECT `id`, `email`, `password_hash`, `role` FROM `users` WHERE `email` = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Kiểm tra tài khoản tồn tại và khớp mật khẩu đã hash
        if ($user && password_verify($password, $user['password_hash'])) {
            // Đăng nhập thành công -> Lưu thông tin vào SESSION
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            if ($user['role'] === 'customer') {
                // Lấy thêm thông tin customer (họ tên, id bảng customers)
                $stmtCust = $pdo->prepare("SELECT `id`, `full_name` FROM `customers` WHERE `user_id` = ?");
                $stmtCust->execute([$user['id']]);
                $cust = $stmtCust->fetch();

                $_SESSION['customer_id'] = $cust ? $cust['id'] : null;
                $_SESSION['full_name']   = $cust ? $cust['full_name'] : 'Khách hàng';

                set_flash('success', 'Chào mừng ' . $_SESSION['full_name'] . ' đã đăng nhập thành công!');

                // Chuyển hướng về trang người dùng muốn truy cập trước đó (nếu an toàn)
                if (!empty($redirect_url) && str_starts_with($redirect_url, '/')) {
                    redirect($redirect_url);
                }
                redirect('/index.php');
            } else {
                // Nếu là Quản trị viên
                $_SESSION['full_name'] = 'Quản trị viên';
                set_flash('success', 'Đăng nhập Quản trị viên thành công!');
                if (!empty($redirect_url) && str_starts_with($redirect_url, '/')) {
                    redirect($redirect_url);
                }
                redirect('/admin/dashboard.php');
            }
        } else {
            $error = 'Email hoặc mật khẩu không chính xác. Vui lòng thử lại!';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-box-arrow-in-right text-primary" style="font-size: 3rem;"></i>
                        <h3 class="fw-bold mt-2">Đăng nhập</h3>
                        <p class="text-muted small">Chào mừng bạn quay trở lại với StayEasy</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form action="/login.php" method="POST">
                        <input type="hidden" name="redirect" value="<?= e($redirect_url) ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Địa chỉ Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required placeholder="name@example.com" autofocus>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Mật khẩu</label>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="Nhập mật khẩu">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-door-open me-1"></i> Đăng nhập
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <span class="text-muted small">Chưa có tài khoản?</span>
                        <a href="/register.php" class="text-decoration-none fw-semibold ms-1">Đăng ký ngay</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
