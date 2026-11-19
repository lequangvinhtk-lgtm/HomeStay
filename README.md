# STAYEASY - WEBSITE ĐẶT PHÒNG KHÁCH SẠN & HOMESTAY
> **Đồ án cơ sở 2 - Dành cho sinh viên năm 2**  
> Ngôn ngữ: **PHP 8 thuần (procedural - thủ tục) + PDO + MySQL + Bootstrap 5 CDN**  
> Môi trường triển khai: **Ubuntu / Linux (Apache, PHP, MySQL)**

---

## 1. TỔNG QUAN DỰ ÁN

Hệ thống quản lý đặt phòng khách sạn và homestay trực tuyến đáp ứng đầy đủ yêu cầu cho cả 2 đối tượng:
- **Khách hàng (Customer):** Xem danh sách, tìm kiếm phòng theo địa điểm, thời gian lưu trú và số khách; đăng ký, đăng nhập, quản lý hồ sơ, gửi yêu cầu đặt phòng, theo dõi lịch sử và hủy đơn theo quy định.
- **Quản trị viên (Admin):** Quản lý cơ sở lưu trú, loại phòng, phòng cụ thể; duyệt đơn đặt phòng với **thuật toán chống trùng lịch (Mục 5.3)**; quản lý check-in, check-out; ghi nhận thanh toán và xem báo cáo doanh thu thực thu dạng bảng theo ngày/tháng.

Code được viết theo phong cách **PHP thủ tục (procedural)**, mỗi trang một file `.php`, kết hợp HTML và xử lý form trong cùng file, kèm chú thích tiếng Việt rõ ràng, dễ giải thích khi bảo vệ đồ án.

---

## 2. TÀI KHOẢN MẪU THỬ NGHIỆM

Sau khi nạp dữ liệu mẫu từ `database/seed.sql`, bạn có thể đăng nhập bằng các tài khoản sau:

### 2.1. Tài khoản Quản trị viên (Admin)
- **URL đăng nhập Admin:** `http://localhost/admin/login.php` (hoặc `http://localhost:8000/admin/login.php`)
- **Email:** `admin@gmail.com`
- **Mật khẩu:** `admin123`

### 2.2. Tài khoản Khách hàng (Customer)
- **URL đăng nhập Khách:** `http://localhost/login.php` (hoặc `http://localhost:8000/login.php`)
- **Khách 1:** `nguyenvanan@gmail.com` / Mật khẩu: `123456` (Nguyễn Văn An - Đà Nẵng)
- **Khách 2:** `lethibich@gmail.com` / Mật khẩu: `123456` (Lê Thị Bích - Hà Nội)
- **Khách 3:** `tranquanghuy@gmail.com` / Mật khẩu: `123456` (Trần Quang Huy - TP. HCM)

---

## 3. HƯỚNG DẪN CÀI ĐẶT TRÊN UBUNTU / LINUX

### Bước 1: Cài đặt các gói cần thiết (Apache, PHP, MySQL)
Mở Terminal trên Ubuntu và chạy các lệnh:
```bash
sudo apt update
sudo apt install -y apache2 mysql-server php libapache2-mod-php php-mysql php-mbstring
```

### Bước 2: Tạo Cơ sở dữ liệu và nạp dữ liệu mẫu
1. Đăng nhập vào MySQL:
```bash
sudo mysql -u root -p
```
2. Thực thi 2 file SQL theo đúng thứ tự (hoặc copy nội dung chạy trực tiếp trong MySQL / phpMyAdmin):
```sql
SOURCE /đường_dẫn_tới_thư_mục_dự_án/database/schema.sql;
SOURCE /đường_dẫn_tới_thư_mục_dự_án/database/seed.sql;
```

### Bước 3: Cấu hình kết nối Cơ sở dữ liệu
Mở file `config/db.php` và kiểm tra thông số kết nối:
```php
$db_host = '127.0.0.1';
$db_name = 'hotel_booking';
$db_user = 'vinh';        // Tài khoản MySQL của bạn
$db_pass = 'Vinh@2007';   // Mật khẩu MySQL của bạn
$db_port = 3306;
```

### Bước 4: Chạy Website

#### Cách 1: Dùng máy chủ PHP tích hợp (Khuyên dùng khi lập trình & kiểm tra nhanh trên VS Code)
Tại thư mục gốc của dự án, mở Terminal và chạy lệnh:
```bash
php -S localhost:8000
```
Mở trình duyệt Google Chrome và truy cập: `http://localhost:8000`

#### Cách 2: Triển khai qua Apache
1. Tạo liên kết biểu tượng (symlink) hoặc copy thư mục vào DocumentRoot của Apache:
```bash
sudo ln -s /đường_dẫn_tới_thư_mục_dự_án /var/www/html/hotel-booking
```
2. Phân quyền ghi cho thư mục ảnh `assets/images/`:
```bash
chmod -R 777 assets/images/
```
3. Truy cập trình duyệt: `http://localhost/hotel-booking/index.php`

---

## 4. CẤU TRÚC THƯ MỤC DỰ ÁN

```
hotel-booking/
├── config/
│   └── db.php                  # Kết nối PDO với MySQL (chống SQL Injection)
├── includes/
│   ├── functions.php           # Các hàm tiện ích dùng chung (format tiền, ngày, check role, flash)
│   ├── header.php, footer.php  # Khung giao diện dùng chung cho khách hàng
│   └── admin_header.php, admin_footer.php # Khung giao diện trang quản trị
├── admin/                      # Khu vực quản trị viên (/admin)
│   ├── login.php, logout.php   # Đăng nhập & đăng xuất Quản trị viên
│   ├── dashboard.php           # Bảng điều khiển tổng quan số liệu
│   ├── properties.php, property_form.php # Quản lý cơ sở lưu trú (Khách sạn/Homestay)
│   ├── room_types.php, room_type_form.php # Quản lý loại phòng
│   ├── rooms.php, room_form.php # Quản lý phòng cụ thể
│   ├── bookings.php, booking_detail.php   # Quản lý & duyệt đơn, chống trùng lịch
│   ├── customers.php, customer_detail.php # Quản lý thông tin & lịch sử khách
│   └── reports.php             # Thống kê doanh thu theo ngày/tháng (dạng bảng)
├── assets/
│   ├── css/style.css           # CSS tùy chỉnh
│   ├── js/main.js              # JavaScript xác nhận confirm() và tính nhanh tiền phòng
│   └── images/                 # Thư mục lưu trữ hình ảnh tải lên và ảnh mẫu
├── database/
│   ├── schema.sql              # Cấu trúc 7 bảng MySQL
│   └── seed.sql                # Dữ liệu mẫu ban đầu (admin, khách, phòng, đơn, thanh toán)
├── docs/
│   ├── test-cases.md           # 12 kịch bản kiểm thử chuẩn
│   └── giai-thich-bao-ve.md    # Tài liệu tóm tắt 5 chức năng chính để bảo vệ đồ án
├── index.php                   # Trang chủ & form tìm phòng nhanh
├── properties.php              # Danh sách khách sạn & homestay
├── property_detail.php         # Chi tiết cơ sở lưu trú & danh sách phòng
├── search.php                  # Tìm kiếm phòng khả dụng (chống trùng lịch)
├── register.php                # Đăng ký tài khoản khách hàng mới
├── login.php, logout.php       # Đăng nhập / Đăng xuất khách hàng
├── profile.php                 # Quản lý thông tin cá nhân & đổi mật khẩu
├── booking_create.php          # Form tạo yêu cầu đặt phòng
├── my_bookings.php             # Danh sách và lịch sử đặt phòng của khách
├── booking_detail.php          # Chi tiết đơn & chức năng hủy đơn của khách
└── README.md                   # Hướng dẫn cài đặt & tổng quan hệ thống
```

---

## 5. QUY TẮC NGHIỆP VỤ CỐT LÕI

1. **Luồng trạng thái đơn đặt phòng (`bookings.status`):**
   ```
   pending ──► confirmed ──► checked_in ──► checked_out ──► completed
      │
      ├──► rejected (Admin từ chối - có ghi chú lý do)
      └──► cancelled (Khách tự hủy khi còn ở pending và trước ngày nhận phòng)
   ```
2. **Quy tắc chống trùng lịch phòng (Mục 5.3):**
   - Đơn `pending` chưa giữ phòng. Chỉ đơn `confirmed` và `checked_in` mới tính là giữ phòng.
   - Hai khoảng ngày lưu trú bị trùng nhau khi:
     $$\text{check\_in}_{\text{mới}} < \text{check\_out}_{\text{cũ}} \quad \text{VÀ} \quad \text{check\_out}_{\text{mới}} > \text{check\_in}_{\text{cũ}}$$
   - Khi Admin bấm **Xác nhận đơn**, hệ thống tự động chạy câu truy vấn kiểm tra điều kiện trên; nếu đã có đơn giữ chỗ thì chặn không cho duyệt.
3. **Thanh toán & Doanh thu thực thu:**
   - Mỗi đơn chỉ thanh toán đủ tiền **1 lần duy nhất** (ràng buộc `UNIQUE` trên cột `payments.booking_id`).
   - Đơn chỉ có thể chuyển sang `completed` khi đã qua bước trả phòng (`checked_out`) và đã có bản ghi thanh toán trong `payments`.
   - Doanh thu thực thu bằng tổng số tiền cột `payments.amount` theo thời điểm thu thực tế (`paid_at`).
4. **Xóa an toàn dữ liệu:**
   - Cơ sở hoặc phòng nếu đã có đơn đặt phòng liên quan trong lịch sử thì khi bấm xóa, hệ thống sẽ chuyển sang trạng thái `inactive` (ngừng hoạt động) chứ không xóa vật lý, đảm bảo toàn vẹn dữ liệu kế toán và lịch sử khách hàng.

---

## 6. TÀI LIỆU HỖ TRỢ BẢO VỆ ĐỒ ÁN

- Xem chi tiết 12 kịch bản kiểm thử tại file [docs/test-cases.md](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/docs/test-cases.md).
- Xem hướng dẫn trả lời vấn đáp giáo viên về 5 chức năng chính kèm câu lệnh SQL tại file [docs/giai-thich-bao-ve.md](file:///home/quangvinh/Downloads/DoAnCoSo2_HomestayDonGIan/docs/giai-thich-bao-ve.md).
# HomeStay
