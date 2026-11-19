<?php
/**
 * Báo cáo Thống kê Doanh thu và Số đơn đặt phòng (Dành cho Admin)
 * Nguyên tắc:
 * 1. Doanh thu thực thu = tổng payments.amount theo ngày/tháng của paid_at (Mục 5.5)
 * 2. Hiển thị dạng BẢNG, đơn giản, dễ đọc, không cần biểu đồ phức tạp (Mục 4)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$page_title = 'Báo cáo & Thống kê doanh thu';

// Chế độ xem: 'month' (theo từng tháng trong năm) hoặc 'day' (theo từng ngày trong tháng)
$view_mode = $_GET['view'] ?? 'month';
$year      = (int)($_GET['year'] ?? date('Y'));
$month     = (int)($_GET['month'] ?? date('m'));

if ($year < 2020 || $year > 2035) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('m');

$stats = [];
$total_revenue = 0;
$total_cash = 0;
$total_bank = 0;
$total_payments_count = 0;

if ($view_mode === 'month') {
    // 1. THỐNG KÊ THEO TỪNG THÁNG TRONG NĂM
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(paid_at) as report_month,
            COUNT(*) as payment_count,
            SUM(CASE WHEN method = 'cash' THEN amount ELSE 0 END) as cash_amount,
            SUM(CASE WHEN method = 'bank_transfer' THEN amount ELSE 0 END) as bank_amount,
            SUM(amount) as total_amount
        FROM payments
        WHERE YEAR(paid_at) = ?
        GROUP BY MONTH(paid_at)
        ORDER BY report_month ASC
    ");
    $stmt->execute([$year]);
    $results = $stmt->fetchAll();

    // Map vào 12 tháng
    $monthly_map = [];
    foreach ($results as $r) {
        $monthly_map[(int)$r['report_month']] = $r;
        $total_revenue += (float)$r['total_amount'];
        $total_cash    += (float)$r['cash_amount'];
        $total_bank    += (float)$r['bank_amount'];
        $total_payments_count += (int)$r['payment_count'];
    }

    for ($m = 1; $m <= 12; $m++) {
        $stats[$m] = $monthly_map[$m] ?? [
            'report_month'  => $m,
            'payment_count' => 0,
            'cash_amount'   => 0,
            'bank_amount'   => 0,
            'total_amount'  => 0,
        ];
    }
} else {
    // 2. THỐNG KÊ CHI TIẾT THEO TỪNG NGÀY TRONG THÁNG
    $stmt = $pdo->prepare("
        SELECT pay.*, 
               b.booking_code, b.nights, b.guests,
               c.full_name as customer_name,
               p.name as property_name, r.room_number
        FROM payments pay
        JOIN bookings b ON pay.booking_id = b.id
        JOIN customers c ON b.customer_id = c.id
        JOIN rooms r ON b.room_id = r.id
        JOIN properties p ON r.property_id = p.id
        WHERE MONTH(pay.paid_at) = ? AND YEAR(pay.paid_at) = ?
        ORDER BY pay.paid_at DESC
    ");
    $stmt->execute([$month, $year]);
    $stats = $stmt->fetchAll();

    foreach ($stats as $s) {
        $total_revenue += (float)$s['amount'];
        if ($s['method'] === 'cash') {
            $total_cash += (float)$s['amount'];
        } else {
            $total_bank += (float)$s['amount'];
        }
        $total_payments_count++;
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-bar-chart-line text-primary me-2"></i>Thống kê Doanh thu thực thu</h3>
        <p class="text-muted small mb-0">Thống kê chuẩn dựa trên các giao dịch thanh toán thành công (payments) theo ngày hoặc theo tháng</p>
    </div>

    <!-- Nút chuyển đổi chế độ xem -->
    <div class="btn-group shadow-sm">
        <a href="/admin/reports.php?view=month&year=<?= $year ?>" class="btn btn-sm <?= $view_mode === 'month' ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
            <i class="bi bi-calendar3 me-1"></i> Báo cáo theo Tháng
        </a>
        <a href="/admin/reports.php?view=day&year=<?= $year ?>&month=<?= $month ?>" class="btn btn-sm <?= $view_mode === 'day' ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
            <i class="bi bi-calendar-event me-1"></i> Báo cáo theo Ngày
        </a>
    </div>
</div>

<!-- Bộ lọc thời gian -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/reports.php" class="row g-2 align-items-center">
            <input type="hidden" name="view" value="<?= e($view_mode) ?>">

            <?php if ($view_mode === 'day'): ?>
                <div class="col-auto">
                    <label class="col-form-label fw-semibold small">Tháng:</label>
                </div>
                <div class="col-md-2">
                    <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>>Tháng <?= $m ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-auto">
                <label class="col-form-label fw-semibold small">Năm:</label>
            </div>
            <div class="col-md-2">
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>>Năm <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-filter me-1"></i> Xem báo cáo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 4 Thẻ KPI Tóm tắt Doanh thu -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Tổng doanh thu thực thu</div>
                <h4 class="fw-bold my-1 text-success"><?= format_money($total_revenue) ?></h4>
                <small class="text-muted"><?= $view_mode === 'month' ? "Năm {$year}" : "Tháng {$month}/{$year}" ?></small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Thu tiền mặt (Cash)</div>
                <h4 class="fw-bold my-1 text-primary"><?= format_money($total_cash) ?></h4>
                <small class="text-muted">Tại quầy lễ tân</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Thu chuyển khoản</div>
                <h4 class="fw-bold my-1 text-info"><?= format_money($total_bank) ?></h4>
                <small class="text-muted">Qua ngân hàng</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm border-start border-warning border-4 h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Số lượt thanh toán</div>
                <h4 class="fw-bold my-1 text-warning"><?= $total_payments_count ?></h4>
                <small class="text-muted">Đơn đã hoàn tất thu tiền</small>
            </div>
        </div>
    </div>
</div>

<!-- BẢNG BÁO CÁO CHI TIẾT -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0 text-primary">
            <i class="bi bi-table me-2"></i>
            <?= $view_mode === 'month' ? "Bảng thống kê 12 tháng năm {$year}" : "Bảng chi tiết các khoản thu trong Tháng {$month}/{$year}" ?>
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <?php if ($view_mode === 'month'): ?>
                <!-- Bảng báo cáo theo từng tháng -->
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Thời gian</th>
                            <th class="text-center">Số lượt thanh toán</th>
                            <th class="text-end">Tiền mặt</th>
                            <th class="text-end">Chuyển khoản</th>
                            <th class="text-end fw-bold">Tổng thực thu</th>
                            <th class="text-center">Chi tiết ngày</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats as $m => $row): ?>
                            <tr class="<?= $row['total_amount'] > 0 ? '' : 'text-muted' ?>">
                                <td class="fw-bold">Tháng <?= $m ?>/<?= $year ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $row['payment_count'] > 0 ? 'bg-secondary' : 'bg-light text-muted' ?>">
                                        <?= $row['payment_count'] ?> đơn
                                    </span>
                                </td>
                                <td class="text-end"><?= format_money($row['cash_amount']) ?></td>
                                <td class="text-end"><?= format_money($row['bank_amount']) ?></td>
                                <td class="text-end fw-bold text-success fs-6">
                                    <?= format_money($row['total_amount']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['payment_count'] > 0): ?>
                                        <a href="/admin/reports.php?view=day&year=<?= $year ?>&month=<?= $m ?>" class="btn btn-sm btn-outline-primary py-0">
                                            Xem ngày
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td>TỔNG CỘNG NĂM <?= $year ?></td>
                            <td class="text-center"><?= $total_payments_count ?> đơn</td>
                            <td class="text-end"><?= format_money($total_cash) ?></td>
                            <td class="text-end"><?= format_money($total_bank) ?></td>
                            <td class="text-end text-danger fs-5"><?= format_money($total_revenue) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else: ?>
                <!-- Bảng báo cáo chi tiết theo ngày trong tháng -->
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ngày thanh toán</th>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Cơ sở & Phòng</th>
                            <th>Hình thức</th>
                            <th>Ghi chú</th>
                            <th class="text-end">Số tiền thực thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stats)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Chưa có khoản thanh toán nào trong tháng <?= $month ?>/<?= $year ?>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stats as $item): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= format_datetime($item['paid_at']) ?></div>
                                    </td>
                                    <td>
                                        <a href="/admin/booking_detail.php?id=<?= $item['booking_id'] ?>" class="fw-bold text-decoration-none">
                                            <?= e($item['booking_code']) ?>
                                        </a>
                                    </td>
                                    <td><?= e($item['customer_name']) ?></td>
                                    <td>
                                        <div><?= e($item['property_name']) ?></div>
                                        <small class="text-muted">Phòng: <?= e($item['room_number']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($item['method'] === 'cash'): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Tiền mặt</span>
                                        <?php else: ?>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info">Chuyển khoản</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= e($item['transaction_note'] ?? 'Không có') ?></small></td>
                                    <td class="text-end fw-bold text-success fs-6">
                                        <?= format_money($item['amount']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="6">TỔNG THỰC THU THÁNG <?= $month ?>/<?= $year ?></td>
                            <td class="text-end text-danger fs-5"><?= format_money($total_revenue) ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
