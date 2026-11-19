/**
 * JavaScript tiện ích nhỏ cho ItachiHome
 * 1. Xác nhận trước khi xóa hoặc hủy
 * 2. Tính nhanh số đêm và tổng tiền tạm tính trên form đặt phòng
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Xác nhận trước khi hủy đơn hoặc xóa dữ liệu
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(function (button) {
        button.addEventListener('click', function (e) {
            const message = button.getAttribute('data-confirm') || 'Bạn có chắc chắn muốn thực hiện thao tác này?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 2. Tính nhanh số đêm và tạm tính tổng tiền khi chọn ngày nhận/ngày trả
    const checkInInput = document.getElementById('booking_check_in');
    const checkOutInput = document.getElementById('booking_check_out');
    const pricePerNightInput = document.getElementById('room_price_per_night');
    const nightsDisplay = document.getElementById('display_nights');
    const totalDisplay = document.getElementById('display_total');

    function parseDateParts(str) {
        if (!str) return null;
        const p = str.split('-');
        if (p.length !== 3) return null;
        return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    }

    function calculateBooking() {
        if (!checkInInput || !checkOutInput || !pricePerNightInput) return;

        const checkInDate = parseDateParts(checkInInput.value);
        const checkOutDate = parseDateParts(checkOutInput.value);
        const pricePerNight = parseFloat(pricePerNightInput.value) || 0;

        if (checkInDate && checkOutDate && checkOutDate > checkInDate) {
            const diffTime = checkOutDate.getTime() - checkInDate.getTime();
            const nights = Math.round(diffTime / (1000 * 60 * 60 * 24));
            const total = nights * pricePerNight;

            if (nightsDisplay) nightsDisplay.textContent = nights + ' đêm';
            if (totalDisplay) {
                totalDisplay.textContent = new Intl.NumberFormat('vi-VN').format(total) + ' đ';
            }
        } else {
            if (nightsDisplay) nightsDisplay.textContent = '0 đêm';
            if (totalDisplay) totalDisplay.textContent = '0 đ';
        }
    }

    if (checkInInput && checkOutInput) {
        checkInInput.addEventListener('change', function() {
            if (checkInInput.value) {
                const nextDay = parseDateParts(checkInInput.value);
                if (nextDay) {
                    nextDay.setDate(nextDay.getDate() + 1);
                    const yyyy = nextDay.getFullYear();
                    const mm = String(nextDay.getMonth() + 1).padStart(2, '0');
                    const dd = String(nextDay.getDate()).padStart(2, '0');
                    const minCheckout = `${yyyy}-${mm}-${dd}`;
                    checkOutInput.min = minCheckout;
                    if (checkOutInput.value && checkOutInput.value <= checkInInput.value) {
                        checkOutInput.value = minCheckout;
                    }
                }
            }
            calculateBooking();
        });
        checkInInput.addEventListener('input', calculateBooking);
        checkOutInput.addEventListener('change', calculateBooking);
        checkOutInput.addEventListener('input', calculateBooking);
        // Chạy lần đầu khi load form
        calculateBooking();
    }
});
