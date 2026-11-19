<?php
// Bao gồm các hàm tiện ích nếu chưa được nạp
if (!function_exists('is_logged_in')) {
    require_once __DIR__ . '/functions.php';
}
$user = current_user();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' - ' : '' ?>StayEasy | Đặt phòng Khách sạn & Homestay</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<!-- Navbar người dùng -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/index.php">
            <i class="bi bi-building-check me-2"></i>StayEasy
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNavbar" aria-controls="userNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="userNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/index.php"><i class="bi bi-house-door me-1"></i>Trang chủ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/properties.php"><i class="bi bi-buildings me-1"></i>Khách sạn & Homestay</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/search.php"><i class="bi bi-search me-1"></i>Tìm phòng</a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <?php if ($user): ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="nav-item me-2">
                            <a class="btn btn-warning btn-sm text-dark fw-bold" href="/admin/dashboard.php">
                                <i class="bi bi-shield-lock me-1"></i>Trang Quản trị
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <a class="nav-link" href="/my_bookings.php">
                            <i class="bi bi-calendar-check me-1"></i>Đơn đặt phòng
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i><?= e($user['full_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><a class="dropdown-item" href="/profile.php"><i class="bi bi-person-gear me-2"></i>Hồ sơ cá nhân</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-light btn-sm ms-lg-2 fw-semibold text-primary" href="/register.php">
                            <i class="bi bi-person-plus me-1"></i>Đăng ký
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Vùng hiển thị thông báo flash nhanh -->
<div class="container mt-3">
    <?php display_flash(); ?>
</div>

<!-- Khối nội dung chính của từng trang bắt đầu ở đây -->
<main class="flex-grow-1">
