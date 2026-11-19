# TÀI LIỆU HƯỚNG DẪN BẢO VỆ ĐỒ ÁN (DÀNH CHO SINH VIÊN)
**Đề tài: Website Đặt phòng Khách sạn & Homestay (ItachiHome)**
*Công nghệ: PHP 8 thuần (thủ tục) + MySQL + PDO (Prepared Statements) + Bootstrap 5*

---

> [!NOTE]
> Tài liệu này tóm tắt ngắn gọn và dễ hiểu về **luồng chạy, file xử lý và câu lệnh SQL then chốt** của 5 chức năng trọng tâm nhất. Bạn có thể mở tài liệu này khi thầy/cô hỏi vấn đáp để trả lời tự tin, mạch lạc.

---

## 1. CHỨC NĂNG ĐĂNG NHẬP VÀ PHÂN QUYỀN

### 1.1. File đảm nhận:
- [login.php](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/login.php) (Dành cho khách hàng và đăng nhập chung)
- [admin/login.php](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/admin/login.php) (Dành riêng cho Quản trị viên)
- [includes/functions.php](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/includes/functions.php) (Hàm `is_logged_in()`, `is_admin()`, `require_login()`, `require_admin()`)

### 1.2. Luồng xử lý:
1. Người dùng gửi Email và Mật khẩu dạng plain text qua phương thức `POST`.
2. Hệ thống tìm tài khoản trong bảng `users` bằng PDO Prepared Statement theo email.
3. Sử dụng hàm chuẩn PHP `password_verify($password, $user['password_hash'])` để so sánh mật khẩu nhập vào với mã hash bcrypt trong CSDL.
4. Nếu khớp:
   - Nếu `role = 'customer'`: Truy vấn tiếp bảng `customers` để lấy `customer_id` và `full_name`, lưu vào `$_SESSION`, sau đó chuyển về trang chủ hoặc trang đặt phòng trước đó.
   - Nếu `role = 'admin'`: Lưu session và chuyển hướng vào `/admin/dashboard.php`.
5. Ở đầu mỗi trang admin, gọi hàm `require_admin()` kiểm tra: nếu không phải `role = 'admin'` thì chuyển hướng ngay về trang đăng nhập admin.

### 1.3. Đoạn SQL then chốt:
```sql
-- Lấy thông tin tài khoản theo email (dùng dấu ? chống SQL Injection)
SELECT id, email, password_hash, role 
FROM users 
WHERE email = ?;

-- Nếu là khách hàng, lấy thêm họ tên và customer_id
SELECT id, full_name 
FROM customers 
WHERE user_id = ?;
```

---

## 2. CHỨC NĂNG TÌM KIẾM PHÒNG (KÈM LỌC TRỐNG)

### 2.1. File đảm nhận:
- [search.php](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/search.php) (Trang tìm kiếm kết quả)
- [index.php](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/index.php) (Form tìm kiếm nhanh ở Hero Banner)

### 2.2. Luồng xử lý:
1. Nhận các tiêu chí qua phương thức `GET`: Thành phố (`city`), Loại hình (`type`), Ngày nhận (`check_in`), Ngày trả (`check_out`), Số khách (`guests`).
2. Kiểm tra tính hợp lệ: `check_in >= hôm nay` và `check_out > check_in`.
3. Chỉ lấy những phòng và cơ sở đang hoạt động (`status = 'active'`).
4. **Điều kiện lọc phòng trống:** Một phòng được coi là khả dụng trong khoảng thời gian người dùng tìm kiếm khi phòng đó **KHÔNG có bất kỳ đơn nào ở trạng thái `confirmed` hoặc `checked_in` bị đè/trùng lịch**. Sử dụng cấu trúc `NOT EXISTS` kết hợp công thức giao thoa ngày.

### 2.3. Đoạn SQL then chốt:
```sql
SELECT r.*, p.name AS property_name, p.city AS property_city, rt.name AS room_type_name
FROM rooms r
JOIN properties p ON r.property_id = p.id
JOIN room_types rt ON r.room_type_id = rt.id
WHERE r.status = 'active' 
  AND p.status = 'active'
  AND rt.status = 'active'
  AND p.city = :city
  AND r.capacity >= :guests
  -- Điều kiện chống trùng lịch: Loại bỏ các phòng đã có đơn giữ chỗ trong khoảng ngày này
  AND NOT EXISTS (
      SELECT 1 FROM bookings b
      WHERE b.room_id = r.id
        AND b.status IN ('confirmed', 'checked_in')
        AND (:check_in < b.check_out AND :check_out > b.check_in)
  )
ORDER BY r.price_per_night ASC;
```

---

## 3. CHỨC NĂNG ĐẶT PHÒNG (BOOKING)

### 3.1. File đảm nhận:
- [booking_create.php](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/booking_create.php)
- [booking_detail.php](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/booking_detail.php)

### 3.2. Luồng xử lý:
1. Kiểm tra khách hàng đã đăng nhập chưa (nếu chưa, chuyển hướng sang `login.php`).
2. Lấy thông tin phòng và kiểm tra trạng thái phòng phải là `active`.
3. Khách nhập ngày nhận, ngày trả, số người, họ tên, số điện thoại người liên hệ.
4. Kiểm tra hợp lệ dữ liệu (Validation):
   - Ngày nhận không ở quá khứ.
   - Ngày trả sau ngày nhận ít nhất 1 đêm.
   - Số người $\le$ sức chứa phòng (`guests <= rooms.capacity`).
5. **Tính toán:**
   - Số đêm: `$nights = $check_in->diff($check_out)->days;`
   - Đơn giá: Lấy đơn giá niêm yết hiện tại của phòng (`$price_per_night = $room['price_per_night']`).
   - Tổng tiền: `$total_amount = $nights * $price_per_night;`
   - *Lưu ý quan trọng:* Đơn giá được lưu trực tiếp vào cột `bookings.price_per_night` tại thời điểm đặt. Sau này admin có tăng hay giảm giá phòng thì đơn cũ vẫn giữ nguyên giá trị đã chốt.
6. Tự động sinh mã đơn dạng: `BK` + `NămThángNgày` + `-` + `Số ngẫu nhiên` (VD: `BK20261117-4821`).
7. Khởi tạo đơn với trạng thái mặc định: **`pending`** (Chờ xác nhận, chưa giữ phòng).

### 3.3. Đoạn SQL then chốt:
```sql
INSERT INTO bookings (
    booking_code, customer_id, room_id, check_in, check_out, 
    nights, guests, contact_name, contact_phone, note, 
    price_per_night, total_amount, status, created_at
) VALUES (
    ?, ?, ?, ?, ?, 
    ?, ?, ?, ?, ?, 
    ?, ?, 'pending', NOW()
);
```

---

## 4. CHỨC NĂNG DUYỆT ĐƠN & THUẬT TOÁN CHỐNG TRÙNG LỊCH

### 4.1. File đảm nhận:
- [admin/booking_detail.php](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/admin/booking_detail.php)
- [admin/bookings.php](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/admin/bookings.php)

### 4.2. Luồng xử lý & Giải thích thuật toán:
- Đơn ở trạng thái `pending` **chưa giữ phòng**, vì vậy nhiều khách có thể cùng gửi yêu cầu đặt phòng cho cùng một khoảng ngày.
- Khi Quản trị viên bấm nút **"Xác nhận đơn" (confirmed)**:
  - Hệ thống chạy một câu SQL `SELECT COUNT(*)` kiểm tra xem phòng này đã có đơn nào khác đang giữ chỗ (`confirmed` hoặc `checked_in`) bị đè lịch lên khoảng ngày của đơn này hay chưa.
  - **Công thức giao nhau của 2 khoảng thời gian:** Hai khoảng $[A_1, A_2]$ và $[B_1, B_2]$ trùng nhau khi và chỉ khi:
    $$\text{check\_in}_{\text{mới}} < \text{check\_out}_{\text{cũ}} \quad \text{VÀ} \quad \text{check\_out}_{\text{mới}} > \text{check\_in}_{\text{cũ}}$$
  - **Nếu `COUNT > 0`:** Nghĩa là phòng đã có người được xác nhận trước đó $\rightarrow$ Hệ thống lập tức dừng lại, báo lỗi *"Phòng đã có người đặt trong thời gian này"* và không cho xác nhận.
  - **Nếu `COUNT = 0`:** Phòng hoàn toàn trống lịch $\rightarrow$ Cập nhật `status = 'confirmed'`. Lúc này phòng chính thức được giữ.

### 4.3. Đoạn SQL then chốt:
```sql
-- 1. Kiểm tra xem có đơn nào đang giữ phòng bị trùng ngày hay không
SELECT COUNT(*) 
FROM bookings 
WHERE room_id = ? 
  AND status IN ('confirmed', 'checked_in')
  AND (? < check_out AND ? > check_in)
  AND id != ?;

-- 2. Nếu không trùng (COUNT = 0), tiến hành xác nhận đơn
UPDATE bookings 
SET status = 'confirmed' 
WHERE id = ?;

-- 3. Trường hợp từ chối đơn
UPDATE bookings 
SET status = 'rejected', reject_reason = ? 
WHERE id = ?;
```

---

## 5. CHỨC NĂNG THANH TOÁN VÀ THỐNG KÊ DOANH THU

### 5.1. File đảm nhận:
- [admin/booking_detail.php](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/admin/booking_detail.php) (Ghi nhận thanh toán và hoàn tất đơn)
- [admin/reports.php](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/admin/reports.php) (Báo cáo doanh thu theo bảng)

### 5.2. Luồng xử lý:
1. **Ghi nhận thanh toán:**
   - Chỉ được ghi nhận cho đơn ở trạng thái `confirmed`, `checked_in`, hoặc `checked_out`.
   - Bảng `payments` có khóa duy nhất: `UNIQUE KEY (booking_id)`. Do đó mỗi đơn chỉ được ghi nhận thanh toán đủ tiền **đúng 1 lần duy nhất** (tránh tình trạng ghi nhận trùng 2 lần).
   - Chọn hình thức: Tiền mặt (`cash`) hoặc Chuyển khoản (`bank_transfer`), lưu ngày giờ thanh toán (`paid_at = NOW()`).
2. **Hoàn thành đơn (`completed`):**
   - Đơn chỉ được chuyển sang `completed` khi đã qua bước trả phòng (`checked_out`) **VÀ** đã có bản ghi trong bảng `payments`.
3. **Thống kê doanh thu thực thu:**
   - **Doanh thu thực thu = Tổng cột `payments.amount`**.
   - Tuyệt đối không tính các đơn `pending`, `rejected`, `cancelled` hoặc đơn chưa thanh toán.
   - Thống kê nhóm theo tháng (`MONTH(paid_at)`) hoặc hiển thị chi tiết từng ngày, hiển thị rõ ràng bằng thẻ bảng (Table) của HTML/Bootstrap.

### 5.3. Đoạn SQL then chốt:
```sql
-- 1. Ghi nhận thanh toán đủ tiền
INSERT INTO payments (booking_id, amount, method, transaction_note, paid_at)
VALUES (?, ?, ?, ?, NOW());

-- 2. Hoàn thành đơn sau khi đã kiểm tra có thanh toán
UPDATE bookings 
SET status = 'completed' 
WHERE id = ?;

-- 3. Thống kê doanh thu thực thu theo từng tháng trong năm
SELECT 
    MONTH(paid_at) AS report_month,
    COUNT(*) AS payment_count,
    SUM(CASE WHEN method = 'cash' THEN amount ELSE 0 END) AS cash_amount,
    SUM(CASE WHEN method = 'bank_transfer' THEN amount ELSE 0 END) AS bank_amount,
    SUM(amount) AS total_amount
FROM payments
WHERE YEAR(paid_at) = ?
GROUP BY MONTH(paid_at)
ORDER BY report_month ASC;
```

---

### TÓM TẮT BẢNG MÃ TRẠNG THÁI ĐƠN HÀNG ĐỂ TRẢ LỜI NHANH:
- `pending`: Khách mới đặt, chưa thanh toán, chưa giữ phòng.
- `confirmed`: Admin đã duyệt, kiểm tra không trùng lịch, phòng chính thức được giữ.
- `checked_in`: Khách đã đến nhận phòng thực tế.
- `checked_out`: Khách đã trả phòng.
- `completed`: Đơn hoàn tất (điều kiện: đã trả phòng và đã thanh toán đủ).
- `rejected`: Admin từ chối (có ghi rõ lý do).
- `cancelled`: Khách tự hủy (chỉ hủy được khi đơn còn đang `pending` và trước ngày nhận phòng).
