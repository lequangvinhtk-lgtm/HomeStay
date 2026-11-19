<?php
/**
 * Trang Thanh toán Đơn đặt phòng (VietQR & Chuyển khoản ngân hàng)
 * Hỗ trợ quét mã QR tự động điền số tiền và nội dung chuyển khoản
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($booking_id <= 0) {
    set_flash('danger', 'Đơn đặt phòng không hợp lệ.');
    redirect('/my_bookings.php');
}

// Lấy customer_id của người dùng hiện tại
$stmtCust = $pdo->prepare("SELECT id FROM customers WHERE user_id = ?");
$stmtCust->execute([$user['id']]);
$customer_id = $stmtCust->fetchColumn();

// Lấy thông tin đơn đặt phòng và quyền truy cập
if (is_admin()) {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               r.room_number, r.image as room_image,
               p.name as property_name, p.type as property_type, p.address as property_address, p.city as property_city,
               rt.name as room_type_name
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN properties p ON r.property_id = p.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               r.room_number, r.image as room_image,
               p.name as property_name, p.type as property_type, p.address as property_address, p.city as property_city,
               rt.name as room_type_name
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN properties p ON r.property_id = p.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.id = ? AND b.customer_id = ?
    ");
    $stmt->execute([$booking_id, $customer_id]);
}
$booking = $stmt->fetch();

if (!$booking) {
    set_flash('danger', 'Không tìm thấy đơn đặt phòng hoặc bạn không có quyền thanh toán đơn này!');
    redirect('/my_bookings.php');
}

// Kiểm tra xem đơn đã thanh toán chưa
$stmtPayment = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ?");
$stmtPayment->execute([$booking['id']]);
$payment = $stmtPayment->fetch();

// Cấu hình ngân hàng & QR
$payment_config = get_payment_config();
$transfer_content = ($payment_config['transfer_prefix'] ?? 'ITACHIHOME') . ' ' . $booking['booking_code'];
$qr_url = get_vietqr_url($booking['total_amount'], $booking['booking_code']);

// Xử lý xác nhận thanh toán chuyển khoản (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_confirm_payment'])) {
    if ($booking['status'] === 'cancelled' || $booking['status'] === 'rejected') {
        set_flash('danger', 'Đơn đặt phòng này đã bị hủy hoặc từ chối, không thể thực hiện thanh toán.');
        redirect('/booking_detail.php?id=' . $booking['id']);
    }

    if ($payment) {
        set_flash('info', 'Đơn đặt phòng này đã được ghi nhận thanh toán trước đó.');
        redirect('/booking_detail.php?id=' . $booking['id']);
    }

    $transaction_ref = trim($_POST['transaction_ref'] ?? '');
    $note = 'Chuyển khoản VietQR: ' . $transfer_content;
    if (!empty($transaction_ref)) {
        $note .= ' | Mã GD: ' . $transaction_ref;
    }

    $stmtInsertPay = $pdo->prepare("
        INSERT INTO payments (booking_id, amount, method, transaction_note, paid_at)
        VALUES (?, ?, 'bank_transfer', ?, NOW())
    ");
    $stmtInsertPay->execute([$booking['id'], $booking['total_amount'], $note]);

    set_flash('success', 'Xác nhận thanh toán thành công! Cảm ơn bạn đã hoàn tất chuyển khoản cho đơn ' . $booking['booking_code'] . '.');
    redirect('/booking_detail.php?id=' . $booking['id']);
}

$page_title = 'Thanh toán đơn ' . $booking['booking_code'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-primary text-white py-4 mb-4">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="small opacity-75 mb-1">
                <a href="/booking_detail.php?id=<?= $booking['id'] ?>" class="text-white text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i> Quay lại chi tiết đơn đặt phòng
                </a>
            </div>
            <h2 class="fw-bold mb-0">Thanh toán Đơn đặt phòng: <?= e($booking['booking_code']) ?></h2>
        </div>
        <div>
            <span class="fs-5"><?= get_status_badge($booking['status']) ?></span>
        </div>
    </div>
</div>

<div class="container py-2 pb-5">
    <?php if ($booking['status'] === 'cancelled' || $booking['status'] === 'rejected'): ?>
        <div class="alert alert-danger p-4 shadow-sm text-center">
            <i class="bi bi-exclamation-triangle-fill fs-1 text-danger d-block mb-3"></i>
            <h4 class="fw-bold">Đơn đặt phòng không hợp lệ để thanh toán</h4>
            <p class="mb-3 text-muted">Đơn đặt phòng này hiện đang ở trạng thái <strong><?= $booking['status'] === 'cancelled' ? 'Đã hủy' : 'Bị từ chối' ?></strong>.</p>
            <a href="/booking_detail.php?id=<?= $booking['id'] ?>" class="btn btn-outline-danger">Xem chi tiết đơn</a>
        </div>
    <?php elseif ($payment): ?>
        <!-- Đã thanh toán -->
        <div class="card border-0 shadow-sm border-success border-top border-4 text-center p-5">
            <div class="text-success mb-3">
                <i class="bi bi-check-circle-fill" style="font-size: 4rem;"></i>
            </div>
            <h3 class="fw-bold text-success mb-2">Đơn hàng đã được thanh toán thành công!</h3>
            <p class="text-muted mb-4">Hệ thống đã ghi nhận khoản thanh toán đầy đủ cho đơn đặt phòng này.</p>

            <div class="row justify-content-center mb-4">
                <div class="col-md-6 col-lg-5 text-start bg-light p-4 rounded-3 border">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Mã đơn phòng:</span>
                        <span class="fw-bold"><?= e($booking['booking_code']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Số tiền đã trả:</span>
                        <span class="fw-bold text-danger fs-5"><?= format_money($payment['amount']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Hình thức:</span>
                        <span class="fw-bold"><?= $payment['method'] === 'cash' ? 'Tiền mặt tại quầy' : 'Chuyển khoản ngân hàng (VietQR)' ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Thời gian ghi nhận:</span>
                        <span><?= format_datetime($payment['paid_at']) ?></span>
                    </div>
                    <?php if (!empty($payment['transaction_note'])): ?>
                        <div class="py-2">
                            <span class="text-muted d-block small">Ghi chú giao dịch:</span>
                            <span class="text-secondary"><?= e($payment['transaction_note']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <a href="/booking_detail.php?id=<?= $booking['id'] ?>" class="btn btn-primary px-4 py-2 me-2">
                    <i class="bi bi-receipt me-1"></i> Xem chi tiết đơn phòng
                </a>
                <a href="/my_bookings.php" class="btn btn-outline-secondary px-4 py-2">
                    Danh sách đơn của tôi
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Chưa thanh toán: Giao diện Quét mã QR & Chuyển khoản -->
        <div class="row g-4">
            <!-- Cột trái: Quét mã QR VietQR -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="fw-bold mb-0 text-primary">
                            <i class="bi bi-qr-code-scan me-2"></i>Quét mã QR để thanh toán
                        </h5>
                    </div>
                    <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                        <div class="p-3 bg-white rounded-3 shadow-sm border mb-3 text-center" style="max-width: 330px;">
                            <img src="<?= e($qr_url) ?>" alt="Mã VietQR Thanh Toán" class="img-fluid rounded" style="width: 100%; max-width: 300px; display: block; margin: 0 auto;">
                        </div>

                        <div class="text-center">
                            <div class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6 mb-2">
                                <i class="bi bi-lightning-charge-fill me-1"></i> VietQR Tự động khớp số tiền
                            </div>
                            <p class="text-muted small mb-0 px-2">
                                Mở ứng dụng <strong>Ngân hàng</strong> hoặc <strong>Ví điện tử</strong> (MoMo, ZaloPay,...) chọn tính năng <strong>Quét QR</strong> để thanh toán nhanh chóng.
                            </p>
                        </div>
                    </div>
                    <div class="card-footer bg-light py-3 border-top text-center">
                        <a href="<?= e($qr_url) ?>" download="VietQR_<?= e($booking['booking_code']) ?>.png" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download me-1"></i> Tải ảnh mã QR về máy
                        </a>
                    </div>
                </div>
            </div>

            <!-- Cột phải: Thông tin chuyển khoản thủ công & Xác nhận -->
            <div class="col-lg-7">
                <!-- Thông tin số tài khoản -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-bank2 text-primary me-2"></i>Thông tin tài khoản ngân hàng
                        </h5>
                        <span class="badge bg-primary-subtle text-primary">Chuyển khoản 24/7</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0">
                                <tbody>
                                    <tr class="border-bottom">
                                        <td class="text-muted" style="width: 35%;">Ngân hàng thụ hưởng:</td>
                                        <td class="fw-bold text-dark fs-6"><?= e($payment_config['bank_name']) ?></td>
                                        <td class="text-end" style="width: 15%;"></td>
                                    </tr>
                                    <tr class="border-bottom">
                                        <td class="text-muted">Chủ tài khoản:</td>
                                        <td class="fw-bold text-dark fs-6 text-uppercase"><?= e($payment_config['account_name']) ?></td>
                                        <td class="text-end"></td>
                                    </tr>
                                    <tr class="border-bottom">
                                        <td class="text-muted">Số tài khoản:</td>
                                        <td class="fw-bold text-primary fs-5">
                                            <span id="txtAccountNo"><?= e($payment_config['account_no']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="txtAccountNo" title="Sao chép số tài khoản">
                                                <i class="bi bi-clipboard me-1"></i>Sao chép
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="border-bottom">
                                        <td class="text-muted">Số tiền cần trả:</td>
                                        <td class="fw-bold text-danger fs-4">
                                            <span id="txtAmount"><?= format_money($booking['total_amount']) ?></span>
                                            <input type="hidden" id="rawAmount" value="<?= $booking['total_amount'] ?>">
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="rawAmount" title="Sao chép số tiền">
                                                <i class="bi bi-clipboard me-1"></i>Sao chép
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Nội dung chuyển khoản:</td>
                                        <td class="fw-bold text-success fs-6">
                                            <span id="txtContent" class="p-1 px-2 bg-success bg-opacity-10 rounded"><?= e($transfer_content) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" data-target="txtContent" title="Sao chép nội dung">
                                                <i class="bi bi-clipboard me-1"></i>Sao chép
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-warning mt-3 mb-0 small">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong>Lưu ý:</strong> Vui lòng điền chính xác <strong>Nội dung chuyển khoản</strong> trên để hệ thống ghi nhận nhanh chóng cho đơn phòng của bạn.
                        </div>
                    </div>
                </div>

                <!-- Tóm tắt đơn phòng -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-muted text-uppercase mb-3 small">Tóm tắt đơn đặt phòng</h6>
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-building text-primary me-2 fs-5"></i>
                            <div>
                                <span class="fw-bold text-dark"><?= e($booking['property_name']) ?></span> - Phòng <?= e($booking['room_number']) ?> (<?= e($booking['room_type_name']) ?>)
                            </div>
                        </div>
                        <div class="d-flex align-items-center text-muted small">
                            <i class="bi bi-calendar-check me-2"></i>
                            <span>Từ <?= format_date($booking['check_in']) ?> đến <?= format_date($booking['check_out']) ?> (<?= $booking['nights'] ?> đêm)</span>
                        </div>
                    </div>
                </div>

                <!-- Form xác nhận chuyển khoản -->
                <div class="card border-0 shadow-sm border-start border-4 border-success">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-success mb-2">
                            <i class="bi bi-check2-circle me-1"></i> Xác nhận sau khi đã chuyển khoản
                        </h5>
                        <p class="text-muted small mb-3">
                            Sau khi hoàn tất thao tác chuyển tiền trên ứng dụng ngân hàng, bạn hãy bấm nút bên dưới để thông báo cho hệ thống.
                        </p>

                        <form action="" method="POST">
                            <input type="hidden" name="action_confirm_payment" value="1">
                            <div class="mb-3">
                                <label for="transaction_ref" class="form-label small fw-semibold text-secondary">Mã giao dịch ngân hàng / Ghi chú (Nếu có):</label>
                                <input type="text" class="form-control" id="transaction_ref" name="transaction_ref" placeholder="Ví dụ: MB123456789 hoặc tên người gửi...">
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100 fw-bold shadow-sm" onclick="return confirm('Bạn đã hoàn tất chuyển khoản chính xác số tiền <?= format_money($booking['total_amount']) ?> đúng không?')">
                                <i class="bi bi-check-lg me-2"></i>Tôi đã hoàn tất chuyển khoản
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Chức năng sao chép nhanh nội dung
document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const targetEl = document.getElementById(targetId);
        if (!targetEl) return;
        
        const textToCopy = targetEl.value !== undefined ? targetEl.value : targetEl.innerText.trim();
        
        navigator.clipboard.writeText(textToCopy).then(() => {
            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="bi bi-check2 text-success me-1"></i>Đã chép!';
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-success', 'text-white');
            setTimeout(() => {
                this.innerHTML = originalHTML;
                this.classList.remove('btn-success', 'text-white');
                this.classList.add('btn-outline-secondary');
            }, 2000);
        }).catch(err => {
            alert('Không thể tự động sao chép. Bạn vui lòng chọn và chép thủ công.');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
