-- ========================================================
-- DỮ LIỆU MẪU BAN ĐẦU (SEED DATA)
-- Mật khẩu mặc định:
--   - Admin (admin@gmail.com): admin123
--   - Khách hàng (nguyenvanan@gmail.com, lethibich@gmail.com, tranquanghuy@gmail.com): 123456
-- ========================================================

USE `hotel_booking`;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `payments`;
TRUNCATE TABLE `bookings`;
TRUNCATE TABLE `rooms`;
TRUNCATE TABLE `room_types`;
TRUNCATE TABLE `properties`;
TRUNCATE TABLE `customers`;
TRUNCATE TABLE `users`;

-- 1. Bảng users
-- Hash cho admin123: $2y$10$G5k6CY1Op7vnhBborxtWk.qxLFCj3ZmV7jiERybedEItjngDLWSiy
-- Hash cho 123456:   $2y$10$GOsb4tRBmExICElJRVHNI.nMB.bWCPeuOwSdAv5jUg.kOoNYwlJ7i
INSERT INTO `users` (`id`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'admin@gmail.com', '$2y$10$G5k6CY1Op7vnhBborxtWk.qxLFCj3ZmV7jiERybedEItjngDLWSiy', 'admin', NOW()),
(2, 'nguyenvanan@gmail.com', '$2y$10$GOsb4tRBmExICElJRVHNI.nMB.bWCPeuOwSdAv5jUg.kOoNYwlJ7i', 'customer', NOW()),
(3, 'lethibich@gmail.com', '$2y$10$GOsb4tRBmExICElJRVHNI.nMB.bWCPeuOwSdAv5jUg.kOoNYwlJ7i', 'customer', NOW()),
(4, 'tranquanghuy@gmail.com', '$2y$10$GOsb4tRBmExICElJRVHNI.nMB.bWCPeuOwSdAv5jUg.kOoNYwlJ7i', 'customer', NOW());

-- 2. Bảng customers (chỉ liên kết với users có role = customer)
INSERT INTO `customers` (`id`, `user_id`, `full_name`, `phone`, `address`) VALUES
(1, 2, 'Nguyễn Văn An', '0901234567', '120 Bạch Đằng, Hải Châu, Đà Nẵng'),
(2, 3, 'Lê Thị Bích', '0912345678', '45 Hàng Bài, Hoàn Kiếm, Hà Nội'),
(3, 4, 'Trần Quang Huy', '0987654321', '88 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh');

-- 3. Bảng properties (4 cơ sở: hotel & homestay ở Đà Nẵng, Hội An, Huế)
INSERT INTO `properties` (`id`, `name`, `type`, `address`, `city`, `description`, `amenities`, `image`, `status`) VALUES
(1, 'Danang Riverside Hotel', 'hotel', '258 Trần Hưng Đạo, Sơn Trà', 'Đà Nẵng', 'Khách sạn view trực diện sông Hàn và cầu Rồng, vị trí trung tâm thuận tiện tham quan thành phố.', 'Wifi miễn phí, Hồ bơi vô cực, Nhà hàng buffet, Thang máy, Lễ tân 24/7, Bãi đỗ xe ô tô', 'hotel_danang_riverside.jpg', 'active'),
(2, 'Itachi Home', 'homestay', 'Tổ 7, Khối An Tân, Biển An Bàng', 'Hội An', 'Itachi Home là homestay mang phong cách nghỉ dưỡng yên bình, ấm áp, ngập tràn cây xanh và không gian thư giãn tuyệt vời bên bãi biển An Bàng thơ mộng.', 'Wifi miễn phí, Bếp chung tự nấu ăn, Cho thuê xe đạp, Sân vườn BBQ, Gần bãi biển', 'itachi_home.jpg', 'active'),
(3, 'Hue Heritage Boutique Hotel', 'hotel', '12 Chu Văn An, Phú Hội', 'Huế', 'Khách sạn mang đậm phong cách kiến trúc Cố đô pha lẫn hiện đại, nằm ngay phố đi bộ Tây.', 'Wifi miễn phí, Ăn sáng miễn phí, Bể bơi trong nhà, Dịch vụ đón tiễn sân bay, Tour Cố Đô', 'hotel_hue_heritage.jpg', 'active'),
(4, 'Green Garden Homestay', 'homestay', '84 Lê Văn Hiến, Ngũ Hành Sơn', 'Đà Nẵng', 'Homestay sân vườn yên bình gần danh thắng Ngũ Hành Sơn, không gian rộng rãi thích hợp cho gia đình.', 'Wifi miễn phí, Sân chơi trẻ em, Bếp nướng ngoài trời, Chỗ để xe rộng, Cho phép thú cưng', 'homestay_green_garden.jpg', 'active');

-- 4. Bảng room_types
INSERT INTO `room_types` (`id`, `property_id`, `name`, `description`, `status`) VALUES
(1, 1, 'Standard City View', 'Phòng tiêu chuẩn hướng phố hiện đại, đầy đủ tiện nghi', 'active'),
(2, 1, 'Deluxe River View', 'Phòng cao cấp ngắm cầu Rồng và sông Hàn lãng mạn', 'active'),
(3, 2, 'Bungalow Vườn', 'Căn nhà gỗ nhỏ xinh giữa vườn hoa ngát hương', 'active'),
(4, 2, 'Family Sea Breeze', 'Phòng gia đình đón gió biển tự nhiên thoáng mát', 'active'),
(5, 3, 'Superior Classic', 'Phòng ấm cúng phong cách cung đình xưa', 'active'),
(6, 3, 'Deluxe Balcony', 'Phòng ban công thoáng đãng ngắm phố đi bộ', 'active'),
(7, 4, 'Cozy Studio', 'Studio khép kín có bếp nhỏ tiện lợi', 'active');

-- 5. Bảng rooms (~10 phòng)
INSERT INTO `rooms` (`id`, `property_id`, `room_type_id`, `room_number`, `capacity`, `price_per_night`, `amenities`, `image`, `status`) VALUES
-- Danang Riverside Hotel (ID: 1)
(1, 1, 1, '101', 2, 550000, 'Điều hòa, Tủ lạnh mini, TV 43 inch, Nước nóng, Máy sấy tóc', 'room_101.jpg', 'active'),
(2, 1, 1, '102', 2, 550000, 'Điều hòa, Tủ lạnh mini, TV 43 inch, Nước nóng, Bình đun nước', 'room_102.jpg', 'active'),
(3, 1, 2, '301', 2, 850000, 'View sông Hàn, Ban công riêng, Bồn tắm nằm, Smart TV 55 inch, Minibar', 'room_301.jpg', 'active'),
(4, 1, 2, '302', 3, 1050000, 'View sông Hàn, 1 giường đôi + 1 giường đơn, Ban công, Bồn tắm nằm, Minibar', 'room_302.jpg', 'active'),
-- An Bang Seaside Homestay (ID: 2)
(5, 2, 3, 'BG-01', 2, 450000, 'Quạt trần, Điều hòa, Phòng tắm lộ thiên nhiệt đới, Hiên trước phòng', 'room_bg01.jpg', 'active'),
(6, 2, 4, 'FAM-01', 4, 900000, '2 giường đôi lớn, Điều hòa, Bếp nhỏ, Tủ lạnh, Bàn trà ngắm biển', 'room_fam01.jpg', 'active'),
-- Hue Heritage Boutique Hotel (ID: 3)
(7, 3, 5, 'H-201', 2, 600000, 'Điều hòa, TV thông minh, Bàn làm việc gỗ sồi, Két sắt mini, Nước suối miễn phí', 'room_h201.jpg', 'active'),
(8, 3, 6, 'H-301', 2, 780000, 'Ban công rộng, Bồn tắm gỗ, Trà cung đình đón khách, Áo choàng lụa', 'room_h301.jpg', 'active'),
-- Green Garden Homestay (ID: 4)
(9, 4, 7, 'G-101', 2, 400000, 'Bếp từ, Nồi niêu cơ bản, Điều hòa, Máy giặt chung, Tủ lạnh', 'room_g101.jpg', 'active'),
(10, 4, 7, 'G-102', 4, 700000, 'Không gian rộng 45m2, 2 giường đôi, Điều hòa, Bếp nấu riêng, Bàn ăn gia đình', 'room_g102.jpg', 'active');

-- 6. Bảng bookings (Các trạng thái phong phú theo quy tắc)
INSERT INTO `bookings` (`id`, `booking_code`, `customer_id`, `room_id`, `check_in`, `check_out`, `nights`, `guests`, `contact_name`, `contact_phone`, `note`, `price_per_night`, `total_amount`, `status`, `reject_reason`, `created_at`) VALUES
-- Đơn hoàn thành (checked_out -> completed, kèm thanh toán)
(1, 'BK20261101-001', 1, 1, '2026-11-01', '2026-11-03', 2, 2, 'Nguyễn Văn An', '0901234567', 'Cần phòng tầng thấp', 550000, 1100000, 'completed', NULL, '2026-10-28 09:15:00'),
-- Đơn đang lưu trú (checked_in)
(2, 'BK20261115-002', 2, 3, '2026-11-15', '2026-11-18', 3, 2, 'Lê Thị Bích', '0912345678', 'Nhận phòng muộn lúc 19h', 850000, 2550000, 'checked_in', NULL, '2026-11-10 14:20:00'),
-- Đơn đã xác nhận (confirmed - giữ phòng)
(3, 'BK20261120-003', 3, 6, '2026-11-20', '2026-11-22', 2, 4, 'Trần Quang Huy', '0987654321', 'Đi cùng gia đình có 2 bé', 900000, 1800000, 'confirmed', NULL, '2026-11-12 11:00:00'),
-- Đơn chờ duyệt (pending)
(4, 'BK20261125-004', 1, 4, '2026-11-25', '2026-11-27', 2, 3, 'Nguyễn Văn An', '0901234567', 'Chuẩn bị thêm 1 gối', 1050000, 2100000, 'pending', NULL, '2026-11-16 16:30:00'),
-- Đơn bị từ chối (rejected)
(5, 'BK20261110-005', 2, 1, '2026-11-10', '2026-11-12', 2, 2, 'Lê Thị Bích', '0912345678', 'Cần hóa đơn VAT', 550000, 1100000, 'rejected', 'Phòng đang bảo trì đột xuất hệ thống nước', '2026-11-08 10:00:00'),
-- Đơn khách hủy (cancelled)
(6, 'BK20261130-006', 3, 9, '2026-11-30', '2026-12-02', 2, 2, 'Trần Quang Huy', '0987654321', 'Kèm xe máy', 400000, 800000, 'cancelled', NULL, '2026-11-14 08:45:00');

-- 7. Bảng payments (Chỉ đơn hoàn thành hoặc đã thanh toán)
INSERT INTO `payments` (`id`, `booking_id`, `amount`, `method`, `transaction_note`, `paid_at`) VALUES
(1, 1, 1100000, 'cash', 'Khách trả tiền mặt tại quầy lễ tân', '2026-11-03 11:30:00'),
(2, 2, 2550000, 'bank_transfer', 'Chuyển khoản Vietcombank - Mã GD: MB892711', '2026-11-15 14:00:00');

SET FOREIGN_KEY_CHECKS = 1;
