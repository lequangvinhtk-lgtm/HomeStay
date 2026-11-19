# TÀI LIỆU TEST CASES HỆ THỐNG ĐẶT PHÒNG KHÁCH SẠN & HOMESTAY (ItachiHome)

Tài liệu này bao gồm 12 kịch bản kiểm thử (Test Cases) quan trọng nhất phục vụ việc kiểm tra và báo cáo bảo vệ đồ án.

---

### BẢNG TỔNG HỢP 12 TEST CASES

| STT | Kịch bản kiểm thử (Test Case) | Các bước thực hiện | Dữ liệu đầu vào | Kết quả mong đợi | Trạng thái |
|:---:|:---|:---|:---|:---|:---:|
| **TC01** | **Đăng ký trùng email** | 1. Vào `/register.php`<br>2. Nhập thông tin với email đã tồn tại trong CSDL<br>3. Bấm "Hoàn tất đăng ký" | Email: `nguyenvanan@gmail.com`<br>Pass: `123456` | Báo lỗi: *"Email này đã được sử dụng. Vui lòng chọn email khác..."*. Không cho tạo tài khoản. | **PASS** |
| **TC02** | **Đăng nhập sai mật khẩu** | 1. Vào `/login.php`<br>2. Nhập email đúng, mật khẩu sai<br>3. Bấm "Đăng nhập" | Email: `nguyenvanan@gmail.com`<br>Pass: `sai_mat_khau` | Báo lỗi: *"Email hoặc mật khẩu không chính xác"*. Không đăng nhập được. | **PASS** |
| **TC03** | **Khách truy cập khu vực `/admin`** | 1. Đăng nhập với tài khoản khách (`role = customer`)<br>2. Gõ trực tiếp URL `/admin/dashboard.php` | Tài khoản: `nguyenvanan@gmail.com` | Bị hàm `require_admin()` chặn lại, chuyển hướng về `/admin/login.php` kèm thông báo lỗi. | **PASS** |
| **TC04** | **Khách A mở đơn của khách B** | 1. Khách A đăng nhập (`customer_id = 1`)<br>2. Nhập URL xem đơn của khách B: `/booking_detail.php?id=2` | Khách A truy cập đơn `id = 2` (thuộc khách B) | Bị chặn bởi điều kiện `WHERE id = ? AND customer_id = ?`, chuyển về `/my_bookings.php` báo không có quyền. | **PASS** |
| **TC05** | **Đặt phòng với ngày trả $\le$ ngày nhận** | 1. Vào trang đặt phòng `/booking_create.php?room_id=1`<br>2. Chọn ngày trả trước hoặc trùng ngày nhận<br>3. Gửi yêu cầu | Nhận: `2026-11-25`<br>Trả: `2026-11-24` (hoặc `2026-11-25`) | Form báo lỗi: *"Ngày trả phòng phải sau ngày nhận phòng ít nhất 1 đêm"*. Chặn gửi đơn. | **PASS** |
| **TC06** | **Đặt phòng vượt quá sức chứa** | 1. Chọn phòng sức chứa 2 người (`capacity = 2`)<br>2. Nhập số khách là 3 người<br>3. Bấm gửi đơn | Sức chứa: `2`<br>Số khách nhập: `3` | Báo lỗi: *"Số lượng khách vượt quá sức chứa tối đa của phòng..."*. Chặn tạo đơn. | **PASS** |
| **TC07** | **Tính tiền phòng chính xác** | 1. Đặt phòng đơn giá 500.000 đ/đêm<br>2. Chọn thời gian lưu trú 3 đêm (ví dụ: từ 01/12 đến 04/12)<br>3. Kiểm tra tổng tiền | Đơn giá: 500.000 đ<br>Số đêm: 3 đêm | Hiển thị và lưu vào CSDL đúng: `3 × 500.000 = 1.500.000 đ`. Đơn giá được chốt cố định tại thời điểm đặt. | **PASS** |
| **TC08** | **Chống đặt trùng lịch khi admin duyệt** | 1. Hai đơn A và B cùng đặt phòng 101 trong khoảng 15/12 - 18/12 (trạng thái pending)<br>2. Admin bấm duyệt đơn A<br>3. Admin bấm duyệt tiếp đơn B | Đơn A: 15/12 - 18/12<br>Đơn B: 16/12 - 19/12 (trùng lịch) | Đơn A duyệt thành công (`confirmed`). Đơn B bị câu SQL chống trùng chặn lại, báo: *"Phòng đã có người đặt trong thời gian này!"*. | **PASS** |
| **TC09** | **Quy tắc hủy đơn của khách hàng** | 1. Với đơn `pending` và ngày hiện tại trước ngày nhận phòng: bấm "Hủy đơn"<br>2. Với đơn đã `confirmed`: kiểm tra nút hủy | Đơn pending: trước check-in<br>Đơn confirmed | Đơn pending: hủy thành công (`cancelled`). Đơn confirmed: hệ thống không hiển thị nút hủy, từ chối hủy. | **PASS** |
| **TC10** | **Chặn ghi nhận thanh toán lần 2** | 1. Vào đơn đã thanh toán trong `/admin/booking_detail.php`<br>2. Kiểm tra form ghi nhận thanh toán | Đơn đã có bản ghi trong bảng `payments` | Ràng buộc `UNIQUE(booking_id)` và kiểm tra PHP ẩn form và chặn thao tác thanh toán lần 2. | **PASS** |
| **TC11** | **Xóa an toàn phòng đã có dữ liệu đơn** | 1. Vào `/admin/rooms.php`<br>2. Bấm Xóa phòng 101 (đã có đơn đặt trong lịch sử) | Phòng 101 (có đơn trong bảng `bookings`) | Hệ thống KHÔNG xóa vật lý, mà tự động cập nhật `status = 'inactive'` để bảo toàn lịch sử đơn. | **PASS** |
| **TC12** | **Thống kê chỉ tính các khoản đã thu tiền** | 1. Vào `/admin/reports.php`<br>2. So sánh tổng doanh thu với tổng giá trị các đơn chưa thanh toán | Bảng `payments` (các đơn thực thu) | Doanh thu chỉ cộng tổng cột `payments.amount`, không cộng các đơn pending, confirmed chưa thanh toán hoặc bị hủy. | **PASS** |

---

### HƯỚNG DẪN THỰC HIỆN TEST THỰC TẾ KHI BÁO CÁO

1. **Test đăng nhập & phân quyền:**
   - Dùng tài khoản `nguyenvanan@gmail.com` / `123456` để kiểm tra các quyền của Khách hàng.
   - Dùng tài khoản `admin@gmail.com` / `admin123` để vào `/admin/dashboard.php`.
2. **Test chống trùng lịch:**
   - Vào `/admin/bookings.php`, tìm các đơn có cùng phòng và khoảng ngày đè lên nhau, bấm duyệt từng đơn để thấy hệ thống bắt lỗi chuẩn xác.
