<?php
/**
 * Trang Đăng nhập dành riêng cho Quản trị viên (/admin)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Nếu đã là admin rồi thì vào thẳng dashboard
if (is_admin()) {
    redirect('/admin/dashboard.php');
}

$page_title = 'Đăng nhập Quản trị viên';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập Email và Mật khẩu quản trị.';
    } else {
        $stmt = $pdo->prepare("SELECT `id`, `email`, `password_hash`, `role` FROM `users` WHERE `email` = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Kiểm tra phân quyền: Phải là admin mới cho phép truy cập
            if ($user['role'] !== 'admin') {
                $error = 'Truy cập bị từ chối! Tài khoản này không có quyền Quản trị viên.';
            } else {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = 'admin';
                $_SESSION['full_name'] = 'Quản trị viên';

                set_flash('success', 'Đăng nhập trang quản trị thành công!');
                redirect('/admin/dashboard.php');
            }
        } else {
            $error = 'Email hoặc mật khẩu không chính xác.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Quản trị - StayEasy</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center min-vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-lg border-0 rounded-3">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-inline-block mb-3">
                            <i class="bi bi-shield-lock-fill" style="font-size: 2.5rem;"></i>
                        </div>
                        <h4 class="fw-bold">StayEasy Admin</h4>
                        <p class="text-muted small">Cổng thông tin quản trị hệ thống</p>
                    </div>

                    <?php display_flash(); ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 small" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form action="/admin/login.php" method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold small">Email Quản trị</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required placeholder="admin@gmail.com" autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold small">Mật khẩu</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required placeholder="admin123">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập Quản trị
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <a href="/index.php" class="text-decoration-none small text-muted">
                            <i class="bi bi-arrow-left me-1"></i> Quay lại trang chủ người dùng
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
