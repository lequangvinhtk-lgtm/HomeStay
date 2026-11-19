<?php
/**
 * Chi tiết Đơn đặt phòng & Bảng điều khiển Xử lý Nghiệp vụ Quản trị viên
 * 1. Xác nhận đơn (confirmed) kèm kiểm tra chống trùng lịch (Mục 5.3)
 * 2. Từ chối đơn (rejected) kèm lý do
 * 3. Nhận phòng (checked_in)
 * 4. Trả phòng (checked_out)
 * 5. Ghi nhận thanh toán (payments)
 * 6. Hoàn thành đơn (completed)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($booking_id <= 0) {
    set_flash('danger', 'Đơn đặt phòng không hợp lệ.');
    redirect('/admin/bookings.php');
}

// Lấy thông tin chi tiết đơn
$stmt = $pdo->prepare("
    SELECT b.*, 
           c.id as customer_id, c.full_name as customer_name, c.phone as customer_phone, c.address as customer_address,
           u.email as customer_email,
           r.id as room_id, r.room_number, r.capacity, r.price_per_night as room_current_price, r.image as room_image,
           p.id as property_id, p.name as property_name, p.type as property_type, p.address as property_address, p.city as property_city,
           rt.name as room_type_name
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    JOIN users u ON c.user_id = u.id
    JOIN rooms r ON b.room_id = r.id
    JOIN properties p ON r.property_id = p.id
    JOIN room_types rt ON r.room_type_id = rt.id
    WHERE b.id = ?
");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    set_flash('danger', 'Không tìm thấy đơn đặt phòng yêu cầu.');
    redirect('/admin/bookings.php');
}

$page_title = 'Xử lý đơn ' . $booking['booking_code'];

// Lấy thông tin thanh toán (nếu đã có)
$stmtPay = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ?");
$stmtPay->execute([$booking['id']]);
$payment = $stmtPay->fetch();

// ========================================================
// XỬ LÝ CÁC HÀNH ĐỘNG THAY ĐỔI TRẠNG THÁI (POST)
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. XÁC NHẬN ĐƠN (pending -> confirmed)
    if ($action === 'confirm') {
        if ($booking['status'] !== 'pending') {
            set_flash('danger', 'Chỉ có thể xác nhận đơn đang ở trạng thái Chờ xác nhận (pending).');
        } else {
            // THUẬT TOÁN CHỐNG TRÙNG LỊCH (Mục 5.3):
            // Kiểm tra xem phòng này đã có đơn nào 'confirmed' hoặc 'checked_in' trong khoảng thời gian này chưa
            // Công thức: ngày_nhận_mới < ngày_trả_cũ AND ngày_trả_mới > ngày_nhận_cũ
            $stmtCheckConflict = $pdo->prepare("
                SELECT COUNT(*) 
                FROM bookings 
                WHERE room_id = ? 
                  AND status IN ('confirmed', 'checked_in')
                  AND (? < check_out AND ? > check_in)
                  AND id != ?
            ");
            $stmtCheckConflict->execute([
                $booking['room_id'],
                $booking['check_in'],
                $booking['check_out'],
                $booking['id']
            ]);
            $conflictCount = $stmtCheckConflict->fetchColumn();

            if ($conflictCount > 0) {
                // ĐÃ CÓ TRÙNG LỊCH: Báo lỗi và KHÔNG cho xác nhận
                set_flash('danger', 'Không thể xác nhận! Phòng này đã có người đặt trong khoảng thời gian từ ' . format_date($booking['check_in']) . ' đến ' . format_date($booking['check_out']) . '.');
            } else {
                // Không trùng: Chuyển sang confirmed
                $stmtConfirm = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
                $stmtConfirm->execute([$booking['id']]);
                set_flash('success', 'Đã duyệt và xác nhận đơn đặt phòng thành công! Phòng đã được giữ.');
            }
        }
        redirect('/admin/booking_detail.php?id=' . $booking['id']);
    }

    // 2. TỪ CHỐI ĐƠN (pending -> rejected)
    if ($action === 'reject') {
        $reject_reason = trim($_POST['reject_reason'] ?? '');
        if ($booking['status'] !== 'pending') {
            set_flash('danger', 'Chỉ có thể từ chối đơn đang ở trạng thái Chờ xác nhận (pending).');
        } elseif (empty($reject_reason)) {
            set_flash('danger', 'Vui lòng nhập lý do từ chối đơn đặt phòng.');
        } else {
            $stmtReject = $pdo->prepare("UPDATE bookings SET status = 'rejected', reject_reason = ? WHERE id = ?");
            $stmtReject->execute([$reject_reason, $booking['id']]);
            set_flash('warning', 'Đã từ chối đơn đặt phòng.');
        }
        redirect('/admin/booking_detail.php?id=' . $booking['id']);
    }

    // 3. NHẬN PHÒNG (confirmed -> checked_in)
    if ($action === 'checkin') {
        if ($booking['status'] !== 'confirmed') {
            set_flash('danger', 'Chỉ đơn đã xác nhận (confirmed) mới có thể chuyển sang nhận phòng (checked_in).');
        } else {
            $stmtCheckin = $pdo->prepare("UPDATE bookings SET status = 'checked_in' WHERE id = ?");
            $stmtCheckin->execute([$booking['id']]);
            set_flash('success', 'Đã ghi nhận khách nhận phòng (checked_in) thành công!');
        }
        redirect('/admin/booking_detail.php?id=' . $booking['id']);
    }

    // 4. TRẢ PHÒNG (checked_in -> checked_out)
    if ($action === 'checkout') {
        if ($booking['status'] !== 'checked_in') {
            set_flash('danger', 'Chỉ đơn đang ở (checked_in) mới có thể chuyển sang trả phòng (checked_out).');
        } else {
            $stmtCheckout = $pdo->prepare("UPDATE bookings SET status = 'checked_out' WHERE id = ?");
            $stmtCheckout->execute([$booking['id']]);
            set_flash('success', 'Đã ghi nhận khách trả phòng (checked_out) thành công!');
        }
        redirect('/admin/booking_detail.php?id=' . $booking['id']);
    }

    // 5. GHI NHẬN THANH TOÁN (payments)
    if ($action === 'record_payment') {
        $allowed_statuses = ['confirmed', 'checked_in', 'checked_out'];
        if (!in_array($booking['status'], $allowed_statuses)) {
            set_flash('danger', 'Chỉ ghi nhận thanh toán cho đơn ở trạng thái Đã xác nhận, Đã nhận phòng hoặc Đã trả phòng.');
        } else {
            // Kiểm tra xem đơn này đã thanh toán chưa (Quy tắc 5.5: Mỗi đơn ghi nhận 1 lần thanh toán đủ tiền)
            $stmtChkPay = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE booking_id = ?");
            $stmtChkPay->execute([$booking['id']]);
            if ($stmtChkPay->fetchColumn() > 0) {
                set_flash('danger', 'Đơn đặt phòng này đã được ghi nhận thanh toán trước đó! Không thể thanh toán lần 2.');
            } else {
                $pay_amount = (float)($_POST['amount'] ?? $booking['total_amount']);
                $pay_method = in_array($_POST['method'] ?? '', ['cash', 'bank_transfer']) ? $_POST['method'] : 'cash';
                $pay_note   = trim($_POST['transaction_note'] ?? '');

                if ($pay_amount <= 0) {
                    set_flash('danger', 'Số tiền thanh toán phải lớn hơn 0 VNĐ.');
                } else {
                    $stmtAddPayment = $pdo->prepare("
                        INSERT INTO payments (booking_id, amount, method, transaction_note, paid_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmtAddPayment->execute([$booking['id'], $pay_amount, $pay_method, $pay_note]);
                    set_flash('success', 'Ghi nhận thanh toán thành công!');
                }
            }
        }
        redirect('/admin/booking_detail.php?id=' . $booking['id']);
    }

    // 6. HOÀN THÀNH ĐƠN (checked_out -> completed)
    if ($action === 'complete') {
        if ($booking['status'] !== 'checked_out') {
            set_flash('danger', 'Chỉ đơn đã trả phòng (checked_out) mới có thể chuyển sang Hoàn thành (completed).');
        } else {
            // Quy tắc 5.5: Đơn chỉ chuyển sang completed khi đã có thanh toán
            $stmtChkPay = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE booking_id = ?");
            $stmtChkPay->execute([$booking['id']]);
            $isPaid = $stmtChkPay->fetchColumn() > 0;

            if (!$isPaid) {
                set_flash('danger', 'Không thể hoàn thành đơn! Đơn này chưa được ghi nhận thanh toán đủ tiền.');
            } else {
                $stmtComplete = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
                $stmtComplete->execute([$booking['id']]);
                set_flash('success', 'Đơn đặt phòng đã được hoàn tất thành công!');
            }
        }
        redirect('/admin/booking_detail.php?id=' . $booking['id']);
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <div class="small mb-1">
            <a href="/admin/bookings.php" class="text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Quay lại danh sách đơn
            </a>
        </div>
        <h3 class="fw-bold mb-0">Mã đơn: <?= e($booking['booking_code']) ?></h3>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="fs-6 me-2">Trạng thái:</span>
        <span class="fs-6"><?= get_status_badge($booking['status']) ?></span>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Cột trái: Chi tiết đơn & Khách hàng -->
    <div class="col-lg-8">
        <!-- Thông tin phòng & Cơ sở lưu trú -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-door-open me-2"></i>Thông tin phòng lưu trú</h6>
                <span class="badge <?= $booking['property_type'] === 'homestay' ? 'bg-success' : 'bg-primary' ?>">
                    <?= get_property_type_text($booking['property_type']) ?>
                </span>
            </div>
            <div class="card-body p-4">
                <div class="row align-items-center mb-3">
                    <div class="col-md-3">
                        <?php $img_src = !empty($booking['room_image']) ? '/assets/images/' . e($booking['room_image']) : '/assets/images/no_image.jpg'; ?>
                        <img src="<?= $img_src ?>" alt="Phòng" class="rounded w-100" style="height: 100px; object-fit: cover;">
                    </div>
                    <div class="col-md-9">
                        <h5 class="fw-bold mb-1"><?= e($booking['property_name']) ?></h5>
                        <div class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i><?= e($booking['property_address']) ?>, <?= e($booking['property_city']) ?></div>
                        <div class="fw-semibold text-primary">Phòng <?= e($booking['room_number']) ?> (<?= e($booking['room_type_name']) ?>)</div>
                    </div>
                </div>

                <div class="row g-3 p-3 bg-light rounded-3 mt-2">
                    <div class="col-sm-6">
                        <div class="text-muted small">Ngày nhận phòng (Check-in):</div>
                        <div class="fw-bold fs-6"><?= format_date($booking['check_in']) ?> (Từ 14:00)</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Ngày trả phòng (Check-out):</div>
                        <div class="fw-bold fs-6"><?= format_date($booking['check_out']) ?> (Trước 12:00)</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Số đêm:</div>
                        <div class="fw-semibold"><?= $booking['nights'] ?> đêm</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Số lượng khách:</div>
                        <div class="fw-semibold"><?= $booking['guests'] ?> khách (Sức chứa tối đa: <?= $booking['capacity'] ?>)</div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Ngày đặt:</div>
                        <div class="fw-semibold"><?= format_datetime($booking['created_at']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Thông tin khách hàng & người nhận phòng -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0"><i class="bi bi-person-lines-fill text-primary me-2"></i>Thông tin liên hệ & Khách hàng</h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Tên người nhận phòng:</div>
                        <div class="fw-bold fs-6"><?= e($booking['contact_name']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Số điện thoại liên hệ:</div>
                        <div class="fw-bold fs-6"><?= e($booking['contact_phone']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Tài khoản khách hàng:</div>
                        <div><?= e($booking['customer_name']) ?> (<?= e($booking['customer_email']) ?>)</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Địa chỉ khách:</div>
                        <div><?= e($booking['customer_address'] ?? 'Chưa cập nhật') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Ghi chú từ khách:</div>
                        <div class="p-2 bg-light rounded mt-1"><?= !empty($booking['note']) ? nl2br(e($booking['note'])) : '<em>Không có ghi chú</em>' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nếu đơn bị từ chối -->
        <?php if ($booking['status'] === 'rejected'): ?>
            <div class="alert alert-danger p-3">
                <h6 class="fw-bold"><i class="bi bi-x-octagon-fill me-2"></i>Đơn đã bị từ chối</h6>
                <div class="small">Lý do: <?= e($booking['reject_reason'] ?? 'Không có lý do') ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cột phải: Bảng thanh toán & CÁC NÚT ĐIỀU HÀNH NGHIỆP VỤ -->
    <div class="col-lg-4">
        <!-- Bảng tính tiền & Thanh toán -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0"><i class="bi bi-cash-stack text-success me-2"></i>Chi phí & Thanh toán</h6>
            </div>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Đơn giá đã chốt:</span>
                    <span class="fw-semibold"><?= format_money($booking['price_per_night']) ?>/đêm</span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Số đêm (<?= $booking['nights'] ?> đêm):</span>
                    <span class="fw-semibold"><?= format_money($booking['total_amount']) ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-bold">Tổng tiền đơn:</span>
                    <span class="fw-bold fs-4 text-danger"><?= format_money($booking['total_amount']) ?></span>
                </div>

                <!-- Trạng thái thanh toán -->
                <?php if ($payment): ?>
                    <div class="p-3 bg-success bg-opacity-10 border border-success rounded-3 text-success">
                        <div class="fw-bold"><i class="bi bi-check-circle-fill me-1"></i> ĐÃ THANH TOÁN ĐỦ</div>
                        <div class="small mt-1">Số tiền: <strong><?= format_money($payment['amount']) ?></strong></div>
                        <div class="small">Hình thức: <?= $payment['method'] === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' ?></div>
                        <div class="small">Thời gian: <?= format_datetime($payment['paid_at']) ?></div>
                        <?php if (!empty($payment['transaction_note'])): ?>
                            <div class="small mt-1 text-muted">Ghi chú: <?= e($payment['transaction_note']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-warning bg-opacity-10 border border-warning rounded-3 text-dark mb-3">
                        <div class="fw-bold"><i class="bi bi-hourglass-split me-1"></i> CHƯA THANH TOÁN</div>
                        <div class="small mt-1">Cần ghi nhận thanh toán trước khi đơn có thể hoàn tất (completed).</div>
                    </div>

                    <!-- Form ghi nhận thanh toán (nếu đơn đang ở confirmed, checked_in hoặc checked_out) -->
                    <?php if (in_array($booking['status'], ['confirmed', 'checked_in', 'checked_out'])): ?>
                        <div class="border rounded p-3 bg-light">
                            <h6 class="fw-bold mb-2 small"><i class="bi bi-credit-card me-1"></i> Ghi nhận thanh toán</h6>
                            <form action="" method="POST">
                                <input type="hidden" name="action" value="record_payment">
                                
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Số tiền thu (VNĐ):</label>
                                    <input type="number" name="amount" class="form-control form-control-sm" value="<?= (float)$booking['total_amount'] ?>" required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Hình thức:</label>
                                    <select name="method" class="form-select form-select-sm">
                                        <option value="cash">Tiền mặt</option>
                                        <option value="bank_transfer">Chuyển khoản</option>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Ghi chú thanh toán:</label>
                                    <input type="text" name="transaction_note" class="form-control form-control-sm" placeholder="Mã GD ngân hàng hoặc ghi chú thu tiền">
                                </div>

                                <button type="submit" class="btn btn-success btn-sm w-100 fw-semibold" onclick="return confirm('Xác nhận đã thu đủ số tiền này?')">
                                    <i class="bi bi-check2-circle me-1"></i> Xác nhận đã thu tiền
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- BẢNG ĐIỀU KHIỂN HÀNH ĐỘNG DỰA TRÊN TRẠNG THÁI HIỆN TẠI -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0"><i class="bi bi-gear-wide-connected text-primary me-2"></i>Thao tác nghiệp vụ</h6>
            </div>
            <div class="card-body p-3 d-grid gap-2">
                <!-- Bước 1: Khi đơn đang PENDING -->
                <?php if ($booking['status'] === 'pending'): ?>
                    <form action="" method="POST" onsubmit="return confirm('Hệ thống sẽ kiểm tra xem phòng có bị trùng lịch với đơn khác hay không trước khi duyệt. Bạn có chắc muốn duyệt đơn này?')">
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-check-circle me-1"></i> 1. Xác nhận đơn (Giữ phòng)
                        </button>
                    </form>

                    <!-- Nút mở modal Từ chối -->
                    <button type="button" class="btn btn-outline-danger w-100 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="bi bi-x-circle me-1"></i> Từ chối đơn đặt phòng
                    </button>
                <?php endif; ?>

                <!-- Bước 2: Khi đơn đang CONFIRMED -->
                <?php if ($booking['status'] === 'confirmed'): ?>
                    <form action="" method="POST" onsubmit="return confirm('Xác nhận khách đã đến và nhận phòng thành công?')">
                        <input type="hidden" name="action" value="checkin">
                        <button type="submit" class="btn btn-info text-dark w-100 py-2 fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1"></i> 2. Khách nhận phòng (Check-in)
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Bước 3: Khi đơn đang CHECKED_IN -->
                <?php if ($booking['status'] === 'checked_in'): ?>
                    <form action="" method="POST" onsubmit="return confirm('Xác nhận khách trả phòng (Check-out)?')">
                        <input type="hidden" name="action" value="checkout">
                        <button type="submit" class="btn btn-secondary w-100 py-2 fw-semibold">
                            <i class="bi bi-box-arrow-right me-1"></i> 3. Khách trả phòng (Check-out)
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Bước 4: Khi đơn đang CHECKED_OUT -> Chờ hoàn thành (cần có payment) -->
                <?php if ($booking['status'] === 'checked_out'): ?>
                    <?php if ($payment): ?>
                        <form action="" method="POST" onsubmit="return confirm('Xác nhận hoàn tất đơn đặt phòng?')">
                            <input type="hidden" name="action" value="complete">
                            <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">
                                <i class="bi bi-check-all me-1"></i> 4. Hoàn thành đơn (Completed)
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i> Cần ghi nhận thanh toán ở trên trước khi bấm Hoàn thành đơn.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Khi đơn đã COMPLETED, REJECTED, CANCELLED -->
                <?php if (in_array($booking['status'], ['completed', 'rejected', 'cancelled'])): ?>
                    <div class="text-center text-muted small py-2">
                        <i class="bi bi-lock-fill me-1"></i> Đơn này đã đóng và không thể thay đổi trạng thái nữa.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nhập lý do từ chối -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="action" value="reject">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="rejectModalLabel">Từ chối đơn đặt phòng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Vui lòng cung cấp lý do từ chối để khách hàng có thể nắm được thông tin:</p>
                    <div class="mb-3">
                        <label for="reject_reason" class="form-label fw-semibold">Lý do từ chối <span class="required-star">*</span></label>
                        <textarea class="form-control" id="reject_reason" name="reject_reason" rows="3" required placeholder="Ví dụ: Phòng đang tiến hành sửa chữa đột xuất, không đủ người phục vụ..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xác nhận Từ chối</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
