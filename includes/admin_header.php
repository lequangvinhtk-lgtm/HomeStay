<?php
// Luôn yêu cầu nạp hàm và kiểm tra quyền Quản trị viên
if (!function_exists('require_admin')) {
    require_once __DIR__ . '/functions.php';
}
require_admin();
$admin_user = current_user();

// Xác định trang hiện tại để active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' - ' : '' ?>Quản trị ItachiHome</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<!-- Navbar Admin -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow">
    <div class="container-fluid px-3">
        <a class="navbar-brand fw-bold text-warning" href="/admin/dashboard.php">
            <i class="bi bi-shield-lock-fill me-2"></i>ItachiHome Admin
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'dashboard.php' ? 'active fw-bold' : '' ?>" href="/admin/dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Tổng quan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array($current_page, ['properties.php', 'property_form.php']) ? 'active fw-bold' : '' ?>" href="/admin/properties.php">
                        <i class="bi bi-buildings me-1"></i>Cơ sở lưu trú
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array($current_page, ['room_types.php', 'room_type_form.php']) ? 'active fw-bold' : '' ?>" href="/admin/room_types.php">
                        <i class="bi bi-tags me-1"></i>Loại phòng
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array($current_page, ['rooms.php', 'room_form.php']) ? 'active fw-bold' : '' ?>" href="/admin/rooms.php">
                        <i class="bi bi-door-open me-1"></i>Phòng
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array($current_page, ['bookings.php', 'booking_detail.php']) ? 'active fw-bold' : '' ?>" href="/admin/bookings.php">
                        <i class="bi bi-calendar2-check me-1"></i>Đơn đặt phòng
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array($current_page, ['customers.php', 'customer_detail.php']) ? 'active fw-bold' : '' ?>" href="/admin/customers.php">
                        <i class="bi bi-people me-1"></i>Tài khoản & Phân quyền
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'reports.php' ? 'active fw-bold' : '' ?>" href="/admin/reports.php">
                        <i class="bi bi-bar-chart-line me-1"></i>Thống kê
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <li class="nav-item me-2">
                    <a class="btn btn-outline-light btn-sm" href="/index.php" target="_blank">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem trang web
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-fill-gear me-1"></i><?= e($admin_user['full_name']) ?> (Admin)
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><span class="dropdown-item-text small text-muted"><?= e($admin_user['email']) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/admin/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất Admin</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Vùng thông báo flash nhanh -->
<div class="container-fluid px-4 mt-3">
    <?php display_flash(); ?>
</div>

<main class="flex-grow-1 container-fluid px-4 py-3">
